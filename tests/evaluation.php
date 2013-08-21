<?php

/**
 * Copyright 2013, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
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
 * Right evaluation tests.
 *
 * @author Vitaliy Filippov
 */

$dir = dirname($_SERVER['PHP_SELF']);
require_once "$dir/../../../maintenance/commandLine.inc";

//$wgRequest->setVal('hacllog', 'true');

$tester = new IntraACLEvaluationTester();
$tester->runTests();

class IntraACLEvaluationTester
{
    var $title;
    var $acls = array();
    var $aclUsers = array();
    var $queue = array('categoryACL', 'namespaceACL', 'pageACL');
    var $numOk, $numFailed;

    function runTests()
    {
        $this->numOk = $this->numFailed = 0;
        print "Starting test suite\n";
        $this->makeUser("-");
        $this->test();
        $this->cleanupUsers();
        print "Ran ".($this->numOk + $this->numFailed)." tests, {$this->numOk} OK, {$this->numFailed} failed\n";
    }

    /**
     * Continue to the next loop of tests, or just to checkAccess when no more loops is available
     */
    protected function test()
    {
        if ($this->queue)
        {
            $loop = array_pop($this->queue);
            $this->$loop();
            $this->queue[] = $loop;
        }
        else
        {
            $this->checkAccess();
        }
    }

    /**
     * 1) Run tests without page ACL
     * 2) Run tests with page ACL
     */
    protected function pageACL()
    {
        $this->title = Title::newFromText("ACLTestPage");
        $this->acls = array();
        $art = new WikiPage($this->title);
        $art->doEdit('Test page', '-', EDIT_FORCE_BOT);
        $this->test();
        $acl = Title::newFromText("ACL:Page/".$this->title);
        $this->testACLs($acl, 'page');
        (new WikiPage($acl))->doDeleteArticle('-');
        $art = new WikiPage($this->title);
        $art->doDeleteArticle('-');
    }

    /**
     * 1) Run tests without namespace ACL
     * 2) Move page into Project namespace
     * 3) Run tests with Project namespace ACL
     * 4) Move page back
     */
    protected function namespaceACL()
    {
        global $wgCanonicalNamespaceNames;
        $this->test();
        $nt = Title::makeTitle(NS_PROJECT, $this->title->getText());
        $ot = $this->title;
        $ot->moveTo($nt);
        $this->title = $nt;
        $acl = Title::newFromText("ACL:Namespace/".$wgCanonicalNamespaceNames[NS_PROJECT]);
        $this->testACLs($acl, 'ns');
        $nt->moveTo($ot);
        $this->title = $ot;
    }

    /**
     * 1) Run tests without category ACL
     * 2) Add Category:C1 to page
     * 3) Run tests with Category:C1 ACL, with category2ACL() added in loop queue
     * 4) Create Category:SubC1 in Category:C1
     * 5) Run tests with Category:C1 ACL
     * 6) Remove Category:C1 and Category:SubC1
     */
    protected function categoryACL()
    {
        $this->test();
        $this->queue[] = 'category2ACL';
        $art = new WikiPage($this->title);
        $art->doEdit(preg_replace('/\[\[Category:[^\]]*\]\]/is', '', $art->getText())." [[Category:C1]]", '-', EDIT_FORCE_BOT);
        $cat1 = new WikiPage(Title::makeTitle(NS_CATEGORY, "C1"));
        $cat1->doEdit("Test category 1", '-', EDIT_FORCE_BOT);
        $acl1 = Title::newFromText("ACL:Category/C1");
        $this->testACLs($acl1, 'cat.1');
        array_pop($this->queue);
        $art = new WikiPage($this->title);
        $art->doEdit(preg_replace('/\[\[Category:[^\]]*\]\]/is', '', $art->getText())." [[Category:SubC1]]", '-', EDIT_FORCE_BOT);
        $subc1 = new WikiPage(Title::makeTitle(NS_CATEGORY, "SubC1"));
        $subc1->doEdit("Test subcategory 1 [[Category:C1]]", '-', EDIT_FORCE_BOT);
        $this->testACLs($acl1, 'cat.1');
        $art = new WikiPage($this->title);
        $art->doEdit(preg_replace('/\[\[Category:[^\]]*\]\]/is', '', $art->getText()), '-', EDIT_FORCE_BOT);
        $cat1->doDeleteArticle('-');
        $subc1->doDeleteArticle('-');
    }

    /**
     * 1) Run tests without second category ACL
     * 2) Add Category:C2 to page
     * 3) Run tests with Category:C2 ACL
     * 4) Remove Category:C2 from page and remove category C2
     */
    protected function category2ACL()
    {
        $this->test();
        $art = new WikiPage($this->title);
        $art->doEdit($art->getText()." [[Category:C2]]", '-', EDIT_FORCE_BOT);
        $cat2 = new WikiPage(Title::makeTitle(NS_CATEGORY, "C2"));
        $cat2->doEdit("Test category 2", '-', EDIT_FORCE_BOT);
        $acl2 = Title::newFromText("ACL:Category/C2");
        $this->testACLs($acl2, 'cat.2');
        $art = new WikiPage($this->title);
        $art->doEdit(str_replace(" [[Category:C2]]", '', $art->getText()), '-', EDIT_FORCE_BOT);
        $cat2->doDeleteArticle('-');
    }

    /**
     * 1) Run tests with $acl granted to a unique user
     * 2) Run tests with $acl granted to the same user through a group
     * 3) Run tests with $acl granted to the same user through an indirect group inclusion
     * 4) Delete $acl and run tests without it
     */
    protected function testACLs($acl, $priority)
    {
        $user = $this->makeUser($acl->getPrefixedText());
        $username = $user->getName();
        $g = new WikiPage(Title::makeTitle(HACL_NS_ACL, "Group/G_$username"));
        $g->doEdit("{{#member: members = User:$username}}", '-', EDIT_FORCE_BOT);
        $gg = new WikiPage(Title::makeTitle(HACL_NS_ACL, "Group/GG_$username"));
        $gg->doEdit("{{#member: members = Group/G_$username}}", '-', EDIT_FORCE_BOT);
        $this->acls[$priority] = array(
            'title' => $acl,
            'users' => array($username => $user),
        );
        $options = array(
            "{{#access: assigned to = User:$username | actions = *}}",
            "{{#access: assigned to = Group/G_$username | actions = *}}",
            "{{#access: assigned to = Group/GG_$username | actions = *}}",
        );
        foreach ($options as $opt)
        {
            (new Article($acl))->doEdit($opt, '-', EDIT_FORCE_BOT);
            $this->test();
        }
        $gg->doDeleteArticle('-');
        $g->doDeleteArticle('-');
        (new Article($acl))->doDeleteArticle('-');
        unset($this->acls[$priority]);
        $this->test();
    }

    /**
     * Last loop in the testsuite - determines which users should and which users
     * shouldn't have access to $this->title with current applied ACLs in different
     * override modes and calls assertReadable().
     */
    protected function checkAccess()
    {
        global $haclgCombineMode, $haclgOpenWikiAccess;
        if (!$this->acls)
        {
            $haclgOpenWikiAccess = false;
            $this->assertReadable(reset($this->aclUsers), false);
            $haclgOpenWikiAccess = true;
            $this->assertReadable(reset($this->aclUsers), true);
            return;
        }
        $priorities = array('ns' => 1, 'cat' => 2, 'page' => 3);
        foreach (array(HACL_COMBINE_EXTEND, HACL_COMBINE_OVERRIDE, HACL_COMBINE_SHRINK) as $mode)
        {
            $haclgCombineMode = $mode;
            $byPriority = array();
            foreach ($this->acls as $key => $info)
            {
                $key = explode('.', $key);
                $key = $priorities[$key[0]];
                $byPriority[$key] = isset($byPriority[$key]) ? array_merge($byPriority[$key], $info['users']) : $info['users'];
            }
            $curPriority = false;
            $allUsers = array();
            foreach ($byPriority as $priority => $users)
            {
                if ($mode == HACL_COMBINE_EXTEND)
                {
                    $allUsers = array_merge($allUsers, $users);
                }
                elseif ($mode == HACL_COMBINE_SHRINK)
                {
                    if (!$curPriority)
                    {
                        $allUsers = $users;
                    }
                    else
                    {
                        foreach ($allUsers as $un => $u)
                        {
                            if (!isset($users[$un]))
                            {
                                unset($allUsers[$un]);
                            }
                        }
                    }
                    $curPriority = $priority;
                }
                elseif ($mode == HACL_COMBINE_OVERRIDE)
                {
                    if (!$curPriority || $curPriority < $priority)
                    {
                        $curPriority = $priority;
                        $allUsers = $users;
                    }
                }
            }
            foreach ($allUsers as $un => $u)
            {
                $this->assertReadable($u, true);
            }
            foreach ($this->aclUsers as $u)
            {
                if (!isset($allUsers[$u->getName()]))
                {
                    $this->assertReadable($u, false);
                    break;
                }
            }
        }
    }

    /**
     * Run a single test - assert $this->title is/isn't readable (depending on $readable)
     * by $user, report status and failure details, if any.
     */
    protected function assertReadable($user, $readable)
    {
        global $haclgCombineMode, $haclgOpenWikiAccess;
        $info = array_merge(array(
            ($haclgOpenWikiAccess ? 'OPEN' : 'CLOSED'), $haclgCombineMode,
        ), array_keys($this->acls));
        $result = false;
        if (class_exists('IACLEvaluator'))
        {
            IACLEvaluator::userCan($this->title, $user, 'read', $result);
        }
        else
        {
            HACLEvaluator::userCan($this->title, $user, 'read', $result);
        }
        $ok = ($readable == $result);
        if ($ok)
        {
            print "[OK] ";
            $this->numOk++;
        }
        else
        {
            print "[FAILED] ";
            $this->numFailed++;
        }
        print '['.implode(' ', $info).'] '.$user->getName().($readable ? " can read " : " cannot read ").$this->title."\n";
        if (!$ok)
        {
            global $haclgCombineMode, $haclgOpenWikiAccess;
            $art = new WikiPage($this->title);
            print "  Details:\n    Open Wiki Access = ".($haclgOpenWikiAccess ? 'true' : 'false')."\n";
            print "    Combine Mode = $haclgCombineMode\n";
            print "    Article content = ".trim($art->getText())."\n";
            if ($this->acls)
            {
                print "  Applied ACLs:\n";
                foreach ($this->acls as $key => $info)
                {
                    print '    '.$info['title'].' '.trim((new WikiPage($info['title']))->getText())."\n";
                }
            }
            else
            {
                print "  No applied ACLs\n";
            }
            wfGetDB(DB_MASTER)->commit();
            exit;
        }
    }

    /**
     * Make a user for testing ACL $key - the same user is returned for the same $key
     */
    protected function makeUser($key)
    {
        if (!isset($this->aclUsers[$key]))
        {
            $name = 'AclTestUser'.count($this->aclUsers);
            $u = User::newFromName($name);
            if (!$u || !$u->getId())
            {
                $u = User::createNew($name, array());
                if (!$u)
                {
                    throw new Exception("Failed to create user $name");
                }
            }
            $this->aclUsers[$key] = $u;
        }
        return $this->aclUsers[$key];
    }

    /**
     * Delete all users created during test run
     */
    protected function cleanupUsers()
    {
        foreach ($this->aclUsers as $u)
        {
            $this->deleteUser($u);
        }
    }

    /**
     * Delete user $objOldUser
     */
    protected function deleteUser($objOldUser)
    {
        $dbw = wfGetDB(DB_MASTER);
        $dbw->delete('user_groups', array('ug_user' => $objOldUser->getId()));
        $dbw->delete('user', array('user_id' => $objOldUser->getId()));
        $users = $dbw->selectField('user', 'COUNT(*)', array());
        $dbw->update('site_stats',
            array('ss_users' => $users),
            array('ss_row_id' => 1));
        return true;
    }
}
