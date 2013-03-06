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
 * WARNING: Now, members of "bureaucrat" and "sysop" Wiki groups (not IntraACL group) can always do anything.
 *
 * @author Thomas Schweitzer
 */
class HACLEvaluator
{
    //--- Private fields ---

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
        global $haclgOpenWikiAccess, $wgRequest;
        $etc = haclfDisableTitlePatch();

        $grant = array('', false, false);
        self::startLog($title, $user, $action);

        $grant = self::userCan_Switches($title, $user, $action);

        // Articles with no SD are not protected if $haclgOpenWikiAccess is
        // true. Otherwise access is denied for non-bureaucrats/sysops.
        if (!$grant[2])
        {
            $grant[2] = $grant[1] = $haclgOpenWikiAccess;
            if ($grant[0])
                $grant[0] .= ' ';
            $grant[0] .= 'No security descriptor for article found. IntraACL is configured to '.
                ($haclgOpenWikiAccess ? 'Open' : 'Closed').' Wiki access';
        }
        elseif (!$grant[1])
        {
            // Other extensions can not allow access anymore
            $grant[2] = false;
        }

        haclfRestoreTitlePatch($etc);
        $result = $grant[1];
        self::finishLog($grant[0], $grant[1], $grant[2]);

        // If the user has no read access to a non-existing page,
        // but has the right to create it - allow him to "read" it,
        // because Wiki needs it to show the creation form.
        if ($action == 'read' && !$result && !$grant[2] && !$title->exists())
            $grant[2] = self::userCan($title, $user, 'create', $result);

        return $grant[2];
    }

    // Returns array(final log message, access granted?, continue hook processing?)
    public static function userCan_Switches($title, $user, $action)
    {
        global $haclgContLang;
        if (!$title)
            return array("Title is <null>.", true, true);

        // Do not check interwiki links
        if ($title->getInterwiki() !== '')
            return array('Interwiki title', true, true);

        $groups = $user->getGroups();
        if ($groups && (in_array('bureaucrat', $groups) || in_array('sysop', $groups)))
            return array('User is a bureaucrat/sysop and can do anything.', true, true);

        // no access to the page "Permission denied" is allowed.
        // together with the TitlePatch which returns this page, this leads
        // to MediaWiki's "Permission error"
        if ($title->getPrefixedText() == $haclgContLang->getPermissionDeniedPage())
            return array('Special handling of "Permission denied" page.', false, true);

        // Check action
        $actionID = HACLRight::getActionID($action);
        if ($actionID == 0)
        {
            // unknown action => nothing can be said about this
            return array('Unknown action.', true, true);
        }

        // Check rights for managing ACLs
        if ($title->getNamespace() == HACL_NS_ACL)
            return array('Checked ACL modification rights.', self::checkACLManager($title, $user, $actionID), true);

        // If there is a whitelist, then allow user to read the page
        global $wgWhitelistRead;
        if ($wgWhitelistRead && $actionID == HACLLanguage::RIGHT_READ && in_array($title, $wgWhitelistRead, false))
            return array('Page is in MediaWiki whitelist', true, true);

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
                return array('Can\'t move or delete non-existing article.', true, true);
            // Check if the title belongs to a namespace with an SD
            list($r, $sd) = self::checkNamespaceRight($title->getNamespace(), $userID, $actionID);
            return array("Checked namespace access right: ($r,$sd)", $r, $sd);
        }

        return self::hasSD($title, $articleID, $userID, $actionID);
    }

    // Checks if user $userID can do action $actionID on article $articleID (or $title)
    // Returns array(log message, has right, has SD)
    // Check sequence: page rights -> category rights -> namespace rights
    // Global $haclgCombineMode specifies override mode.
    public static function hasSD($title, $articleID, $userID, $actionID)
    {
        global $haclgCombineMode;
        $msg = array();

        if ($articleID)
        {
            // First check page rights
            $sd = HACLSecurityDescriptor::getSDForPE($articleID, HACLLanguage::PET_PAGE);
            if ($sd)
            {
                $r = self::hasRight($articleID, HACLLanguage::PET_PAGE, $userID, $actionID);
                $msg[] = 'Access '.($r ? 'allowed' : 'denied').' by page SD';
                if ($haclgCombineMode == HACL_COMBINE_OVERRIDE ||
                    $haclgCombineMode == HACL_COMBINE_EXTEND && $r ||
                    $haclgCombineMode == HACL_COMBINE_SHRINK && !$r)
                    return array(implode(', ', $msg), $r, true);
            }

            // If the page is a category page, check that category's rights
            if ($title->getNamespace() == NS_CATEGORY)
            {
                $sd = HACLSecurityDescriptor::getSDForPE($articleID, HACLLanguage::PET_CATEGORY);
                if ($sd)
                {
                    $r = self::hasRight($articleID, HACLLanguage::PET_CATEGORY, $userID, $actionID);
                    $msg[] = 'Access '.($r ? 'allowed' : 'denied').' by category SD for category page';
                    if ($haclgCombineMode == HACL_COMBINE_OVERRIDE ||
                        $haclgCombineMode == HACL_COMBINE_EXTEND && $r ||
                        $haclgCombineMode == HACL_COMBINE_SHRINK && !$r)
                        return array(implode(', ', $msg), $r, true);
                }
            }

            // Check category rights
            list($r, $sd) = self::hasCategoryRight($title, $userID, $actionID);
            if ($sd)
            {
                $msg[] = 'Access '.($r ? 'allowed' : 'denied').' by category SD';
                if ($haclgCombineMode == HACL_COMBINE_OVERRIDE ||
                    $haclgCombineMode == HACL_COMBINE_EXTEND && $r ||
                    $haclgCombineMode == HACL_COMBINE_SHRINK && !$r)
                    return array(implode(', ', $msg), $r, true);
            }
        }

        // Check namespace rights
        list($r, $sd) = self::checkNamespaceRight($title->getNamespace(), $userID, $actionID);
        if ($sd)
        {
            $msg[] = 'Access '.($r ? 'allowed' : 'denied').' by namespace SD';
            if ($haclgCombineMode == HACL_COMBINE_OVERRIDE ||
                $haclgCombineMode == HACL_COMBINE_EXTEND && $r ||
                $haclgCombineMode == HACL_COMBINE_SHRINK && !$r)
                return array(implode(', ', $msg), $r, true);
        }

        // If mode is extend and we are here, maybe a denying SD was found.
        // If mode is shrink and we are here, maybe an allowing SD was found.
        // If it is found, $msg is not empty.
        if ($msg && $haclgCombineMode != HACL_COMBINE_OVERRIDE)
            return array(implode(', ', $msg), $haclgCombineMode == HACL_COMBINE_SHRINK, true);

        return array('', false, false);
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
        $rights = IACLStorage::get('IR')->getRights($titleID, $type, $actionID, $originNE);

        // Check for all rights, if they are granted for the given user
        foreach ($rights as $right)
            if ($right->grantedForUser($userID))
                return true;

        return false;
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
                if (empty($visitedParents[$p]))
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
            $group = IACLStorage::get('Groups')->getGroupByID($t->getArticleID());
            return $group ? $group->userCanModify($userID) : true;
        }
        else
        {
            // SD / right template
            $sd = IACLStorage::get('SD')->getSDByID($t->getArticleID());
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
                    // Anyone is allowed to edit non-existing right
                    // templates and SDs for namespaces/categories
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
     * Starts the log for an evaluation. The log string is assembled in self::mLog.
     *
     * @param Title $title
     * @param User $user
     * @param string $action
     */
    private static function startLog($title, $user, $action)
    {
        global $wgRequest, $haclgEvaluatorLog, $haclgCombineMode;

        self::$mLogEnabled = $haclgEvaluatorLog && $wgRequest->getVal('hacllog', 'false') == 'true';

        if (!self::$mLogEnabled)
        {
            // Logging is disabled
            return;
        }
        self::$mLog = "";

        self::$mLog .= "IntraACL Evaluation Log\n";
        self::$mLog .= "======================\n\n";
        self::$mLog .= "Title: ". (is_null($title) ? "null" : $title->getFullText()). "\n";
        self::$mLog .= "User: ". $user->getName(). "\n";
        self::$mLog .= "Action: $action; mode: $haclgCombineMode\n";
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
