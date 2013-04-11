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
    var $rights = array(), $errors = array();

    function __construct($title)
    {
        $this->title = $title;
        list($this->peName, $this->peType) = IACLDefinition::nameOfPE($title);
    }

    static function instance($parser)
    {
        if (!isset(self::$instances[''.$parser->mTitle]))
        {
            return self::$instances[''.$parser->mTitle] = new self($parser->mTitle);
        }
        return self::$instances[''.$parser->mTitle];
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
        $params = $this->getParameters($args);

        // handle the parameter 'action'
        list($actions, $em2) = $this->actions($params);

        // handle the parameter 'assigned to'
        list($users, $groups, $em1) = $this->assignedTo($params, 'assigned to', $actions);

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
        $params = $this->getParameters($args);

        // handle the parameter 'rights'
        list($rights, $errors) = $this->rights($params);

        foreach ($rights as $name => $id)
        {
            if ($id)
            {
                $this->rights[$id[0]][$id[1]] = IACL::ACTION_INCLUDE_SD;
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
     * @param int    $actions   Action bitmask to save into $this->rights
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
            $res = IACLStorage::get('Util')->getUsers(array('user_name' => array_keys($check)));
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
            $res = IACLStorage::get('Util')->getPages(array('page_namespace' => HACL_NS_ACL, 'page_title' => array_keys($groups)));
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
                $this->mRules[IACL::RULE_USER][$id] |= $actions;
            }
        }
        foreach ($groups as $name => $id)
        {
            if ($id !== false)
            {
                $this->mRules[IACL::RULE_GROUP][$id] |= $actions;
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
            $id = $haclgContLang->getActionId($actions[$i])
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

        $rights = explode(',', $params[$param]);
        for ($i = 0; $i < count($rights); $i++)
        {
            list($peName, $peType) = IACLDefinition::nameOfPE(trim($rights[$i]));
            $rights[$i] = [ $peType, $peName ];
        }
        if (!$rights)
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
        global $haclgContLang;
        if ($article->getTitle()->getNamespace() == HACL_NS_ACL)
        {
            self::$mInstance = new self($article->getTitle());
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
        global $haclgContLang;
        if (!self::$mInstance)
            return true;
        $html = self::$mInstance->consistencyCheckHtml();
        if ($html)
            $out->addHTML($html);
        return true;
    }

    /**
     * NewRevisionFromEditComplete hook is used in MediaWiki 1.13
     * as it is ran from import as well as from doEdit().
     */
    public static function NewRevisionFromEditComplete($article, $rev, $baseID, $user)
    {
        return self::updateDefinition($article);
    }

    /**
     * ArticleEditUpdates hook is used in MediaWiki 1.14+.
     */
    public static function ArticleEditUpdates($article, $editInfo, $changed)
    {
        return self::updateDefinition($article, $editInfo->newText);
    }

    /**
     * This method is called, after an article has been saved. If the article
     * belongs to the namespace ACL (i.e. a right, SD, group)
     * its content is transferred to the database.
     *
     * @param Article $article
     * @param User $user
     * @param string $text
     * @return true
     */
    public static function updateDefinition($article, $text = NULL)
    {
        // Is the article in the ACL namespace?
        $title = $article->getTitle();
        if (($title->getNamespace() == HACL_NS_ACL) &&
            ($type = HACLEvaluator::hacl_type($title)))
        {
            //--- Get article content, if not yet ---
            if ($text === NULL)
                $text = $article->getContent();

            //--- Create an instance for parsing this article
            self::$mInstance = new self($title);

            //--- Find parser functions inside $text ---
            self::parse($text, $title);

            //--- Check if the definition is empty ---
            if (self::$mInstance->checkEmptiness())
            {
                wfDebug(__METHOD__.": '$title' definition is empty, removing from DB\n");
                self::removeDef($title);
                return true;
            }

            //--- Remove old SD / Group ---
            if ($type == 'group')
                self::removeGroup($title);
            else
            {
                // It is a right or security descriptor
                if ($sd = HACLSecurityDescriptor::newFromID($title->getArticleId(), false))
                {
                    // Check access
                    if (!$sd->userCanModify())
                        return true;
                    // remove all current rights, however the right remains in
                    // the hierarchy of rights, as it might be "revived"
                    $sd->removeAllRights();
                    // The empty right article can now be changed by everyone
                    $sd->setManageGroups(NULL);
                    $sd->setManageUsers('*,#');
                    $sd->save();
                }
            }

            //--- Try to store the definition in the database ---
            if (self::$mInstance->saveDefinition() !== NULL)
            {
                // The cache must be invalidated, so that error messages can be
                // generated when the article is displayed for the first time after
                // saving.
                $title->invalidateCache();
            }
            elseif ($type != 'group')
            {
                // Error during saving SD, remove it completely instead of leaving in incorrect state.
                $sd->delete();
            }

            //--- Destroy instance ---
            self::$mInstance = NULL;
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
        //--- Remove old SD / Group ---
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

    //--- Private methods ---

    private static function removeGroup($title)
    {
        try
        {
            $group = HACLGroup::newFromID($title->getArticleId());
            // It is a group
            // => remove all current members, however the group remains in the
            //    hierarchy of groups, as it might be "revived"
            $group->removeAllMembers();
            // The empty group article can now be changed by everyone
            $group->setManageGroups(NULL);
            $group->setManageUsers('*,#');
            $group->save();
        } catch(Exception $e) {}
    }

    /* Parse wikitext inside a separate parser to overcome its non-reenterability */
    static function parse($text, $title)
    {
        global $wgParser;
        if (!self::$mParser)
            self::$mParser = clone $wgParser;
        $options = clone $wgParser->getOptions();
        self::$mParser->parse($text, $title, $options);
    }

    /* Return HTML consistency check status for pages in ACL namespace */
    private function consistencyCheckHtml()
    {
        global $haclgContLang, $haclgHaloScriptPath;
        if ($this->title->getNamespace() != HACL_NS_ACL)
            return '';
        $id = $this->title->getArticleId();
        $msg = array();
        if ($id && !$this->mType)
        {
            // Warning: Article does not correspond to any ACL definition
            // FIXME add the list of valid prefixes into warning text
            $msg[] = wfMsgForContent('hacl_invalid_prefix');
        }
        elseif ($id)
        {
            $msg = $this->checkEmptiness();

            // Check for invalid parser functions
            $ivpf = $this->findInvalidParserFunctions($this->mType);
            $msg = array_merge($msg, $ivpf);

            if (!$this->mDefinitionValid)
                $msg[] = wfMsgForContent('hacl_errors_in_definition');

            if (!$msg)
            {
                // Check if the article is already represented in IntraACL storage
                $exists = false;
                if ($this->mType == 'group')
                {
                    $grp = HACLGroup::newFromId($id, false);
                    if ($grp)
                    {
                        $exists = true;
                        // Check consistency: is the definition in the DB equal to article text?
                        $consistent = $grp->checkIsEqual(
                            $this->mUserMembers, $this->mGroupMembers,
                            $this->mGroupManagerUsers, $this->mGroupManagerGroups
                        );
                    }
                }
                else
                {
                    $exists = HACLSecurityDescriptor::exists($id);
                    // TODO add consistency check for security descriptors
                    $consistent = $exists;
                }

                if (!$exists)
                    $msg = array(wfMsgForContent('hacl_acl_element_not_in_db'));
                elseif (!$consistent)
                    $msg = array(wfMsgForContent('hacl_acl_element_inconsistent'));
            }
        }

        // Merge errors into HTML text
        $html = '';
        if ($msg)
        {
            $html .= wfMsgForContent('hacl_consistency_errors');
            $html .= wfMsgForContent('hacl_definitions_will_not_be_saved');
            $html .= "<ul>";
            foreach ($msg as $m)
                $html .= "<li>$m</li>";
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
     * This class collects all functions for ACLs of an article. The collected
     * definitions are finally saved to the database with this method.
     * If there is already a definition for the article, it will be replaced.
     *
     * @return bool
     *         true, if saving was successful
     *         false, if not
     */
    private function saveDefinition()
    {
        switch ($this->mType)
        {
            case 'group':
                return $this->saveGroup();
                break;
            case 'template':
            case 'right':
                return $this->saveSecurityDescriptor(true);
                break;
            case 'sd':
                return $this->saveSecurityDescriptor(false);
                break;
            default:
                return NULL;
        }
    }

    /**
     * Saves a group based on the definitions given in the current article.
     *
     * @return bool
     *         true, if saving was successful
     *         false, if not
     */
    private function saveGroup()
    {
        global $wgUser;
        $t = $this->title;
        wfDebug(__METHOD__." Saving group: $t\n");
        $group = HACLGroup::newFromId($t->getArticleID());
        // TODO Check modification access
        $group['manage_groups'] = $this->mGroupManagerGroups;
        $group['manage_users'] = $this->mGroupManagerUsers;
        $group['members'] = $this->mGroupMembers + $this->mUserMembers;
        $group->save();
        return true;
    }

    /**
     * Saves a right or security descriptor based on the definitions given in
     * the current article.
     *
     * @param bool $isRight
     *         true  => save a right
     *         false => save a security descriptor
     *
     * @return bool
     *         true, if saving was successful
     *         false, if not
     */
    private function saveSecurityDescriptor($isRight)
    {
        $t = $this->title;
        wfDebug(__METHOD__." Saving SD: $t\n");
        $sd = HACLSecurityDescriptor::newFromID($t->getArticleID());
        // TODO Check modification access
        $sd['manage_groups'] = $this->mRightManagerGroups;
        $sd['manage_users'] = $this->mRightManagerUsers;
        $sd['inline_rights'] = $this->mInlineRights;
        $sd['inclusions'] = $this->mPredefinedRights;
        $sd->save();
        return true;
    }

    /**
     * Checks the definition for emptiness.
     *
     * Groups:
     *  - must have members (users or groups)
     * Predefined Rights and Security Descriptors:
     *  - must have inline or predefined rights
     *  - a namespace can only be protected if it is not member of $haclgUnprotectableNamespaces
     *
     * @return array(string)
     *         An array of error messages or an empty array, if the definition is correct.
     */
    private function checkEmptiness()
    {
        global $haclgContLang, $wgContLang;
        $msg = array();
        // Check if the definition of a group is complete and valid
        if ($this->mType == 'group')
        {
            // check for members
            if (count($this->mGroupMembers) == 0 &&
                count($this->mUserMembers) == 0)
                $msg[] = wfMsgForContent('hacl_group_must_have_members');
        }
        // Check if the definition of a right or security descriptor is complete and valid
        elseif ($this->mType == 'right' || $this->mType == 'sd')
        {
            // check for inline or predefined rights
            if (!$this->mInlineRights &&
                !$this->mPredefinedRights)
                $msg[] = wfMsgForContent('hacl_right_must_have_rights');
        }
        // Additional checks for SDs
        if ($this->mType == 'sd')
        {
            $sdName = $this->title->getFullText();
            list($pe, $peType) = HACLSecurityDescriptor::nameOfPE($sdName);
            // Check if the protected element for a security descriptor does exist
            if (HACLSecurityDescriptor::peIDforName($pe, $peType) === false)
                $msg[] = wfMsgForContent('hacl_pe_not_exists', $this->title->getText());
            global $haclgUnprotectableNamespaceIds;
            // a namespace can only be protected if it is not member of $haclgUnprotectableNamespaces
            // (transformed into $haclgUnprotectableNamespaceIds on extension init)
            if ($haclgUnprotectableNamespaceIds &&
                $peType == HACLLanguage::PET_NAMESPACE &&
                $haclgUnprotectableNamespaceIds[$wgLang->getNsIndex($pe)])
            {
                // This namespace can not be protected
                $msg[] = wfMsgForContent('hacl_unprotectable_namespace');
            }
        }
        return $msg;
    }

    /**
     * Checks if invalid parser functions were used in definition.
     *
     * @param string $type
     *         One of 'group', 'right', 'sd'
     *
     * @return array(string)
     *         An array of error messages. (May be empty.)
     */
    private function findInvalidParserFunctions($type)
    {
        $msg = array();
        global $haclgContLang;

        if (count($this->mInlineRights) > 0) {
            if ($type == 'group') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function", 'access');
            }
        }
        if (count($this->mPredefinedRights) > 0) {
            if ($type == 'group') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function", 'predefined right');
            }
        }
        if (count($this->mRightManagerGroups) > 0 ||
            count($this->mRightManagerUsers) > 0) {
            if ($type == 'group') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function", 'manage rights');
            }
        }
        if (count($this->mGroupManagerGroups) > 0 ||
            count($this->mGroupManagerUsers) > 0) {
            if ($type == 'right' || $type == 'sd') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function", 'manage group');
            }
        }
        if (count($this->mUserMembers) > 0 ||
            count($this->mGroupMembers) > 0) {
            if ($type == 'right' || $type == 'sd') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function", 'member');
            }
        }
        return $msg;
    }

    /**
     * Formats the wikitext for displaying assignees of a right or members of a
     * group.
     *
     * @param array(string) $users
     *         Array of user names (without namespace "User"). May be empty.
     * @param array(string) $groups
     *         Array of group names (without namespace "ACL"). May be emtpy.
     * @param bool $isAssignedTo
     *         true  => output for "assignedTo"
     *         false => output for "members"
     * @return string
     *         A formatted wikitext with users and groups
     */
    private function showAssignees($users, $groups, $isAssignedTo = true)
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
     * @param array(string) $messages
     *         An array of error messages. May be empty.
     *
     * @return string
     *         A formatted wikitext with all error messages.
     */
    private function showErrors($messages) {
        $text = "";
        if (!empty($messages)) {
            $text .= "\n:;".wfMsgForContent('hacl_error').
            wfMsgForContent('hacl_definitions_will_not_be_saved').
                "\n";
            $text .= ":*".implode("\n:*", $messages);
        }
        return $text;
    }

    /**
     * Formats the wikitext for displaying the warnings of a parser function.
     *
     * @param array(string) $messages
     *         An array of warnings. May be empty.
     *
     * @return string
     *         A formatted wikitext with all warnings.
     */
    private function showWarnings($messages) {
        $text = "";
        if (!empty($messages)) {
            $text .= "\n:;".wfMsgForContent('hacl_warning').
            wfMsgForContent('hacl_will_not_work_as_expected').
                "\n";
            $text .= ":*".implode("\n:*", $messages);
        }
        return $text;
    }

    /**
     * Formats the wikitext for displaying predefined rights.
     *
     * @param array(string) $rights
     *         An array of rights. May be empty.
     * @param bool $addACLNS
     *         If <true>, the ACL namespace is added to the pages if it is missing.
     *
     * @return string
     *         A formatted wikitext with all rights.
     */
    private function showRights($rights, $addACLNS = true)
    {
        $text = "";
        global $wgContLang;
        $aclNS = $wgContLang->getNsText(HACL_NS_ACL);
        foreach ($rights as $r)
        {
            // Rights can be given without the namespace "ACL". However, the
            // right should be linked correctly. So if the namespace is missing,
            // the link is adapted.
            if (strpos($r, $aclNS) === false && $addACLNS)
                $r = "$aclNS:$r|$r";
            $text .= '*[['.$r."]]\n";
        }
        return $text;
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
    private static function move($from, $to)
    {
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
}
