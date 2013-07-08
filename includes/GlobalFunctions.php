<?php

/**
 * Copyright 2013+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
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

/**
 * This file contains global functions that are called from the Halo-Access-Control-List
 * extension.
 */
if (!defined('MEDIAWIKI'))
{
    die("This file is part of the IntraACL extension. It is not a valid entry point.");
}

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

    $wgSpecialPages['IntraACLSelftest'] = array('IntraACLSelftestSpecial');
    $wgSpecialPageGroups['IntraACLSelftest'] = 'hacl_group';

    // Register resource modules
    global $wgResourceModules;
    $mod = array(
        'localBasePath' => dirname(__DIR__) . '/scripts',
        'remoteExtPath' => 'IntraACL/scripts',
        'group' => 'ext.intraacl',
        'dependencies' => array(
            'mediawiki.legacy.wikibits',
            'mediawiki.legacy.ajax',
        ),
    );
    $wgResourceModules['ext.intraacl.acleditor'] = $mod + array(
        'scripts' => array(
            'exAttach.js',
            'offsetRect.js',
            'SHint.js',
            'HACL_ACLEditor.js',
            'HACL_Toolbar.js',
        ),
        'messages' => array(
            'hacl_edit_save',
            'hacl_edit_create',
            'hacl_regexp_user',
            'hacl_regexp_group',
            'hacl_start_typing_user',
            'hacl_start_typing_group',
            'hacl_start_typing_page',
            'hacl_start_typing_category',
            'hacl_edit_users_affected',
            'hacl_edit_groups_affected',
            'hacl_edit_no_users_affected',
            'hacl_edit_no_groups_affected',
            'hacl_indirect_grant',
            'hacl_indirect_grant_all',
            'hacl_indirect_grant_reg',
            'hacl_edit_sd_exists',
            'hacl_edit_define_rights',
            'hacl_edit_define_manager',
            'hacl_edit_define_tmanager',
            'hacl_edit_define_manager_np',
            'hacl_edit_ahint_all',
            'hacl_edit_ahint_manage',
            'hacl_edit_ahint_template',
            'hacl_edit_ahint_read',
            'hacl_edit_ahint_edit',
            'hacl_edit_ahint_create',
            'hacl_edit_ahint_delete',
            'hacl_edit_ahint_move',
            'hacl_edit_goto_group',
            'hacl_edit_lose',
        ),
    );
    $wgResourceModules['ext.intraacl.groupeditor'] = $mod + array(
        'scripts' => array(
            'exAttach.js',
            'offsetRect.js',
            'SHint.js',
            'HACL_GroupEditor.js',
        ),
        'messages' => array(
            'hacl_grp_save',
            'hacl_grp_create',
            'hacl_no_member_user',
            'hacl_no_member_group',
            'hacl_no_manager_user',
            'hacl_no_manager_group',
            'hacl_current_member_user',
            'hacl_current_member_group',
            'hacl_current_manager_user',
            'hacl_current_manager_group',
            'hacl_regexp_user',
            'hacl_regexp_group',
            'hacl_start_typing_user',
            'hacl_start_typing_group',
            'hacl_indirect_through',
            'hacl_edit_all',
            'hacl_edit_reg',
        ),
    );

    // Set up autoloading; essentially all classes should be autoloaded!
    global $wgAutoloadClasses;
    $wgAutoloadClasses += array(
        // Internals
        'IACLDefinition'            => "$haclgIP/includes/Definition.php",
        'IACLParserFunctions'       => "$haclgIP/includes/ParserFunctions.php",
        'IACLParserFunctionHooks'   => "$haclgIP/includes/ParserFunctions.php",
        'IACLEvaluator'             => "$haclgIP/includes/Evaluator.php",
        'HACLGroup'                 => "$haclgIP/includes/HACL_Group.php",
        'HACLSecurityDescriptor'    => "$haclgIP/includes/HACL_SecurityDescriptor.php",
        'HACLRight'                 => "$haclgIP/includes/HACL_Right.php",
        'HACLQuickacl'              => "$haclgIP/includes/HACL_Quickacl.php",
        'HACLToolbar'               => "$haclgIP/includes/HACL_Toolbar.php",

        // Special page
        'IntraACLSpecial'           => "$haclgIP/includes/ACLSpecial.php",
        'IntraACLSelftestSpecial'   => "$haclgIP/includes/SpecialSelftest.php",

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
    $wgHooks['ArticleViewHeader'][]     = 'IACLParserFunctions::ArticleViewHeader';
    $wgHooks['InitializeArticleMaybeRedirect'][] = 'IACLParserFunctions::initializeArticleMaybeRedirect';
    $wgHooks['ArticleViewFooter'][]     = 'IACLParserFunctions::ArticleViewFooter';
    $wgHooks['ArticleEditUpdates'][]    = 'IACLParserFunctions::ArticleEditUpdates';
    $wgHooks['ArticleDelete'][]         = 'IACLParserFunctions::articleDelete';
    $wgHooks['ArticleUndelete'][]       = 'IACLParserFunctions::articleUndelete';
    $wgHooks['TitleMoveComplete'][]     = 'IACLParserFunctions::TitleMoveComplete';
    $wgHooks['LanguageGetMagic'][]      = 'haclfLanguageGetMagic';
    $wgHooks['LoadExtensionSchemaUpdates'][] = 'iaclfLoadExtensionSchemaUpdates';

    return true;
}

function iaclfCanonicalNsText($index)
{
    static $ns;
    if (!$ns)
    {
        global $wgCanonicalNamespaceNames;
        $ns = $wgCanonicalNamespaceNames;
        $ns[0] = 'Main';
    }
    return @$ns[$index];
}

function haclfLanguageGetMagic(&$magicWords, $langCode)
{
    global $haclgContLang;
    $magicWords['haclaccess']           = array(0, 'access');
    $magicWords['haclpredefinedright']  = array(0, 'predefined right');
    $magicWords['haclmanagerights']     = array(0, 'manage rights');
    $magicWords['haclmember']           = array(0, 'member');
    $magicWords['haclmanagegroup']      = array(0, 'manage group');
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
        $wgHooks['userCan'][] = 'IACLEvaluator::userCan';
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
        {
            $haclgUnprotectableNamespaceIds[$ns] = true;
        }
    }

    $wgHooks['GetPreferences'][] = 'HACLToolbar::GetPreferences';

    //-- includes for Ajax calls --
    global $wgUseAjax, $wgRequest;
    if ($wgUseAjax && $wgRequest->getVal('action') == 'ajax' )
    {
        $funcName = isset( $_POST["rs"] )
                        ? $_POST["rs"]
                        : (isset( $_GET["rs"] ) ? $_GET["rs"] : NULL);
        if (strpos($funcName, 'hacl') === 0)
        {
            require_once("$haclgIP/includes/HACL_Toolbar.php");
            require_once("$haclgIP/includes/AjaxConnector.php");
        }
    }

    //--- credits (see "Special:Version") ---
    $wgExtensionCredits['other'][] = array(
        'name'        => 'IntraACL',
        'version'     => '2011-12-30',
        'author'      => "Vitaliy Filippov, Stas Fomin, Thomas Schweitzer",
        'url'         => 'http://wiki.4intra.net/IntraACL',
        'description' => 'The best MediaWiki rights extension, based on HaloACL.');

    // IACLParserFunctions callbacks
    $wgParser->setFunctionHook('haclaccess',            'IACLParserFunctionHooks::access');
    $wgParser->setFunctionHook('haclpredefinedright',   'IACLParserFunctionHooks::predefinedRight');
    $wgParser->setFunctionHook('haclmanagerights',      'IACLParserFunctionHooks::manageRights');
    $wgParser->setFunctionHook('haclmember',            'IACLParserFunctionHooks::addMember');
    $wgParser->setFunctionHook('haclmanagegroup',       'IACLParserFunctionHooks::manageRights');

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
    {
        $haclgHaloScriptPath = $wgScriptPath.'/'.$haclgHaloScriptPath;
    }
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
    {
        $haclgNamespaceIndex = 300;
    }

    define('HACL_NS_ACL',       $haclgNamespaceIndex);
    define('HACL_NS_ACL_TALK',  $haclgNamespaceIndex+1);

    haclfInitContentLanguage($wgLanguageCode);

    // Register namespace identifiers
    if (!is_array($wgExtraNamespaces))
    {
        $wgExtraNamespaces = array();
    }
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
    {
        return;
    }
    wfProfileIn(__FUNCTION__);

    $haclContLangFile = 'HACL_Language' . str_replace('-', '_', ucfirst($langcode));
    $haclContLangClass = 'HACLLanguage' . str_replace('-', '_', ucfirst($langcode));
    require_once "$haclgIP/languages/HACL_Language.php";
    if (file_exists("$haclgIP/languages/$haclContLangFile.php"))
    {
        include_once("$haclgIP/languages/$haclContLangFile.php");
    }

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
    {
        $hash .= '!'.$wgUser->getId();
    }
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
    {
        return 0;
    }
    if ($t->getNamespace() == NS_SPECIAL)
    {
        return -IACLStorage::get('SpecialPage')->idForSpecial($t->getBaseText());
    }
    $id = $t->getArticleID();
    if ($id === 0)
    {
        $id = $t->getArticleID(Title::GAID_FOR_UPDATE);
    }
    return $id;
}

/**
 * This function is called from the hook 'EditPage::showEditForm:fields'.
 * It adds the ACL toolbar to edited pages.
 */
function haclfAddToolbarForEditPage($editpage, $out)
{
    if ($editpage->mTitle->mNamespace == HACL_NS_ACL)
    {
        return true;
    }
    $out->addHTML(HACLToolbar::get($editpage->mTitle, !empty($editpage->eNonReadable)));
    return true;
}

// Hook into maintenance/update.php
function iaclfLoadExtensionSchemaUpdates($updater = NULL)
{
    global $wgExtNewTables, $wgDBtype;
    $file = dirname(__FILE__).'/../storage/intraacl-tables.sql';
    if ($updater && $updater->getDB()->getType() == 'mysql')
    {
        $updater->addExtensionUpdate(array('addTable', 'intraacl_rules', $file, true));
    }
    elseif ($wgDBtype == 'mysql')
    {
        $wgExtNewTables[] = array('addTable', 'intraacl_rules', $file);
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

class IACL
{
    /**
     * Definition/child types
     */
    const PE_NAMESPACE  = 1;    // Namespace security descriptor, identified by namespace index
    const PE_CATEGORY   = 2;    // Category security descriptor, identified by category page ID
    const PE_RIGHT      = 3;    // Right template, identified by ACL definition (ACL:XXX) page ID
    const PE_PAGE       = 4;    // Page security descriptor, identified by page ID
    const PE_SPECIAL    = 5;    // Special page, identified by a surrogate ID from special page table
    const PE_ALL_USERS  = 6;    // All users including anonymous
    const PE_REG_USERS  = 7;    // Registered users
    const PE_GROUP      = 8;    // Group, identified by group (ACL:Group/XXX) page ID
    const PE_USER       = 9;    // User, identified by user ID. Used only as child, not as definition (obviously)

    /**
     * Action/child relation details, stored as bitmap in rules table
     * SDs can contain everything except ACTION_GROUP_MEMBER
     * Groups can contain ACTION_GROUP_MEMBER and ACTION_MANAGE rules
     */
    const ACTION_READ           = 0x01;     // Allows to read pages
    const ACTION_EDIT           = 0x02;     // Allows to edit pages. Implies read right
    const ACTION_CREATE         = 0x04;     // Allows to create articles in the namespace
    const ACTION_MOVE           = 0x08;     // Allows to move pages with history
    const ACTION_DELETE         = 0x10;     // Allows to delete pages with history
    const ACTION_FULL_ACCESS    = 0x1F;
    const ACTION_MANAGE         = 0x20;     // Allows to modify right definition or group. Implies read/edit/create/move/delete rights
    const ACTION_PROTECT_PAGES  = 0x80;     // Allows to modify affected page right definitions. Implies read/edit/create/move/delete pages
    const ACTION_INCLUDE_SD     = 0x01;     // Used for child SDs (1 has no effect, any other value can be also used)
    const ACTION_GROUP_MEMBER   = 0x01;     // Used in group definitions: specifies that the child is a group member

    /**
     * Bit offset of indirect rights in 'actions' column
     * I.e., 8 means higher byte is for indirect rights
     */
    const INDIRECT_OFFSET       = 8;

    static $nameToType = array(
        'right'     => IACL::PE_RIGHT,
        'namespace' => IACL::PE_NAMESPACE,
        'category'  => IACL::PE_CATEGORY,
        'page'      => IACL::PE_PAGE,
        'special'   => IACL::PE_SPECIAL,
    );
    static $typeToName = array(
        IACL::PE_RIGHT     => 'right',
        IACL::PE_NAMESPACE => 'namespace',
        IACL::PE_CATEGORY  => 'category',
        IACL::PE_PAGE      => 'page',
        IACL::PE_SPECIAL   => 'special',
    );
    static $nameToAction = array(
        'read'   => IACL::ACTION_READ,
        'edit'   => IACL::ACTION_EDIT,
        'create' => IACL::ACTION_CREATE,
        'delete' => IACL::ACTION_DELETE,
        'move'   => IACL::ACTION_MOVE,
    );
    static $actionToName = array(
        IACL::ACTION_READ => 'read',
        IACL::ACTION_EDIT => 'edit',
        IACL::ACTION_CREATE => 'create',
        IACL::ACTION_DELETE => 'delete',
        IACL::ACTION_MOVE => 'move',
        // Backwards compatibility with ACL editor
        IACL::ACTION_MANAGE => 'template',
        IACL::ACTION_PROTECT_PAGES => 'manage',
    );

    /**
     * Returns the ID of an action for the given name of an action
     * (only for Mediawiki
     */
    static function getActionID($name)
    {
        return @self::$nameToAction[$name] ?: 0;
    }
}
