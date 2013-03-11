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

class IntraACL_SQL_IR
{
    /**
     * Saves the given inline right in the database.
     *
     * @param HACLRight $right
     *         This object defines the inline right that wil be saved.
     *
     * @return int
     *         The ID of an inline right is determined by the database (AUTO INCREMENT).
     *         The new ID is returned.
     *
     * @throws
     *         Exception
     *
     */
    public function saveRight(HACLRight $right) {
        $dbw = wfGetDB( DB_MASTER );

        $groups = implode(',', $right->getGroups());
        $users  = implode(',', $right->getUsers());
        $rightID = $right->getRightID();
        $setValues = array(
            'actions'     => $right->getActions(),
            'groups'      => $groups,
            'users'       => $users,
            'description' => $right->getDescription(),
            'name'        => $right->getName(),
            'origin_id'   => $right->getOriginID());
        if ($rightID == -1) {
            // right does not exist yet in the DB.
            $dbw->insert('halo_acl_rights', $setValues);
            // retrieve the auto-incremented ID of the right
            $rightID = $dbw->insertId();
        } else {
            $setValues['right_id'] = $rightID;
            $dbw->replace('halo_acl_rights', NULL, $setValues);
        }

        return $rightID;
    }

    /**
     * Retrieves the description of the inline right with the ID $rightID from
     * the database.
     *
     * @param int $rightID
     *         ID of the requested inline right.
     *
     * @return HACLRight
     *         A new inline right object or <NULL> if there is no such right in the
     *         database.
     *
     */
    public function getRightByID($rightID)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('halo_acl_rights', '*', array('right_id' => $rightID), __METHOD__);
        if ($dbr->numRows($res) == 1)
            return $this->rowToRight($dbr->fetchObject($res));
        return NULL;
    }

    /**
     * Converts DB row fetched with fetchObject() into HACLRight object
     */
    public function rowToRight($row)
    {
        if (!$row)
            return NULL;
        $rightID     = $row->right_id;
        $actions     = $row->actions;
        $groups      = IACLStorage::explode($row->groups);
        $users       = IACLStorage::explode($row->users);
        $description = $row->description;
        $name        = $row->name;
        $originID    = $row->origin_id;
        $sd = new HACLRight($actions, $groups, $users, $description, $name, $originID);
        $sd->setRightID($rightID);
        return $sd;
    }

    /**
     * Returns the IDs of all inline rights for the protected element with the
     * ID $peID that have the protection type $type and match the action $actionID.
     *
     * @param  int $peID
     *         ID of the protected element
     * @param  string $type
     *         Type of the protected element: One of
     *         HACLLanguage::PET_*
     *
     * @param  int $actionID
     *         ID of the action. One of
     *         HACLLanguage::RIGHT_*
     *
     * @return array(HACLRight)
     *         An array of IDs of rights that match the given constraints.
     */
    public function getRights($peID, $type, $actionID, $originNotEqual = NULL)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $rt = $dbr->tableName('halo_acl_rights');
        $rpet = $dbr->tableName('halo_acl_pe_rights');

        $sql = "SELECT rights.* FROM $rt AS rights, $rpet AS pe ".
            "WHERE pe.pe_id = $peID AND pe.type = '$type' AND ".
            "rights.right_id = pe.right_id AND".
            "(rights.actions & $actionID) != 0";
        if ($originNotEqual)
        {
            if (!is_array($originNotEqual))
                $sql .= " AND origin_id!=".intval($originNotEqual);
            else
            {
                foreach($originNotEqual as &$o)
                    $o = intval($o);
                $sql .= " AND origin_id NOT IN (".implode(", ", $originNotEqual).")";
            }
        }

        $res = $dbr->query($sql, __METHOD__);
        $rights = array();
        foreach ($res as $row)
            $rights[] = $this->rowToRight($row);

        return $rights;
    }

    /**
     * Retrieves all rights for a set of security descriptors,
     * or for ALL security descriptors if their IDs are omitted
     * @return array(SDid => array(HACLRight))
     */
    public function getAllRights($sdids = NULL)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('halo_acl_rights', '*', $sdids ? array('origin_id' => $sdids) : '1', __METHOD__);
        $rights = array();
        foreach ($res as $row)
            $rights[$row->origin_id][] = $this->rowToRight($row);
        return $rights;
    }

    /**
     * Reverse-lookup for rights. Determines for which protected elements
     * action $actionID is granted to one of users $users or one of groups $groups,
     * without expanding groups.
     */
    public function lookupRights($users, $groups, $actionID, $pe_type)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $tp = $dbr->tableName('halo_acl_pe_rights');
        $tr = $dbr->tableName('halo_acl_rights');
        if ($users !== NULL && !is_array($users))
            $users = array($users);
        if ($groups && !is_array($groups))
            $groups = array($groups);
        $where = array();
        if ($users)
            $where[] = "r.users REGEXP ".$dbr->addQuotes('(,|^)('.implode('|', $users).')(,|$)');
        if ($groups)
            $where[] = "r.groups REGEXP ".$dbr->addQuotes('(,|^)('.implode('|', $groups).')(,|$)');
        $where = $where ? array('('.implode(' OR ', $where).')') : array();
        $where[] = 'p.right_id=r.right_id';
        $where[] = '(r.actions&'.intval($actionID).')!=0';
        if ($pe_type)
            $where[] = 'p.type='.$dbr->addQuotes($pe_type);
        $where = implode(' AND ', $where);
        $sql = "SELECT p.type, p.pe_id FROM $tp p, $tr r WHERE $where GROUP BY p.type, p.pe_id";
        $res = $dbr->query($sql, __METHOD__);
        $r = array();
        foreach ($res as $row)
            $r[] = array($row->type, $row->pe_id);
        return $r;
    }

    /**
     * Deletes the inline right with the ID $rightID from the database. All
     * references to the right (from protected elements) are deleted as well.
     *
     * @param int $rightID
     *         ID of the right that is removed from the database.
     *
     */
    public function deleteRight($rightID) {
        $dbw = wfGetDB( DB_MASTER );

        // Delete the right from the definition of rights in halo_acl_rights
        $dbw->delete('halo_acl_rights', array('right_id' => $rightID), __METHOD__);

        // Delete all references to the right from protected elements
        $dbw->delete('halo_acl_pe_rights', array('right_id' => $rightID), __METHOD__);
    }

}
