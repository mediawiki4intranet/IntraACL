<table class="acle">
<tr>
 <td style="vertical-align: top; width: 500px">
  <form action="<?= $wgScript ?>?action=submit" method="POST" id="groupEditForm">
   <input type="hidden" name="wpEditToken" value="<?= htmlspecialchars($wgUser->editToken()) ?>" />
   <input type="hidden" name="wpEdittime" value="<?= $grpTitle ? $grpArticle->getTimestamp() : '' ?>" />
   <input type="hidden" name="wpStarttime" value="<?= wfTimestampNow() ?>" />
   <input type="hidden" id="wpTitle" name="title" value="<?= $grpTitle ? htmlspecialchars($grpTitle->getPrefixedText()) : '' ?>" />
   <input type="hidden" name="wpSave" value="Save" />
   <p>
    <b><?= wfMsg('hacl_grp_name') ?></b>
    <input type="text" id="grp_name" style="width: 200px" onchange="GE.name_change(true)" onkeyup="GE.name_change()"  />
   </p>
   <p><b><?= wfMsg('hacl_grp_definition_text') ?></b></p>
   <p><textarea id="grp_def" name="wpTextbox1" rows="6" style="width: 500px" onchange="GE.parse_fill_indirect()"><?= $grpTitle ? htmlspecialchars($grpArticle->getContent()) : '' ?></textarea></p>
  </form>
 </td>
 <td style="vertical-align: top">
  <table>
   <tr>
    <th colspan="2"><?= wfMsg('hacl_grp_members') ?></th>
   </tr>
   <tr>
    <th><?= wfMsg('hacl_grp_users') ?></th>
    <td><input type="text" id="member_users" style="width: 200px" autocomplete="off" /></td>
   </tr>
   <tr>
    <th><?= wfMsg('hacl_grp_groups') ?></th>
    <td><input type="text" id="member_groups" style="width: 200px" autocomplete="off" /></td>
   </tr>
   <tr>
    <th colspan="2"><?= wfMsg('hacl_grp_managers') ?></th>
   </tr>
   <tr>
    <th><?= wfMsg('hacl_grp_users') ?></th>
    <td><input type="text" id="manager_users" style="width: 200px" autocomplete="off" /></td>
   </tr>
   <tr>
    <th><?= wfMsg('hacl_grp_groups') ?></th>
    <td><input type="text" id="manager_groups" style="width: 200px" autocomplete="off" /></td>
   </tr>
  </table>
 </td>
</tr>
</table>
<p id="grp_pns">
 <span><a id="grp_pn" class="acl_pn" href="#"></a></span>
 <input type="button" value="<?= wfMsg($grpTitle ? 'hacl_grp_save' : 'hacl_grp_create') ?>" id="wpSave" onclick="document.getElementById('groupEditForm').submit()" />
 <a id="grp_delete_link" href="<?= $grpTitle ? $grpTitle->getLocalUrl(array('action' => 'delete')) : '' ?>"><?= wfMsg('hacl_grp_delete') ?></a>
</p>
<p id="grp_pnhint" class="acl_error" style="display: none"><?= wfMsg('hacl_grp_enter_name_first') ?></p>
<p id="grp_exists_hint" class="acl_info" style="display: none"><?= wfMsg('hacl_grp_exists') ?></p>
<p id="grp_define_member" class="acl_error" style="display: none"><?= wfMsg('hacl_grp_define_members') ?></p>
<p id="grp_define_manager" class="acl_error" style="display: none"><?= wfMsg('hacl_grp_define_managers') ?></p>

<script language="JavaScript" src="<?= $haclgHaloScriptPath ?>/scripts/exAttach.js"></script>
<script language="JavaScript" src="<?= $haclgHaloScriptPath ?>/scripts/offsetRect.js"></script>
<script language="JavaScript" src="<?= $haclgHaloScriptPath ?>/scripts/SHint.js"></script>
<script language="JavaScript" src="<?= $haclgHaloScriptPath ?>/scripts/HACL_GroupEditor.js"></script>

<script language="JavaScript">
var GE;
exAttach(window, 'load', function()
{
    var msg = {
    <?php foreach (explode(' ',
        'grp_save grp_create no_member_user no_member_group no_manager_user no_manager_group'.
        ' current_member_user current_member_group current_manager_user current_manager_group'.
        ' regexp_user regexp_group start_typing_user start_typing_group indirect_through'.
        ' edit_all edit_reg'
    ) as $msg)
        print "'$msg': '".addslashes(wfMsgNoTrans("hacl_$msg"))."',\n"; ?>
'group_prefix' : '<?= $grpTitle ? $grpPrefix : $haclgContLang->getGroupPrefix() ?>',
'NS_ACL' : '<?= $wgContLang->getNsText(HACL_NS_ACL) ?>'
    };
    GE = new HACLGroupEditor(msg, "<?= addslashes($grpName) ?>");
});
</script>
