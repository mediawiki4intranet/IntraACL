<?php if (!$lists) { ?>
<?= wfMsg('hacl_acllist_empty') ?>
<?php }
foreach (array('default', 'namespace', 'category', 'right', 'template', 'page', 'property') as $k) {
 if ($lists[$k]) { ?>
 <?= wfMsg('hacl_acllist_'.$k) ?>
 <ul>
  <?php foreach ($lists[$k] as $d) { ?>
   <li>
    <a title="<?= htmlspecialchars($d['name']) ?>" href="<?= $d['editlink'] ?>"><?= htmlspecialchars($d['real']) ?></a>
    <?php if ($d['single']) { ?>
     = <a title="<?= htmlspecialchars($d['singletip']) ?>" href=""><?= htmlspecialchars($d['singlename']) ?></a>
    <?php } ?>
    &nbsp;<a title="<?= wfMsg('hacl_acllist_view') ?>" href="<?= $d['viewlink'] ?>"><img src="<?= $haclgHaloScriptPath ?>/skins/images/view.png" /></a>
    <a title="<?= wfMsg('hacl_acllist_edit') ?>" href="<?= $d['editlink'] ?>"><img src="<?= $haclgHaloScriptPath ?>/skins/images/edit.png" /></a>
   </li>
  <?php } ?>
 </ul>
 <?php }
}
if ($max) { ?>
 <p>...</p>
<?php } ?>
