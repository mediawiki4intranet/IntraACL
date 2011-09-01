<div id="hacl_toolbar">
<?php if (count($options) > 1 && $canModify) { ?>
 <label for="hacl_protected_with"><?= wfMsg('hacl_toolbar_page_prot') ?></label>
 <select name="hacl_protected_with" id="hacl_protected_with" onchange="haclt_change_goto(this, '<?= wfMsg('hacl_toolbar_goto') ?>')">
  <?php foreach($options as $o) { ?>
   <option title="<?= htmlspecialchars($o['title']) ?>" <?= !empty($o['current']) ? ' selected="selected"' : '' ?> value="<?= htmlspecialchars($o['value']) ?>"><?= htmlspecialchars($o['name']) ?></option>
  <?php } ?>
 </select>
 <?php if ($selectedIndex !== false && $options[$selectedIndex]['title']) { ?>
  <a id="hacl_toolbar_goto" href="<?= Title::newFromText($options[$selectedIndex]['title'])->getLocalUrl() ?>" target="_blank" title="<?= htmlspecialchars(wfMsg('hacl_toolbar_goto', $options[$selectedIndex]['title'])) ?>">
   <img src="<?= $wgScriptPath ?>/skins/monobook/external.png" width="10" height="10" alt="&rarr;" />
  </a>
 <?php } else { ?>
  <a id="hacl_toolbar_goto" href="#" target="_blank" style="display: none">
   <img src="<?= $wgScriptPath ?>/skins/monobook/external.png" width="10" height="10" alt="&rarr;" />
  </a>
 <?php } ?>
<?php } elseif (!$canModify) { ?>
 <?= wfMsg('hacl_toolbar_cannot_modify') ?>
<?php } elseif ($title->exists()) { ?>
 <?= wfMsg('hacl_toolbar_no_right_templates', $quick_acl_link) ?>
<?php } if ($globalACL) { ?>
 <div class="haclt_tip">
  <a onclick="haclt_show('gacl')" class="haclt_title" id="haclt_gacl_title"><?= wfMsg('hacl_toolbar_global_acl') ?></a>
  <div class="haclt_text" id="haclt_gacl_text" style="display: none"><div class="x">
   <?= wfMsg('hacl_toolbar_global_acl_tip') ?><br /><?= $globalACL ?>
  </div></div>
 </div>
<?php } if ($anyLinks || $embeddedToolbar) { ?>
 <div class="haclt_tip">
  <a onclick="haclt_show('emb')" class="haclt_title" id="haclt_emb_title"><?= wfMsg('hacl_toolbar_embedded_acl') ?></a>
  <div class="haclt_text" id="haclt_emb_text" style="display: none"><div class="x<?= $embeddedToolbar ? ' xl' : '' ?>" id="haclt_emb">
   <?= $embeddedToolbar ? $embeddedToolbar : wfMsg('hacl_toolbar_loading') ?>
  </div></div>
 </div>
<?php } if ($title->exists()) { ?>
 <a style="text-decoration: none" class="haclt_title" target="_blank" href="index.php?title=Special:IntraACL&action=acl&sd=<?= urlencode($haclgContLang->getPetPrefix(HACLLanguage::PET_PAGE).'/'.$title) ?>"><img src="<?= $haclgHaloScriptPath ?>/skins/images/edit.png" width="16" height="16" alt="Edit" /> <?= wfMsg('hacl_toolbar_advanced_'.($pageSDId ? 'edit' : 'create')) ?></a>
<?php } elseif (!$hasQuickACL) {?>
 <?= wfMsg('hacl_toolbar_select_qacl', $quick_acl_link) ?>
<?php } if ($nonreadable) { ?>
 <input style="vertical-align: middle" type="checkbox" name="hacl_nonreadable_create" id="hacl_nonreadable_create" />
 <label style="vertical-align: middle" for="hacl_nonreadable_create">Создать нечитаемую статью</label>
<?php } ?>
 <div class="qacl"><a target="_blank" href="<?= $quick_acl_link ?>" title="<?= wfMsg('hacl_toolbar_qacl_title') ?>"><?= wfMsg('hacl_toolbar_qacl') ?></a></div>
</div>
