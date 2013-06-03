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
        'rightgraph'  => 1,
    );

    var $aclTargetTypes = array(
        'protect' => array('page' => 1, 'namespace' => 1, 'category' => 1),
        'define' => array('right' => 1),
    );

    var $isAdmin;

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
    public function execute($par)
    {
        global $wgOut, $wgRequest, $wgUser, $wgTitle, $haclgHaloScriptPath;
        haclCheckScriptPath();
        $q = $wgRequest->getValues();
        if ($wgUser->isLoggedIn())
        {
            wfLoadExtensionMessages('IntraACL');
            $wgOut->setPageTitle(wfMsg('hacl_special_page'));
            $groups = $wgUser->getGroups();
            $this->isAdmin = in_array('bureaucrat', $groups) || in_array('sysop', $groups);
            if (!isset($q['action']) ||
                !isset(self::$actions[$q['action']]) ||
                $q['action'] == 'rightgraph' && !$this->isAdmin)
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

    // key="value", key="value", ...
    public static function attrstring($attr)
    {
        $a = array();
        foreach ($attr as $k => $v)
            $a[] = "$k=\"".str_replace('"', '\\"', $v)."\"";
        return implode(', ', $a);
    }

    // Displays full graph of IntraACL rights using Graphviz, in SVG format
    // Does not reflect the right override method - just displays which rights apply
    // to different protected elements and which users have these rights
    // SD -> (namespace = page cluster)
    // SD -> page
    // SD -> category -> subcategory -> subcluster of a namespace
    // SD -> included SD
    // User -> group -> SD
    // User -> SD
    public function html_rightgraph(&$q)
    {
        global $wgOut, $wgContLang;
        $patch = haclfDisableTitlePatch();
        // Group members
        $groups = IACLStorage::get('Groups')->getGroupsByIds(NULL);
        $ids = array();
        foreach ($groups as $g)
            $ids[] = $g->group_id;
        $members = IACLStorage::get('Groups')->getMembersOfGroups($ids);
        // Security descriptors
        $sds = IACLStorage::get('SD')->getSDs2(NULL, NULL, NULL, false);
        $ids = array();
        foreach ($sds as $r)
        {
            if ($r->type == 'page' || $r->type == 'category')
                $ids[] = $r->pe_id;
            $ids[] = $r->sd_id;
        }
        $titles = IACLStorage::get('Util')->getTitles($ids, true);
        // Inline rights
        $rights = IACLStorage::get('IR')->getAllRights();
        // Fetch categories and subcategories
        $cattitles = array();
        foreach ($sds as $r)
            if ($r->type == 'category' && isset($titles[$r->pe_id]))
                $cattitles[] = $titles[$r->pe_id];
        $cattitles = IACLStorage::get('Util')->getAllChildrenCategories($cattitles);
        $catkeys = array();
        foreach ($cattitles as $t)
        {
            $catkeys[$t->getArticleId()] = $t->getDBkey();
            $titles[$t->getArticleId()] = $t;
        }
        $catlinks = IACLStorage::get('Util')->getCategoryLinks($catkeys);
        $catkeys = array_flip($catkeys);
        // Filter inconsistent SDs
        $newsds = array();
        foreach ($sds as $r)
            if ($r->type != 'template' &&
                ($r->type != 'page' && $r->type != 'category' || isset($titles[$r->pe_id])))
                $newsds[] = $r;
        $sds = $newsds;
        // Draw security descriptors
        $nodes = array();
        $ns_first = array();
        $cat_cluster = array();
        $cat_cluster[NS_CATEGORY][''] = array();
        foreach ($sds as $r)
        {
            if ($r->type != 'page')
                $nodes['sd'.$r->sd_id] = $r;
            if ($r->type == 'page')
            {
                $nodes['pg'.$r->pe_id] = true;
                $edges['sd'.$r->sd_id]['pg'.$r->pe_id] = true;
                if (!isset($ns_first[$titles[$r->pe_id]->getNamespace()]))
                    $ns_first[$titles[$r->pe_id]->getNamespace()] = 'pg'.$r->pe_id;
            }
            elseif ($r->type == 'category')
            {
                $edges['sd'.$r->sd_id]['cat'.$r->pe_id] = true;
                $nodes['cat'.$r->pe_id] = true;
                $cluster['sd'.$r->sd_id] = "clusterns".NS_CATEGORY;
            }
        }
        // Group pages in category clusters within namespaces
        foreach ($catlinks as $catkey => $cattitles)
        {
            $cattitle = $titles[$catkeys[$catkey]];
            $catid = $cattitle->getArticleId();
            foreach ($cattitles as $t)
            {
                $tns = $t->getNamespace();
                $tid = $t->getArticleId();
                if ($tns == NS_CATEGORY)
                {
                    $edges["cat$catid"]["cat$tid"] = true;
                    if (!isset($nodes["cat$tid"]))
                        $nodes["cat$tid"] = true;
                }
                elseif (isset($nodes["pg$tid"]))
                {
                    if (!isset($cluster["pg$tid"]))
                    {
                        $cluster["pg$tid"] = 'clustercat'.$tns.'_'.$catid;
                        if (!isset($cat_cluster[$tns][$catid]))
                        {
                            $cat_cluster[$tns][$catid] = array();
                            $edges["cat$catid"]["pg$tid"] = 'lhead=clustercat'.$tns.'_'.$catid;
                        }
                    }
                    else
                        $edges["cat$catid"]["pg$tid"] = true;
                }
            }
        }
        // Set namespace clusters for non-grouped nodes
        foreach ($nodes as $n => &$attr)
        {
            if (substr($n, 0, 2) == 'pg' && !isset($cluster[$n]))
                $cluster[$n] = 'clusterns'.$titles[substr($n, 2)]->getNamespace();
            elseif (substr($n, 0, 3) == 'cat')
            {
                $cluster[$n] = 'clusterns'.NS_CATEGORY;
                if (!isset($ns_first[NS_CATEGORY]))
                    $ns_first[NS_CATEGORY] = $n;
            }
        }
        unset($attr);
        // Group SDs in the same clusters as their PEs and draw namespace SD edges
        $sdbyid = array();
        foreach ($sds as $r)
        {
            if ($r->type == 'page')
            {
                $cluster['sd'.$r->sd_id] = $cluster['pg'.$r->pe_id];
                $sdbyid[$r->sd_id] = $r;
            }
            elseif ($r->type == 'namespace')
            {
                $cluster['sd'.$r->sd_id] = '';
                if (isset($ns_first[$r->pe_id]))
                    $k = $ns_first[$r->pe_id];
                else
                {
                    $k = 'etc'.$r->pe_id;
                    $nodes[$k] = array(
                        'label'   => '...',
                        'shape'   => 'circle',
                        'href'    => Title::newFromText('Special:Allpages')->getFullUrl(array('namespace' => $r->pe_id)),
                        'tooltip' => "Click to see all pages in namespace ".$wgContLang->getNsText($r->pe_id),
                    );
                    $cluster[$k] = "clusterns".$r->pe_id;
                    $ns_first[$r->pe_id] = $k;
                }
                $edges['sd'.$r->sd_id][$k] = "lhead=clusterns".$r->pe_id;
            }
            elseif ($r->type == 'right')
                $cluster['sd'.$r->sd_id] = '';
        }
        // Draw right hierarchy
        $hier = IACLStorage::get('SD')->getFullSDHierarchy();
        foreach ($hier as $row)
        {
            if (isset($sdbyid[$row->child_id]) && $sdbyid[$row->child_id]->type == 'page')
                $nodes['sd'.$row->child_id] = $sdbyid[$row->child_id];
            if (isset($sdbyid[$row->parent_right_id]) && $sdbyid[$row->parent_right_id]->type == 'page')
                $nodes['sd'.$row->parent_right_id] = $sdbyid[$row->parent_right_id];
            if (isset($nodes['sd'.$row->child_id]) &&
                isset($nodes['sd'.$row->parent_right_id]))
                $edges['sd'.$row->child_id]['sd'.$row->parent_right_id] = true;
        }
        foreach ($cluster as $k => $cl)
        {
            if (isset($nodes[$k]))
            {
                if (preg_match('/clustercat(\d+)_(\d+)/', $cl, $m))
                    $cat_cluster[$m[1]][$m[2]][] = $k;
                elseif (preg_match('/clusterns(\d+)/', $cl, $m))
                    $cat_cluster[$m[1]][''][] = $k;
                elseif ($cl === '')
                    $cat_cluster[''][''][] = $k;
            }
        }
        // Set node attributes
        $shapes = array(
            'sd' => 'note',
            'pg' => 'ellipse',
            'cat' => 'folder',
        );
        $colors = array(
            'sd_page'      => '#ffd0d0',
            'sd_category'  => '#ffff80',
            'sd_right'     => '#90ff90',
            'sd_namespace' => '#c0c0ff',
            'cat'          => '#ffe0c0',
        );
        foreach ($nodes as $n => $r)
        {
            if (is_array($r))
                continue;
            preg_match('/([a-z]+)(\d+)/', $n, $m);
            $type = $m[1];
            $id = $m[2];
            $nodes[$n] = array(
                'shape' => $shapes[$type],
                'label' => $titles[$id]->getPrefixedText(),
                'href'  => $titles[$id]->getFullUrl(),
            );
            if ($type != 'pg')
            {
                $type2 = $type;
                if ($type2 == 'sd')
                    $type2 .= '_'.$r->type;
                if (isset($colors[$type2]))
                    $nodes[$n]['fillcolor'] = $colors[$type2];
                $nodes[$n]['style'] = 'filled';
            }
        }
        // Draw clusters
        $graph = '';
        $ns_first[''] = '';
        foreach ($ns_first as $ns => $first)
        {
            if ($ns !== '')
            {
                $graph .= "subgraph clusterns$ns {\n";
                $graph .= "graph [label=\"Namespace ".($ns ? $wgContLang->getNsText($ns) : 'Main').
                    "\", href=\"".Title::newFromText('Special:Allpages')->getFullUrl(array('namespace' => $ns)).
                    "\"];\n";
            }
            foreach ($cat_cluster[$ns] as $cat => $ks)
            {
                if ($cat !== '')
                {
                    $graph .= "subgraph clustercat${ns}_$cat {\n";
                    $graph .= 'graph [label="'.$titles[$cat]->getPrefixedText().'", href="'.$titles[$cat]->getFullUrl().'"];'."\n";
                }
                foreach ($ks as $nodename)
                    $graph .= "$nodename [".self::attrstring($nodes[$nodename])."];\n";
                if ($cat !== '')
                    $graph .= "}\n";
            }
            if ($ns !== '')
                $graph .= "}\n";
        }
        // Draw edges
        foreach ($edges as $from => $to)
        {
            if (isset($nodes[$from]))
            {
                foreach ($to as $id => $attr)
                {
                    if ($attr !== true)
                        $attr .= ', ';
                    else
                        $attr = '';
                    $attr .= self::attrstring(array(
                        'href' => $nodes[$from]['href'],
                        'tooltip' => $nodes[$from]['label'],
                    ));
                    $graph .= "$from -> $id [$attr];\n";
                }
            }
        }
        // Render the graph
        $graph = "<graphviz>\ndigraph G {\nedge [penwidth=2 color=blue];\nnode [fontname=courier];\nsplines=polyline;\noverlap=false;\nranksep=2;\nrankdir=LR;\ncompound=true;\n$graph\n}\n</graphviz>\n";
        $wgOut->addWikiText($graph);
        $wgOut->addHTML("<pre>$graph</pre>");
        haclfRestoreTitlePatch($patch);
    }

    /* Displays list of all ACL definitions, filtered and loaded using AJAX */
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
        require(dirname(__FILE__).'/../templates/HACL_ACLList.tpl.php');
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
        $aclContent = '{{#manage rights: assigned to = User:'.$wgUser->getName().'}}';
        $aclPEName = $aclPEType = '';
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
                else
                    $aclArticle = NULL;
                list($aclPEName, $aclPEType) = HACLSecurityDescriptor::nameOfPE($aclTitle->getText());
            }
        }
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/../templates/HACL_ACLEditor.tpl.php');
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
            IACLStorage::get('QuickACL')->saveQuickAcl($wgUser->getId(), $ids, $args['qa_default']);
            wfGetDB(DB_MASTER)->commit();
            header("Location: $wgScript?title=Special:IntraACL&action=quickaccess&like=".urlencode($like));
            exit;
        }
        /* Load data */
        $templates = IACLStorage::get('SD')->getSDs2('right', $like);
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
        require(dirname(__FILE__).'/../templates/HACL_QuickACL.tpl.php');
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
        $actions = array('acllist', 'acl', 'quickaccess', 'grouplist', 'group');
        if ($this->isAdmin)
            $actions[] = 'rightgraph';
        foreach ($actions as $action)
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
        require(dirname(__FILE__).'/../templates/HACL_GroupList.tpl.php');
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
        require(dirname(__FILE__).'/../templates/HACL_GroupEditor.tpl.php');
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
            foreach (IACLStorage::get('Util')->getUsers(array_keys($memberids['user'])) as $u)
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

    /* "Real" ACL list, loaded using AJAX */
    static function haclAcllist($t, $n, $offset = 0, $limit = 10)
    {
        global $wgScript, $wgTitle, $haclgHaloScriptPath, $haclgContLang, $wgUser;
        haclCheckScriptPath();
        // Load data
        $sdpages = IACLStorage::get('SD')->getSDPages($t, $n, $offset, $limit, $total);
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
        require(dirname(__FILE__).'/../templates/HACL_ACLListContents.tpl.php');
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
        $groups = IACLStorage::get('Groups')->getGroups($n, $not_n);
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
        $max = false;
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/../templates/HACL_GroupListContents.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }
}
