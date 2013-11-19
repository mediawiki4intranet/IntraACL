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
 * This script re-parses all IntraACL right definitions.
 *
 * There is normally no need to do it. It can be useful only when the permission
 * database gets unexpected corruptions or during the IntraACL development.
 *
 * @author Vitaliy Filippov
 */

require_once __DIR__.'/../../../maintenance/Maintenance.php';

class IntraACL_Reparse extends Maintenance
{
    var $mDescription = 'IntraACL definition refresher';

    function execute()
    {
        global $wgParser;
        $wgParser->parse(' ', Title::newMainPage(), new ParserOptions);
        $dbw = wfGetDB(DB_MASTER);
        $dbw->delete('halo_acl_special_pages', array('1=1'), __METHOD__);
        $dbw->delete('intraacl_rules', array('1=1'), __METHOD__);
        DeferReparsePageRights::refreshAll();
    }
}

$maintClass = 'IntraACL_Reparse';
require_once(RUN_MAINTENANCE_IF_MAIN);
