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

/**
 * This is the main class for the evaluation of user rights for a protected object.
 * It implements the function "userCan" that is called from MW for granting or
 * denying access to articles.
 */
class IACLEvaluator
{
    // String with logging information
    static $mLog = "";

    // Are IntraACL's logging activities enabled?
    static $mLogEnabled = false;

    /**
     * This function is called from the userCan-hook of MW. This method decides
     * if the article for the given title can be accessed.
     * See further information at: http://www.mediawiki.org/wiki/Manual:Hooks/userCan
     *
     * TODO: Switch to getUserPermissionsErrors hooks
     *
     * @param Title $title      The title object for the article that will be accessed.
     * @param User $user        Reference to the current user.
     * @param string $action    Action concerning the title in question
     * @param boolean $result   Reference to the result propagated along the chain of hooks.
     */
    public static function userCan($title, $user, $action, &$result)
    {
        global $haclgOpenWikiAccess;

        self::startLog($title, $user, $action);

        $etc = haclfDisableTitlePatch();
        list($msg, $result) = self::userCan_Switches($title, $user, $action);
        haclfRestoreTitlePatch($etc);

        if ($msg)
        {
            self::log($msg);
        }
        // Articles with no SD are not protected if $haclgOpenWikiAccess is
        // true. Otherwise access is denied for non-bureaucrats/sysops.
        if ($result < 0)
        {
            $result = $haclgOpenWikiAccess;
            self::log(
                'No security descriptor for article found. IntraACL is configured to '.
                ($haclgOpenWikiAccess ? 'Open' : 'Closed').' Wiki access'
            );
        }
        self::log("The action is " . ($result ? "allowed.\n\n" : "forbidden.\n\n"));
        self::finishLog();

        // Always continue hook processing
        return true;
    }

    /**
     * Returns array(final log message, access granted?, continue hook processing?)
     */
    public static function userCan_Switches($title, $user, $action)
    {
        global $haclgContLang, $haclgSuperGroups;
        if (!$title)
        {
            return array('Title is <null>', 1);
        }
        if ($title->getInterwiki() !== '')
        {
            // Do not check interwiki links
            return array('Interwiki title', 1);
        }

        $groups = $user->getGroups();
        if ($groups && array_intersect($groups, $haclgSuperGroups))
        {
            return array('User is a superuser and can do anything.', 1);
        }

        if ($title->getPrefixedText() == $haclgContLang->getPermissionDeniedPage())
        {
            // no access to the page "Permission denied" emitted by TitlePatch is allowed
            return array('Special handling of "Permission denied" page', 0);
        }

        // Check action
        $actionID = IACL::getActionID($action);
        if (!$actionID)
        {
            // Unknown action => nothing can be said about this
            return array('Unknown action', 1);
        }

        // Check rights for managing ACLs
        if ($title->getNamespace() == HACL_NS_ACL)
        {
            return array('Checked ACL modification rights', self::checkACLManager($title, $user, $actionID));
        }

        // If there is a whitelist, then allow user to read the page
        global $wgWhitelistRead;
        if ($wgWhitelistRead && $actionID == IACL::ACTION_READ && in_array($title, $wgWhitelistRead, false))
        {
            return array('Page is in MediaWiki whitelist', 1);
        }

        // haclfArticleID also returns IDs for special pages
        $articleID = haclfArticleID($title);
        $userID = $user->getId();
        if ($articleID && $actionID == IACL::ACTION_CREATE)
        {
            // create=edit for existing articles
            $actionID = IACL::ACTION_EDIT;
        }
        elseif (!$articleID)
        {
            if ($actionID == IACL::ACTION_EDIT)
            {
                // edit=create for non-existing articles
                self::log('Article does not exist yet. Checking right to create.');
                $actionID = IACL::ACTION_CREATE;
            }
            elseif ($actionID == IACL::ACTION_DELETE || $actionID == IACL::ACTION_MOVE)
            {
                return array('Moving/deleting non-existing article is pointless', 1);
            }
            $r = IACLDefinition::userCan($userID, IACL::PE_NAMESPACE, $title->getNamespace(), $actionID);
            if ($r <= 0 && $actionID == IACL::ACTION_READ)
            {
                // Read right is needed to show edit form
                $r = IACLDefinition::userCan($userID, IACL::PE_NAMESPACE, $title->getNamespace(), IACL::ACTION_CREATE);
            }
            return array('Checked namespace access right', $r);
        }

        return self::hasSD($title, $articleID, $userID, $actionID);
    }

    /**
     * Checks if user $userID can do action $actionID on article $articleID (or $title)
     * Returns array(log message, has right, has SD)
     * Check sequence: page rights -> category rights -> namespace rights
     * Global $haclgCombineMode specifies override mode.
     */
    public static function hasSD($title, $articleID, $userID, $actionID)
    {
        global $haclgCombineMode;

        $seq = array();
        if ($articleID)
        {
            if ($title->getNamespace() == NS_SPECIAL)
            {
                // Check special page rights (special pages have no categories)
                $seq[] = array('special page SD', IACL::PE_SPECIAL, -$articleID);
            }
            else
            {
                // First check page rights
                $seq[] = array('page SD', IACL::PE_PAGE, $articleID);
                if ($title->getNamespace() == NS_CATEGORY)
                {
                    // If the page is a category page, check that category's rights
                    $seq[] = array('category SD for category page', IACL::PE_CATEGORY, $articleID);
                }
                // Check category rights
                $seq[] = array('category SD', IACL::PE_CATEGORY, IACLStorage::get('Util')->getParentCategoryIDs($articleID));
            }
        }
        $seq[] = array('namespace SD', IACL::PE_NAMESPACE, $title->getNamespace());

        $msg = array();
        foreach ($seq as $pe)
        {
            $r = IACLDefinition::userCan($userID, $pe[1], $pe[2], $actionID);
            if ($r >= 0)
            {
                $msg[] = 'Access '.($r > 0 ? 'allowed' : 'denied').' by '.$pe[0];
            }
            if ($haclgCombineMode == HACL_COMBINE_OVERRIDE && $r >= 0 ||
                $haclgCombineMode == HACL_COMBINE_EXTEND && $r > 0 ||
                $haclgCombineMode == HACL_COMBINE_SHRINK && $r == 0)
            {
                return array(implode("\n", $msg), $r);
            }
        }

        // If $msg is not empty and mode is extend => denying SD was found.
        // If $msg is not empty and mode is shrink => allowing SD was found.
        // If $msg is not empty and mode is shrink => we've already returned.
        if ($msg)
        {
            return array(implode("\n", $msg), $haclgCombineMode == HACL_COMBINE_SHRINK ? 1 : 0);
        }

        return array('', -1);
    }

    /**
     * This method checks if a user wants to create/modify an article in the ACL namespace.
     * Should not be used outside of IACLEvaluator because doesn't do any additional access checks.
     *
     * @param Title $t
     * @param User $user
     * @param int $actionID     Action ID
     * @return bool             Whether the user has the right to perform the action
     */
    protected static function checkACLManager(Title $t, User $user, $actionID)
    {
        global $haclgSuperGroups;
        $userID = $user->getId();
        if (!$userID)
        {
            // No access for anonymous users to ACL pages
            return false;
        }

        if ($actionID == IACL::ACTION_READ)
        {
            // Read access for all registered users
            // FIXME if not OpenWikiAccess, then return false for users who can't read the article
            return true;
        }

        $peId = IACLDefinition::nameOfPE($t);
        if (!$peId)
        {
            return false;
        }
        $peId[1] = IACLDefinition::peIDforName($peId[0], $peId[1]);
        if (IACLDefinition::userCan($userID, $peId[0], $peId[1], IACL::ACTION_MANAGE))
        {
            return true;
        }

        // "protect page" right is a hole
        // 1) user A has read+edit access to article X
        // 2) he adds [[Category:HisOwnCategory]] marker to article X
        // 3) ACL:Category/HisOwnCategory grants PROTECT_PAGES to him
        // 4) he gets the right to change ACL:Page/X
        // 5) he removes all other users from ACL:Page/X => no one more can see the article :-(
        // 6) okay, but per-namespace "protect page" right is also a hole
        // 7) and "move page" right with namespace rights is also a hole
        // 8) and user who can edit the article always can remove all categories from it
        // 9) soooooooooo...
        // "move page" right is a hole
        // category rights are a hole - any editor can change them

        // Check for ACTION_PROTECT_PAGES inherited from namespaces and categories
        if ($peId[0] == IACL::PE_PAGE && self::checkProtectPageRight($peId[1], $userID))
        {
            return true;
        }
        return false;
    }

    protected static function checkProtectPageRight($pageID, $userID)
    {
        $etc = haclfDisableTitlePatch();
        $title = Title::newFromId($pageID);
        haclfRestoreTitlePatch($etc);
        return
            IACLDefinition::userCan($userID, IACL::PE_NAMESPACE, $title->getNamespace(), IACL::ACTION_PROTECT_PAGES) > 0 ||
            IACLDefinition::userCan($userID, IACL::PE_CATEGORY, IACLStorage::get('Util')->getParentCategoryIDs($pageID), IACL::ACTION_PROTECT_PAGES) > 0;
    }

    /**
     * Starts the log for an evaluation. The log string is assembled in self::mLog.
     *
     * @param Title $title
     * @param User $user
     * @param string $action
     */
    static function startLog($title, $user, $action)
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
     * @param string $msg   The message to add.
     */
    static function log($msg)
    {
        if (self::$mLogEnabled)
        {
            self::$mLog .= "$msg\n";
        }
    }

    /**
     * Finishes the log for an evaluation.
     */
    static function finishLog()
    {
        if (!self::$mLogEnabled)
        {
            // Logging is disabled
            return;
        }
        // FIXME emit this in <pre> and after the page content
        echo self::$mLog;
    }
}
