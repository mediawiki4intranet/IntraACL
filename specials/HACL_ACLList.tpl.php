<div id="acl_list" style="border: 1px solid gray; width: 500px; height: 500px; padding: 5px; overflow-y: scroll; overflow: -moz-scrollbars-vertical; float: left"></div>

<div style="float: left; margin: 0 8px">
<p><b><?= wfMsg('hacl_acllist_filter_name') ?></b></p>
<p><input type="text" id="acl_filter" onchange="change_filter()" onkeyup="change_filter()" style="width: 400px" /></p>

<p><b><?= wfMsg('hacl_acllist_filter_type') ?></b></p>
<p><input type="checkbox" id="atg_all" checked="checked" onclick="change_filter(this)" onchange="change_filter(this)" /> <label for="atg_all"><?= wfMsg('hacl_acllist_typegroup_all') ?></label></p>
<ul>
<?php foreach($this->aclTargetTypes as $t => $l) { ?>
 <li>
  <input type="checkbox" id="atg_<?= $t ?>" checked="checked" onclick="change_filter(this)" onchange="change_filter(this)" /> <label for="atg_<?= $t ?>"><?= wfMsg('hacl_acllist_typegroup_'.$t) ?></label>
  <ul>
  <?php foreach($l as $k => $true) { ?>
   <li><input type="checkbox" id="at_<?= $k ?>" checked="checked" onclick="change_filter(this)" onchange="change_filter(this)" /> <label for="at_<?= $k ?>"><?= wfMsg('hacl_acllist_type_'.$k) ?></label></li>
  <? } ?>
  </ul>
 </li>
<? } ?>
</ul>
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
    for (var i in aclTypeGroups)
        for (var j in aclTypeGroups[i])
            if (document.getElementById('at_'+j).checked)
                types.push(j);
    sajax_do_call('haclAcllist', [ types.join(','), document.getElementById('acl_filter').value ],
        function(request) { document.getElementById('acl_list').innerHTML = request.responseText; }
    );
}
exAttach(window, 'load', function() { change_filter() });
</script>
