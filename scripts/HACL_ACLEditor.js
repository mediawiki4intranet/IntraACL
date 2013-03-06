if (!String.prototype.trim)
    String.prototype.trim = function() { return this.replace(/^\s*/, '').replace(/\s*$/, ''); };

// escape &<>"'
var htmlspecialchars = function(s)
{
    var r = { '&' : '&amp;', '<' : '&lt;', '>' : '&gt;', '"' : '&quot;', '\'' : '&apos;' };
    for (var i in r)
        s = s.replace(i, r[i]);
    return s;
};

/* msg:             Localisation messages (TODO: use ResourceLoader)
   petPrefixes:     PET_XX => prefix from haclgContLang
   isSysop:         Is current user a sysop or bureaucrat?
   initialTitle:    SD Title -> getText()
   initialType:     SD -> getPEType()
   initialExists:   Does the SD exist?
*/
var HACLACLEditor = function(params)
{
    this.msg = params.msg;
    this.pet_prefixes = params.petPrefixes;
    this.is_sysop = params.isSysop;

    this.group_cache = {};
    this.predef_cache = {};
    this.rights_direct = {};
    this.rights_indirect = {};
    this.predef_included = {};
    this.last_target_names = {};
    this.last_target_type = '';

    // Autocompleters (SHint's)
    this.user_hint = null;
    this.target_hint = null;
    this.inc_hint = null;

    this.regexp_user = this.msg.regexp_user ? new RegExp(this.msg.regexp_user, 'gi') : '';
    this.regexp_group = this.msg.regexp_group ? new RegExp(this.msg.regexp_group, 'gi') : '';
    this.action_alias = {};
    this.all_actions = [];

    var an = ['manage', 'create', 'delete', 'edit', 'move', 'read'];
    for (var a in an)
    {
        a = an[a];
        this.action_alias[a] = a;
        if (a != 'manage')
            this.all_actions.push(a);
    }

    this.init(params.initialTitle, params.initialType, params.initialExists);
};

// target ACL page name/type change
// total_change==true only when onchange is fired (element loses focus)
HACLACLEditor.prototype.target_change = function(total_change)
{
    // check if target really changed
    var what = document.getElementById('acl_what').value;
    var an = document.getElementById('acl_name');
    if (this.last_target_type != what)
    {
        total_change = true;
        if (this.last_target_type)
        {
            // remember name for each type separately
            this.last_target_names[this.last_target_type] = an.value;
            if (this.last_target_names[what])
                an.value = this.last_target_names[what];
            else if (what == 'template')
                an.value = wgUserName;
            else
                an.value = '';
            this.target_hint.curValue = null; // force SHint refill
            this.last_target_type = what; // prevent recursion
            this.target_hint.change_old();
        }
        this.last_target_type = what;
    }
    var name = an.value.trim();
    if (this.last_target_names[this.last_target_type] &&
        this.last_target_names[this.last_target_type] == name &&
        !total_change)
        return;
    this.last_target_names[this.last_target_type] = name;
    // yes, target really changed, hide/show elements
    var t;
    if (name.length)
    {
        var pn = document.getElementById('acl_pn');
        t = this.msg.NS_ACL+':'+this.pet_prefixes[what]+'/'+name;
        pn.innerHTML = t;
        pn.href = wgScript+'/'+encodeURI(t);
        document.getElementById('wpTitle').value = t;
        document.getElementById('acl_delete_link').href = wgScript + '?title=' + encodeURI(t) + '&action=delete';
        var ae = this;
        if (total_change)
        {
            // Retrieve SD status and embedded content list
            sajax_do_call('haclSDExists_GetEmbedded', [ what, name ], function(request) { ae.ajax_sd_exists(request) });
        }
    }
    else
    {
        // Clear Embedded ACL list for empty names
        var emb = document.getElementById('acl_embed');
        emb.innerHTML = '';
        emb.style.display = 'none';
    }
    if (!t || !total_change)
        document.getElementById('acl_exists_hint').style.display = 'none';
    document.getElementById('acl_delete_link').style.display = t ? '' : 'none';
    document.getElementById('acl_pns').style.display = t ? '' : 'none';
    document.getElementById('acl_pnhint').style.display = t ? 'none' : '';
    this.check_errors();
};

// haclSDExists_GetEmbedded callback
HACLACLEditor.prototype.ajax_sd_exists = function(request)
{
    if (request.status != 200)
        return;
    var data = eval('('+request.responseText+')'); // json parse
    document.getElementById('acl_exists_hint').style.display = data.exists ? '' : 'none';
    document.getElementById('acl_delete_link').style.display = data.exists ? '' : 'none';
    var emb = document.getElementById('acl_embed');
    emb.innerHTML = data.exists && data.embedded ? data.embedded : '';
    emb.style.display = data.exists && data.embedded ? '' : 'none';
    document.getElementById('wpSave').value = data.exists ? this.msg.edit_save : this.msg.edit_create;
};

// add predefined ACL inclusion
HACLACLEditor.prototype.include_acl = function()
{
    var inc = document.getElementById('inc_acl').value;
    if (inc)
    {
        this.predef_included[inc] = true;
        this.save_sd();
    }
};

// parse PF parameter text into array of comma separated values
// is_assigned_to=true means it is the list of users/groups
HACLACLEditor.prototype.pf_param = function(name, value, is_assigned_to)
{
    var re = new RegExp('[:\\|]\\s*' + name.replace(' ', '\\s+') + '\\s*=\\s*([^\\|\\}]*)', 'i');
    var ass = re.exec(value);
    if (!ass)
        return [];
    ass = (ass[1] || '');
    if (is_assigned_to)
    {
        if (this.regexp_user)
            ass = ass.replace(this.regexp_user, '$1User:');
        if (this.regexp_group)
            ass = ass.replace(this.regexp_group, '$1Group/');
    }
    return ass.trim().split(/[,\s]*,[,\s]*/);
};

// parse definition text from textbox
HACLACLEditor.prototype.parse_sd = function()
{
    var t = document.getElementById('acl_def').value;
    var m = t.match(/\{\{\s*\#access\s*:\s*[^\}]*?\}\}/ig) || [];
    var r = {}, i = 0, j, k, h, act, ass;
    while (m[i])
    {
        ass = this.pf_param('assigned to', m[i], true);
        act = this.pf_param('actions', m[i].toLowerCase(), false);
        h = {};
        for (j = act.length-1; j >= 0; j--)
        {
            if (act[j] == '*')
            {
                for (var x in this.all_actions)
                    h[this.all_actions[x]] = true;
            }
            else
                h[act[j]] = true;
        }
        for (j in h)
        {
            j = this.action_alias[j];
            if (!j)
            {
                // skip invalid actions
                continue;
            }
            for (k in ass)
            {
                r[ass[k]] = r[ass[k]] || {};
                r[ass[k]][j] = true;
            }
        }
        i++;
    }
    // template manage rights
    var m1 = t.match(/\{\{\s*\#manage\s+rights\s*:\s*[^\}]*?\}\}/ig) || [];
    i = 0;
    while (m1[i])
    {
        ass = this.pf_param('assigned to', m1[i], true);
        for (k in ass)
        {
            r[ass[k]] = r[ass[k]] || {};
            r[ass[k]]['template'] = true;
        }
        i++;
    }
    // ACL inclusions
    var m2 = t.match(/\{\{\s*\#predefined\s+right\s*:\s*[^\}]*?\}\}/ig) || [];
    i = 0;
    this.predef_included = {};
    while (m2[i])
    {
        ass = this.pf_param('rights', m2[i], false);
        for (k in ass)
            this.predef_included[ass[k]] = true;
        i++;
    }
    // Save this.rights_direct
    this.rights_direct = r;
};

// Check for errors (now: at least 1 manager defined, at least 1 action defined)
HACLACLEditor.prototype.check_errors = function()
{
    var has_managers = false, has_rights = false;
    var merge = [ this.rights_direct, this.rights_indirect ];
    var dontlose = false;
    var curUser = 'User:'+mediaWiki.config.get('wgUserName');
    for (var h in merge)
    {
        h = merge[h];
        for (var m in h)
        {
            for (var a in h[m])
            {
                if (a == 'manage' && this.last_target_type == 'page' ||
                    a == 'template')
                    has_managers = true;
                else
                    has_rights = true;
            }
            if (has_rights && has_managers)
                break;
        }
        dontlose = dontlose || h[curUser] && (this.last_target_type == 'page' && h[curUser]['manage'] || h[curUser]['template']);
    }
    document.getElementById('acl_define_rights').style.display = has_rights ? 'none' : '';
    var m = document.getElementById('acl_define_manager');
    m.style.display = has_managers ? 'none' : '';
    var managerErrorMessages = {
        'page': 'edit_define_manager',
        'category': 'edit_define_manager_np',
        'namespace': 'edit_define_manager_np',
        'right': 'edit_define_tmanager'
    };
    var msg = this.msg[managerErrorMessages[this.last_target_type]];
    if (!dontlose)
    {
        msg = this.msg['edit_lose'] + '<br />' + msg;
    }
    m.innerHTML = msg;
};

// fill in this.rights_direct with closure data
HACLACLEditor.prototype.closure_ajax = function(request)
{
    if (request.status != 200)
        return;
    var d = eval('('+request.responseText+')'); // JSON parse
    if (d && d['groups'])
        for (var g in d['groups'])
            this.group_cache[g] = d['groups'][g];
    if (d && d['rights'])
        for (var g in d['rights'])
            this.predef_cache[g] = d['rights'][g];
};

// modify closure, append d = [ group1, group2, ... ],
// append predefined rights sd = [ right1, ... ]
HACLACLEditor.prototype.closure_groups_sd = function(d, sd)
{
    var c = false;
    // Groups
    for (var g in d)
    {
        c = true;
        g = d[g];
        if (this.group_cache[g])
        {
            for (var m in this.group_cache[g])
            {
                m = this.group_cache[g][m];
                this.rights_indirect[m] = this.rights_indirect[m] || {};
                for (var a in this.rights_direct[g])
                    this.rights_indirect[m][a] = g;
            }
        }
    }
    // TODO: Also check namespace/category 'manage' rights for page targets
    // Predefined rights
    for (var r in sd)
    {
        c = true;
        r = sd[r];
        if (this.predef_cache[r])
        {
            for (var m in this.predef_cache[r])
            {
                this.rights_indirect[m] = this.rights_indirect[m] || {};
                for (var a in this.predef_cache[r][m])
                    this.rights_indirect[m][a] = m;
            }
        }
    }
    // refresh hint
    if (c && !this.user_hint.element.value.trim().length)
        this.user_hint.change_ajax(this.get_empty_hint());
};

// parse ACL and re-fill closure
HACLACLEditor.prototype.parse_make_closure = function()
{
    this.parse_sd();
    this.fill_closure();
};

// re-fill this.rights_indirect
HACLACLEditor.prototype.fill_closure = function()
{
    var ge = this;
    var g = [];
    var fetch = [];
    this.rights_indirect = {};
    for (var k in this.rights_direct)
    {
        if (k.substr(0, 6) == 'Group/')
        {
            g.push(k);
            if (!this.group_cache[k])
                fetch.push(k);
        }
    }
    var sd = [];
    var fetch_sd = [];
    for (var k in this.predef_included)
    {
        sd.push(k);
        if (!this.predef_cache[k])
            fetch_sd.push(k);
    }
    if (fetch.length || fetch_sd.length)
    {
        sajax_do_call(
            'haclGroupClosure',
            [ fetch.join(','), fetch_sd.join('[') ],
            function(request) { ge.closure_ajax(request); ge.closure_groups_sd(g, sd); ge.check_errors(); }
        );
    }
    else
    {
        if (g.length || sd.length)
            ge.closure_groups_sd(g, sd);
        ge.check_errors();
    }
};

// save definition text into textbox
HACLACLEditor.prototype.save_sd = function()
{
    var r = this.rights_direct, i, j, k, m, h, man;
    // remove old definitions
    var t = document.getElementById('acl_def').value;
    t = t.replace(/\{\{\s*\#(access|manage\s+rights|predefined\s*right):\s*[^\}]*?\}\}\s*/ig, '').trim();
    if (t.length)
        t = t + "\n";
    // build {{#access: }} rights
    m = {};
    for (j in r)
    {
        h = r[j];
        i = [];
        for (var k in h)
            if (k != 'template' &&
                k != 'manage')
                i.push(k);
        if (i.length)
        {
            i = i.sort();
            if (i.join(',') == this.all_actions.join(','))
                i = ['*'];
        }
        if (h['manage'])
            i.push(this.action_alias['manage']);
        if (i.length)
        {
            i = i.join(', ');
            m[i] = m[i] || [];
            m[i].push(j);
        }
    }
    for (j in m)
        t += '{{#access: assigned to = '+m[j].join(", ")+' | actions = '+j+"}}\n";
    // include {{#manage rights: }}
    m = [];
    for (j in r)
        if (r[j]['template'])
            m.push(j);
    if (m.length)
        t = t + '{{#manage rights: assigned to = '+m.join(", ")+"}}\n";
    // include predefined rights
    var predef = [];
    for (var i in this.predef_included)
        predef.push(i);
    if (predef.length)
        t = t + "{{#predefined right: rights="+predef.join(", ")+"}}\n";
    document.getElementById('acl_def').value = t;
    this.check_errors();
};

// onchange for action checkboxes
HACLACLEditor.prototype.act_change = function(e)
{
    if (e.disabled)
        return;
    var g_to = this.get_grant_to();
    var a = e.id.substr(4), direct, grp;
    if (a == 'all')
    {
        var act = this.all_actions;
        direct = grp = true;
        for (var i in act)
        {
            i = act[i];
            direct = direct && this.rights_direct[g_to] && this.rights_direct[g_to][i];
            grp = grp &&
                (this.rights_indirect[g_to] && this.rights_indirect[g_to][i] ||
                this.rights_direct['#'] && this.rights_direct['#'][i]);
        }
    }
    else
    {
        direct = this.rights_direct[g_to] && this.rights_direct[g_to][a];
        grp = this.rights_indirect[g_to] && this.rights_indirect[g_to][a] ||
            this.rights_direct['#'] && this.rights_direct['#'][a];
    }
    // this.grant if not yet
    if (e.checked && !direct && !grp)
        this.grant(g_to, a, true);
    // if right is this.granted through some group, we can't revoke
    else if (!e.checked && direct && !grp)
        this.grant(g_to, a, false);
};

// onchange for to_type
HACLACLEditor.prototype.to_type_change = function()
{
    var t = document.getElementById('to_type').value;
    document.getElementById('to_name').style.display = t == '*' || t == '#' ? 'none' : '';
    document.getElementById('to_name').value = '';
    this.to_name_change();
    // force refresh hint (for the case when value didn't change)
    this.user_hint.fill_handler(this.user_hint, '');
};

// additional onchange for to_name - load to_name's rights from this.rights_indirect
HACLACLEditor.prototype.to_name_change = function()
{
    var g_to = this.get_grant_to();
    var goto_link = document.getElementById('hacl_to_goto');
    if (g_to && g_to.substr(0, 6) == 'Group/')
    {
        goto_link.href = wgScript+'/'+this.msg.NS_ACL+':'+this.msg.group_prefix+'/'+encodeURI(g_to.substr(6));
        goto_link.title = this.msg.edit_goto_group.replace('$1', g_to.substr(6));
        goto_link.style.display = '';
    }
    else
        goto_link.style.display = 'none';
    var act = this.all_actions.slice(0); // copy array
    act.push('manage', 'template', 'all');
    var all_direct = true, all_grp = true;
    var c, l, direct, grp;
    for (var a in act)
    {
        a = act[a];
        c = document.getElementById('act_'+a);
        l = document.getElementById('act_label_'+a);
        // determine direct and indirect rights
        if (g_to)
        {
            direct = this.rights_direct[g_to] && this.rights_direct[g_to][a];
            grp = this.rights_indirect[g_to] && this.rights_indirect[g_to][a];
            if (!grp && g_to.substr(0, 5) == 'User:')
            {
                if (this.rights_direct['#'] && this.rights_direct['#'][a])
                    grp = this.msg.indirect_grant_reg;
                else if (!grp && this.rights_direct['*'] && this.rights_direct['*'][a])
                    grp = this.msg.indirect_grant_all;
            }
        }
        if (a == 'all')
        {
            // load saved all_direct and all_grp
            direct = all_direct;
            grp = all_grp;
        }
        else if (a != 'manage' && a != 'template')
        {
            // make all_direct and all_grp
            all_direct = all_direct && direct;
            all_grp = all_grp && grp;
        }
        c.checked = direct || grp;
        // disable checkbox:
        // - if right is granted through some group
        // - or if no grant target selected
        c.disabled = !g_to || grp;
        l.className = c.disabled ? 'act_disabled' : '';
        c.title = l.title = (grp ? this.msg.indirect_grant.replace('$1', grp) : this.msg['edit_ahint_'+a]);
    }
};

// get grant subject (*, #, User:X, Group/X)
HACLACLEditor.prototype.get_grant_to = function()
{
    var g_to = document.getElementById('to_type').value;
    if (g_to == '*' || g_to == '#')
        return g_to;
    var n = document.getElementById('to_name').value.trim();
    if (!n)
        return '';
    if (g_to == 'group')
        g_to = 'Group/' + n;
    else if (g_to == 'user')
        g_to = 'User:' + n;
    return g_to;
};

// this.grant/revoke g_act to/from g_to and update textbox with definition
// g_act is: one of this.all_actions, or 'manage', or 'all'
HACLACLEditor.prototype.grant = function(g_to, g_act, g_yes)
{
    if (g_act == 'all')
        act = this.all_actions;
    else
        act = [ g_act ];
    if (g_yes)
    {
        for (var a in act)
        {
            this.rights_direct[g_to] = this.rights_direct[g_to] || {};
            this.rights_direct[g_to][act[a]] = true;
        }
    }
    else
    {
        for (var a in act)
            if (this.rights_direct[g_to])
                delete this.rights_direct[g_to][act[a]];
    }
    if (g_act == 'all')
        for (var a in this.all_actions)
            document.getElementById('act_'+this.all_actions[a]).checked = g_yes;
    else
    {
        var c = this.rights_direct[g_to];
        if (c)
            for (var a in this.all_actions)
                c = c && this.rights_direct[g_to][this.all_actions[a]];
        document.getElementById('act_all').checked = c;
    }
    this.save_sd();
    if (g_to.substr(0, 6) == 'Group/')
        this.fill_closure();
};

// get autocomplete html code for the case when to_name is empty
HACLACLEditor.prototype.get_empty_hint = function()
{
    var tt = document.getElementById('to_type').value;
    var involved = [], n, j = 0;
    if (tt == 'group' || tt == 'user')
    {
        var x = {};
        for (n in this.rights_direct)
            for (j in this.rights_direct[n])
            {
                x[n] = true;
                break;
            }
        for (n in this.rights_indirect)
            for (j in this.rights_indirect[n])
            {
                x[n] = true;
                break;
            }
        j = 0;
        for (var n in x)
        {
            if (n != '*' && n != '#' &&
                (tt == 'group') == (n.substr(0, 6) == 'Group/'))
            {
                n = htmlspecialchars(n.replace(/^User:|^Group\//, ''));
                involved.push('<div id="hi_'+(++j)+'" class="hacl_ti" title="'+n+'">'+n+'</div>');
            }
        }
    }
    if (!involved.length)
        return '<div class="hacl_tt">'+this.msg['edit_no_'+tt+'s_affected']+' '+this.msg['start_typing_'+tt]+'</div>';
    return '<div class="hacl_tt">'+this.msg['edit_'+tt+'s_affected']+'</div>'+involved.join('');
};

HACLACLEditor.prototype.user_hint_fill = function(h, v)
{
    if (!v.length)
        h.change_ajax(this.get_empty_hint());
    else
        sajax_do_call('haclAutocomplete', [ document.getElementById('to_type').value, v ],
            function (request) { if (request.status == 200) h.change_ajax(request.responseText) })
};

HACLACLEditor.prototype.user_hint_change = function(h)
{
    // onchange for to_name
    if (!this.user_hint.element.value.trim())
        this.user_hint.msg_hint = this.get_empty_hint();
    var wv = document.getElementById('to_type').value;
    this.user_hint.element.style.display = wv == '*' || wv == '#' ? 'none' : '';
    if (wv == '*' || wv == '#')
        this.user_hint.focus(false);
    else
        this.user_hint.change_old();
};

HACLACLEditor.prototype.target_hint_fill = function (h, v)
{
    var wv = document.getElementById('acl_what').value;
    if (wv == 'right')
        return;
    // Always show autocomplete for namespaces
    if (wv != 'namespace' && !v.length)
        h.tip_div.innerHTML = '<div class="hacl_tt">'+this.msg['start_typing_'+wv]+'</div>';
    else
        sajax_do_call('haclAutocomplete', [ wv, v ],
            function (request) { if (request.status == 200) h.change_ajax(request.responseText) })
};

HACLACLEditor.prototype.target_hint_focus = function(f)
{
    this.target_hint.tip_div.style.display =
        this.last_target_type != 'right'
        && (f || this.target_hint.nodefocus) ? '' : 'none';
    this.target_hint.nodefocus = undefined;
};

HACLACLEditor.prototype.inc_hint_fill = function(h, v)
{
    sajax_do_call('haclAutocomplete', [ 'sd', v ],
        function (request) { if (request.status == 200) h.change_ajax(request.responseText) })
};

// Initialize ACL editor
HACLACLEditor.prototype.init = function(aclTitle, aclType, aclExists)
{
    if (aclTitle)
    {
        // JS split() has no limit parameter
        aclTitle = aclTitle.split('/');
        var typ = aclTitle.shift();
        aclTitle = [ typ, aclTitle.join('/') ];
        document.getElementById('acl_name').value = aclTitle[1];
        this.pet_prefixes[aclType] = aclTitle[0];
        var what_item = document.getElementById('acl_what_'+aclType);
        if (what_item)
            what_item.selected = true;
        else
            document.getElementById('acl_what_right').selected = true;
    }
    this.parse_make_closure();
    // use ge.XX instead of this.XX because methods are often called in element or SHint context
    var ge = this;
    // create autocompleter for user/group name
    this.user_hint = new SHint('to_name', 'hacl', function(h, v) { ge.user_hint_fill(h, v) });
    this.user_hint.change_old = this.user_hint.change;
    this.user_hint.change = function(ev) { ge.user_hint_change() };
    this.user_hint.onset = function(ev, e) { ge.to_name_change() };
    this.user_hint.init();
    exAttach('to_name', 'change', function(ev, e) { ge.to_name_change() });
    // init protection target
    this.target_change();
    this.to_name_change();
    // create autocompleter for protection target
    this.target_hint = new SHint('acl_name', 'hacl', function(h, v) { ge.target_hint_fill(h, v) });
    this.target_hint.change_old = this.target_hint.change;
    this.target_hint.change = function(ev) { ge.target_change(!ev); ge.target_hint.change_old(); };
    this.target_hint.h_blur = function(ev) { ge.target_change(true); ge.target_hint.focus(false); return 1; };
    // do not hint template targets
    this.target_hint.focus = function(f) { ge.target_hint_focus(f) };
    this.target_hint.onset = function(ev, e) { ge.target_change(ev, e) };
    this.target_hint.max_height = 400;
    this.target_hint.init();
    // create autocompleter for ACL inclusion
    this.inc_hint = new SHint('inc_acl', 'hacl', function(h, v) { ge.inc_hint_fill(h, v) });
    this.inc_hint.init();
    document.getElementById('acl_exists_hint').style.display = aclExists ? '' : 'none';
    document.getElementById('acl_delete_link').style.display = aclExists ? '' : 'none';
    this.check_errors();
}
