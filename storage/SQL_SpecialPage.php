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
    var $name2id, $id2name;

    /**
     * There is always a relatively small amount of special pages
     * So we are free to load all their ID<>name mappings at once
     */
    protected function load()
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('halo_acl_special_pages', '*', NULL, __METHOD__);
        $this->name2id = $this->id2name = array();
        foreach ($res as $row)
        {
            $this->name2id[$row->name] = $row->id;
            $this->id2name[$row->id] = $row->name;
        }
    }

    /**
     * Special pages do not have an article ID, however access control relies
     * on IDs. This method assigns an ID to each Special Page whose ID
     * is requested. If no ID is stored yet for a given name, a new one is created.
     *
     * @param string $name  Full name of the special page.
     * @return int          The generated ID of the page.
     */
    public function idForSpecial($name)
    {
        if (!$this->name2id)
        {
            $this->load();
        }
        if (empty($this->name2id[$name]))
        {
            $dbw = wfGetDB(DB_MASTER);
            $dbw->insert('halo_acl_special_pages', array('name' => $name), __METHOD__);
            $id = $dbw->insertId();
            $this->name2id[$name] = $id;
            $this->id2name[$id] = $name;
            return $id;
        }
        return $this->name2id[$name];
    }

    /**
     * Retrieve multiple special page IDs at once.
     *
     * @param array|int $ids        Generated special page IDs.
     * @return array(int => string) Special page names.
     */
    public function specialsForIds($ids)
    {
        if (!$this->id2name)
        {
            $this->load();
        }
        $names = array();
        foreach ((array)$ids as $id)
        {
            if (isset($this->id2name[$id]))
            {
                $names[$id] = $this->id2name[$id];
            }
        }
        return $names;
    }
}
