<form action="<?= $wgScript ?>?action=submit" method="POST">
<input type="hidden" name="wpEditToken" value="<?= htmlspecialchars($wgUser->editToken()) ?>" />
<input type="hidden" name="wpEdittime" value="<?= $aclArticle ? $aclArticle->getTimestamp() : '' ?>" />
<input type="hidden" name="wpStarttime" value="<?= wfTimestampNow() ?>" />
<input type="hidden" id="wpTitle" name="title" value="<?= $aclArticle ? htmlspecialchars($aclTitle->getPrefixedText()) : '' ?>" />
<table class="acle">
<tr>
 <td style="vertical-align: top; width: 500px">
  <p><b><?= wfMsg('hacl_edit_definition_text') ?></b></p>
  <p><textarea id="acl_def" name="wpTextbox1" rows="6" style="width: 500px" onchange="AE.parse_make_closure()"><?= htmlspecialchars($aclContent) ?></textarea></p>
  <p><b><?= wfMsg('hacl_edit_definition_target') ?></b></p>
  <p>
   <select id="acl_what" onchange="AE.target_change(true)" style="max-width: 200px">
    <?php foreach($this->aclTargetTypes as $t => $l) { ?>
     <optgroup label="<?= wfMsg('hacl_edit_'.$t) ?>">
     <?php foreach($l as $k => $true) { ?>
      <option id="acl_what_<?= $k ?>" value="<?= $k ?>"><?= wfMsg("hacl_define_$k") ?></option>
     <?php } ?>
     </optgroup>
    <?php } ?>
   </select>
   <input type="text" class="txt" autocomplete="off" id="acl_name" style="width: 290px" />
  </p>
 </td>
 <td style="vertical-align: top">
  <p><b><?= wfMsg('hacl_edit_modify_definition') ?></b></p>
  <p>
   <select id="to_type" onchange="AE.to_type_change()" style="max-width: 200px">
    <option value="user"><?= wfMsg('hacl_edit_user') ?></option>
    <option value="group"><?= wfMsg('hacl_edit_group') ?></option>
    <option value="*"><?= wfMsg('hacl_edit_all') ?></option>
    <option value="#"><?= wfMsg('hacl_edit_reg') ?></option>
   </select>
   <input type="text" class="txt" id="to_name" style="width: 200px" autocomplete="off" />
   <a id="hacl_to_goto" href="#" target="_blank" style="display: none" title="">
    <img src="<?= $wgScriptPath ?>/skins/monobook/external-ltr.png" width="10" height="10" alt="&rarr;" />
   </a>
  </p>
  <p>
   <input type="checkbox" id="act_all" onclick="AE.act_change(this)" onchange="AE.act_change(this)" />
   <label for="act_all" id="act_label_all"><?= wfMsg('hacl_edit_action_all') ?></label>
   <input type="checkbox" id="act_manage" onclick="AE.act_change(this)" onchange="AE.act_change(this)" />
   <label for="act_manage" id="act_label_manage"><?= wfMsg('hacl_edit_action_manage') ?></label>
   <br />
   <?php foreach(explode(',', 'read,edit,create,delete,move') as $k) { ?>
   <input type="checkbox" id="act_<?= $k ?>" onclick="AE.act_change(this)" onchange="AE.act_change(this)" />
   <label for="act_<?= $k ?>" id="act_label_<?= $k ?>"><?= wfMsg("hacl_edit_action_$k") ?></label>
   <?php } ?>
   <br />
   <input type="checkbox" id="act_template" onclick="AE.act_change(this)" onchange="AE.act_change(this)" />
   <label for="act_template" id="act_label_template"><?= wfMsg('hacl_edit_action_template') ?></label>
  </p>
  <p>
   <label for="inc_acl"><?= wfMsg('hacl_edit_include_right') ?></label>
   <input type="text" class="txt" id="inc_acl" />
   <input type="button" value="<?= wfMsg('hacl_edit_include_do') ?>" onclick="AE.include_acl()" />
  </p>
  <div id="acl_embed" style="display: none"></div>
 </td>
</tr>
</table>
<p id="acl_pns">
 <span><a id="acl_pn" class="acl_pn" href="#"></a></span>
 <input type="submit" name="wpSave" value="<?= wfMsg($aclArticle ? 'hacl_edit_save' : 'hacl_edit_create') ?>" id="wpSave" />&nbsp;<a id="acl_delete_link" href="<?= $aclArticle ? $aclTitle->getLocalUrl(array('action' => 'delete')) : '' ?>"><?= wfMsg('hacl_edit_delete') ?></a>
</p>
<p id="acl_pnhint" class="acl_error" style="display: none"><?= wfMsg('hacl_edit_enter_name_first') ?></p>
<p id="acl_exists_hint" class="acl_info" style="display: none"><?= wfMsg('hacl_edit_sd_exists') ?></p>
<p id="acl_define_rights" class="acl_error"><?= wfMsg('hacl_edit_define_rights') ?></p>
<p id="acl_define_manager" class="acl_error"></p>
<p id="acl_non_canonical" class="acl_error"></p>
</form>
