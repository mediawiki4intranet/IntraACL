<?php

if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

/*

For small databases:

1) Materialize everything
2) On first request load ALL grants for current user
   Memory consumption: O(n_groups_of_user + n_rights_of_user)
3) => Every check is a SINGLE hash check without DB round-trips
4) Update complexity: O((n_parent_groups + n_parent_rights) * n_users_in_group)
   Most complex update is the update of a low-level group

For big databases:

1) Still materialize everything. It is unlikely that for a given definition
   there will be a very big number of other definitions that use it. So update
   complexity should be OK.
2) But there can be a big (and growing) number of individually protected pages a given user can access.
   So loading ALL grants for current user can be very memory consuming.
   In PHP [5.4], each single integer stored in an array takes ~80 bytes
   => 1000 indexed defs will take 80kb, and 10000 - 800kb.
3) So we cache only N recent definitions for current user (N is configurable),
   and starting with (N+1)th we make DB queries for each protected element.
   Also we pre-cache rules for embedded elements (image/template/category links).

'Manage rights' use cases:

1) Create right/group and then be able to edit it
2) Allow/restrict protecting of individual pages in namespace/category
3) Allow some users to edit ALL right definitions
   Probably solvable with MW right 'sysop'

*/

/**
 * 'Definition' is either a Security Descriptor or a Group
 *
 * SD may be either a right definition for some protected element
 * (page, category, namespace) or just a template suited for inclusion
 * into other SD(s).
 * SD can contain (action, user) and (action, group) grants and/or inclusions
 * of other SDs.
 *
 * Group may contain users and/or other groups as its members, and also
 * users and/or other groups as their managers.
 */

class IACL
{
    /**
     * Definition/child types
     */
    const PE_NAMESPACE  = 1;    // Namespace security descriptor, identified by namespace index
    const PE_CATEGORY   = 2;    // Category security descriptor, identified by category page ID
    const PE_RIGHT      = 3;    // Right template, identified by ACL definition (ACL:XXX) page ID
    const PE_PAGE       = 4;    // Page security descriptor, identified by page ID
    const PE_GROUP      = 5;    // Group, identified by group (ACL:Group/XXX) page ID
    const PE_USER       = 6;    // User, identified by user ID. Used only as child, not as definition (obviously)

    /**
     * Action/child relation details, stored as bitmap in rules table
     */
    const ACTION_READ           = 0x01;     // Allows to read pages
    const ACTION_EDIT           = 0x02;     // Allows to edit pages. Implies read right
    const ACTION_CREATE         = 0x04;     // Allows to create articles in the namespace
    const ACTION_MOVE           = 0x08;     // Allows to move pages with history
    const ACTION_DELETE         = 0x10;     // Allows to delete pages with history
    const ACTION_MANAGE         = 0x20;     // Allows to modify right definition or group. Implies read/edit/create/move/delete rights
    const ACTION_PROTECT_PAGES  = 0x80;     // Allows to modify affected page right definitions. Implies read/edit/create/move/delete pages
    const ACTION_GROUP_MEMBER   = 0x01;     // Used in group definitions: specifies that the child is a group member

    /**
     * Bit offset of indirect rights in 'actions' column
     * I.e., 8 means higher byte is for indirect rights
     */
    const INDIRECT_OFFSET       = 8;

    const ALL_USERS             = 0;
    const REGISTERED_USERS      = -1;
}

class IACLDefinition implements ArrayAccess
{
    // Definition has no DB row by itself as it would be degenerate
    var $row = array();             // Only for pe_type and pe_id, not stored in the DB
    var $add = array();             // All additional data
    var $collection;                // Remembered mass-fetch collection
    var $rw;                        // Is this a read-write (dirty) copy?
    static $clean = array();        // Clean object cache
    static $dirty = array();        // Dirty object cache

    function newEmpty()
    {
        $this->rw = true;
    }

    function offsetGet($k)
    {
        if (isset($this->add[$k]))
        {
            return $this->add[$k];
        }
        $m = 'get_'.$k;
        return $this->$m();
    }

    function offsetSet($k, $v)
    {
        if (!$this->rw)
        {
            $this->makeDirty();
        }
        if ($k == 'child_ids')
        {
            unset($this->add['children']);
        }
        return $this->add[$k] = $v;
    }

    function offsetExists($k)
    {
        return $k == 'pe_id' || $k == 'pe_type' || isset($this->add[$k]) || method_exists($this, 'get_'.$k);
    }

    function offsetUnset($k)
    {
        return $this->offsetSet($k, NULL);
    }

    /**
     * $where['pe'] = array(array(<pe_type>, <pe_id>), ...)
     */
    static function select($where, $options = array())
    {
        $byid = array();
        if (isset($where['pe']))
        {
            $pe = $where['pe'];
            unset($where['pe']);
            if (!is_array(@$pe[0]))
            {
                $pe = array($pe);
            }
            foreach ($pe as $i => $id)
            {
                $key = $id[0].'-'.$id[1];
                if (isset(self::$clean[$key]))
                {
                    $byid[$key] = self::$clean[$key];
                    unset($pe[$i]);
                }
            }
            if (!$pe)
            {
                // All objects already fetched from cache
                return $byid;
            }
            $where['(pe_type, pe_id)'] = $pe;
        }
        $rules = IACLStorage::get('SD')->getRules($where);
        $coll = array();
        foreach ($rules as $rule)
        {
            $key = $rule['pe_type'].'-'.$rule['pe_id'];
            if (!isset($byid[$key]))
            {
                self::$clean[$key] = $coll[$key] = $byid[$key] = $obj = new self();
                $obj->add['pe_type'] = $rule['pe_type'];
                $obj->add['pe_id'] = $rule['pe_id'];
                $obj->collection = &$coll;
            }
            else
            {
                $obj = $byid[$key];
            }
            $obj->add['rules'][$rule['child_type']][$rule['child_id']] = $rule;
        }
        return $coll;
    }

    // Parent SDs are SDs that include this SD
    protected function get_parents()
    {
        $sds = $this->collection ?: array($this->row['sd_id'] => $this);
        $ids = array();
        foreach ($sds as $sd)
        {
            if (!isset($sd->add['parents']))
            {
                $ids[] = $sd->row['sd_id'];
                $sd->add['parents'] = array();
            }
        }
        $rules = IACLStorage::get('SD')->getRules(array(
            'rule_type' => self::RULE_SD,
            'child_id'  => $ids,
            'is_direct' => 1,
        ));
        $ids = array();
        foreach ($rules as $r)
        {
            $ids[$r['sd_id']][] = $r['child_id'];
        }
        $parents = self::select(array('sd_id' => array_keys($ids)));
        foreach ($parents as $parent)
        {
            foreach ($ids[$parent['sd_id']] as $child)
            {
                $sds[$child]->add['parents'][$parent['sd_id']] = $parent;
            }
        }
        return $this->add['parents'];
    }

    // Child SDs are SDs included by this SD
    protected function get_children()
    {
        $ids = array();
        foreach ($sds as $sd)
        {
            if ($sd->add['children'] === NULL)
            {
                $ids += $sd['child_ids'];
            }
        }
        $children = self::select(array('sd_id' => $ids));
        foreach ($sds as $sd)
        {
            if ($sd->add['children'] === NULL)
            {
                $sd->add['children'] = array();
                foreach ($sd['child_ids'] as $child_id => $true)
                {
                    if (isset($children[$child_id]))
                    {
                        $sd->add['children'][$child_id] = $children[$child_id];
                    }
                }
            }
        }
        return $this->add['children'];
    }

    // Returns array(user_id => rule)
    protected function get_user_rights()
    {
        return $this['rules'][IACL::PE_USER];
    }

    // Returns array(group_id => rule)
    protected function get_group_rights()
    {
        return $this['rules'][IACL::PE_GROUP];
    }

    // Returns array('<type>-<id>' => rule)
    protected function get_child_ids()
    {
        $r = $this['rules'];
        $res = array();
        foreach (array(IACL::PE_CATEGORY, IACL::PE_PAGE, IACL::PE_NAMESPACE, IACL::PE_RIGHT) as $k)
        {
            if (isset($r[$k]))
            {
                foreach ($r[$k] as $sd => $rule)
                {
                    $res["$k-$sd"] = $rule;
                }
            }
        }
        return $res;
    }

    /**
     * Checks whether this SD only includes SINGLE predefined right and
     * does not include any inline rights or manage template rights.
     * If so, the ID of this single predefined right is returned.
     * If not, NULL is returned.
     */
    protected function get_single_child()
    {
        if (!$this['user_rights'] &&
            !$this['group_rights'] &&
            count($i = $this['child_ids']) == 1)
        {
            return reset($i);
        }
        return NULL;
    }

    function makeDirty()
    {
        if (!$this->rw)
        {
            self::$dirty[$this->row['sd_id']] = $this;
            self::$clean[$this->row['sd_id']] = clone $this;
            $this->collection = NULL;
            $this->rw = true;
        }
    }

    function dirty()
    {
        if ($this->rw)
        {
            return $this;
        }
        elseif (!isset(self::$dirty[$this->row['sd_id']]))
        {
            self::$dirty[$this->row['sd_id']] = clone $this;
            self::$dirty[$this->row['sd_id']]->rw = true;
            self::$dirty[$this->row['sd_id']]->collection = NULL;
        }
        return self::$dirty[$this->row['sd_id']];
    }

    function clean()
    {
        if ($this->rw)
        {
            if (isset(self::$clean[$this->row['sd_id']]))
            {
                return self::$clean[$this->row['sd_id']];
            }
            return false;
        }
        return $this;
    }

    /**
     * Check (with caching) if a given user is granted some action
     */
    static function userCan($userID, $peType, $peID, $actionID)
    {
        static $userCache = array();
        static $incomplete = array();
        if (!isset($userCache[$userID]))
        {
            global $iaclPreloadLimit;
            // Prefer more general (pe_type ASC) and more recent (pe_id DESC) rules when preloading
            // IACL::REGISTERED_USERS entry acts as a default access level entry
            // TODO Maybe exclude groups till $peType != group? (because it usually has no effect on permission check speed)
            $rules = IACLStorage::get('SD')->getRules(
                array(
                    'child_type' => IACL::PE_USER,
                    'child_id' => array($userID, IACL::REGISTERED_USERS)
                ),
                array(
                    'LIMIT' => $iaclPreloadLimit,
                    'ORDER BY' => 'pe_type ASC, pe_id DESC, child_id ASC'
                )
            );
            $incomplete[$userID] = count($rules) >= $iaclPreloadLimit;
            $userCache[$userID] = array();
            foreach ($rules as $rule)
            {
                $userCache[$userID][$rule['pe_type']][$rule['pe_id']] = $rule['actions'];
            }
        }
        if ($incomplete[$userID] && !isset($userCache[$userID][$peType][$peID]))
        {
            $rules = IACLStorage::get('SD')->getRules(array(
                'pe_type' => $peType,
                'pe_id' => $peID,
                'child_type' => IACL::PE_USER,
                'child_id' => array($userID, IACL::REGISTERED_USERS),
            ));
            foreach ($rules as $rule)
            {
                if ($rule['child_id'] == $userID)
                {
                    $userCache[$userID][$peType][$peID] = $rule[0]['actions'];
                    break;
                }
            }
        }
        return isset($userCache[$userID][$peType][$peID]) &&
            ($userCache[$userID][$peType][$peID] & $actionID);
    }

    /**
     * Returns the ID of a protection object that is given by its name.
     * The ID depends on the type.
     *
     * @param  string $peName   Object name
     * @param  int $peType      Object type (IACL::PE_*)
     * @return int/bool         Object id or <false> if it does not exist
     */
    public static function peIDforName($peName, $peType)
    {
        $ns = NS_MAIN;
        if ($peType === IACL::PE_NAMESPACE)
        {
            // $peName is a namespace => get its ID
            global $wgContLang;
            $peName = str_replace(' ', '_', trim(str_replace('_', ' ', $peName)));
            $idx = $wgContLang->getNsIndex($peName);
            if ($idx == false)
                return (strtolower($peName) == 'main') ? 0 : false;
            return $idx;
        }
        elseif ($peType === IACL::PE_RIGHT)
            $ns = NS_ACL;
        elseif ($peType === IACL::PE_CATEGORY)
            $ns = NS_CATEGORY;
        elseif ($peType === IACL::PE_USER)
            $ns = NS_USER;
        elseif ($peType === IACL::PE_GROUP)
            $ns = NS_ACL;
        // Return the page id
        // TODO add caching here
        $id = haclfArticleID($peName, $ns);
        return $id == 0 ? false : $id;
    }

    /**
     * Tries to get definition by its composite ID (type, ID).
     *
     * @param  int $peID    ID of the protected element
     * @param  int $peType  Type of the protected element
     * @return object|bool  Definition object or <false> if it does not exist
     */
    public static function getSDForPE($peID, $peType)
    {
        $r = self::select(array('pe' => array($peType, $peID)));
        return $r ? $r[0] : false;
    }

    /**
     * Determine protected element name and type by definition page title
     *
     *  ACL:Page/<Page title>               PE_PAGE
     *  ACL:Category/<Category name>        PE_CATEGORY
     *  ACL:Namespace/<Namespace name>      PE_NAMESPACE
     *  ACL:Namespace/Main
     *  ACL:Group/<Group name>              PE_GROUP
     *  ACL:<Right template name>           PE_RIGHT
     *
     * @param string $defTitle  Definition title, with or without ACL: namespace
     * @return array(string $name, int $type)   Name of the protected element and its type.
     */
    public static function nameOfPE($defTitle)
    {
        global $wgContLang, $haclgContLang;
        // Ignore the namespace
        $ns = $wgContLang->getNsText(HACL_NS_ACL).':';
        if (strpos($defTitle, $ns) === 0)
        {
            $defTitle = substr($defTitle, strlen($ns));
        }
        $p = strpos($defTitle, '/');
        if (!$p)
        {
            return array($defTitle, IACL::PE_RIGHT);
        }
        $prefix = substr($defTitle, 0, $p);
        $type = $haclgContLang->getPetAlias($prefix);
        if ($type != IACL::PE_RIGHT)
        {
            $peName = substr($defTitle, $p+1);
            return array($peName, $type);
        }
        // Right by default
        return array($defTitle, IACL::PE_RIGHT);
    }

    /**
     * Determine ACL definition page title by protected element type and name
     *
     * @param string $nameOfPE  PE name
     * @param string $peType    PE type
     * @return string $defTitle Definition title
     */
    public static function nameOfSD($nameOfPE, $peType)
    {
        global $wgContLang, $haclgContLang;
        $defTitle = $wgContLang->getNsText(HACL_NS_ACL).':';
        $prefix = $haclgContLang->getPetPrefix($peType);
        if ($prefix)
        {
            $defTitle .= $prefix.'/';
        }
        return $defTitle . $nameOfPE;
    }

    /**
     * Saves this definition into database
     */
    public function save($cascade = true)
    {
        // Load ID and parents before saving, as the definition may be deleted next
        $parents = $this->getDirectParents();
        $peType = $this['pe_type'];
        $peID = $this['pe_id'];
        $st = IACLStorage::get('SD');
        if (!$this->exists)
        {
            $this->add = $this->row = array();
            $delRules = array(array('pe_type' => $peType, 'pe_id' => $peID));
            $addRules = array();
        }
        else
        {
            if (isset($this->add['user_rights']) ||
                isset($this->add['group_rights']) ||
                isset($this->add['child_ids']))
            {
                list($delRules, $addRules) = $this->diffRules();
                if ($oldRules)
                {
                    $st->deleteRules($oldRules);
                }
                if ($addRules)
                {
                    $st->addRules($addRules);
                }
            }
        }
        // Commit new state into cache
        self::$clean[$id] = $this;
        unset(self::$dirty[$id]);
        // Invalidate parents - they will do the same recursively for their parents and so on
        $preventLoop[$id] = true;
        foreach ($parents as $p)
        {
            if (!isset($preventLoop[$p['sd_id']]))
            {
                $p->save($preventLoop);
            }
        }
        // TODO Invalidate cache (if any)
    }

    protected function diffRules()
    {
        $oldRules = $this->clean()['rules'];
        $addRules = $this->add['rules'] = $this->buildRules();
        foreach ($oldRules as $k => $rule)
        {
            if (isset($addRules[$k]) && $addRules[$k]['actions'] == $rule['actions'])
            {
                unset($addRules[$k]);
                unset($oldRules[$k]);
            }
        }
        return array($oldRules, $addRules);
    }

    protected function buildRules()
    {
        $rules = array();
        if (!empty($this->add['user_rights']))
        {
            foreach ($this->add['user_rights'] as $action => $users)
            {
                foreach ($users as $user => $true)
                {
                    $rules[self::RULE_USER.'-'.$action.'-'.$user] = array(
                        'sd_id'     => $this->row['sd_id'],
                        'rule_type' => self::RULE_USER,
                        'action_id' => $action,
                        'child_id'  => $user,
                        'is_direct' => 1,
                    );
                    unset($oldHash[self::RULE_USER.'-'.$action.'-'.$user]);
                }
            }
        }
        if (!empty($this->add['group_rights']))
        {
            foreach ($this->add['group_rights'] as $action => $groups)
            {
                foreach ($groups as $group => $true)
                {
                    $rules[self::RULE_GROUP.'-'.$action.'-'.$group] = array(
                        'sd_id'     => $this->row['sd_id'],
                        'rule_type' => self::RULE_GROUP,
                        'action_id' => $action,
                        'child_id'  => $group,
                        'is_direct' => 1,
                    );
                }
            }
        }
        if (!empty($this->add['child_ids']))
        {
            $children = self::select(array('sd_id' => array_keys($this->add['child_ids'])));
            $this->add['child_ids'] = $children;
            foreach ($this->add['child_ids'] as $sdid => &$sd)
            {
                $childRules = $sd['rules'];
                foreach ($childRules as $key => $rule)
                {
                    if (!isset($rules[$key]))
                    {
                        $rule['sd_id'] = $this->row['sd_id'];
                        $rule['is_direct'] = 0;
                        $rules[$key] = $rule;
                    }
                }
                $rules[self::RULE_SD.'-0-'.$sdid] = array(
                    'sd_id'     => $this->row['sd_id'],
                    'rule_type' => self::RULE_SD,
                    'action_id' => 0,
                    'child_id'  => $sdid,
                    'is_direct' => 1,
                );
                // Set to true so child_rules will still be (SD => true)
                $sd = true;
            }
        }
        return $rules;
    }

    /**
     * This method checks the integrity of this SD. The integrity can be violated
     * by missing groups, users or predefined rights.
     *
     * return mixed bool / array
     *     <true> if the SD is valid,
     *  array(string=>bool) otherwise
     *         The array has the keys "groups", "users" and "rights" with boolean values.
     *         If the value is <true>, at least one of the corresponding entities
     *         is missing.
     */
    public function checkIntegrity()
    {
        $missingGroups = false;
        $missingUsers = false;
        $missingPR = false;

        //== Check integrity of group managers ==

        // Check for missing groups
        foreach ($this->mManageGroups as $gid) {
            if (!IACLStorage::get('Groups')->groupExists($gid)) {
                $missingGroups = true;
                break;
            }
        }

        // Check for missing users
        foreach ($this->mManageUsers as $uid) {
            if ($uid > 0 && User::whoIs($uid) === false) {
                $missingUsers = true;
                break;
            }
        }


        //== Check integrity of inline rights ==

        $irIDs = $this->getInlineRights(false);
        foreach ($irIDs as $irID) {
            $ir = IACLStorage::get('IR')->getRightByID($irID);
            $groupIDs = $ir->getGroups();
            // Check for missing groups
            foreach ($groupIDs as $gid) {
                if (!IACLStorage::get('Groups')->groupExists($gid)) {
                    $missingGroups = true;
                    break;
                }
            }
            // Check for missing users
            $userIDs = $ir->getUsers();
            foreach ($userIDs as $uid) {
                if ($uid > 0 && User::whoIs($uid) === false) {
                    $missingUsers = true;
                    break;
                }
            }
        }

        // Check for missing predefined rights
        $prIDs = $this->getPredefinedRights(false);
        foreach ($prIDs as $prid) {
            if (!IACLStorage::get('SD')->sdExists($prid)) {
                $missingPR = true;
                break;
            }
        }

        if (!$missingGroups && !$missingPR && !$missingUsers) {
            return true;
        }
        return array('groups' => $missingGroups,
                     'users'  => $missingUsers,
                     'rights' => $missingPR);
    }
}
