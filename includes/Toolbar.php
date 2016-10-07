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

if (!defined('MEDIAWIKI'))
{
    die("This file is part of the IntraACL extension. It is not a valid entry point.");
}

/**
 * IntraACL toolbar for article edit mode.
 * On each article edit, there is a small toolbar at the top of the page with
 * a selectbox allowing to select desired page protection from Quick ACL list.
 */
class IACLToolbar
{
    /**
     * This method returns HTML code for the IntraACL toolbar,
     * for the $title editing mode.
     *
     * Looks like the following:
     * Page protection: <selectbox>. [Additional ACL ↓] [Used content ↓] [Edit ACL] ... [Manage Quick ACL]
     *
     * Options for selectbox:
     * - [no custom rights] - use only category/namespace rights
     * - [ACL:Page/XXX] - use custom ACL
     * - [Right 1] - use ACL template 1
     * - [Right 2] - use ACL template 2
     * - ...
     * ACL templates are detected using HACLSecurityDescriptor::isSinglePredefinedRightInclusion()
     * So if ACL:Page/XXX is really the inclusion of a single right template, it will be detected.
     */
    static function get($title, $nonreadable)
    {
        global $wgUser, $wgRequest, $haclgContLang, $wgContLang,
            $haclgHaloScriptPath, $wgScriptPath, $wgOut,
            $haclgOpenWikiAccess;

        self::addToolbarLinks($wgOut);

        $ns = $wgContLang->getNsText(HACL_NS_ACL);
        $options = array(array(
            'value' => 'unprotected',
            'name' => wfMessage('hacl_toolbar_unprotected')->text(),
            'title' => wfMessage('hacl_toolbar_unprotected')->text(),
        ));

        if (!is_object($title))
        {
            $title = Title::newFromText($title);
        }

        if ($title->getNamespace() == HACL_NS_ACL)
        {
            return '';
        }

        // $found = "is current page SD in the list?"
        $found = false;

        // The list of ACLs which affect $title except ACL:Page/$title itself
        // I.e. category and namespace ACLs
        $globalACL = array();

        $pageSDId = NULL;
        $pet = $title->getNamespace() == NS_CATEGORY ? IACL::PE_CATEGORY : IACL::PE_PAGE;
        $pageSDTitle = Title::newFromText(IACLDefinition::nameOfSD($pet, $title));
        if (!$pageSDTitle)
        {
            // too long title... :(
            return '';
        }
        // Check SD modification rights
        $canModify = $pageSDTitle->userCan('edit');
        if ($title->exists())
        {
            $pageSD = IACLDefinition::getSDForPE($pet, $title->getArticleId());
            if ($pageSD)
            {
                $realPageSDId = $pageSDId = array($pageSD['pe_type'], $pageSD['pe_id']);
                // Check if page SD is a single predefined right inclusion
                if ($pageSD['single_child'])
                {
                    $pageSDId = $pageSD['single_child'];
                    // But don't change $realPageSDId
                }
                else
                {
                    $found = true;
                    $options[] = array(
                        'current' => true,
                        'value' => implode('-', $pageSDId),
                        'name' => $pageSDTitle->getFullText(),
                        'title' => $pageSDTitle->getFullText(),
                    );
                }
            }
            else
            {
                // Get protected categories this article belongs to (for hint)
                $categories = IACLStorage::get('Util')->getParentCategoryIDs($title->getArticleId());
                foreach ($categories as &$cat)
                {
                    $cat = array(IACL::PE_CATEGORY, $cat);
                }
                unset($cat); // prevent reference bugs
                $defs = IACLDefinition::select(array('pe' => $categories));
                foreach ($defs as $def)
                {
                    $globalACL[] = $def['def_title'];
                }
            }
        }

        // Add Quick ACLs
        $quickacl = IACLQuickacl::newForUserId($wgUser->getId());
        $default = $quickacl->default_pe_id;
        $hasQuickACL = false;
        foreach ($quickacl->getPEIds() as $def)
        {
            $hasQuickACL = true;
            $sdTitle = IACLDefinition::getSDTitle($def);
            if (!$sdTitle)
            {
                continue;
            }
            $option = array(
                'value' => implode('-', $def),
                'current' => ($def == $pageSDId),
                'name' => $sdTitle->getPrefixedText(),
                'title' => $sdTitle->getPrefixedText(),
            );
            $found = $found || $option['current'];
            if ($default == $def)
            {
                if (!$title->exists())
                {
                    // Select default option for new articles
                    $option['current'] = true;
                }
                // Always insert default SD as the second option
                array_splice($options, 1, 0, array($option));
            }
            else
            {
                $options[] = $option;
            }
        }

        // If page SD is not yet in the list, insert it as the second option
        if ($pageSDId && !$found)
        {
            $sdTitle = IACLDefinition::getSDTitle($pageSDId);
            array_splice($options, 1, 0, array(array(
                'name'    => $sdTitle->getPrefixedText(),
                'value'   => implode('-', $pageSDId),
                'current' => true,
                'title'   => $sdTitle->getPrefixedText(),
            )));
        }

        // Alter selection using request data (hacl_protected_with)
        if ($canModify && ($st = $wgRequest->getVal('hacl_protected_with')))
        {
            foreach ($options as &$o)
            {
                $o['current'] = $o['value'] == $st;
            }
            unset($o); // prevent reference bugs
        }

        $selectedIndex = false;
        foreach ($options as $i => $o)
        {
            if (!empty($o['current']))
            {
                $selectedIndex = $i;
            }
        }

        // Check if page namespace has an ACL (for hint)
        if (!$pageSDId && !$globalACL)
        {
            $sdTitle = IACLDefinition::getSDTitle(array(IACL::PE_NAMESPACE, $title->getNamespace()));
            if ($sdTitle->exists())
            {
                $globalACL[] = $sdTitle;
            }
        }

        if ($globalACL)
        {
            foreach ($globalACL as &$t)
            {
                if ($haclgOpenWikiAccess || $t->userCan('read'))
                {
                    $t = Xml::element('a', array('href' => $t->getLocalUrl(), 'target' => '_blank'), $t->getText());
                }
            }
            unset($t); // prevent reference bugs
            $globalACL = implode(', ', $globalACL);
        }

        // Check if the article does include any content
        $anyLinks = $embeddedToolbar = false;
        if ($title->exists())
        {
            if (!$pageSDId)
            {
                $pageSDId = '';
            }
            $c = false;
            foreach ($wgRequest->getValues() as $k => $v)
            {
                if (substr($k, 0, 7) == 'sd_emb_' && $v !== "")
                {
                    $c = true;
                    break;
                }
            }
            if ($c)
            {
                // If there were any changes in the embedded content
                // toolbar, display it initially
                $embeddedToolbar = self::getEmbeddedHtml($title->getArticleId(), $realPageSDId[0], $realPageSDId[1]);
            }
            else
            {
                // Else only check for imagelinks/templatelinks existence
                $dbr = wfGetDB(DB_SLAVE);
                $res = $dbr->query(
                    $dbr->selectSQLText('imagelinks', '1',
                        array('il_from' => $title->getArticleId()),
                        __METHOD__, array('GROUP BY' => 'il_from')) . ' UNION ' .
                    $dbr->selectSQLText('templatelinks', '1',
                        array('tl_from' => $title->getArticleId()),
                        __METHOD__, array('GROUP BY' => 'tl_from')),
                    __METHOD__
                );
                $res = $res->fetchObject();
                if ($res)
                {
                    $anyLinks = true;
                }
            }
        }

        // Link to Quick ACL manage page
        $quick_acl_link = Title::newFromText('Special:IntraACL')->getLocalUrl(array('action' => 'quickaccess'));

        // Run template
        ob_start();
        require(dirname(__FILE__).'/../templates/HACL_Toolbar.tpl.php');
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    /**
     * Add toolbar head-items to $out
     */
    public static function addToolbarLinks($out)
    {
        $out->addModuleStyles('ext.intraacl.toolbar');
        $out->addModules('ext.intraacl.common');
    }

    /**
     * The only case when the user can create an article non-readable to himself
     * is when he has create, but no read access to the namespace.
     * The only case when he can correct it by changing saved text
     * is when he has read access to some category.
     * Warn him about it.
     */
    public static function warnNonReadableCreate($editpage)
    {
        global $haclgOpenWikiAccess, $wgUser, $wgOut, $haclgSuperGroups;
        $g = $wgUser->getGroups();
        if (!isset($editpage->eNonReadable) &&
            !$editpage->mTitle->getArticleId() &&
            (!$g || !array_intersect($g, $haclgSuperGroups)))
        {
            $r = IACLDefinition::userCan($wgUser->getId(), IACL::PE_NAMESPACE, $editpage->mTitle->getNamespace(), IACL::ACTION_READ);
            if ($r == 0 || $r == -1 && !$haclgOpenWikiAccess)
            {
                $editpage->eNonReadable = true;
            }
        }
        if (!empty($editpage->eNonReadable))
        {
            $wgOut->addHTML(self::getReadableCategoriesSelectBox());
        }
        return true;
    }

    /**
     * Get categories which are granted readable for current user
     */
    public static function getReadableCategories()
    {
        global $wgUser;
        $uid = $wgUser->getId();
        // Lookup readable categories
        $pe = IACLStorage::get('SD')->getRules(array(
            'pe_type' => IACL::PE_CATEGORY,
            'child_type' => IACL::PE_USER,
            'child_id' => $uid,
            '(actions & '.(IACL::ACTION_READ | (IACL::ACTION_READ << IACL::INDIRECT_OFFSET)).') != 0',
        ));
        if ($pe)
        {
            foreach ($pe as &$e)
            {
                $e = $e['pe_id'];
            }
            unset($e);
            $dbr = wfGetDB(DB_SLAVE);
            $res = $dbr->select('page', '*', array('page_id' => $pe), __METHOD__);
            $titles = array();
            foreach ($res as $row)
            {
                $titles[] = Title::newFromRow($row);
            }
            // Add child categories
            $pe = IACLStorage::get('Util')->getAllChildrenCategories($titles);
        }
        return $pe;
    }

    /**
     * Get the selectbox which adds a category to wikitext when changed
     */
    public static function getReadableCategoriesSelectBox($for_upload = false)
    {
        $pe = self::getReadableCategories();
        if (!$pe)
        {
            return wfMessage($for_upload ? 'hacl_nonreadable_upload_nocat' : 'hacl_nonreadable_create_nocat')->plain();
        }
        if ($for_upload)
        {
            global $wgOut;
            self::addToolbarLinks($wgOut);
        }
        $for_upload = $for_upload ? ', 1' : '';
        $select = array();
        foreach ($pe as $cat)
        {
            $select[] = '<a href="javascript:haclt_addcat(\''.
                htmlspecialchars(addslashes($cat->getPrefixedText())).
                '\''.$for_upload.')">'.
                htmlspecialchars($cat->getText()).'</a>';
        }
        return wfMessage($for_upload ? 'hacl_nonreadable_upload' : 'hacl_nonreadable_create', implode(', ', $select))->plain();
    }

    /**
     * Similar to warnNonReadableCreate, but warns about non-readable file uploads
     */
    public static function warnNonReadableUpload($upload)
    {
        global $haclgOpenWikiAccess, $wgUser, $wgOut, $haclgSuperGroups;
        $g = $wgUser->getGroups();
        if (!$g || !array_intersect($g, $haclgSuperGroups))
        {
            $dest = NULL;
            if ($upload->mDesiredDestName)
            {
                $dest = Title::makeTitleSafe(NS_FILE, $upload->mDesiredDestName);
            }
            if (!$dest || !$dest->exists())
            {
                // New file
                $r = IACLDefinition::userCan($wgUser->getId(), IACL::PE_NAMESPACE, NS_FILE, IACL::ACTION_CREATE);
                if ($r == 0 || $r == -1 && !$haclgOpenWikiAccess)
                {
                    $wgOut->showErrorPage('hacl_upload_forbidden', 'hacl_upload_forbidden_text');
                    return false;
                }
                else
                {
                    $r = IACLDefinition::userCan($wgUser->getId(), IACL::PE_NAMESPACE, NS_FILE, IACL::ACTION_READ);
                    if ($r == 0 || $r == -1 && !$haclgOpenWikiAccess)
                    {
                        $upload->uploadFormTextTop .= self::getReadableCategoriesSelectBox(true);
                    }
                }
            }
            elseif (($permission_errors = $dest->getUserPermissionsErrors('edit', $wgUser)))
            {
                // New version of existing file
                $wgOut->showPermissionsErrorPage($permission_errors);
                return false;
            }
        }
        return true;
    }

    /**
     * Check if any of categories mentioned in $text gives $wgUser read access to $title
     */
    protected static function checkForReadableCategories($text, $title)
    {
        global $wgUser, $wgParser;
        $options = ParserOptions::newFromUser($wgUser);
        // clearState = true when not cleared yet
        $text = $wgParser->preSaveTransform($text, $title, $wgUser, $options, !$wgParser->mStripState);
        $parserOutput = $wgParser->parse($text, $title, $options);
        $catIds = array();
        foreach ($parserOutput->getCategoryLinks() as $cat)
        {
            // FIXME Resolve multiple title IDs at once
            $cat = Title::makeTitle(NS_CATEGORY, $cat);
            if (($id = $cat->getArticleId()))
            {
                $catIds[$id] = true;
            }
        }
        $catIds = array_keys($catIds + array_flip(IACLStorage::get('Util')->getParentCategoryIDs(array_keys($catIds))));
        $r = IACLDefinition::userCan(
            $wgUser->getId(), IACL::PE_CATEGORY, $catIds, IACL::ACTION_READ
        );
        return $r == 1;
    }

    /**
     * Related to warnNonReadableCreate, checks if the user is creating
     * a non-readable page without checking the "force" checkbox
     */
    public static function attemptNonReadableCreate($editpage)
    {
        global $wgUser, $wgRequest, $wgOut, $haclgSuperGroups, $haclgOpenWikiAccess;
        $g = $wgUser->getGroups();
        if (!$editpage->mTitle->getArticleId() && (!$g || !array_intersect($g, $haclgSuperGroups)))
        {
            $r = IACLDefinition::userCan(
                $wgUser->getId(), IACL::PE_NAMESPACE, $editpage->mTitle->getNamespace(), IACL::ACTION_READ
            );
            if ($r == 0 || $r == -1 && !$haclgOpenWikiAccess)
            {
                $editpage->eNonReadable = true;
                $cats = self::checkForReadableCategories($editpage->textbox1, $editpage->mTitle);
                if (!$cats && !$wgRequest->getBool('hacl_nonreadable_create'))
                {
                    $editpage->showEditForm();
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Similar to attemptNonReadableCreate, but for uploads
     */
    public static function attemptNonReadableUpload($special, &$warnings)
    {
        global $haclgOpenWikiAccess, $wgUser, $wgOut, $haclgSuperGroups;
        $g = $wgUser->getGroups();
        if (!$g || !array_intersect($g, $haclgSuperGroups))
        {
            $file = $special->mUpload->getLocalFile();
            if (!$file->exists())
            {
                // Only check for new files
                $r = IACLDefinition::userCan($wgUser->getId(), IACL::PE_NAMESPACE, NS_FILE, IACL::ACTION_READ);
                if ($r == 0 || $r == -1 && !$haclgOpenWikiAccess)
                {
                    $cats = self::checkForReadableCategories($special->mComment, $file->getTitle());
                    if (!$cats)
                    {
                        $special->uploadFormTextAfterSummary .= self::getReadableCategoriesSelectBox(true);
                        $warnings['hacl_nonreadable_upload_warning'] = array();
                    }
                }
            }
        }
        return true;
    }

    /**
     * This method is called after an article has been saved.
     * This is the server side of IntraACL protection toolbar,
     * allowing to modify page SD together with article save.
     *
     * No modifications are made if either:
     * - Page namespace is ACL
     * - User is anonymous
     * - Users don't have the right to modify page SD
     * - 'haloacl_protect_with' request value is invalid
     *   (valid are 'unprotected', or ID/name of predefined right or THIS page SD)
     *
     * @param WikiPage $article The article which was saved
     * @param User $user        The user who saved the article
     * @param string $text      The content of the article
     *
     * @return true
     */
    public static function articleSaveComplete_SaveSD($article, User $user, $text)
    {
        global $wgUser, $wgRequest, $haclgContLang;

        if ($user->isAnon())
        {
            // Don't handle protection toolbar for anonymous users
            return true;
        }

        if ($article->getTitle()->getNamespace() == HACL_NS_ACL)
        {
            // Don't use protection toolbar for articles in the namespace ACL.
            // Note that embedded content protection toolbar is handled nevertheless.
            return true;
        }

        // Obtain user selection
        // hacl_protected_with == '<peType>:<peID>' or 'unprotected'
        $selectedSD = $wgRequest->getVal('hacl_protected_with');
        if ($selectedSD && $selectedSD != 'unprotected')
        {
            // Some SD is selected by the user
            // Ignore selection of invalid SDs
            $selectedSD = array_map('intval', explode('-', $selectedSD, 2));
            if (count($selectedSD) != 2)
            {
                $selectedSD = NULL;
            }
        }

        if (!$selectedSD)
        {
            return true;
        }

        if ($selectedSD == 'unprotected')
        {
            $selectedSD = NULL;
        }

        // Check if current SD must be modified
        if ($article->exists())
        {
            $pageSD = IACLDefinition::getSDForPE(IACL::PE_PAGE, $article->getId());
            if ($pageSD && $selectedSD)
            {
                // Check if page's SD ID passed as selected
                if ($pageSD['pe_type'] == $selectedSD[0] &&
                    $pageSD['pe_id'] == $selectedSD[1])
                {
                    return true;
                }
                // Check if page's SD is single inclusion and it is passed as selected
                if ($pageSD['single_child'] == $selectedSD)
                {
                    return true;
                }
            }
        }

        // Check if no protection selected and no protection exists
        if (!$selectedSD && !$pageSD)
        {
            return true;
        }

        // Check if other SD is a predefined right
        // FIXME Allow selecting non-PE_RIGHTs in quick acl toolbar?
        if ($selectedSD && $selectedSD[0] != IACL::PE_RIGHT)
        {
            return true;
        }

        // Check SD modification rights
        $pageSDName = IACLDefinition::nameOfSD(IACL::PE_PAGE, $article->getTitle());
        $etc = haclfDisableTitlePatch();
        $pageSDTitle = Title::newFromText($pageSDName);
        haclfRestoreTitlePatch($etc);
        if (!$pageSDTitle->userCan('edit'))
        {
            return true;
        }

        $newSDArticle = new WikiPage($pageSDTitle);
        if ($selectedSD)
        {
            // Create/modify page SD
            $selectedSDTitle = IACLDefinition::getSDTitle($selectedSD);
            $content = '{{#predefined right: '.$selectedSDTitle->getText()."}}\n".
                '{{#manage rights: assigned to = User:'.$wgUser->getName()."}}\n";
            $newSDArticle->doEdit($content, wfMessage('hacl_comment_protect_with', $selectedSDTitle->getFullText())->text());
        }
        else
        {
            // Remove page SD
            $newSDArticle->doDeleteArticle(wfMessage('hacl_comment_unprotect'))->text();
        }

        // Continue hook processing
        return true;
    }

    /**
     * This method handles embedded content protection.
     * Must be set onto ArticleSaveComplete hook AFTER articleSaveComplete_SaveSD
     * in order to handle newly created page SDs.
     */
    public static function articleSaveComplete_SaveEmbedded(&$article, &$user, $text)
    {
        // Flag to prevent recursion
        static $InsideSaveEmbedded;
        if ($InsideSaveEmbedded)
        {
            return true;
        }
        $InsideSaveEmbedded = true;

        global $wgRequest, $wgOut, $haclgContLang, $wgUser;

        $isACL = $article->getTitle()->getNamespace() == HACL_NS_ACL;
        if ($isACL)
        {
            $articleSD = IACLDefinition::newFromTitle($article->getTitle(), false);
            if (!$articleSD || $articleSD['pe_type'] != IACL::PE_PAGE)
            {
                // This is not a page SD, do nothing.
                return true;
            }
        }
        else
        {
            // FIXME possibly use the category SD for category pages
            //       the problem here is that in ACL editor two different SDs
            //       may be created and queried for embedded content:
            //       for category article and for category itself
            $articleSD = IACLDefinition::getSDForPE(IACL::PE_PAGE, $article->getId());
            if (!$articleSD)
            {
                return true;
            }
        }

        // Handle embedded content protection
        $errors = array();
        foreach ($wgRequest->getValues() as $k => $v)
        {
            if (substr($k, 0, 7) == 'sd_emb_' && $v)
            {
                $wgRequest->setVal($k, false); // clear value to handle embedded content only one time
                $emb_pe_id = intval(substr($k, 7));
                $emb_title = Title::newFromId($emb_pe_id);
                list($req_sd_type, $req_sd_id, $emb_sd_revid) = explode('-', $v, 3);
                if ($emb_title)
                {
                    $emb_sd_title = Title::newFromText(IACLDefinition::nameOfSD(IACL::PE_PAGE, $emb_title));
                    $emb_sd_article = new WikiPage($emb_sd_title);
                }
                // Check for errors:
                if (!$emb_title || !$emb_title->getArticleId() || !$emb_sd_title->userCan('edit'))
                {
                    // Embedded content deleted || Manage access denied
                    $errors[] = array($emb_title, 'canedit');
                }
                elseif ($req_sd_type && $req_sd_id && "$req_sd_type-$req_sd_id" != $articleSD['key'])
                {
                    // Invalid SD requested for protection
                    $errors[] = array($emb_title, 'invalidsd');
                }
                elseif (!$emb_sd_revid && $emb_sd_title->exists() ||
                    $emb_sd_revid && $emb_sd_title->getLatestRevId() != $emb_sd_revid)
                {
                    // Mid-air collision: SD created/changed by someone in the meantime
                    $errors[] = array($emb_title, 'midair');
                }
                else
                {
                    // Save embedded element SD
                    $emb_sd_article->doEdit(
                        '{{#predefined right: '.$articleSD['def_title'].'}}',
                        wfMessage('hacl_comment_protect_embedded', ''.$articleSD['def_title'])->text(),
                        EDIT_FORCE_BOT
                    );
                }
            }
        }

        // Display errors to the user, if any
        // This is safe to do as we are definitely in interactive non-batch edit mode
        if ($errors)
        {
            foreach ($errors as &$e)
            {
                $e = "[[:".$e[0]->getPrefixedText()."]] (".wfMessage('hacl_embedded_error_'.$e[1])->text().")";
            }
            $wgOut->setTitle(Title::newFromText('Special:IntraACL'));
            $wgOut->addWikiText(wfMessage(
                'hacl_embedded_not_saved',
                implode(", ", $errors),
                $article->getTitle()->getPrefixedText()
            )->plain());
            $wgOut->setPageTitle(wfMessage('hacl_embedded_not_saved_title')->text());
            $wgOut->output();
            // FIXME terminate MediaWiki more correctly
            wfGetDB(DB_MASTER)->commit();
            exit;
        }

        // Clear flag and continue hook processing
        $InsideSaveEmbedded = false;
        return true;
    }

    /**
     * Get HTML code for linked content protection toolbar.
     * Used by ACL editor and IntraACL toolbar.
     * Handled by IACLToolbar::articleSaveComplete_SaveEmbedded.
     *
     * FIXME: Linked content protection is not exactly usable, because it does not replicate
     * namespace and category rights applied to the source page. I don't yet know how to deal
     * with it...
     *
     * @param required int $peID - page ID to retrieve linked content from
     * @param optional int $sdType
     * @param optional int $sdID - page SD ID to check if SDs of linked content are already
     *     single inclusions of this SD.
     * @return html code for embedded content protection toolbar
     *     it containts checkboxes with names "sd_emb_$pageID" and values
     *     "$sdType-$sdID-$revid". $sdID here is the passed $sdID and $revid is the ID
     *     of embedded element's SD last revision, if it exists.
     *     Value may be even just "-" when the toolbar was queried for article without SD,
     *     and when the embedded element did not have any SD.
     */
    public static function getEmbeddedHtml($peID, $sdType, $sdID)
    {
        global $haclgContLang, $wgRequest;
        if (!$sdID)
        {
            $sdID = '';
        }
        // Retrieve the list of templates used on the page with id=$peID
        $templatelinks = IACLStorage::get('SD')->getEmbedded($peID, $sdType, $sdID, 'templatelinks');
        // Retrieve the list of images used on the page
        $imagelinks = IACLStorage::get('SD')->getEmbedded($peID, $sdType, $sdID, 'imagelinks');
        // Build HTML code for embedded content toolbar
        $links = array_merge($templatelinks, $imagelinks);
        $html = array();
        $all = array();
        foreach ($links as $link)
        {
            $id = $link['title']->getArticleId();
            $href = $link['title']->getLocalUrl();
            $t = $link['title']->getPrefixedText();
            // Latest revision ID is checked to detect editing conflicts
            $revid = $link['sd_revid'];
            if ($prev = $wgRequest->getVal("sd_emb_$id"))
            {
                list($unused, $revid) = explode($prev, '/', 2);
            }
            if ($link['sd_title'])
            {
                if ($link['sd_single'])
                {
                    // Already protected by page SD
                    $customprot = wfMessage('hacl_toolbar_emb_already_prot')->text();
                }
                else
                {
                    // Custom SD defined
                    $customprot = wfMessage('hacl_toolbar_emb_custom_prot', $link['sd_title']->getLocalUrl())->text();
                }
            }
            else
            {
                $customprot = '';
            }
            if ($link['used_on_pages'] > 1)
            {
                $usedon = Title::newFromText("Special:WhatLinksHere/$t")->getLocalUrl(array('hidelinks' => 1));
                $usedon = wfMessage('hacl_toolbar_used_on', $link['used_on_pages'], $usedon)->text();
            }
            else
            {
                $usedon = '';
            }
            $P = $customprot || $usedon ? " — " : "";
            $S = $customprot && $usedon ? "; " : "";
            // [x] Title — custom SD defined; used on Y pages
            $h = '<input type="checkbox" id="sd_emb_'.$id.'" name="sd_emb_'.$id.'"'.
                ($link['sd_single']
                    ? ' value="" checked="checked" disabled="disabled"'
                    : " value=\"$sdType-$sdID-$revid\" onchange=\"hacle_noall(this)\" onclick=\"hacle_noall(this)\"".
                      ($prev ? ' checked="checked"' : '')).
                ' />'.
                ' <label for="sd_emb_'.$id.'"><a target="_blank" href="'.htmlspecialchars($href).'">'.
                htmlspecialchars($t).'</a></label>'.$P.$customprot.$S.$usedon;
            $h = '<div class="hacl_embed'.($link['sd_single'] ? '_disabled' : '').'">'.$h.'</div>';
            $html[] = $h;
            if (!$link['sd_single'])
            {
                $all[] = $id;
            }
        }
        if ($all)
        {
            $html[] = '<div class="hacl_embed"><input type="checkbox" id="hacl_emb_all" onchange="hacle_checkall(this, ['.
                implode(',',$all).'])" onclick="hacle_checkall(this, ['.implode(',',$all).'])" /> '.
                wfMessage('hacl_toolbar_emb_all')->text().'</div>';
        }
        elseif ($html)
        {
            $html[] = '<div class="hacl_embed_disabled"><input type="checkbox" disabled="disabled" checked="checked" /> '.
                wfMessage('hacl_toolbar_emb_all_already')->text().'</div>';
        }
        if ($html)
        {
            array_unshift($html, '<div class="hacl_emb_text">'.wfMessage('hacl_toolbar_protect_embedded')->text().'</div>');
        }
        $html = implode("\n", $html);
        return $html;
    }

    /**
     * Hook for displaying "ACL" tab for standard skins
     */
    static function SkinTemplateContentActions(&$actions)
    {
        if ($act = self::getContentAction())
        {
            array_splice($actions, 1, 0, array($act));
        }
        return true;
    }

    /**
     * Hook for displaying "ACL" tab for Vector skin
     */
    static function SkinTemplateNavigation(&$skin, &$links)
    {
        if ($act = self::getContentAction())
        {
            array_splice($links['namespaces'], 1, 0, array($act));
        }
        return true;
    }

    /**
     * User setting hook allowing user to select whether to display "ACL" tab
     */
    static function GetPreferences($user, &$prefs)
    {
        global $haclgDisableACLTab;
        if (!empty($haclgDisableACLTab))
        {
            $prefs['showacltab'] =
                array(
                    'type' => 'toggle',
                    'label-message' => 'tog-showacltab',
                    'section' => 'rendering/advancedrendering',
                );
        }
        return true;
    }

    // Returns content-action for inserting into skin tabs
    static function getContentAction()
    {
        global $wgTitle, $haclgContLang, $haclgDisableACLTab, $wgUser;
        if ($wgUser->isAnon())
        {
            return NULL;
        }
        if ($wgTitle->getNamespace() == HACL_NS_ACL)
        {
            // Display the link to article or category
            list($peType, $peName) = IACLDefinition::nameOfPE($wgTitle->getText());
            if ($peType == IACL::PE_PAGE || $peType == IACL::PE_CATEGORY)
            {
                $title = $peType == IACL::PE_PAGE ? Title::newFromText($peName) : Title::makeTitleSafe(NS_CATEGORY, $peName);
                return array(
                    'class' => false,
                    'text'  => wfMessage("hacl_tab_".IACL::$typeToName[$peType])->text(),
                    'href'  => $title->getLocalUrl(),
                );
            }
        }
        elseif ($wgTitle->exists())
        {
            // Display the link to category or page SD
            if ($wgTitle->getNamespace() == NS_CATEGORY)
            {
                $sd = IACLDefinition::nameOfSD(IACL::PE_CATEGORY, $wgTitle);
            }
            else
            {
                $sd = IACLDefinition::nameOfSD(IACL::PE_PAGE, $wgTitle);
            }
            $etc = haclfDisableTitlePatch();
            $sd = Title::newFromText($sd, HACL_NS_ACL);
            haclfRestoreTitlePatch($etc);
            // Hide ACL tab if SD does not exist and $haclgDisableACLTab is true
            if (!$sd || !empty($haclgDisableACLTab) && !$sd->exists() && !$wgUser->getOption('showacltab'))
            {
                return NULL;
            }
            return array(
                'class' => $sd->exists() ? false : 'new',
                'text'  => wfMessage('hacl_tab_acl')->text(),
                'href'  => $sd->getLocalUrl(),
            );
        }
        return NULL;
    }
}
