<?php

/**
 * Copyright 2010+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
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

/**
 * A special page for defining and managing IntraACL objects
 *
 * FIXME: Add read access checks on SDs.
 *
 * @author Vitaliy Filippov
 */

if (!defined('MEDIAWIKI'))
{
    die();
}

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

    /**
     * Identical to Xml::element, but does no htmlspecialchars() on $contents
     */
    static function xelement($element, $attribs = null, $contents = '', $allowShortTag = true)
    {
        if (is_null($contents))
        {
            return Xml::openElement($element, $attribs);
        }
        elseif ($contents == '')
        {
            return Xml::element($element, $attribs, $contents, $allowShortTag);
        }
        return Xml::openElement($element, $attribs) . $contents . Xml::closeElement($element);
    }

    /**
     * Constructor of IntraACL special page class
     */
    public function __construct()
    {
        $this->mRestriction = 'user';
        parent::__construct('IntraACL');
    }

    /**
     * Entry point
     */
    public function execute($par)
    {
        global $wgOut, $wgRequest, $wgUser, $wgTitle, $haclgHaloScriptPath, $haclgSuperGroups;
        haclCheckScriptPath();
        $q = $wgRequest->getValues();
        if ($wgUser->isLoggedIn())
        {
            $wgOut->setPageTitle(wfMsg('hacl_special_page'));
            $groups = $wgUser->getGroups();
            $this->isAdmin = true && array_intersect($groups, $haclgSuperGroups);
            if (!isset($q['action']) ||
                !isset(self::$actions[$q['action']]) ||
                $q['action'] == 'rightgraph' && !$this->isAdmin)
            {
                $q['action'] = 'acllist';
            }
            $f = 'html_'.$q['action'];
            $wgOut->addLink(array(
                'rel' => 'stylesheet',
                'type' => 'text/css',
                'media' => 'screen, projection',
                'href' => $haclgHaloScriptPath.'/skins/haloacl.css',
            ));
            if ($f == 'html_acllist')
            {
                $wgOut->addHTML('<p style="margin-top: -8px">'.wfMsgExt('hacl_acllist_hello', 'parseinline').'</p>');
            }
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

    /**
     * key="value", key="value", ...
     */
    public static function attrstring($attr)
    {
        $a = array();
        foreach ($attr as $k => $v)
        {
            $a[] = "$k=\"".str_replace('"', '\\"', $v)."\"";
        }
        return implode(', ', $a);
    }

    /**
     * Displays full graph of IntraACL rights using Graphviz, in SVG format.
     * Does not reflect the right override method - just displays which rights apply
     * to different protected elements.
     *
     * SD -> (namespace = page cluster)
     * SD -> page
     * SD -> category -> each subcategory -> subcluster of a namespace
     * Included SD -> SD
     */
    public function html_rightgraph(&$q)
    {
        global $wgOut, $wgContLang;
        $patch = haclfDisableTitlePatch();
        // FIXME Special pages?
        // Select ALL SDs
        $titles = array();
        $defs = IACLDefinition::select(array('pe_type != '.IACL::PE_GROUP));
        $cats = array();
        foreach ($defs as $def)
        {
            $titles[$def['key']] = $def['def_title'];
            if ($def['pe_type'] == IACL::PE_CATEGORY)
            {
                $cats[] = $def['pe_title'];
            }
            if ($def['pe_type'] == IACL::PE_CATEGORY ||
                $def['pe_type'] == IACL::PE_PAGE)
            {
                $titles[$def['pe_id']] = $def['pe_title'];
            }
        }
        $cattitles = IACLStorage::get('Util')->getAllChildrenCategories($cats);
        $catkeys = array();
        foreach ($cattitles as $t)
        {
            // FIXME Mass-fetch article IDs using LinkBatch
            $titles[$t->getArticleId()] = $t;
            $catkeys[$t->getDBkey()] = $t->getArticleId();
        }
        $catlinks = IACLStorage::get('Util')->getCategoryLinks(array_keys($catkeys));
        // Draw security descriptors
        $nodes = array();
        $edges = array();
        $ns_first = array();
        foreach ($defs as $def)
        {
            if ($def['pe_type'] != IACL::PE_PAGE)
            {
                $nodes['sd'.$def['key']] = $def;
                if ($def['pe_type'] == IACL::PE_CATEGORY)
                {
                    $edges['sd'.$def['key']]['cat'.$def['pe_id']] = true;
                    $nodes['cat'.$def['pe_id']] = true;
                    $cluster['sd'.$def['key']] = "clusterns".NS_CATEGORY;
                }
            }
            else
            {
                $nodes['pg'.$def['pe_id']] = true;
                $edges['sd'.$def['key']]['pg'.$def['pe_id']] = true;
                if (!isset($ns_first[$titles[$def['pe_id']]->getNamespace()]))
                {
                    $ns_first[$titles[$def['pe_id']]->getNamespace()] = 'pg'.$def['pe_id'];
                }
            }
        }
        // Group pages in category clusters within namespaces
        $cat_cluster = array();
        $cat_cluster[NS_CATEGORY][''] = array();
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
                    {
                        $nodes["cat$tid"] = true;
                    }
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
                    {
                        $edges["cat$catid"]["pg$tid"] = true;
                    }
                }
            }
        }
        // Set namespace clusters for non-grouped nodes
        foreach ($nodes as $n => &$attr)
        {
            if (substr($n, 0, 2) == 'pg' && !isset($cluster[$n]))
            {
                $cluster[$n] = 'clusterns'.$titles[substr($n, 2)]->getNamespace();
            }
            elseif (substr($n, 0, 3) == 'cat')
            {
                $cluster[$n] = 'clusterns'.NS_CATEGORY;
                if (!isset($ns_first[NS_CATEGORY]))
                {
                    $ns_first[NS_CATEGORY] = $n;
                }
            }
        }
        unset($attr); // prevent reference bugs
        // Group SDs in the same clusters as their PEs and draw namespace SD edges
        $sdbyid = array();
        foreach ($defs as $def)
        {
            if ($def['pe_type'] == IACL::PE_PAGE)
            {
                $cluster['sd'.$def['key']] = $cluster['pg'.$def['pe_id']];
                $sdbyid[$def['key']] = $def;
            }
            elseif ($def['pe_type'] == IACL::PE_NAMESPACE)
            {
                $cluster['sd'.$def['key']] = '';
                if (isset($ns_first[$def['pe_id']]))
                {
                    $k = $ns_first[$def['pe_id']];
                }
                else
                {
                    $k = 'etc'.$def['pe_id'];
                    $nodes[$k] = array(
                        'label'   => '...',
                        'shape'   => 'circle',
                        'href'    => Title::newFromText('Special:Allpages')->getFullUrl(array('namespace' => $def['pe_id'])),
                        // FIXME i18n for tooltip here
                        'tooltip' => "Click to see all pages in namespace ".$wgContLang->getNsText($def['pe_id']),
                    );
                    $cluster[$k] = "clusterns".$def['pe_id'];
                    $ns_first[$def['pe_id']] = $k;
                }
                $edges['sd'.$def['key']][$k] = "lhead=clusterns".$def['pe_id'];
            }
            elseif ($def['pe_type'] == IACL::PE_RIGHT)
            {
                $cluster['sd'.$def['key']] = '';
            }
        }
        // Draw right hierarchy
        $hier = array();
        foreach ($defs as $def)
        {
            foreach ($def['rules'] as $rules)
            {
                foreach ($rules as $rule)
                {
                    if ($rule['child_type'] != IACL::PE_USER &&
                        $rule['child_type'] != IACL::PE_GROUP &&
                        $rule['child_type'] != IACL::PE_ALL_USERS &&
                        $rule['child_type'] != IACL::PE_REG_USERS &&
                        ($rule['actions'] & IACL::ACTION_INCLUDE_SD))
                    {
                        $parent = $rule['pe_type'].'-'.$rule['pe_id']; // get_key
                        $child = $rule['child_type'].'-'.$rule['child_id']; // get_key
                        if (isset($sdbyid[$child]) && $sdbyid[$child]['pe_type'] == IACL::PE_PAGE)
                        {
                            $nodes['sd'.$child] = $sdbyid[$child];
                        }
                        if (isset($sdbyid[$parent]) && $sdbyid[$parent]['pe_type'] == IACL::PE_PAGE)
                        {
                            $nodes['sd'.$parent] = $sdbyid[$parent];
                        }
                        if (isset($nodes['sd'.$child]) &&
                            isset($nodes['sd'.$parent]))
                        {
                            $edges['sd'.$child]['sd'.$parent] = true;
                        }
                    }
                }
            }
        }
        foreach ($cluster as $k => $cl)
        {
            if (isset($nodes[$k]))
            {
                if (preg_match('/clustercat(\d+)_(\d+)/', $cl, $m))
                {
                    $cat_cluster[$m[1]][$m[2]][] = $k;
                }
                elseif (preg_match('/clusterns(\d+)/', $cl, $m))
                {
                    $cat_cluster[$m[1]][''][] = $k;
                }
                elseif ($cl === '')
                {
                    $cat_cluster[''][''][] = $k;
                }
            }
        }
        // Set node attributes
        $shapes = array(
            'sd' => 'note',
            'pg' => 'ellipse',
            'cat' => 'folder',
        );
        $colors = array(
            'sd'.IACL::PE_PAGE      => '#ffd0d0',
            'sd'.IACL::PE_CATEGORY  => '#ffff80',
            'sd'.IACL::PE_RIGHT     => '#90ff90',
            'sd'.IACL::PE_NAMESPACE => '#c0c0ff',
            'cat'                   => '#ffe0c0',
        );
        foreach ($nodes as $n => $r)
        {
            if (is_array($r))
            {
                continue;
            }
            preg_match('/([a-z]+)(\d+(?:-\d+)?)/', $n, $m);
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
                {
                    $type2 .= $r['pe_type'];
                }
                if (isset($colors[$type2]))
                {
                    $nodes[$n]['fillcolor'] = $colors[$type2];
                }
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
                {
                    $graph .= "\"$nodename\" [".self::attrstring($nodes[$nodename])."];\n";
                }
                if ($cat !== '')
                {
                    $graph .= "}\n";
                }
            }
            if ($ns !== '')
            {
                $graph .= "}\n";
            }
        }
        // Draw edges
        foreach ($edges as $from => $to)
        {
            if (isset($nodes[$from]))
            {
                foreach ($to as $id => $attr)
                {
                    if ($attr !== true)
                    {
                        $attr .= ', ';
                    }
                    else
                    {
                        $attr = '';
                    }
                    $attr .= self::attrstring(array(
                        'href' => $nodes[$from]['href'],
                        'tooltip' => $nodes[$from]['label'],
                    ));
                    $graph .= "\"$from\" -> \"$id\" [$attr];\n";
                }
            }
        }
        // Render the graph
        $graph = "<graphviz>\ndigraph G {\nedge [penwidth=2 color=blue];\nsplines=polyline;\n".
            "overlap=false;\nranksep=2;\nrankdir=LR;\ncompound=true;\n$graph\n}\n</graphviz>\n";
        $wgOut->addWikiText($graph);
        $wgOut->addHTML("<pre>$graph</pre>");
        haclfRestoreTitlePatch($patch);
    }

    /**
     * Displays list of all ACL definitions, filtered and loaded using AJAX
     */
    public function html_acllist(&$q)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $haclgContLang;
        $limit = !empty($q['limit']) ? intval($q['limit']) : 100;
        if (empty($q['filter']))
        {
            $q['filter'] = '';
        }
        if (empty($q['offset']))
        {
            $q['offset'] = 0;
        }
        if (!empty($q['types']))
        {
            $types = array_flip(explode(',', $q['types']));
            foreach ($types as $k => &$i)
            {
                $i = true;
            }
            unset($i);
        }
        else
        {
            $types = array();
            foreach ($this->aclTargetTypes as $k => $a)
            {
                foreach ($a as $v => $t)
                {
                    $types[$v] = true;
                }
            }
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

    /**
     * Create/edit ACL definition using interactive editor
     */
    public function html_acl(&$q)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $haclgContLang, $wgContLang, $wgScriptPath;
        $aclTitle = $aclArticle = NULL;
        $aclContent = '{{#manage rights: assigned to = User:'.$wgUser->getName().'}}';
        $aclPEName = $aclPEType = false;
        if (!empty($q['sd']))
        {
            $aclTitle = Title::newFromText($q['sd'], HACL_NS_ACL);
            $defId = IACLDefinition::nameOfPE($aclTitle);
            if ($aclTitle && $defId[0] != IACL::PE_GROUP)
            {
                if (($aclArticle = new Article($aclTitle)) &&
                    $aclArticle->exists())
                {
                    $aclContent = $aclArticle->getContent();
                    $aclSDName = $aclTitle->getText();
                }
                else
                {
                    $aclArticle = NULL;
                }
                list($aclPEType, $aclPEName) = $defId;
            }
        }
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/../templates/HACL_ACLEditor.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        if ($aclArticle)
        {
            $msg = 'hacl_acl_edit';
        }
        elseif ($aclTitle)
        {
            $msg = 'hacl_acl_create_title';
        }
        else
        {
            $msg = 'hacl_acl_create';
        }
        $wgOut->addModules('ext.intraacl.acleditor');
        $wgOut->setPageTitle(wfMsg($msg, $aclTitle ? $aclTitle->getText() : ''));
        $wgOut->addHTML($html);
    }

    /**
     * Manage Quick Access ACL list
     */
    public function html_quickaccess(&$args)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $wgRequest;
        /* Handle save request */
        $args = $wgRequest->getValues();
        $like = empty($args['like']) ? '' : $args['like'];
        if (!empty($args['save']))
        {
            $ids = array();
            $default = NULL;
            foreach ($args as $k => $v)
            {
                if (substr($k, 0, 3) == 'qa_' && $k !== 'qa_default')
                {
                    $ids[] = array_map('intval', explode('-', substr($k, 3), 2));
                }
            }
            $default = !empty($args['qa_default']) ? array_map('intval', explode('-', $args['qa_default'], 2)) : NULL;
            $quickacl = new IACLQuickacl($wgUser->getId(), $ids, $default);
            $quickacl->save();
            // FIXME Terminate MediaWiki more correctly
            wfGetDB(DB_MASTER)->commit();
            header("Location: $wgScript?title=Special:IntraACL&action=quickaccess&like=".urlencode($like));
            exit;
        }
        /* Load data */
        $total = 0;
        $titles = IACLStorage::get('SD')->getSDPages('right', $like, NULL, NULL, NULL, $total);
        $quickacl = IACLQuickacl::newForUserId($wgUser->getId());
        $quickacl_ids = $quickacl->getPEIds();
        $defs = IACLDefinition::newFromTitles($titles);
        $templates = array();
        foreach ($titles as $k => $title)
        {
            if (isset($defs[$k]))
            {
                $pe = array($defs[$k]['pe_type'], $defs[$k]['pe_id']);
                $templates[] = array(
                    'default' => $quickacl->default_pe_id == $pe,
                    'selected' => array_key_exists(implode('-', $pe), $quickacl_ids),
                    'editlink' => $wgScript.'?title=Special:IntraACL&action=acl&sd='.urlencode($title->getText()),
                    'viewlink' => $title->getLocalUrl(),
                    'title' => $title,
                    'id' => implode('-', $pe),
                );
            }
        }
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/../templates/HACL_QuickACL.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        $wgOut->setPageTitle(wfMsg('hacl_qacl_manage'));
        $wgOut->addHTML($html);
    }

    /**
     * Add header with available actions
     */
    public function _actions(&$q)
    {
        global $wgScript, $wgOut, $wgUser;
        $act = $q['action'];
        if ($act == 'acl' && !empty($q['sd']))
        {
            $act = 'acledit';
        }
        elseif ($act == 'group' && !empty($q['group']))
        {
            $act = 'groupedit';
        }
        $html = array();
        $actions = array('acllist', 'acl', 'quickaccess', 'grouplist', 'group');
        if ($this->isAdmin)
        {
            $actions[] = 'rightgraph';
        }
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

    /**
     * Manage groups
     */
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

    /**
     * Create or edit a group
     */
    public function html_group(&$q)
    {
        global $wgOut, $wgUser, $wgScript, $haclgHaloScriptPath, $wgContLang, $haclgContLang;
        $grpTitle = $grpArticle = $grpName = $grpPrefix = NULL;
        if (!empty($q['group']))
        {
            $pe = IACLDefinition::nameOfPE($q['group']);
            $t = Title::newFromText($q['group'], HACL_NS_ACL);
            if ($t && $pe[0] == IACL::PE_GROUP)
            {
                $a = new Article($t);
                if ($a->exists())
                {
                    $grpTitle = $t;
                    $grpArticle = $a;
                    $grpPrefix = $haclgContLang->getPetPrefix(IACL::PE_GROUP);
                    $grpName = $pe[1];
                }
            }
        }
        /* Run template */
        ob_start();
        require(dirname(__FILE__).'/../templates/HACL_GroupEditor.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        $wgOut->addModules('ext.intraacl.groupeditor');
        $wgOut->setPageTitle($grpTitle ? wfMsg('hacl_grp_editing', $grpTitle->getText()) : wfMsg('hacl_grp_creating'));
        $wgOut->addHTML($html);
    }

    /**
     * "Real" ACL list, loaded using AJAX
     */
    static function haclAcllist($t, $n, $offset = 0, $limit = 10)
    {
        global $wgScript, $wgTitle, $haclgHaloScriptPath, $haclgContLang, $wgUser;
        haclCheckScriptPath();
        // Load data
        $spec = SpecialPage::getTitleFor('IntraACL');
        $titles = IACLStorage::get('SD')->getSDPages($t, $n, NULL, $offset, $limit, $total);
        $defs = IACLDefinition::newFromTitles($titles);
        // Build SD data for template
        $lists = array();
        foreach ($titles as $k => $sd)
        {
            $d = array(
                'name' => $sd->getText(),
                'real' => $sd->getText(),
                'editlink' => $spec->getLocalUrl(array('action' => 'acl', 'sd' => $sd->getText())),
                'viewlink' => $sd->getLocalUrl(),
                'single' => NULL,
            );
            $pe = IACLDefinition::nameOfPE($sd);
            $d['type'] = IACL::$typeToName[$pe[0]];
            $d['real'] = $pe[1];
            // Single SD inclusion
            if (isset($defs[$k]) && !empty($defs[$k]['single_child']))
            {
                $s = $defs[$k]['single_child'];
                $name = IACLDefinition::peNameForID($s[0], $s[1]);
                $d['single'] = Title::newFromText(IACLDefinition::nameOfSD($s[0], $name));
                $d['singletype'] = IACL::$typeToName[$s[0]];
                $d['singlename'] = $name;
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
        {
            $nextpage = $pageurl.'&offset='.intval($offset+$limit);
        }
        if ($offset >= $limit)
        {
            $prevpage = $pageurl.'&offset='.intval($offset-$limit);
        }
        // Run template
        ob_start();
        require(dirname(__FILE__).'/../templates/HACL_ACLListContents.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    /**
     * "Real" group list, loaded using AJAX
     */
    static function haclGrouplist($n, $not_n = NULL)
    {
        global $wgScript, $haclgHaloScriptPath, $haclgContLang;
        haclCheckScriptPath();
        /* Load data */
        $total = 0;
        $titles = IACLStorage::get('SD')->getSDPages('group', $n, $not_n, NULL, NULL, $total);
        $groups = array();
        $l = strlen($haclgContLang->getPetPrefix(IACL::PE_GROUP))+1;
        foreach ($titles as $t)
        {
            $groups[] = array(
                'name' => $t->getText(),
                'real' => substr($t->getText(), $l),
                'editlink' => $wgScript.'?title=Special:IntraACL&action=group&group='.urlencode($t->getText()),
                'viewlink' => $t->getLocalUrl(),
            );
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
