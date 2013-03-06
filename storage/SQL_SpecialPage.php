<?php

/**
 * Copyright (c) 2010+,
 *   Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *   Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 *
 * This file is part of IntraACL MediaWiki extension
 * http://wiki.4intra.net/IntraACL
 *
 * Loosely based on HaloACL (c) 2009, ontoprise GmbH
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

class IntraACL_SQL_SpecialPage
{
    /**
     * Special pages do not have an article ID, however access control relies
     * on IDs. This method assigns a (negative) ID to each Special Page whose ID
     * is requested. If no ID is stored yet for a given name, a new one is created.
     *
     * @param string $name
     *         Full name of the special page
     *
     * @return int id
     *         The ID of the page. These IDs are negative, so they do not collide
     *         with normal page IDs.
     */
    public function idForSpecial($name) {
        $dbw = wfGetDB( DB_MASTER );

        $obj = $dbw->selectRow('halo_acl_special_pages', 'id', array('name' => $name), __METHOD__);
        if ($obj === false) {
            // ID not found => create a new one
            $dbw->insert('halo_acl_special_pages', array('name' => $name), __METHOD__);
            // retrieve the auto-incremented ID of the right
            return -$dbw->insertId();
        } else {
            return -$obj->id;
        }
    }

    /**
     * Special pages do not have an article ID, however access control relies
     * on IDs. This method retrieves the name of a special page for its ID.
     *
     * @param int $id
     *         ID of the special page
     *
     * @return string name
     *         The name of the page if the ID is valid. <0> otherwise
     */
    public function specialForID($id) {
        $dbw = wfGetDB( DB_MASTER );
        $obj = $dbw->selectRow('halo_acl_special_pages', 'name', array('id' => -$id), __METHOD__);
        return ($obj === false) ? 0 : $obj->name;
    }
}
