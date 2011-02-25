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
