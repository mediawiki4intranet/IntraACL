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

if (!defined('MEDIAWIKI'))
    die("This file is part of the IntraACL extension. It is not a valid entry point.");

/**
 * Base class for all exceptions of IntraACL.
 * @author Thomas Schweitzer
 * Date: 02.04.2009
 */
class HACLException extends Exception
{
    // An unknown user is given for a group 
    // Parameters:
    // 1 - name of the user
    const UNKNOWN_USER = 1;

    // An internal error occurred
    // Parameters:
    // 1 - Description of the internal error
    const INTERNAL_ERROR = 2;

    /**
     * Constructor of the IntraACL exception.
     *
     * @param string $message
     *         The error message
     * @param int $code
     *         A user defined error code.
     */
    public function __construct($args) {
        $code = 0;
        if (!is_array($args)) {
            $code = $args;
            $args = func_get_args();
        } else {
            // If the constructor is called from sub-classes, all parameters
            // are passed as array
            $code = $args[0];
        }
        $msg = $this->createMessage($args);

        // initialize super class
        parent::__construct($msg, $code);
    }

    protected function createMessage($args) {
        $msg = "";
        switch ($args[0]) {
            case self::UNKNOWN_USER:
                $msg = "The user $args[1] is unknown.";
                break;
               case self::INTERNAL_ERROR:
                $msg = "Internal error: $args[1]";
                break;
        }
        return $msg;
    }
}

/**
 * Exceptions for the operations on groups of IntraACL.
 * @author Thomas Schweitzer
 * Date: 02.04.2009
 */
class HACLGroupException extends HACLException
{
    //--- Constants ---

    // There is no article for the specified group.
    // Parameters:
    // 1 - name of the group
    const NO_GROUP_ID = 1;

    // An unauthorized user tries to modify the definition of a group.
    // Parameters:
    // 1 - name of the group
    // 2 - name of the user
    const USER_CANT_MODIFY_GROUP = 2;

    // An unknown group is given. It has no group ID.
    // Parameters:
    // 1 - name of the group
    const UNKNOWN_GROUP = 3;

    // There is no group for the given group ID.
    // Parameters:
    // 1 - ID of the group
    const INVALID_GROUP_ID = 4;

    /**
     * Constructor of the group exception.
     *
     * @param int $code
     *         A user defined error code.
     */
    public function __construct($code = 0)
    {
        $args = func_get_args();
        // initialize super class
        parent::__construct($args);
    }

    protected function createMessage($args)
    {
        $msg = "";
        switch ($args[0])
        {
            case self::NO_GROUP_ID:
                $msg = "The article for group $args[1] is not yet created.";
                break;
            case self::INVALID_GROUP_ID:
                $msg = "The group ID <$args[1]> is invalid.";
                break;
            case self::USER_CANT_MODIFY_GROUP:
                $msg = "The user $args[2] is not authorized to add or change the group $args[1].";
                break;
            case self::UNKNOWN_GROUP:
                $msg = "The group $args[1] does not exist. There is no article that defines this group.";
                break;
        }
        return $msg;
    }
}

/**
 * Exceptions for the operations on inline rights of IntraACL.
 * @author Thomas Schweitzer
 * Date: 20.04.2009
 */
class HACLRightException extends HACLException
{
    //--- Constants ---

    // There is no inline right with the given ID
    // Parameters:
    // 1 - ID of the inline right
    const UNKNOWN_RIGHT = 1;

    /**
     * Constructor of the SD exception.
     *
     * @param int $code
     *         A user defined error code.
     */
    public function __construct($code = 0)
    {
        $args = func_get_args();
        // initialize super class
        parent::__construct($args);
    }

    protected function createMessage($args)
    {
        $msg = "";
        switch ($args[0])
        {
            case self::UNKNOWN_RIGHT:
                $msg = "There is no inline right with the name or ID \"$args[1]\".";
                break;
        }
        return $msg;
    }
}

/**
 * Exceptions for the operations on security descriptors (SD) of IntraACL.
 * @author Thomas Schweitzer
 * Date: 16.04.2009
 */
class HACLSDException extends HACLException
{
    //--- Constants ---

    // There is no article for the specified SD.
    // Parameters:
    // 1 - name of the SD
    const NO_SD_ID = 1;

    // A unauthorized user tries to modify the definition of a SD.
    // Parameters:
    // 1 - name of the SD
    // 2 - name of the user
    const USER_CANT_MODIFY_SD = 2;

    // An unknown group is given for an SD.
    // Parameters:
    // 1 - name of the SD
    // 2 - name of the group
    const UNKNOWN_GROUP = 3;

    // There is no article or namespace for the specified protected element.
    // Parameters:
    // 1 - name of the protected element
    // 2 - type of the protected element
    const NO_PE_ID = 4;

    // There is no security descriptor with the given name or ID
    // Parameters:
    // 1 - name or ID of the SD
    const UNKNOWN_SD = 5;

    // An SD is added as child to an SD.
    // Parameters:
    // 1 - Name of the SD to which the other SD is added
    // 2 - Name of the SD that is added to the other SD
    const CANNOT_ADD_SD = 6;

    /**
     * Constructor of the SD exception.
     *
     * @param int $code
     *         A user defined error code.
     */
    public function __construct($code = 0)
    {
        $args = func_get_args();
        // initialize super class
        parent::__construct($args);
    }

    protected function createMessage($args)
    {
        $msg = "";
        switch ($args[0])
        {
            case self::NO_SD_ID:
                $msg = "The article for the security descriptor $args[1] is not yet created.";
                break;
            case self::USER_CANT_MODIFY_SD:
                $msg = "The user $args[2] is not authorized to change the security descriptor $args[1].";
                break;
            case self::UNKNOWN_GROUP:
                $msg = "The group $args[2] is unknown. It can not be used for security descriptor $args[1].";
                break;
            case self::NO_PE_ID:
                $msg = "The element \"$args[1]\" that shall be protected does not exist. It's requested type is \"$args[2]\"";
                break;
            case self::UNKNOWN_SD:
                $msg = "There is no security descriptor with the name or ID \"$args[1]\".";
                break;
            case self::CANNOT_ADD_SD:
                $msg = "You can not add the security descriptor \"$args[2]\" as right to the security descriptor \"$args[1]\"";
        }
        return $msg;
    }
}
