<?= wfMsg('hacl_qacl_manage_text') ?>
<fieldset style="margin: 0 0 16px 0">
 <legend><?= wfMsg('hacl_qacl_filter_sds') ?></legend>
 <form action="<?= $wgScript ?>">
  <label for="hacl_qafilter"><?= wfMsg('hacl_qacl_filter') ?></label>
  <input type="hidden" name="title" value="Special:IntraACL" />
  <input type="hidden" name="action" value="quickaccess" />
  <input type="text" name="like" id="hacl_qafilter" value="<?= htmlspecialchars($like) ?>" />
  <input type="submit" value="<?= wfMsg('hacl_qacl_filter_submit') ?>" />
 </form>
</fieldset>
<?php if ($templates) { ?>
<p><?= wfMsg('hacl_qacl_hint') ?></p>
<form action="<?= $wgScript ?>?title=Special:IntraACL&action=quickaccess&save=1" method="POST">
 <input type="hidden" name="like" value="<?= htmlspecialchars($like) ?>" />
 <table class="wikitable">
  <tr>
   <th><?= wfMsg('hacl_qacl_col_select') ?></th>
   <th><?= wfMsg('hacl_qacl_col_default') ?></th>
   <th><?= wfMsg('hacl_qacl_col_name') ?></th>
   <th><?= wfMsg('hacl_qacl_col_actions') ?></th>
  </tr>
  <?php foreach ($templates as $sd) { ?>
   <tr>
    <td style="text-align: center">
     <input type="checkbox" name="qa_<?= $sd->getSDId() ?>" id="qa_<?= $sd->getSDId() ?>" <?= $sd->selected ? ' checked="checked"' : '' ?> />
    </td>
    <td style="text-align: center">
     <input onchange="set_checked(<?= $sd->getSDId() ?>)" type="radio" name="qa_default" id="qd_<?= $sd->getSDId() ?>" value="<?= $sd->getSDId() ?>" <?= $sd->default ? ' checked="checked"' : '' ?> />
    </td>
    <td style="text-align: center"><a title="<?= $sd->getSDName() ?>" href="<?= $sd->viewlink ?>"><?= $sd->getSDName() ?></a></td>
    <td style="text-align: center">
     <a title="<?= wfMsg('hacl_acllist_edit') ?>" href="<?= $sd->editlink ?>">
      <img src="<?= $haclgHaloScriptPath ?>/skins/images/edit.png" />
     </a>
    </td>
   </tr>
  <?php } ?>
  <tr>
   <td></td>
   <td style="text-align: center"><input type="radio" name="qa_default" id="qd_clear" value="" <?= !$quickacl->default_sd_id ? ' checked="checked"' : '' ?> /></td>
   <td style="text-align: center" colspan="2"><?= wfMsg('hacl_qacl_empty_default') ?></td>
  </tr>
 </table>
 <p>
  <input type="submit" value="<?= wfMsg('hacl_qacl_save') ?>" style="font-weight: bold" />
 </p>
</form>
<script language="JavaScript">
var curDefault = <?= $quickacl->getDefaultSD_ID() ?>;
var clear_default = function() {
  var d = document.getElementById('qd_'+curDefault);
  if (d)
  {
    d.checked = false;
    curDefault = 0;
  }
};
var set_checked = function(x) {
  if (document.getElementById('qd_'+x).checked)
  {
    curDefault = x;
    document.getElementById('qa_'+x).checked = true;
  }
};
</script>
<?php } else { ?>
 <?= wfMsg('hacl_qacl_empty') ?>
<?php } ?>
