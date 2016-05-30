<?php

/**
 * Copyright 2010+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *                  Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 * This file is part of IntraACL MediaWiki extension. License: GPLv3.
 * Homepage: http://wiki.4intra.net/IntraACL
 *
 * Loosely based on HaloACL (c) 2009, ontoprise GmbH
 * But almost anything is already rewritten.
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
 * This file has to be included into LocalSettings.php in order to enable IntraACL extension.
 * After that the function enableIntraACL() must be called.
 */
if (!defined('MEDIAWIKI'))
{
    die("This file is part of the IntraACL extension. It is not a valid entry point.");
}

define('HACL_COMBINE_EXTEND', 'extend');
define('HACL_COMBINE_SHRINK', 'shrink');
define('HACL_COMBINE_OVERRIDE', 'override');

# Workaround for online updater
global $haclgIP;

# This is the path to your installation of IntraACL as seen on your
# local filesystem. Used against some PHP file path issues.
$haclgIP = dirname(__DIR__);

# This is the path to your installation of IntraACL as seen from the
# web. Change it if required ($wgScriptPath is the path to the base directory
# of your wiki). No final slash.
$haclgHaloScriptPath = 'extensions/IntraACL';

# Set this variable to false to disable the patch that checks all titles
# for accessibility. Unfortunately, the Title-object does not check if an article
# can be accessed. A patch adds this functionality and checks every title that is
# created. If a title can not be accessed, a replacement title called "Permission
# denied" is returned. This is the best and securest way of protecting an article,
# however, it slows down things a bit.
$haclgEnableTitleCheck = false;

# This variable controls the behaviour of unreadable articles included into other
# articles. When it is a non-empty string, it is treated as the name of a message
# inside MediaWiki: namespace (i.e. localisation message). When it is set to an
# empty string, nothing is displayed in place of protected inclusion. When it is
# set to boolean FALSE, inclusion directive is shown instead of article content.
$haclgInclusionDeniedMessage = 'haloacl-inclusion-denied';

# This flag applies to articles that have or inherit no security descriptor.
#
# true
#    If this value is <true>, all articles that have no security descriptor are
#    fully accessible for IntraACL. Other extensions or $wgGroupPermissions can
#     still prohibit access.
#    Remember that security descriptor are also inherited via categories or
#    namespaces.
# false
#    If it is <false>, no access is granted at all. Only the latest author of an
#    article can create a security descriptor.
$haclgOpenWikiAccess = true;

# Values of this array are treated as language-dependent names of namespaces which
# can not be protected by IntraACL.
$haclgUnprotectableNamespaces = array();

# If this is true, "ACL" tab will be hidden for unprotected pages.
$haclgDisableACLTab = false;

# If $haclgEvaluatorLog is <true>, you can specify the URL-parameter "hacllog=true".
# In this case IntraACL echos the reason why actions are permitted or prohibited.
$haclgEvaluatorLog = true;

# If you already have custom namespaces on your site, insert
#    $haclgNamespaceIndex = ???;
# into your LocalSettings.php *before* including this file. The number ??? must
# be the smallest even namespace number that is not in use yet. However, it
# must not be smaller than 100.
$haclgNamespaceIndex = 102;

# This specifies how different right definitions which apply to one page combine.
# There may be page, category and namespace rights.
# Possible values:
# - HACL_COMBINE_EXTEND: user has the right if it is granted within ANY of the applicable definitions.
# - HACL_COMBINE_SHRINK: user has the right only if it is granted within ALL applicable definitions.
# - HACL_COMBINE_OVERRIDE: more specific rights override less specific ones.
#   I.e. page rights override category rights, which override namespace rights.
$haclgCombineMode = HACL_COMBINE_EXTEND;

# Names of MediaWiki groups members of which are IntraACL super-users
# and can view and edit all articles, ACLs and etc. It is needed to
# have someone who can repair incorrect right definitions created by users
# (which is a very common scenario in the case of IntraACL because of a
# relatively complex right model).
$haclgSuperGroups = array('bureaucrat', 'sysop');

# Preload 1000 SDs individually granted for current user during right checks.
# If your database is very large and this number is exceeded, IntraACL will begin to
# make individual DB queries for the access check of each separate page.
$iaclPreloadLimit = 1000;

# Use stored procedure for correct list paging. With stored procedure enabled, page lists
# run slower, but don't look weird because permissions are checked inside MySQL and it can
# return correct count of readable pages. Otherwise MediaWiki may show 100 pages, but some
# of them may contain 5 items, 1 item or even no items at all.
#
# Turn off (by setting this variable to false) if you don't care of if your MySQL installation
# doesn't have stored procedure support available.
$iaclUseStoredProcedure = true;

# If set to true, IntraACL will skip read permission checks, i.e. all pages will
# be always available for reading by everyone. This removes IntraACL's performance penalty,
# but still allows to restrict edit permissions.
$iaclDisableReadPermissions = false;

# See also $wgWhitelistRead - IntraACL opens whitelisted pages for reading

// load global functions
require_once(dirname(__FILE__).'/GlobalFunctions.php');
