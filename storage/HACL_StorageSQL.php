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
            'type'      => "ENUM('category','page','namespace','property','right','template') DEFAULT 'page' NOT NULL",
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
        $dbw =& wfGetDB( DB_MASTER );
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
    public function groupNameForID($groupID) {
        $dbr =& wfGetDB( DB_SLAVE );
        $groupName = $dbr->selectField('halo_acl_groups', 'group_name', array('group_id' => $groupID), __METHOD__);
        return $groupName;
    }

    /**
     * Saves the given group in the database.
     *
     * @param HACLGroup $group
     *         This object defines the group that wil be saved.
     *
     * @throws
     *         Exception
     *
     */
    public function saveGroup(HACLGroup $group) {
        $dbw =& wfGetDB(DB_MASTER);
        $mgGroups = implode(',', $group->getManageGroups());
        $mgUsers  = implode(',', $group->getManageUsers());
        $dbw->replace('halo_acl_groups', NULL, array(
            'group_id'   => $group->getGroupID() ,
            'group_name' => $group->getGroupName() ,
            'mg_groups'  => $mgGroups,
            'mg_users'   => $mgUsers), __METHOD__);
    }

    /**
     * Retrieves all groups from the database.
     * [starting with $prefix]
     * [maximum $limit]
     *
     * @return Array
     *         Array of Group Objects
     */
    public function getGroups($prefix = NULL, $limit = NULL)
    {
        $dbr = wfGetDB(DB_SLAVE);

        $options = array('ORDER BY' => 'group_name');
        if ($limit !== NULL)
            $options['LIMIT'] = $limit;
        $res = $dbr->select(
            'halo_acl_groups', '*',
            array($prefix === NULL ? 1 : 'group_name LIKE '.$dbr->addQuotes("%/$prefix%")),
            __METHOD__,
            $options
        );

        $groups = array();
        while ($row = $dbr->fetchObject($res))
        {
            $groupID = $row->group_id;
            $groupName = $row->group_name;
            $mgGroups = self::strToIntArray($row->mg_groups);
            $mgUsers = self::strToIntArray($row->mg_users);
            $groups[] = new HACLGroup($groupID, $groupName, $mgGroups, $mgUsers);
        }
        return $groups;
    }

    /**
     * Retrieves all users and the groups they are attached to
     *
     *
     * @return Array
     *         Array of Group Objects
     *
     */
    public function getUsersWithGroups() {
        $dbr =& wfGetDB( DB_SLAVE );
        $ut = $dbr->tableName('user');
        $gt = $dbr->tableName('halo_acl_groups');
        $gmt = $dbr->tableName('halo_acl_group_members');
        $sql = "SELECT user_id, group_id, group_name
                FROM user
                LEFT JOIN $gmt ON $gmt.child_id = user.user_id
                LEFT JOIN $gt ON $gt.group_id = $gmt.parent_group_id";

        $users = array();

        $res = $dbr->query($sql, __METHOD__);

        $curUser = NULL;

        while ($row = $dbr->fetchObject($res)) {

            if ($curUser != $row->user_id) {

                $curGroupArray = array();
                $curUser = $row->user_id;
            }
            $curGroupArray[] = array("id"=>$row->group_id, "name"=>$row->group_name);
            $users[$row->user_id] = $curGroupArray;
        }

        $dbr->freeResult($res);

        return $users;
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
        $dbr =& wfGetDB( DB_SLAVE );
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
        $dbr =& wfGetDB( DB_SLAVE );
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
        $dbw =& wfGetDB( DB_MASTER );

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
        $dbw =& wfGetDB( DB_MASTER );

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
        $dbw =& wfGetDB( DB_MASTER );

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
        $dbw =& wfGetDB( DB_MASTER );
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
        $dbw =& wfGetDB( DB_MASTER );
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
    public function getMembersOfGroup($groupID, $memberType) {
        $dbr =& wfGetDB( DB_SLAVE );
        $res = $dbr->select('halo_acl_group_members', 'child_id', array(
            'parent_group_id' => $groupID,
            'child_type'      => $memberType), __METHOD__);

        $members = array();
        while ($row = $dbr->fetchObject($res)) {
            $members[] = (int) $row->child_id;
        }

        $dbr->freeResult($res);

        return $members;

    }

    /**
     * Returns all groups the user is member of
     *
     * @param string $memberType
     *         'user' => ask for all user IDs
     *         'group' => ask for all group IDs
     * @return array(int)
     *         List of IDs of all direct users or groups in this group.
     *
     */
    public function getGroupsOfMember($userID) {

        $dbr =& wfGetDB( DB_SLAVE );
        $ut = $dbr->tableName('user');
        $gt = $dbr->tableName('halo_acl_groups');
        $gmt = $dbr->tableName('halo_acl_group_members');
        $sql = "SELECT DISTINCT user_id, group_id, group_name
                FROM user
                LEFT JOIN $gmt ON $gmt.child_id = user.user_id
                LEFT JOIN $gt ON $gt.group_id = $gmt.parent_group_id
                WHERE user.user_id = $userID";

        $res = $dbr->query($sql, __METHOD__);

        $curGroupArray = array();
        while ($row = $dbr->fetchObject($res)) {
            $curGroupArray[] = array(
                'id' => $row->group_id,
                'name' => $row->group_name
            );
        }

        $dbr->freeResult($res);

        return $curGroupArray;


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

    public function getGroupMembersRecursive($groupID, $children = NULL)
    {
        if (!$children)
        {
            $a = array();
            $children = array(&$a);
        }
        $dbr = wfGetDB(DB_SLAVE);
        $r = $dbr->select('halo_acl_group_members', 'child_type, child_id', array('parent_group_id' => $groupID), __METHOD__);
        while ($obj = $dbr->fetchRow($r))
        {
            if (!$children[0][$obj[0]][$obj[1]])
            {
                $children[0][$obj[0]][$obj[1]] = true;
                if ($obj[0] == 'group')
                    getGroupMembersRecursive($obj[1], $children);
            }
        }
        return $children[0];
    }

    public function getUserNames($user_ids)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $rows = array();
        if ($user_ids)
        {
            $res = $dbr->select('user', 'user_id, user_name, user_real_name', array('user_id' => $user_ids), __METHOD__);
            while ($r = $dbr->fetchRow($res))
            {
                unset($r[0]);
                unset($r[1]);
                unset($r[2]);
                $rows[] = $r;
            }
        }
        return $rows;
    }

    public function getGroupNames($group_ids)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $rows = array();
        if ($group_ids)
        {
            $res = $dbr->select('halo_acl_groups', '*', array('group_id' => $group_ids), __METHOD__);
            while ($r = $dbr->fetchRow($res))
            {
                unset($r[0]);
                unset($r[1]);
                unset($r[2]);
                unset($r[3]);
                $rows[] = $r;
            }
        }
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
        $dbw =& wfGetDB( DB_MASTER );

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
    public function groupExists($groupID) {
        $dbr =& wfGetDB( DB_SLAVE );

        $obj = $dbr->selectRow('halo_acl_groups', 'group_id', array('group_id' => $groupID), __METHOD__);
        return ($obj !== false);
    }

    /***************************************************************************
     *
     * Functions for security descriptors (SD)
     *
     **************************************************************************/

    /**
     * Retrieves all SDs from
     * the database.
     *
     *
     * @return Array
     *         Array of SD Objects
     *
     */
    public function getSDs($types)
    {
        $dbr =& wfGetDB( DB_SLAVE );

        $or = array(
            'all' => 0x3F,
            'page' => 0x01,
            'category' => 0x02,
            'namespace' => 0x04,
            'property' => 0x08,
            'standardacl' => 0x0F,
            'acltemplate' => 0x10,
            'defusertemplate' => 0x20,
        );
        $mask = 0;
        foreach ($types as $type)
            $mask = $mask | $or["$type"];
        $where = array();
        if (($mask & 0x3F) != 0x3F)
        {
            $t = array();
            if ($mask & 0x01)
                $t[] = 'page';
            if ($mask & 0x02)
                $t[] = 'category';
            if ($mask & 0x04)
                $t[] = 'namespace';
            if ($mask & 0x08)
                $t[] = 'property';
            if (($mask & 0x30) == 0x30)
                $t[] = 'right';
            elseif ($mask & 0x30)
            {
                // strip leading "Template/"
                $u = $dbr->tableName('user');
                $where[] = "type='right' AND SUBSTRING(page_title FROM 10) " .
                    ($mask & 0x10 ? '' : 'NOT') .
                    " IN (SELECT user_name FROM $u)";
            }
            if ($t)
                $where[] = "type IN ('".implode("','", $t)."')";
            if ($where)
                $where = '(' . implode(') OR (', $where) . ')';
        }

        $sds = array();
        $res = $dbr->select(
            array('halo_acl_security_descriptors', 'page'), '*', $where, __METHOD__,
            array('ORDER BY' => 'page_title'),
            array('page' => array('LEFT JOIN', array('page_id=sd_id')))
        );
        while ($row = $dbr->fetchObject($res))
            $sds[] = HACLSecurityDescriptor::newFromID($row->sd_id);
        $dbr->freeResult($res);

        return $sds;
    }

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
    public function setInlineRightsForProtectedElements($inlineRights, $securityDescriptors)
    {
        $dbw = wfGetDB(DB_MASTER);
        foreach ($securityDescriptors as $sd)
        {
            // retrieve the protected element and its type
            $obj = $dbw->selectRow('halo_acl_security_descriptors', 'pe_id, type', array('sd_id' => $sd), __METHOD__);
            if (!$obj)
                continue;
            foreach ($inlineRights as $ir)
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
        $dbr = wfGetDB( DB_SLAVE );
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
        $dbr =& wfGetDB( DB_SLAVE );

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

        $parentIDs = array($prID => true);
        $childIDs = array($prID);
        $exclude = array();
        while (true)
        {
            $res = $dbr->select(
                'halo_acl_rights_hierarchy', 'parent_right_id',
                array('child_id' => $childIDs), __METHOD__,
                array('DISTINCT')
            );
            $childIDs = array();
            while ($row = $dbr->fetchObject($res))
            {
                $prid = (int)$row->parent_right_id;
                $parentIDs[$prid] = true;
                if (!$exclude[$prid])
                {
                    $childIDs[] = $prid;
                    $exclude[$prid] = true;
                }
            }
            $dbr->freeResult($res);
            if (empty($childIDs))
            {
                // No further children found
                break;
            }
        }

        // $parentIDs now contains all SDs/PRs that include $prID
        // => select only the SDs

        $sdIDs = array();
        if (!$parentIDs)
            return array();
        $res = $dbr->select('halo_acl_security_descriptors', 'sd_id',
            array("type != 'right'", 'sd_id' => array_keys($parentIDs)), __METHOD__);
        while ($row = $dbr->fetchObject($res))
            $sdIDs[] = (int)$row->sd_id;
        $dbr->freeResult($res);

        return $sdIDs;
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
        if (is_array($SDID) && !$SDID)
            return array();
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
                if ($byid[$id])
                    $r[] = $byid[$id];
            return $r;
        }
        return NULL;
    }

    /* Create HACLSecurityDescriptor from DB row object */
    static function rowToSD($row)
    {
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
        $dbw = wfGetDB( DB_MASTER );

        // Delete all inline rights that are defined by the SD (and the
        // references to them)
        $res = $dbw->select('halo_acl_rights', 'right_id', array('origin_id' => $SDID), __METHOD__);

        while ($row = $dbw->fetchObject($res)) {
            $this->deleteRight($row->right_id);
        }
        $dbw->freeResult($res);

        // Remove all inline rights from the hierarchy below $SDID from their
        // protected elements. This may remove too many rights => the parents
        // of $SDID must materialize their rights again
        $prs = $this->getPredefinedRightsOfSD($SDID, true);
        $irs = $this->getInlineRightsOfSDs($prs);

        if (!empty($irs)) {
            $sds = $this->getSDsIncludingPR($SDID);
            $sds[] = $SDID;
            foreach ($sds as $sd) {
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

        // Get all direct parents of $SDID
        $res = $dbw->select('halo_acl_rights_hierarchy', 'parent_right_id',
            array('child_id' => $SDID), __METHOD__);
        $parents = array();
        while ($row = $dbw->fetchObject($res))
            $parents[] = $row->parent_right_id;
        $dbw->freeResult($res);

        // Delete the SD from the hierarchy of rights in halo_acl_rights_hierarchy
        //if (!$rightsOnly) {
        //    $dbw->delete('halo_acl_rights_hierarchy', array('child_id' => $SDID));
        //}
        $dbw->delete('halo_acl_rights_hierarchy', array('parent_right_id' => $SDID), __METHOD__);

        // Rematerialize the rights of the parents of $SDID
        foreach ($parents as $p) {
            $sd = HACLSecurityDescriptor::newFromID($p);
            $sd->materializeRightsHierarchy();
        }

        // Delete the SD from the definition of SDs in halo_acl_security_descriptors
        if (!$rightsOnly) {
            $dbw->delete('halo_acl_security_descriptors', array('sd_id' => $SDID), __METHOD__);
        }
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
        $sql_is_single = $sdID ?
                "(SELECT 1=SUM(CASE WHEN child_id=$sdID THEN 1 ELSE 2 END)
                  FROM $rh rh WHERE rh.parent_right_id=sd.sd_id)" : "0";
        $sql = "SELECT p1.*, p2.page_title sd_title, p2.page_touched sd_touched,
                 $sql_is_single sd_inc_single,
                 (NOT EXISTS (SELECT * FROM $r r WHERE r.origin_id=sd.sd_id)) sd_no_rights,
                 (COUNT(il2.$linksfield)) used_on_pages
                FROM $il il1 INNER JOIN $p p1 ON $linksjoin
                LEFT JOIN $sd sd ON sd.type='page' AND sd.pe_id=p1.page_id
                LEFT JOIN $p p2 ON p2.page_id=sd.sd_id
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
        $dbw =& wfGetDB( DB_MASTER );

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
     * @return array<int>
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
        while ($row = $dbr->fetchObject($res))
            $rights[] = self::rowToRight($row);
        $dbr->freeResult($res);

        return $rights;
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
        $dbw =& wfGetDB( DB_MASTER );

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
        $dbr =& wfGetDB( DB_SLAVE );
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

    public static function getSDs2($type = NULL, $prefix = NULL, $limit = NULL)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $options = array('ORDER BY' => 'page_title');
        if ($limit)
            $options['LIMIT'] = $limit;
        $where = array('sd_id=page_id');
        if ($type !== NULL)
            $where['type'] = $type;
        if (strlen($prefix))
            $where[] = 'page_title LIKE '.$dbr->addQuotes("$prefix%").' OR page_title LIKE '.$dbr->addQuotes("%/$prefix%");
        $res = $dbr->select(array('halo_acl_security_descriptors', 'page'),
            'sd_id, pe_id, type, mr_groups, mr_users, page_namespace, page_title',
            $where, __METHOD__,
            $options
        );
        $rights = array();
        foreach ($res as $r)
            $rights[] = new HACLSecurityDescriptor(
                $r->sd_id, $r->page_title, $r->pe_id,
                $r->type, $r->mg_groups ? $r->mg_groups : array(),
                $r->mg_users ? $r->mg_users : array()
            );
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
        $dbw =& wfGetDB( DB_MASTER );

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
        $dbw =& wfGetDB( DB_MASTER );
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

    /**
     * Returns all Articles names and ids
     *
     * @param string $subName
     * @return array(int, string)
     *         List of IDs of all direct users or groups in this group.
     *
     */
    public function getArticles($subName, $noACLs = false, $type = NULL) {
        global $haclgNamespaceIndex;
        $dbr =& wfGetDB( DB_SLAVE );

        $where = array('lower(page_title) LIKE lower('.$dbr->addQuotes("%$subName%").')');
        if ($type == "property")
            $where['page_namespace'] = SMW_NS_PROPERTY;
        elseif ($type == "category")
            $where['page_namespace'] = NS_CATEGORY;
        if ($noACLs)
            $where[] = 'page_namespace != '.$haclgNamespaceIndex;

        $res = $dbr->select('page', 'page_id, page_title', $where, __METHOD__, array('ORDER BY' => 'page_title'));
        $articleArray = array();
        while ($row = $dbr->fetchObject($res)) {
            $articleArray[] = array("id"=>$row->page_id, "name"=>$row->page_title);
        }
        $dbr->freeResult($res);
        return $articleArray;
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
