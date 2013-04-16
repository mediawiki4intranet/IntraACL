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
    /**
     * Saves the given SD in the database.
     *
     * @param HACLSecurityDescriptor $sd
     *         This object defines the SD that wil be saved.
     *
     * @throws
     *         Exception
     *
     */
    public function saveSD(HACLSecurityDescriptor $sd)
    {
        $dbw = wfGetDB(DB_MASTER);
        $mgGroups = implode(',', $sd->getManageGroups());
        $mgUsers = implode(',', $sd->getManageUsers());
        $dbw->replace('halo_acl_security_descriptors', NULL, array(
            'sd_id'     => $sd->getSDID(),
            'pe_id'     => $sd->getPEID(),
            'type'      => $sd->getPEType(),
            'mr_groups' => $mgGroups,
            'mr_users'  => $mgUsers), __METHOD__);
    }

    /**
     * Adds a predefined right to a security descriptor or a predefined right.
     *
     * The table "halo_acl_rights_hierarchy" stores the hierarchy of rights. There
     * is a tuple for each parent-child relationship.
     *
     * @param int $parentRightID
     *         ID of the parent right or security descriptor
     * @param int $childRightID
     *         ID of the right that is added as child
     * @throws
     *         Exception
     *         ... on database failure
     */
    public function addRightToSD($parentRightID, $childRightID)
    {
        $dbw = wfGetDB(DB_MASTER);
        $dbw->replace('halo_acl_rights_hierarchy', NULL, array(
            'parent_right_id' => $parentRightID,
            'child_id'        => $childRightID), __METHOD__);
    }

    /**
     * Adds the given inline rights to the protected elements of the given
     * security descriptors.
     *
     * The table "halo_acl_pe_rights" stores for each protected element (e.g. a
     * page) its type of protection and the IDs of all inline rights that are
     * assigned.
     *
     * @param array<int> $inlineRights
     *         This is an array of IDs of inline rights. All these rights are
     *         assigned to all given protected elements.
     * @param array<int> $securityDescriptors
     *         This is an array of IDs of security descriptors that protect elements.
     * @throws
     *         Exception
     *         ... on database failure
     */
    public function setInlineRightsForProtectedElements($ir_ids, $sd_ids)
    {
        $dbw = wfGetDB(DB_MASTER);
        foreach ($sd_ids as $sd)
        {
            // retrieve the protected element and its type
            $obj = $dbw->selectRow('halo_acl_security_descriptors', 'pe_id, type', array('sd_id' => $sd), __METHOD__);
            if (!$obj)
                continue;
            foreach ($ir_ids as $ir)
            {
                $dbw->replace('halo_acl_pe_rights', NULL, array(
                    'pe_id'    => $obj->pe_id,
                    'type'     => $obj->type,
                    'right_id' => $ir), __METHOD__);
            }
        }
    }

    /**
     * Returns all direct inline rights of all given security
     * descriptor IDs.
     *
     * @param array<int> $sdIDs
     *         Array of security descriptor IDs.
     * @param boolean $asObject
     *         If true, return an array of HACLRight objects.
     *         If false, return an array of right IDs.
     *
     * @return array<int>
     *         An array of inline right IDs or HACLRight objects.
     */
    public function getInlineRightsOfSDs($sdIDs, $asObject = false)
    {
        if (empty($sdIDs))
            return array();
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(
            'halo_acl_rights', '*',
            array('origin_id' => $sdIDs), __METHOD__
        );

        $irs = array();
        while ($row = $dbr->fetchObject($res))
            $irs[] = $asObject ? IACLStorage::get('IR')->rowToRight($row) : (int)$row->right_id;
        return $irs;
    }

    /**
     * Returns the IDs of all predefined rights of the given security
     * descriptor ID.
     *
     * @param int $sdID
     *         ID of the security descriptor.
     * @param bool $recursively
     *         <true>: The whole hierarchy of rights is returned.
     *         <false>: Only the direct rights of this SD are returned.
     *
     * @return array<int>
     *         An array of predefined right IDs without duplicates.
     */
    public function getPredefinedRightsOfSD($sdID, $recursively) {
        $dbr = wfGetDB( DB_SLAVE );

        $parentIDs = array($sdID);
        $childIDs = array();
        $exclude = array();
        while (true) {
            if (empty($parentIDs)) {
                break;
            }
            $res = $dbr->select(
                'halo_acl_rights_hierarchy', 'child_id',
                array('parent_right_id' => $parentIDs), __METHOD__,
                array('DISTINCT')
            );

            $exclude = array_merge($exclude, $parentIDs);
            $parentIDs = array();

            while ($row = $dbr->fetchObject($res)) {
                $cid = (int) $row->child_id;
                if (!in_array($cid, $childIDs)) {
                    $childIDs[] = $cid;
                }
                if (!in_array($cid, $exclude)) {
                    // Add a new parent for the next level in the hierarchy
                    $parentIDs[] = $cid;
                }
            }
            $numRows = $dbr->numRows($res);
            $dbr->freeResult($res);
            if ($numRows == 0 || !$recursively) {
                // No further children found
                break;
            }
        }
        return $childIDs;
    }

    /**
     * Finds all (real) security descriptors that are related to the given
     * predefined right. The IDs of all SDs that include this right (via the
     * hierarchy of rights) are returned.
     *
     * @param  int $prID
     *         IDs of the protected right
     *
     * @return array<int>
     *         An array of IDs of all SD that include the PR via the hierarchy
     *         of PRs.
     */
    public function getSDsIncludingPR($prID)
    {
        $dbr = wfGetDB(DB_SLAVE);

        $result = array($prID => true);
        $childIDs = array($prID);
        while ($childIDs)
        {
            $res = $dbr->select(
                'halo_acl_rights_hierarchy', 'parent_right_id',
                array('child_id' => $childIDs), __METHOD__,
                array('DISTINCT')
            );
            $childIDs = array();
            foreach ($res as $row)
            {
                $prid = (int)$row->parent_right_id;
                if (!$result[$prid])
                {
                    $childIDs[] = $prid;
                    $result[$prid] = true;
                }
            }
        }

        return array_keys($result);
    }

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
     * Retrieves the SD object(s) with the ID(s) $SDID from the database.
     *
     * @param  int $SDID
     *         ID of the requested SD.
     *         Optionally an array of SD ids.
     *
     * @return HACLSecurityDescriptor
     *         A new SD object or <NULL> if there is no such SD in the
     *         database.
     *         If $SDID is an array, then return value will also be an array
     *         with SD objects in the preserved order.
     */
    public function getSDByID($SDID)
    {
        if (!$SDID)
            return is_array($SDID) ? array() : NULL;
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(
            array('sd' => 'halo_acl_security_descriptors', 'page'),
            'sd.*, page_title',
            array('sd_id' => $SDID, 'page_id=sd_id'),
            __METHOD__
        );
        if (!is_array($SDID))
            return $this->rowToSD($dbr->fetchObject($res));
        elseif (is_array($SDID))
        {
            $byid = array();
            foreach ($res as $row)
            {
                $sd = $this->rowToSD($row);
                $byid[$sd->getSDId()] = $sd;
            }
            $r = array();
            foreach ($SDID as $id)
                if (isset($byid[$id]))
                    $r[] = $byid[$id];
            return $r;
        }
        return NULL;
    }

    /* Create HACLSecurityDescriptor from DB row object */
    public function rowToSD($row)
    {
        if (!$row)
            return NULL;
        if (!$row->page_title)
            $row->page_title = HACLSecurityDescriptor::nameForID($sdID);
        return new HACLSecurityDescriptor(
            (int)$row->sd_id,
            str_replace('_', ' ', $row->page_title),
            (int)$row->pe_id,
            $row->type,
            IACLStorage::explode($row->mr_groups),
            IACLStorage::explode($row->mr_users)
        );
    }

    /**
     * Deletes the SD with the ID $SDID from the database. The right remains as
     * child in the hierarchy of rights, as it is still defined as child in the
     * articles that define its parents.
     *
     * @param int $SDID
     *         ID of the SD that is removed from the database.
     * @param bool $rightsOnly
     *         If <true>, only the rights that $SDID contains are deleted from
     *         the hierarchy of rights, but $SDID is not removed.
     *         If <false>, the complete $SDID is removed (but remains as child
     *         in the hierarchy of rights).
     *
     */
    public function deleteSD($SDID, $rightsOnly = false)
    {
        $dbw = wfGetDB(DB_MASTER);
        wfDebug("-- deleteSD $SDID $rightsOnly\n");

        // Delete all inline rights that are defined by the SD (and the
        // references to them)
        $irs = $this->getInlineRightsOfSDs($SDID);
        foreach ($irs as $ir)
            IACLStorage::get('IR')->deleteRight($ir);

        // Remove all inline rights from the hierarchy below $SDID from their
        // protected elements. This may remove too many rights => the parents
        // of $SDID must materialize their rights again
        $prs = $this->getPredefinedRightsOfSD($SDID, true);
        $irs = $this->getInlineRightsOfSDs($prs);

        $parents = $this->getSDsIncludingPR($SDID);
        if (!empty($irs))
        {
            $sds = $parents;
            foreach ($sds as $sd)
            {
                // retrieve the protected element and its type
                $obj = $dbw->selectRow('halo_acl_security_descriptors', 'pe_id, type',
                    array('sd_id' => $sd), __METHOD__);
                if (!$obj)
                    continue;

                $dbw->delete('halo_acl_pe_rights', array(
                    'right_id' => $irs,
                    'pe_id' => $obj->pe_id,
                    'type' => $obj->type), __METHOD__);
            }
        }

        // Delete the SD from the hierarchy of rights in halo_acl_rights_hierarchy
        //if (!$rightsOnly)
        //    $dbw->delete('halo_acl_rights_hierarchy', array('child_id' => $SDID));
        $dbw->delete('halo_acl_rights_hierarchy', array('parent_right_id' => $SDID), __METHOD__);

        // Rematerialize the rights of the parents of $SDID
        foreach ($parents as $p)
        {
            if ($p != $SDID &&
                ($sd = HACLSecurityDescriptor::newFromID($p, false)))
            {
                $sd->materializeRightsHierarchy();
            }
        }

        // Delete definition of SD from halo_acl_security_descriptors
        if (!$rightsOnly)
            $dbw->delete('halo_acl_security_descriptors', array('sd_id' => $SDID), __METHOD__);
    }

    /**
     * Checks if the SD with the ID $sdID exists in the database.
     *
     * @param int $sdID
     *         ID of the SD
     *
     * @return bool
     *         <true> if the SD exists
     *         <false> otherwise
     */
    public function sdExists($sdID) {
        $dbr = wfGetDB( DB_SLAVE );
        $obj = $dbr->selectRow('halo_acl_security_descriptors', 'sd_id',
            array('sd_id' => $sdID), __METHOD__);
        return ($obj !== false);
    }

    /**
     * Tries to find the ID of the security descriptor for the protected element
     * with the ID $peID.
     *
     * @param int $peID
     *         ID of the protected element
     * @param int $peType
     *         Type of the protected element
     *
     * @return mixed int|bool
     *         int: ID of the security descriptor
     *         <false>, if there is no SD for the protected element
     */
    public function getSDForPE($peID, $peType)
    {
        $dbr = wfGetDB( DB_SLAVE );
        $obj = $dbr->selectRow('halo_acl_security_descriptors', 'sd_id',
            array('pe_id' => $peID, 'type' => $peType), __METHOD__);
        return ($obj === false) ? false : $obj->sd_id;
    }

    /**
     * Retrieves security descriptors from the database
     */
    public function getSDs2($type = NULL, $prefix = NULL, $limit = NULL, $as_object = true)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $options = array('ORDER BY' => 'page_title');
        if ($limit)
            $options['LIMIT'] = $limit;
        $where = array('sd_id=page_id');
        if ($type !== NULL)
            $where['type'] = $type;
        if (strlen($prefix))
            $where[] = 'page_title LIKE '.$dbr->addQuotes('%'.str_replace(' ', '_', $prefix).'%');
        $res = $dbr->select(array('halo_acl_security_descriptors', 'page'),
            'sd_id, pe_id, type, mr_groups, mr_users, page_namespace, page_title',
            $where, __METHOD__,
            $options
        );
        $rights = array();
        foreach ($res as $r)
        {
            if ($as_object)
                $rights[] = new HACLSecurityDescriptor(
                    $r->sd_id, $r->page_title, $r->pe_id,
                    $r->type, $r->mr_groups ? $r->mr_groups : array(),
                    $r->mr_users ? $r->mr_users : array()
                );
            else
                $rights[] = $r;
        }
        return $rights;
    }

    /**
     * Select all SD pages (not only saved SDs as incorrect SDs may be not saved),
     * with additional fields:
     * - sd_single_id is ID of the only one included predefined right.
     * - sd_single_title is page title of this PR.
     * - sd_no_rights is true, if SD has no direct inline rights.
     * I.e. when sd_no_rights is true, non-NULL sd_single_id means that SD
     * contains only one predefined right inclusion.
     */
    public function getSDPages($types, $name, $offset, $limit, &$total)
    {
        global $haclgContLang;
        $dbr = wfGetDB(DB_SLAVE);
        $t = $types ? array_flip(IACLStorage::explode($types)) : NULL;
        $n = str_replace(' ', '_', $name);
        $where = array();
        foreach ($haclgContLang->getPetAliases() as $k => $v)
            if (!$t || array_key_exists($v, $t))
                $where[] = 'CAST(page_title AS CHAR CHARACTER SET utf8) COLLATE utf8_unicode_ci LIKE '.$dbr->addQuotes($k.'/'.$n.'%');
        $where = 'page_namespace='.HACL_NS_ACL.' AND ('.implode(' OR ', $where).')';
        // Select SDs
        $res = $dbr->select('page', '*', $where, __METHOD__, array(
            'SQL_CALC_FOUND_ROWS',
            'ORDER BY' => 'page_title',
            'OFFSET' => $offset,
            'LIMIT' => $limit,
        ));
        $rows = array();
        foreach ($res as $row)
        {
            $row->sd_single_title = NULL;
            $rows[$row->page_id] = $row;
        }
        if (!$rows)
            return $rows;
        // Select total page count
        $res = $dbr->query('SELECT FOUND_ROWS()', __METHOD__);
        $total = $res->fetchRow();
        $total = $total[0];
        // Select single-inclusion information
        $res = $dbr->select(array('i' => 'halo_acl_rights_hierarchy', 'r' => 'halo_acl_rights', 'p' => 'page'),
            'i.parent_right_id, p.*',
            array('r.origin_id IS NULL', 'i.parent_right_id' => array_keys($rows)),
            __METHOD__,
            array('GROUP BY' => 'i.parent_right_id', 'HAVING' => 'COUNT(i.child_id)=1'),
            array(
                'r' => array('LEFT JOIN', array('r.origin_id=i.parent_right_id')),
                'p' => array('INNER JOIN', array('p.page_id=i.child_id'))
            )
        );
        foreach ($res as $row)
            $rows[$row->parent_right_id]->sd_single_title = Title::newFromRow($row);
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
