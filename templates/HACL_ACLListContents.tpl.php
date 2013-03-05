<input type="hidden" id="totalPages" value="<?= ceil($total/$limit) ?>" />
<input type="hidden" id="pageUrl" value="<?= $pageurl ?>" />
<?php if (!$lists) { ?>
<?= wfMsg('hacl_acllist_empty') ?>
<?php } if ($prevpage) { ?>
<p><a href="<?= $prevpage ?>" onclick="change_page(<?= intval($offset/$limit-1) ?>); return false;"><?= wfMsg('hacl_acllist_prev') ?></a></p>
<?php }
foreach (array('default', 'namespace', 'category', 'right', 'template', 'page') as $k) {
 if (!empty($lists[$k])) { ?>
 <?= wfMsg('hacl_acllist_'.$k) ?>
 <ul>
  <?php foreach ($lists[$k] as $d) { ?>
   <li>
    <a title="<?= htmlspecialchars($d['name']) ?>" href="<?= $d['editlink'] ?>"><?= htmlspecialchars($d['real']) ?></a>
    <?php if ($d['single']) { ?>
     = <a title="<?= htmlspecialchars($d['singletip']) ?>" href="<?= $d['singlelink'] ?>"><?= htmlspecialchars($d['singlename']) ?></a>
    <?php } ?>
    &nbsp;<a title="<?= wfMsg('hacl_acllist_view') ?>" href="<?= $d['viewlink'] ?>"><img src="<?= $haclgHaloScriptPath ?>/skins/images/view.png" /></a>
    <a title="<?= wfMsg('hacl_acllist_edit') ?>" href="<?= $d['editlink'] ?>"><img src="<?= $haclgHaloScriptPath ?>/skins/images/edit.png" /></a>
   </li>
  <?php } ?>
 </ul>
 <?php }
}
if ($nextpage) { ?>
<p><a href="<?= $nextpage ?>" onclick="change_page(<?= intval(1+$offset/$limit) ?>); return false;"><?= wfMsg('hacl_acllist_next') ?></a></p>
<?php }
