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
    const PE_CATEGORY   = 1;    // Category security descriptor, identified by category page ID
    const PE_PAGE       = 2;    // Page security descriptor, identified by page ID
    const PE_NAMESPACE  = 3;    // Namespace security descriptor, identified by namespace index
    const PE_RIGHT      = 4;    // Right template, identified by ACL definition (ACL:XXX) page ID
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
}

class IACLDefinition implements ArrayAccess
{
    // Definition has no DB row itself as it would be degenerate

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
        if (isset($this->row[$k]))
        {
            // sd_id, pe_id, type, sd_data
            return $this->row[$k];
        }
        elseif (isset($this->add[$k]))
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
        if ($k == 'sd_id' || $k == 'pe_id' || $k == 'type')
        {
            return $this->row[$k] = $v;
        }
        elseif ($k == 'child_ids')
        {
            unset($this->add['children']);
        }
        return $this->add[$k] = $v;
    }

    function offsetExists($k)
    {
        return isset($this->row[$k]) || isset($this->add[$k]) || method_exists($this, 'get_'.$k);
    }

    function offsetUnset($k)
    {
        return $this->offsetSet($k, NULL);
    }

    static function select($where, $options = array())
    {
        $byid = array();
        if (isset($where['sd_id']))
        {
            $where['sd_id'] = (array)$where['sd_id'];
            foreach ($where['sd_id'] as $k => $id)
            {
                if (isset(self::$clean[$id]))
                {
                    $byid[$id] = self::$clean[$id];
                    unset($where['sd_id'][$k]);
                }
            }
            if (!$where['sd_id'])
            {
                // All objects already fetched from cache
                return $byid;
            }
        }
        $rows = IACLStorage::get('SD')->getSDs($where, $options);
        foreach ($rows as $row)
        {
            self::$clean[$row->sd_id] = $byid[$row->sd_id] = $obj = new self($row);
            $obj->collection = &$byid;
        }
        return $byid;
    }

    // TODO use instead of concatenated keys
    static function ruleKey($rule, $action = NULL, $child = NULL)
    {
        if (is_array($rule))
        {
            return pack('CCV', $rule['rule_type'], $rule['action_id'], $rule['child_id']);
        }
        return pack('CCV', $rule, $action, $child);
    }

    // Get rules
    protected function get_rules()
    {
        $sds = $this->collection ?: array($this->row['sd_id'] => $this);
        $ids = array();
        foreach ($sds as $sd)
        {
            if (!isset($sd->add['rules']))
            {
                $ids[] = $sd->row['sd_id'];
                $sd->add['rules'] = array();
                $sd->add['user_rights'] = array();
                $sd->add['group_rights'] = array();
                $sd->add['child_ids'] = array();
            }
        }
        $rules = IACLStorage::get('SD')->getRules(array('sd_id' => $ids));
        foreach ($rules as $rule)
        {
            $a = &$sds[$rule['sd_id']]->add;
            $a['rules'][$rule['rule_type'].'-'.$rule['action_id'].'-'.$rule['child_id']] = $rule;
            if ($rule['rule_type'] == self::RULE_USER)
            {
                $a['user_rights'][$rule['action_id']][$rule['child_id']] = $rule['is_direct'];
            }
            elseif ($rule['rule_type'] == self::RULE_GROUP)
            {
                $a['group_rights'][$rule['action_id']][$rule['child_id']] = $rule['is_direct'];
            }
            elseif ($rule['rule_type'] == self::RULE_SD)
            {
                $a['child_ids'][$rule['child_id']] = $rule['is_direct'];
            }
        }
        return $this->add['rules'];
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

    // Returns array(action_id => array(user_id => true))
    protected function get_user_rights()
    {
        $this['rules'];
        return $this->add['user_rights'];
    }

    // Returns array(action_id => array(group_id => true))
    protected function get_group_rights()
    {
        $this['rules'];
        return $this->add['group_rights'];
    }

    // Returns array(child_id => true)
    protected function get_child_ids()
    {
        $this['rules'];
        return $this->add['child_ids'];
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
     * Check if the given user is granted some action
     */
    function userCan($userID, $actionID)
    {
        if (isset($this['user_rights'][$actionID][$userID]))
        {
            return true;
        }
        ??? user groups
        foreach ($groups as $gid)
        {
            if (isset($this['group_rights'][$actionID][$userID]))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the ID of a protected element that is given by its name. The ID
     * depends on the type of the protected element:
     * - PET_PAGE: ID of the article that is protected
     * - PET_NAMESPACE: ID of the namespace that is protected
     * - PET_CATEGORY: ID of the category article whose instances are protected
     * - PET_RIGHT: not applicable
     *
     * @param  string $peName
     *         Name of the protected object. Category:XXX for categories.
     * @param  int $peType
     *         Type of the protected element. See HACLLanguage::PET_*
     * @return int/bool
     *         ID of the protected element or <false>, if it does not exist.
     */
    public static function peIDforName($peName, $peType)
    {
        $ns = NS_MAIN;
        if ($peType === HACLLanguage::PET_NAMESPACE)
        {
            // $peName is a namespace => get its ID
            global $wgContLang;
            $peName = str_replace(' ', '_', trim(str_replace('_', ' ', $peName)));
            $idx = $wgContLang->getNsIndex($peName);
            if ($idx == false)
                return (strtolower($peName) == 'main') ? 0 : false;
            return $idx;
        }
        elseif ($peType === HACLLanguage::PET_RIGHT)
            return 0;
        elseif ($peType === HACLLanguage::PET_CATEGORY)
            $ns = NS_CATEGORY;
        // return the page id
        $id = haclfArticleID($peName, $ns);
        return $id == 0 ? false : $id;
    }

    /**
     * Tries to find the ID of the security descriptor for the protected element
     * with the ID $peID.
     *
     * @param  int $peID
     *         ID of the protected element
     *
     * @param  int $peType
     *         Type of the protected element
     *
     * @return mixed int|bool
     *         int: ID of the security descriptor
     *         <false>, if there is no SD for the protected element
     */
    public static function getSDForPE($peID, $peType)
    {
        $r = self::select(array('pe_id' => $peID, 'type' => $peType));
        return $r ? $r[0] : false;
    }

    /**
     * The name of the security descriptor determines which element it protects.
     * This method returns the name and type of the element that is protected
     * by the security descriptor with the name $nameOfSD.
     *
     * @param string $nameOfSD
     *         Name of the security descriptor that protects an element (with or
     *         without namespace).
     *
     * @return array(string, string)
     *         Name of the protected element and its type (one of HACLLanguage::PET_CATEGORY
     *         etc). It the type is HACLLanguage::PET_RIGHT, the name is <null>.
     */
    public static function nameOfPE($nameOfSD)
    {
        global $wgContLang, $haclgContLang;
        $ns = $wgContLang->getNsText(HACL_NS_ACL).':';

        // Ignore the namespace
        if (strpos($nameOfSD, $ns) === 0)
            $nameOfSD = substr($nameOfSD, strlen($ns));

        $p = strpos($nameOfSD, '/');
        if (!$p)
            return array(NULL, HACLLanguage::PET_RIGHT);

        $prefix = substr($nameOfSD, 0, $p);
        if ($type = $haclgContLang->getPetAlias($prefix))
        {
            $peName = substr($nameOfSD, $p+1);
            if ($type == HACLLanguage::PET_CATEGORY)
                $peName = $wgContLang->getNsText(NS_CATEGORY).':'.$peName;
            elseif ($type == HACLLanguage::PET_RIGHT)
                $peName = NULL;
            return array($peName, $type);
        }

        // Right by default
        return array(NULL, 'right');
    }

    /**
     * The name of the protected element and its type determine the name of
     * its security descriptor.
     * This method returns the complete name of the SD (with namespace) for a
     * given protected element.
     *
     * @param string $nameOfPE
     *         The full name of the protected element
     * @param string $peType
     *         The type of the protected element which is one of HACLLanguage::PET_*
     *
     * @return array(string, string)
     *         Name of the protected element and its type (one of HACLLanguage::PET_*
     *         etc). It the type is HACLLanguage::PET_RIGHT, the name is <null>.
     */
    public static function nameOfSD($nameOfPE, $peType)
    {
        global $wgContLang, $haclgContLang;
        $ns = $wgContLang->getNsText(HACL_NS_ACL).':';
        $prefix = $haclgContLang->getPetPrefix($peType).'/';
        $sdName = $ns.$prefix.$nameOfPE;
        return $sdName;
    }

    /**
     * Saves this SD into database
     */
    public function save(&$preventLoop = array())
    {
        // Save ID and parents before saving, as the SD may be deleted
        $parents = $this->getDirectParents();
        $id = $this['sd_id'];
        $st = IACLStorage::get('SD');
        if (!$this->exists)
        {
            $st->deleteRules(array('sd_id' => $this->row['sd_id']));
            $st->deleteSDs(array('sd_id' => $this->row['sd_id']));
            $this->add = $this->row = array();
        }
        else
        {
            $st->replaceSDs(array(
                'sd_id' => $this->row['sd_id'],
                'type'  => $this->row['type'],
                'pe_id' => $this->row['pe_id'],
            ));
            if (isset($this->add['user_rights']) ||
                isset($this->add['group_rights']) ||
                isset($this->add['child_ids']))
            {
                $oldRules = $this->clean()['rules'];
                $addRules = $this->add['rules'] = $this->buildRules();
                foreach ($oldRules as $k => $rule)
                {
                    if (isset($addRules[$k]['is_direct']) && $addRules[$k]['is_direct'] == $rule['is_direct'])
                    {
                        unset($addRules[$k]);
                        unset($oldRules[$k]);
                    }
                }
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
