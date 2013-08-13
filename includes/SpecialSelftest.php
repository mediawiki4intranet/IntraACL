<?php

/**
 * Copyright 2013+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
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

/**
 * A special page for doing permission system self-checks.
 *
 * @author Vitaliy Filippov
 */

if (!defined('MEDIAWIKI'))
{
    die();
}

class IntraACLSelftestSpecial extends SpecialPage
{
    var $access_denied_page;

    function __construct()
    {
        $this->mRestriction = 'user';
        parent::__construct('IntraACLSelftest');
    }

    function execute($par)
    {
        global $wgOut, $wgRequest, $haclgContLang, $wgTitle;
        if (!function_exists('curl_init'))
        {
            $wgOut->showErrorPage('iacl-selftest-no-curl', 'iacl-selftest-no-curl-text');
            return;
        }
        $this->access_denied_page =
            preg_quote(SpecialPage::getTitleFor('Badtitle')->getPrefixedText()) .
            '|' . preg_quote($haclgContLang->getPermissionDeniedPage());
        $q = $wgRequest->getValues();
        if (!empty($q['do']))
        {
            $wgOut->disable();
            $this->doChecks();
        }
        else
        {
            $wgOut->setPageTitle(wfMsg('hacl_selftest_title'));
            $wgOut->addWikiText(wfMsgNoTrans('hacl_selftest_info'));
            $wgOut->addHTML('<iframe style="border-width: 0; width: 100%; height: 500px" src="'.$wgTitle->getLocalUrl(array('do' => 1)).'"></iframe>');
        }
    }

    static function loadConfig()
    {
        $msg = wfMessage('IntraACL right tests');
        $pages = array();
        if (!$msg->isBlank())
        {
            $msg = $msg->plain();
            foreach (explode("\n", $msg) as $p)
            {
                $p = array_map('trim', explode("|", preg_replace('#^\*\s*#s', '', $p)));
                if (count($p) > 1)
                {
                    $pages[] = $p;
                }
            }
        }
        return $pages;
    }

    function doChecks()
    {
        // Fill Firefox buffer so incremental rendering kicks in
        print str_repeat(" ", 1024);
        print "<html><body><ul>";
        $pages = self::loadConfig();
        foreach ($pages as $p)
        {
            try
            {
                $mustbe = @$p[2] && $p[2] !== 'no';
                if (@$p[3] == 'search')
                {
                    $readable = $this->isFound($p[0], $p[1]);
                }
                else
                {
                    $readable = $this->isReadable($p[0], $p[1]);
                }
                $error = $mustbe != $readable;
                $status = '';
            }
            catch (Exception $e)
            {
                $status = $e->getMessage();
                $error = true;
            }
            $status = $status ? ' - <span style="color: '.($error ? 'red' : 'green').'">'.$status.'</span>' : '';
            $error = '<span style="color: '.($error ? 'red' : 'green').'">'.($error ? '[ NOT OK ]' : '[ OK ]').'</span> ';
            $msg = @$p[4] ? $p[4] : implode(' | ', $p);
            print '<li>'.$error.htmlspecialchars($msg).$status."</li>\n";
            flush();
            ob_flush();
        }
        print "</ul></body></html>";
    }

    function authCookie($username)
    {
        global $wgCookiePrefix;
        $opts = array();
        if (!empty($username))
        {
            $dbr = wfGetDB(DB_SLAVE);
            $row = $dbr->selectRow('user', array('user_id', 'user_name', 'user_token'), array('user_name' => $username), __METHOD__);
            if (!$row)
            {
                throw new Exception("User '$username' not found");
            }
            $opts = array(
                CURLOPT_COOKIE =>
                    $wgCookiePrefix.'Token='.urlencode($row->user_token).'; '.
                    $wgCookiePrefix.'UserID='.$row->user_id.'; '.
                    $wgCookiePrefix.'UserName='.urlencode($row->user_name)
            );
        }
        return $opts;
    }

    function isReadable($username, $title)
    {
        $opts = $this->authCookie($username);
        if (!$title instanceof Title)
        {
            $etc = haclfDisableTitlePatch();
            $title = Title::newFromText($title);
            haclfRestoreTitlePatch($etc);
        }
        list($status, $content) = self::GET($title->getFullUrl(), $opts);
        if (!empty($username) && !preg_match('/wgUserName\W+'.preg_quote($username).'.*<\/head/s', $content))
        {
            throw new Exception("Cannot connect to server or authenticate under '$username'");
        }
        return !preg_match('/wgPageName\W+'.$this->access_denied_page.'.*<\/head/s', $content);
    }

    function isFound($username, $title)
    {
        $opts = $this->authCookie($username);
        if (!$title instanceof Title)
        {
            $etc = haclfDisableTitlePatch();
            $title = Title::newFromText($title);
            haclfRestoreTitlePatch($etc);
        }
        $titleText = $title->getText();
        $search = preg_replace('!([/\.\(\)\!\#\-:])!', '', $titleText);
        $url = SpecialPage::getTitleFor('Search')->getFullUrl(array(
            'search' => $search,
            'limit' => 10000,
            'offset' => 0,
            'redirs' => 1,
            'ns'.$title->getNamespace() => 1,
        ));
        list($status, $content) = self::GET($url, $opts);
        preg_match('#<ul[^<>]*class=[\'"]?mw-search-results[^<>]*>(.*)</ul>#is', $content, $m);
        if ($m)
        {
            preg_match_all('#<li>(.*?)</li>#is', $m[1], $list, PREG_PATTERN_ORDER);
            $all = array();
            if ($list[1])
            {
                foreach ($list[1] as $link)
                {
                    preg_match('#<a[^<>]*>(.*?)</a\s*>#is', $link, $m);
                    $link = trim(strip_tags(html_entity_decode($m[1])));
                    $all[] = $link;
                    if ($link == $titleText)
                    {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    static function GET($url, $opts = array())
    {
        $curl = curl_init();
        curl_setopt_array($curl, $opts+array(
            CURLOPT_URL => $url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_RETURNTRANSFER => true,
        ));
        $content = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (intval($status / 100) == 4 || intval($status / 100) == 5)
        {
            throw new Exception("Error requesting $url - HTTP $status");
        }
        return array($status, $content);
    }
}
