<?php

/* Copyright 2010+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *                  Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 * This file is part of IntraACL MediaWiki extension. License: GPLv3.
 * http://wiki.4intra.net/IntraACL
 * $Id$
 *
 * Based on HaloACL
 * Copyright 2009, ontoprise GmbH
 *
 * The IntraACL-Extension is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The IntraACL-Extension is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * This file contains functions for client/server communication with AJAX.
 * ALL buggy HaloACL AJAX code is now removed.
 * @author Vitaliy Filippov
 */

/**
 * Define ajax-callable functions
 */
global $wgAjaxExportList;
$wgAjaxExportList[] = 'haclAutocomplete';
$wgAjaxExportList[] = 'haclAcllist';
$wgAjaxExportList[] = 'haclGroupClosure';
$wgAjaxExportList[] = 'haclSDExists_GetEmbedded';
$wgAjaxExportList[] = 'haclGrouplist';
$wgAjaxExportList[] = 'haclGroupExists';

function haclAutocomplete($t, $n, $limit = 11, $checkbox_prefix = false)
{
    if (!$limit)
        $limit = 11;
    $a = array();
    $dbr = wfGetDB(DB_SLAVE);
    // Users
    if ($t == 'user')
    {
        $r = $dbr->select(
            'user', 'user_name, user_real_name',
            array('user_name LIKE '.$dbr->addQuotes('%'.$n.'%').' OR user_real_name LIKE '.$dbr->addQuotes('%'.$n.'%')),
            __METHOD__,
            array('ORDER BY' => 'user_name', 'LIMIT' => $limit)
        );
        while ($row = $r->fetchRow())
            $a[] = array($row[1] ? $row[0] . ' (' . $row[1] . ')' : $row[0], $row[0]);
    }
    // IntraACL Groups
    elseif ($t == 'group')
    {
        $ip = 'hi_';
        $r = IACLStorage::get('Groups')->getGroups($n, $limit);
        foreach ($r as $group)
        {
            $n = $group['group_name'];
            if (($p = strpos($n, '/')) !== false)
                $n = substr($n, $p+1);
            $a[] = array($n, $n);
        }
    }
    // MediaWiki Pages
    elseif ($t == 'page')
    {
        $ip = 'ti_';
        $n = str_replace(' ', '_', $n);
        $where = array();
        // Check if namespace is specified within $n
        $etc = haclfDisableTitlePatch();
        $tt = Title::newFromText($n.'X');
        if ($tt->getNamespace() != NS_MAIN)
        {
            $n = substr($tt->getDBkey(), 0, -1);
            $where['page_namespace'] = $tt->getNamespace();
        }
        haclfRestoreTitlePatch($etc);
        // Select page titles
        $where[] = 'page_title LIKE '.$dbr->addQuotes($n.'%');
        $r = $dbr->select(
            'page', 'page_title, page_namespace',
            $where, __METHOD__,
            array('ORDER BY' => 'page_namespace, page_title', 'LIMIT' => $limit)
        );
        while ($row = $r->fetchRow())
        {
            $title = Title::newFromText($row[0], $row[1]);
            // Filter unreadable
            if ($title->userCanRead())
            {
                $title = $title->getPrefixedText();
                $a[] = array($title, $title);
            }
        }
    }
    // Namespaces
    elseif ($t == 'namespace')
    {
        $ip = 'ti_';
        global $wgCanonicalNamespaceNames, $wgContLang, $haclgUnprotectableNamespaceIds;
        $ns = $wgCanonicalNamespaceNames;
        $ns[0] = 'Main';
        ksort($ns);
        // Unlimited
        $limit = count($ns)+1;
        $n = mb_strtolower($n);
        $nl = mb_strlen($n);
        foreach ($ns as $k => $v)
        {
            $v = str_replace('_', ' ', $v);
            $name = str_replace('_', ' ', $wgContLang->getNsText($k));
            if (!$name)
                $name = $v;
            if ($k >= 0 && (!$nl ||
                mb_strtolower(mb_substr($v, 0, $nl)) == $n ||
                mb_strtolower(mb_substr($name, 0, $nl)) == $n) &&
                empty($haclgUnprotectableNamespaceIds[$k]))
            {
                $a[] = array($name, $v);
            }
        }
    }
    // Categories
    elseif ($t == 'category')
    {
        $ip = 'ti_';
        $where = array(
            'page_namespace' => NS_CATEGORY,
            'page_title LIKE '.$dbr->addQuotes(str_replace(' ', '_', $n).'%')
        );
        $r = $dbr->select(
            'page', 'page_title',
            $where, __METHOD__,
            array('ORDER BY' => 'page_title', 'LIMIT' => $limit)
        );
        while ($row = $r->fetchRow())
        {
            $title = Title::newFromText($row[0], NS_CATEGORY);
            // Filter unreadable
            if ($title->userCanRead())
            {
                $title = $title->getText();
                $a[] = array($title, $title);
            }
        }
    }
    // ACL definitions, optionally of type = substr($t, 3)
    elseif (substr($t, 0, 2) == 'sd')
    {
        $ip = 'ri_';
        foreach (IACLStorage::get('SD')->getSDs2($t == 'sd' ? NULL : substr($t, 3), $n, $limit) as $sd)
        {
            $rn = $sd->getSDName();
            $a[] = array($rn, $rn);
        }
    }
    // No items
    if (!$a)
        return '<div class="hacl_tt">'.wfMsg('hacl_autocomplete_no_'.$t.'s').'</div>';
    // More than (limit-1) items => add '...' at the end of list
    $max = false;
    if (count($a) >= $limit)
    {
        array_pop($a);
        $max = true;
    }
    $i = 0;
    $html = '';
    if ($checkbox_prefix)
    {
        // This is used by Group Editor: display autocomplete list with checkboxes
        $ip = $checkbox_prefix . '_';
        foreach ($a as $item)
        {
            $i++;
            $html .= '<div id="'.$ip.$i.'" class="hacl_ti" title="'.
                htmlspecialchars($item[1]).'"><input style="cursor: pointer" type="checkbox" id="c'.$ip.$i.
                '" /> '.htmlspecialchars($item[0]).' <span id="t'.$ip.$i.'"></span></div>';
        }
    }
    else
    {
        // This is used by ACL Editor: simple autocomplete lists for editboxes
        foreach ($a as $item)
        {
            $i++;
            $html .= '<div id="item'.$i.'" class="hacl_ti" title="'.
                htmlspecialchars($item[1]).'">'.
                htmlspecialchars($item[0]).'</div>';
        }
    }
    if ($max)
        $html .= '<div class="hacl_tt">...</div>';
    return $html;
}

function haclAcllist()
{
    $a = func_get_args();
    return call_user_func_array(array('IntraACLSpecial', 'haclAcllist'), $a);
}

function haclGrouplist()
{
    $a = func_get_args();
    return call_user_func_array(array('IntraACLSpecial', 'haclGrouplist'), $a);
}

/**
 * Return group members for each group of $groups='group1,group2,...',
 * + returns rights for each predefined right of $predefined='sd1[sd2,...'
 * predefined right names are joined by [ as it is forbidden by MediaWiki in titles
 */
function haclGroupClosure($groups, $predefined = '')
{
    $pe = array();
    foreach (explode(',', $groups) as $k)
    {
        $k = trim($k);
        if ($k)
        {
            $pe[] = array(IACL::PE_GROUP, $k);
        }
    }
    $pe = IACLDefinition::newFromNames($pe);
    $members = array();
    if ($pe)
    {
        foreach ($pe[IACL::PE_GROUP] as $name => $g)
        {
            // User:X, group->getText(), then sort($members[$k])
            $members[$name] = $g
        }
    }
    $rights = array();
    foreach (explode('[', $predefined) as $k)
        if ($k)
            $rights[$k] = IntraACLSpecial::getRights($k);
    return json_encode(array('groups' => $members, 'rights' => $rights));
}

function haclSDExists_GetEmbedded($type, $name)
{
    $data = array(
        'exists' => false,
        'embedded' => '',
    );
    $peID = HACLSecurityDescriptor::peIDforName($name, $type);
    if ($peID !== false)
    {
        if ($sdID = HACLSecurityDescriptor::getSDForPE($peID, $type))
        {
            // FIXME returns true only for correct SD definitions
            $data['exists'] = true;
        }
        if ($type == 'page') // PET_PAGE
        {
            // Build HTML code for embedded protection toolbar
            $data['embedded'] = HACLToolbar::getEmbeddedHtml($peID, $sdID);
        }
    }
    return json_encode($data);
}

function haclGroupExists($name)
{
    global $haclgContLang;
    $grpTitle = Title::newFromText($haclgContLang->getGroupPrefix().'/'.$name, HACL_NS_ACL);
    return $grpTitle && $grpTitle->getArticleId() ? 'true' : 'false';
}

class IACLAjax
{
    /**
     * Recursively get rights of SD by name or ID
     */
    static function getRights($sdnameorid)
    {
        if (!$sdnameorid)
            return array();
        if (!is_numeric($sdnameorid))
        {
            if ($t = Title::newFromText($sdnameorid, HACL_NS_ACL))
                $sdid = $t->getArticleId();
        }
        else
            $sdid = $sdnameorid;
        if (!$sdid)
            return array();
        $res = array();
        /* Inline rights */
        $rights = IACLStorage::get('SD')->getInlineRightsOfSDs($sdid, true);
        foreach ($rights as $r)
        {
            /* get action names */
            $actmask = $r->getActions();
            $actions = array();
            if ($actmask & HACLLanguage::RIGHT_READ)
                $actions[] = 'read';
            if ($actmask & HACLLanguage::RIGHT_MANAGE)
                $actions[] = 'manage';
            if ($actmask & HACLLanguage::RIGHT_EDIT)
                $actions[] = 'edit';
            if ($actmask & HACLLanguage::RIGHT_CREATE)
                $actions[] = 'create';
            if ($actmask & HACLLanguage::RIGHT_MOVE)
                $actions[] = 'move';
            if ($actmask & HACLLanguage::RIGHT_DELETE)
                $actions[] = 'delete';
            $memberids = array(
                'user' => array_flip($r->getUsers()),
                'group' => array_flip($r->getGroups()),
            );
            /* get groups closure */
            $memberids = IACLStorage::get('Groups')->getGroupMembersRecursive(array_keys($memberids['group']), $memberids);
            $members = array();
            foreach (IACLStorage::get('Util')->getUsers(array('user_id' => array_keys($memberids['user']))) as $u)
                $members[] = 'User:'.$u->user_name;
            foreach (IACLStorage::get('Groups')->getGroupsByIds(array_keys($memberids['group'])) as $g)
                $members[] = $g->group_name;
            /* merge into result */
            foreach ($members as $m)
                foreach ($actions as $a)
                    $res[$m][$a] = true;
        }
        /* Predefined rights */
        $predef = IACLStorage::get('SD')->getPredefinedRightsOfSD($sdid, false);
        foreach ($predef as $id)
        {
            $sub = self::getRights($id);
            foreach ($sub as $m => $acts)
                foreach ($acts as $a => $true)
                    $res[$m][$a] = true;
        }
        /* Sort members */
        asort($res);
        return $res;
    }
}
