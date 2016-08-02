<?php

/**
 * Copyright 2010+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *                  Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 * This file is part of IntraACL MediaWiki extension. License: GPLv3.
 * Homepage: http://wiki.4intra.net/IntraACL
 *
 * Loosely based on HaloACL (c) 2009, ontoprise GmbH
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

class IACLParserFunctionHooks
{
    static $functions = array(
        'access'          => true,
        'predefinedRight' => true,
        'manageRights'    => true,
        'addMember'       => true,
    );

    static function __callStatic($function, $args)
    {
        $parser = array_shift($args);
        if (isset(self::$functions[$function]))
        {
            $pf = IACLParserFunctions::instance($parser);
            if ($pf)
            {
                return $pf->$function($parser, $args);
            }
        }
        return '{{#' . $function . ': ' . implode(', ', $args) . '}}';
    }
}

/**
 * Handles parser functions:
 * - {{#access: ... }}
 * - {{#predefined right: ... }}
 * - {{#manage rights: ... }}
 * - {{#member: ... }}
 * - {{#manage group: ... }}
 */
class IACLParserFunctions
{
    static $instances = array();
    var $title, $peType, $peName;

    // Parsed right definitions and errors are saved here
    var $rules = array(), $hasActions = 0, $errors = array(), $badLinks = array(), $isInterwiki = false;

    // Right definiton object
    var $def = NULL;

    // Parser instance
    static $parser;

    static function newFromTitle($title)
    {
        $pe = IACLDefinition::nameOfPE($title);
        if ($pe)
        {
            $self = new self();
            $self->title = $title;
            list($self->peType, $self->peName) = $pe;
            return $self;
        }
        return false;
    }

    static function instance($parser, $noCreate = false)
    {
        $title = ($parser instanceof Parser ? $parser->mTitle : $parser);
        if (!isset(self::$instances["$title"]))
        {
            return $noCreate ? false : (self::$instances["$title"] = self::newFromTitle($title));
        }
        return self::$instances["$title"];
    }

    static function destroyInstance($instance)
    {
        unset(self::$instances[''.$instance->title]);
    }

    //--- Callbacks for parser functions ---

    /**
     * {{#access: assigned to = User:A, Group:B, *, #, ... | actions = read,edit,create }}
     * Grants <actions> to <assigned to>.
     *
     * @param Parser $parser
     * @return string Wikitext
     */
    public function access(&$parser, $args)
    {
        if ($this->peType == IACL::PE_GROUP)
        {
            return wfMessage('hacl_invalid_parser_function', 'access')->text();
        }

        $params = $this->getParameters($args);

        // handle the parameter 'action'
        list($bitmask, $actions, $em2) = $this->actions($params);

        // handle the parameter 'assigned to'
        list($users, $groups, $em1) = $this->assignedTo($params, 'assigned to', $bitmask);

        $errMsgs = $em1 + $em2;

        // Format the defined right in Wikitext
        $text = wfMessage('hacl_pf_rights_title', implode(', ', $actions))->text();
        $text .= $this->showAssignees(array_keys($users), array_keys($groups));
        $text .= $this->showErrors($errMsgs);

        return $text;
    }

    /**
     * Includes other right definition(s) into this one.
     *
     * Preferred syntax (split by |):
     *   {{#predefined right: ACL:Right/Default | ACL:Page/Begemot}}
     * Deprecated syntax:
     *   {{#predefined right: rights = Right/Default, ...}}
     *
     * @param Parser $parser
     * @return string Wikitext
     */
    public function predefinedRight(&$parser, $args)
    {
        if ($this->peType == IACL::PE_GROUP)
        {
            return wfMessage('hacl_invalid_parser_function', 'predefined right')->text();
        }

        $params = $this->getParameters($args);
        if (!isset($params['rights']))
        {
            // New syntax
            $params = array('rights' => $args);
        }
        else
        {
            $rights = trim($params['rights']);
            $rights = $rights === '' ? array() : explode(',', $rights);
            $params['rights'] = $rights;
        }

        // handle the parameter 'rights'
        list($rights, $errors) = $this->rights($params);

        foreach ($rights as $name => $id)
        {
            if ($id && $id[2] !== NULL)
            {
                $this->rules[$id[0]][$id[2]] = IACL::ACTION_INCLUDE_SD;
            }
        }

        // Format the rights in Wikitext
        $text = wfMessage('hacl_pf_predefined_rights_title')->text();
        $text .= $this->showRights(array_keys($rights));
        $text .= $this->showErrors($errors);

        return $text;
    }

    /**
     * {{#manage rights: assigned to = User:A, Group/B, *, #, ...}}
     * Grants manage right for this definition/group to <assigned to>
     *
     * @param Parser $parser
     * @return string Wikitext
     */
    public function manageRights(&$parser, $args)
    {
        $params = $this->getParameters($args);

        // handle the parameter "assigned to"
        list($users, $groups, $errMsgs) = $this->assignedTo($params, 'assigned to', IACL::ACTION_MANAGE);

        // Format the right managers in Wikitext
        $text = wfMessage('hacl_pf_right_managers_title')->text();
        $text .= $this->showAssignees(array_keys($users), array_keys($groups));
        $text .= $this->showErrors($errMsgs);

        return $text;
    }

    /**
     * {{#member: members = User:A, Group/B, *, #, ...}}
     * Adds <members> to current group.
     *
     * @param Parser $parser
     * @return string Wikitext
     */
    public function addMember(&$parser, $args)
    {
        if ($this->peType != IACL::PE_GROUP)
        {
            return wfMessage('hacl_invalid_parser_function', 'predefined right')->text();
        }

        $params = $this->getParameters($args);

        // handle the parameter "assigned to"
        list($users, $groups, $errMsgs) = $this->assignedTo($params, 'members', IACL::ACTION_GROUP_MEMBER);

        // Format the group members in Wikitext
        $text = wfMessage('hacl_pf_group_members_title')->text();
        $text .= $this->showAssignees(array_keys($users), array_keys($groups));
        $text .= $this->showErrors($errMsgs);

        return $text;
    }

    /**
     * Parses function parameters and returns associative array of them.
     *
     * @param array $args Parser function arguments
     * @return array      Array of argument values indexed by argument names.
     */
    protected function getParameters($args)
    {
        $parameters = array();
        foreach ($args as $arg)
        {
            if (is_string($arg) && preg_match('/^\s*(.*?)\s*=\s*(.*?)\s*$/', $arg, $p))
            {
                $parameters[strtolower($p[1])] = $p[2];
            }
        }
        return $parameters;
    }

    /**
     * Parses user/group lists and assigns $actions to them.
     * I.e. User:A, Group/B, Group:B, *, #
     *
     * @param array  $params    Array of argument values, indexed by argument name
     * @param string $param     Argument to handle
     * @param int    $actions   Action bitmask to save into $this->rules
     * @return array(
     *     array($userName => int $userId | false),
     *     array($groupName => int $groupId | false),
     *     array($error, ...)
     * )
     */
    protected function assignedTo($params, $param, $actions)
    {
        $errors = array();
        $all_reg = array();
        $users = array();
        $groups = array();

        if (!isset($params[$param]))
        {
            // The parameter is missing
            $errors[] = wfMessage('hacl_missing_parameter', $param)->text();
            return array($users, $groups, $errors);
        }

        $etc = haclfDisableTitlePatch();
        $assignedTo = explode(',', $params[$param]);

        // read assigned users and groups
        foreach ($assignedTo as $assignee)
        {
            $assignee = trim($assignee);
            if ($assignee === '*')
            {
                // all users
                $all_reg[IACL::PE_ALL_USERS] = '*';
            }
            elseif ($assignee === '#')
            {
                // registered users
                $all_reg[IACL::PE_REG_USERS] = '#';
            }
            elseif (($t = Title::newFromText($assignee)))
            {
                $assignee = $t->getText();
                if ($t->getNamespace() == NS_USER)
                {
                    $users[$assignee] = false;
                }
                else
                {
                    if ($p = strpos($assignee, ':'))
                    {
                        // Allow Group:X syntax
                        $assignee = substr($assignee, 0, $p) . '/' . substr($assignee, $p+1);
                    }
                    $assignee = str_replace(' ', '_', $assignee);
                    $groups[$assignee] = false;
                }
            }
            else
            {
                $errors[] = wfMessage('hacl_unknown_user', $assignee)->text();
            }
        }
        // Get user IDs in a single pass
        if ($users)
        {
            $check = $users;
            $res = wfGetDB(DB_SLAVE)->select('user', '*', array('user_name' => array_keys($check)));
            foreach ($res as $ur)
            {
                $users[$ur->user_name] = $ur->user_id;
                unset($check[$ur->user_name]);
            }
            foreach ($check as $invalid => $true)
            {
                $errors[] = wfMessage('hacl_unknown_user', $invalid)->text();
                $this->badLinks[] = Title::makeTitleSafe(NS_USER, $invalid);
            }
        }
        // Get group IDs in a single pass
        if ($groups)
        {
            $check = $groups;
            $res = wfGetDB(DB_SLAVE)->select('page', '*', array('page_namespace' => HACL_NS_ACL, 'page_title' => array_keys($groups)));
            foreach ($res as $gr)
            {
                unset($groups[$gr->page_title]);
                $groups[str_replace('_', ' ', $gr->page_title)] = $gr->page_id;
                unset($check[$gr->page_title]);
            }
            foreach ($check as $invalid => $true)
            {
                $errors[] = wfMessage('hacl_unknown_group', $invalid)->text();
                $this->badLinks[] = Title::makeTitleSafe(HACL_NS_ACL, $invalid);
            }
        }
        if (!$users && !$groups && !$all_reg)
        {
            // No users/groups specified at all => add error message
            $errors[] = wfMessage('hacl_missing_parameter_values', $param)->text();
        }
        else
        {
            $this->hasActions |= $actions;
        }
        foreach ($all_reg as $t => $true)
        {
            if (!isset($this->rules[$t][0]))
            {
                $this->rules[$t][0] = $actions;
            }
            else
            {
                $this->rules[$t][0] |= $actions;
            }
        }
        foreach ($users as $name => $id)
        {
            if ($id !== false)
            {
                if (!isset($this->rules[IACL::PE_USER][$id]))
                {
                    $this->rules[IACL::PE_USER][$id] = $actions;
                }
                else
                {
                    $this->rules[IACL::PE_USER][$id] |= $actions;
                }
            }
        }
        foreach ($groups as $name => $id)
        {
            if ($id !== false)
            {
                if (!isset($this->rules[IACL::PE_GROUP][$id]))
                {
                    $this->rules[IACL::PE_GROUP][$id] = $actions;
                }
                else
                {
                    $this->rules[IACL::PE_GROUP][$id] |= $actions;
                }
            }
        }
        haclfRestoreTitlePatch($etc);

        // Append $all_reg to $users for display
        $users += array_flip($all_reg);

        return array($users, $groups, $errors);
    }

    /**
     * Parses action names from 'actions' parameter and returns a bitmask.
     *
     * @param array  $params    Array of argument values, indexed by argument name
     * @return array($bitmask, $actionNames, array($error, ...))
     */
    protected function actions($params)
    {
        global $wgContLang, $haclgContLang;
        $errMsgs = array();
        $bitmask = 0;
        $actions = array();

        $param = 'actions';
        if (!isset($params[$param]))
        {
            // The parameter "actions" is missing.
            $errMsgs[] = wfMessage('hacl_missing_parameter', $param)->text();
            return array($bitmask, $actions, $errMsgs);
        }

        $actions = explode(',', $params[$param]);
        for ($i = 0; $i < count($actions); $i++)
        {
            $actions[$i] = trim($actions[$i]);
            // Check if the action is valid
            $id = $haclgContLang->getActionId($actions[$i]);
            if (!$id)
            {
                $errMsgs[] = wfMessage('hacl_invalid_action', $actions[$i])->text();
            }
            else
            {
                $bitmask |= $id;
            }
        }
        if (!$actions)
        {
            $errMsgs[] = wfMessage('hacl_missing_parameter_values', $param)->text();
        }

        return array($bitmask, $actions, $errMsgs);
    }

    /**
     * Parses names of right definitions.
     *
     * @param array  $params    Array of argument values, indexed by argument name
     * @return array(array($name => array($peType, $peID), ...), array($error, ...))
     */
    protected function rights($params)
    {
        global $wgContLang, $haclgContLang;
        $errMsgs = array();
        $rights = array();

        $param = 'rights';
        if (!isset($params[$param]))
        {
            // The parameter "rights" is missing
            $errMsgs[] = wfMessage('hacl_missing_parameter', $param)->text();
            return array($rights, $errMsgs);
        }

        $result = array();
        foreach ($params[$param] as $r)
        {
            $r = trim($r);
            if ($r)
            {
                // FIXME: resolve multiple IDs at once
                $result[$r] = IACLDefinition::nameOfPE($r);
                if ($result[$r])
                {
                    $result[$r][2] = IACLDefinition::peIDforName($result[$r][0], $result[$r][1]);
                    $subt = Title::newFromText(IACLDefinition::nameOfSD($result[$r][0], $result[$r][1]));
                    if ($result[$r][2] === NULL || !$subt->exists())
                    {
                        $this->badLinks[] = $subt;
                        $errMsgs[] = wfMessage('hacl_invalid_predefined_right', $subt)->text();
                    }
                }
                else
                {
                    $errMsgs[] = wfMessage('hacl_invalid_predefined_right', $r)->text();
                }
            }
        }
        if (!$result)
        {
            $errMsgs[] = wfMessage('hacl_missing_parameter_values', $param)->text();
        }

        return array($result, $errMsgs);
    }

    //--- MediaWiki Hooks ---

    /**
     * We need to create a work instance to display consistency checks
     * during display of an article
     */
    public static function ArticleViewHeader(&$article, &$outputDone, &$pcache)
    {
        if ($article->getTitle()->getNamespace() == HACL_NS_ACL)
        {
            global $haclgHaloScriptPath, $wgOut;
            // Disable parser cache
            $pcache = false;
            // Warn for non-canonical titles
            // FIXME We need to canonicalize special page names!
            $self = self::instance($article->getTitle());
            if (!$self)
            {
                return true;
            }
            $editor = true;
            $self->makeDef();
            $html = '';
            $sdName = self::getCanonicalDefTitle($self->title);
            $old = $self->title->getPrefixedText();
            if ($sdName !== NULL && $sdName != $old)
            {
                if (!$article->exists())
                {
                    $editor = false;
                    $html .= '<div class="error"><p>'.
                        wfMessage('hacl_non_canonical_acl_new', Title::newFromText($sdName)->getLocalUrl(), $sdName, $old)->text().'</p></div>';
                }
                else
                {
                    $html .= '<div class="error"><p>'.
                        wfMessage('hacl_non_canonical_acl', SpecialPage::getTitleFor('MovePage', $old)
                        ->getLocalUrl(array('wpLeaveRedirect' => 0, 'wpNewTitle' => $sdName)), $sdName, $old)->text().'</p></div>';
                }
            }
            // Add "Create/edit with IntraACL editor" link
            if ($editor && $article->getTitle()->userCan('edit'))
            {
                $html .= wfMessage($self->def && $self->def->clean() ? 'hacl_edit_with_special' : 'hacl_create_with_special',
                    Title::newFromText('Special:IntraACL')->getLocalUrl(array(
                        'action' => ($self->peType == IACL::PE_GROUP ? 'group' : 'acl'),
                        ($self->peType == IACL::PE_GROUP ? 'group' : 'sd') => $self->title->getPrefixedText(),
                    )),
                    $haclgHaloScriptPath . '/skins/images/edit.png')->text();
                if ($self->peType == IACL::PE_CATEGORY)
                {
                    // Add "This is category ACL, see category page ACL here"
                    $pa = Title::newFromText(IACLDefinition::nameOfSD(IACL::PE_PAGE, 'Category:'.$self->peName));
                    $html .= wfMessage('hacl_category_acl_shown', $pa->getPrefixedText(), $pa->getLocalUrl(), $haclgHaloScriptPath . '/skins/images/warn.png')->text();
                }
                elseif ($self->peType == IACL::PE_PAGE)
                {
                    $peTitle = Title::newFromText($self->peName);
                    if ($peTitle->getNamespace() == NS_CATEGORY)
                    {
                        // Add "This is category page ACL, see category ACL here"
                        $pa = Title::newFromText(IACLDefinition::nameOfSD(IACL::PE_CATEGORY, $peTitle->getText()));
                        $html .= wfMessage('hacl_category_page_acl_shown', $pa->getPrefixedText(), $pa->getLocalUrl(), $haclgHaloScriptPath . '/skins/images/warn.png')->text();
                    }
                    elseif (MWNamespace::hasSubpages($peTitle->getNamespace()))
                    {
                        // Add "This is page ACL, see tree ACL here"
                        $pa = Title::newFromText(IACLDefinition::nameOfSD(IACL::PE_TREE, $peTitle->getText()));
                        $html .= wfMessage('hacl_page_acl_shown', $pa->getPrefixedText(), $pa->getLocalUrl(), $haclgHaloScriptPath . '/skins/images/warn.png')->text();
                    }
                }
                elseif ($self->peType == IACL::PE_TREE)
                {
                    // Add "This is tree ACL, see page ACL here"
                    $peTitle = Title::newFromText($self->peName);
                    $pa = Title::newFromText(IACLDefinition::nameOfSD(IACL::PE_PAGE, $peTitle->getText()));
                    $html .= wfMessage('hacl_tree_acl_shown', $pa->getPrefixedText(), $pa->getLocalUrl(), $haclgHaloScriptPath . '/skins/images/warn.png')->text();
                }
            }
            $wgOut->addHTML($html);
        }
        return true;
    }

    /**
     * Returns a canonical definition title for $title, even
     * if the protected page does not exist
     */
    protected static function getCanonicalDefTitle($title)
    {
        static $cache = array();
        if (array_key_exists("$title", $cache))
        {
            return $cache["$title"];
        }
        $pe = IACLDefinition::nameOfPE($title);
        if ($pe)
        {
            $peName = false;
            if ($pe[0] == IACL::PE_PAGE)
            {
                // Pages may contain namespace name, and we want to redirect
                // from a non-canonical name even the page itself does not exist
                $t = Title::newFromText($pe[1]);
                if ($t->getInterwiki())
                {
                    // No protection can be applied to interwiki links!
                    return $cache["$title"] = NULL;
                }
                if ($t)
                {
                    $peName = ($t->getNamespace() ? iaclfCanonicalNsText($t->getNamespace()).':' : '') . $t->getText();
                }
            }
            else
            {
                $peID = IACLDefinition::peIDforName($pe[0], $pe[1]);
                if ($peID !== NULL)
                {
                    $peName = IACLDefinition::peNameForID($pe[0], $peID);
                }
            }
            if ($peName)
            {
                return $cache["$title"] = IACLDefinition::nameOfSD($pe[0], $peName);
            }
        }
        return $cache["$title"] = NULL;
    }

    /**
     * Redirect to canonical ACL page from a non-canonical one if the latter doesn't exist
     */
    public static function initializeArticleMaybeRedirect(&$title, &$request, &$ignoreRedirect, &$target, &$article)
    {
        if ($title->getNamespace() == HACL_NS_ACL && !$article->exists())
        {
            $sdName = self::getCanonicalDefTitle($title);
            if ($sdName !== NULL && $sdName != $title->getPrefixedText())
            {
                // Use $article instead of $target because MW doesn't redirect
                // when $target does not exist
                $article = new Article(Title::newFromText($sdName));
                $article->setRedirectedFrom($title);
                return false;
            }
        }
        return true;
    }

    /**
     * This method is called just before an article's HTML is sent to the
     * client. If the article corresponds to any ACL definitions, their consistency
     * is checked and error messages are added to the article.
     *
     * @param unknown_type $out
     * @param unknown_type $text
     * @return bool true
     */
    public static function ArticleViewFooter($article)
    {
        global $wgOut;
        $html = self::instance($article->getTitle(), true);
        if ($html)
        {
            $html = $html->consistencyCheckStatus();
            if ($html)
            {
                $wgOut->addHTML($html);
            }
        }
        return true;
    }

    /**
     * ArticleEditUpdates hook is used in MediaWiki 1.14+.
     */
    public static function ArticleEditUpdates($article, $editInfo, $changed)
    {
        return self::updateDefinition($article);
    }

    /**
     * This method is called, after an article has been saved. If the article
     * belongs to the namespace ACL (i.e. a right, SD, group)
     * its content is transferred to the database.
     *
     * @param WikiPage $article
     */
    public static function updateDefinition($article)
    {
        $title = $article->getTitle();
        if ($title->getNamespace() == HACL_NS_ACL)
        {
            $self = self::instance($title, true);
            // Here we do not rely on the article being parsed during save.
            // FIXME Actually, we can rely on it, as we're in ArticleEditUpdates hook.
            //       But... earlier we relied on it and we had bugs...
            if (!$self)
            {
                $self = self::instance($title);
                if (!$self)
                {
                    return true;
                }
                self::parse($article->getText(), $title);
            }
            // TODO Remove incorrect definitions from the DB?
            $self->makeDef();
            if (!$self->def && ($self->peType == IACL::PE_GROUP || $self->peType == IACL::PE_RIGHT))
            {
                throw new Exception(
                    '[BUG] Something strange: article "'.$title.'" does not exist just after saving!'.
                    ' Maybe title contains an invalid UTF-8 sequence and should be deleted from the database?'
                );
            }
            // Prevent overwriting canonical definitions with non-canonical ones
            $canonical = ($self->def && $self->def['def_title']->getPrefixedText() === $title->getPrefixedText());
            if ($self->def && ($canonical || !$self->def['def_title']->exists()))
                $self->def->save();
            $self->saveBadlinks($title);
            self::destroyInstance($self);
        }
        self::refreshBadlinks($title);
        return true;
    }

    /**
     * Create or update the definition object
     */
    protected function makeDef()
    {
        if ($this->def === NULL)
        {
            $this->def = IACLDefinition::newFromName($this->peType, $this->peName, true);
            if (!$this->def)
            {
                $this->def = false;
            }
            if (!$this->def && ($this->peType == IACL::PE_PAGE ||
                $this->peType == IACL::PE_TREE || $this->peType == IACL::PE_CATEGORY))
            {
                $title = $this->peType == IACL::PE_CATEGORY
                    ? Title::makeTitleSafe(NS_CATEGORY, $this->peName)
                    : Title::newFromText($this->peName);
                if (!$title->getInterwiki())
                {
                    // Save PE itself into bad links
                    $this->badLinks[] = $title;
                }
                else
                {
                    // This is an interwiki title!
                    $this->isInterwiki = true;
                }
            }
        }
        // Overwrite rules
        if ($this->def)
        {
            $this->def = $this->def->dirty();
            if ($this->isUnprotectable())
            {
                // This namespace can not be protected
                $this->def['rules'] = array();
            }
            else
            {
                $this->def['rules'] = $this->rules;
            }
        }
    }

    /**
     * Does current PE belong to an unprotectable namespace?
     */
    protected function isUnprotectable()
    {
        global $haclgUnprotectableNamespaceIds;
        return $haclgUnprotectableNamespaceIds &&
            ($this->peType == IACL::PE_NAMESPACE && isset($haclgUnprotectableNamespaceIds[$this->def['pe_id']]) ||
            $this->peType == IACL::PE_PAGE && isset($haclgUnprotectableNamespaceIds[Title::newFromText($this->peName)->getNamespace()]) ||
            $this->peType == IACL::PE_CATEGORY && isset($haclgUnprotectableNamespaceIds[NS_CATEGORY]));
    }

    /**
     * Save $this->badLinks into the DB
     */
    protected function saveBadlinks($title)
    {
        $dbw = wfGetDB(DB_SLAVE);
        $rows = array();
        $id = $title->getArticleId();
        foreach ($this->badLinks as $bl)
        {
            $rows[] = array(
                'bl_from' => $id,
                'bl_namespace' => $bl->getNamespace(),
                'bl_title' => $bl->getDBkey(),
            );
        }
        $dbw->delete('intraacl_badlinks', array('bl_from' => $id), __METHOD__);
        if ($rows)
        {
            $dbw->insert('intraacl_badlinks', $rows, __METHOD__);
        }
    }

    /**
     * Reparse right definitions that tried to use $title while it did not exist
     */
    protected static function refreshBadlinks($title)
    {
        $etc = haclfDisableTitlePatch();
        $dbw = wfGetDB(DB_SLAVE);
        $bad = array(
            'bl_namespace' => $title->getNamespace(),
            'bl_title' => $title->getDBkey(),
        );
        $res = $dbw->select(array('p' => 'page', 'intraacl_badlinks'), 'p.*', $bad + array(
            'bl_from=page_id'
        ), __METHOD__);
        foreach ($res as $row)
        {
            $t = Title::newFromRow($row);
            $page = new WikiPage($t);
            $page->doEdit($page->getText(), 'Re-parse definition with bad links', EDIT_UPDATE);
        }
        $dbw->delete('intraacl_badlinks', $bad, __METHOD__);
        haclfRestoreTitlePatch($etc);
    }

    /**
     * Completely remove definition (when article is deleted or cleared)
     */
    public static function removeDef($title)
    {
        $def = IACLDefinition::newFromTitle($title, false);
        $dbw = wfGetDB(DB_MASTER);
        $dbw->delete('intraacl_badlinks', array('bl_from' => $title->getArticleId()), __METHOD__);
        if ($def)
        {
            $badLinks = array();
            foreach ($def['parents'] as $p)
            {
                $def_title = $p['def_title'];
                // FIXME NULL may happen here for SDs whose articles were already deleted...
                if ($def_title)
                {
                    $badLinks[] = array(
                        'bl_from' => $def_title->getArticleId(),
                        'bl_namespace' => $title->getNamespace(),
                        'bl_title' => $title->getDBkey(),
                    );
                }
            }
            if ($badLinks)
            {
                $dbw->insert('intraacl_badlinks', $badLinks, __METHOD__);
            }
            IACLQuickacl::deleteForSD($def['pe_type'], $def['pe_id']);
            $def['rules'] = array();
            $def->save();
        }
    }

    /**
     * This method is called, when an article is deleted. If the article
     * belongs to the namespace ACL (i.e. a right, SD, group)
     * its removal is reflected in the database.
     *
     * @param Article $article
     * @param User $user
     * @param string $reason
     */
    public static function articleDelete(&$article, &$user, &$reason)
    {
        // The article is in the ACL namespace?
        $title = $article->getTitle();
        if ($title->getNamespace() == HACL_NS_ACL)
        {
            // Remove definition
            self::removeDef($title);
        }
        else
        {
            // Remove rules for non-existing PE
            $id = $article->getTitle()->getArticleID();
            IACLStorage::get('SD')->deleteRules(array(array(
                'pe_type IN ('.IACL::PE_PAGE.', '.IACL::PE_CATEGORY.', '.IACL::PE_TREE.')',
                'pe_id' => $id,
            )));
            // Add the removed protected element into bad links
            $pagesd = IACLDefinition::getSDForPE(IACL::PE_PAGE, $id);
            $catsd = IACLDefinition::getSDForPE(IACL::PE_CATEGORY, $id);
            $treesd = IACLDefinition::getSDForPE(IACL::PE_TREE, $id);
            $badLinks = array();
            foreach (array($pagesd, $catsd, $treesd) as $sd)
            {
                if ($sd)
                {
                    $badLinks[] = array(
                        'bl_from' => $sd['def_title']->getArticleId(),
                        'bl_namespace' => $title->getNamespace(),
                        'bl_title' => $title->getDBkey(),
                    );
                }
            }
            if ($badLinks)
            {
                $dbw = wfGetDB(DB_MASTER);
                $dbw->insert('intraacl_badlinks', $badLinks, __METHOD__);
            }
        }
        return true;
    }

    /**
     * This method is called, after an article is moved. If the article has a
     * security descriptor of type page, the SD is moved accordingly.
     *
     * @param Title $oldTitle
     * @param Title $newTitle
     * @param User $user
     * @param int $pageid
     * @param int $redirid
     */
    public static function TitleMoveComplete($oldTitle, $newTitle, $user, $pageid, $redirid)
    {
        global $wgVersion;

        if ($oldTitle->getNamespace() == HACL_NS_ACL)
        {
            // Move definition data!
            $old = IACLDefinition::newFromTitle($oldTitle, false);
            $new = false;
            if ($old)
            {
                $rules = $old['rules'];
                $old['rules'] = array();
                $old->save();
                $new = IACLDefinition::newFromTitle($newTitle, true);
            }
            if ($old && $new)
            {
                $new['rules'] = $rules;
                $new->save();
            }
            elseif (version_compare($wgVersion, '1.19', '<'))
            {
                // Before 1.19, Title::moveTo() doesn't do ArticleEditUpdates, do it manually
                self::updateDefinition(new WikiPage($newTitle));
            }
            self::refreshBadlinks($newTitle);
            return true;
        }

        // Check if the old title has page SD
        $oldSDTitle = Title::newFromText(IACLDefinition::nameOfSD(IACL::PE_PAGE, $oldTitle));
        if ($oldSDTitle->exists())
        {
            // Move SD for page
            $newSDTitle = Title::newFromText(IACLDefinition::nameOfSD(IACL::PE_PAGE, $newTitle));
            wfDebug("Move SD for page: $oldSDTitle -> $newSDTitle\n");
            if ($newSDTitle->exists() && $newSDTitle->userCan('delete'))
            {
                $page = new WikiPage($newSDTitle);
                $page->doDeleteArticle(wfMessage('hacl_move_acl')->text());
            }
            else
            {
                // FIXME report "permission denied to overwrite $to"
            }
            $oldSDTitle->moveTo($newSDTitle, false, wfMessage('hacl_move_acl')->text(), true);
        }
        self::refreshBadlinks($newTitle);

        return true;
    }

    /**
     * Parse wikitext inside a separate parser to overcome its non-reenterability
     */
    public static function parse($text, $title)
    {
        global $wgParser;
        if (!self::$parser)
        {
            self::$parser = clone $wgParser;
        }
        $options = new ParserOptions();
        self::$parser->parse($text, $title, $options);
    }

    /**
     * Return HTML consistency check status for pages in ACL namespace
     */
    public function consistencyCheckStatus($asHtml = true)
    {
        global $haclgContLang, $haclgHaloScriptPath;
        $msg = array();
        $this->makeDef();
        if ($this->errors)
        {
            $msg[] = wfMessage('hacl_errors_in_definition')->text();
        }
        $sdName = self::getCanonicalDefTitle($this->title);
        if ($sdName !== NULL && $sdName != $this->title->getPrefixedText())
        {
            $msg[] = wfMessage('hacl_non_canonical_acl_short', $sdName)->text();
        }
        if ($this->isUnprotectable())
        {
            // This namespace can not be protected
            $msg[] = wfMessage('hacl_unprotectable_namespace')->text();
        }
        if ($this->title->exists() && !$this->rules)
        {
            if ($this->peType == IACL::PE_GROUP)
            {
                $msg[] = wfMessage('hacl_group_must_have_members')->text();
            }
            else
            {
                $msg[] = wfMessage('hacl_right_must_have_rights')->text();
            }
        }
        if (!$this->def)
        {
            if ($this->isInterwiki)
            {
                $msg[] = wfMessage('hacl_pe_is_interwiki', $this->peName)->text();
            }
            else
            {
                $msg[] = wfMessage('hacl_pe_not_exists', $this->peName)->text();
            }
        }
        else
        {
            list($del, $add) = $this->def->diffRules();
            if ($del || $add)
            {
                // TODO Show inconsistency details
                $msg[] = wfMessage('hacl_acl_element_inconsistent')->text();
            }
        }
        if (!$asHtml)
        {
            return $msg;
        }
        // Merge errors into HTML text
        $html = '';
        if ($msg)
        {
            $html .= wfMessage('hacl_consistency_errors')->text();
            $html .= "<ul>";
            foreach ($msg as $m)
            {
                $html .= "<li>$m</li>";
            }
            $html .= "</ul>";
        }
        return $html;
    }

    /**
     * Formats the wikitext for displaying assignees of a right or members of a group.
     *
     * @param array(string) $users  User names
     * @param array(string) $groups Group names
     * @param bool $isAssignedTo
     *         true  => output for "assignedTo"
     *         false => output for "members"
     * @return string       Formatted wikitext with users and groups
     */
    function showAssignees($users, $groups, $isAssignedTo = true)
    {
        $text = "";
        if ($users)
        {
            global $wgContLang;
            $userNS = $wgContLang->getNsText(NS_USER);
            $text .= $isAssignedTo
                ? ':;'.wfMessage('hacl_assigned_user')->text()
                : ':;'.wfMessage('hacl_user_member')->text();
            foreach ($users as &$u)
            {
                if ($u == '*')
                {
                    $u = wfMessage('hacl_all_users')->text();
                }
                elseif ($u == '#')
                {
                    $u = wfMessage('hacl_registered_users')->text();
                }
                else
                {
                    $u = "[[$userNS:$u|$u]]";
                }
            }
            $text .= implode(', ', $users);
            $text .= "\n";
        }
        if ($groups)
        {
            global $wgContLang;
            $aclNS = $wgContLang->getNsText(HACL_NS_ACL);
            $text .= $isAssignedTo
                ? ':;'.wfMessage('hacl_assigned_groups')->text()
                : ':;'.wfMessage('hacl_group_member')->text();
            $first = true;
            foreach ($groups as $g)
            {
                if (!$first)
                {
                    $text .= ', ';
                }
                else
                {
                    $first = false;
                }
                $text .= "[[$aclNS:$g|$g]]";
            }
            $text .= "\n";
        }
        return $text;
    }

    /**
     * Formats the wikitext for displaying the error messages of a parser function.
     *
     * @param array(string) $messages Error messages
     * @return string Wikitext
     */
    function showErrors($messages)
    {
        $text = "";
        if ($messages)
        {
            if (!$this->errors)
            {
                $text .= "\n:;".wfMessage('hacl_error')->text().
                    wfMessage('hacl_will_not_work_as_expected')->text();
            }
            $text .= "\n:*".implode("\n:*", $messages);
        }
        $this->errors = array_merge($this->errors, $messages);
        return $text;
    }

    /**
     * Formats the wikitext for displaying included rights.
     *
     * @param array(string) $rights
     *         An array of rights. May be empty.
     * @param bool $addACLNS
     *         If <true>, the ACL namespace is added to the pages if it is missing.
     *
     * @return string
     *         A formatted wikitext with all rights.
     */
    function showRights($rights, $addACLNS = true)
    {
        global $wgContLang;
        $aclNS = $wgContLang->getNsText(HACL_NS_ACL);
        $text = "";
        foreach ($rights as $name => $r)
        {
            // Rights can be given without the namespace "ACL". However, the
            // right should be linked correctly. So if the namespace is missing,
            // the link is adapted.
            if (strpos($r, $aclNS) !== 0 && $addACLNS)
            {
                $r = "$aclNS:$r|$r";
            }
            $text .= "* [[$r]]\n";
        }
        return $text;
    }
}
