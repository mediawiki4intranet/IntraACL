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
 * This file contains the class used to manipulate user quick ACL lists.
 */
if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

class HACLQuickacl
{
    protected $userid = 0;
    protected $sd_ids = array();
    var $default_sd_id = 0;

    public function getUserid()
    {
        return $this->userid;
    }

    public function setUserid($userid)
    {
        $this->userid = $userid;
    }

    function __construct($userid, $sd_ids, $default_sd_id = NULL)
    {
        $this->userid = $userid;
        $this->sd_ids = array_flip($sd_ids);
        $this->default_sd_id = $default_sd_id ? $default_sd_id : 0;
    }

    public function getDefaultSD_ID()
    {
        return $this->default_sd_id;
    }

    public function setDefaultSD_ID($id)
    {
        if ($id)
            $this->sd_ids[$id] = true;
        $this->default_sd_id = $id ? $id : 0;
    }

    public function getSD_IDs()
    {
        return array_keys($this->sd_ids);
    }

    public function getSDs()
    {
        return IACLStorage::get('SD')->getSDById(array_keys($this->sd_ids));
    }

    public function addSD_ID($sdID)
    {
        $this->sd_ids[$sdID] = true;
    }

    public static function newForUserId($user_id)
    {
        return IACLStorage::get('QuickACL')->getQuickacl($user_id);
    }

    public function save()
    {
        return IACLStorage::get('QuickACL')->saveQuickacl($this->userid, array_keys($this->sd_ids), $this->default_sd_id);
    }

    public static function removeQuickAclsForSD($sdid)
    {
        return IACLStorage::get('QuickACL')->deleteQuickaclForSD($sdid);
    }
}
