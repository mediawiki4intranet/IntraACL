<?php

/**
 * Copyright 2013+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *                  Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 * This file is part of IntraACL MediaWiki extension. License: GPLv3.
 * Homepage: http://wiki.4intra.net/IntraACL
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('MEDIAWIKI'))
{
    die("This file is part of the IntraACL extension. It is not a valid entry point.");
}

/**
 * 'Definition' is either a Security Descriptor or a Group
 *
 * SD may be either a right definition for some protected element
 * (page, category, namespace) or just a template suited for inclusion
 * into other SD(s).
 *
 * SD may contain (action, user) and (action, group) grants and/or inclusions
 * of other SDs.
 *
 * Group may contain users and/or other groups as its members, and also
 * users and/or other groups as their managers.
 */

class IACLDefinition implements ArrayAccess
{
    // Definition has no DB row by itself as it would be degenerate
    var $data = array();            // Object data
    var $collection;                // Remembered mass-fetch collection
    var $rw;                        // Is this a read-write (dirty) copy?
    static $clean = array();        // Clean object cache
    static $dirty = array();        // Dirty object cache

    /**
     * Per-user cache of allowed actions
     * array($userID => array($peType => array($peID => <actions bitmask>)))
     */
    static $userCache = array();

    /**
     * Loading state of the per-user cache
     * array($userID => <bitmask>)
     *
     * 0x01 => SDs preloaded
     * 0x02 => Groups preloaded
     * 0x04 => SDs incomplete
     * 0x08 => Groups incomplete
     */
    static $userCacheLoaded = array();

    protected static function newEmpty($type, $id)
    {
        $self = new self();
        $self->rw = true;
        $self->data['pe_type'] = $type;
        $self->data['pe_id'] = $id;
        $self->data['rules'] = array();
        return $self;
    }

    /**
     * Returns non-empty definitions by their page titles, indexed by full title texts
     *
     * @param array(Title|string) $titles
     * @return array(Title => IACLDefinition)
     */
    static function newFromTitles($titles)
    {
        $where = array();
        if (!is_array($titles))
        {
            $titles = array($titles);
        }
        foreach ($titles as &$k)
        {
            // FIXME: resolve multiple IDs at once
            // id = get_id(name, type)
            $pe = self::nameOfPE($k);
            if ($pe)
            {
                $id = self::peIDforName($pe[0], $pe[1]);
                if ($id !== NULL)
                {
                    $where[] = array($pe[0], $id);
                }
                $k = array($pe[0], $pe[1], $id, "$k");
            }
        }
        $defs = self::select(array('pe' => $where));
        $r = array();
        foreach ($titles as &$k)
        {
            if (is_array($k) && $k[2] !== NULL)
            {
                $r[$k[3]] = isset($defs[$k[0].'-'.$k[2]]) ? $defs[$k[0].'-'.$k[2]] : NULL;
            }
        }
        return $r;
    }

    static function newFromTitle(Title $title, $allowEmpty = true)
    {
        $pe = self::nameOfPE($title);
        if ($pe)
        {
            return self::newFromName($pe[0], $pe[1], $allowEmpty);
        }
        return NULL;
    }

    /**
     * Returns an existing or a non-existing definition for $peType, $peName
     */
    static function newFromName($peType, $peName, $allowEmpty = true)
    {
        $id = self::peIDforName($peType, $peName);
        if ($id !== NULL)
        {
            $def = self::select(array('pe' => array($peType, $id)));
            if ($def)
            {
                $def = reset($def);
            }
            elseif ($allowEmpty)
            {
                $def = self::newEmpty($peType, $id);
            }
            return $def;
        }
        return false;
    }

    function offsetGet($k)
    {
        if (isset($this->data[$k]))
        {
            return $this->data[$k];
        }
        $m = 'get_'.$k;
        // Crash with "unknown method" error on unknown field request
        return $this->$m();
    }

    function offsetSet($k, $v)
    {
        if (!$this->rw)
        {
            $this->makeDirty();
        }
        if ($k == 'pe_id' || $k == 'pe_type' || $k == 'rules')
        {
            // FIXME unset(children, parents, etc)
            return $this->data[$k] = $v;
        }
        throw new Exception(__CLASS__.': Trying to set unknown field: '.$k);
    }

    function offsetExists($k)
    {
        return $k == 'pe_id' || $k == 'pe_type' || isset($this->data[$k]) || method_exists($this, 'get_'.$k);
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
            if (!$pe)
            {
                return array();
            }
            if (!empty($pe[0]) && !is_array($pe[0]))
            {
                $pe = array($pe);
            }
            foreach ($pe as $i => $id)
            {
                $key = $id[0].'-'.$id[1];
                if (isset(self::$clean[$key]))
                {
                    if (self::$clean[$key])
                    {
                        $byid[$key] = self::$clean[$key];
                    }
                    unset($pe[$i]);
                }
            }
            if (!$pe)
            {
                // All objects already fetched from cache
                return $byid;
            }
            foreach ($pe as &$p)
            {
                $p = '('.intval($p[0]).', '.intval($p[1]).')';
            }
            $where[] = '(pe_type, pe_id) IN ('.implode(', ', $pe).')';
        }
        $rules = IACLStorage::get('SD')->getRules($where);
        // $coll is a separate array so we include into $object->collection
        // only items actually fetched from the DB, not from the cache
        $coll = array();
        foreach ($rules as $rule)
        {
            $key = $rule['pe_type'].'-'.$rule['pe_id'];
            if (!isset(self::$clean[$key]))
            {
                self::$clean[$key] = $coll[$key] = $byid[$key] = $obj = new self();
                $obj->data['pe_type'] = $rule['pe_type'];
                $obj->data['pe_id'] = $rule['pe_id'];
                $obj->collection = &$coll;
            }
            else
            {
                $obj = self::$clean[$key];
            }
            $obj->data['rules'][$rule['child_type']][$rule['child_id']] = $rule;
        }
        return $byid;
    }

    protected function get_key()
    {
        return $this->data['pe_type'].'-'.$this->data['pe_id'];
    }

    /**
     * Get definitions that directly include this one with any granted action.
     * Fetches them massively.
     */
    protected function get_parents()
    {
        $sds = $this->collection ? $this->collection : array($this['key'] => $this);
        $ids = array();
        foreach ($sds as $sd)
        {
            if (!isset($sd->data['parents']))
            {
                $ids[] = '('.$sd->data['pe_type'].', '.$sd->data['pe_id'].')';
                $sd->data['parents'] = array();
            }
        }
        $ids = implode(', ', $ids);
        $rules = IACLStorage::get('SD')->getRules(array(
            "(child_type, child_id) IN ($ids)",
            '(actions & '.((1 << IACL::INDIRECT_OFFSET) - 1).') != 0',
        ));
        $ids = array();
        $keys = array();
        foreach ($rules as $r)
        {
            $ids[$r['pe_type'].'-'.$r['pe_id']] = array($r['pe_type'], $r['pe_id']);
            $keys[$r['pe_type'].'-'.$r['pe_id']][] = $r['child_type'].'-'.$r['child_id'];
        }
        $parents = self::select(array('pe' => array_values($ids)));
        foreach ($parents as $parent)
        {
            foreach ($keys[$key = $parent['pe_type'].'-'.$parent['pe_id']] as $child_key)
            {
                $sds[$child_key]->data['parents'][$key] = $parent;
            }
        }
        return $this->data['parents'];
    }

    /**
     * Checks whether this SD only includes a SINGLE predefined right and
     * does not include any inline rights (except {{#manage rights}} = ACTION_MANAGE).
     * If so, (Type, ID) pair for this single predefined right is returned.
     * If not, NULL is returned.
     *
     * @return array($peType, $peId) Identifier of child SD or NULL
     */
    protected function get_single_child()
    {
        $direct = ((1 << IACL::INDIRECT_OFFSET) - 1) & ~IACL::ACTION_MANAGE;
        $single = NULL;
        foreach ($this['rules'] as $type => $rules)
        {
            foreach ($rules as $id => $actions)
            {
                $actions = is_array($actions) ? $actions['actions'] : $actions;
                if ($type == IACL::PE_USER ||
                    $type == IACL::PE_GROUP ||
                    $type == IACL::PE_ALL_USERS ||
                    $type == IACL::PE_REG_USERS ||
                    ($actions & $direct) != IACL::ACTION_INCLUDE_SD)
                {
                    if ($actions & $direct)
                    {
                        return NULL;
                    }
                    // Do not return for empty actions
                }
                else
                {
                    $single = array($type, $id);
                }
            }
        }
        return $single;
    }

    /**
     * Returns definition page title (programmatically, without DB access)
     *
     * @return Title
     */
    protected function get_def_title()
    {
        return self::getSDTitle(array($this->data['pe_type'], $this->data['pe_id']));
    }

    /**
     * Returns Title of the protected element if it is a PE_PAGE, PE_TREE, PE_CATEGORY or PE_SPECIAL,
     * using mass-fetch DB operations for the current type.
     *
     * @return Title
     */
    protected function get_pe_title()
    {
        $t = $this->data['pe_type'];
        if ($t != IACL::PE_PAGE && $t != IACL::PE_CATEGORY && $t != IACL::PE_SPECIAL && $t != IACL::PE_TREE)
        {
            return NULL;
        }
        $sds = $this->collection ? $this->collection : array($this['key'] => $this);
        $ids = array();
        foreach ($sds as $sd)
        {
            if (!isset($sd->data['pe_title']) && $sd->data['pe_type'] == $t)
            {
                $ids[] = $sd->data['pe_id'];
            }
        }
        if ($t == IACL::PE_SPECIAL)
        {
            $names = IACLStorage::get('SpecialPage')->specialsForIds($ids);
            foreach ($names as &$n)
            {
                $n = Title::newFromText(NS_SPECIAL, $n);
            }
        }
        else
        {
            $names = IACLStorage::get('Util')->getTitles($ids, true);
        }
        foreach ($sds as $sd)
        {
            if (!isset($sd->data['pe_title']) && $sd->data['pe_type'] == $t)
            {
                if (!isset($names[$sd->data['pe_id']]))
                {
                    // Database inconsistency :-(
                    throw new Exception(
                        'BUG: Definition ('.$sd->data['pe_type'].', '.
                        $sd->data['pe_id'].') is saved, but PE does not exist!'
                    );
                }
                $sd->data['pe_title'] = $names[$sd->data['pe_id']];
            }
        }
        return $this->data['pe_title'];
    }

    function makeDirty()
    {
        if (!$this->rw)
        {
            $k = $this['key'];
            if (isset(self::$dirty[$k]))
            {
                throw new Exception("BUG: Trying to create second writable object of same Definition!");
            }
            self::$dirty[$k] = $this;
            self::$clean[$k] = clone $this;
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
        elseif (!isset(self::$dirty[$key = $this->data['pe_type'].'-'.$this->data['pe_id']]))
        {
            self::$dirty[$key] = clone $this;
            self::$dirty[$key]->rw = true;
            self::$dirty[$key]->collection = NULL;
        }
        return self::$dirty[$key];
    }

    function clean()
    {
        if ($this->rw)
        {
            if (isset(self::$clean[$key = $this->data['pe_type'].'-'.$this->data['pe_id']]))
            {
                return self::$clean[$key];
            }
            return false;
        }
        return $this;
    }

    /**
     * Check (with clever caching) if a given user is granted some action
     * in any of the definition(s) identified by $peType/$peID.
     *
     * @param int $userID       User ID or 0 for anonymous user
     * @param int $peType       Parent right type, one of IACL::PE_*
     * @param int/array $peID   Parent right ID(s).
     * @param int $actionID     Action ID, one of IACL::ACTION_*
     * @return int              1 = allow, 0 = deny, -1 = don't care
     */
    static function userCan($userID, $peType, $peID, $actionID)
    {
        if (is_array($peID) && !$peID)
            return -1;
        if ($userID < 0)
            $userID = 0;
        $actionID |= ($actionID << IACL::INDIRECT_OFFSET);
        // Check cache
        $found = -1;
        foreach ((array)$peID as $id)
        {
            if (isset(self::$userCache[$userID][$peType][$id]))
            {
                $found = (self::$userCache[$userID][$peType][$id] & $actionID) ? 1 : 0;
                if ($found > 0)
                    return $found;
            }
        }
        // Return if access is denied or if everything is already loaded
        $isGroup = ($peType == IACL::PE_GROUP) ? 1 : 0;
        if ($found == 0 ||
            isset(self::$userCacheLoaded[$userID]) &&
            (self::$userCacheLoaded[$userID] & (1 << $isGroup)) &&
            !(self::$userCacheLoaded[$userID] & (4 << $isGroup)))
        {
            return $found;
        }
        // Nothing in the cache and there is a chance to load something, try to do it
        if ($userID)
        {
            // Fallback chain: current user -> registered users (0) -> all users (-1)
            $applicable = array(
                '('.IACL::PE_USER.','.$userID.')',
                '('.IACL::PE_REG_USERS.',0)',
                '('.IACL::PE_ALL_USERS.',0)',
            );
        }
        else
        {
            $applicable = array(
                '('.IACL::PE_ALL_USERS.',0)'
            );
        }
        $where0 = array(
            '(child_type, child_id) IN ('.implode(', ', $applicable).')',
        );
        if (!isset(self::$userCacheLoaded[$userID]) ||
            !(self::$userCacheLoaded[$userID] & (1 << $isGroup)))
        {
            global $iaclPreloadLimit;
            self::$userCacheLoaded[$userID] = (isset(self::$userCacheLoaded[$userID]) ? self::$userCacheLoaded[$userID] : 0) | (1 << $isGroup);
            // Preload up to $iaclPreloadLimit rules, preferring more general (pe_type ASC)
            // and more recent (pe_id DESC) rules for better cache hit ratio.
            // Groups are unused in permission checks and thus have no effect on permission check speed,
            // so don't preload them until explicitly requested
            $where = $where0;
            if ($peType != IACL::PE_GROUP)
                $where[] = 'pe_type != '.IACL::PE_GROUP;
            else
                $where['pe_type'] = IACL::PE_GROUP;
            $rules = IACLStorage::get('SD')->getRules($where, array(
                'ORDER BY' => 'child_type ASC, pe_type ASC, pe_id DESC',
                'LIMIT' => $iaclPreloadLimit,
            ));
            if (count($rules) >= $iaclPreloadLimit)
            {
                // There are exactly $iaclPreloadLimit rules
                // => we assume there can be more
                self::$userCacheLoaded[$userID] |= (4 << $isGroup);
            }
            $seenGeneral = false;
            foreach ($rules as $rule)
            {
                if ($rule['child_type'] != IACL::PE_USER)
                    $seenGeneral = true;
                $a = &self::$userCache[$userID][$rule['pe_type']][$rule['pe_id']];
                $a = $a | $rule['actions'];
            }
            if (count($rules) >= $iaclPreloadLimit && $userID && $seenGeneral)
            {
                // For non-anonymous users, we must load all the missing rules for preloaded PEs
                // Else we may load only PE_ALL and miss some PE_REG/PE_USER items
                $where = array();
                foreach (self::$userCache[$userID] as $peType => $pes)
                {
                    $where[] = '(pe_type = '.intval($peType).' AND pe_id IN ('.implode(',', array_keys($pes)).'))';
                }
                $where = array_merge($where0, array('('.implode(' OR ', $where).')'));
                $rules = IACLStorage::get('SD')->getRules($where, array());
                foreach ($rules as $rule)
                {
                    $a = &self::$userCache[$userID][$rule['pe_type']][$rule['pe_id']];
                    $a = $a | $rule['actions'];
                }
            }
        }
        if ((self::$userCacheLoaded[$userID] & (4 << $isGroup)))
        {
            // Not all rules were preloaded => database appears to be relatively big, perform a query for single PE
            $where = $where0;
            $where['pe_type'] = $peType;
            $where['pe_id'] = $peID;
            $rules = IACLStorage::get('SD')->getRules($where, array());
            foreach ($rules as $rule)
            {
                $a = &self::$userCache[$userID][$rule['pe_type']][$rule['pe_id']];
                $a = $a | $rule['actions'];
            }
        }
        // Check cache again
        $found = -1;
        foreach ((array)$peID as $id)
        {
            if (isset(self::$userCache[$userID][$peType][$id]))
            {
                $found = (self::$userCache[$userID][$peType][$id] & $actionID) ? 1 : 0;
                if ($found > 0)
                    return $found;
            }
        }
        return $found;
    }

    /**
     * Returns the ID of a protected object that is given by its name.
     * The ID depends on the type.
     *
     * @param  string $peName   Object name
     * @param  int $peType      Object type (IACL::PE_*)
     * @return int/bool         Object id or <false> if it does not exist
     */
    public static function peIDforName($peType, $peName)
    {
        $ns = NS_MAIN;
        $force = true;
        switch ($peType)
        {
            case IACL::PE_PAGE:
                // Page ACLs allow any namespace except NS_SPECIAL
                $force = false;
                break;
            case IACL::PE_NAMESPACE:
                // $peName is a namespace => get its ID
                global $wgContLang;
                $peName = str_replace(' ', '_', trim($peName, " _\t\n\r"));
                $idx = $wgContLang->getNsIndex($peName);
                if ($idx == false)
                {
                    return (strtolower($peName) == 'main') ? 0 : false;
                }
                return $idx;
            case IACL::PE_CATEGORY:
                $ns = NS_CATEGORY;
                break;
            case IACL::PE_USER:
                $user = User::newFromName($peName);
                return $user->getId();
            case IACL::PE_SPECIAL:
                $ns = NS_SPECIAL;
                break;
            // Groups and rights are in ACL namespace
            case IACL::PE_GROUP:
            case IACL::PE_RIGHT:
                global $haclgContLang;
                $ns = HACL_NS_ACL;
                $peName = $haclgContLang->getPetPrefix($peType).'/'.$peName;
                break;
        }
        // Return the page id
        // TODO add caching here
        $id = haclfArticleID($peName, $ns, $force);
        if ($id < 0)
        {
            if ($peType != IACL::PE_SPECIAL)
            {
                throw new Exception(__METHOD__.': BUG: Special page title passed, but PE type = '.$peType.' (not PE_SPECIAL)');
            }
            return -$id;
        }
        return $id ? $id : NULL;
    }

    /**
     * Resolve protected element name by its ID
     */
    public static function peNameForID($peType, $peID)
    {
        $res = NULL;
        $etc = haclfDisableTitlePatch();
        if ($peType == IACL::PE_NAMESPACE)
        {
            $res = iaclfCanonicalNsText($peID);
        }
        elseif ($peType == IACL::PE_RIGHT)
        {
            global $haclgContLang;
            $t = Title::newFromId($peID);
            $p = $haclgContLang->getPetPrefix($peType);
            if ($t)
            {
                $res = $t->getText();
                if (substr($res, 0, strlen($p)+1) == "$p/")
                    $res = substr($res, strlen($p)+1);
            }
        }
        elseif ($peType == IACL::PE_CATEGORY)
        {
            $t = Title::newFromId($peID);
            $res = $t ? $t->getText() : NULL;
        }
        elseif ($peType == IACL::PE_USER)
        {
            $u = User::newFromId($peID);
            $res = $u ? $u->getName() : NULL;
        }
        elseif ($peType == IACL::PE_GROUP)
        {
            $t = Title::newFromId($peID);
            $res = $t ? substr($t->getText(), 6) : NULL;
        }
        elseif ($peType == IACL::PE_SPECIAL)
        {
            $name = IACLStorage::get('SpecialPage')->specialsForIds($peID);
            $res = reset($name);
        }
        else
        {
            $t = Title::newFromId($peID);
            if ($t)
            {
                // Always use canonical namespace names
                $res = ($t->getNamespace() ? iaclfCanonicalNsText($t->getNamespace()).':' : '') . $t->getText();
            }
        }
        haclfRestoreTitlePatch($etc);
        return $res;
    }

    /**
     * Tries to get definition by its composite ID (type, ID).
     *
     * @param  int $peID    ID of the protected element
     * @param  int $peType  Type of the protected element
     * @return object|bool  Definition object or <false> if it does not exist
     */
    public static function getSDForPE($peType, $peID)
    {
        $r = self::select(array('pe' => array($peType, $peID)));
        return reset($r);
    }

    /**
     * Determine protected element name and type by definition page title
     *
     *  ACL:Page/<Page title>               PE_PAGE
     *  ACL:Tree/<Page title>               PE_TREE
     *  ACL:Page/Special:<Special title>    PE_SPECIAL
     *  ACL:Category/<Category name>        PE_CATEGORY
     *  ACL:Namespace/<Namespace name>      PE_NAMESPACE
     *  ACL:Namespace/Main                  PE_NAMESPACE
     *  ACL:Group/<Group name>              PE_GROUP
     *  ACL:Right/<Right template name>     PE_RIGHT
     *
     * @param string/Title $defTitle
     *     Definition title, with or without ACL: namespace.
     * @return array(int $type, string $name)
     *     Name of the protected element and its type, or NULL if title belongs
     *     to an incorrect namespace or has incorrect prefix.
     */
    public static function nameOfPE($defTitle)
    {
        global $wgContLang, $haclgContLang;
        if ($defTitle instanceof Title)
        {
            if ($defTitle->getNamespace() != HACL_NS_ACL || $defTitle->getInterwiki())
            {
                return NULL;
            }
            $defTitle = $defTitle->getText();
        }
        else
        {
            // Ignore the namespace
            $ns = $wgContLang->getNsText(HACL_NS_ACL).':';
            if (strpos($defTitle, $ns) === 0)
            {
                $defTitle = substr($defTitle, strlen($ns));
            }
        }
        $p = strpos($defTitle, '/');
        if (!$p)
        {
            return NULL;
        }
        $prefix = substr($defTitle, 0, $p);
        $type = $haclgContLang->getPetAlias($prefix);
        if ($type && strlen($defTitle) > $p+1)
        {
            $peName = substr($defTitle, $p+1);
            if ($type == IACL::PE_PAGE)
            {
                $p = strpos($peName, ':');
                if ($wgContLang->getNsIndex(substr($peName, 0, $p)) == NS_SPECIAL)
                {
                    // Special page maps to a separate PE type
                    return array(IACL::PE_SPECIAL, substr($peName, $p+1));
                }
            }
            return array($type, $peName);
        }
        return array(IACL::PE_RIGHT, $defTitle);
    }

    /**
     * Determine ACL definition page title by protected element type and name
     *
     * @param int    $peType    PE type
     * @param string $nameOfPE  PE name
     * @return string $defTitle Definition title text
     */
    public static function nameOfSD($peType, $nameOfPE)
    {
        global $wgContLang, $haclgContLang;
        $defTitle = $wgContLang->getNsText(HACL_NS_ACL).':';
        if ($nameOfPE instanceof Title)
        {
            // Get canonical prefixed text from a Title object
            $base = $nameOfPE->getText();
            $ns = $nameOfPE->getNamespace();
            if ($ns == NS_CATEGORY && $peType == IACL::PE_CATEGORY ||
                $ns == HACL_NS_ACL && ($peType == IACL::PE_RIGHT || $peType == IACL::PE_GROUP) ||
                $ns == NS_SPECIAL && $peType == IACL::PE_SPECIAL)
            {
                $ns = false;
            }
            elseif ($peType != IACL::PE_PAGE)
            {
                throw new Exception("BUG: ".__METHOD__." called with title object $nameOfPE, but peType=$peType");
            }
            if ($ns == NS_SPECIAL)
            {
                // Canonicalize special page titles
                list($base, $par) = SpecialPageFactory::resolveAlias($t->getText());
                if ("$par" !== '')
                {
                    $base = "$base/$par";
                }
            }
            $nameOfPE = ($ns ? iaclfCanonicalNsText($nameOfPE->getNamespace()).':' : '') . $base;
        }
        if ($peType == IACL::PE_SPECIAL)
        {
            $defTitle .= $haclgContLang->getPetPrefix(IACL::PE_PAGE).'/Special:';
        }
        else
        {
            $prefix = $haclgContLang->getPetPrefix($peType);
            if ($prefix)
            {
                $defTitle .= $prefix.'/';
            }
        }
        return $defTitle . $nameOfPE;
    }

    /**
     * Get SD title for a PE given by its type and ID
     *
     * @param array(int, int) $pe PE type and ID
     * @return Title
     */
    public static function getSDTitle($pe)
    {
        // FIXME Do we need to disable title patch?
        // $etc = haclfDisableTitlePatch();
        $peName = IACLDefinition::peNameForID($pe[0], $pe[1]);
        if ($peName !== NULL)
        {
            return Title::newFromText(IACLDefinition::nameOfSD($pe[0], $peName));
        }
        return NULL;
        // haclfRestoreTitlePatch($etc);
    }

    /**
     * Saves this definition into database
     */
    public function save()
    {
        // Load ID and parents before saving, as the definition may be deleted next
        $parents = $this['parents'];
        $peType = $this['pe_type'];
        $peID = $this['pe_id'];
        $key = $peType.'-'.$peID;
        $st = IACLStorage::get('SD');
        if (!$this->data['rules'])
        {
            // Delete definition
            $clean = $this->clean();
            $delRules = $clean['rules'] ? $clean['rules'] : array();
            $addRules = array();
            $st->deleteRules(array(array('pe_type' => $peType, 'pe_id' => $peID)));
            if ($peType == IACL::PE_TREE)
                $st->refreshParentPagesForParent($peID);
        }
        else
        {
            // Update definition
            list($delRules, $addRules) = $this->diffRulesAssoc();
            if ($delRules)
            {
                $st->deleteRules(self::expandRuleArray($delRules));
            }
            if ($addRules)
            {
                $st->addRules(self::expandRuleArray($addRules));
                if ($peType == IACL::PE_TREE && (!$this->clean() || !$this->clean()['rules']))
                    $st->refreshParentPages($peID);
            }
        }
        // Invalidate userCan() cache (FIXME - in fact our parents don't need to do it...)
        if (isset($delRules[IACL::PE_ALL_USERS]) ||
            isset($delRules[IACL::PE_REG_USERS]) ||
            isset($addRules[IACL::PE_ALL_USERS]) ||
            isset($addRules[IACL::PE_REG_USERS]))
        {
            self::$userCache = array();
            self::$userCacheLoaded = array();
        }
        else
        {
            if (isset($delRules[IACL::PE_USER]))
            {
                foreach ($delRules[IACL::PE_USER] as $userID => $rule)
                {
                    unset(self::$userCache[$userID]);
                    unset(self::$userCacheLoaded[$userID]);
                }
            }
            if (isset($addRules[IACL::PE_USER]))
            {
                foreach ($addRules[IACL::PE_USER] as $userID => $rule)
                {
                    unset(self::$userCache[$userID]);
                    unset(self::$userCacheLoaded[$userID]);
                }
            }
        }
        // Invalidate 'parents' field for children
        foreach ($delRules as $peType => $ids)
        {
            foreach ($ids as $peID => $rules)
            {
                if (!empty(self::$clean["$peType-$peID"]))
                {
                    unset(self::$clean["$peType-$peID"]->data['parents']);
                }
            }
        }
        foreach ($addRules as $peType => $ids)
        {
            foreach ($ids as $peID => $rules)
            {
                if (!empty(self::$clean["$peType-$peID"]))
                {
                    unset(self::$clean["$peType-$peID"]->data['parents']);
                }
            }
        }
        // Commit new state into the object cache (FIXME - is the object cache needed at all?)
        unset(self::$dirty[$key]);
        $this->rw = false;
        if ($this->data['rules'])
        {
            self::$clean[$key] = $this;
        }
        else
        {
            self::$clean[$key] = false;
        }
        // Invalidate parents - they will do the same recursively for their parents and so on
        if ($delRules || $addRules)
        {
            foreach ($parents as $p)
                $p->save();
        }
    }

    public function diffRulesAssoc()
    {
        $oldRules = $this->clean();
        $oldRules = $oldRules ? $oldRules['rules'] : array();
        $addRules = $this->data['rules'] = $this->buildRules();
        foreach ($oldRules as $type => $children)
        {
            foreach ($children as $child => $rule)
            {
                if (isset($addRules[$type][$child]) && $addRules[$type][$child]['actions'] == $rule['actions'])
                {
                    unset($addRules[$type][$child]);
                    if (empty($addRules[$type]))
                    {
                        unset($addRules[$type]);
                    }
                    unset($oldRules[$type][$child]);
                    if (empty($oldRules[$type]))
                    {
                        unset($oldRules[$type]);
                    }
                }
            }
        }
        return array($oldRules, $addRules);
    }

    // Convert array(type => child => rule) to linear array
    public static function expandRuleArray($rules)
    {
        if ($rules)
        {
            $rules = call_user_func_array('array_merge', array_map('array_values', array_values($rules)));
        }
        return $rules;
    }

    public function diffRules()
    {
        $diff = $this->diffRulesAssoc();
        $diff[0] = self::expandRuleArray($diff[0]);
        $diff[1] = self::expandRuleArray($diff[1]);
        $oldRules = $this->clean();
        $oldRules = $oldRules ? $oldRules['rules'] : array();
        $addRules = $this->data['rules'] = $this->buildRules();
        foreach ($oldRules as $type => $children)
        {
            foreach ($children as $child => $rule)
            {
                if (isset($addRules[$type][$child]) && $addRules[$type][$child]['actions'] == $rule['actions'])
                {
                    unset($addRules[$type][$child]);
                    if (empty($addRules[$type]))
                    {
                        unset($addRules[$type]);
                    }
                    unset($oldRules[$type][$child]);
                    if (empty($oldRules[$type]))
                    {
                        unset($oldRules[$type]);
                    }
                }
            }
        }
        if ($oldRules)
        {
            $oldRules = call_user_func_array('array_merge', array_map('array_values', array_values($oldRules)));
        }
        if ($addRules)
        {
            $addRules = call_user_func_array('array_merge', array_map('array_values', array_values($addRules)));
        }
        return array($oldRules, $addRules);
    }

    protected function buildRules()
    {
        if (!$this->rw)
        {
            $this->makeDirty();
        }
        $directRules = $rules = array();
        $directMask = ((1 << IACL::INDIRECT_OFFSET)-1);
        $childIds = array();
        $thisId = array(
            'pe_type'   => $this['pe_type'],
            'pe_id'     => $this['pe_id'],
        );
        // Process direct grants
        foreach ($this->data['rules'] as $childType => $children)
        {
            foreach ($children as $child => $actions)
            {
                $actions = $directMask & (is_array($actions) ? $actions['actions'] : $actions);
                if ($actions)
                {
                    if ($childType != IACL::PE_USER && $childType != IACL::PE_ALL_USERS && $childType != IACL::PE_REG_USERS)
                    {
                        $childIds[] = array($childType, $child);
                    }
                    if ($thisId != IACL::PE_GROUP && ($childType == IACL::PE_USER || $childType == IACL::PE_GROUP))
                    {
                        // Edit right implies read right
                        if ($actions & IACL::ACTION_EDIT)
                        {
                            $actions |= IACL::ACTION_READ;
                        }
                    }
                    $directRules[$childType][$child] = $rules[$childType][$child] = $thisId + array(
                        'child_type'    => $childType,
                        'child_id'      => $child,
                        'actions'       => $actions,
                    );
                }
            }
        }
        // Process indirect grants
        $children = self::select(array('pe' => $childIds));
        $member = IACL::ACTION_GROUP_MEMBER | (IACL::ACTION_GROUP_MEMBER << IACL::INDIRECT_OFFSET);
        foreach ($children as $child)
        {
            if ($child['pe_type'] == IACL::PE_GROUP)
            {
                // Groups may be included in other groups or in right definitions
                $actions = $directRules[$child['pe_type']][$child['pe_id']]['actions'] << IACL::INDIRECT_OFFSET;
                foreach ($child['rules'] as $ccType => $ccs)
                {
                    foreach ($ccs as $ccId => $rule)
                    {
                        // Only take member rules into account
                        if ($rule['actions'] & $member)
                        {
                            if (!isset($rules[$rule['child_type']][$rule['child_id']]))
                            {
                                $rules[$rule['child_type']][$rule['child_id']] = $thisId + array(
                                    'child_type' => $rule['child_type'],
                                    'child_id'   => $rule['child_id'],
                                    'actions'    => $actions,
                                );
                            }
                            else
                            {
                                $rules[$rule['child_type']][$rule['child_id']]['actions'] |= $actions;
                            }
                        }
                    }
                }
            }
            elseif ($this['pe_type'] != IACL::PE_GROUP)
            {
                // Right definitions can only be included into other right definitions
                foreach ($child['rules'] as $ccType => $ccs)
                {
                    foreach ($ccs as $ccId => $rule)
                    {
                        // Make all rights indirect
                        $actions = (($rule['actions'] & $directMask) << IACL::INDIRECT_OFFSET) |
                            ($rule['actions'] & ($directMask << IACL::INDIRECT_OFFSET));
                        if (!isset($rules[$rule['child_type']][$rule['child_id']]))
                        {
                            $rules[$rule['child_type']][$rule['child_id']] = $thisId + array(
                                'child_type' => $rule['child_type'],
                                'child_id'   => $rule['child_id'],
                                'actions'    => $actions,
                            );
                        }
                        else
                        {
                            $rules[$rule['child_type']][$rule['child_id']]['actions'] |= $actions;
                        }
                    }
                }
            }
        }
        // Add empty ALL_USERS grant if not yet
        if ($rules && !isset($rules[IACL::PE_ALL_USERS][0]))
        {
            $rules[IACL::PE_ALL_USERS][0] = $thisId + array(
                'child_type' => IACL::PE_ALL_USERS,
                'child_id'   => 0,
                'actions'    => 0,
            );
        }
        return $rules;
    }
}

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
