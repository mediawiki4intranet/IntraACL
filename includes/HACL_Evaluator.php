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

if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

/**
 * This is the main class for the evaluation of user rights for a protected object.
 * It implements the function "userCan" that is called from MW for granting or
 * denying access to articles.
 *
 * WARNING: Now, members of "bureaucrat" Wiki group (not IntraACL group) can always do anything.
 *
 * @author Thomas Schweitzer
 */
class HACLEvaluator
{
    //---- Constants for the modes of the evaluator ----
    const NORMAL = 0;
    const DENY_DIFF = 1;
    const ALLOW_PROPERTY_READ = 2;

    //--- Private fields ---

    // The current mode of the evaluator
    static $mMode = HACLEvaluator::NORMAL;

    // Saving protected properties is allowed if the value did not change
    static $mSavePropertiesAllowed = false;

    // String with logging information
    static $mLog = "";

    // Is logging IntraACL's activities enabled?
    static $mLogEnabled = false;

    // Empty constructor
    function __construct() {}

    //--- Public methods ---

    /**
     * This function is called from the userCan-hook of MW. This method decides
     * if the article for the given title can be accessed.
     * See further information at: http://www.mediawiki.org/wiki/Manual:Hooks/userCan
     *
     * @param Title $title
     *        The title object for the article that will be accessed.
     * @param User $user
     *        Reference to the current user.
     * @param string $action
     *        Action concerning the title in question
     * @param boolean $result
     *        Reference to the result propagated along the chain of hooks.
     *
     * @return boolean
     *         true
     */
    public static function userCan($title, $user, $action, &$result)
    {
        global $haclgContLang, $haclgOpenWikiAccess, $wgRequest;
        $etc = haclfDisableTitlePatch();

        // $R = array(final log message, access granted?, continue hook processing?);
        $R = array('', false, false);
        self::startLog($title, $user, $action);
        if (!$title)
        {
            $R = array("Title is <null>.", true, true);
            goto fin;
        }

        $groups = $user->getGroups();
        if ($groups && in_array('bureaucrat', $groups))
        {
            $R = array('User is a bureaucrat and can do anything.', true, true);
            goto fin;
        }

        // Check if property access is requested.
        global $haclgProtectProperties;
        if ($haclgProtectProperties && ($r = self::checkPropertyAccess($title, $user, $action)) !== -1)
        {
            $R = array('Properties are protected, property right evaluated.', $r && true, true);
            goto fin;
        }

        // no access to the page "Permission denied" is allowed.
        // together with the TitlePatch which returns this page, this leads
        // to MediaWiki's "Permission error"
        if ($title->getPrefixedText() == $haclgContLang->getPermissionDeniedPage())
        {
            $R = array('Special handling of "Permission denied" page.', false, true);
            goto fin;
        }

        // Check action
        $actionID = HACLRight::getActionID($action);
        if ($actionID == 0)
        {
            // unknown action => nothing can be said about this
            $R = array('Unknown action.', true, true);
            goto fin;
        }

        // Check rights for managing ACLs
        if ($title->getNamespace() == HACL_NS_ACL)
        {
            $R = array('Checked ACL modification rights.', self::checkACLManager($title, $user, $actionID), true);
            goto fin;
        }

        // haclfArticleID also returns IDs for special pages
        $articleID = haclfArticleID($title);
        $userID = $user->getId();
        if ($articleID && $actionID == HACLLanguage::RIGHT_CREATE)
            $actionID = HACLLanguage::RIGHT_EDIT;
        elseif (!$articleID)
        {
            if ($actionID == HACLLanguage::RIGHT_EDIT)
            {
                self::log('Article does not exist yet. Checking right to create.');
                $actionID = HACLLanguage::RIGHT_CREATE;
            }
            elseif ($actionID == HACLLanguage::RIGHT_DELETE ||
                $actionID == HACLLanguage::RIGHT_MOVE)
            {
                $R = array('Can\'t move or delete non-existing article.', true, true);
                goto fin;
            }
            // Check if the title belongs to a namespace with an SD
            list($r, $sd) = self::checkNamespaceRight($title->getNamespace(), $userID, $actionID);
            $R = array('Checked namespace access right.', $r, $sd);
            goto fin;
        }

        if ($haclgProtectProperties)
        {
            $action = $wgRequest->getText('action');
            $submit = $action == 'submit';
            $edit = $action == 'edit';
            $savePage = $wgRequest->getCheck('wpSave');
            $sameTitle = $wgRequest->getText('title');
            $sameTitle = str_replace(' ', '_', $sameTitle) == str_replace(' ', '_', $title->getFullText());
            // Check if the article contains protected properties that avert
            // editing the article
            // There is no need to check for protected properties if an edited article
            // is submitted. An article with protected properties may be saved if their
            // values are not changed. This is checked in method "onEditFilter" when
            // the article is about to be saved.
            if (($submit && !$savePage) || ($edit && $sameTitle))
            {
                // First condition:
                // The article is submitted but not saved (preview). This causes, that
                // the wikitext will be displayed.
                // Second condition:
                // The requested article is edited. Nevertheless, the passed $action
                // might be "read" as MW tries to show the articles source
                // => prohibit this if it contains properties without read-access
                $allowed = self::checkProperties($title, $userID, HACLLanguage::RIGHT_EDIT);
            }
            else
                $allowed = $savePage || self::checkProperties($title, $userID, $actionID);
            if (!$allowed)
            {
                $R = array('The article contains protected properties.', false, false);
                goto fin;
            }
        }

        $R = self::hasSD($title, $articleID, $userID, $actionID);

    fin:
        // Articles with no SD are not protected if $haclgOpenWikiAccess is
        // true. Otherwise access is denied for non-bureaucrats.
        if ($R[0] && (!$R[1] || !$R[2]))
            $R[0] .= ' ';
        if (!$R[2])
        {
            $R[2] = $R[1] = $haclgOpenWikiAccess;
            $R[0] .= 'No security descriptor for article found. IntraACL is configured to '.
                ($haclgOpenWikiAccess ? 'Open' : 'Closed').' Wiki access';
        }
        elseif (!$R[1])
        {
            $R[0] .= 'Access is denied.';
            $R[2] = false; // Other extensions can not decide anything if access is denied
        }

        haclfRestoreTitlePatch($etc);
        $result = $R[1];
        self::finishLog($R[0], $R[1], $R[2]);

        // If the user has no read access to a non-existing page,
        // but has the right to create it - allow him to "read" it,
        // because Wiki needs it to show the creation form.
        if ($actionID == HACLLanguage::RIGHT_READ && !$result && !$R[2] && !$articleID)
            $R[2] = self::userCan($title, $user, 'create', $result);

        return $R[2];
    }

    public static function hasSD($title, $articleID, $userID, $actionID)
    {
        $hasSD = false;
        $msg = array();

        if ($articleID)
        {
            // First check page rights
            $sd = HACLSecurityDescriptor::getSDForPE($articleID, HACLLanguage::PET_PAGE);
            $hasSD = $hasSD || $sd;
            if ($hasSD)
            {
                $r = self::hasRight($articleID, HACLLanguage::PET_PAGE, $userID, $actionID);
                $msg[] = ($r ? 'Access allowed by' : 'Found') . ' page SD.';
                if ($r)
                    goto ok;
            }

            // If the page is a category page, check the category right
            if ($title->getNamespace() == NS_CATEGORY)
            {
                $sd = HACLSecurityDescriptor::getSDForPE($articleID, HACLLanguage::PET_CATEGORY);
                $hasSD = $hasSD || $sd;
                if ($sd)
                {
                    $r = self::hasRight($articleID, HACLLanguage::PET_CATEGORY, $userID, $actionID);
                    $msg[] = ($r ? 'Access allowed by' : 'Found') . ' category SD for category page.';
                    if ($r)
                        goto ok;
                }
            }

            // Check category rights
            list($r, $sd) = self::hasCategoryRight($title, $userID, $actionID);
            $hasSD = $hasSD || $sd;
            if ($sd)
            {
                $msg[] = ($r ? 'Access allowed by' : 'Found') . ' category SD.';
                if ($r)
                    goto ok;
            }
        }

        // Check namespace rights
        list($r, $sd) = self::checkNamespaceRight($title->getNamespace(), $userID, $actionID);
        $hasSD = $hasSD || $sd;
        if ($sd)
        {
            $msg[] = ($r ? 'Access allowed by' : 'Found') . ' namespace SD.';
            if ($r)
                goto ok;
        }

ok:
        return array(implode(' ', $msg), $hasSD && $r, $hasSD);
    }

    /**
     * Checks if the given user has the right to perform the given action on
     * the given title. The hierarchy of categories is not considered here.
     *
     * @param  int $titleID
     *         ID of the protected object
     * @param  string $peType
     *         The type of the protection to check for the title. One of HACLLanguage::PET_*
     * @param  int $userID
     *         ID of the user who wants to perform an action
     * @param  int $actionID
     *         The action, the user wants to perform. One of HACLLanguage::RIGHT_*
     * @return bool
     *         <true>, if the user has the right to perform the action
     *         <false>, otherwise
     */
    public static function hasRight($titleID, $type, $userID, $actionID, $originNE = NULL)
    {
        // retrieve all appropriate rights from the database
        $rights = HACLStorage::getDatabase()->getRights($titleID, $type, $actionID, $originNE);

        // Check for all rights, if they are granted for the given user
        foreach ($rights as $right)
            if ($right->grantedForUser($userID))
                return true;

        return false;
    }

    /**
     * Checks, if the given user has the right to perform the given action on
     * the given property. (This happens only if protection of semantic properties
     * is enabled (see $haclgProtectProperties in HACL_Initialize.php))
     *
     * @param mixed(Title|int) $propertyTitle
     *         ID or title of the property whose rights are evaluated
     * @param int $userID
     *         ID of the user who wants to perform an action
     * @param int $actionID
     *         The action, the user wants to perform. One of the constant defined
     *         in HACLRight: READ, FORMEDIT, EDIT
     * @return bool
     *        <true>, if the user has the right to perform the action
     *         <false>, otherwise
     */
    public static function hasPropertyRight($propertyTitle, $userID, $actionID)
    {
        global $haclgProtectProperties;
        if (!$haclgProtectProperties)
        {
            // Protection of properties is disabled.
            return true;
        }

        if ($propertyTitle instanceof Title)
            $propertyTitle = $propertyTitle->getArticleID();

        $hasSD = HACLSecurityDescriptor::getSDForPE($propertyTitle, HACLLanguage::PET_PROPERTY) !== false;

        if (!$hasSD)
        {
            global $haclgOpenWikiAccess;
            // Properties with no SD are not protected if $haclgOpenWikiAccess is
            // true. Otherwise access is denied
            return $haclgOpenWikiAccess;
        }
        return self::hasRight($propertyTitle, HACLLanguage::PET_PROPERTY, $userID, $actionID);
    }

    /**
     * This function is called, before an article is saved.
     * If protection of properties is switched on, it checks if the article contains
     * properties that have been changed and for which the current user has no
     * access rights. In that case, saving the article is aborted and an error
     * message is displayed.
     *
     * @param EditPage $editor
     * @param string $text
     * @param $section
     * @param string $error
     *         If a property is not accessible, this error message is modified and
     *         displayed on the editor page.
     *
     * @return bool
     *         true
     */
    public static function onEditFilter($editor, $text, $section, &$error) {
        global $wgParser, $wgUser;
        $article = $editor->mArticle;
        $options = new ParserOptions;
        $options->enableLimitReport();
        self::$mMode = HACLEvaluator::ALLOW_PROPERTY_READ;
        $output = $wgParser->parse($article->preSaveTransform($text),
                                   $article->mTitle, $options);
        self::$mMode = HACLEvaluator::NORMAL;

        $protectedProperties = "";
        if (isset($output->mSMWData)) {
            foreach ($output->mSMWData->getProperties() as $name => $prop) {
                if (!$prop->userCan("propertyformedit")) {
                    // Access to property is restricted
                    if (!isset($oldPV)) {
                        // Get all old properties of the page from the semantic store
                        $oldPV = smwfGetStore()->getSemanticData($editor->mTitle);
                    }
                    if (self::propertyValuesChanged($prop, $oldPV, $output->mSMWData)) {
                        $protectedProperties .= "* $name\n";
                    }
                }
            }
        }
        if (empty($protectedProperties)) {
            self::$mSavePropertiesAllowed = true;
            return true;
        }

        self::$mSavePropertiesAllowed = false;
        $error = wfMsgForContent('hacl_sp_cant_save_article', $protectedProperties);

        // Special handling for semantic forms
        if (defined('SF_VERSION')) {
            include_once('includes/SpecialPage.php');
            $spt = SpecialPage::getTitleFor('EditData');
            $url = $spt->getFullURL();
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            if (strpos($referer, $url) === 0) {
                // A semantic form was saved.
                // => abort with an error message
                global $wgOut;
                $wgOut->addWikiText($error);
                return false;
            }
        }
        return true;
    }

    /**
     * This method is called when the difference of two revisions of an article is
     * about to be displayed.
     * If one of the revisions contains a property that can not be read, the mode
     * for the ACL evaluator is set accordingly for following calls to the userCan
     * hook.
     *
     * @param DifferenceEngine $diffEngine
     * @param Revision $oldRev
     * @param Revision $newRev
     * @return boolean true
     */
    public static function onDiffViewHeader(DifferenceEngine &$diffEngine, $oldRev, $newRev) {

        $newText = $diffEngine->mNewtext;
        if (!isset($newText)) {
            $diffEngine->loadText();
        }
        $newText = $diffEngine->mNewtext;
        $oldText = $diffEngine->mOldtext;

        global $wgParser;
        $options = new ParserOptions;
        $output = $wgParser->parse($newText, $diffEngine->mTitle, $options);

        if (isset($output->mSMWData)) {
            foreach ($output->mSMWData->getProperties() as $name => $prop) {
                if (!$prop->userCan("propertyread")) {
                    HACLEvaluator::$mMode = HACLEvaluator::DENY_DIFF;
                    return true;
                }
            }
        }

        $output = $wgParser->parse($oldText, $diffEngine->mTitle, $options);

        if (isset($output->mSMWData)) {
            foreach ($output->mSMWData->getProperties() as $name => $prop) {
                if (!$prop->userCan("propertyread")) {
                    HACLEvaluator::$mMode = HACLEvaluator::DENY_DIFF;
                    return true;
                }
            }
        }

        return true;
    }

    //--- Private methods ---

    /**
     * Checks, if the given user has the right to perform the given action on
     * the given title. The hierarchy of categories is evaluated.
     *
     * @param mixed string|array<string> $parents
     *         If a string is given, this is the name of an article whose parent
     *         categories are evaluated. Otherwise it is an array of parent category
     *         names
     * @param int $userID
     *         ID of the user who wants to perform an action
     * @param int $actionID
     *         The action, the user wants to perform. One of the constant defined
     *         in HACLRight: READ, FORMEDIT, EDIT, ANNOTATE, CREATE, MOVE and DELETE.
     * @param array<string> $visitedParents
     *         This array contains the names of all parent categories that were already
     *         visited.
     * @return array(bool rightGranted, bool hasSD)
     *         rightGranted:
     *             <true>, if the user has the right to perform the action
     *             <false>, otherwise
     *         hasSD:
     *             <true>, if there is an SD for the article
     *             <false>, if not
     */
    public static function hasCategoryRight($parents, $userID, $actionID, $visitedParents = array())
    {
        if (is_string($parents) || is_object($parents))
        {
            // The article whose parent categories shall be evaluated is given
            $t = is_object($parents) ? $parents : Title::newFromText($parents);
            if (!$t)
                return true;
            return self::hasCategoryRight(array_keys($t->getParentCategories()), $userID, $actionID);
        }
        elseif (is_array($parents))
        {
            if (!$parents)
                return array(false, false);
        }
        else
            return array(false, false);

        // Check for each parent if the right is granted
        $parentTitles = array();
        $hasSD = false;
        foreach ($parents as $p)
        {
            $parentTitles[] = $t = Title::newFromText($p);
            $id = $t->getArticleID();
            if ($id)
            {
                $sd = (HACLSecurityDescriptor::getSDForPE($id, HACLLanguage::PET_CATEGORY) !== false);
                if ($sd)
                {
                    $hasSD = true;
                    $r = self::hasRight($id, HACLLanguage::PET_CATEGORY, $userID, $actionID);
                    if ($r)
                        return array(true, true);
                }
            }
        }

        // No parent category has the required right
        // => check the next level of parents
        $parents = array();
        foreach ($parentTitles as $pt)
        {
            $ptParents = array_keys($pt->getParentCategories());
            foreach ($ptParents as $p)
            {
                if (!$visitedParents[$p])
                {
                    $parents[] = $p;
                    $visitedParents[$p] = true;
                }
            }
        }

        // Recursively check all parents
        list($r, $sd) = self::hasCategoryRight($parents, $userID, $actionID, $visitedParents);
        return array($r, $hasSD || $sd);
    }

    /**
     * Checks if access is granted to the namespace of the given title.
     *
     * @param int $nsID
     *        Namespace index
     * @param int $userID
     *        ID of the user who want to access the namespace
     * @param int $actionID
     *        ID of the action the user wants to perform
     *
     * @return array(bool rightGranted, bool hasSD)
     *         rightGranted:
     *             <true>, if the user has the right to perform the action
     *             <false>, otherwise
     *         hasSD:
     *             <true>, if there is an SD for the article
     *             <false>, if not
     *
     */
    public static function checkNamespaceRight($nsID, $userID, $actionID)
    {
        $hasSD = HACLSecurityDescriptor::getSDForPE($nsID, HACLLanguage::PET_NAMESPACE) !== false;
        if (!$hasSD)
            return array(false, false);
        return array(self::hasRight($nsID, HACLLanguage::PET_NAMESPACE, $userID, $actionID), $hasSD);
    }

    /**
     * This method checks if a user wants to create/modify
     * an article in the ACL namespace.
     *
     * @param Title $t
     *        The title.
     * @param User $user
     *        User-object of the user.
     * @param int $actionID
     *        ID of the action (one of HACLLanguage::RIGHT_*).
     *        In fact, $actionID=RIGHT_READ checks read access
     *        and any other action checks change access.
     *
     * @return bool <true>, if the user has the right to perform the action
     *              <false>, otherwise
     */
    public static function checkACLManager(Title $t, $user, $actionID)
    {
        $userID = $user->getId();
        // No access for anonymous users to ACL pages
        if (!$userID)
            return false;

        // Read access for all registered users
        // FIXME if not OpenWikiAccess, then false for users who can't read the article
        if ($actionID == HACLLanguage::RIGHT_READ)
            return true;

        // Sysops and bureaucrats can modify all ACL definitions
        $groups = $user->getGroups();
        if (in_array('sysop', $groups) || in_array('bureaucrat', $groups))
            return true;

        if (self::hacl_type($t) == 'group')
        {
            // Group
            $group = HACLStorage::getDatabase()->getGroupByID($t->getArticleID());
            return $group ? $group->userCanModify($userID) : true;
        }
        else
        {
            // SD / right template
            $sd = HACLStorage::getDatabase()->getSDByID($t->getArticleID());
            if ($sd)
                return $sd->userCanModify($userID);
            else
            {
                list($name, $type) = HACLSecurityDescriptor::nameOfPE($t->getText());
                if ($type == HACLLanguage::PET_PAGE)
                {
                    // Page ACL manage rights are inherited from RIGHT_MANAGE
                    // of categories and namespaces
                    $title = Title::newFromText($name);
                    $articleID = haclfArticleID($title);
                    $R = self::hasSD($title, $articleID, $userID, HACLLanguage::RIGHT_MANAGE);
                    if (!is_array($R))
                        die('IntraACL internal error: HACLEvaluator::hasSD returned non-array value');
                    // If there is no SD, allow action by default
                    return $R[1] || !$R[2];
                }
                else
                {
                    // Non-existing right templates and SDs for
                    // properties/namespaces/categories are editables by anyone
                    return true;
                }
            }
        }

        return false;
    }

    /* Check is $title corresponds to some IntraACL definition page
       Returns 'group', 'sd' or FALSE */
    public static function hacl_type($title)
    {
        global $haclgContLang;
        $text = is_object($title) ? $title->getText() : $title;
        if (($p = strpos($text, '/')) === false)
            return false;
        $prefix = substr($text, 0, $p);
        if ($t = $haclgContLang->getPrefix($prefix))
            return $t;
        return false;
    }

    /**
     * This method checks if a user wants to edit an article with protected
     * properties. (This happens only if protection of semantic properties
     * is enabled (see $haclgProtectProperties in HACL_Initialize.php))
     *
     * @param Title $t
     *         The title.
     * @param int $userID
     *         ID of the user.
     * @param int $actionID
     *         ID of the action. The actions FORMEDIT, WYSIWYG, EDIT, ANNOTATE,
     *      CREATE, MOVE and DELETE are relevant for managing an ACL object.
     *
     * @return bool
     *         rightGranted:
     *             <true>, if the user has the right to perform the action
     *             <false>, otherwise
     */
    private static function checkProperties(Title $t, $userID, $actionID) {
        global $haclgProtectProperties;
        global $wgRequest;
        if (!$haclgProtectProperties) {
            // Properties are not protected.
            return true;
        }

        if ($actionID == HACLLanguage::RIGHT_READ) {
            // The article is only read but not edited => action is allowed
            return true;
        }
        // Articles with protected properties are protected if an unauthorized
        // user wants to edit it
        if ($actionID != HACLLanguage::RIGHT_EDIT) {

            $a = @$wgRequest->data['action'];
            if (isset($a)) {
                // Some web request are translated to other actions before they
                // are passed to the userCan hook. E.g. action=history is passed
                // as action=read.
                // Articles with protected properties can be viewed, because the
                // property values are replaced by dummy text but showing the wikitext
                // (e.g. in the history) must be prohibited.

                // Define exceptions for actions that display only rendered text
                static $actionExceptions = array("purge","render","raw");
                if (in_array($a,$actionExceptions)) {
                    return true;
                }

            } else {
                return true;
            }

        }

        if (function_exists('smwfGetStore'))
            return true;
        // Get all properties of the page
        $semdata = smwfGetStore()->getSemanticData($t);
        $props = $semdata->getProperties();
        foreach ($props as $p) {
//            if (!$p->isShown()) {
//                // Ignore invisible(internal) properties
//                continue;
//            }
            // Check if a property is protected
            $wpv = $p->getWikiPageValue();
            if (!$wpv) {
                // no page for property
                continue;
            }
            $t = $wpv->getTitle();

            if (!self::hasPropertyRight($t, $userID, $actionID)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if access to a property should be evaluated. This is the case if
     * the string $action is one of 'propertyread', 'propertyformedit' or
     * 'propertyedit'.
     *
     * @param Title $title
     *         Title object for the property whose rights are checked.
     * @param User $user
     *         User who wants to access the property
     * @param string $action
     *         If this is one of 'propertyread', 'propertyformedit' or 'propertyedit'
     *         property rights are checked
     * @return bool / int
     *         <true>:  Access to the property is granted.
     *         <false>: Access to the property is denied.
     *      -1: $action is not concerned with properties.
     */
    private static function checkPropertyAccess(Title $title, User $user, $action)
    {
        if (self::$mMode == HACLEvaluator::DENY_DIFF)
            return false;
        if (self::$mMode == HACLEvaluator::ALLOW_PROPERTY_READ && $action == 'propertyread')
            return true;

        switch ($action)
        {
            case 'propertyread':
                $actionID = HACLLanguage::RIGHT_READ;
                break;
            case 'propertyformedit':
                $actionID = HACLLanguage::RIGHT_EDIT;
                break;
            case 'propertyedit':
                $actionID = HACLLanguage::RIGHT_EDIT;
                break;
            default:
                // No property access requested
                return -1;
        }
        if (self::$mSavePropertiesAllowed)
            return true;
        return self::hasPropertyRight($title, $user->getId(), $actionID);
    }

    /**
     * This function checks if the values of the property $property have changed
     * in the comparison of the semantic database ($oldValues) and the wiki text
     * that is about to be stored ($newValues).
     *
     * @param SMWPropertyValue $property
     *         The property whose old and new values are compared.
     * @param SMWSemanticData $oldValues
     *         The semantic data object with the old values
     * @param SMWSemanticData $newValues
     *         The semantic data object with the new values
     * @return boolean
     *         <true>, if values have been added, removed or changed,
     *         <false>, if values are exactly the same.
     */
    private static function propertyValuesChanged(
        SMWPropertyValue $property, SMWSemanticData $oldValues,
        SMWSemanticData $newValues)
    {
        // Get all old values of the property
        $oldPV = $oldValues->getPropertyValues($property);
        $oldValues = array();
        self::$mMode = HACLEvaluator::ALLOW_PROPERTY_READ;
        foreach ($oldPV as $v)
            $oldValues[$v->getHash()] = false;
        self::$mMode = HACLEvaluator::NORMAL;

        // Get all new values of the property
        $newPV = $newValues->getPropertyValues($property);
        foreach ($newPV as $v)
        {
            self::$mMode = HACLEvaluator::ALLOW_PROPERTY_READ;
            $wv = $v->getWikiValue();
            if (empty($wv))
            {
                // A property has an empty value => can be ignored
                continue;
            }

            $nv = $v->getHash();
            self::$mMode = HACLEvaluator::NORMAL;
            if (array_key_exists($nv, $oldValues))
            {
                // Old value was not changed
                $oldValues[$nv] = true;
            }
            else
            {
                // A new value was added
                return true;
            }
        }

        foreach ($oldValues as $stillThere)
        {
            if (!$stillThere)
            {
                // A property value has been deleted
                return true;
            }
        }

        // Property values have not changed.
        return false;
    }

    /**
     * Starts the log for an evaluation. The log string is assembled in self::mLog.
     *
     * @param Title $title
     * @param User $user
     * @param string $action
     */
    private static function startLog($title, $user, $action)
    {
        global $wgRequest, $haclgEvaluatorLog;

        self::$mLogEnabled = $haclgEvaluatorLog && $wgRequest->getVal('hacllog', 'false') == 'true';

        if (!self::$mLogEnabled)
        {
            // Logging is disabled
            return;
        }
        self::$mLog = "";

        self::$mLog .= "IntraACL Evaluation Log\n";
        self::$mLog .= "======================\n\n";
        self::$mLog .= "Article: ". (is_null($title) ? "null" : $title->getFullText()). "\n";
        self::$mLog .= "User: ". $user->getName(). "\n";
        self::$mLog .= "Action: $action\n";
    }

    /**
     * Adds a message to the evaluation log.
     *
     * @param string $msg
     *         The message to add.
     */
    private static function log($msg)
    {
        if (self::$mLogEnabled)
            self::$mLog .= "$msg\n";
    }

    /**
     * Finishes the log for an evaluation.
     *
     * @param string $msg
     *     This message is added to the log.
     * @param bool $result
     *     The result of the evaluation:
     *     true - action is allowed
     *     false - action is forbidden
     * @param bool $returnVal
     *     Return value of the userCan-hook:
     *     true - IntraACL allows the right or has no opinion about it. Other extensions must decide.
     *     false - IntraACL found a right and stops the chain of userCan-hooks.
     */
    private static function finishLog($msg, $result, $returnVal)
    {
        if (!self::$mLogEnabled)
        {
            // Logging is disabled
            return;
        }

        self::$mLog .= "$msg\n";
        self::$mLog .= "The action is ". ($result ? "allowed.\n" : "forbidden.\n");
        if ($returnVal)
        {
            // IntraACL has no opinion about the requested right.
            self::$mLog .= "The system and other extensions can still decide if this action is allowed.\n";
        }
        else
            self::$mLog .= "The right is determined by IntraACL. No other extensions can influence this.\n";
        self::$mLog .= "\n\n";

        echo self::$mLog;
    }
}
