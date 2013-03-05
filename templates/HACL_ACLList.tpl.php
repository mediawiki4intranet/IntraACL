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
  <? } ?>
  </ul>
 </li>
<? } ?>
</ul>

<p><b><?= wfMsg('hacl_acllist_perpage') ?></b> <input type="text" id="perPage" value="<?= $limit ?>" onchange="change_filter()" /></p>

<input type="hidden" id="acl_page" value="<?= intval($q['offset']/$limit) ?>" />
<p id="resultPagesP" style="display: none"><b><?= wfMsg('hacl_acllist_result_page') ?></b> <span id="resultPages"></span></p>

</div>

<div style="clear: both"></div>

<script language="JavaScript" src="<?= $haclgHaloScriptPath ?>/scripts/exAttach.js"></script>
<script language="JavaScript">
<?php
$all = array();
$s = '';
foreach($this->aclTargetTypes as $t => $l)
{
    $all += $l;
    $s .= ", '$t' : {'" . implode("':1,'", array_keys($l)) . "':1}";
}
print "var aclTypeGroups = { 'all' : {'".implode("':1,'", array_keys($all))."':1}$s };";
?>
function change_filter(chk)
{
    document.getElementById('acl_page').value = 0;
    reload_acl(chk);
}
function reload_acl(chk)
{
    if (chk)
    {
        if (chk.id.substr(0, 4) == 'atg_')
            for (var j in aclTypeGroups[chk.id.substr(4)])
                document.getElementById('at_'+j).checked = chk.checked;
        for (var i in aclTypeGroups)
        {
            if (chk.id != 'atg_'+i)
            {
                var gc = true;
                for (var j in aclTypeGroups[i])
                    gc = gc && document.getElementById('at_'+j).checked;
                document.getElementById('atg_'+i).checked = gc;
            }
        }
    }
    var types = [];
    var th = {};
    for (var i in aclTypeGroups)
        for (var j in aclTypeGroups[i])
            if (document.getElementById('at_'+j).checked && !th[j])
                th[j] = true, types.push(j);
    var pg = document.getElementById('acl_page').value;
    var limit = parseInt(document.getElementById('perPage').value);
    if (!limit || limit != limit)
        limit = 100;
    sajax_do_call(
        'haclAcllist',
        [ types.join(','), document.getElementById('acl_filter').value, pg*limit, limit ],
        function(request)
        {
            document.getElementById('acl_list').innerHTML = request.responseText;
            var tp = document.getElementById('totalPages');
            if (!tp)
                return;
            var totalPages = parseInt(tp.value);
            document.getElementById('resultPagesP').style.display = totalPages <= 1 ? 'none' : '';
            if (totalPages > 1)
            {
                var e = '';
                var p = document.getElementById('pageUrl').value;
                for (var i = 0; i < totalPages; i++)
                {
                    if (i == pg)
                        e += ' <b>'+(i+1)+'</b>';
                    else
                        e += ' <a href="'+p+'&offset='+(limit*i)+'" onclick="change_page('+i+');return false;">'+(i+1)+'</a>';
                }
                document.getElementById('resultPages').innerHTML = e;
            }
        }
    );
}
function change_page(n)
{
    document.getElementById('acl_page').value = n;
    reload_acl();
}
exAttach(window, 'load', function() { reload_acl() });
</script>
