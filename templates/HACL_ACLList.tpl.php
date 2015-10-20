<div id="acl_list" style="border: 1px solid gray; width: 500px; height: 500px; padding: 5px; overflow-y: scroll; overflow: -moz-scrollbars-vertical; float: left"></div>

<div style="float: left; margin: 0 8px">
<p><b><?= wfMsg('hacl_acllist_filter_name') ?></b></p>
<p><input type="text" id="acl_filter" value="<?= htmlspecialchars($q['filter']) ?>" onchange="change_filter()" onkeyup="change_filter()" style="width: 400px" /></p>

<p><b><?= wfMsg('hacl_acllist_filter_type') ?></b></p>
<p><input type="checkbox" id="atg_all" <?= $types['all'] ? ' checked="checked"' : '' ?> onclick="change_filter(this)" onchange="change_filter(this)" /> <label for="atg_all"><?= wfMsg('hacl_acllist_typegroup_all') ?></label></p>
<ul>
<?php foreach($this->aclTargetTypes as $t => $l) { ?>
 <li>
  <input type="checkbox" id="atg_<?= $t ?>" <?= $types[$t] ? ' checked="checked"' : '' ?> onclick="change_filter(this)" onchange="change_filter(this)" /> <label for="atg_<?= $t ?>"><?= wfMsg('hacl_acllist_typegroup_'.$t) ?></label>
  <ul>
  <?php foreach($l as $k => $true) { ?>
   <li><input type="checkbox" id="at_<?= $k ?>" <?= $types[$k] ? ' checked="checked"' : '' ?> onclick="change_filter(this)" onchange="change_filter(this)" /> <label for="at_<?= $k ?>"><?= wfMsg('hacl_acllist_type_'.$k) ?></label></li>
  <?php } ?>
  </ul>
 </li>
<?php } ?>
</ul>

<p><b><?= wfMsg('hacl_acllist_perpage') ?></b> <input type="text" id="perPage" value="<?= $limit ?>" onchange="change_filter()" /></p>

<input type="hidden" id="acl_page" value="<?= intval($q['offset']/$limit) ?>" />
<p id="resultPagesP" style="display: none"><b><?= wfMsg('hacl_acllist_result_page') ?></b> <span id="resultPages"></span></p>

</div>

<div style="clear: both"></div>
