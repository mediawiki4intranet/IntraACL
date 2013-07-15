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

/**
 * Utility functions used to query standard MediaWiki database
 */
class IntraACL_SQL_Util
{
    /**
     * Massively retrieves users with IDs $user_ids from the DB
     * @return array(int $userID => stdClass $row)
     */
    public function getUsers($user_ids)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $rows = array();
        if ($user_ids)
        {
            $res = $dbr->select('user', '*', array('user_id' => $user_ids), __METHOD__);
            foreach ($res as $r)
            {
                $rows[$r->user_id] = $r;
            }
        }
        return $rows;
    }

    /**
     * Massively retrieves titles with ids $ids from the DB
     * @return array(object), indexed by page ID, if $as_object is false
     * @return array(Title), indexed by page ID, if $as_object is true
     */
    public function getTitles($ids, $as_object = false)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $rows = array();
        if ($ids)
        {
            $res = $dbr->select('page', '*', array('page_id' => $ids), __METHOD__);
            if (!$as_object)
                foreach ($res as $r)
                    $rows[$r->page_id] = $r;
            else
                foreach ($res as $r)
                    $rows[$r->page_id] = Title::newFromRow($r);
        }
        return $rows;
    }

    /**
     * Massively retrieves contents of categories with db-keys $dbkeys
     * @return array(category_dbkey => array(Title))
     */
    public function getCategoryLinks($dbkeys)
    {
        if (!$dbkeys)
        {
            return array();
        }
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(array('categorylinks', 'p' => 'page'), 'cl_to, p.*',
            array('page_id=cl_from', 'cl_to' => $dbkeys), __METHOD__);
        $cont = array();
        foreach ($res as $row)
        {
            $cont[$row->cl_to][] = Title::newFromRow($row);
        }
        return $cont;
    }

    /**
     * Get Title objects for child categories, recursively, including initial $categories
     * $categories: array(Title) - Title objects of parent categories
     */
    public function getAllChildrenCategories($categories)
    {
        if (!$categories)
        {
            return array();
        }
        $dbr = wfGetDB(DB_SLAVE);
        $cats = array();
        foreach ($categories as $c)
        {
            $cats[$c->getDBkey()] = $c;
        }
        $categories = array_keys($cats);
        // Get subcategories
        while ($categories)
        {
            $res = $dbr->select(array('p' => 'page', 'categorylinks'), 'p.*',
                array('cl_from=page_id', 'cl_to' => $categories, 'page_namespace' => NS_CATEGORY),
                __METHOD__);
            $categories = array();
            foreach ($res as $row)
            {
                if (empty($cats[$row->page_title]))
                {
                    $categories[] = $row->page_title;
                    $cats[$row->page_title] = Title::newFromRow($row);
                }
            }
        }
        return array_values($cats);
    }

    /**
     * Returns IDs of all parent categories for article with ID $articleID
     * (including non-direct inclusions)
     * FIXME: Maybe speed up this by materializing?
     *
     * @param int|array(int) $articleID
     */
    public function getParentCategoryIDs($articleID)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $ids = array();
        $new = is_array($articleID) ? $articleID : array($articleID);
        while ($new)
        {
            $res = $dbr->select(
                array('categorylinks', 'page'), 'page_id',
                array('cl_from' => $new, 'cl_to=page_title', 'page_namespace' => NS_CATEGORY),
                __METHOD__
            );
            $new = array();
            foreach ($res as $row)
            {
                if (!isset($ids[$row->page_id]))
                {
                    $ids[$row->page_id] = true;
                    $new[] = $row->page_id;
                }
            }
        }
        return array_keys($ids);
    }
}
