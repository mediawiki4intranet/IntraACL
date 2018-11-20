<?php if (!$groups) {
print wfMessage('hacl_grouplist_empty');
} else { ?>
<ul>
<?php foreach ($groups as $gr) { ?>
 <li>
  <a title="<?= $gr['name'] ?>" href="<?= $gr['editlink'] ?>"><?= $gr['real'] ?></a>&nbsp;
  <a title="<?= wfMessage('hacl_grouplist_view') ?>" href="<?= $gr['viewlink'] ?>"><img src="<?= $haclgHaloScriptPath ?>/skins/images/view.png" /></a>
  <a title="<?= wfMessage('hacl_grouplist_edit') ?>" href="<?= $gr['editlink'] ?>"><img src="<?= $haclgHaloScriptPath ?>/skins/images/edit.png" /></a>
 </li>
<?php } ?>
</ul>
<?php if ($max) { ?>
 <p>...</p>
<?php }
} ?>
