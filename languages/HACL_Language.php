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
    ///////////////
    // CONSTANTS //
    ///////////////

    //--- IDs of parser functions ---
    const PF_ACCESS             = 1;
    const PF_MANAGE_RIGHTS      = 2;
    const PF_MANAGE_GROUP       = 3;
    const PF_PREDEFINED_RIGHT   = 4;
    const PF_MEMBER             = 7;

    //--- IDs of parser function parameters ---
    const PFP_ASSIGNED_TO       = 8;
    const PFP_ACTIONS           = 9;
    const PFP_DESCRIPTION       = 10;
    const PFP_RIGHTS            = 11;
    const PFP_MEMBERS           = 13;
    const PFP_NAME              = 14;

    //---- Actions ----
    // RIGHT_FORMEDIT, RIGHT_WYSIWYG and RIGHT_ANNOTATE are considered useless and removed
    // RIGHT_MANAGE is the right to edit the security descriptor.
    // RIGHT_MANAGE must not be confused with {{#manage rights: }} ^-)
    // RIGHT_MANAGE is inherited through predefined right inclusion, while {{#manage rights: }} is not.
    // RIGHT_MANAGE does not have the effect on predefined rights itself, while {{#manage rights: }} has.
    const RIGHT_MANAGE      = 0x80;
    const RIGHT_ALL_ACTIONS = 0x1F; // all = read + edit + create + move + delete
    const RIGHT_READ        = 0x10; // read page
    const RIGHT_EDIT        = 0x08; // edit page
    const RIGHT_CREATE      = 0x04; // create new page
    const RIGHT_MOVE        = 0x02; // move(rename) page
    const RIGHT_DELETE      = 0x01; // delete page

    //---- Types of protected elements ----
    const PET_PAGE      = 'page';       // Protect pages
    const PET_CATEGORY  = 'category';   // Protect instances of a category
    const PET_NAMESPACE = 'namespace';  // Protect instances of a namespace
    const PET_RIGHT     = 'right';      // Not an actual SD but a right template equal to SD by structure

    //////////////////////////////////
    // LANGUAGE-INDEPENDENT ALIASES //
    //////////////////////////////////

    // THESE ARE NOT CONSTANTS, BUT WE STRONGLY RECOMMEND
    // NOT TO OVERRIDE THESE VALUES (it is logically to have
    // language-independent parser function names and parameters and etc):

    // Default content for "Permission denied" page, is filled during installation
    public $mPermissionDeniedPageContent = "{{:MediaWiki:hacl_permission_denied}}";

    // Parser function names
    public $mParserFunctions = array(
        self::PF_ACCESS             => 'access',
        self::PF_MANAGE_RIGHTS      => 'manage rights',
        self::PF_MANAGE_GROUP       => 'manage group',
        self::PF_PREDEFINED_RIGHT   => 'predefined right',
        self::PF_MEMBER             => 'member'
    );

    // Parser function parameter names
    public $mParserFunctionsParameters = array(
        self::PFP_ASSIGNED_TO   => 'assigned to',
        self::PFP_ACTIONS       => 'actions',
        self::PFP_DESCRIPTION   => 'description',
        self::PFP_RIGHTS        => 'rights',
        self::PFP_MEMBERS       => 'members',
        self::PFP_NAME          => 'name'
    );

    // Action names
    public $mActionNames = array(
        self::RIGHT_READ        => 'read',
        self::RIGHT_EDIT        => 'edit',
        self::RIGHT_CREATE      => 'create',
        self::RIGHT_MOVE        => 'move',
        self::RIGHT_DELETE      => 'delete',
        self::RIGHT_ALL_ACTIONS => '*',
        self::RIGHT_MANAGE      => 'manage',
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

    // SD page prefixes (ACL:Prefix/Name) for different protected element types
    public $mPetPrefixes = array(
        self::PET_PAGE      => 'Page',
        self::PET_CATEGORY  => 'Category',
        self::PET_NAMESPACE => 'Namespace',
        self::PET_RIGHT     => 'Right',
    );

    // Group page prefix (ACL:Group/Name)
    public $mGroupPrefix = 'Group';

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
     * This method returns the language dependent name of a parser function.
     *
     * @param  int $parserFunctionID
     *         ID of the parser function i.e. one of self::PF_*
     *
     * @return string
     *         The language dependent name of the parser function.
     */
    public function getParserFunction($parserFunctionID)
    {
        return $this->mParserFunctions[$parserFunctionID];
    }

    /**
     * This method returns the language dependent name of a parser function
     * parameter.
     *
     * @param int $parserFunctionParameterID
     *         ID of the parser function parameter i.e. one of self::PFP_*
     *
     * @return string
     *         The language dependent name of the parser function.
     */
    public function getParserFunctionParameter($parserFunctionParameterID)
    {
        return $this->mParserFunctionsParameters[$parserFunctionParameterID];
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
