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
 * This file contains the class HACLGroup.
 *
 * @author Thomas Schweitzer
 * Date: 03.04.2009
 *
 */
if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

/**
 * This class describes a group in HaloACL.
 *
 * A group is always represented by an article in the wiki, so the group's
 * description contains the page ID of this article and the name of the group.
 *
 * Only authorized users and groups of users can modify the definition of the
 * group. Their IDs are stored in the group as well.
 *
 * @author Thomas Schweitzer
 *
 */
class HACLGroup
{
    //--- Constants ---
    const NAME   = 0;       // Mode parameter for getUsers/getGroups
    const ID     = 1;       // Mode parameter for getUsers/getGroups
    const OBJECT = 2;       // Mode parameter for getUsers/getGroups
    const USER   = 'user';  // Child type for users
    const GROUP  = 'group'; // Child type for groups

    //--- Private fields ---
    private $mGroupID;      // int: Page ID of the article that defines this group
    private $mGroupName;    // string: The name of this group
    private $mManageGroups; // array(int): IDs of the groups that can modify this group
    private $mManageUsers;  // array(int): IDs of the users that can modify this group

    /**
     * Constructor for HACLGroup
     *
     * @param int/string $groupID
     *         Article's page ID. If <null>, the class tries to find the correct ID
     *         by the given $groupName. Of course, this works only for existing
     *         groups.
     * @param string $groupName
     *         Name of the group
     * @param array<int/string>/string $manageGroups
     *         An array or a string of comma separated group names or IDs that
     *         can modify the group's definition. Group names are converted and
     *         internally stored as group IDs. Invalid values cause an exception.
     * @param array<int/string>/string $manageUsers
     *         An array or a string of comma separated of user names or IDs that
     *         can modify the group's definition. User names are converted and
     *         internally stored as user IDs. Invalid values cause an exception.
     * @throws
     *         HACLGroupException(HACLGroupException::UNKNOWN_GROUP)
     *         HACLException(HACLException::UNKNOWN_USER)
     *
     */
    function __construct($groupID, $groupName, $manageGroups, $manageUsers)
    {
        if (is_null($groupID))
            $groupID = self::idForGroup($groupName);
        $this->mGroupID = 0+$groupID;
        $this->mGroupName = $groupName;
        $this->setManageGroups($manageGroups);
        $this->setManageUsers($manageUsers);
    }

    //--- getters ---

    public function getGroupID()        { return $this->mGroupID; }
    public function getGroupName()      { return $this->mGroupName; }
    public function getManageGroups()   { return $this->mManageGroups; }
    public function getManageUsers()    { return $this->mManageUsers; }

    //--- Public methods ---

    /**
     * Creates a new group object based on the name of the group. The group must
     * exists in the database.
     *
     * @param string $groupName
     *         Name of the group.
     *
     * @return HACLGroup
     *         A new group object.
     *
     * @throws
     *         HACLGroupException(HACLGroupException::UNKNOWN_Group)
     *             ...if the requested group in the not the database.
     *
     */
    public static function newFromName($groupName, $throw_error = true)
    {
        $group = IACLStorage::get('Groups')->getGroupByName($groupName);
        if ($group === null && $throw_error)
            throw new HACLGroupException(HACLGroupException::UNKNOWN_GROUP, $groupName);
        return $group;
    }

    /**
     * Creates a new group object based on the ID of the group. The group must
     * exists in the database.
     *
     * @param int $groupID
     *         ID of the group i.e. the ID of the article that defines the group.
     *
     * @return HACLGroup
     *         A new group object.
     *
     * @throws
     *         HACLGroupException(HACLGroupException::INVALID_GROUP_ID)
     *             ...if the requested group in the not the database.
     */
    public static function newFromID($groupID, $throw_error = true) {
        $group = IACLStorage::get('Groups')->getGroupByID($groupID);
        if ($group === null && $throw_error) {
            throw new HACLGroupException(HACLGroupException::INVALID_GROUP_ID, $groupID);
        }
        return $group;
    }

    /**
     * Returns the page ID of the article that defines the group with the object,
     * name or ID $group.
     *
     * @param mixed (int/string/HACLGroup) $group
     *         ID, name or object for the group whose ID is returned.
     *
     * @return int
     *         The ID of the group's article or <null> if there is no ID for the group.
     *
     */
    public static function idForGroup($group) {
        if (is_int($group)) {
            // group ID given
            return $group;
        } elseif (is_string($group)) {
            // Name of group given
            return haclfArticleID($group, HACL_NS_ACL);
        } elseif (is_a($group, 'HACLGroup')) {
            // group object given
            return $group->getGroupID();
        }
        // This should not happen
        return null;
    }

    /**
     * Returns the name of the group with the ID $groupID.
     *
     * @param int $groupID
     *         ID of the group whose name is requested
     *
     * @return string
     *         Name of the group with the given ID or <null> if there is no such
     *         group defined in the database.
     */
    public static function nameForID($groupID) {
        return IACLStorage::get('Groups')->groupNameForID($groupID);
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
    public static function exists($groupID) {
        return IACLStorage::get('Groups')->groupExists($groupID);
    }

    /**
     * This method checks the integrity of this group. The integrity can be violated
     * by missing groups and users.
     *
     * return mixed bool / array
     *     <true> if the group is valid,
     *  array(string=>bool) otherwise
     *         The array has the keys "groups", "users" with boolean values.
     *         If the value is <true>, at least one of the corresponding entities
     *         is missing.
     */
    public function checkIntegrity() {
        $missingGroups = false;
        $missingUsers = false;

        //== Check integrity of group managers ==

        // Check for missing managing groups
        foreach ($this->mManageGroups as $gid) {
            if (!IACLStorage::get('Groups')->groupExists($gid)) {
                $missingGroups = true;
                break;
            }
        }

        // Check for missing managing users
        foreach ($this->mManageUsers as $uid) {
            if ($uid > 0 && User::whoIs($uid) === false) {
                $missingUsers = true;
                break;
            }
        }

        //== Check integrity of group's content  ==
        $groupIDs = $this->getGroups(self::ID);
        // Check for missing groups
        foreach ($groupIDs as $gid) {
            if (!IACLStorage::get('Groups')->groupExists($gid)) {
                $missingGroups = true;
                break;
            }
        }

        // Check for missing users
        $userIDs = $this->getUsers(self::ID);
        foreach ($userIDs as $uid) {
            if ($uid > 0 && User::whoIs($uid) === false) {
                $missingUsers = true;
                break;
            }
        }

        if (!$missingGroups && !$missingUsers) {
            return true;
        }
        return array('groups' => $missingGroups,
                     'users'  => $missingUsers);
    }

    /**
     * Checks whether the user/group ID set $ids is equal to name set $names.
     */
    static function checkIdSet($names, $ids, $is_user = false)
    {
        $ids = array_flip($ids);
        foreach ($names as $name)
        {
            if ($is_user)
                list($id) = haclfGetUserID($name, false);
            else
                $id = HACLGroup::idForGroup($name);
            if ($id && !array_key_exists($id, $ids))
                return false;
            unset($ids[$id]);
        }
        return !$ids;
    }

    /**
     * Checks if the group content is equal to the sets in arguments.
     */
    function checkIsEqual($member_users, $member_groups, $manager_users, $manager_groups)
    {
        return self::checkIdSet($member_users, $this->getUsers(self::ID), true) &&
            self::checkIdSet($member_groups, $this->getGroups(self::ID), false) &&
            self::checkIdSet($manager_users, $this->mManageUsers, true) &&
            self::checkIdSet($manager_groups, $this->mManageGroups, false);
    }

    /**
     * Checks if the given user can modify this group.
     *
     * @param User/string/int $user
     *         User-object, name of a user or ID of a user who wants to modify this
     *         group. If <null>, the currently logged in user is assumed.
     *
     * @param boolean $throwException
     *         If <true>, the exception
     *         HACLGroupException(HACLGroupException::USER_CANT_MODIFY_GROUP)
     *         is thrown, if the user can't modify the group.
     *
     * @return boolean
     *         One of these values is returned if no exception is thrown:
     *         <true>, if the user can modify this group and
     *         <false>, if not
     *
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     *         If requested: HACLGroupException(HACLGroupException::USER_CANT_MODIFY_GROUP)
     *
     */
    public function userCanModify($user, $throwException = false)
    {
        // Get the ID of the user who wants to add/modify the group
        list($userID, $userName) = haclfGetUserID($user);
        // Check if the user can modify the group
        if (in_array($userID, $this->mManageUsers)) {
            return true;
        }
        if ($userID > 0 && in_array(-1, $this->mManageUsers)) {
            // registered users can modify the SD
            return true;
        }

        // Check if the user belongs to a group that can modify the group
        foreach ($this->mManageGroups as $groupID) {
            if (IACLStorage::get('Groups')->hasGroupMember($groupID, $userID, self::USER, true)) {
                return true;
            }
        }

        // Sysops and bureaucrats can modify the SD
        $user = User::newFromId($userID);
        $groups = $user->getGroups();
        if (in_array('sysop', $groups) || in_array('bureaucrat', $groups)) {
            return true;
        }

        if ($throwException) {
            if (empty($userName)) {
                // only user id is given => retrieve the name of the user
                $user = User::newFromId($userID);
                $userName = ($user) ? $user->getId() : "(User-ID: $userID)";
            }
            throw new HACLGroupException(HACLGroupException::USER_CANT_MODIFY_GROUP,
                $this->mGroupName, $userName);
        }
        return false;
    }

    /**
     * Saves this group in the database. A group needs a name and at least one group
     * or user who can modify the definition of this group. If no group or user
     * is given, the specified or the current user gets this right. If no user is
     * logged in, the operation fails.
     *
     * If the group already exists and the given user has the right to modify the
     * group, the groups definition is changed.
     *
     * @param User/string $user
     *         User-object or name of the user who wants to save this group. If this
     *         value is empty or <null>, the current user is assumed.
     *
     * @throws
     *         HACLGroupException(HACLGroupException::NO_GROUP_ID)
     *         HACLException(HACLException::UNKNOWN_USER)
     *         HACLGroupException(HACLGroupException::USER_CANT_MODIFY_GROUP)
     *         Exception (on failure in database level)
     *
     */
    public function save($user = null)
    {
        // Get the page ID of the article that defines the group
        if ($this->mGroupID == 0)
            throw new HACLGroupException(HACLGroupException::NO_GROUP_ID, $this->mGroupName);
        IACLStorage::get('Groups')->saveGroup($this);
    }

    /**
     * Sets the users who can manage this group. The group has to be saved
     * afterwards to persists the changes in the database.
     *
     * @param mixed string|array(mixed int|string|User) $manageUsers
     *          If a single string is given, it contains a comma-separated list of
     *          user names.
     *          If an array is given, it can contain user-objects, names of users or
     *          IDs of a users. If <null> or empty, the currently logged in user is
     *          assumed.
     *          There are two special user names:
     *            '*' - all users (ID: 0)
     *            '#' - all registered users (ID: -1)
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     *             ...if a user does not exist.
     */
    public function setManageUsers($manageUsers)
    {
        if (!empty($manageUsers) && is_string($manageUsers))
        {
            // Managing users are given as comma separated string
            // Split into an array
            $manageUsers = explode(',', $manageUsers);
        }
        if (is_array($manageUsers))
        {
            $this->mManageUsers = $manageUsers;
            for ($i = 0; $i < count($manageUsers); ++$i)
            {
                $mu = $manageUsers[$i];
                if (is_string($mu))
                    $mu = trim($mu);
                $uid = haclfGetUserID($mu);
                $this->mManageUsers[$i] = $uid[0];
            }
        }
        else
            $this->mManageUsers = array();
    }

    /**
     * Sets the groups who can manage this group. The group has to be saved
     * afterwards to persists the changes in the database.
     *
     * @param mixed string|array(mixed int|string|User) $manageGroups
     *         If a single string is given, it contains a comma-separated list of
     *         group names.
     *         If an array is given, it can contain IDs (int), names (string) or
     *      objects (HACLGroup) for the group
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     *             ...if a user does not exist.
     */
    public function setManageGroups($manageGroups)
    {
        if (!empty($manageGroups) && is_string($manageGroups))
        {
            // Managing groups are given as comma separated string
            // Split into an array
            $manageGroups = explode(',', $manageGroups);
        }
        if (is_array($manageGroups))
        {
            $this->mManageGroups = $manageGroups;
            for ($i = 0; $i < count($manageGroups); ++$i)
            {
                $mg = $manageGroups[$i];
                if (is_string($mg))
                    $mg = trim($mg);
                $gid = self::idForGroup($mg);
                if (!$gid)
                    throw new HACLGroupException(HACLGroupException::UNKNOWN_GROUP, $mg);
                $this->mManageGroups[$i] = (int) $gid;
            }
        }
        else
            $this->mManageGroups = array();
    }

    /**
     * Adds the user $user to this group. The new user is immediately added
     * to the group's definition in the database.
     *
     * @param User/string/int $user
     *         This can be a User-object, name of a user or ID of a user. This user
     *         is added to the group.
     * @param User/string/int $mgUser
     *         User-object, name of a user or ID of a user who wants to modify this
     *         group. If <null>, the currently logged in user is assumed.
     *
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     *         HACLGroupException(HACLGroupException::USER_CANT_MODIFY_GROUP)
     *
     */
    public function addUser($user)
    {
        // Check if $mgUser can modify this group.
        list($userID, $userName) = haclfGetUserID($user);
        IACLStorage::get('Groups')->addUserToGroup($this->mGroupID, $userID);
    }

    /**
     * Adds the group $group to this group. The new group is immediately added
     * to the group's definition in the database.
     *
     * @param mixed(HACLGroup/string/id) $group
     *         Group object, name or ID of the group that is added to $this group.
     * @param User/string/int $mgUser
     *         User-object, name of a user or ID of a user who wants to modify this
     *         group. If <null>, the currently logged in user is assumed.
     *
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     *         HACLGroupException(HACLGroupException::USER_CANT_MODIFY_GROUP)
     *         HACLGroupException(HACLGroupException::INVALID_GROUP_ID)
     *
     */
    public function addGroup($group)
    {
        // Check if $mgUser can modify this group.
        $groupID = self::idForGroup($group);
        if ($groupID == 0)
            throw new HACLGroupException(HACLGroupException::INVALID_GROUP_ID, $groupID);
        IACLStorage::get('Groups')->addGroupToGroup($this->mGroupID, $groupID);
    }

    /**
     * Removes all members (groups and users) from this group. They are
     * immediately removed from the group's definition in the database.
     */
    public function removeAllMembers()
    {
        IACLStorage::get('Groups')->removeAllMembersFromGroup($this->mGroupID);
    }

    /**
     * Removes the user $user from this group. The user is immediately removed
     * from the group's definition in the database.
     *
     * @param User/string/int $user
     *         This can be a User-object, name of a user or ID of a user. This user
     *         is removed from the group.
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     */
    public function removeUser($user)
    {
        list($userID, $userName) = haclfGetUserID($user);
        IACLStorage::get('Groups')->removeUserFromGroup($this->mGroupID, $userID);
    }

    /**
     * Removes the group $group from this group. The group is immediately removed
     * from the group's definition in the database.
     *
     * @param mixed(HACLGroup/string/id) $group
     *         Group object, name or ID of the group that is removed from $this group.
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     *         HACLGroupException(HACLGroupException::INVALID_GROUP_ID)
     */
    public function removeGroup($group)
    {
        $groupID = self::idForGroup($group);
        if ($groupID == 0)
            throw new HACLGroupException(HACLGroupException::INVALID_GROUP_ID, $groupID);

        IACLStorage::get('Groups')->removeGroupFromGroup($this->mGroupID, $groupID);
    }

    /**
     * Returns all users who are member of this group.
     *
     * @param int $mode
     *         HACLGroup::NAME:   The names of all users are returned.
     *         HACLGroup::ID:     The IDs of all users are returned.
     *         HACLGroup::OBJECT: User-objects for all users are returned.
     *
     * @return array(string/int/User)
     *         List of all direct users in this group.
     *
     */
    public function getUsers($mode)
    {
        // retrieve the IDs of all users in this group
        $users = IACLStorage::get('Groups')->getMembersOfGroup($this->mGroupID, self::USER);

        if ($mode === self::ID)
            return $users;
        for ($i = 0; $i < count($users); ++$i)
        {
            if ($mode === self::NAME)
                $users[$i] = User::whoIs($users[$i]);
            elseif ($mode === self::OBJECT)
                $users[$i] = User::newFromId($users[$i]);
        }
        return $users;
    }

    /**
     * Returns all groups who are member of this group.
     *
     * @param int $mode
     *         HACLGroup::NAME:   The names of all groups are returned.
     *         HACLGroup::ID:     The IDs of all groups are returned.
     *         HACLGroup::OBJECT: HACLGroup-objects for all groups are returned.
     *
     * @return array(string/int/HACLGroup)
     *         List of all direct groups in this group.
     *
     */
    public function getGroups($mode)
    {
        // retrieve the IDs of all groups in this group
        $groups = IACLStorage::get('Groups')->getMembersOfGroup($this->mGroupID, self::GROUP);
        if ($mode === self::ID)
            return $groups;
        for ($i = 0; $i < count($groups); ++$i)
        {
            if ($mode === self::NAME)
                $groups[$i] = self::nameForID($groups[$i]);
            elseif ($mode === self::OBJECT)
                $groups[$i] = self::newFromID($groups[$i]);
        }
        return $groups;
    }

    /**
     * Checks if this group has the given group as member.
     *
     * @param mixed (int/string/HACLGroup) $group
     *         ID, name or object for the group that is checked for membership.
     *
     * @param bool recursive
     *         <true>, checks recursively among all children of this group if
     *                 $group is a member
     *         <false>, checks only if $group is an immediate member of this group
     *
     * @return bool
     *         <true>, if $group is a member of this group
     *         <false>, if not
     * @throws
     *         HACLGroupException(HACLGroupException::NO_GROUP_ID)
     *             ...if the name of the group is invalid
     *         HACLGroupException(HACLGroupException::INVALID_GROUP_ID)
     *             ...if the ID of the group is invalid
     *
     */
    public function hasGroupMember($group, $recursive)
    {
        $groupID = self::idForGroup($group);
        if ($groupID === 0)
            throw new HACLGroupException(HACLGroupException::INVALID_GROUP_ID, $groupID);
        return IACLStorage::get('Groups')->hasGroupMember($this->mGroupID, $groupID, self::GROUP, $recursive);
    }

    /**
     * Checks if this group has the given user as member.
     *
     * @param User/string/int $user
     *         ID, name or object for the user that is checked for membership.
     *
     * @param bool recursive
     *         <true>, checks recursively among all children of this group if
     *                 $group is a member
     *         <false>, checks only if $group is an immediate member of this group
     *
     * @return bool
     *         <true>, if $group is a member of this group
     *         <false>, if not
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     *             ...if the user does not exist.
     *
     */
    public function hasUserMember($user, $recursive)
    {
        $userID = haclfGetUserID($user);
        return IACLStorage::get('Groups')->hasGroupMember($this->mGroupID, $userID[0], self::USER, $recursive);
    }

    /**
     * Deletes this group from the database. All references to this group in the
     * hierarchy of groups are deleted as well.
     */
    public function delete()
    {
        return IACLStorage::get('Groups')->deleteGroup($this->mGroupID);
    }

    /**
     * returns group description
     * containing users and groups
     * @return <string>
     */
    public function getGroupDescription()
    {
        $result = "";
        foreach ($this->getUsers(HACLGroup::NAME) as $i)
        {
            if (!$result)
                $result = "U:$i";
            else
                $result .= ", U:$i";
        }
        foreach ($this->getGroups(HACLGroup::NAME) as $i)
        {
            if (!$result)
                $result = "G:$i";
            else
                $result .= ", G:$i";
        }
        return $result;
    }
}
