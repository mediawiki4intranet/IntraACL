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

class IntraACL_SQL_SD
{
    function getRules($where)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('intraacl_rules', '*', $where, __METHOD__);
        $rows = array();
        while ($r = $res->fetchObject())
        {
            $rows[] = (array)$r;
        }
        return $rows;
    }

    function addRules($rows)
    {
        $dbw = wfGetDB(DB_MASTER);
        $dbw->insert('intraacl_rules', $rows);
    }

    function deleteRules($rows)
    {
        $this->deleteWhere('intraacl_rules', $rows, __METHOD__);
    }

    /**
     * Common format:
     * $rows = array('field' => 'value')
     * or
     * $rows = array(array('f1' => 'v11', 'f2' => 'v12'), array('f1' => 'v21', 'f2' => 'v22'), ...)
     */
    function deleteWhere($table, $rows, $method)
    {
        $dbw = wfGetDB(DB_MASTER);
        if (isset($rows[0]) && is_array($rows[0]))
        {
            // By rows
            $list = array();
            $key = array_keys($rows[0]);
            foreach ($rows as $r)
            {
                $in = array();
                foreach ($key as $k)
                {
                    $in[] = $dbw->addQuotes($r[$k]);
                }
                $in = '('.implode(',', $in).')';
                $list[] = $in;
            }
            $dbw->query(
                'DELETE FROM '.$dbw->tableName($table).' WHERE '.
                '('.implode(',', $key).') IN ('.implode(',', $list).')',
                $method
            );
        }
        else
        {
            // By conditions
            $dbw->delete($table, $rows, $method);
        }
    }



    // OLD METHODS

    /**
     * Retrieves the full hierarchy of SDs from the DB
     */
    public function getFullSDHierarchy()
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('halo_acl_rights_hierarchy', '*', '1', __METHOD__);
        $rows = array();
        foreach ($res as $row)
            $rows[] = $row;
        return $rows;
    }

    /**
     * Select all SD pages (not only saved SDs as incorrect SDs may be not saved),
     * with an additional field:
     *
     *   single_child => NULL or [ peType, peID, pageTitle ] of a single included SD
     */
    public function getSDPages($types, $name, $offset, $limit, &$total)
    {
        global $haclgContLang;
        $dbr = wfGetDB(DB_SLAVE);
        $t = $types ? array_flip(IACLStorage::explode($types)) : NULL;
        $n = str_replace(' ', '_', $name);
        $where = array();
        foreach ($haclgContLang->getPetAliases() as $k => $v)
        {
            if (!$t || array_key_exists($v, $t))
            {
                $where[] = 'CAST(page_title AS CHAR CHARACTER SET utf8) COLLATE utf8_unicode_ci LIKE '.$dbr->addQuotes($k.'/'.$n.'%');
            }
        }
        $where = 'page_namespace='.HACL_NS_ACL.' AND ('.implode(' OR ', $where).')';
        // Select SDs
        $res = $dbr->select('page', '*', $where, __METHOD__, array(
            'SQL_CALC_FOUND_ROWS',
            'ORDER BY' => 'page_title',
            'OFFSET' => $offset,
            'LIMIT' => $limit,
        ));
        $titles = array();
        $rows = array();
        foreach ($res as $row)
        {
            $t = Title::newFromRow($row);
            $titles[] = $t;
            $rows["$t"] = $row;
        }
        if (!$rows)
        {
            return $rows;
        }
        $defs = IACLDefinitions::newFromTitles($titles);
        foreach ($rows as $k => &$t)
        {
            if (isset($defs[$k]))
            {
                $t->single_child = $defs[$k]['single_child'];
                if ($t->single_child)
                {
                    // FIXME Will definitely have problems with ambigious SD titles
                    $name = IACLDefinition::peNameForID($t->single_child[0], $t->single_child[1]);
                    $t->single_child[2] = IACLDefinition::nameOfSD($name, $t->single_child[0]);
                }
            }
        }
        return $rows;
    }

    /**
     * Retrieve the list of content used in article with page_id=$peID
     * from $linkstable = one of 'imagelinks', 'templatelinks', with following info:
     * - count of pages on which it is used
     * - its SD title
     * - modification timestamp its SD, if one does exist, for conflict detection
     * - is its SD a single inclusion of other SD with ID $sdID?
     *   $sdID usually is SD ID for the page $peID as the protection of embedded
     *   content is usually including page SD.
     * with their security descriptors' modification timestamps (if they exist),
     * and the status of these SDs - are they a single inclusion of $sdID ?
     *
     * This is a very high-level subroutine, for optimisation purposes.
     * This method does not return used content with recursion as it used to
     * determine embedded content of only ONE article.
     *
     * @param int $peID
     *      Page ID to retrieve the list of content used in.
     * @param int $sdID
     *      (optional) SD ID to check if used content SDs are a single inclusion of $sdID.
     * @param $linkstable
     *      'imagelinks' (retrieve used images) or 'templatelinks' (retrieve used templates).
     * @return array(array('title' => , 'sd_touched' => , 'single' => ))
     */
    public function getEmbedded($peID, $sdID, $linkstable)
    {
        $dbr = wfGetDB(DB_SLAVE);
        if ($linkstable == 'imagelinks')
        {
            $linksjoin = "p1.page_title=il1.il_to AND p1.page_namespace=".NS_FILE;
            $linksfield = "il_from";
        }
        elseif ($linkstable == 'templatelinks')
        {
            $linksjoin = "p1.page_title=il1.tl_title AND p1.page_namespace=il1.tl_namespace";
            $linksfield = "tl_from";
        }
        else
            die("Unknown \$linkstable='$linkstable' passed to ".__METHOD__);
        $linksjoin2 = str_replace('il1.', 'il2.', $linksjoin);
        $il = $dbr->tableName($linkstable);
        $p  = $dbr->tableName('page');
        $sd = $dbr->tableName('halo_acl_security_descriptors');
        $r  = $dbr->tableName('halo_acl_rights');
        $rh = $dbr->tableName('halo_acl_rights_hierarchy');
        $rev = $dbr->tableName('revision');
        $sql_is_single = $sdID ?
                "(SELECT 1=SUM(CASE WHEN child_id=$sdID THEN 1 ELSE 2 END)
                  FROM $rh rh WHERE rh.parent_right_id=sd.sd_id)" : "0";
        $sql = "SELECT p1.*, p2.page_title sd_title, rev.rev_timestamp sd_touched,
                 $sql_is_single sd_inc_single,
                 (NOT EXISTS (SELECT * FROM $r r WHERE r.origin_id=sd.sd_id)) sd_no_rights,
                 (COUNT(il2.$linksfield)) used_on_pages
                FROM $il il1 INNER JOIN $p p1 ON $linksjoin
                LEFT JOIN $sd sd ON sd.type='page' AND sd.pe_id=p1.page_id
                LEFT JOIN $p p2 ON p2.page_id=sd.sd_id
                LEFT JOIN $rev rev ON rev.rev_id=p2.page_latest
                LEFT JOIN $il il2 ON $linksjoin2
                WHERE il1.$linksfield=$peID
                GROUP BY p1.page_id
                ORDER BY p1.page_namespace, p1.page_title";
        $res = $dbr->query($sql, __METHOD__);
        $embedded = array();
        foreach ($res as $obj)
        {
            $embedded[] = array(
                'title' => Title::newFromRow($obj),
                'sd_title' => $obj->sd_title ? Title::makeTitleSafe(HACL_NS_ACL, $obj->sd_title) : NULL,
                // Modification timestamp of an SD
                'sd_touched' => $obj->sd_touched,
                // Is SD a single inclusion of $sdID?
                'sd_single' => $obj->sd_inc_single && $obj->sd_no_rights,
                // Count of pages on which it is used
                'used_on_pages' => $obj->used_on_pages,
            );
        }
        return $embedded;
    }

    /**
     * Retrieve the category SDs of categories $title belongs to,
     * including parent ones.
     */
    public function getParentCategorySDs($title)
    {
        $id = $title->getArticleId();
        if (!$id)
            return array();
        // First retrieve IDs of categories which have corresponding page
        $dbr = wfGetDB(DB_SLAVE);
        $catids = array();
        $ids = array($id => true);
        while ($ids)
        {
            $res = $dbr->select(
                array('categorylinks', 'page'), 'page_id',
                array(
                    'cl_from' => array_keys($ids),
                    'page_namespace' => NS_CATEGORY,
                    'page_title=cl_to',
                ), __METHOD__
            );
            $ids = array();
            foreach ($res as $row)
            {
                $row = $row->page_id;
                if (!isset($catids[$row]))
                    $ids[$row] = $catids[$row] = true;
            }
        }
        // Then retrieve their SDs if they exist
        if (!$catids)
            return array();
        $res = $dbr->select(
            array('p' => 'page', 'halo_acl_security_descriptors'), 'p.*',
            array('page_id=sd_id', 'pe_id' => array_keys($catids), 'type' => HACLLanguage::PET_CATEGORY),
            __METHOD__
        );
        $prot = array();
        foreach ($res as $row)
            $prot[] = Title::newFromRow($row);
        return $prot;
    }
}
