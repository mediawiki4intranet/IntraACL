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
 * Internationalization file for IntraACL
 * @author Vitaliy Filippov
 * Based on HaloACL HACL_Messages.php
 */

$messages = array();

/**
 * English
 */
$messages['en'] = array(

    // General
    'intraacl'                          => 'IntraACL',
    'hacl_special_page'                 => 'IntraACL',  // Name of the special page for administration
    'specialpages-group-hacl_group'     => 'IntraACL',
    'hacl_unknown_user'                 => 'The user "$1" is unknown.',
    'hacl_unknown_group'                => 'The group "$1" is unknown.',
    'hacl_missing_parameter'            => 'The parameter "$1" is missing.',
    'hacl_missing_parameter_values'     => 'There are no valid values for parameter "$1".',
    'hacl_invalid_predefined_right'     => 'A rights template with the name "$1" does not exist or it contains no valid rights definition.',
    'hacl_invalid_action'               => '"$1" is an invalid value for an action.',
    'hacl_wrong_namespace'              => 'Articles with rights or group definitions must belong to the namespace "ACL".',
    'hacl_group_must_have_members'      => 'A group must have at least one member (group or user).',
    'hacl_group_must_have_managers'     => 'A group must have at least one manager (group or user).',
    'hacl_invalid_parser_function'      => 'The use of the "#$1" function in this article is not allowed.',
    'hacl_right_must_have_rights'       => 'A right or security descriptor must contain rights or reference other rights.',
    'hacl_right_must_have_managers'     => 'A right or security descriptor must have at least one manager (group or user).',
    'hacl_pf_rightname_title'           => "===$1===\n",
    'hacl_pf_rights_title'              => "===Right(s): $1===\n",
    'hacl_pf_rights'                    => ":;Right(s):\n:: $1\n",
    'hacl_pf_right_managers_title'      => "===Right managers===\n",
    'hacl_pf_predefined_rights_title'   => "===Included rights===\n",
    'hacl_pf_group_managers_title'      => "===Group managers===\n",
    'hacl_pf_group_members_title'       => "===Group members===\n",
    'hacl_assigned_user'                => 'Assigned users: ',
    'hacl_assigned_groups'              => 'Assigned groups:',
    'hacl_user_member'                  => 'Users who are member of this group:',
    'hacl_group_member'                 => 'Groups who are member of this group:',
    'hacl_description'                  => 'Description:',
    'hacl_error'                        => 'Errors:',
    'hacl_warning'                      => 'Warnings:',
    'hacl_consistency_errors'           => '<h2>There are errors in ACL definition</h2>',
    'hacl_definitions_will_not_be_saved' => '(The definitions in this article will not be saved and they will not be taken to effect due to the following errors.)',
    'hacl_will_not_work_as_expected'    => '(Because of the following warnings, the definition will not work as expected.)',
    'hacl_errors_in_definition'         => 'The definitions in this article have errors. Please refer to the details below!',
    'hacl_all_users'                    => 'all users',
    'hacl_registered_users'             => 'registered users',
    'hacl_acl_element_not_in_db'        => 'No entry has been made in the ACL database about this article. Please re-save it again with all the articles that use it.',
    'hacl_acl_element_inconsistent'     => 'This article contains an inconsistent definition. Please re-save it.',
    'hacl_unprotectable_namespace'      => 'This namespace cannot be protected. Please contact the wiki administrator.',
    'hacl_permission_denied'            => "You are not allowed to perform the requested action on this page.\n\nReturn to [[Main Page]].",
    'hacl_move_acl'                     => 'ACL moved with the article',
    'hacl_move_acl_include'             => 'Include move target rights (protect RC)',

    'hacl_nonreadable_create'           =>
'<div style="border: 0.2em solid red; padding: 0 0.5em 0.5em">
<span style="color: red; font-weight: bold">Warning!</span>
You have no read access to the namespace inside which you are creating this article.
<ul><li>Either include the article into one of categories readable by you: $1</li>
<li>Or check "<b>Create a non-readable article</b>"</li></ul>
</div>',

    'hacl_nonreadable_create_nocat'     =>
'<div style="border: 0.2em solid red; padding: 0 0.5em 0.5em">
<span style="color: red; font-weight: bold">Warning!</span>
You have no read access to the namespace inside which you are creating this article.
Please check "<b>Create a non-readable article</b>" to confirm your intention,
because there also are no categories readable for you.
</div>',

    'hacl_nonreadable_upload'           => '<div style="border: 0.2em solid red; padding: 0.2em 0.5em 0.5em; display: inline-block; width: 50%">
<span style="color: red; font-weight: bold">Warning!</span>
You have no read access to the <tt>File</tt> namespace.<br />
Please, add one of the following categories to the description, or you will <b>not</b> be able to view the file after uploading:&nbsp;$1
</div>',

    'hacl_nonreadable_upload_nocat'     => '<div style="border: 0.2em solid red; padding: 0.2em 0.5em 0.5em; display: inline-block; width: 50%">
<span style="color: red; font-weight: bold">Warning!</span>
You have no read access to the <tt>File</tt> namespace. You will <b>not</b> be able to view the file after uploading!
</div>',

    /**** IntraACL: ****/

    'tog-showacltab'                    => 'Always show ACL tab (page access rights)',

    // General
    'hacl_invalid_prefix'               =>
'This page does not protect anything, create any rights or right templates.
Either it is supposed to be included into other ACL definitions, or is created incorrectly.
If you want to protect some pages, ACL page must be named as one of: ACL:Page/*, ACL:Category/*, ACL:Namespace/*, ACL:Right/*.',
    'hacl_pe_not_exists'                => 'The element supposed to be protected with this ACL does not exist.',
    'hacl_edit_with_special'            => '<p><a href="$1"><img src="$2" width="16" height="16" alt="Edit" /> Edit this definition with IntraACL editor.</a></p><hr />',
    'hacl_create_with_special'          => '<p><a href="$1"><img src="$2" width="16" height="16" alt="Create" /> Create this definition with IntraACL editor.</a></p><hr />',
    'hacl_tab_acl'                      => 'ACL',
    'hacl_tab_page'                     => 'Page',
    'hacl_tab_category'                 => 'Category',

    // Special:IntraACL actions
    'hacl_action_acllist'               => 'Manage ACL',
    'hacl_action_acl'                   => 'Create new ACL definition',
    'hacl_action_quickaccess'           => 'Manage Quick ACL',
    'hacl_action_grouplist'             => 'Manage groups',
    'hacl_action_group'                 => 'Create a group',
    'hacl_action_rightgraph'            => 'View rights on a graph',

    // ACL Editor
    'hacl_autocomplete_no_users'        => 'No users found',
    'hacl_autocomplete_no_groups'       => 'No groups found',
    'hacl_autocomplete_no_pages'        => 'No pages found',
    'hacl_autocomplete_no_namespaces'   => 'No namespaces found',
    'hacl_autocomplete_no_categorys'    => 'No categories found',
    'hacl_autocomplete_no_sds'          => 'No security descriptors found',

    'hacl_login_first_title'            => 'Please login',
    'hacl_login_first_text'             => 'Please [[Special:Userlogin|login]] first to use IntraACL special page.',
    'hacl_acl_create'                   => 'Create ACL definition',
    'hacl_acl_create_title'             => 'Create ACL definition: $1',
    'hacl_acl_edit'                     => 'Editing ACL definition: $1',
    'hacl_edit_definition_text'         => 'Definition text:',
    'hacl_edit_definition_target'       => 'Definition target:',
    'hacl_edit_modify_definition'       => 'Modify definition:',
    'hacl_edit_include_right'           => 'Include other SD:',
    'hacl_edit_include_do'              => 'Include',
    'hacl_edit_save'                    => 'Save ACL',
    'hacl_edit_create'                  => 'Create ACL',
    'hacl_edit_delete'                  => 'Delete&nbsp;ACL',
    'hacl_edit_protect'                 => 'Protect:',
    'hacl_edit_define'                  => 'Define:',

    'hacl_indirect_grant'               => 'This right is granted through $1, cannot revoke.',
    'hacl_indirect_grant_all'           => 'all users right',
    'hacl_indirect_grant_reg'           => 'all registered users right',
    'hacl_indirect_through'             => '(through $1)',

    'hacl_edit_sd_exists'               => 'This definition already exists.',
    'hacl_edit_enter_name_first'        => 'Error: Enter name to save ACL!',
    'hacl_edit_define_rights'           => 'Error: ACL must include at least 1 right!',
    'hacl_edit_define_manager'          => 'Warning: If ACL does not include any manage rights, it can only be modified by administrators and users who have the right through namespace or category right.',
    'hacl_edit_define_manager_np'       => 'Warning: If ACL does not include any manage rights, it can only be modified by administrators.',
    'hacl_edit_define_tmanager'         => 'Warning: If ACL template does not include any template manage rights, it can only be modified by administrators.',

    'hacl_start_typing_page'            => 'Start typing to display page list...',
    'hacl_start_typing_category'        => 'Start typing to display category list...',
    'hacl_start_typing_user'            => 'Start typing to display user list...',
    'hacl_start_typing_group'           => 'Start typing to display group list...',
    'hacl_edit_users_affected'          => 'Users affected:',
    'hacl_edit_groups_affected'         => 'Groups affected:',
    'hacl_edit_no_users_affected'       => 'No users affected.',
    'hacl_edit_no_groups_affected'      => 'No groups affected.',
    'hacl_edit_goto_group'              => 'Go to group $1 definition',

    'hacl_edit_user'                    => 'User',
    'hacl_edit_group'                   => 'Group',
    'hacl_edit_all'                     => 'All users',
    'hacl_edit_reg'                     => 'Registered users',

    'hacl_edit_action_all'              => 'All',
    'hacl_edit_action_manage'           => 'Manage pages',
    'hacl_edit_action_template'         => 'Manage template',
    'hacl_edit_action_read'             => 'Read',
    'hacl_edit_action_edit'             => 'Edit',
    'hacl_edit_action_create'           => 'Create',
    'hacl_edit_action_delete'           => 'Delete',
    'hacl_edit_action_move'             => 'Move',

    'hacl_edit_ahint_all'               => 'All page access rights: read, edit, create, delete, move.',
    'hacl_edit_ahint_manage'            => 'This is the inheritable manage right. It allows changing other SDs, except right templates, which include this one, or SDs for pages belonging to given category/namespace. But, it DOES NOT allow changing this definition, if it is not a page right.',
    'hacl_edit_ahint_template'          => 'This is the non-inheritable manage right. It has no effect when included into some other SD, but always allows changing THIS definition.',
    'hacl_edit_ahint_read'              => 'This is the right to read pages.',
    'hacl_edit_ahint_edit'              => 'This is the right to edit pages.',
    'hacl_edit_ahint_create'            => 'This is the right to create new articles within given namespace.', // FIXME ( ... and category )
    'hacl_edit_ahint_delete'            => 'This is the right to delete existing pages.',
    'hacl_edit_ahint_move'              => 'This is the right to move (rename) existing pages.',

    'hacl_define_page'                  => 'Protect page:',
    'hacl_define_namespace'             => 'Protect namespace:',
    'hacl_define_category'              => 'Protect category:',
    'hacl_define_right'                 => 'Define right:',

    // ACL list
    'hacl_acllist'                      => 'Intranet Access Control Lists',
    'hacl_acllist_hello'                => 'Hi, this is \'\'\'[http://wiki.4intra.net/IntraACL IntraACL]\'\'\', the best MediaWiki rights extension. You can get help [http://wiki.4intra.net/IntraACL here]. Select function below to start working:',
    'hacl_acllist_empty'                => '<span style="color:red;font-weight:bold">No matching ACL definitions found.</span>',
    'hacl_acllist_filter_name'          => 'Filter by name:',
    'hacl_acllist_filter_type'          => 'Filter by type:',
    'hacl_acllist_hint_single'          => 'The ACL $1 is just an inclusion of $2.',
    'hacl_acllist_perpage'              => 'On the page:',
    'hacl_acllist_result_page'          => 'Pages:',
    'hacl_acllist_prev'                 => '&larr; Previous page',
    'hacl_acllist_next'                 => 'Next page &rarr;',
    'hacl_acllist_typegroup_all'        => 'All definitions',
    'hacl_acllist_typegroup_protect'    => 'Rights for:',
    'hacl_acllist_typegroup_define'     => 'Templates:',

    'hacl_acllist_type_page'            => 'Page',
    'hacl_acllist_type_namespace'       => 'Namespace',
    'hacl_acllist_type_category'        => 'Category',
    'hacl_acllist_type_right'           => 'Predefined rights',
    'hacl_acllist_type_template'        => 'User templates',

    'hacl_acllist_page'                 => 'Rights for pages:',
    'hacl_acllist_namespace'            => 'Rights for namespaces:',
    'hacl_acllist_category'             => 'Rights for categories:',
    'hacl_acllist_right'                => 'Predefined rights:',
    'hacl_acllist_edit'                 => 'Edit',
    'hacl_acllist_view'                 => 'View',

    // Quick ACL list editor
    'hacl_qacl_filter_sds'              => 'Filter templates by name',
    'hacl_qacl_filter'                  => 'Name starts with:',
    'hacl_qacl_filter_submit'           => 'Apply',
    'hacl_qacl_manage'                  => 'IntraACL: Manage Quick Access list',
    'hacl_qacl_manage_text'             =>
        'This is a list of all the ACL templates that you can use in your quick access list.
        Quick ACL will be shown in the dropdown box in protection toolbar every time you edit some page.
        The template marked as default will be selected by default for new pages.',
    'hacl_qacl_save'                    => 'Save selections',
    'hacl_qacl_hint'                    => 'Select some ACL templates and then click Save selections:',
    'hacl_qacl_empty'                   => 'There are no ACL templates available for Quick Access. Create one using <b>Create new ACL definition</b>.',
    'hacl_qacl_empty_default'           => 'No protection',
    'hacl_qacl_col_select'              => 'Select',
    'hacl_qacl_col_default'             => 'Default',
    'hacl_qacl_col_name'                => 'Name',
    'hacl_qacl_col_actions'             => 'Actions',

    // Group list
    'hacl_grouplist'                    => 'IntraACL Groups',
    'hacl_grouplist_filter_name'        => 'Name contains:',
    'hacl_grouplist_filter_not_name'    => 'Name does not contain:',
    'hacl_grouplist_empty'              => '<span style="color:red;font-weight:bold">No matching IntraACL groups found.</span>',
    'hacl_grouplist_view'               => 'View',
    'hacl_grouplist_edit'               => 'Edit',

    // Group editor
    'hacl_grp_creating'                 => 'Create IntraACL group',
    'hacl_grp_editing'                  => 'Editing IntraACL group: $1',
    'hacl_grp_create'                   => 'Create group',
    'hacl_grp_save'                     => 'Save group',
    'hacl_grp_delete'                   => 'Delete group',
    'hacl_grp_name'                     => 'Group name:',
    'hacl_grp_definition_text'          => 'Group definition text:',
    'hacl_grp_member_all'               => 'All users',
    'hacl_grp_member_reg'               => 'All registered users',
    'hacl_grp_members'                  => 'Group members:',
    'hacl_grp_managers'                 => 'Group managers:',
    'hacl_grp_users'                    => 'Users:',
    'hacl_grp_groups'                   => 'Groups:',

    'hacl_grp_exists'                   => 'This group already exists.',
    'hacl_grp_enter_name_first'         => 'Error: Enter name to save group!',
    'hacl_grp_define_members'           => 'Error: Group must have at least 1 member!',
    'hacl_grp_define_managers'          => 'Error: Group must have at least 1 manager!',

    'hacl_no_member_user'               => 'No member users by now.',
    'hacl_no_member_group'              => 'No member groups by now.',
    'hacl_no_manager_user'              => 'No manager users by now.',
    'hacl_no_manager_group'             => 'No manager groups by now.',
    'hacl_current_member_user'          => 'Member users:',
    'hacl_current_member_group'         => 'Member groups:',
    'hacl_current_manager_user'         => 'Manager users:',
    'hacl_current_manager_group'        => 'Manager groups:',
    'hacl_regexp_user'                  => '',
    'hacl_regexp_group'                 => '(^|,\s*)Group:',

    // Toolbar and parts
    'hacl_toolbar_advanced_edit'        => 'Edit ACL',
    'hacl_toolbar_advanced_create'      => 'Create ACL',
    'hacl_toolbar_goto'                 => 'Go to $1.',
    'hacl_toolbar_global_acl'           => 'Additional ACL &darr;',
    'hacl_toolbar_global_acl_tip'       => 'These definitions also have effect on this page:',
    'hacl_toolbar_embedded_acl'         => 'Used content &darr;',
    'hacl_toolbar_loading'              => 'Loading...',
    'hacl_toolbar_page_prot'            => 'Page protection:',
    'hacl_toolbar_cannot_modify'        => 'You can not modify page protection.',
    'hacl_toolbar_no_right_templates'   => 'No custom page rights.',
    'hacl_toolbar_unprotected'          => 'No custom rights',
    'hacl_toolbar_used_on'              => 'used on <a href="$2">$1 pages</a>',
    'hacl_toolbar_protect_embedded'     => 'Protect linked images and templates with same SD (will <span style="color:red;font-weight:bold">overwrite</span> any defined SD):',
    'hacl_toolbar_emb_already_prot'     => 'already protected',
    'hacl_toolbar_emb_custom_prot'      => '<a href="$1">custom SD</a> defined',
    'hacl_toolbar_emb_all'              => 'Overwrite <span style="color:red;font-weight:bold">all</span> SDs',
    'hacl_toolbar_emb_all_already'      => 'All elements are protected',
    'hacl_toolbar_qacl'                 => 'Manage Quick ACL',
    'hacl_toolbar_select_qacl'          => 'To create a protected page, first select some <a href="$1">Quick ACL</a>.',
    'hacl_toolbar_qacl_title'           => 'Manage the list of templates always available in the select box.',
    'hacl_comment_protect_with'         => 'Page protected with $1.',
    'hacl_comment_unprotect'            => 'Custom page rights removed.',
    'hacl_comment_protect_embedded'     => 'Page protected with $1 as an embedded element.',
    'hacl_embedded_error_canedit'       => 'access denied',
    'hacl_embedded_error_invalidsd'     => 'invalid protection requested',
    'hacl_embedded_error_midair'        => 'mid-air collision',
    'hacl_embedded_not_saved_title'     => 'Embedded content rights not saved',
    'hacl_embedded_not_saved'           => 'Rights for the following embedded (linked) elements are not saved: $1.

Possible reasons for this may be:
* Access denied: You have no rights to modify rights of some of these elements.
* Edit conflict: Security descriptor(s) for some of these elements could be modified by someone in the meantime.
* Element deleted: Some of these elements could be deleted in the meantime.
* Invalid protection requested: Most probably, the form was submitted incorrectly.

Please review these elements, return to [[$2]] and protect them again.',
);

/**
 * Russian
 */
$messages['ru'] = array(

    // General
    'hacl_unknown_user'                 => 'Пользователя "$1" не существует.',
    'hacl_unknown_group'                => 'Группы "$1" не существует.',
    'hacl_missing_parameter'            => 'Не хватает параметра "$1".',
    'hacl_missing_parameter_values'     => 'Некорректное значение параметра "$1".',
    'hacl_invalid_predefined_right'     => 'Включаемое определение "$1" не существует или некорректно.',
    'hacl_invalid_action'               => 'Действия "$1" не существует.',
    'hacl_wrong_namespace'              => 'Страницы с определениями прав или групп должны быть в пространстве имён "ACL".',
    'hacl_group_must_have_members'      => 'В группу должен кто-то входить (пользователь или другая группа).',
    'hacl_group_must_have_managers'     => 'Кому-то должно быть разрешено править группу (пользователю или другой группе).',
    'hacl_invalid_parser_function'      => 'В данной статье нельзя использовать функцию "#$1".',
    'hacl_right_must_have_rights'       => 'Определение прав должно содержать хотя бы одно право или включение других прав.',
    'hacl_right_must_have_managers'     => 'Должны быть заданы права модификации, чтобы кто-нибудь смог изменять это право.',
    'hacl_pf_rightname_title'           => "===$1===\n",
    'hacl_pf_rights_title'              => "===Права: $1===\n",
    'hacl_pf_rights'                    => ":;Права:\n:: $1\n",
    'hacl_pf_right_managers_title'      => "===Могут изменять права===\n",
    'hacl_pf_predefined_rights_title'   => "===Включения прав===\n",
    'hacl_pf_group_managers_title'      => "===Могут изменять группу===\n",
    'hacl_pf_group_members_title'       => "===Члены группы===\n",
    'hacl_assigned_user'                => 'Пользователи:',
    'hacl_assigned_groups'              => 'Группы:',
    'hacl_user_member'                  => 'Эти пользователи входят в группу:',
    'hacl_group_member'                 => 'Эти группы входят в группу:',
    'hacl_description'                  => 'Описание:',
    'hacl_error'                        => 'Ошибки:',
    'hacl_warning'                      => 'Предупреждения:',
    'hacl_consistency_errors'           => '<h2>Определение содержит ошибки</h2>',
    'hacl_definitions_will_not_be_saved' => '(Определение на данной странице не будет сохранено и не будет работать из-за следующих ошибок:)',
    'hacl_will_not_work_as_expected'    => '(Следующие ошибки некритичны, но из-за них определение может не работать так, как задумано:)',
    'hacl_errors_in_definition'         => 'Определение на данной странице содержит ошибки, обратите внимание на подробности ниже!',
    'hacl_all_users'                    => 'все пользователи',
    'hacl_registered_users'             => 'зарегистрированные пользователи',
    'hacl_acl_element_not_in_db'        => 'Эта статья не сохранена в базе данных прав. Пожалуйста, пересохраните её.',
    'hacl_acl_element_inconsistent'     => 'Это определение в БД не соответствует определению на странице. Пожалуйста, пересохраните страницу.',
    'hacl_unprotectable_namespace'      => 'Это пространство имён относится к незащищаемым. Обратитесь к администраторам MediaWiki.',
    'hacl_permission_denied'            => "Вам запрещено это действие на данной странице.\n\nВернуться на [[Заглавная страница|главную страницу]].",
    'hacl_move_acl'                     => 'Права перемещены вместе со страницей',
    'hacl_move_acl_include'             => 'Включение прав перемещённой страницы (защита RC)',

    'hacl_nonreadable_create'           =>
'<div style="border: 0.2em solid red; padding: 0 0.5em 0.5em">
<span style="color: red; font-weight: bold">Внимание!</span>
Вы создаёте статью в пространстве имён, на чтение которого не имеете доступа.
<ul><li>Либо включите статью в одну из категорий, к которым имеете доступ на чтение: $1</li>
<li>Либо отметьте флажок "<b>Создать нечитаемую статью</b>"</li></ul>
</div>',

    'hacl_nonreadable_create_nocat'     =>
'<div style="border: 0.2em solid red; padding: 0 0.5em 0.5em">
<span style="color: red; font-weight: bold">Внимание!</span>
Вы создаёте статью в пространстве имён, на чтение которого не имеете доступа.
Отметьте флажок "<b>Создать нечитаемую статью</b>" для подтверждения своих намерений,
так как категорий, доступных вам для чтения, нет.
</div>',

    'hacl_nonreadable_upload'           => '<div style="border: 0.2em solid red; padding: 0.2em 0.5em 0.5em; display: inline-block; width: 50%">
<span style="color: red; font-weight: bold">Внимание!</span>
У вас нет доступа на чтение пространства имён <tt>Файл</tt>.<br />
Вы <b>не сможете</b> просмотреть файл после загрузки, если не добавите в описание одну из категорий:&nbsp;$1
</div>',

    'hacl_nonreadable_upload_nocat'     => '<div style="border: 0.2em solid red; padding: 0.2em 0.5em 0.5em; display: inline-block; width: 50%">
<span style="color: red; font-weight: bold">Внимание!</span>
У вас доступа на чтение пространства имён Файл. Вы <b>не сможете</b> просмотреть файл после загрузки!
</div>',

    /**** IntraACL: ****/

    'tog-showacltab'                    => 'Всегда показывать вкладку ACL (права доступа к странице)',

    // General
    'hacl_invalid_prefix'               =>
'Эта страница ничего не защищает и не задаёт группы. Либо так и задумано, либо она некорректно создана.
Если вы хотите что-то защитить, создавайте статьи с именами: ACL:Page/*, ACL:Category/*, ACL:Namespace/*, ACL:Right/*.',
    'hacl_pe_not_exists'                => 'То, что должна защищать эта статья, не существует.',
    'hacl_edit_with_special'            => '<p><a href="$1"><img src="$2" width="16" height="16" alt="Править" /> Править это определение редактором IntraACL.</a></p><hr />',
    'hacl_create_with_special'          => '<p><a href="$1"><img src="$2" width="16" height="16" alt="Создать" /> Создать это определение редактором IntraACL.</a></p><hr />',
    'hacl_tab_acl'                      => 'ACL',
    'hacl_tab_page'                     => 'Страница',
    'hacl_tab_category'                 => 'Категория',

    // Special:IntraACL actions
    'hacl_action_acllist'               => 'Список ACL',
    'hacl_action_acl'                   => 'Создать новый ACL',
    'hacl_action_quickaccess'           => 'Шаблоны быстрого доступа',
    'hacl_action_grouplist'             => 'Группы',
    'hacl_action_group'                 => 'Создать группу',
    'hacl_action_rightgraph'            => 'Права на графе',

    // ACL Editor
    'hacl_autocomplete_no_users'        => 'Пользователи не найдены',
    'hacl_autocomplete_no_groups'       => 'Группы не найдены',
    'hacl_autocomplete_no_pages'        => 'Страницы не найдены',
    'hacl_autocomplete_no_namespaces'   => 'Пространства имён не найдены',
    'hacl_autocomplete_no_categorys'    => 'Категории не найдены',
    'hacl_autocomplete_no_sds'          => 'Определения прав не найдены',

    'hacl_login_first_title'            => 'Сначала представьтесь',
    'hacl_login_first_text'             => 'Пожалуйста, [[Special:Userlogin|представьтесь]] для использования редактора IntraACL.',
    'hacl_acl_create'                   => 'Создать ACL',
    'hacl_acl_create_title'             => 'Создать ACL: $1',
    'hacl_acl_edit'                     => 'Правка ACL: $1',
    'hacl_edit_definition_text'         => 'Текст определения:',
    'hacl_edit_definition_target'       => 'Цель защиты:',
    'hacl_edit_modify_definition'       => 'Правка определения:',
    'hacl_edit_include_right'           => 'Включение других прав:',
    'hacl_edit_include_do'              => 'Включить',
    'hacl_edit_save'                    => 'Сохранить ACL',
    'hacl_edit_create'                  => 'Создать ACL',
    'hacl_edit_delete'                  => 'Удалить&nbsp;ACL',
    'hacl_edit_protect'                 => 'Защитить:',
    'hacl_edit_define'                  => 'Определить:',

    'hacl_indirect_grant'               => 'Это право дано пользователю через $1, напрямую его снять нельзя.',
    'hacl_indirect_grant_all'           => 'право, данное всем пользователям',
    'hacl_indirect_grant_reg'           => 'право, данное зарегистрированным пользователям',
    'hacl_indirect_through'             => '(через $1)',

    'hacl_edit_sd_exists'               => 'Определение уже существует.',
    'hacl_edit_enter_name_first'        => 'Ошибка: введите имя, чтобы сохранить ACL!',
    'hacl_edit_define_rights'           => 'Ошибка: ACL должен включать хотя бы одно право!',
    'hacl_edit_define_manager'          => 'Предупреждение: если ACL не включает права изменения прав, его смогут править администраторы и участники, которым это разрешено через права на пространства имён или категории.',
    'hacl_edit_define_manager_np'       => 'Предупреждение: если ACL не включает права изменения прав, его смогут править только администраторы.',
    'hacl_edit_define_tmanager'         => 'Предупреждение: если шаблон ACL не включает прав изменения шаблона, его смогут править только администраторы.',

    'hacl_start_typing_page'            => 'Начните ввод для подсказки страницы...',
    'hacl_start_typing_category'        => 'Начните ввод для подсказки категории...',
    'hacl_start_typing_user'            => 'Начните ввод для подсказки пользователя...',
    'hacl_start_typing_group'           => 'Начните ввод для подсказки группы...',
    'hacl_edit_users_affected'          => 'Затронутые пользователи:',
    'hacl_edit_groups_affected'         => 'Затронутые группы:',
    'hacl_edit_no_users_affected'       => 'Нет затронутых пользователей.',
    'hacl_edit_no_groups_affected'      => 'Нет затронутых групп.',
    'hacl_edit_goto_group'              => 'Просмотреть группу $1',

    'hacl_edit_user'                    => 'Пользователь',
    'hacl_edit_group'                   => 'Группа',
    'hacl_edit_all'                     => 'Все пользователи',
    'hacl_edit_reg'                     => 'Зарегистрированные пользователи',

    'hacl_edit_action_all'              => 'Полный доступ',
    'hacl_edit_action_manage'           => 'Изменение прав',
    'hacl_edit_action_template'         => 'Изменение шаблона прав',
    'hacl_edit_action_read'             => 'Чтение',
    'hacl_edit_action_edit'             => 'Правка',
    'hacl_edit_action_create'           => 'Создание',
    'hacl_edit_action_delete'           => 'Удаление',
    'hacl_edit_action_move'             => 'Переименование',

    'hacl_edit_ahint_all'               => 'Полный доступ к странице: чтение, правка, создание, удаление, переименование.',
    'hacl_edit_ahint_manage'            =>
        'Это наследуемое право изменения прав. Оно даёт менять другие права (но не шаблоны прав), включающие это,'.
        ' а также права страниц, принадлежащих к заданной категории/пространству имён.'.
        ' Однако, оно НЕ ДАЁТ прав менять данное определение, если только оно само не является правом на страницу.',
    'hacl_edit_ahint_template'          => 'Это НЕнаследуемое право изменения прав. Оно не наследуется другими правами при включении, но всегда даёт менять САМО определение.',
    'hacl_edit_ahint_read'              => 'Право чтения страниц.',
    'hacl_edit_ahint_edit'              => 'Право изменения страниц.',
    'hacl_edit_ahint_create'            => 'Право создания страниц в заданном пространстве имён.', // FIXME ( ... and category )
    'hacl_edit_ahint_delete'            => 'Право удаления страниц.',
    'hacl_edit_ahint_move'              => 'Право переименования (перемещения) страниц вместе с историей.',

    'hacl_define_page'                  => 'Защитить страницу:',
    'hacl_define_namespace'             => 'Защитить пространство имён:',
    'hacl_define_category'              => 'Защитить категорию:',
    'hacl_define_right'                 => 'Шаблон прав:',

    // ACL list
    'hacl_acllist'                      => 'IntraACL — Списки контроля доступа',
    'hacl_acllist_hello'                => 'Привет, меня зовут \'\'\'[http://wiki.4intra.net/IntraACL IntraACL]\'\'\', я — лучшее из расширений MediaWiki для защиты страниц. Справку читайте [http://wiki.4intra.net/IntraACL здесь]. Для начала работы выберите желаемое действие:',
    'hacl_acllist_empty'                => '<span style="color:red;font-weight:bold">Подходящих ACL не найдено.</span>',
    'hacl_acllist_filter_name'          => 'Выбор по началу имени:',
    'hacl_acllist_filter_type'          => 'Выбор по типу:',
    'hacl_acllist_hint_single'          => 'Право $1 содержит лишь включение $2.',
    'hacl_acllist_perpage'              => 'Показать на странице:',
    'hacl_acllist_result_page'          => 'Страницы:',
    'hacl_acllist_prev'                 => '&larr; Предыдущая страница',
    'hacl_acllist_next'                 => 'Следующая страница &rarr;',
    'hacl_acllist_typegroup_all'        => 'Все права',
    'hacl_acllist_typegroup_protect'    => 'Права для:',
    'hacl_acllist_typegroup_define'     => 'Шаблоны:',

    'hacl_acllist_type_page'            => 'Страниц',
    'hacl_acllist_type_namespace'       => 'Пространств имён',
    'hacl_acllist_type_category'        => 'Категорий',
    'hacl_acllist_type_right'           => 'Шаблоны прав',

    'hacl_acllist_page'                 => 'Права для страниц:',
    'hacl_acllist_namespace'            => 'Права для пространств имён:',
    'hacl_acllist_category'             => 'Права для категорий:',
    'hacl_acllist_right'                => 'Шаблоны прав:',
    'hacl_acllist_edit'                 => 'Изменить',
    'hacl_acllist_view'                 => 'Просмотр',

    // Quick ACL list editor
    'hacl_qacl_filter_sds'              => 'Выбор по имени',
    'hacl_qacl_filter'                  => 'Начало имени:',
    'hacl_qacl_filter_submit'           => 'Выбрать',
    'hacl_qacl_manage'                  => 'IntraACL — Шаблоны быстрого доступа',
    'hacl_qacl_manage_text'             =>
        'Это список шаблонов прав, которые вы можете использовать как шаблоны быстрого доступа.
        Выбранные шаблоны показываются для выбора в режиме редактирования и создания каждой вики-страницы.
        Шаблон по умолчанию будет изначально выбран для новых страниц.',
    'hacl_qacl_save'                    => 'Сохранить выбор',
    'hacl_qacl_hint'                    => 'Отметьте какие-нибудь шаблоны прав и нажмите "Сохранить выбор":',
    'hacl_qacl_empty'                   => 'Таких шаблонов, которые можно выбрать для быстрого доступа, нет. Для создания нажмите <b>Создать новый ACL</b>.',
    'hacl_qacl_empty_default'           => 'Без защиты',
    'hacl_qacl_col_select'              => 'Выбрать',
    'hacl_qacl_col_default'             => 'По умолчанию',
    'hacl_qacl_col_name'                => 'Имя шаблона',
    'hacl_qacl_col_actions'             => 'Действия',

    // Group list
    'hacl_grouplist'                    => 'Группы IntraACL',
    'hacl_grouplist_filter_name'        => 'Имя содержит:',
    'hacl_grouplist_filter_not_name'    => 'Имя не содержит:',
    'hacl_grouplist_empty'              => '<span style="color:red;font-weight:bold">Подходящих групп не найдено.</span>',
    'hacl_grouplist_view'               => 'Просмотр',
    'hacl_grouplist_edit'               => 'Изменить',

    // Group editor
    'hacl_grp_creating'                 => 'Создать группу IntraACL',
    'hacl_grp_editing'                  => 'Правка группы IntraACL: $1',
    'hacl_grp_create'                   => 'Создать группу',
    'hacl_grp_save'                     => 'Сохранить группу',
    'hacl_grp_delete'                   => 'Удалить группу',
    'hacl_grp_name'                     => 'Имя группы:',
    'hacl_grp_definition_text'          => 'Текст определения группы:',
    'hacl_grp_member_all'               => 'Все пользователи',
    'hacl_grp_member_reg'               => 'Зарегистрированные пользователи',
    'hacl_grp_members'                  => 'Члены группы:',
    'hacl_grp_managers'                 => 'Могут править группу:',
    'hacl_grp_users'                    => 'Пользователи:',
    'hacl_grp_groups'                   => 'Другие группы:',

    'hacl_grp_exists'                   => 'Эта группа уже существует.',
    'hacl_grp_enter_name_first'         => 'Ошибка: введите имя, чтобы сохранить группу!',
    'hacl_grp_define_members'           => 'Ошибка: в группу должен входить хотя бы кто-то!',
    'hacl_grp_define_managers'          => 'Ошибка: хотя бы кому-то должна быть разрешена правка группы!',

    'hacl_no_member_user'               => 'В группу не входит ни один пользователь.',
    'hacl_no_member_group'              => 'В группу не входит ни одна другая группа.',
    'hacl_no_manager_user'              => 'Править группу не может ни один пользователь.',
    'hacl_no_manager_group'             => 'Править группу не могут члены ни одной другой группы.',
    'hacl_current_member_user'          => 'Пользователи, входящие в группу:',
    'hacl_current_member_group'         => 'Группы, входящие в группу:',
    'hacl_current_manager_user'         => 'Эти пользователи могут править группу:',
    'hacl_current_manager_group'        => 'Члены этих групп могут править группу:',
    'hacl_regexp_user'                  => '(^|,\s*)Участник:',
    'hacl_regexp_group'                 => '(^|,\s*)(Group|Группа)[:/]',

    // Toolbar and parts
    'hacl_toolbar_advanced_edit'        => 'Править редактором',
    'hacl_toolbar_advanced_create'      => 'Создать редактором',
    'hacl_toolbar_goto'                 => 'Перейти к $1.',
    'hacl_toolbar_global_acl'           => 'Другие права &darr;',
    'hacl_toolbar_global_acl_tip'       => 'Эти определения прав также действуют на страницу:',
    'hacl_toolbar_embedded_acl'         => 'Связанное содержимое &darr;',
    'hacl_toolbar_loading'              => 'Загрузка...',
    'hacl_toolbar_page_prot'            => 'Права доступа:',
    'hacl_toolbar_cannot_modify'        => 'Вам запрещено изменять защиту статьи.',
    'hacl_toolbar_no_right_templates'   => 'Права не заданы.',
    'hacl_toolbar_unprotected'          => 'Особых прав нет',
    'hacl_toolbar_used_on'              => 'на <a href="$2">$1 страницах</a>',
    'hacl_toolbar_protect_embedded'     => 'Защитить включённые файлы и статьи теми же правами, что и статью (внимание — существующие права будут <span style="color:red;font-weight:bold">перезаписаны</span>):',
    'hacl_toolbar_emb_already_prot'     => 'уже защищено',
    'hacl_toolbar_emb_custom_prot'      => 'заданы <a href="$1">права доступа</a>',
    'hacl_toolbar_emb_all'              => 'Перезаписать <span style="color:red;font-weight:bold">все</span>',
    'hacl_toolbar_emb_all_already'      => 'Все элементы уже защищены',
    'hacl_toolbar_qacl'                 => 'Шаблоны быстрого доступа',
    'hacl_toolbar_select_qacl'          => 'Чтобы создать защищённую страницу, сначала выберите <a href="$1">шаблоны быстрого доступа</a>.',
    'hacl_toolbar_qacl_title'           => 'Управление списком шаблонов прав, доступных на этой панели.',
    'hacl_comment_protect_with'         => 'Страница защищена $1.',
    'hacl_comment_unprotect'            => 'Особые права страницы удалены.',
    'hacl_comment_protect_embedded'     => 'Страница сочтена подстатьёй и защищена $1.',
    'hacl_embedded_error_canedit'       => 'доступ запрещён',
    'hacl_embedded_error_invalidsd'     => 'запрошена неверная защита',
    'hacl_embedded_error_midair'        => 'конфликт редактирования',
    'hacl_embedded_not_saved_title'     => 'Связанное содержимое не защищено',
    'hacl_embedded_not_saved'           => 'Права для следующих связанных элементов не сохранены: $1.

Возможные причины:
* Доступ запрещён: У вас нет прав для изменения защиты каких-либо из этих элементов.
* Конфликт редактирования: Определения защиты каких-либо из этих элементов были изменены кем-то другим, пока вы редактировали статью.
* Элемент(ы) удален(ы): Какие-либо из этих элементов могли быть удалены, пока вы редактировали статью.
* Запрошена неверная защита: Скорее всего, была некорректно отправлена форма редактирования.

Пожалуйста, рассмотрите ситуацию внимательнее, вернитесь к [[$2]] и пересохраните права.',
);

/**
 * German
 */
$messages['de'] = array(
    'hacl_unknown_user'                   => 'Der Benutzer "$1" ist unbekannt.',
    'hacl_unknown_group'                  => 'Die Gruppe "$1" ist unbekannt.',
    'hacl_missing_parameter'              => 'Der Parameter "$1" fehlt.',
    'hacl_missing_parameter_values'       => 'Der Parameter "$1" hat keine gültigen Werte.',
    'hacl_invalid_predefined_right'       => 'Es existiert keine Rechtevorlage mit dem Namen "$1" oder sie enthält keine gültige Rechtedefinition.',
    'hacl_invalid_action'                 => '"$1" ist ein ungültiger Wert für eine Aktion.',
    'hacl_wrong_namespace'                => 'Artikel mit Rechte- oder Gruppendefinitionen müssen zum Namensraum "Rechte" gehören.',
    'hacl_group_must_have_members'        => 'Eine Gruppe muss mindestens ein Mitglied haben (Gruppe oder Benutzer).',
    'hacl_group_must_have_managers'       => 'Eine Gruppe muss mindestens einen Verwalter haben (Gruppe oder Benutzer).',
    'hacl_invalid_parser_function'        => 'Sie dürfen die Funktion "#$1" in diesem Artikel nicht verwenden.',
    'hacl_right_must_have_rights'         => 'Ein Recht oder eine Sicherheitsbeschreibung müssen Rechte oder Verweise auf Rechte enthalten.',
    'hacl_right_must_have_managers'       => 'Ein Recht oder eine Sicherheitsbeschreibung müssen mindestens einen Verwalter haben (Gruppe oder Benutzer).',
    'hacl_pf_rightname_title'             => '===$1==='."\n",
    'hacl_pf_rights_title'                => '===Recht(e): $1==='."\n",
    'hacl_pf_rights'                      => ':;Recht(e):
:: $1
',
    'hacl_pf_right_managers_title'        => '===Rechteverwalter==='."\n",
    'hacl_pf_predefined_rights_title'     => '===Rechtevorlagen==='."\n",
    'hacl_pf_group_managers_title'        => '===Gruppenverwalter==='."\n",
    'hacl_pf_group_members_title'         => '===Gruppenmitglieder==='."\n",
    'hacl_assigned_user'                  => 'Zugewiesene Benutzer: ',
    'hacl_assigned_groups'                => 'Zugewiesene Gruppen:',
    'hacl_user_member'                    => 'Benutzer, die Mitglied dieser Gruppe sind:',
    'hacl_group_member'                   => 'Gruppen, die Mitglied dieser Gruppe sind:',
    'hacl_description'                    => 'Beschreibung:',
    'hacl_error'                          => 'Fehler:',
    'hacl_warning'                        => 'Warnungen:',
    'hacl_consistency_errors'             => '<h2>Fehler in der Rechtedefinition</h2>',
    'hacl_definitions_will_not_be_saved'  => '(Wegen der folgenden Fehler werden die Definitionen dieses Artikel nicht gespeichert und haben keine Auswirkungen.)',
    'hacl_will_not_work_as_expected'      => '(Wegen der folgenden Warnungen wird die Definition nicht wie erwartet angewendet.)',
    'hacl_errors_in_definition'           => 'Die Definitionen in diesem Artikel sind fehlerhaft. Bitte schauen Sie sich die folgenden Details an!',
    'hacl_anonymous_users'                => 'anonyme Benutzer',
    'hacl_registered_users'               => 'registrierte Benutzer',
    'hacl_acl_element_not_in_db'          => 'Zu diesem Artikel gibt es keinen Eintrag in der Rechtedatenbank. Vermutlich wurde er gelöscht und wiederhergestellt. Bitte speichern Sie ihn und alle Artikel die ihn verwenden neu.',
    'hacl_unprotectable_namespace'        => 'Dieser Namensraum kann nicht geschützt werden. Bitte fragen Sie Ihren Wikiadministrator.',
    'hacl_permission_denied'              => 'Sie dürfen die gewünschte Aktion auf dieser Seite nicht durchführen.

Zurück zur [[Hauptseite]].',
);
