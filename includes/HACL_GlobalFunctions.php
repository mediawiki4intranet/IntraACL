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
 * This file contains global functions that are called from the Halo-Access-Control-List
 * extension.
 */
if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

/**
 * Switch on Halo Access Control Lists. This function must be called in
 * LocalSettings.php after HACL_Initialize.php was included and default values
 * that are defined there have been modified.
 * For readability, this is the only global function that does not adhere to the
 * naming conventions.
 *
 * This function installs the extension, sets up all autoloading, special pages
 * etc.
 */
function enableIntraACL()
{
    global $haclgIP;

    // Register messages
    global $wgExtensionFunctions, $wgExtensionMessagesFiles, $wgVersion;
    $wgExtensionFunctions[] = 'haclfSetupExtension';
    $wgExtensionMessagesFiles['IntraACL'] = $haclgIP . '/languages/HACL_Messages.php';

    // Register special pages
    global $wgSpecialPages, $wgSpecialPageGroups;
    $wgSpecialPages['IntraACL'] = array('IntraACLSpecial');
    $wgSpecialPageGroups['IntraACL'] = 'hacl_group';

    // Set up autoloading; essentially all classes should be autoloaded!
    global $wgAutoloadClasses;
    $wgAutoloadClasses += array(
        // Internals
        'HACLParserFunctions'       => "$haclgIP/includes/HACL_ParserFunctions.php",
        'HACLEvaluator'             => "$haclgIP/includes/HACL_Evaluator.php",
        'HACLGroup'                 => "$haclgIP/includes/HACL_Group.php",
        'HACLSecurityDescriptor'    => "$haclgIP/includes/HACL_SecurityDescriptor.php",
        'HACLRight'                 => "$haclgIP/includes/HACL_Right.php",
        'HACLQuickacl'              => "$haclgIP/includes/HACL_Quickacl.php",
        'HACLToolbar'               => "$haclgIP/includes/HACL_Toolbar.php",

        // Special page
        'IntraACLSpecial'           => "$haclgIP/includes/HACL_ACLSpecial.php",

        // Exception classes
        'HACLException'             => "$haclgIP/includes/HACL_Exception.php",
        'HACLGroupException'        => "$haclgIP/includes/HACL_Exception.php",
        'HACLSDException'           => "$haclgIP/includes/HACL_Exception.php",
        'HACLRightException'        => "$haclgIP/includes/HACL_Exception.php",

        // Storage
        'IACLStorage'               => "$haclgIP/storage/Storage.php",
        'IntraACL_SQL_Groups'       => "$haclgIP/storage/SQL_Groups.php",
        'IntraACL_SQL_IR'           => "$haclgIP/storage/SQL_IR.php",
        'IntraACL_SQL_QuickACL'     => "$haclgIP/storage/SQL_QuickACL.php",
        'IntraACL_SQL_SD'           => "$haclgIP/storage/SQL_SD.php",
        'IntraACL_SQL_SpecialPage'  => "$haclgIP/storage/SQL_SpecialPage.php",
        'IntraACL_SQL_Util'         => "$haclgIP/storage/SQL_Util.php",
    );

    // ACL update hooks are registered even in commandline.
    global $wgHooks;
    $wgHooks['ArticleViewHeader'][]     = 'HACLParserFunctions::articleViewHeader';
    $wgHooks['OutputPageBeforeHTML'][]  = 'HACLParserFunctions::outputPageBeforeHTML';
    if ($wgVersion < '1.14')
        $wgHooks['NewRevisionFromEditComplete'][] = 'HACLParserFunctions::NewRevisionFromEditComplete';
    else
        $wgHooks['ArticleEditUpdates'][] = 'HACLParserFunctions::ArticleEditUpdates';
    $wgHooks['ArticleDelete'][]         = 'HACLParserFunctions::articleDelete';
    $wgHooks['ArticleUndelete'][]       = 'HACLParserFunctions::articleUndelete';
    $wgHooks['TitleMoveComplete'][]     = 'HACLParserFunctions::TitleMoveComplete';
    $wgHooks['LanguageGetMagic'][]      = 'haclfLanguageGetMagic';
    $wgHooks['LoadExtensionSchemaUpdates'][] = 'haclfLoadExtensionSchemaUpdates';

    return true;
}

function haclfLanguageGetMagic(&$magicWords, $langCode)
{
    global $haclgContLang;
    $magicWords['haclaccess']           = array(0, $haclgContLang->getParserFunction(HACLLanguage::PF_ACCESS));
    $magicWords['haclpredefinedright']  = array(0, $haclgContLang->getParserFunction(HACLLanguage::PF_PREDEFINED_RIGHT));
    $magicWords['haclmanagerights']     = array(0, $haclgContLang->getParserFunction(HACLLanguage::PF_MANAGE_RIGHTS));
    $magicWords['haclmember']           = array(0, $haclgContLang->getParserFunction(HACLLanguage::PF_MEMBER));
    $magicWords['haclmanagegroup']      = array(0, $haclgContLang->getParserFunction(HACLLanguage::PF_MANAGE_GROUP));
    return true;
}

/**
 * Do the actual initialisation of the extension. This is just a delayed init that
 * makes sure MediaWiki is set up properly before we add our stuff.
 *
 * The main things this function does are: register all hooks, set up extension
 * credits, and init some globals that are not for configuration settings.
 */
function haclfSetupExtension()
{
    wfProfileIn(__FUNCTION__);

    global $haclgIP, $wgHooks, $wgParser, $wgExtensionCredits,
        $wgLanguageCode, $wgRequest, $wgContLang, $haclgUnprotectableNamespaces, $haclgUnprotectableNamespaceIds;

    /* Title patch is disabled until full initialization of extension.
     * This was formerly done with haclfDisableTitlePatch() in the beginning
     * of this file and haclfRestoreTitlePatch() here.
     * But this does not allow changing $haclgEnableTitlePatch after enabling IntraACL.
     */
    if (!empty($_SERVER['SERVER_NAME']))
    {
        define('HACL_HALOACL_VERSION', '1.0');

        // UI hooks - useless in console mode
        $wgHooks['EditPage::showEditForm:initial'][] = 'HACLToolbar::warnNonReadableCreate';
        $wgHooks['UploadForm:initial'][] = 'HACLToolbar::warnNonReadableUpload';
        $wgHooks['EditPage::attemptSave'][] = 'HACLToolbar::attemptNonReadableCreate';
        $wgHooks['EditPage::showEditForm:fields'][] = 'haclfAddToolbarForEditPage';
        $wgHooks['SkinTemplateContentActions'][] = 'HACLToolbar::SkinTemplateContentActions';
        $wgHooks['SkinTemplateNavigation'][] = 'HACLToolbar::SkinTemplateNavigation';
        // UI hooks used to update permissions along with article modification
        // ArticleSaveComplete_SaveSD hook must run before articleSaveComplete_SaveEmbedded
        $wgHooks['ArticleSaveComplete'][] = 'HACLToolbar::articleSaveComplete_SaveSD';
        $wgHooks['ArticleSaveComplete'][] = 'HACLToolbar::articleSaveComplete_SaveEmbedded';

        // Permission and cache checks - intentionally disabled in console mode
        $wgHooks['userCan'][] = 'HACLEvaluator::userCan';
        $wgHooks['IsFileCacheable'][] = 'haclfIsFileCacheable';
        $wgHooks['PageRenderingHash'][] = 'haclfPageRenderingHash';
    }
    else
    {
        // Also disable security checks in console mode
        // Also issue a warning as an insurance to not run Wiki in some bad setup
        file_put_contents('php://stderr', '** WARNING: IntraACL security checks are disabled because
** $_SERVER[SERVER_NAME] is empty, which probably means we are in console
');
    }

    //--- Transform config (unprotectable namespace names to ids) ---
    $haclgUnprotectableNamespaceIds = array();
    foreach ($haclgUnprotectableNamespaces as $ns)
    {
        $ns = $wgContLang->getNsIndex($ns);
        if ($ns !== false)
            $haclgUnprotectableNamespaceIds[$ns] = true;
    }

    wfLoadExtensionMessages('IntraACL');

    $wgHooks['GetPreferences'][] = 'HACLToolbar::GetPreferences';

    //-- includes for Ajax calls --
    global $wgUseAjax, $wgRequest;
    if ($wgUseAjax && $wgRequest->getVal('action') == 'ajax' ) {
        $funcName = isset( $_POST["rs"] )
                        ? $_POST["rs"]
                        : (isset( $_GET["rs"] ) ? $_GET["rs"] : NULL);
        if (strpos($funcName, 'hacl') === 0) {
            require_once("$haclgIP/includes/HACL_Toolbar.php");
            require_once("$haclgIP/includes/HACL_AjaxConnector.php");
        }
    }

    //--- credits (see "Special:Version") ---
    $wgExtensionCredits['other'][] = array(
        'name'        => 'IntraACL',
        'version'     => '2011-12-30',
        'author'      => "Vitaliy Filippov, Stas Fomin, Thomas Schweitzer",
        'url'         => 'http://wiki.4intra.net/IntraACL',
        'description' => 'The best MediaWiki rights extension, based on HaloACL.');

    // HACLParserFunctions callbacks
    $wgParser->setFunctionHook('haclaccess',            'HACLParserFunctions::access');
    $wgParser->setFunctionHook('haclpredefinedright',   'HACLParserFunctions::predefinedRight');
    $wgParser->setFunctionHook('haclmanagerights',      'HACLParserFunctions::manageRights');
    $wgParser->setFunctionHook('haclmember',            'HACLParserFunctions::addMember');
    $wgParser->setFunctionHook('haclmanagegroup',       'HACLParserFunctions::manageGroup');

    haclCheckScriptPath();

    wfProfileOut(__FUNCTION__);
    return true;
}

/**
 * Check $haclgHaloScriptPath and prepend it with $wgScriptPath
 * if it is not yet absolute.
 */
function haclCheckScriptPath()
{
    global $haclgHaloScriptPath, $wgScriptPath;
    if ($haclgHaloScriptPath{0} != '/')
        $haclgHaloScriptPath = $wgScriptPath.'/'.$haclgHaloScriptPath;
    return $haclgHaloScriptPath;
}

/**********************************************/
/***** namespace settings                 *****/
/**********************************************/

/**
 * Init the additional namespaces used by IntraACL. The
 * parameter denotes the least unused even namespace ID that is
 * greater or equal to 100.
 */
function haclfInitNamespaces()
{
    global $haclgNamespaceIndex, $wgExtraNamespaces, $wgNamespaceAliases,
        $wgNamespacesWithSubpages, $wgLanguageCode, $haclgContLang;

    if (!isset($haclgNamespaceIndex))
        $haclgNamespaceIndex = 300;

    define('HACL_NS_ACL',       $haclgNamespaceIndex);
    define('HACL_NS_ACL_TALK',  $haclgNamespaceIndex+1);

    haclfInitContentLanguage($wgLanguageCode);

    // Register namespace identifiers
    if (!is_array($wgExtraNamespaces))
        $wgExtraNamespaces = array();
    $namespaces = $haclgContLang->getNamespaces();
    $namespacealiases = $haclgContLang->getNamespaceAliases();
    $wgExtraNamespaces = $wgExtraNamespaces + $namespaces;
    $wgNamespaceAliases = $wgNamespaceAliases + $namespacealiases;

    // Support subpages for the namespace ACL
    $wgNamespacesWithSubpages = $wgNamespacesWithSubpages + array(
        HACL_NS_ACL => true,
        HACL_NS_ACL_TALK => true,
    );
}

/**********************************************/
/***** language settings                  *****/
/**********************************************/

/**
 * Initialise a global language object for content language. This
 * must happen early on, even before user language is known, to
 * determine labels for additional namespaces. In contrast, messages
 * can be initialised much later when they are actually needed.
 */
function haclfInitContentLanguage($langcode)
{
    global $haclgIP, $haclgContLang;
    if (!empty($haclgContLang))
        return;
    wfProfileIn(__FUNCTION__);

    $haclContLangFile = 'HACL_Language' . str_replace('-', '_', ucfirst($langcode));
    $haclContLangClass = 'HACLLanguage' . str_replace('-', '_', ucfirst($langcode));
    require_once "$haclgIP/languages/HACL_Language.php";
    if (file_exists("$haclgIP/languages/$haclContLangFile.php"))
        include_once("$haclgIP/languages/$haclContLangFile.php");

    // fallback if language not supported
    if (!class_exists($haclContLangClass))
    {
        include_once($haclgIP . '/languages/HACL_LanguageEn.php');
        $haclContLangClass = 'HACLLanguageEn';
    }
    $haclgContLang = new $haclContLangClass();

    wfProfileOut(__FUNCTION__);
}

/**
 * Returns the ID and name of the given user.
 *
 * @param User/string/int $user
 *         User-object, name of a user or ID of a user. If <null> (which is the
 *      default), the currently logged in user is assumed.
 *      There are two special user names:
 *            '*' - all users including anonymous (ID: 0)
 *            '#' - all registered users (ID: -1)
 * @return array(int,string)
 *         (Database-)ID of the given user and his name. For the sake of
 *      performance the name is not retrieved, if the ID of the user is
 *         passed in parameter $user.
 * @throws
 *         HACLException(HACLException::UNKNOWN_USER)
 *             ...if the user does not exist.
 */
function haclfGetUserID($user = null, $throw_error = true)
{
    $userID = false;
    $userName = '';
    if ($user === NULL)
    {
        // no user given
        // => the current user's ID is requested
        global $wgUser;
        $userID = $wgUser->getId();
        $userName = $wgUser->getName();
    }
    elseif (is_int($user) || is_numeric($user))
    {
        // user-id given
        $userID = (int) $user;
    }
    elseif (is_string($user))
    {
        if ($user == '#')
        {
            // Special name for all registered users
            $userID = -1;
        }
        elseif ($user == '*')
        {
            // Anonymous user
            $userID = 0;
        }
        else
        {
            // name of user given
            $etc = haclfDisableTitlePatch();
            $userID = User::idFromName($user);
            haclfRestoreTitlePatch($etc);
            if (!$userID)
                $userID = false;
            $userName = $user;
        }
    }
    elseif (is_a($user, 'User'))
    {
        // User-object given
        $userID = $user->getId();
        $userName = $user->getName();
    }

    if ($userID === 0)
    {
        // Anonymous user
        $userName = '*';
    }
    elseif ($userID === -1)
    {
        // all registered users
        $userName = '#';
    }

    if ($userID === false && $throw_error)
    {
        // invalid user
        throw new HACLException(HACLException::UNKNOWN_USER, '"'.$user.'"');
    }

    return array($userID, $userName);
}

/**
 * Pages in the namespace ACL are not cacheable
 *
 * @param Article $article
 *         Check, if this article can be cached
 *
 * @return bool
 *         <true>, for articles that are not in the namespace ACL
 *         <false>, otherwise
 */
function haclfIsFileCacheable($article)
{
    return $article->getTitle()->getNamespace() != HACL_NS_ACL;
}

/**
 * The hash for the page cache depends on the user.
 * TODO: Rework this along with the new IntraACL caching system.
 *
 * @param string $hash
 *         A reference to the hash. This the ID of the current user is appended
 *         to this hash.
 */
function haclfPageRenderingHash(&$hash)
{
    global $wgUser, $wgTitle;
    if (is_object($wgUser))
        $hash .= '!'.$wgUser->getId();
    return true;
}

/**
 * A patch in the Title-object checks for each creation of a title, if access
 * to this title is granted. While the rights for a title are evaluated, this
 * may lead to a recursion. So the patch can be switched off. After the critical
 * operation (typically Title::new... ), the patch should be switched on again with
 * haclfRestoreTitlePatch().
 *
 * @return bool
 *         The current state of the Title-patch. This value has to be passed to
 *         haclfRestoreTitlePatch().
 */
function haclfDisableTitlePatch()
{
    global $haclgEnableTitleCheck;
    $etc = $haclgEnableTitleCheck;
    $haclgEnableTitleCheck = false;
    return $etc;
}

/**
 * See documentation of haclfDisableTitlePatch
 *
 * @param bool $etc
 *         The former state of the title patch.
 */
function haclfRestoreTitlePatch($etc)
{
    global $haclgEnableTitleCheck;
    $haclgEnableTitleCheck = $etc;
}

/**
 * Returns the article ID for a given article name. This function has a special
 * handling for Special pages, which do not have an article ID. IntraACL stores
 * special IDs for these pages. Their IDs are always negative while the IDs of
 * normal pages are positive.
 *
 * @param string $articleName
 *         Name of the article
 * @param int $defaultNS
 *         The default namespace if no namespace is given in the name
 *
 * @return int
 *         ID of the article:
 *         >0: ID of an article in a normal namespace
 *         =0: Name of the article is invalid
 *         <0: ID of a Special Page
 *
 */
function haclfArticleID($articleName, $defaultNS = NS_MAIN)
{
    $t = $articleName;
    if (!is_object($t))
    {
        $etc = haclfDisableTitlePatch();
        $t = Title::newFromText($articleName, $defaultNS);
        haclfRestoreTitlePatch($etc);
    }
    if (!$t)
        return 0;
    if ($t->getNamespace() == NS_SPECIAL)
        return IACLStorage::get('SpecialPage')->idForSpecial($t->getBaseText());
    $id = $t->getArticleID();
    if ($id === 0)
        $id = $t->getArticleID(Title::GAID_FOR_UPDATE);
    return $id;
}

/**
 * This function is called from the hook 'EditPage::showEditForm:fields'.
 * It adds the ACL toolbar to edited pages.
 */
function haclfAddToolbarForEditPage($editpage, $out)
{
    if ($editpage->mTitle->mNamespace == HACL_NS_ACL)
        return true;
    $out->addHTML(HACLToolbar::get($editpage->mTitle, !empty($editpage->eNonReadable)));
    return true;
}

// Hook into maintenance/update.php
function haclfLoadExtensionSchemaUpdates($updater = NULL)
{
    global $wgExtNewTables, $wgDBtype;
    $file = dirname(__FILE__).'/../storage/intraacl-tables.sql';
    if ($updater && $updater->getDB()->getType() == 'mysql')
    {
        $updater->addExtensionUpdate(array('addTable', 'halo_acl_rights', $file, true));
    }
    elseif ($wgDBtype == 'mysql')
    {
        $wgExtNewTables[] = array('addTable', 'halo_acl_rights', $file);
    }
    else
    {
        die("IntraACL only supports MySQL at the moment");
    }
    // Defer creating 'Permission Denied' page until all schema updates are finished
    global $egDeferCreatePermissionDenied;
    $egDeferCreatePermissionDenied = new DeferCreatePermissionDenied();
    // Reparse right definitions if needed
    global $egDeferReparseSpecialPageRights;
    $egDeferReparseSpecialPageRights = new DeferReparseSpecialPageRights();
    return true;
}

// Creates 'Permission Denied' page during destruction
class DeferCreatePermissionDenied
{
    function __destruct()
    {
        global $haclgContLang;
        $pd = $haclgContLang->getPermissionDeniedPage();
        $t = Title::newFromText($pd);
        if (!$t->getArticleId())
        {
            // Create page "Permission denied".
            echo "Creating IntraACL 'Permission denied' page...";
            $a = new Article($t);
            $a->doEdit($haclgContLang->getPermissionDeniedPageContent(), "", EDIT_NEW);
            echo "done.\n";
        }
    }
}

// Reparse right definitions if needed
class DeferReparseSpecialPageRights
{
    function __destruct()
    {
        global $wgContLang;
        $dbw = wfGetDB(DB_MASTER);
        $badSpecial = 'name LIKE '.$dbw->addQuotes($wgContLang->getNsText(NS_SPECIAL).':%').' OR name LIKE \'Special:%\'';
        if ($dbw->tableExists('halo_acl_special_pages') &&
            $dbw->selectField('halo_acl_special_pages', '1', array($badSpecial), __METHOD__, array('LIMIT' => 1)))
        {
            print "Refreshing special page right definitions...\n";
            $dbw->delete('halo_acl_special_pages', array($badSpecial), __METHOD__);
            $sds = 'page_title LIKE '.$dbw->addQuotes('Page/'.$wgContLang->getNsText(NS_SPECIAL).':%').' OR page_title LIKE \'Page/Special:%\'';
            $res = $dbw->select('page', '*', array('page_namespace' => HACL_NS_ACL, $sds), __METHOD__);
            foreach ($res as $row)
            {
                $title = Title::newFromRow($row);
                $article = new Article($title);
                $article->doEdit($article->getText(), 'Re-parse right definition', EDIT_UPDATE);
            }
        }
    }
}
