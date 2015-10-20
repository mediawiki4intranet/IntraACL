<p><b><?= wfMsg('hacl_grouplist_filter_name') ?></b></p>
<p><input type="text" id="acl_filter" onchange="change_filter()" onkeyup="change_filter()" style="width: 400px" /></p>
<p><b><?= wfMsg('hacl_grouplist_filter_not_name') ?></b></p>
<p><input type="text" id="acl_not_filter" onchange="change_filter()" onkeyup="change_filter()" style="width: 400px" /></p>

<div id="acl_list" style="border: 1px solid gray; width: 500px; height: 500px; padding: 5px; overflow-y: scroll; overflow: -moz-scrollbars-vertical; float: left"></div>

<div style="clear: both"></div>
