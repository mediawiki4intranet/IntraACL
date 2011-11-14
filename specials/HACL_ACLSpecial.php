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
 * A special page for defining and managing IntraACL objects
 *
 * @author Vitaliy Filippov
 */

if (!defined('MEDIAWIKI'))
    die();

class IntraACLSpecial extends SpecialPage
{
    static $actions = array(
        'acllist'     => 1,
        'acl'         => 1,
        'quickaccess' => 1,
        'grouplist'   => 1,
        'group'       => 1,
    );

    var $aclTargetTypes = array(
        'protect' => array('page' => 1, 'namespace' => 1, 'category' => 1),
        'define' => array('right' => 1),
    );

    /* Identical to Xml::element, but does no htmlspecialchars() on $contents */
    static function xelement($element, $attribs = null, $contents = '', $allowShortTag = true)
    {
        if (is_null($contents))
            return Xml::openElement($element, $attribs);
        elseif ($contents == '')
            return Xml::element($element, $attribs, $contents, $allowShortTag);
        return Xml::openElement($element, $attribs) . $contents . Xml::closeElement($element);
    }

    /* Constructor of IntraACL special page class */
    public function __construct()
    {
        $this->mRestriction = 'user';
        parent::__construct('IntraACL');
    }

    /* Entry point */
    public function execute()
    {
        global $wgOut, $wgRequest, $wgUser, $wgTitle, $haclgHaloScriptPath;
        haclCheckScriptPath();
        $q = $wgRequest->getValues();
        if ($wgUser->isLoggedIn())
        {
            wfLoadExtensionMessages('IntraACL');
            $wgOut->setPageTitle(wfMsg('hacl_special_page'));
            if (!self::$actions[$q['action']])
                $q['action'] = 'acllist';
            $f = 'html_'.$q['action'];
            $wgOut->addLink(array(
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'media' => 'screen, projection',
                'href' => $haclgHaloScriptPath.'/skins/haloacl.css',
            ));
            if ($f == 'html_acllist')
                $wgOut->addHTML('<p style="margin-top: -8px">'.wfMsgExt('hacl_acllist_hello', 'parseinline').'</p>');
            $this->_actions($q);
            $this->$f($q);
        }
        else
        {
            $q = $_GET;
            unset($q['title']);
            $wgOut->redirect(
                Title::newFromText('Special:UserLogin')
                ->getFullUrl(array(
                    'returnto' => 'Special:IntraACL',
                    'returntoquery' => http_build_query($q)
                ))
            );
        }
    }

    /* View list of all ACL definitions, filtered and loaded using AJAX */
    public function html_acllist(&$q)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $haclgContLang;
        $limit = !empty($q['limit']) ? intval($q['limit']) : 100;
        if (empty($q['filter'])) $q['filter'] = '';
        if (empty($q['offset'])) $q['offset'] = 0;
        if (!empty($q['types']))
        {
            $types = array_flip(explode(',', $q['types']));
            foreach ($types as $k => &$i)
                $i = true;
            unset($i);
        }
        else
        {
            $types = array();
            foreach ($this->aclTargetTypes as $k => $a)
                foreach ($a as $v => $t)
                    $types[$v] = true;
        }
        $types['all'] = true;
        foreach ($this->aclTargetTypes as $k => $a)
        {
            $types[$k] = true;
            foreach ($a as $v => $t)
            {
                $types[$k] = $types[$k] && $types[$v];
                $types['all'] = $types['all'] && $types[$v];
            }
        }
        // Run template
        ob_start();
        require(dirname(__FILE__).'/HACL_ACLList.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        $wgOut->setPageTitle(wfMsg('hacl_acllist'));
        $wgOut->addHTML($html);
    }

    /* Create/edit ACL definition using interactive editor */
    public function html_acl(&$q)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $haclgContLang, $wgContLang, $wgScriptPath;
        $aclTitle = $aclArticle = NULL;
        $aclContent = $aclPEName = $aclPEType = '';
        if (!empty($q['sd']))
        {
            $aclTitle = Title::newFromText($q['sd'], HACL_NS_ACL);
            $t = HACLEvaluator::hacl_type($aclTitle);
            if ($aclTitle && $t != 'group')
            {
                if (($aclArticle = new Article($aclTitle)) &&
                    $aclArticle->exists())
                {
                    $aclContent = $aclArticle->getContent();
                    $aclSDName = $aclTitle->getText();
                }
                list($aclPEName, $aclPEType) = HACLSecurityDescriptor::nameOfPE($aclTitle->getText());
            }
        }
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/HACL_ACLEditor.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        if ($aclArticle)
            $msg = 'hacl_acl_edit';
        elseif ($aclTitle)
            $msg = 'hacl_acl_create_title';
        else
            $msg = 'hacl_acl_create';
        $wgOut->setPageTitle(wfMsg($msg, $aclTitle ? $aclTitle->getText() : ''));
        $wgOut->addHTML($html);
    }

    /* Manage Quick Access ACL list */
    public function html_quickaccess(&$args)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $wgRequest;
        /* Handle save */
        $args = $wgRequest->getValues();
        $like = empty($args['like']) ? '' : $args['like'];
        if (!empty($args['save']))
        {
            $ids = array();
            foreach ($args as $k => $v)
                if (substr($k, 0, 3) == 'qa_')
                    $ids[] = substr($k, 3);
            HACLStorage::getDatabase()->saveQuickAcl($wgUser->getId(), $ids, $args['qa_default']);
            wfGetDB(DB_MASTER)->commit();
            header("Location: $wgScript?title=Special:IntraACL&action=quickaccess&like=".urlencode($like));
            exit;
        }
        /* Load data */
        $templates = HACLStorage::getDatabase()->getSDs2('right', $like);
        $quickacl = HACLQuickacl::newForUserId($wgUser->getId());
        $quickacl_ids = array_flip($quickacl->getSD_IDs());
        foreach ($templates as $sd)
        {
            $sd->default = $quickacl->default_sd_id == $sd->getSDId();
            $sd->selected = array_key_exists($sd->getSDId(), $quickacl_ids);
            $sd->editlink = $wgScript.'?title=Special:IntraACL&action=acl&sd='.urlencode($sd->getSDName());
            $sd->viewlink = Title::newFromText($sd->getSDName(), HACL_NS_ACL)->getLocalUrl();
        }
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/HACL_QuickACL.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        $wgOut->setPageTitle(wfMsg('hacl_qacl_manage'));
        $wgOut->addHTML($html);
    }

    /* Add header with available actions */
    public function _actions(&$q)
    {
        global $wgScript, $wgOut, $wgUser;
        $act = $q['action'];
        if ($act == 'acl' && !empty($q['sd']))
            $act = 'acledit';
        elseif ($act == 'group' && !empty($q['group']))
            $act = 'groupedit';
        $html = array();
        foreach (array('acllist', 'acl', 'quickaccess', 'grouplist', 'group') as $action)
        {
            $a = '<b>'.wfMsg("hacl_action_$action").'</b>';
            if ($act != $action)
            {
                $url = "$wgScript?title=Special:IntraACL&action=$action";
                $a = '<a href="'.htmlspecialchars($url).'">'.$a.'</a>';
            }
            $html[] = $a;
        }
        $html = '<p>'.implode(' &nbsp; &nbsp; ', $html).'</p>';
        $wgOut->addHTML($html);
    }

    /* Manage groups */
    public function html_grouplist(&$q)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $haclgContLang;
        ob_start();
        require(dirname(__FILE__).'/HACL_GroupList.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        $wgOut->setPageTitle(wfMsg('hacl_grouplist'));
        $wgOut->addHTML($html);
    }

    /* Create or edit a group */
    public function html_group(&$q)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $wgContLang, $haclgContLang;
        if (empty($q['group']) ||
            !($grpTitle = Title::newFromText($q['group'], HACL_NS_ACL)) ||
            HACLEvaluator::hacl_type($grpTitle) != 'group' ||
            !($grpArticle = new Article($grpTitle)) ||
            !$grpArticle->exists())
        {
            $grpTitle = NULL;
            $grpArticle = NULL;
            $grpName = '';
        }
        else
            list($grpPrefix, $grpName) = explode('/', $grpTitle->getText(), 2);
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/HACL_GroupEditor.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        $wgOut->setPageTitle($grpTitle ? wfMsg('hacl_grp_editing', $grpTitle->getText()) : wfMsg('hacl_grp_creating'));
        $wgOut->addHTML($html);
    }

    /* Recursively get rights of SD by name or ID */
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
        $st = HACLStorage::getDatabase();
        $res = array();
        /* Inline rights */
        $rights = $st->getInlineRightsOfSDs($sdid, true);
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
            $memberids = $st->getGroupMembersRecursive(array_keys($memberids['group']), $memberids);
            $members = array();
            /* get user names */
            foreach ($st->getUserNames(array_keys($memberids['user'])) as $u)
                $members[] = 'User:'.$u['user_name'];
            /* get group names */
            foreach ($st->getGroupNames(array_keys($memberids['group'])) as $g)
                $members[] = $g['group_name'];
            /* merge into result */
            foreach ($members as $m)
                foreach ($actions as $a)
                    $res[$m][$a] = true;
        }
        /* Predefined rights */
        $predef = $st->getPredefinedRightsOfSD($sdid, false);
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

    /* "Real" ACL list, loaded using AJAX */
    static function haclAcllist($t, $n, $offset = 0, $limit = 10)
    {
        global $wgScript, $wgTitle, $haclgHaloScriptPath, $haclgContLang, $wgUser;
        haclCheckScriptPath();
        // Load data
        $sdpages = HACLStorage::getDatabase()->getSDPages($t, $n, $offset, $limit, $total);
        // Build SD data for template
        $lists = array();
        foreach ($sdpages as $r)
        {
            $sd = Title::newFromRow($r);
            $d = array(
                'name' => $sd->getText(),
                'real' => $sd->getText(),
                'editlink' => $wgScript.'?title=Special:IntraACL&action=acl&sd='.urlencode($sd->getText()),
                'viewlink' => $sd->getLocalUrl(),
            );
            list($d['type'], $d['real']) = explode('/', $d['real'], 2);
            if ($d['real'])
                $d['type'] = $haclgContLang->getPetAlias($d['type']);
            else
                $d['real'] = $d['type'];
            // Single SD inclusion
            $d['single'] = $r->sd_single_title;
            if ($r->sd_single_title)
            {
                list($d['singletype'], $d['singlename']) = explode('/', $d['single']->getText(), 2);
                if ($d['singlename'])
                    $d['singletype'] = $haclgContLang->getPetAlias($d['type']);
                else
                    $d['singlename'] = $d['singletype'];
                $d['singlelink'] = $d['single']->getLocalUrl();
                $d['singletip'] = wfMsg('hacl_acllist_hint_single', $d['real'], $d['single']->getPrefixedText());
            }
            $lists[$d['type']][] = $d;
        }
        // Next and previous page links
        $pageurl = Title::makeTitleSafe(NS_SPECIAL, 'IntraACL')->getLocalUrl(array(
            'types' => $t,
            'filter' => $n,
            'limit' => $limit,
        ));
        $nextpage = $prevpage = false;
        if ($total > $limit+$offset)
            $nextpage = $pageurl.'&offset='.intval($offset+$limit);
        if ($offset >= $limit)
            $prevpage = $pageurl.'&offset='.intval($offset-$limit);
        // Run template
        ob_start();
        require(dirname(__FILE__).'/HACL_ACLListContents.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    /* "Real" group list, loaded using AJAX */
    static function haclGrouplist($n, $not_n = NULL)
    {
        global $wgScript, $haclgHaloScriptPath;
        haclCheckScriptPath();
        /* Load data */
        $groups = HACLStorage::getDatabase()->getGroups($n, $not_n);
        foreach ($groups as &$g)
        {
            $gn = $g['group_name'];
            $g = array(
                'name' => $gn,
                'real' => $gn,
                'editlink' => $wgScript.'?title=Special:IntraACL&action=group&group='.urlencode($gn),
                'viewlink' => Title::newFromText($gn, HACL_NS_ACL)->getLocalUrl(),
            );
            if ($p = strpos($g['real'], '/'))
                $g['real'] = substr($g['real'], $p+1);
        }
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/HACL_GroupListContents.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }
}
