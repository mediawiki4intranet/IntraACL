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
 * This file provides the access to the database tables that are
 * used by the IntraACL extension.
 *
 * @author Thomas Schweitzer
 */

/**
 * This class encapsulates all methods that care about the database tables of
 * the IntraACL extension. It is a singleton that contains an instance
 * of the actual database access object e.g. the Mediawiki SQL database.
 */
class HACLStorage
{
    //--- Private fields---

    private static $mInstance; // HACLStorage: the only instance of this singleton
    private static $mDatabase; // The actual database object

    //--- Constructor ---

    /**
     * Constructor.
     * Creates the object that handles the concrete database access.
     *
     */
    private function __construct()
    {
        global $haclgIP;
        if (self::$mDatabase == NULL)
        {
            global $haclgBaseStore;
            switch ($haclgBaseStore)
            {
                case (HACL_STORE_SQL):
                    require_once("$haclgIP/storage/HACL_StorageSQL.php");
                    self::$mDatabase = new HACLStorageSQL();
                break;
            }
        }
    }

    //--- Public methods ---

    /**
     * Returns the single instance of this class.
     *
     * @return HACLStorage
     *         The single instance of this class.
     */
    public static function getInstance()
    {
        if (!isset(self::$mInstance))
        {
            $c = __CLASS__;
            self::$mInstance = new $c;
        }
        return self::$mInstance;
    }

    /**
     * Returns the actual database.
     *
     * @return object
     *         The object to access the database.
     */
    public static function getDatabase()
    {
        self::getInstance(); // Make sure, singleton is initialized
        return self::$mDatabase;
    }
}
