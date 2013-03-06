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

class IntraACL_SQL_Groups
{
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
                $mgGroups = IACLStorage::explode($row->mg_groups);
                $mgUsers = IACLStorage::explode($row->mg_users);
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
            $mgGroups = IACLStorage::explode($row->mg_groups);
            $mgUsers  = IACLStorage::explode($row->mg_users);

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
            $mgGroups = IACLStorage::explode($row->mg_groups);
            $mgUsers  = IACLStorage::explode($row->mg_users);

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
        if (!$ids)
            return array();
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
        $dbr = wfGetDB(DB_SLAVE);

        // Ask for the immediate parents of $childID
        // Then check recursively, if one of the parent groups of $childID is $parentID
        if ($memberType === 'user')
        {
            // Include 0 and -1 to check for "all users" (*) / "all registered users" (#) grants, respectively
            $childID = array($childID, -1, 0);
        }
        $where = array('child_type' => $memberType, 'child_id' => $childID);
        $parents = array();
        do
        {
            $res = $dbr->select('halo_acl_group_members', 'parent_group_id', $where, __METHOD__);
            $new = array();
            foreach ($res as $row)
                if (!isset($parents[$row->parent_group_id]))
                    $new[$row->parent_group_id] = true;
            if (isset($new[$parentID]))
                return true;
            $parents += $new;
            $where = array('child_type' => 'group', 'child_id' => array_keys($new));
        } while ($recursive && $new);

        return false;
    }

    public function getGroupMembersRecursive($groupID, $children = array())
    {
        if (!isset($children['user']))
            $children['user'] = array();
        if (!isset($children['group']))
            $children['group'] = array();
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
     * Massively retrieves IntraACL groups with $group_ids from the DB
     * If $group_ids is NULL, retrieves ALL groups
     * @return array(group_id => row)
     */
    public function getGroupsByIds($group_ids)
    {
        if (!$group_ids)
            return array();
        $dbr = wfGetDB(DB_SLAVE);
        $rows = array();
        $res = $dbr->select('halo_acl_groups', '*', is_null($group_ids) ? '1' : array('group_id' => $group_ids), __METHOD__);
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
}
