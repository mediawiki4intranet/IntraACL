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
 * This class is used to manipulate user quick ACL lists.
 */
class IACLQuickacl
{
    var $userid;
    var $pe_ids = array(/*array($peType, $peID)*/);
    var $default_pe_id; /*array($peType, $peID)*/

    static function newForUserId($userid)
    {
        list($pe_ids, $default_pe_id) = IACLStorage::get('QuickACL')->getQuickacl($userid);
        return new self($userid, $pe_ids, $default_pe_id);
    }

    function __construct($userid, $pe_ids, $default_pe_id = NULL)
    {
        $this->userid = $userid;
        $this->default_pe_id = $default_pe_id ?: NULL;
        foreach ($pe_ids as $pe)
        {
            $this->addPE($pe);
        }
    }

    function getDefs()
    {
        return IACLDefinition::select(array('pe' => $this->pe_ids));
    }

    function addPE($peType, $peID)
    {
        $this->pe_ids["$peType-$peID"] = array($peType, $peID);
    }

    function getPEIds()
    {
        return $this->pe_ids;
    }

    function save()
    {
        return IACLStorage::get('QuickACL')->saveQuickacl($this->userid, $this->pe_ids, $this->default_pe_id);
    }
}
