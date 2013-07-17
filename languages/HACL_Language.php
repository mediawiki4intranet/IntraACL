<?php

/**
 * Copyright 2013+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *                  Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 * This file is part of IntraACL MediaWiki extension. License: GPLv3.
 * Homepage: http://wiki.4intra.net/IntraACL
 *
 * Loosely based on HaloACL (c) 2009, ontoprise GmbH
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * HACLLanguage is the class where all shared constants and important
 * language-specific names live.
 *
 * All properties are public, but use getters instead to get a runtime error
 * when trying to access non-existing property.
 */
abstract class HACLLanguage
{
    //////////////////////////////////
    // LANGUAGE-INDEPENDENT ALIASES //
    //////////////////////////////////

    // DO NOT OVERRIDE THESE VALUES

    // Default content for "Permission denied" page, is filled during installation
    public $mPermissionDeniedPageContent = "{{:MediaWiki:hacl_permission_denied}}";

    // SD page prefixes (ACL:<Prefix>/<Name>) for different protected element types
    public $mPetPrefixes = array(
        IACL::PE_PAGE       => 'Page',
        IACL::PE_CATEGORY   => 'Category',
        IACL::PE_NAMESPACE  => 'Namespace',
        IACL::PE_RIGHT      => 'Right',
        IACL::PE_GROUP      => 'Group',
    );

    // Action names
    public $mActionNames = array(
        IACL::ACTION_READ            => 'read',
        IACL::ACTION_EDIT            => 'edit',
        IACL::ACTION_CREATE          => 'create',
        IACL::ACTION_MOVE            => 'move',
        IACL::ACTION_DELETE          => 'delete',
        IACL::ACTION_FULL_ACCESS     => '*',
        IACL::ACTION_MANAGE          => 'manage',
        IACL::ACTION_PROTECT_PAGES   => 'protect_pages',
    );

    // Lookup array: lowercased action name --> action ID
    public $mActionAliases = array();

    ////////////////////////////////
    // LANGUAGE-DEPENDENT ALIASES //
    ////////////////////////////////

    // IntraACL namespaces and aliases
    public $mNamespaces = array(
        HACL_NS_ACL       => 'ACL',
        HACL_NS_ACL_TALK  => 'ACL_talk'
    );

    // IntraACL namespace aliases, is appended to $wgNamespaceAliases
    public $mNamespaceAliases = array();

    // "Permission denied" page, inaccessible Title's are replaced with it
    public $mPermissionDeniedPage = 'Permission denied';

    // Add language-dependent protected element type names here
    // [ lowercased localized alias => IACL::PE_* ]
    //
    // Only for compatibility!
    // From now all ACL title prefixes are always english.
    // (because they're stored as-is in the DB, and won't survive $wgContLang change otherwise)
    public $mPetAliases = array();

    //////////////////////////////////////
    // CONSTRUCTOR, fills lookup arrays //
    //////////////////////////////////////

    public function __construct()
    {
        foreach ($this->mPetPrefixes as $id => $prefix)
        {
            $this->mPetAliases[mb_strtolower($prefix)] = $id;
        }
        foreach ($this->mActionNames as $id => $name)
        {
            $this->mActionAliases[mb_strtolower($name)] = $id;
        }
    }

    /////////////
    // GETTERS //
    /////////////

    public function getNamespaces()
    {
        return $this->mNamespaces;
    }

    public function getNamespaceAliases()
    {
        return $this->mNamespaceAliases;
    }

    public function getPermissionDeniedPage()
    {
        return $this->mPermissionDeniedPage;
    }

    public function getPermissionDeniedPageContent()
    {
        return $this->mPermissionDeniedPageContent;
    }

    // Get self::ACTION_* action ID by action name $name
    public function getActionId($name)
    {
        if (isset($this->mActionAliases[mb_strtolower($name)]))
        {
            return $this->mActionAliases[mb_strtolower($name)];
        }
        return false;
    }

    public function getPetPrefix($type)
    {
        if (!$type)
        {
            throw new Exception('BUG: Empty $type parameter passed to '.__METHOD__);
        }
        return $this->mPetPrefixes[$type];
    }

    public function getPetPrefixes()
    {
        return $this->mPetPrefixes;
    }

    public function getPetAlias($alias)
    {
        if (isset($this->mPetAliases[mb_strtolower($alias)]))
        {
            return $this->mPetAliases[mb_strtolower($alias)];
        }
        return false;
    }

    public function getPetAliases()
    {
        return $this->mPetAliases;
    }
}
