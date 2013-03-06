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
 * This file contains the class HACLSecurityDescriptor.
 *
 * @author Thomas Schweitzer
 */
if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

/**
 * This class describes security descriptors and predefined rights in IntraACL.
 *
 * Pages, categories and namespaces are protected by a security
 * descriptor (SD). An SD is an article (with certain naming conventions) that
 * contains the rules that are applied to the protected object.
 *
 * Each SD has an ID (the page ID of the article that contains the SD),
 * the page ID of the protected element, a type (page, category, namespace)
 * and a list of users and groups who can modify the SD.
 *
 * Predefined rights have the same structure as security descriptors. So in the
 * following, SD is used as synonym for predefined right. SDs and PRs can be
 * distinguished by their type $mPEType.
 *
 * @author Thomas Schweitzer
 */
class HACLSecurityDescriptor 
{
    //--- Private fields ---
    private $mSDID;            // int: Page ID of the article that defines this SD
    private $mPEID;            // int: Page ID of the protected element
    private $mSDName;          // string: The name of this SD
    private $mPEType;          // string: Type of the protected element (see constants PET_... above)
    private $mManageGroups;    // array(int): IDs of the groups that can modify
                               //             the definition of this SD
    private $mManageUsers;     // array(int): IDs of the users that can modify
                               //             the definition of this SD

    /**
     * Constructor for HACLSecurityDescriptor. If your create a new security
     * descriptor, you have to call the method <save> to stored it in the
     * database.
     *
     * @param int/string $SDID
     *         Article's page ID. If <null>, the class tries to find the correct ID
     *         by the given $SDName. Of course, this works only for existing
     *         security descriptors.
     * @param string $SDName
     *         Name of the SD
     * @param int/string $peID
     *         Page/namespace ID or name of the protected element. This should be
     *         0 if $peType is PET_RIGHT.
     * @param int $peType
     *         Type of the protected element (see constants PET_...)
     * @param array<int/string>/string $manageGroups
     *         An array or a string of comma separated group names or IDs that
     *         can modify the SD's definition. Group names are converted and
     *         internally stored as group IDs. Invalid values cause an exception.
     * @param array<int/string>/string $manageUsers
     *         An array or a string of comma separated user names or IDs that
     *         can modify the SD's definition. User names are converted and
     *         internally stored as user IDs. Invalid values cause an exception.
     * @throws
     *         HACLSDException(HACLSDException::NO_PE_ID)
     */
    function __construct($SDID, $SDName, $peID, $peType, $manageGroups, $manageUsers)
    {
        if (is_null($SDID))
            $SDID = self::idForSD($SDName);
        $this->mSDID = 0+$SDID;
        $this->mSDName = $SDName;
        if (!is_numeric($peID))
        {
            $peName = $peID;
            $peID = self::peIDforName($peID, $peType);
            if ($peID === false)
                throw new HACLSDException(HACLSDException::NO_PE_ID, $peName, $peType);
        }
        $this->mPEID = $peID;
        $this->mPEType = $peType;
        if ($peType == HACLLanguage::PET_RIGHT)
            $this->mPEID = 0;
        $this->setManageGroups($manageGroups);
        $this->setManageUsers($manageUsers);
    }

    //--- getter/setter ---

    public function getSDID()           { return $this->mSDID; }
    public function getPEID()           { return $this->mPEID; }
    public function getSDName()         { return $this->mSDName; }
    public function getPEType()         { return $this->mPEType; }
    public function getPEName()         { $exploded = explode('/', $this->mSDName, 2); return $exploded[1]; }
    public function getManageGroups()   { return $this->mManageGroups; }
    public function getManageUsers()    { return $this->mManageUsers; }

    //--- Public methods ---

    /**
     * Returns the ID of a protected element that is given by its name. The ID
     * depends on the type of the protected element:
     * - PET_PAGE: ID of the article that is protected
     * - PET_NAMESPACE: ID of the namespace that is protected
     * - PET_CATEGORY: ID of the category article whose instances are protected
     * - PET_RIGHT: not applicable
     *
     * @param  string $peName
     *         Name of the protected object. Category:XXX for categories.
     * @param  int $peType
     *         Type of the protected element. See HACLLanguage::PET_*
     * @return int/bool
     *         ID of the protected element or <false>, if it does not exist.
     */
    public static function peIDforName($peName, $peType)
    {
        $ns = NS_MAIN;
        if ($peType === HACLLanguage::PET_NAMESPACE)
        {
            // $peName is a namespace => get its ID
            global $wgContLang;
            $peName = str_replace(' ', '_', trim(str_replace('_', ' ', $peName)));
            $idx = $wgContLang->getNsIndex($peName);
            if ($idx == false)
                return (strtolower($peName) == 'main') ? 0 : false;
            return $idx;
        }
        elseif ($peType === HACLLanguage::PET_RIGHT)
            return 0;
        elseif ($peType === HACLLanguage::PET_CATEGORY)
            $ns = NS_CATEGORY;
        // return the page id
        $id = haclfArticleID($peName, $ns);
        return $id == 0 ? false : $id;
    }

    /**
     * Creates a new SD object based on the name of the SD. The SD must
     * exists in the database.
     *
     * @param string $SDName
     *         Name of the SD.
     *
     * @return HACLSecurityDescriptor
     *         A new SD object.
     * @throws
     *         HACLSDException(HACLSDException::UNKNOWN_SD)
     *             ...if the requested SD in the not the database.
     */
    public static function newFromName($SDName) {
        $id = self::idForSD($SDName);
        if (!$id) {
            throw new HACLSDException(HACLSDException::UNKNOWN_SD, $SDName);
        }
        return self::newFromID($id);
    }

    /**
     * Creates a new SD object based on the ID of the SD. The SD must
     * exists in the database.
     *
     * @param int $SDID
     *         ID of the SD i.e. the ID of the article that defines the SD.
     *
     * @return HACLSecurityDescriptor
     *         A new SD object.
     * @throws
     *         HACLSDException(HACLSDException::UNKNOWN_SD)
     *             ...if the requested SD in the not the database.
     */
    public static function newFromID($SDID, $throwError = true)
    {
        $sd = IACLStorage::get('SD')->getSDByID($SDID);
        if (!$sd && $throwError)
            throw new HACLSDException(HACLSDException::UNKNOWN_SD, $SDID);
        return $sd;
    }

    /**
     * Returns the page ID of the article that defines the SD with the name
     * $SDName.
     *
     * @param string $SDName
     *         Name of the SD
     *
     * @return int
     *         The ID of the SD's article or <null> if the article does not exist.
     *
     */
    public static function idForSD($SDName) {
        return haclfArticleID($SDName, HACL_NS_ACL);
    }

    /**
     * Returns the name of the SD with the ID $SDID. This is the ID of the article
     * that defines the SD.
     *
     * @param int $SDID
     *         ID of the SD whose name is requested
     *
     * @return string
     *         Name of the SD with the given ID or <null> if there is no article
     *         that defines this SD
     */
    public static function nameForID($SDID)
    {
        $etc = haclfDisableTitlePatch();
        $nt = Title::newFromID($SDID);
        haclfRestoreTitlePatch($etc);
        return $nt ? $nt->getText() : NULL;
    }

    /**
     * Checks if the SD with the ID $sdID exists in the database.
     *
     * @param  int $sdID
     *         ID of the SD
     *
     * @return bool
     *         <true> if the SD exists
     *         <false> otherwise
     */
    public static function exists($sdID)
    {
        return IACLStorage::get('SD')->sdExists($sdID);
    }

    /**
     * Tries to find the ID of the security descriptor for the protected element
     * with the ID $peID.
     *
     * @param  int $peID
     *         ID of the protected element
     *
     * @param  int $peType
     *         Type of the protected element
     *
     * @return mixed int|bool
     *         int: ID of the security descriptor
     *         <false>, if there is no SD for the protected element
     */
    public static function getSDForPE($peID, $peType)
    {
        return IACLStorage::get('SD')->getSDForPE($peID, $peType);
    }

    /**
     * Sets the users who can manage this SD. The SD has to be saved
     * afterwards to persists the changes in the database.
     *
     * @param mixed string|array(mixed int|string|User) $manageUsers
     *        If a single string is given, it contains a comma-separated list of
     *        user names.
     *        If an array is given, it can contain user-objects, names of users or
     *        IDs of a users. If <null> or empty, the currently logged in user is
     *        assumed.
     *        There are two special user names:
     *        '*' - anonymous user (ID:0)
     *        '#' - all registered users (ID: -1)
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
                $uid = haclfGetUserID($mu, false);
                $this->mManageUsers[$i] = $uid[0];
            }
        }
        else
            $this->mManageUsers = array();
    }

    /**
     * Sets the groups who can manage this SD. The SD has to be saved
     * afterwards to persists the changes in the database.
     *
     * @param mixed string|array(mixed int|string|User) $manageGroups
     *         If a single string is given, it contains a comma-separated list of
     *         group names.
     *         If an array is given, it can contain IDs (int), names (string) or
     *      objects (HACLGroup) for the group
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
                if ($gid = HACLGroup::idForGroup($mg))
                    $this->mManageGroups[$i] = $gid;
            }
        }
        else
            $this->mManageGroups = array();
    }

    /**
     * The name of the security descriptor determines which element it protects.
     * This method returns the name and type of the element that is protected
     * by the security descriptor with the name $nameOfSD.
     *
     * @param string $nameOfSD
     *         Name of the security descriptor that protects an element (with or
     *         without namespace).
     *
     * @return array(string, string)
     *         Name of the protected element and its type (one of HACLLanguage::PET_CATEGORY
     *         etc). It the type is HACLLanguage::PET_RIGHT, the name is <null>.
     */
    public static function nameOfPE($nameOfSD)
    {
        global $wgContLang, $haclgContLang;
        $ns = $wgContLang->getNsText(HACL_NS_ACL).':';

        // Ignore the namespace
        if (strpos($nameOfSD, $ns) === 0)
            $nameOfSD = substr($nameOfSD, strlen($ns));

        $p = strpos($nameOfSD, '/');
        if (!$p)
            return array(NULL, HACLLanguage::PET_RIGHT);

        $prefix = substr($nameOfSD, 0, $p);
        if ($type = $haclgContLang->getPetAlias($prefix))
        {
            $peName = substr($nameOfSD, $p+1);
            if ($type == HACLLanguage::PET_CATEGORY)
                $peName = $wgContLang->getNsText(NS_CATEGORY).':'.$peName;
            elseif ($type == HACLLanguage::PET_RIGHT)
                $peName = NULL;
            return array($peName, $type);
        }

        // Right by default
        return array(NULL, 'right');
    }

    /**
     * The name of the protected element and its type determine the name of
     * its security descriptor.
     * This method returns the complete name of the SD (with namespace) for a
     * given protected element.
     *
     * @param string $nameOfPE
     *         The full name of the protected element
     * @param string $peType
     *         The type of the protected element which is one of HACLLanguage::PET_*
     *
     * @return array(string, string)
     *         Name of the protected element and its type (one of HACLLanguage::PET_*
     *         etc). It the type is HACLLanguage::PET_RIGHT, the name is <null>.
     */
    public static function nameOfSD($nameOfPE, $peType)
    {
        global $wgContLang, $haclgContLang;
        $ns = $wgContLang->getNsText(HACL_NS_ACL).':';
        $prefix = $haclgContLang->getPetPrefix($peType).'/';
        $sdName = $ns.$prefix.$nameOfPE;
        return $sdName;
    }

    /**
     * Saves the given predefined rights (PR) and adds them to this security
     * descriptor (SD).
     *
     * PRs and SDs have the same structure and are both managed in the class
     * HACLSecurityDescriptor. The type of PRs is PET_RIGHT. You can add PRs to PRs
     * and SDs, but not SDs to SDs.
     *
     * The hierarchy of rights in the database is immediately updated.
     * For each protected element (e.g. a page) all rights are stored in the
     * database. The hierarchy of rights is materialized in the DB. If a PR is
     * added to an SD, add sub-rights of the PR are added to the element that
     * is protected by the SD. If a PR is added to a PR, all sub-rights are added
     * to all elements whose SD includes the parent PR.
     * For better performance, all predefined rights should be added at once and
     * not one after another.
     *
     * @param array<HACLSecurityDescriptor> $rights
     *         These rights are added. The type of their security descriptors must be
     *         PET_RIGHT.
     *
     * @param User/string/int $user
     *         User-object, name of a user or ID of a user who wants to delete this
     *         SD. If <null> (default), the currently logged in user is assumed.
     *
     * @throws
     *         Exception
     *         ... in case of database failure
     *         HACLSDException(HACLSDException::CANNOT_ADD_SD)
     *         ... if an SD is added to an SD or PR
     */
    public function addPredefinedRights($rights, $user = null)
    {
        if (empty($rights))
            return;

        // Update the hierarchy of SDs/PRs
        foreach ($rights as $r)
            IACLStorage::get('SD')->addRightToSD($this->getSDID(), $r->getSDID());

        // Assign rights to all protected elements
        $this->materializeRightsHierarchy();
    }

    /**
     * Adds several inline rights. The origin-ID of the inline rights are set
     * and they are saved to the database. The new hierarchy of rights is
     * materialized in the database. It is always faster to add all inline rights
     * at once instead of one after another.
     *
     * @param array<HACLRight> $rights
     *         An array of inline rights
     * @param User/string/int $user
     *         User-object, name of a user or ID of a user who wants to delete this
     *         SD. If <null> (default), the currently logged in user is assumed.
     */
    public function addInlineRights($rights, $user = null)
    {
        if (empty($rights))
            return;
        foreach ($rights as $r)
        {
            $r->setOriginID($this->mSDID);
            $r->save();
        }
        $this->materializeRightsHierarchy();
    }

    /**
     * All inline and predefined rights are removed from this SD. The materialized
     * rights are updated.
     *
     * @param User/string/int $user
     *         User-object, name of a user or ID of a user who wants to delete this
     *         SD. If <null> (default), the currently logged in user is assumed.
     *
     * @throws
     *     HACLSDException(HACLSDException::USER_CANT_MODIFY_SD)
     *
     */
    public function removeAllRights($user = NULL)
    {
        return IACLStorage::get('SD')->deleteSD($this->mSDID, true);
    }

    /**
     * Returns a list of IDs of all inline rights of this SD.
     *
     * @param bool $recursively
     *         If <true>, the whole hierarchy of rights of this SD is considered
     *                    and all derived inline rights are returned.
     *         If <false>, only the direct inline rights of this SD are returned.
     *
     * @return array<int>
     *         An array of inline right IDs. There are no duplicate IDs.
     *
     */
    // FIXME add caching
    public function getInlineRights($recursively = true)
    {
        if ($recursively)
        {
            // find all derived inline rights as well
            // => first, find all derived predefined rights
            $sdIDs = self::getPredefinedRights(true);
            $sdIDs[] = $this->getSDID();
        }
        else
            $sdIDs = array($this->getSDID());
        // Get all direct rights of this SDs
        $ir = IACLStorage::get('SD')->getInlineRightsOfSDs($sdIDs);
        return $ir;
    }

    /**
     * Returns an array of IDs of predefined rights of this SD.
     *
     * @param bool $recursively
     *         <true>: The whole hierarchy of rights is returned.
     *         <false>: Only the direct rights of this SD are returned.
     *
     * @return array<int>
     *         Array of unique IDs of rights of this SD.
     */
    public function getPredefinedRights($recursively = true)
    {
        $pr = IACLStorage::get('SD')->getPredefinedRightsOfSD($this->getSDID(), $recursively);
        return $pr;
    }

    /**
     * Checks whether this SD only includes SINGLE predefined right and
     * does not include any inline rights or manage template rights.
     * If so, the ID of this single predefined right is returned.
     * If not, NULL is returned.
     */
    public function isSinglePredefinedRightInclusion()
    {
        $pr = $this->getPredefinedRights(false);
        if (count($pr) == 1 && !$this->mManageUsers && !$this->mManageGroups &&
            !$this->getInlineRights(false))
            return $pr[0];
        return NULL;
    }

    /**
     * Finds all security descriptors that are including this one.
     * Note that the inclusion of an SD into different SD/PR is also allowed.
     *
     * @return array<int>
     *         An array of IDs of all SD that include $this SD or PR
     *         via the hierarchy of PRs, including $this one.
     */
    public function getSDsIncludingPR()
    {
        return IACLStorage::get('SD')->getSDsIncludingPR($this->mSDID);
    }

    /**
     * This method checks the integrity of this SD. The integrity can be violated
     * by missing groups, users or predefined rights.
     *
     * return mixed bool / array
     *     <true> if the SD is valid,
     *  array(string=>bool) otherwise
     *         The array has the keys "groups", "users" and "rights" with boolean values.
     *         If the value is <true>, at least one of the corresponding entities
     *         is missing.
     */
    public function checkIntegrity() {
        $missingGroups = false;
        $missingUsers = false;
        $missingPR = false;

        //== Check integrity of group managers ==

        // Check for missing groups
        foreach ($this->mManageGroups as $gid) {
            if (!IACLStorage::get('Groups')->groupExists($gid)) {
                $missingGroups = true;
                break;
            }
        }

        // Check for missing users
        foreach ($this->mManageUsers as $uid) {
            if ($uid > 0 && User::whoIs($uid) === false) {
                $missingUsers = true;
                break;
            }
        }


        //== Check integrity of inline rights ==

        $irIDs = $this->getInlineRights(false);
        foreach ($irIDs as $irID) {
            $ir = IACLStorage::get('IR')->getRightByID($irID);
            $groupIDs = $ir->getGroups();
            // Check for missing groups
            foreach ($groupIDs as $gid) {
                if (!IACLStorage::get('Groups')->groupExists($gid)) {
                    $missingGroups = true;
                    break;
                }
            }
            // Check for missing users
            $userIDs = $ir->getUsers();
            foreach ($userIDs as $uid) {
                if ($uid > 0 && User::whoIs($uid) === false) {
                    $missingUsers = true;
                    break;
                }
            }
        }

        // Check for missing predefined rights
        $prIDs = $this->getPredefinedRights(false);
        foreach ($prIDs as $prid) {
            if (!IACLStorage::get('SD')->sdExists($prid)) {
                $missingPR = true;
                break;
            }
        }

        if (!$missingGroups && !$missingPR && !$missingUsers) {
            return true;
        }
        return array('groups' => $missingGroups,
                     'users'  => $missingUsers,
                     'rights' => $missingPR);
    }

    protected function checkIndirectManageRight($userID)
    {
        $title = Title::newFromId($this->mPEID);
        if ($title && HACLEvaluator::hasRight($title->getNamespace(), HACLLanguage::PET_NAMESPACE, $userID, HACLLanguage::RIGHT_MANAGE))
            return true;
        list($r) = HACLEvaluator::hasCategoryRight($title, $userID, HACLLanguage::RIGHT_MANAGE);
        return $r;
    }

    /**
     * Checks if the given user can modify this SD.
     *
     * @param User/string/int $user
     *         User-object, name of a user or ID of a user who wants to modify this
     *         SD. If <null>, the currently logged in user is assumed.
     *
     * @param boolean $throwException
     *         If <true>, the exception
     *         HACLSDException(HACLSDException::USER_CANT_MODIFY_SD)
     *         is thrown, if the user can't modify the SD.
     *
     * @return boolean
     *         One of these values is returned if no exception is thrown:
     *         <true>, if the user can modify this SD and
     *         <false>, if not
     *
     * @throws
     *         HACLException(HACLException::UNKNOWN_USER)
     *         If requested: HACLSDException(HACLSDException::USER_CANT_MODIFY_SD)
     */
    public function userCanModify($user = false, $throwException = false)
    {
        global $wgUser;
        if (!$user)
            $user = $wgUser;

        // Get the ID of the user who wants to add/modify the SD
        list($userID, $userName) = haclfGetUserID($user);

        // Check if the user can modify the SD
        if (in_array($userID, $this->mManageUsers))
            return true;
        if ($userID > 0 && in_array(-1, $this->mManageUsers))
        {
            // -1 means that all registered users can modify the SD
            return true;
        }

        // Check if the user belongs to a group that can modify the SD
        foreach ($this->mManageGroups as $groupID)
            if (IACLStorage::get('Groups')->hasGroupMember($groupID, $userID, HACLGroup::USER, true))
                return true;

        // Sysops and bureaucrats can modify anything
        $user = User::newFromId($userID);
        $groups = $user->getGroups();
        if (in_array('sysop', $groups) || in_array('bureaucrat', $groups))
            return true;

        // Check for RIGHT_MANAGE inherited from included SDs/PRs, for all except PRs
        if ($this->mPEType != HACLLanguage::PET_RIGHT)
        {
            $r = HACLEvaluator::hasRight(
                $this->mPEID, $this->mPEType, $userID,
                HACLLanguage::RIGHT_MANAGE,
                // Include direct RIGHT_MANAGE only for page SDs
                $this->mPEType == HACLLanguage::PET_PAGE ? NULL : $this->mSDID
            );
            if ($r)
                return true;
        }

        // Check for RIGHT_MANAGE inherited from namespaces and categories
        if ($this->mPEType == HACLLanguage::PET_PAGE)
        {
            $etc = haclfDisableTitlePatch();
            $r = $this->checkIndirectManageRight($userID);
            haclfRestoreTitlePatch($etc);
            if ($r)
                return true;
        }

        if ($throwException)
        {
            if (empty($userName))
            {
                // only user id is given => retrieve the name of the user
                $userName = ($user) ? $user->getId() : "(User-ID: $userID)";
            }
            throw new HACLSDException(HACLSDException::USER_CANT_MODIFY_SD, $this->mSDName, $userName);
        }
        return false;
    }

    /**
     * Saves this SD in the database. A SD needs a name and at least one group
     * or user who can modify the definition of this group. If no group or user
     * is given, the specified or the current user gets this right. If no user is
     * logged in, the operation fails.
     *
     * If the SD already exists and the given user has the right to modify the
     * SD, the SDs definition is changed.
     *
     * @param  User/string $user
     *         User-object or name of the user who wants to save this SD. If this
     *         value is empty or <null>, the current user is assumed.
     *
     * @throws
     *         HACLSDException(HACLSDException::NO_SD_ID)
     *         Exception (on failure in database level)
     */
    public function save()
    {
        // Check that SD refers to some page ID
        if ($this->mSDID == 0)
            throw new HACLSDException(HACLSDException::NO_SD_ID, $this->mSDName);
        IACLStorage::get('SD')->saveSD($this);
    }

    /**
     * Deletes this security descriptor from the database.
     *
     * @param User/string/int $user
     *         User-object, name of a user or ID of a user who wants to delete this
     *         SD. If <null>, the currently logged in user is assumed.
     */
    public function delete()
    {
        return IACLStorage::get('SD')->deleteSD($this->mSDID);
    }

    /**
     * Rights are organized in a hierarchy. Security descriptors (SD) and predefined
     * rights (PR) (which are almost the same) can contain PRs and inline rights.
     * SDs protect elements (e.g. pages). For faster evaluation of rights, all
     * inline rights that belong to an SD through the hierarchy of rights are
     * assigned to the protected element in the database.
     */
    public function materializeRightsHierarchy()
    {
        // Recursively find all inline rights that belong to right
        $ir = $this->getInlineRights(true);

        // Recursively find all security descriptors that get the new right
        if (!empty($ir))
            $sd = $this->getSDsIncludingPR();

        // Add all inline rights to all protected elements (i.e. materialize the
        // hierarchy of rights)
        if (!empty($ir) && !empty($sd))
            IACLStorage::get('SD')->setInlineRightsForProtectedElements($ir, $sd);
    }
}
