<?php

/* Copyright 2010+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *                  Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 * This file is part of IntraACL MediaWiki extension. License: GPLv3.
 * http://wiki.4intra.net/IntraACL
 * $Id$
 *
 * Loosely based on HaloACL (c) 2009, ontoprise GmbH
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

if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

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
    var $rules = array(), $hasActions = 0, $errors = array();

    // Parser instance
    static $parser;

    function __construct($title)
    {
        $this->title = $title;
        list($this->peName, $this->peType) = IACLDefinition::nameOfPE($title);
    }

    static function instance($parser, $noCreate = false)
    {
        $title = ($parser instanceof Parser ? $parser->mTitle : $parser);
        if (!isset(self::$instances["$title"]))
        {
            return $noCreate ? false : (self::$instances["$title"] = new self($title));
        }
        return self::$instances["$title"];
    }

    static function destroyInstance($instance)
    {
        unset(self::$instances[''.$instance->title]);
    }

    static function access($parser)
    {
        $args = func_get_args();
        return self::instance($parser)->_access($parser, $args);
    }

    static function predefinedRight($parser)
    {
        $args = func_get_args();
        return self::instance($parser)->_predefinedRight($parser, $args);
    }

    static function manageRights($parser)
    {
        $args = func_get_args();
        return self::instance($parser)->_manageRights($parser, $args);
    }

    static function addMember($parser)
    {
        $args = func_get_args();
        return self::instance($parser)->_addMember($parser, $args);
    }

    static function manageGroup($parser)
    {
        $args = func_get_args();
        // Same handler as for manageRights
        return self::instance($parser)->_manageRights($parser, $args);
    }

    //--- Callbacks for parser functions ---

    /**
     * {{#access: assigned to = User:A, Group:B, *, #, ... | actions = read,edit,create }}
     * Grants <actions> to <assigned to>.
     *
     * @param Parser $parser
     * @return string Wikitext
     */
    public function _access(&$parser, $args)
    {
        if ($this->peType == IACL::PE_GROUP)
        {
            return wfMsgForContent('hacl_invalid_parser_function', 'access');
        }

        $params = $this->getParameters($args);

        // handle the parameter 'action'
        list($bitmask, $actions, $em2) = $this->actions($params);

        // handle the parameter 'assigned to'
        list($users, $groups, $em1) = $this->assignedTo($params, 'assigned to', $bitmask);

        $errMsgs = $em1 + $em2;
        if ($errMsgs)
        {
            $this->mDefinitionValid = false;
        }

        // Format the defined right in Wikitext
        $text = wfMsgForContent('hacl_pf_rights_title', implode(', ', $actions));
        $text .= $this->showAssignees(array_keys($users), array_keys($groups));
        $text .= $this->showErrors($errMsgs);

        return $text;
    }

    /**
     * {{#predefined right: rights = Right/Default, ...}}
     * Includes other right definitions into this one.
     *
     * @param Parser $parser
     * @return string Wikitext
     */
    public function _predefinedRight(&$parser, $args)
    {
        if ($this->peType == IACL::PE_GROUP)
        {
            return wfMsgForContent('hacl_invalid_parser_function', 'predefined right');
        }

        $params = $this->getParameters($args);

        // handle the parameter 'rights'
        list($rights, $errors) = $this->rights($params);

        foreach ($rights as $name => $id)
        {
            if ($id)
            {
                $this->rules[$id[0]][$id[1]] = IACL::ACTION_INCLUDE_SD;
            }
            else
            {
                $this->mDefinitionValid = false;
                $em[] = wfMsgForContent('hacl_invalid_predefined_right', $name);
            }
        }

        // Format the rights in Wikitext
        $text = wfMsgForContent('hacl_pf_predefined_rights_title');
        $text .= $this->showRights(array_keys($rights));
        $text .= $this->showErrors($em);

        return $text;
    }

    /**
     * {{#manage rights: assigned to = User:A, Group/B, *, #, ...}}
     * Grants manage right for this definition/group to <assigned to>
     *
     * @param Parser $parser
     * @return string Wikitext
     */
    public function _manageRights(&$parser, $args)
    {
        $params = $this->getParameters($args);

        // handle the parameter "assigned to"
        list($users, $groups, $errMsgs) = $this->assignedTo($params, 'assigned to', IACL::RIGHT_MANAGE);

        if ($errMsgs)
        {
            $this->mDefinitionValid = false;
        }

        // Format the right managers in Wikitext
        $text = wfMsgForContent('hacl_pf_right_managers_title');
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
    public function _addMember(&$parser, $args)
    {
        if ($this->peType != IACL::PE_GROUP)
        {
            return wfMsgForContent('hacl_invalid_parser_function', 'predefined right');
        }

        $params = $this->getParameters($args);

        // handle the parameter "assigned to"
        list($users, $groups, $errMsgs) = $this->assignedTo($params, 'members', IACL::RIGHT_GROUP_MEMBER);

        if ($errMsgs)
        {
            $this->mDefinitionValid = false;
        }

        // Format the group members in Wikitext
        $text = wfMsgForContent('hacl_pf_group_members_title');
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
        $users = array();
        $groups = array();

        if (!isset($params[$param]))
        {
            // The parameter is missing
            $errors[] = wfMsgForContent('hacl_missing_parameter', $param);
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
                $users['*'] = IACL::ALL_USERS;
            }
            elseif ($assignee === '#')
            {
                // registered users
                $users['#'] = IACL::REGISTERED_USERS;
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
                    $groups[$assignee] = false;
                }
            }
            else
            {
                $errors[] = wfMsgForContent('hacl_unknown_user', $assignee);
            }
        }
        // Get user IDs in a single pass
        if ($users)
        {
            $check = $users;
            unset($check['*']);
            unset($check['#']);
            $res = wfGetDB(DB_SLAVE)->select('user', '*', array('user_name' => array_keys($check)));
            foreach ($res as $ur)
            {
                $users[$ur->user_name] = $ur->user_id;
                unset($check[$ur->user_name]);
            }
            foreach ($check as $invalid)
            {
                $errors[] = wfMsgForContent('hacl_unknown_user', $invalid);
            }
        }
        // Get group IDs in a single pass
        if ($groups)
        {
            $check = $groups;
            $res = wfGetDB(DB_SLAVE)->select('page', '*', array('page_namespace' => HACL_NS_ACL, 'page_title' => array_keys($groups)));
            foreach ($res as $gr)
            {
                $groups[$gr->page_title] = $gr->page_id;
                unset($check[$gr->page_title]);
            }
            foreach ($check as $invalid)
            {
                $errors[] = wfMsgForContent('hacl_unknown_group', $invalid);
            }
        }
        if (!$users && !$groups)
        {
            // No users/groups specified at all => add error message
            $errors[] = wfMsgForContent('hacl_missing_parameter_values', $param);
        }
        foreach ($users as $name => $id)
        {
            if ($id !== false)
            {
                $this->rules[IACL::RULE_USER][$id] |= $actions;
                $this->hasActions |= $actions;
            }
        }
        foreach ($groups as $name => $id)
        {
            if ($id !== false)
            {
                $this->rules[IACL::RULE_GROUP][$id] |= $actions;
                $this->hasActions |= $actions;
            }
        }
        haclfRestoreTitlePatch($etc);

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
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter', $param);
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
                $errMsgs[] = wfMsgForContent('hacl_invalid_action', $actions[$i]);
            }
            else
            {
                $bitmask |= $id;
            }
        }
        if (!$actions)
        {
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter_values', $param);
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
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter', $param);
            return array($rights, $errMsgs);
        }

        $rights = trim($params[$param]);
        $rights = $rights === '' ? array() : explode(',', $rights);
        $result = array();
        foreach ($rights as $r)
        {
            $r = trim($r);
            if ($r)
            {
                list($peName, $peType) = IACLDefinition::nameOfPE();
                $result[$r] = [ $peType, $peName ];
            }
        }
        if (!$result)
        {
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter_values', $param);
        }

        return array($rights, $errMsgs);
    }

    //--- MediaWiki Hooks ---

    /**
     * We need to create a work instance to display consistency checks
     * during display of an article;
     */
    public static function articleViewHeader(&$article, &$outputDone, &$pcache)
    {
        // TODO make it different: disable cache for ACL
        if ($article->getTitle()->getNamespace() == HACL_NS_ACL)
        {
            $pcache = false;
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
    public static function outputPageBeforeHTML(&$out, &$text)
    {
        global $haclgContLang, $wgTitle;
        $html = self::instance($wgTitle, false);
        if ($html)
            $html = $html->consistencyCheckHtml();
        if ($html)
            $out->addHTML($html);
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
     * @param Article $article
     */
    public static function updateDefinition($article)
    {
        $title = $article->getTitle();
        if ($title->getNamespace() == HACL_NS_ACL)
        {
            $self = self::instance($title, true);
            if (!$self)
            {
                $self = self::instance($title);
                self::parse($article->getContent(), $title);
            }
            $id = IACLDefinition::peIDforName($self->peName, $self->peType);
            if ($id)
            {
                $def = IACLDefinition::select(array('pe' => array($self->peType, $id)));
                if ($def)
                {
                    $def = reset($def);
                }
                else
                {
                    $def = IACLDefinition::newEmpty();
                    $def['pe_type'] = $self->peType;
                    $def['pe_id'] = $id;
                }
                $def['rights'] = $self->rules;
                $def->save();
            }
            self::destroyInstance($self);
        }
        return true;
    }

    /* Also do handle article undeletes */
    public static function articleUndelete(&$title, $isnew)
    {
        if ($title->getNamespace() == HACL_NS_ACL)
        {
            $article = new Article($title);
            self::updateDefinition($article);
        }
        return true;
    }

    /* Remove definition completely (used with article delete or clear) */
    public static function removeDef($title)
    {
        $type = HACLEvaluator::hacl_type($title);
        if ($type == 'group')
            self::removeGroup($title);
        elseif ($type)
        {
            // It is a right or security descriptor
            if ($sd = HACLSecurityDescriptor::newFromID($title->getArticleId(), false))
            {
                // Check access
                if (!$sd->userCanModify())
                {
                    wfDebug(__METHOD__.": INCONSISTENCY! Article '$title' deleted, but corresponding SD remains, because userCanModify() = false\n");
                    return false;
                }
                // Delete SD permanently
                $sd->delete();
            }
        }
    }

    /**
     * This method is called, when an article is deleted. If the article
     * belongs to the namespace ACL (i.e. a right, SD, group)
     * its removal is reflected in the database.
     *
     * @param unknown_type $article
     * @param unknown_type $user
     * @param unknown_type $reason
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
            // If a protected article is deleted, its SD will be deleted as well
            $sdID = HACLSecurityDescriptor::getSDForPE(
                $article->getTitle()->getArticleID(),
                HACLLanguage::PET_PAGE);
            if ($sdID)
            {
                $t = Title::newFromID($sdID);
                if ($t)
                {
                    $a = new Article($t);
                    $a->doDelete("");
                }
                else
                {
                    // SD article is already deleted somehow, but SD remains (DB inconsistency), delete it
                    if ($sd = HACLSecurityDescriptor::newFromID($sd, false))
                    {
                        $sd->delete();
                        wfDebug("DB INCONSISTENCY: $t already deleted, but corresponding SD remained, removing.\n");
                    }
                }
            }
        }
        return true;
    }

    /**
     * This method is called, after an article is moved. If the article has a
     * security descriptor of type page, the SD is moved accordingly.
     *
     * @param unknown_type $oldTitle
     * @param unknown_type $newTitle
     */
    public static function TitleMoveComplete($oldTitle, $newTitle, $wgUser, $pageid, $redirid)
    {
        if ($oldTitle->getNamespace() == HACL_NS_ACL)
            return true;
        $newName = $newTitle->getFullText();

        // Check if the old title has an SD
        $sd = HACLSecurityDescriptor::getSDForPE($pageid, HACLLanguage::PET_PAGE);
        if ($sd !== false)
        {
            // move SD for page
            wfDebug("Move SD for page: ID=$sd, pageid=$pageid\n");
            $oldSD = Title::newFromID($sd);
            $newSD = HACLSecurityDescriptor::nameOfSD($newName,
                HACLLanguage::PET_PAGE);
            self::move($oldSD, $newSD);
        }

        return true;
    }

    /**
     * Moves the SD content from $from to $to, and overwrites
     * the source article with single PR inclusion of target to protect
     * old revisions of source article (needed if there's a redirect left).
     *
     * We must use Title::moveTo() here to preserve the ID of old SD...
     *
     * @param string $from
     *        Original name of the SD article.
     * @param string $to
     *        New name of the SD article.
     */
    static function move($from, $to)
    {
        // TODO incorrect
        wfDebug(__METHOD__.": move SD requested from $from to $to\n");
        $etc = haclfDisableTitlePatch();
        if (!is_object($from))
            $from = Title::newFromText($from);
        if (!is_object($to))
            $to = Title::newFromText($to);
        haclfRestoreTitlePatch($etc);
        if ($to->exists() && $to->userCan('delete'))
        {
            // FIXME report about "permission denied to overwrite $to"
            $page = new Article($to);
            $page->doDeleteArticle(wfMsg('hacl_move_acl'));
        }
        $from->moveTo($to, false, wfMsg('hacl_move_acl'), false);
        // FIXME if there's no redirect there's also no need for PR inclusion
        // FIXME also we should revive the SD for a non-existing PE when that PE is created again
        $fromA = new Article($from);
        $fromA->doEdit('{{#predefined right:rights='.$to->getPrefixedText().'}}', wfMsg('hacl_move_acl_include'));
    }

    /* Parse wikitext inside a separate parser to overcome its non-reenterability */
    static function parse($text, $title)
    {
        global $wgParser;
        if (!self::$parser)
            self::$parser = clone $wgParser;
        $options = clone $wgParser->getOptions();
        self::$parser->parse($text, $title, $options);
    }

    /**
     * Return HTML consistency check status for pages in ACL namespace
     */
    function consistencyCheckHtml()
    {
        global $haclgContLang, $haclgHaloScriptPath;
        $msg = array();
        if ($this->errors)
        {
            $msg[] = wfMsgForContent('hacl_errors_in_definition');
        }
        if (!$this->rules)
        {
            if ($this->peType == IACL::PE_GROUP)
            {
                $msg[] = wfMsgForContent('hacl_group_must_have_members');
            }
            else
            {
                $msg[] = wfMsgForContent('hacl_right_must_have_rights');
            }
        }
        $this->makeDef();
        if ($this->peType == IACL::PE_NAMESPACE)
        {
            global $haclgUnprotectableNamespaceIds;
            // a namespace can only be protected if it is not member of $haclgUnprotectableNamespaces
            // (transformed into $haclgUnprotectableNamespaceIds on extension init)
            if ($haclgUnprotectableNamespaceIds &&
                $haclgUnprotectableNamespaceIds[$this->def['pe_id']])
            {
                // This namespace can not be protected
                // TODO So don't save the definition
                $msg[] = wfMsgForContent('hacl_unprotectable_namespace');
            }
        }
        if (!$this->def['pe_id'])
        {
            $msg[] = wfMsgForContent('hacl_pe_not_exists', $this->peName);
        }
        else
        {
            list($del, $add) = $this->def->diffRules();
            if ($del || $add)
            {
                // TODO Show inconsistency details
                $msg[] = wfMsgForContent('hacl_acl_element_inconsistent');
            }
        }
        // Merge errors into HTML text
        $html = '';
        if ($msg)
        {
            $html .= wfMsgForContent('hacl_consistency_errors');
            $html .= "<ul>";
            foreach ($msg as $m)
            {
                $html .= "<li>$m</li>";
            }
            $html .= "</ul>";
        }
        // Add "Create/edit with IntraACL editor" link
        // TODO do not display it when the user has no rights to change ACL
        $html .= wfMsgForContent($id ? 'hacl_edit_with_special' : 'hacl_create_with_special',
            Title::newFromText('Special:IntraACL')->getLocalUrl(array(
                'action' => ($this->mType == 'group' ? 'group' : 'acl'),
                ($this->mType == 'group' ? 'group' : 'sd') => $this->title->getPrefixedText(),
            )),
            $haclgHaloScriptPath . '/skins/images/edit.png');
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
                ? ':;'.wfMsgForContent('hacl_assigned_user')
                : ':;'.wfMsgForContent('hacl_user_member');
            foreach ($users as &$u)
            {
                if ($u == '*')
                    $u = wfMsgForContent('hacl_all_users');
                elseif ($u == '#')
                    $u = wfMsgForContent('hacl_registered_users');
                else
                    $u = "[[$userNS:$u|$u]]";
            }
            $text .= implode(', ', $users);
            $text .= "\n";
        }
        if ($groups)
        {
            global $wgContLang;
            $aclNS = $wgContLang->getNsText(HACL_NS_ACL);
            $text .= $isAssignedTo
                ? ':;'.wfMsgForContent('hacl_assigned_groups')
                : ':;'.wfMsgForContent('hacl_group_member');
            $first = true;
            foreach ($groups as $g)
            {
                if (!$first)
                    $text .= ', ';
                else
                    $first = false;
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
        $this->errors = array_merge($this->errors, $messages);
        if (!empty($messages))
        {
            $text .= "\n:;".wfMsgForContent('hacl_error').
                wfMsgForContent('hacl_will_not_work_as_expected').
                "\n:*".implode("\n:*", $messages);
        }
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
        foreach ($rights as $name => $peName)
        {
            // Rights can be given without the namespace "ACL". However, the
            // right should be linked correctly. So if the namespace is missing,
            // the link is adapted.
            if (strpos($r, $aclNS) === false && $addACLNS)
            {
                $r = "$aclNS:$r|$r";
            }
            $text .= "* [[$r]]\n";
        }
        return $text;
    }
}
