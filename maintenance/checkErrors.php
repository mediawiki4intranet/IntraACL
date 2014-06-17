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

/**
 * This script checks for syntax and consistency errors in all IntraACL definitions
 *
 * @author Vitaliy Filippov
 */

require_once __DIR__.'/../../../maintenance/Maintenance.php';

class IntraACL_ErrorCheck extends Maintenance
{
    function __construct()
    {
        parent::__construct();
        $this->mDescription = 'IntraACL error checker';
        $this->addOption('quiet', 'Only report erroneous definitions, do not print "OK" for good ones');
    }

    function execute()
    {
        global $wgParser;
        $wgParser->parse(' ', Title::newMainPage(), new ParserOptions);
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('page', '*', array('page_namespace' => HACL_NS_ACL), __METHOD__);
        $titles = array();
        foreach ($res as $row)
        {
            $titles[] = Title::newFromRow($row);
        }
        $quiet = $this->hasOption('quiet');
        $seen = array();
        foreach ($titles as $title)
        {
            $page = new WikiPage($title);
            $pf = IACLParserFunctions::instance($title);
            IACLParserFunctions::parse($page->getText(), $title);
            $errors = $pf->consistencyCheckStatus(false);
            $errors = array_merge($errors, $pf->errors);
            if ($pf->def)
            {
                if (isset($seen[$pf->def['key']]))
                {
                    $errors[] = "Duplicate definition! Previous one is ".$seen[$pf->def['key']];
                }
                $seen[$pf->def['key']] = "$title";
            }
            IACLParserFunctions::destroyInstance($pf);
            if ($errors)
            {
                print "Errors on $title:\n";
                foreach ($errors as $e)
                {
                    print "\t$e\n";
                }
            }
            elseif (!$quiet)
            {
                print "OK $title\n";
            }
        }
    }
}

$maintClass = 'IntraACL_ErrorCheck';
require_once(RUN_MAINTENANCE_IF_MAIN);
