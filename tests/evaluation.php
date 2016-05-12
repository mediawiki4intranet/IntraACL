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
 * TODO: Test different actions (read, edit, delete, move, manage, sd management)
 *
 * @author Vitaliy Filippov
 */

$dir = dirname($_SERVER['PHP_SELF']);
require_once "$dir/../../../maintenance/Maintenance.php";

class IntraACLEvaluationTester extends Maintenance
{
    var $title;
    var $acls = array();
    var $aclUsers = array();
    var $anonUser;
    var $queue;
    var $numOk, $numFailed;

    var $pfx = '', $newline = "\n";

    var $stopOnFailure = false, $onlyFailure = false, $logActions = false;

    function __construct()
    {
        parent::__construct();
        $this->addOption('evaluation-log', 'Enable very verbose IntraACL evaluation logs for all tests', false, false);
        $this->addOption('stop', 'Stop on first failed test', false, false);
        $this->addOption('only-failures', 'Only print test failure results', false, false);
        $this->addOption('admin-user', 'Administrator username (WikiSysop by default)', false, false);
        $this->addOption('one-line', 'Print output in one line using escape seq', false, false);
        $this->addOption('log-actions', 'Log all performed wiki page manipulations', false, false);
    }

    /**
     * Save $content to $page
     */
    protected function doEdit($page, $content)
    {
        $page->doEdit($content, '-', EDIT_FORCE_BOT);
        if ($this->logActions)
        {
            print "Update ".$page->getTitle()." = ".$page->getId()." to: ".str_replace("\n", " ", $content)."\n";
        }
    }

    /**
     * Delete $page
     */
    protected function doDelete($page)
    {
        if ($page instanceof Title)
        {
            $page = new WikiPage($page);
        }
        if ($this->logActions)
        {
            print "Delete ".$page->getTitle()." = ".$page->getId()."\n";
        }
        global $wgTitle;
        $wgTitle = $page->getTitle();
        $page->doDeleteArticle('-');
        $wgTitle = NULL;
    }

    /**
     * Move page
     */
    protected function doMove($oldtitle, $newtitle)
    {
        if (is_string($oldtitle))
            $oldtitle = Title::newFromText($oldtitle);
        if (is_string($newtitle))
            $newtitle = Title::newFromText($newtitle);
        if ($newtitle->exists())
            $this->doDelete($newtitle);
        if ($this->logActions)
            print "Move $oldtitle to $newtitle\n";
        $err = $oldtitle->moveTo($newtitle, true, '-', false);
        if ($err !== true)
        {
            print "Error moving $oldtitle to $newtitle: \n";
            var_dump($err);
            exit;
        }
    }

    function execute()
    {
        if ($this->getOption('evaluation-log', false))
        {
            global $wgRequest;
            $wgRequest->setVal('hacllog', 'true');
        }
        if ($this->getOption('one-line', false))
        {
            $this->pfx = "\r\x1B[K";
            $this->newline = '';
        }
        // Override user (we should run under admin)
        global $wgUser;
        $this->anonUser = $wgUser;
        $username = $this->getOption('admin-user', 'WikiSysop');
        $wgUser = User::newFromName($username);
        if (!$wgUser->getId())
        {
            print "User $username does not exist, please specify administrator username using --admin-user option.\n";
            exit;
        }
        $this->onlyFailures = $this->getOption('only-failures', false);
        $this->stopOnFailure = $this->getOption('stop', false);
        $this->logActions = $this->getOption('log-actions', false);
        $this->numOk = $this->numFailed = 0;
        print "Starting test suite\n";
        // AclTestUser0 is never specified in any ACL so we use it for testing access denial
        $this->makeUser(":0");
        // AclTestUser1 is specified in every ACL so we can test the shrink mode
        $u1 = $this->makeUser(":shrink")->getName();
        $u2 = $this->makeUser(":gm")->getName();
        $g = new WikiPage(Title::makeTitle(HACL_NS_ACL, "Group/G_$u1"));
        $this->doEdit($g, "{{#member: members = User:$u1}} {{#manage group: assigned to = User:$u2}}");
        $gg = new WikiPage(Title::makeTitle(HACL_NS_ACL, "Group/GG_$u1"));
        $this->doEdit($gg, "{{#member: members = Group/G_$u1}} {{#manage group: assigned to = User:$u2}}");
        // Run tests
        $this->stack = array('groupManagers');
        $this->test();
        $this->stack = array('checkAccess', 'categoryACL', 'treeACL', 'namespaceACL', 'pageACL');
        $this->test();
        // Remove users and groups
        $this->doDelete(Title::makeTitle(HACL_NS_ACL, "Group/G_$u1"));
        $this->doDelete(Title::makeTitle(HACL_NS_ACL, "Group/GG_$u1"));
        $this->cleanupUsers();
        print "\nRan ".($this->numOk + $this->numFailed)." tests, {$this->numOk} OK, {$this->numFailed} failed\n";
    }

    /**
     * Continue to the next loop of tests, or a no-op when no inner loops are pending
     */
    protected function test()
    {
        if ($this->stack)
        {
            $loop = array_pop($this->stack);
            $this->$loop();
            $this->stack[] = $loop;
        }
    }

    /**
     * Run group manager checks
     */
    protected function groupManagers()
    {
        $oldTitle = $this->title;
        $u1 = $this->makeUser(":shrink")->getName();
        $this->title = Title::makeTitle(HACL_NS_ACL, "Group/G_$u1");
        $this->assertCan($this->makeUser(':gm'), true, 'edit');
        $this->assertCan($this->makeUser(':shrink'), false, 'edit');
        $this->assertCan($this->makeUser(':0'), false, 'edit');
        $this->title = Title::makeTitle(HACL_NS_ACL, "Group/GG_$u1");
        $this->assertCan($this->makeUser(':gm'), true, 'edit');
        $this->assertCan($this->makeUser(':shrink'), false, 'edit');
        $this->assertCan($this->makeUser(':0'), false, 'edit');
        $this->title = $oldTitle;
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
        $this->doEdit($art, 'Test page');
        $acl = Title::newFromText("ACL:Page/".self::canon($this->title));
        if ($acl->exists())
        {
            $this->doDelete($acl);
            $acl = Title::newFromText("ACL:Page/".self::canon($this->title));
        }
        $this->test();
        $this->testACLs($acl, 'page');
        $this->doDelete($this->title);
    }

    protected static function canon($t)
    {
        return ($t->getNamespace() ? iaclfCanonicalNsText($t->getNamespace()).':' : '') . $t->getText();
    }

    /**
     * Check that tree ACL protects subsubpages, protects page itself, overrides parent tree ACL and
     * correctly interacts with other ACL types.
     * 1) Run tests on page A/B/C/D with tree ACL created for pages A and A/B
     * 2) Run tests on page A without tree ACL
     */
    protected function treeACL()
    {
        $t_base = self::canon($this->title);
        $t_sub = "$t_base/Subpage";
        $t_subsub = "$t_base/Subpage/S2/D";
        $this->doDelete(Title::newFromText("ACL:Tree/$t_base"));
        $this->doDelete(Title::newFromText("ACL:Tree/$t_sub"));
        $this->doDelete(Title::newFromText($t_subsub));
        // test subpage itself
        $origpageacl = Title::newFromText("ACL:Page/$t_base");
        $orig = $origpageacl->exists();
        if ($orig)
            $this->doMove($origpageacl, "ACL:Page/$t_sub");
        $this->title = Title::newFromText($t_sub);
        $this->doEdit(new WikiPage($this->title), 'Test subpage');
        $acl = Title::newFromText("ACL:Tree/$t_sub");
        if ($acl->exists())
        {
            $this->doDelete($acl);
            $acl = Title::newFromText("ACL:Tree/$t_sub");
        }
        $stk = $this->stack;
        $this->stack = array('checkAccess');
        $this->testACLs($acl, 'tree');
        $this->stack = $stk;
        // create acl for base page
        $this->doDelete(Title::newFromText("ACL:Tree/$t_sub"));
        $baseacl = Title::newFromText("ACL:Tree/$t_base");
        $subpageacl_user = $this->makeUser("ACL:Page/$t_subsub");
        $this->doEdit(new WikiPage($baseacl), "Base tree ACL\n{{#access: assigned to = *, #, User:$subpageacl_user | actions = *}}");
        // test subsubpage
        if ($orig)
            $this->doMove("ACL:Page/$t_sub", "ACL:Page/$t_subsub");
        $this->title = Title::newFromText($t_subsub);
        $this->doEdit(new WikiPage($this->title), 'Test subsubpage');
        $this->testACLs($acl, 'tree', array('title' => $baseacl, 'users' => array('*' => '*')));
        $this->doDelete($this->title);
        $this->doDelete(Title::newFromText("ACL:Tree/$t_base"));
        $this->doDelete(Title::newFromText("ACL:Tree/$t_sub"));
        $this->doDelete(Title::newFromText($t_sub));
        unset($this->acls['tree']);
        if ($orig)
            $this->doMove("ACL:Page/$t_subsub", "ACL:Page/$t_base");
        $this->title = Title::newFromText($t_base);
        $this->test();
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
        $this->doMove($ot, $nt);
        $this->title = $nt;
        $acl = Title::newFromText("ACL:Namespace/".$wgCanonicalNamespaceNames[NS_PROJECT]);
        $this->testACLs($acl, 'ns');
        $this->doMove($nt, $ot);
        $this->title = $ot;
    }

    /**
     * 1) Run tests without category ACL
     * 2) Add Category:C1 to page
     * 3) Run tests with Category:C1 ACL
     * 4) Create Category:SubC1 in Category:C1
     * 5) Run tests with Category:C1 ACL
     * 6) Remove Category:C1 and Category:SubC1
     */
    protected function categoryACL()
    {
        $this->test();
        $this->stack[] = 'category2ACL';
        $art = new WikiPage($this->title);
        $this->doEdit($art, preg_replace('/\[\[Category:[^\]]*\]\]/is', '', $art->getText())." [[Category:C1]]");
        $cat1 = new WikiPage(Title::makeTitle(NS_CATEGORY, "C1"));
        $this->doEdit($cat1, "Test category 1");
        $acl1 = Title::newFromText("ACL:Category/C1");
        $this->testACLs($acl1, 'cat.1');
        $art = new WikiPage($this->title);
        $this->doEdit($art, preg_replace('/\[\[Category:[^\]]*\]\]/is', '', $art->getText())." [[Category:SubC1]]");
        $subc1 = new WikiPage(Title::makeTitle(NS_CATEGORY, "SubC1"));
        $this->doEdit($subc1, "Test subcategory 1 [[Category:C1]]");
        $this->testACLs($acl1, 'cat.1');
        $art = new WikiPage($this->title);
        $this->doEdit($art, preg_replace('/\[\[Category:[^\]]*\]\]/is', '', $art->getText()));
        $this->doDelete($cat1);
        $this->doDelete($subc1);
        array_pop($this->stack);
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
        $this->doEdit($art, $art->getText()." [[Category:C2]]");
        $cat2 = new WikiPage(Title::makeTitle(NS_CATEGORY, "C2"));
        $this->doEdit($cat2, "Test category 2");
        $acl2 = Title::newFromText("ACL:Category/C2");
        $this->testACLs($acl2, 'cat.2');
        $art = new WikiPage($this->title);
        $this->doEdit($art, str_replace(" [[Category:C2]]", '', $art->getText()));
        $this->doDelete($cat2);
    }

    /**
     * 1) Run tests with $acl granted to a unique user
     * 2) Run tests with $acl granted to the same user through an indirect group inclusion
     * 3) Run tests for # and *
     * 4) Delete $acl and run tests without it
     */
    protected function testACLs($acl, $priority, $overAfterDel = NULL)
    {
        $user1 = $this->makeUser(":shrink");
        $u1 = $user1->getName();
        $user = $this->makeUser($acl->getPrefixedText());
        $username = $user->getName();
        $g = new WikiPage(Title::makeTitle(HACL_NS_ACL, "Group/G_$username"));
        $this->doEdit($g, "{{#member: members = User:$username}}");
        $gg = new WikiPage(Title::makeTitle(HACL_NS_ACL, "Group/GG_$username"));
        $this->doEdit($gg, "{{#member: members = Group/G_$username}}");
        $this->acls[$priority] = array(
            'title' => $acl,
            'users' => array($username => $user, $u1 => $user1),
        );
        $options = array(
            "{{#access: assigned to = Group/GG_$u1, User:$username | actions = *}}",
            "{{#access: assigned to = User:$u1, Group/GG_$username | actions = *}}",
            "{{#access: assigned to = * | actions = *}}\n{{#manage rights: assigned to = User:$u1}}",
            "{{#access: assigned to = # | actions = *}}\n{{#manage rights: assigned to = Group/GG_$u1}}",
        );
        static $testedRegGroup;
        if (!$testedRegGroup)
        {
            // Test inclusion of # via groups
            $options[] = "{{#access: assigned to = User:$u1, Group/GG_$username | actions = *}}";
            $testedRegGroup = true;
        }
        foreach ($options as $i => $opt)
        {
            if ($i == 2)
            {
                $this->acls[$priority]['users'] = array('*' => '*');
            }
            elseif ($i == 3)
            {
                $this->acls[$priority]['users'] = array('#' => '#');
            }
            elseif ($i == 4)
            {
                $g = new WikiPage(Title::makeTitle(HACL_NS_ACL, "Group/G_$username"));
                $this->doEdit($g, "{{#member: members = #}}");
            }
            $this->doEdit(new WikiPage($acl), $opt);
            $this->test();
        }
        $this->doDelete(Title::makeTitle(HACL_NS_ACL, "Group/GG_$username"));
        $this->doDelete(Title::makeTitle(HACL_NS_ACL, "Group/G_$username"));
        $this->doDelete($acl);
        if ($overAfterDel)
            $this->acls[$priority] = $overAfterDel;
        else
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
        $priorities = array('ns' => 1, 'cat' => 2, 'tree' => 3, 'page' => 4);
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
                        if (isset($allUsers['*']) && isset($users['#']) && !isset($users['*']))
                        {
                            $allUsers['#'] = true;
                        }
                        elseif (isset($users['*']) && isset($allUsers['#']) && !isset($allUsers['*']))
                        {
                            $users['#'] = true;
                        }
                        foreach ($allUsers as $un => $u)
                        {
                            if (!isset($users[$un]) &&
                                ($un == '*' || $un == '#' || !isset($users['*']) && !isset($users['#'])))
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
            if (isset($allUsers['*']))
            {
                $this->assertReadable($this->anonUser, true);
                $this->assertReadable(reset($this->aclUsers), true);
            }
            elseif (isset($allUsers['#']))
            {
                $this->assertReadable($this->anonUser, false);
                $this->assertReadable(reset($this->aclUsers), true);
            }
            else
            {
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
                $this->assertReadable($this->anonUser, false);
            }
        }
    }

    protected function assertReadable($user, $readable)
    {
        $this->assertCan($user, $readable, 'read');
    }

    /**
     * Run a single test - assert $user can/cannot do $action on (depending on $can)
     * by $user, report status and failure details, if any.
     */
    protected function assertCan($user, $can, $action)
    {
        global $haclgCombineMode, $haclgOpenWikiAccess, $wgUser, $iaclUseStoredProcedure;
        $info = array_merge(array(
            ($haclgOpenWikiAccess ? 'OPEN' : 'CLOSED'), $haclgCombineMode,
        ), array_keys($this->acls));
        $result = false;
        IACLEvaluator::userCan($this->title, $user, $action, $result);
        $ok = ($can == $result) ? 1 : 0;
        if ($ok && $action == 'read' && $iaclUseStoredProcedure)
        {
            // Check stored procedure
            $dbw = wfGetDB(DB_MASTER);
            $query = array(
                'tables' => array('page'),
                'fields' => '*',
                'conds' => array('page_namespace' => $this->title->getNamespace(), 'page_title' => $this->title->getDBkey()),
                'options' => array(),
                'join_conds' => array(),
            );
            $oldUser = $wgUser;
            $wgUser = $user;
            IACLEvaluator::FilterPageQuery($query);
            $wgUser = $oldUser;
            $res = $dbw->select($query['tables'], $query['fields'], $query['conds'], __METHOD__, $query['options'], $query['join_conds']);
            $row = $res->fetchObject();
            $ok = $can == ($row && true) ? 1 : -1;
        }
        if ($ok == 1)
        {
            $str = "[OK] ";
            $this->numOk++;
        }
        elseif ($ok == 0)
        {
            $str = "[FAILED] ";
            $this->numFailed++;
        }
        elseif ($ok == -1)
        {
            $str = "[SP FAILED] ";
            $this->numFailed++;
        }
        $str = $this->pfx.sprintf("%5d ", $this->numFailed+$this->numOk).$str.'['.implode(' ', $info).'] '.
            $user->getName().($can ? " can " : " cannot ").$action.' '.$this->title.($ok ? $this->newline : "\n");
        if ($ok != 1 || !$this->onlyFailures)
        {
            print $str;
        }
        if ($ok != 1)
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
                    print '    '.$info['title'].' '.trim(preg_replace('/\s+/s', ' ', (new WikiPage($info['title']))->getText()))."\n";
                }
            }
            else
            {
                print "  No applied ACLs\n";
            }
            wfGetDB(DB_MASTER)->commit();
            if ($this->stopOnFailure)
            {
                exit;
            }
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

$maintClass = "IntraACLEvaluationTester";
require_once(RUN_MAINTENANCE_IF_MAIN);
