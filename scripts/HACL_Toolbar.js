/* json ajax shim for IntraACL (shared with all other scripts) */
window.haclt_ajax = function(action, params, callback, dataType)
{
	$.ajax({
		type: "POST",
		url: mw.util.wikiScript(),
		data: {
			action: 'ajax',
			rs: action,
			rsargs: params
		},
		dataType: (dataType||'json'),
		success: callback
	});
};
/* Change "go to selected ACL &rarr;" link */
window.haclt_change_goto = function(e, msg)
{
	var l = document.getElementById('hacl_toolbar_goto');
	var t = e.options[e.selectedIndex].title;
	l.style.display = t ? '' : 'none';
	l.href = t ? mw.config.get('wgScript')+'?title='+encodeURI(t) : '';
	l.title = msg.replace('$1', t);
};
/* Show/hide embedded content toolbar */
window.haclt_show = function(n, s)
{
	var emb = document.getElementById('haclt_emb');
	var t = document.getElementById('haclt_'+n+'_text');
	if (s === null || s === undefined)
		s = t.style.display == 'none';
	if (n == 'emb' && s && emb.className != 'x xl')
	{
		t.style.display = '';
		haclt_ajax('haclSDExists_GetEmbedded', ['page', mw.config.get('wgPageName')], function(data)
		{
			emb.innerHTML = data.embedded;
			t.style.display = data.embedded ? '' : 'none';
			emb.className = data.embedded ? 'x xl' : 'x';
		});
	}
	else
		t.style.display = s ? '' : 'none';
};
/* Append category 'cat' text into wpTextbox1 if upl==false, or wpUploadDescription if upl==true */
window.haclt_addcat = function(cat, upl)
{
	if (cat)
	{
		if (window.wikEd && wikEd.useWikEd)
			wikEd.UpdateTextarea();
		document.getElementById(upl ? 'wpUploadDescription' : 'wpTextbox1').value += '\n[['+cat+']]\n';
		if (window.wikEd && wikEd.useWikEd)
			wikEd.UpdateFrame();
	}
};
/* Check all embedded content checkboxes (shared with ACLEditor.js) */
window.hacle_checkall = function(c, ids)
{
	c = c.checked;
	for (var id in ids)
	{
		id = ids[id];
		var chk = document.getElementById('sd_emb_'+id);
		if (chk)
			chk.checked = c;
	}
};
/* Uncheck all embedded content checkboxes (shared with ACLEditor.js) */
window.hacle_noall = function(c)
{
	c = c.checked;
	if (!c)
	{
		c = document.getElementById('hacl_emb_all');
		if (c)
			c.checked = false;
	}
};
