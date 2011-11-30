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
 * This file contains the implementation of parser functions for IntraACL.
 */
if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

/**
 * The class HACLParserFunctions handles parser functions of the IntraACL
 * extension. The following functions are parsed:
 * - access
 * - predefined right
 * - manage rights
 * - member
 * - manage group
 */
class HACLParserFunctions
{
    //--- Constants ---

    //--- Private fields ---
    // Title: The title to which the functions are applied
    private $mTitle = NULL;

    // array(HACLRight): All inline rights of the title
    private $mInlineRights = array();

    // array(string): All predefined rights that are referenced
    private $mPredefinedRights = array();

    // array(string): Users who can change a right
    private $mRightManagerUsers = array();

    // array(string): Groups who can change a right
    private $mRightManagerGroups = array();

    // array(string): Users who can change a group
    private $mGroupManagerUsers = array();

    // array(string): Groups who can change a group
    private $mGroupManagerGroups = array();

    // array(string): Users who are member of a group
    private $mUserMembers = array();

    // array(string): Groups who are member of a group
    private $mGroupMembers = array();

    // bool: true if all parser functions of an article are valid
    private $mDefinitionValid = true;

    // string: Type of the definition: group, right, sd, invalid
    private $mType = false;

    // HACLParserFunctions: Currently used instance of this class
    private static $mInstance = null;

    // Global cloned Parser object
    private static $mParser;

    // array(string): fingerprints of all invokations of parser functions
    // The parser may be called several times in the same article but the data
    // generated in the parser functions must only be saved once.
    private $mFingerprints = array();

    /**
     * Constructor for HACLParserFunctions. This object is a singleton.
     */
    public function __construct($title)
    {
        $this->mTitle = $title;
        $this->mType = HACLEvaluator::hacl_type($this->mTitle);
    }

    public static function access(&$parser)
    {
        $args = func_get_args();
        if (self::$mInstance)
            return self::$mInstance->_access($parser, $args);
        return '';
    }

    public static function predefinedRight(&$parser)
    {
        $args = func_get_args();
        if (self::$mInstance)
            return self::$mInstance->_predefinedRight($parser, $args);
        return '';
    }

    public static function manageRights(&$parser)
    {
        $args = func_get_args();
        if (self::$mInstance)
            return self::$mInstance->_manageRights($parser, $args);
        return '';
    }

    public static function addMember(&$parser)
    {
        $args = func_get_args();
        if (self::$mInstance)
            return self::$mInstance->_addMember($parser, $args);
        return '';
    }

    public static function manageGroup(&$parser)
    {
        $args = func_get_args();
        if (self::$mInstance)
            return self::$mInstance->_manageGroup($parser, $args);
        return '';
    }

    //--- Callbacks for parser functions ---

    /**
     * Callback for parser function "#access:".
     * This parser function defines an access control entry (ACE) in form of an
     * inline right definition. It can appear several times in an article and
     * has the following parameters:
     * assigned to: This is a comma separated list of user groups and users whose
     *              access rights are defined. The special value stands for all
     *              anonymous users. The special value user stands for all
     *              registered users.
     * actions: This is the comma separated list of actions that are permitted.
     *          The allowed values are read, edit, formedit, create, move,
     *          annotate and delete. The special value comprises all of these actions.
     * description:This description in prose explains the meaning of this ACE.
     * name: (optional) A short name for this inline right
     *
     * @param Parser $parser
     *         The parser object
     *
     * @return string
     *         Wikitext
     *
     * @throws
     *         HACLException(HACLException::INTERNAL_ERROR)
     *             ... if the parser function is called for different articles
     */
    public function _access(&$parser, $args)
    {
        $params = $this->getParameters($args);
        $fingerprint = $this->makeFingerprint("access", $params);

        // handle the parameter "assigned to".
        list($users, $groups, $em1, $warnings) = $this->assignedTo($params);

        // handle the parameter 'action'
        list($actions, $em2) = $this->actions($params);

        // handle the (optional) parameter 'description'
        global $haclgContLang;
        $descPN = $haclgContLang->getParserFunctionParameter(HACLLanguage::PFP_DESCRIPTION);
        $description = array_key_exists($descPN, $params)
                        ? $params[$descPN]
                        : "";
        // handle the (optional) parameter 'name'
        $namePN = $haclgContLang->getParserFunctionParameter(HACLLanguage::PFP_NAME);
        $name = array_key_exists($namePN, $params)
                    ? $params[$namePN]
                    : "";

        $errMsgs = $em1 + $em2;

        if (count($errMsgs) == 0)
        {
            // no errors
            // => create and store the new right for later use.
            if (!in_array($fingerprint, $this->mFingerprints))
            {
                $ir = new HACLRight($this->actionNamesToIDs($actions), $groups, $users, $description, $name);
                $this->mInlineRights[] = $ir;
                $this->mFingerprints[] = $fingerprint;
            }
        }
        else
            $this->mDefinitionValid = false;

        // Format the defined right in Wikitext
        if (!empty($name))
            $text = wfMsgForContent('hacl_pf_rightname_title', $name).
                wfMsgForContent('hacl_pf_rights', implode(', ', $actions));
        else
            $text = wfMsgForContent('hacl_pf_rights_title', implode(', ', $actions));
        $text .= $this->showAssignees($users, $groups);
        $text .= $this->showDescription($description);
        $text .= $this->showErrors($errMsgs);
        $text .= $this->showWarnings($warnings);

        return $text;

    }

    /**
     * Callback for parser function "#predefined right:".
     * Besides inline right definitions ACLs can refer to other sets of rights
     * that are defined in another article. This parser function established the
     * connection. It can appear several times in security descriptors and
     * articles with predefined rights. There is only one parameter:
     * rights: This is a comma separated list of article names with the prefix
     *         ACL:Right/
     *
     * @param Parser $parser
     *         The parser object
     *
     * @return string
     *         Wikitext
     *
     * @throws
     *         HACLException(HACLException::INTERNAL_ERROR)
     *             ... if the parser function is called for different articles
     */
    public function _predefinedRight(&$parser, $args)
    {
        $params = $this->getParameters($args);
        $fingerprint = $this->makeFingerprint("predefinedRight", $params);

        // handle the parameter 'rights'
        list($rights, $em, $warnings) = $this->rights($params);

        if (count($em) == 0) {
            // no errors
            // => store the rights for later use.
            if (!in_array($fingerprint, $this->mFingerprints)) {
                foreach ($rights as $r) {
                    try {
                        $rightDescr = HACLSecurityDescriptor::newFromName($r);
                        $this->mPredefinedRights[] = $rightDescr;
                    } catch (HACLSDException $e) {
                        // There is an article with the name of the right but it does
                        // not define a right (yet)
                        $em[] = wfMsgForContent('hacl_invalid_predefined_right', $r);
                        $this->mDefinitionValid = false;
                    }
                }
                $this->mFingerprints[] = $fingerprint;
            }
        } else {
            $this->mDefinitionValid = false;
        }

        // Format the rights in Wikitext
        $text = wfMsgForContent('hacl_pf_predefined_rights_title');
        $text .= $this->showRights($rights);
        $text .= $this->showErrors($em);
        $text .= $this->showWarnings($warnings);

        return $text;
    }

    /**
     * Callback for parser function "#manage rights:".
     * This function can be used in security descriptors and predefined rights.
     * It defines which user or group can change the ACL.
     * assigned to: This is a comma separated list of users and groups that can
     *              modify the security descriptor.
     *
     * @param Parser $parser
     *         The parser object
     *
     * @return string
     *         Wikitext
     *
     * @throws
     *         HACLException(HACLException::INTERNAL_ERROR)
     *             ... if the parser function is called for different articles
     */
    public function _manageRights(&$parser, $args)
    {
        $params = $this->getParameters($args);
        $fingerprint = $this->makeFingerprint("manageRights", $params);

        // handle the parameter "assigned to".
        list($users, $groups, $errMsgs, $warnings) = $this->assignedTo($params);

        if (count($errMsgs) == 0) {
            // no errors
            // => store the list of assignees for later use.
            if (!in_array($fingerprint, $this->mFingerprints)) {
                $this->mRightManagerUsers  = array_merge($this->mRightManagerUsers, $users);
                $this->mRightManagerGroups = array_merge($this->mRightManagerGroups, $groups);
                $this->mFingerprints[] = $fingerprint;
            }
        } else {
            $this->mDefinitionValid = false;
        }

        // Format the right managers in Wikitext
        $text = wfMsgForContent('hacl_pf_right_managers_title');
        $text .= $this->showAssignees($users, $groups);
        $text .= $this->showErrors($errMsgs);
        $text .= $this->showWarnings($warnings);

        return $text;
    }

    /**
     * Callback for parser function "#member:".
     * This function can appear (several times) in ACL group definitions. It
     * defines a list of users and ACL groups that belong to the group.
     * members: This is a comma separated list of users and groups that belong
     *          to the group.
     *
     * @param Parser $parser
     *         The parser object
     *
     * @return string
     *         Wikitext
     *
     * @throws
     *         HACLException(HACLException::INTERNAL_ERROR)
     *             ... if the parser function is called for different articles
     */
    public function _addMember(&$parser, $args)
    {
        $params = $this->getParameters($args);
        $fingerprint = $this->makeFingerprint("addMember", $params);

        // handle the parameter "assigned to".
        list($users, $groups, $errMsgs, $warnings) = $this->assignedTo($params, false);

        if (count($errMsgs) == 0) {
            // no errors
            // => store the list of members for later use.
            if (!in_array($fingerprint, $this->mFingerprints)) {
                $this->mUserMembers  = array_merge($this->mUserMembers, $users);
                $this->mGroupMembers = array_merge($this->mGroupMembers, $groups);
                $this->mFingerprints[] = $fingerprint;
            }
        } else {
            $this->mDefinitionValid = false;
        }

        // Format the group members in Wikitext
        $text = wfMsgForContent('hacl_pf_group_members_title');
        $text .= $this->showAssignees($users, $groups, false);
        $text .= $this->showErrors($errMsgs);
        $text .= $this->showWarnings($warnings);

        return $text;
    }

    /**
     * Callback for parser function "#manage group:".
     * This function can be used in ACL group definitions. It defines which user
     * or group can change the group.
     * assigned to: This is a comma separated list of users and groups that can
     *              modify the group.
     *
     * @param Parser $parser
     *         The parser object
     *
     * @return string
     *         Wikitext
     *
     *
     * @throws
     *         HACLException(HACLException::INTERNAL_ERROR)
     *             ... if the parser function is called for different articles
     */
    public function _manageGroup(&$parser, $args)
    {
        $params = $this->getParameters($args);
        $fingerprint = $this->makeFingerprint("managerGroup", $params);

        // handle the parameter "assigned to".
        list($users, $groups, $errMsgs, $warnings) = $this->assignedTo($params);

        if (count($errMsgs) == 0) {
            // no errors
            // => store the list of assignees for later use.
            if (!in_array($fingerprint, $this->mFingerprints)) {
                $this->mGroupManagerUsers  = array_merge($this->mGroupManagerUsers, $users);
                $this->mGroupManagerGroups = array_merge($this->mGroupManagerGroups, $groups);
                $this->mFingerprints[] = $fingerprint;
            }
        } else {
            $this->mDefinitionValid = false;
        }

        // Format the right managers in Wikitext
        $text = wfMsgForContent('hacl_pf_group_managers_title');
        $text .= $this->showAssignees($users, $groups);
        $text .= $this->showErrors($errMsgs);
        $text .= $this->showWarnings($warnings);

        return $text;
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
                    // Article is already deleted somehow, but SD remains (DB inconsistency), delete it
                    if ($sd = HACLSecurityDescriptor::newFromID($sd, false))
                        $sd->delete();
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
        if ($this->mTitle->getNamespace() != HACL_NS_ACL)
            return '';
        $id = $this->mTitle->getArticleId();
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
                ($this->mType == 'group' ? 'group' : 'sd') => $this->mTitle->getPrefixedText(),
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
        // FIXME make this HACLGroup's method
        global $wgUser;
        $t = $this->mTitle;
        $id = $t->getArticleID();
        $group = new HACLGroup(
            $id,
            $t->getText(),
            $this->mGroupManagerGroups,
            $this->mGroupManagerUsers
        );
        if (HACLGroup::exists($id) && !$group->userCanModify($wgUser))
        {
            wfDebug(__METHOD__." ".$wgUser->getName()." does not have the right to modify group $t\n");
            return false;
        }
        wfDebug(__METHOD__." Saving group: $t\n");
        $group->save();
        $group->removeAllMembers();
        foreach ($this->mGroupMembers as $m)
            $group->addGroup($m);
        foreach ($this->mUserMembers as $m)
            $group->addUser($m);
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
        $t = $this->mTitle;
        wfDebug(__METHOD__." Saving SD: $t\n");
        try
        {
            $sd = HACLSecurityDescriptor::newFromID($t->getArticleID());
            // The right already exists. => delete the rights it contains
            $sd->removeAllRights();
        }
        catch (HACLSDException $e)
        {
        }

        wfDebug(__METHOD__." Saving SD: $t --\n");
        try
        {
            list($pe, $peType) = HACLSecurityDescriptor::nameOfPE($t->getText());
            $sd = new HACLSecurityDescriptor(
                $t->getArticleID(), $t->getText(), $pe, $peType,
                $this->mRightManagerGroups, $this->mRightManagerUsers
            );
            $sd->save();

            // add all inline rights
            $sd->addInlineRights($this->mInlineRights);
            // add all predefined rights
            $sd->addPredefinedRights($this->mPredefinedRights);
        }
        catch (HACLSDException $e)
        {
            wfDebug("[IntraACL] Error saving $t: $e");
            return false;
        }

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
            $sdName = $this->mTitle->getFullText();
            list($pe, $peType) = HACLSecurityDescriptor::nameOfPE($sdName);
            // Check if the protected element for a security descriptor does exist
            if (HACLSecurityDescriptor::peIDforName($pe, $peType) === false)
                $msg[] = wfMsgForContent('hacl_pe_not_exists', $this->mTitle->getText());
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
                $msg[] = wfMsgForContent("hacl_invalid_parser_function",
                    $haclgContLang->getParserFunction(HACLLanguage::PF_ACCESS));
            }
        }
        if (count($this->mPredefinedRights) > 0) {
            if ($type == 'group') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function",
                    $haclgContLang->getParserFunction(HACLLanguage::PF_PREDEFINED_RIGHT));
            }
        }
        if (count($this->mRightManagerGroups) > 0 ||
            count($this->mRightManagerUsers) > 0) {
            if ($type == 'group') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function",
                    $haclgContLang->getParserFunction(HACLLanguage::PF_MANAGE_RIGHTS));
            }
        }
        if (count($this->mGroupManagerGroups) > 0 ||
            count($this->mGroupManagerUsers) > 0) {
            if ($type == 'right' || $type == 'sd') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function",
                    $haclgContLang->getParserFunction(HACLLanguage::PF_MANAGE_GROUP));
            }
        }
        if (count($this->mUserMembers) > 0 ||
            count($this->mGroupMembers) > 0) {
            if ($type == 'right' || $type == 'sd') {
                $msg[] = wfMsgForContent("hacl_invalid_parser_function",
                    $haclgContLang->getParserFunction(HACLLanguage::PF_MEMBER));
            }
        }
        return $msg;
    }

    /**
     * Returns the parser function parameters that were passed to the parser-function
     * callback.
     *
     * @param array(mixed) $args
     *         Arguments of a parser function callback
     * @return array(string=>string)
     *         Array of argument names and their values.
     */
    private function getParameters($args) {
        $parameters = array();

        foreach ($args as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (preg_match('/^\s*(.*?)\s*=\s*(.*?)\s*$/', $arg, $p) == 1) {
                $parameters[strtolower($p[1])] = $p[2];
            }
        }

        return $parameters;
    }

    /**
     * This method handles the parameter "assignedTo" of the parser functions
     * #access, #manage rights and #manage groups.
     * If $isAssignedTo is false, the parameter "members" for parser function
     * #members is handled. The values have the same format as for "assigned to".
     *
     * @param array(string=>string) $params
     *         Array of argument names and their values. These are the arguments
     *         that were passed to the parser function as returned by the method
     *         getParameters().
     *
     * @param bool $isAssignedTo
     *         true  => parse the parameter "assignedTo"
     *         false => parse the parameter "members"
     * @return array(users:array(string), groups:array(string),
     *               error messages:array(string), warnings: array(string))
     */
    private function assignedTo($params, $isAssignedTo = true)
    {
        global $wgContLang, $haclgContLang;
        $errMsgs = array();
        $warnings = array();
        $users = array();
        $groups = array();

        $assignedToPN = $isAssignedTo
            ? $haclgContLang->getParserFunctionParameter(HACLLanguage::PFP_ASSIGNED_TO)
            : $haclgContLang->getParserFunctionParameter(HACLLanguage::PFP_MEMBERS);
        if (!array_key_exists($assignedToPN, $params))
        {
            // The parameter "assigned to" is missing.
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter', $assignedToPN);
            return array($users, $groups, $errMsgs);
        }

        $etc = haclfDisableTitlePatch();

        $assignedTo = $params[$assignedToPN];
        $assignedTo = explode(',', $assignedTo);
        // read assigned users and groups
        foreach ($assignedTo as $assignee)
        {
            $assignee = trim($assignee);
            $t = Title::newFromText($assignee);
            if ($t && $t->getNamespace() == NS_USER ||
                $assignee == '*' || $assignee == '#')
            {
                // user found
                if ($assignee != '*' && $assignee != '#')
                {
                    $user = $t->getText();
                    // Check if the user exists
                    if (User::idFromName($user) == 0)
                    {
                        // User does not exist => add a warning
                        $warnings[] = wfMsgForContent("hacl_unknown_user", $user);
                    }
                    else
                        $users[] = $user;
                }
                else
                    $users[] = $assignee;
            }
            else
            {
                if ($p = strpos($assignee, ':'))
                {
                    // Allow Group:X syntax
                    $assignee = substr($assignee, 0, $p) . '/' . substr($assignee, $p+1);
                }
                // group found
                // Check if the group exists
                if (HACLGroup::idForGroup($assignee) == NULL)
                {
                    // Group does not exist => add a warning
                    $warnings[] = wfMsgForContent("hacl_unknown_group", $assignee);
                }
                else
                    $groups[] = $assignee;
            }
        }
        if (count($users) == 0 && count($groups) == 0)
        {
            // No users/groups specified at all => add error message
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter_values', $assignedToPN);
        }
        haclfRestoreTitlePatch($etc);

        return array($users, $groups, $errMsgs, $warnings);
    }

    /**
     * This method handles the parameter "actions" of the parser function #access.
     *
     * @param array(string=>string) $params
     *         Array of argument names and their values. These are the arguments
     *         that were passed to the parser function as returned by the method
     *         getParameters().
     *
     * @return array(actions:array(string), error messages:array(string))
     */
    private function actions($params) {
        global $wgContLang, $haclgContLang;
        $errMsgs = array();
        $actions = array();

        $actionsPN = $haclgContLang->getParserFunctionParameter(HACLLanguage::PFP_ACTIONS);
        if (!array_key_exists($actionsPN, $params)) {
            // The parameter "actions" is missing.
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter', $actionsPN);
            return array($actions, $errMsgs);
        }

        $actions = $params[$actionsPN];
        $actions = explode(',', $actions);
        for ($i = 0; $i < count($actions); ++$i) {
            $actions[$i] = trim($actions[$i]);
            // Check if the action is valid
            if (!$haclgContLang->getActionId($actions[$i]))
                $errMsgs[] = wfMsgForContent('hacl_invalid_action', $actions[$i]);
        }
        if (count($actions) == 0) {
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter_values', $actionsPN);
        }

        return array($actions, $errMsgs);
    }

    /**
     * This method handles the parameter "rights" of the parser function
     * #predefined right.
     *
     * @param array(string=>string) $params
     *         Array of argument names and their values. These are the arguments
     *         that were passed to the parser function as returned by the method
     *         getParameters().
     *
     * @return array(rights:array(string), error messages:array(string), warnings:array(string))
     */
    private function rights($params) {
        global $wgContLang, $haclgContLang;
        $errMsgs = array();
        $warnings = array();
        $rights = array();

        $rightsPN = $haclgContLang->getParserFunctionParameter(HACLLanguage::PFP_RIGHTS);
        if (!array_key_exists($rightsPN, $params)) {
            // The parameter "rights" is missing.
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter', $rightsPN);
            return array($rights, $errMsgs);
        }

        $rights = $params[$rightsPN];
        $rights = explode(',', $rights);
        // trim rights
        for ($i = 0; $i < count($rights); ++$i) {
            $rights[$i] = trim($rights[$i]);
            // Check if the right exists
            if (HACLSecurityDescriptor::idForSD($rights[$i]) == 0) {
                // The right does not exist
                $warnings[] = wfMsgForContent('hacl_invalid_predefined_right', $rights[$i]);
                unset($rights[$i]);
            }
        }
        if (count($rights) == 0) {
            $errMsgs[] = wfMsgForContent('hacl_missing_parameter_values', $rightsPN);
        } else {
            // Create new indices in the array (in case invalid rights have been removed)
            $rights = array_values($rights);
        }

        return array($rights, $errMsgs, $warnings);
    }

    /**
     * Converts an array of language dependent action names as they are used in
     * rights to a combined (ORed) action ID bit-field.
     *
     * @param array(string) $actionNames
     *         Language dependent action names like 'read' or 'lesen'.
     *
     * @return int
     *         An action ID that is the ORed combination of action IDs they are
     *     defined as constants in the class HACLRight.
     */
    private function actionNamesToIDs($actionNames)
    {
        global $haclgContLang;
        $actionID = 0;
        foreach ($actionNames as $an)
            if ($id = $haclgContLang->getActionId($an))
                $actionID |= $id;
        return $actionID;
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
     * Formats the wikitext for displaying the description of a right.
     *
     * @param string $description
     *         A description. Empty descriptions are allowed.
     *
     * @return string
     *         A formatted wikitext with the description.
     */
    private function showDescription($description) {
        $text = "";
        if (!empty($description)) {
            $text .= ":;".wfMsgForContent('hacl_description').$description;
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
     * old revisions of source article.
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

        $from->moveTo($to, false, wfMsg('hacl_move_acl'), true);
        $fromA = new Article($from);
        $fromA->doEdit('{{#predefined right:rights='.$to->getPrefixedText().'}}', wfMsg('hacl_move_acl_include'));
    }

    /**
     * Creates a fingerprint from a parser function name and its parameters.
     *
     * @param string $functionName
     *         Name of the parser function
     * @param array(string=>string) $params
     *         Parameters of the parser function
     * @return string
     *         The fingerprint
     */
    private static function makeFingerprint($functionName, $params)
    {
        $fingerprint = "$functionName";
        foreach ($params as $k => $v)
            $fingerprint .= $k.$v;
        return $fingerprint;
    }
}
