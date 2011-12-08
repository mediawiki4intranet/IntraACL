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
 * This class encapsulates all methods that care about the database tables of
 * the IntraACL extension. This is the implementation for the SQL database.
 * @author Thomas Schweitzer
 */
class HACLStorageSQL {

    /**
     * Initializes the database tables of the IntraACL extensions.
     * These are:
     * - halo_acl_pe_rights:
     *         table of materialized inline rights for each protected element
     * - halo_acl_rights:
     *         description of each inline right
     * - halo_acl_rights_hierarchy:
     *         holds predefined rights inclusions
     * - halo_acl_security_descriptors:
     *         table for security descriptors and predefined rights
     * - halo_acl_groups:
     *         stores the ACL groups
     * - halo_acl_group_members:
     *         stores the hierarchy of groups and their users
     */
    public function initDatabaseTables()
    {
        $dbw = wfGetDB( DB_MASTER );

        $verbose = true;

        // halo_acl_rights:
        //        description of each inline right
        $table = $dbw->tableName('halo_acl_rights');

        HACLDBHelper::setupTable($table, array(
            'right_id'         => 'INT(8) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'actions'          => 'INT(8) NOT NULL',
            'groups'           => 'Text CHARACTER SET utf8 COLLATE utf8_bin',
            'users'            => 'Text CHARACTER SET utf8 COLLATE utf8_bin',
            'description'      => 'Text CHARACTER SET utf8 COLLATE utf8_bin',
            'name'             => 'Text CHARACTER SET utf8 COLLATE utf8_bin',
            'origin_id'        => 'INT(8) UNSIGNED NOT NULL'),
        $dbw, $verbose);
        HACLDBHelper::reportProgress("   ... done!\n",$verbose);

        // halo_acl_pe_rights:
        //         table of materialized inline rights for each protected element
        $table = $dbw->tableName('halo_acl_pe_rights');

        HACLDBHelper::setupTable($table, array(
            'pe_id'    => 'INT(8) NOT NULL',
            'type'     => "ENUM('category','page','namespace','property') DEFAULT 'page' NOT NULL",
            'right_id' => 'INT(8) UNSIGNED NOT NULL'),
        $dbw, $verbose, "pe_id,type,right_id");
        HACLDBHelper::reportProgress("   ... done!\n",$verbose);

        // halo_acl_rights_hierarchy:
        //        hierarchy of predefined rights
        $table = $dbw->tableName('halo_acl_rights_hierarchy');

        HACLDBHelper::setupTable($table, array(
            'parent_right_id' => 'INT(8) UNSIGNED NOT NULL',
            'child_id'        => 'INT(8) UNSIGNED NOT NULL'),
        $dbw, $verbose, "parent_right_id,child_id");
        HACLDBHelper::reportProgress("   ... done!\n",$verbose, "parent_right_id, child_id");

        // halo_acl_security_descriptors:
        //        table for security descriptors and predefined rights
        $table = $dbw->tableName('halo_acl_security_descriptors');

        HACLDBHelper::setupTable($table, array(
            'sd_id'     => 'INT(8) UNSIGNED NOT NULL',
            'pe_id'     => 'INT(8)',
            'type'      => "ENUM('category','page','namespace','property','right') DEFAULT 'page' NOT NULL",
            'mr_groups' => 'TEXT CHARACTER SET utf8 COLLATE utf8_bin',
            'mr_users'  => 'TEXT CHARACTER SET utf8 COLLATE utf8_bin'),
        $dbw, $verbose, 'sd_id');
        HACLDBHelper::reportProgress("   ... done!\n",$verbose);

        // halo_acl_groups:
        //        stores the ACL groups
        $table = $dbw->tableName('halo_acl_groups');

        HACLDBHelper::setupTable($table, array(
            'group_id'   => 'INT(8) UNSIGNED NOT NULL',
            'group_name' => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL',
            'mg_groups'  => 'TEXT CHARACTER SET utf8 COLLATE utf8_bin',
            'mg_users'   => 'TEXT CHARACTER SET utf8 COLLATE utf8_bin'),
        $dbw, $verbose, 'group_id');
        HACLDBHelper::reportProgress("   ... done!\n",$verbose);

        // halo_acl_group_members:
        //        stores the hierarchy of groups and their users
        $table = $dbw->tableName('halo_acl_group_members');

        HACLDBHelper::setupTable($table, array(
            'parent_group_id'     => 'INT(8) UNSIGNED NOT NULL',
            'child_type'          => 'ENUM(\'group\', \'user\') DEFAULT \'user\' NOT NULL',
            'child_id'            => 'INT(8) NOT NULL'),
        $dbw, $verbose, "parent_group_id,child_type,child_id");
        HACLDBHelper::reportProgress("   ... done!\n",$verbose, "parent_group_id, child_type, child_id");

        // halo_acl_special_pages:
        //        stores the IDs of special pages that have no article ID
        $table = $dbw->tableName('halo_acl_special_pages');

        HACLDBHelper::setupTable($table, array(
            'id'     => 'INT(8) NOT NULL AUTO_INCREMENT',
            'name'   => 'VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL'),
        $dbw, $verbose, "id,name");
        HACLDBHelper::reportProgress("   ... done!\n",$verbose, "id,name");

        // halo_acl_quickacl:
        //        stores quick ACL lists for users
        $table = $dbw->tableName('halo_acl_quickacl');

        HACLDBHelper::setupTable($table, array(
            'sd_id'      => 'INT(8) NOT NULL',
            'user_id'    => 'INT(10) NOT NULL',
            'qa_default' => 'TINYINT(1) NOT NULL'),
        $dbw, $verbose, "sd_id,user_id");
        HACLDBHelper::reportProgress("   ... done!\n",$verbose, "sd_id,user_id");

        return true;
    }

    public function dropDatabaseTables() {
        global $wgDBtype;
        $verbose = true;

        HACLDBHelper::reportProgress("Deleting all database content and tables generated by IntraACL ...\n\n",$verbose);
        $dbw = wfGetDB( DB_MASTER );
        $tables = array(
            'halo_acl_rights',
            'halo_acl_pe_rights',
            'halo_acl_rights_hierarchy',
            'halo_acl_security_descriptors',
            'halo_acl_groups',
            'halo_acl_group_members',
            'halo_acl_special_pages',
            'halo_acl_quickacl');
        foreach ($tables as $table) {
            $name = $dbw->tableName($table);
            $dbw->query('DROP TABLE ' . ($wgDBtype == 'postgres' ? '' : ' IF EXISTS') . $name, __METHOD__);
            HACLDBHelper::reportProgress(" ... dropped table $name.\n", $verbose);
        }
        HACLDBHelper::reportProgress("All data removed successfully.\n",$verbose);
    }

    /***************************************************************************
     *
     * Functions for groups
     *
     **************************************************************************/

    /**
     * Returns the name of the group with the ID $groupID.
     *
     * @param int $groupID
     *         ID of the group whose name is requested
     *
     * @return string
     *         Name of the group with the given ID or <NULL> if there is no such
     *         group defined in the database.
     */
    public function groupNameForID($groupID)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $groupName = $dbr->selectField('halo_acl_groups', 'group_name', array('group_id' => $groupID), __METHOD__);
        return $groupName;
    }

    /**
     * Saves the given group in the database.
     * @param HACLGroup $group
     *        This object defines the group that wil be saved.
     */
    public function saveGroup(HACLGroup $group)
    {
        $dbw = wfGetDB(DB_MASTER);
        $mgGroups = implode(',', $group->getManageGroups());
        $mgUsers = implode(',', $group->getManageUsers());
        $dbw->replace(
            'halo_acl_groups', NULL, array(
                'group_id'   => $group->getGroupID(),
                'group_name' => $group->getGroupName(),
                'mg_groups'  => $mgGroups,
                'mg_users'   => $mgUsers
            ), __METHOD__
        );
    }

    /**
     * Retrieves all groups from the database.
     * [name contains $text]
     * [name does not contain $nottext]
     * [maximum $limit]
     *
     * @return Array
     *         Array of Group Objects
     */
    public function getGroups($text = NULL, $nottext = NULL, $limit = NULL, $as_object = false)
    {
        $dbr = wfGetDB(DB_SLAVE);

        $options = array('ORDER BY' => 'group_name');
        if ($limit !== NULL)
            $options['LIMIT'] = $limit;
        $where = array();
        if (strlen($text))
            $where[] = 'group_name LIKE '.$dbr->addQuotes("%$text%");
        if (strlen($nottext))
            $where[] = 'group_name NOT LIKE '.$dbr->addQuotes("%$nottext%");
        $res = $dbr->select('halo_acl_groups', '*', $where, __METHOD__, $options);

        $groups = array();
        if ($as_object)
        {
            while ($row = $dbr->fetchObject($res))
            {
                $groupID = $row->group_id;
                $groupName = $row->group_name;
                $mgGroups = self::strToIntArray($row->mg_groups);
                $mgUsers = self::strToIntArray($row->mg_users);
                $groups[] = new HACLGroup($groupID, $groupName, $mgGroups, $mgUsers);
            }
        }
        else
        {
            while ($row = $dbr->fetchRow($res))
                $groups[] = $row;
        }
        return $groups;
    }

    /**
     * Retrieves the description of the group with the name $groupName from
     * the database.
     *
     * @param string $groupName
     *         Name of the requested group.
     *
     * @return HACLGroup
     *         A new group object or <NULL> if there is no such group in the
     *         database.
     *
     */
    public function getGroupByName($groupName) {
        $dbr = wfGetDB( DB_SLAVE );
        $gt = $dbr->tableName('halo_acl_groups');
        $group = NULL;

        $res = $dbr->select('halo_acl_groups', '*', array('group_name' => $groupName), __METHOD__);

        if ($dbr->numRows($res) == 1) {
            $row = $dbr->fetchObject($res);
            $groupID = $row->group_id;
            $mgGroups = self::strToIntArray($row->mg_groups);
            $mgUsers  = self::strToIntArray($row->mg_users);

            $group = new HACLGroup($groupID, $groupName, $mgGroups, $mgUsers);
        }
        $dbr->freeResult($res);

        return $group;
    }

    /**
     * Retrieves the description of the group with the ID $groupID from
     * the database.
     *
     * @param int $groupID
     *         ID of the requested group.
     *
     * @return HACLGroup
     *         A new group object or <NULL> if there is no such group in the
     *         database.
     *
     */
    public function getGroupByID($groupID) {
        $dbr = wfGetDB( DB_SLAVE );
        $group = NULL;

        $res = $dbr->select('halo_acl_groups', '*', array('group_id' => $groupID), __METHOD__);

        if ($dbr->numRows($res) == 1) {
            $row = $dbr->fetchObject($res);
            $groupID = $row->group_id;
            $groupName = $row->group_name;
            $mgGroups = self::strToIntArray($row->mg_groups);
            $mgUsers  = self::strToIntArray($row->mg_users);

            $group = new HACLGroup($groupID, $groupName, $mgGroups, $mgUsers);
        }
        $dbr->freeResult($res);

        return $group;
    }

    /**
     * Adds the user with the ID $userID to the group with the ID $groupID.
     *
     * @param int $groupID
     *         The ID of the group to which the user is added.
     * @param int $userID
     *         The ID of the user who is added to the group.
     *
     */
    public function addUserToGroup($groupID, $userID) {
        $dbw = wfGetDB( DB_MASTER );

        $dbw->replace('halo_acl_group_members', NULL, array(
            'parent_group_id' => $groupID,
            'child_type'      => 'user',
            'child_id '       => $userID), __METHOD__);
    }

    /**
     * Adds the group with the ID $childGroupID to the group with the ID
     * $parentGroupID.
     *
     * @param $parentGroupID
     *         The group with this ID gets the new child with the ID $childGroupID.
     * @param $childGroupID
     *         The group with this ID is added as child to the group with the ID
     *      $parentGroup.
     *
     */
    public function addGroupToGroup($parentGroupID, $childGroupID) {
        $dbw = wfGetDB( DB_MASTER );

        $dbw->replace('halo_acl_group_members', NULL, array(
            'parent_group_id' => $parentGroupID,
            'child_type'      => 'group',
            'child_id '       => $childGroupID), __METHOD__);
    }

    /**
     * Removes the user with the ID $userID from the group with the ID $groupID.
     *
     * @param $groupID
     *         The ID of the group from which the user is removed.
     * @param int $userID
     *         The ID of the user who is removed from the group.
     *
     */
    public function removeUserFromGroup($groupID, $userID) {
        $dbw = wfGetDB( DB_MASTER );

        $dbw->delete('halo_acl_group_members', array(
            'parent_group_id' => $groupID,
            'child_type'      => 'user',
            'child_id '       => $userID), __METHOD__);
    }

    /**
     * Removes all members from the group with the ID $groupID.
     *
     * @param $groupID
     *         The ID of the group from which the user is removed.
     *
     */
    public function removeAllMembersFromGroup($groupID) {
        $dbw = wfGetDB( DB_MASTER );
        $dbw->delete('halo_acl_group_members', array('parent_group_id' => $groupID), __METHOD__);
    }

    /**
     * Removes the group with the ID $childGroupID from the group with the ID
     * $parentGroupID.
     *
     * @param $parentGroupID
     *         This group loses its child $childGroupID.
     * @param $childGroupID
     *         This group is removed from $parentGroupID.
     *
     */
    public function removeGroupFromGroup($parentGroupID, $childGroupID) {
        $dbw = wfGetDB( DB_MASTER );
        $dbw->delete('halo_acl_group_members', array(
            'parent_group_id' => $parentGroupID,
            'child_type'      => 'group',
            'child_id '       => $childGroupID), __METHOD__);
    }

    /**
     * Returns the IDs of all users or groups that are a member of the group
     * with the ID $groupID.
     *
     * @param string $memberType
     *         'user' => ask for all user IDs
     *         'group' => ask for all group IDs
     * @return array(int)
     *         List of IDs of all direct users or groups in this group.
     *
     */
    public function getMembersOfGroup($groupID, $memberType)
    {
        $dbr = wfGetDB( DB_SLAVE );
        $res = $dbr->select('halo_acl_group_members', 'child_id', array(
            'parent_group_id' => $groupID,
            'child_type'      => $memberType), __METHOD__);

        $members = array();
        while ($row = $dbr->fetchObject($res))
            $members[] = (int) $row->child_id;

        $dbr->freeResult($res);

        return $members;
    }

    /**
     * Massively retrieve members of groups with IDs $ids
     */
    public function getMembersOfGroups($ids)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('halo_acl_group_members', '*', array('parent_group_id' => $ids), __METHOD__);
        $members = array();
        foreach ($res as $row)
            $members[$row->parent_group_id][$row->child_type][] = $row->child_id;
        return $members;
    }

    /**
     * Returns all groups the user is member of
     *
     * @param  string $memberType: 'user' or 'group'
     * @param  int $memberID: ID of asked user or group
     * @param  boolean $recurse: recursive or no
     * @return array(int): parent group IDs
     */
    public function getGroupsOfMember($memberType, $memberID, $recurse = true)
    {
        $dbr = wfGetDB(DB_SLAVE);

        $type = $memberType;
        $ids = $memberID;
        $groups = array();
        if ($memberType == 'group')
            $groups[$memberID] = true;
        do
        {
            $res = $dbr->select('halo_acl_group_members', 'parent_group_id', array(
                'child_type' => $type,
                'child_id'   => $ids,
            ), __METHOD__);
            $type = 'group';
            $ids = array();
            foreach ($res as $row)
            {
                $id = $row->parent_group_id;
                if (empty($groups[$id]))
                {
                    $ids[] = $id;
                    $groups[$id] = true;
                }
            }
        } while ($recurse && $ids);
        if ($memberType == 'group')
            unset($groups[$memberID]);

        return array_keys($groups);
    }

    /**
     * Checks if the given user or group with the ID $childID belongs to the
     * group with the ID $parentID.
     *
     * @param int $parentID
     *         ID of the group that is checked for a member.
     *
     * @param int $childID
     *         ID of the group or user that is checked for membership.
     *
     * @param string $memberType
     *         HACLGroup::USER  : Checks for membership of a user
     *         HACLGroup::GROUP : Checks for membership of a group
     *
     * @param bool recursive
     *         <true>, checks recursively among all children of this $parentID if
     *                 $childID is a member
     *         <false>, checks only if $childID is an immediate member of $parentID
     *
     * @return bool
     *         <true>, if $childID is a member of $parentID
     *         <false>, if not
     *
     */
    public function hasGroupMember($parentID, $childID, $memberType, $recursive)
    {
        $dbr = wfGetDB( DB_SLAVE );

        $parents = array();

        // Ask for the immediate parents of $childID
        // Then check recursively, if one of the parent groups of $childID is $parentID
        do
        {
            $where = array(
                'child_id'   => $parents ? array_keys($parents) : $childID,
                'child_type' => $parents ? 'group' : $memberType,
            );
            $yes = $dbr->selectField('halo_acl_group_members', 'parent_group_id', $where+array(
                'parent_group_id' => $parentID,
            ), __METHOD__);
            if ($yes)
                return true;
            $res = $dbr->select('halo_acl_group_members', 'parent_group_id', $where, __METHOD__);
            $new = false;
            while ($row = $dbr->fetchRow($res))
                if (!$parents[$row[0]])
                    $new = $parents[$row[0]] = true;
            $dbr->freeResult($res);
        } while ($recursive && $new);

        return false;
    }

    public function getGroupMembersRecursive($groupID, $children = array())
    {
        $dbr = wfGetDB(DB_SLAVE);
        while ($groupID)
        {
            $r = $dbr->select('halo_acl_group_members',
                'child_type, child_id',
                array('parent_group_id' => $groupID),
                __METHOD__);
            $groupID = array();
            while ($obj = $dbr->fetchRow($r))
            {
                if (!$children[$obj[0]][$obj[1]])
                {
                    $children[$obj[0]][$obj[1]] = true;
                    if ($obj[0] == 'group')
                        $groupID[] = $obj[1];
                }
            }
        }
        return $children;
    }

    /**
     * Massively retrieves users with IDs $user_ids from the DB
     * @return array(object), indexed by user ID
     */
    public function getUsers($user_ids)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $rows = array();
        if ($user_ids)
        {
            $res = $dbr->select('user', '*', array('user_id' => $user_ids), __METHOD__);
            foreach ($res as $r)
                $rows[$r->user_id] = $r;
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
            return array();
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(array('categorylinks', 'page'), 'cl_to, page.*',
            array('page_id=cl_from', 'cl_to' => $dbkeys), __METHOD__);
        $cont = array();
        foreach ($res as $row)
            $cont[$row->cl_to][] = Title::newFromRow($row);
        return $cont;
    }

    /**
     * Massively retrieves IntraACL groups with $group_ids from the DB
     * @return array(object), indexed by group ID
     */
    public function getGroupsByIds($group_ids)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $rows = array();
        $res = $dbr->select('halo_acl_groups', '*', $group_ids ? array('group_id' => $group_ids) : '1', __METHOD__);
        foreach ($res as $r)
            $rows[$r->group_id] = $r;
        return $rows;
    }

    /**
     * Deletes the group with the ID $groupID from the database. All references
     * to the group in the hierarchy of groups are deleted as well.
     *
     * However, the group is not removed from any rights, security descriptors etc.
     * as this would mean that articles will have to be changed.
     *
     *
     * @param int $groupID
     *         ID of the group that is removed from the database.
     *
     */
    public function deleteGroup($groupID) {
        $dbw = wfGetDB( DB_MASTER );

        // Delete the group from the hierarchy of groups (as parent and as child)
        $dbw->delete('halo_acl_group_members', array('parent_group_id' => $groupID), __METHOD__);
        $dbw->delete('halo_acl_group_members', array('child_type' => 'group', 'child_id' => $groupID), __METHOD__);

        // Delete the group's definition
        $dbw->delete('halo_acl_groups', array('group_id' => $groupID), __METHOD__);
    }

    /**
     * Checks if the group with the ID $groupID exists in the database.
     *
     * @param int $groupID
     *         ID of the group
     *
     * @return bool
     *         <true> if the group exists
     *         <false> otherwise
     */
    public function groupExists($groupID)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $obj = $dbr->selectRow('halo_acl_groups', 'group_id', array('group_id' => $groupID), __METHOD__);
        return ($obj !== false);
    }

    /***************************************************************************
     *
     * Functions for security descriptors (SD)
     *
     **************************************************************************/

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
            $irs[] = $asObject ? self::rowToRight($row) : (int)$row->right_id;
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
            array('halo_acl_security_descriptors', 'page'),
            'halo_acl_security_descriptors.*, page_title',
            array('sd_id' => $SDID, 'page_id=sd_id'),
            __METHOD__
        );
        if (!is_array($SDID))
            return self::rowToSD($dbr->fetchObject($res));
        elseif (is_array($SDID))
        {
            $byid = array();
            foreach ($res as $row)
            {
                $sd = self::rowToSD($row);
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
    static function rowToSD($row)
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
            self::strToIntArray($row->mr_groups),
            self::strToIntArray($row->mr_users)
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
            $this->deleteRight($ir);

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
            if ($p != $SDID)
            {
                $sd = HACLSecurityDescriptor::newFromID($p);
                $sd->materializeRightsHierarchy();
            }
        }

        // Delete definition of SD from halo_acl_security_descriptors
        if (!$rightsOnly)
            $dbw->delete('halo_acl_security_descriptors', array('sd_id' => $SDID), __METHOD__);
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
     * Select all SD pages (not only saved SDs as incorrect SDs may be not saved),
     * with additional fields:
     * - sd_single_id is ID of the only one included predefined right.
     * - sd_single_title is page title of this PR.
     * - sd_no_rights is true, if SD has no direct inline rights.
     * I.e. when sd_no_rights is true, non-NULL sd_single_id means that SD
     * contains only one predefined right inclusion.
     *
     * Partly repeated with getSDs2()
     * FIXME: remove this duplication
     */
    public function getSDPages($types, $name, $offset, $limit, &$total)
    {
        global $haclgContLang;
        $dbr = wfGetDB(DB_SLAVE);
        $t = $types ? array_flip(explode(',', $types)) : NULL;
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
        $res = $dbr->select(array('halo_acl_rights_hierarchy', 'halo_acl_rights', 'page'),
            'parent_right_id, page.*',
            array('origin_id IS NULL', 'parent_right_id' => array_keys($rows)),
            __METHOD__,
            array('GROUP BY' => 'parent_right_id', 'HAVING' => 'COUNT(child_id)=1'),
            array(
                'halo_acl_rights' => array('LEFT JOIN', array('origin_id=parent_right_id')),
                'page' => array('INNER JOIN', array('page_id=child_id'))
            )
        );
        foreach ($res as $row)
            $rows[$row->parent_right_id]->sd_single_title = Title::newFromRow($row);
        return $rows;
    }

    /***************************************************************************
     *
     * Functions for inline rights
     *
     **************************************************************************/

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
            return self::rowToRight($dbr->fetchObject($res));
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
        $groups      = self::strToIntArray($row->groups);
        $users       = self::strToIntArray($row->users);
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
            $rights[] = self::rowToRight($row);

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
            $rights[$row->origin_id][] = self::rowToRight($row);
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
            array('page', 'halo_acl_security_descriptors'), 'page.*',
            array('page_id=sd_id', 'pe_id' => array_keys($catids), 'type' => HACLLanguage::PET_CATEGORY),
            __METHOD__
        );
        $prot = array();
        foreach ($res as $row)
            $prot[] = Title::newFromRow($row);
        return $prot;
    }

    /**
     * Get Title objects for child categories, recursively, including initial $categories
     * $categories: array(Title) - Title objects of parent categories
     */
    public function getAllChildrenCategories($categories)
    {
        if (!$categories)
            return array();
        $dbr = wfGetDB(DB_SLAVE);
        $cats = array();
        foreach ($categories as $c)
            $cats[$c->getDBkey()] = $c;
        $categories = array_keys($cats);
        // Get subcategories
        while ($categories)
        {
            $res = $dbr->select(array('page', 'categorylinks'), 'page.*',
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
    public static function getSDForPE($peID, $peType)
    {
        $dbr = wfGetDB( DB_SLAVE );
        $obj = $dbr->selectRow('halo_acl_security_descriptors', 'sd_id',
            array('pe_id' => $peID, 'type' => $peType), __METHOD__);
        return ($obj === false) ? false : $obj->sd_id;
    }

    /**
     * Retrieves security descriptors from the database
     */
    public static function getSDs2($type = NULL, $prefix = NULL, $limit = NULL, $as_object = true)
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

    /***************************************************************************
     *
     * Functions for special page IDs
     *
     **************************************************************************/

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
    public static function idForSpecial($name) {
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
    public static function specialForID($id) {
        $dbw = wfGetDB( DB_MASTER );
        $obj = $dbw->selectRow('halo_acl_special_pages', 'name', array('id' => -$id), __METHOD__);
        return ($obj === false) ? 0 : $obj->name;
    }

    /**
     * Lists of users and groups are stored as comma separated string of IDs.
     * This function converts the string to an array of integers. Non-numeric
     * elements in the list are skipped.
     *
     * @param string $values
     *         comma separated string of integer values
     * @return array(int)
     *         Array of integers or <NULL> if the string was empty.
     */
    private static function strToIntArray($values) {
        if (!is_string($values) || strlen($values) == 0) {
            return NULL;
        }
        $values = explode(',', $values);
        $intValues = array();
        foreach ($values as $v) {
            if (is_numeric($v)) {
                $intValues[] = (int) trim($v);
            }
        }
        return (count($intValues) > 0 ? $intValues : NULL);
    }

    /***************************************************************************
     *
     * Functions for quickacls
     *
     **************************************************************************/

    public function saveQuickAcl($user_id, $sd_ids, $default_sd_id = NULL)
    {
        $dbw = wfGetDB(DB_MASTER);

        // delete old quickacl entries
        $dbw->delete('halo_acl_quickacl', array('user_id' => $user_id), __METHOD__);

        $rows = array();
        foreach ($sd_ids as $sd_id)
            $rows[] = array(
                'sd_id'         => $sd_id,
                'user_id'       => $user_id,
                'qa_default'    => $default_sd_id == $sd_id ? 1 : 0,
            );
        $dbw->insert('halo_acl_quickacl', $rows, __METHOD__);
    }

    public function getQuickacl($user_id)
    {
        $dbr = wfGetDB( DB_SLAVE );

        $res = $dbr->select('halo_acl_quickacl', 'sd_id, qa_default', array('user_id' => $user_id), __METHOD__);
        $sd_ids = array();
        $default_id = NULL;
        while ($row = $dbr->fetchObject($res))
        {
            $sd_ids[] = (int)$row->sd_id;
            if ($row->qa_default)
                $default_id = (int)$row->sd_id;
        }
        $dbr->freeResult($res);

        $quickacl = new HACLQuickacl($user_id, $sd_ids, $default_id);
        return $quickacl;
    }

    public function deleteQuickaclForSD($sdid)
    {
        $dbw = wfGetDB( DB_MASTER );
        // delete old quickacl entries
        $dbw->delete('halo_acl_quickacl', array('sd_id' => $sdid), __METHOD__);
        return true;
    }
}
