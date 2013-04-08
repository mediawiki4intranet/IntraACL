<?php

/* Copyright 2010+, Vitaliy Filippov <vitalif[d.o.g]mail.ru>
 *                  Stas Fomin <stas.fomin[d.o.g]yandex.ru>
 * This file is part of IntraACL MediaWiki extension. License: GPLv3.
 * http://wiki.4intra.net/IntraACL
 * $Id$
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

/**
 * HACLLanguage is the class where all shared constants and important
 * language-specific names live.
 * All properties are public, but use getters instead to get a runtime error
 * when trying to access non-existing property.
 */

/**
 * Security descriptors protect different types of elements i.e. pages,
 * instances of categories and namespaces. The name of a security descriptor
 * has a prefix that matches this type. The prefix depends on the language.
 *
 * Example: ACL:Page/X is the security descriptor for page X. The prefix is
 *          "Page".
 */

abstract class HACLLanguage
{
    //////////////////////////////////
    // LANGUAGE-INDEPENDENT ALIASES //
    //////////////////////////////////

    // THESE ARE NOT CONSTANTS, BUT WE STRONGLY RECOMMEND
    // NOT TO OVERRIDE THESE VALUES (it is logically to have
    // language-independent parser function names and parameters and etc):

    // Default content for "Permission denied" page, is filled during installation
    public $mPermissionDeniedPageContent = "{{:MediaWiki:hacl_permission_denied}}";

    // Action names
    public $mActionNames = array(
        IACL::RIGHT_READ            => 'read',
        IACL::RIGHT_EDIT            => 'edit',
        IACL::RIGHT_CREATE          => 'create',
        IACL::RIGHT_MOVE            => 'move',
        IACL::RIGHT_DELETE          => 'delete',
        IACL::RIGHT_ALL_ACTIONS     => '*',
        IACL::RIGHT_MANAGE          => 'manage',
        IACL::RIGHT_PROTECT_PAGES   => 'protect_pages',
    );

    // Lookup array: ACL:Prefix/Name --> lowercased prefix --> type (sd, group, right)
    public $mPrefixes = array();

    // Lookup array: lowercased action name --> action ID
    public $mActionAliases = array();

    ////////////////////////////////
    // LANGUAGE-DEPENDENT ALIASES //
    ////////////////////////////////

    // THESE ARE RECOMMENDED TO BE OVERRIDDEN IN SUBCLASSES

    // IntraACL namespaces and aliases
    public $mNamespaces = array(
        HACL_NS_ACL       => 'ACL',
        HACL_NS_ACL_TALK  => 'ACL_talk'
    );

    // IntraACL namespace aliases, is appended to $wgNamespaceAliases
    public $mNamespaceAliases = array();

    // "Permission denied" page, inaccessible Title's are replaced with it
    public $mPermissionDeniedPage = 'Permission denied';

    // SD page prefixes (ACL:<Prefix>/<Name>) for different protected element types
    public $mPetPrefixes = array(
        IACL::PE_PAGE       => 'Page',
        IACL::PE_CATEGORY   => 'Category',
        IACL::PE_NAMESPACE  => 'Namespace',
        IACL::PE_RIGHT      => 'Right',
        IACL::PE_GROUP      => 'Group',
    );

    // Lookup array: ACL:Prefix/Name --> lowercased prefix --> protected element type constant
    // Add language-dependent protected element type names here
    public $mPetAliases = array();

    //////////////////////////////////////
    // CONSTRUCTOR, fills lookup arrays //
    //////////////////////////////////////

    public function __construct()
    {
        foreach ($this->mPetPrefixes as $id => $prefix)
            $this->mPetAliases[mb_strtolower($prefix)] = $id;
        foreach ($this->mPetAliases as $prefix => $id)
            $this->mPrefixes[$prefix] = $id == self::PET_RIGHT ? 'right' : 'sd';
        $this->mPrefixes[mb_strtolower($this->mGroupPrefix)] = 'group';
        foreach ($this->mActionNames as $id => $name)
            $this->mActionAliases[mb_strtolower($name)] = $id;
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

    /**
     * This method returns the language dependent names of all actions that
     * are used in rights.
     *
     * @return array(int => string)
     *         A mapping from action IDs to action names.
     *         The possible IDs are HACLLanguage::RIGHT_*
     */
    public function getActionNames()
    {
        return $this->mActionNames;
    }

    // Get self::RIGHT_* action ID by action name $name
    public function getActionId($name)
    {
        if (isset($this->mActionAliases[mb_strtolower($name)]))
            return $this->mActionAliases[mb_strtolower($name)];
        return false;
    }

    public function getPetPrefix($type)
    {
        return $this->mPetPrefixes[$type];
    }

    public function getPetPrefixes()
    {
        return $this->mPetPrefixes;
    }

    public function getPrefix($prefix)
    {
        if (isset($this->mPrefixes[mb_strtolower($prefix)]))
            return $this->mPrefixes[mb_strtolower($prefix)];
        return false;
    }

    public function getPrefixes()
    {
        return $this->mPrefixes;
    }

    public function getPetAlias($alias)
    {
        if (isset($this->mPetAliases[mb_strtolower($alias)]))
            return $this->mPetAliases[mb_strtolower($alias)];
        return false;
    }

    public function getPetAliases()
    {
        return $this->mPetAliases;
    }

    public function getGroupPrefix()
    {
        return $this->mGroupPrefix;
    }
}
