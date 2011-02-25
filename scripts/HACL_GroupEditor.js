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

// Constructor
// Parameters:
// msg: hash of localisation messages in form { KEY : wfMsgNoTrans('hacl_KEY') }
//  Needs following keys:
//   grp_save grp_create no_member_user no_member_group no_manager_user no_manager_group
//   current_member_user current_member_group current_manager_user current_manager_group
//   regexp_user regexp_group start_typing_user start_typing_group
//  Plus group_prefix = $haclgContLang->mGroupPrefix
//  Plus NS_ACL = getNsText(HACL_NS_ACL)
// initial_group: name of group we are currently editing
var HACLGroupEditor = function(msg, initial_group)
{
    // properties
    this.msg = msg;
    this.group_name = '';
    this.members = {};
    this.managers = {};
    this.ind_members = {};
    this.ind_managers = {};
    this.group_cache = {};
    this.limit = 11;
    this.regexp_user = msg.regexp_user ? new RegExp(msg.regexp_user, 'gi') : '';
    this.regexp_group = msg.regexp_group ? new RegExp(msg.regexp_group, 'gi') : '';

    // initialize
    this.init(initial_group);
};

// group name changed
HACLGroupEditor.prototype.name_change = function(total_change)
{
    var gn = document.getElementById('grp_name');
    var name = gn.value.trim();
    if (this.group_name.length && this.group_name == name && !total_change)
        return;
    this.group_name = name;
    if (name.length)
    {
        var pn = document.getElementById('grp_pn');
        var t = this.msg.NS_ACL+':'+this.msg.group_prefix+'/'+name;
        pn.innerHTML = t;
        pn.href = wgScript+'/'+t;
        document.getElementById('wpTitle').value = t;
        document.getElementById('grp_delete_link').href = wgScript + '?title=' + encodeURI(t) + '&action=delete';
        if (total_change)
        {
            var ge = this;
            sajax_do_call('haclGroupExists', [ name ], function(request) { ge.exists_ajax(request) });
        }
    }
    if (!name.length || !total_change)
        document.getElementById('grp_exists_hint').style.display = 'none';
    document.getElementById('grp_delete_link').style.display = name.length ? '' : 'none';
    document.getElementById('grp_pns').style.display = name.length ? '' : 'none';
    document.getElementById('grp_pnhint').style.display = name.length ? 'none' : '';
};

// react to group existence check
HACLGroupEditor.prototype.exists_ajax = function(request)
{
    if (request.status != 200)
        return;
    var exists = eval('('+request.responseText+')'); // json parse
    if (exists)
        document.getElementById('grp_exists_hint').style.display = '';
    else
        document.getElementById('grp_delete_link').style.display = 'none';
    document.getElementById('wpSave').value = exists ? this.msg.grp_save : this.msg.grp_create;
};

// parse PF parameter text into array of comma separated values
// is_assigned_to=true means it is the list of users/groups
HACLGroupEditor.prototype.pf_param = function(name, value, is_assigned_to)
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

// parse group definition text from textbox
HACLGroupEditor.prototype.parse = function()
{
    var t = document.getElementById('grp_def').value;
    var m, i, k, ass;
    // members
    this.members = {};
    i = 0;
    m = t.match(/\{\{\s*\#member\s*:\s*[^\}]*?\}\}/ig) || [];
    while (m[i])
    {
        ass = this.pf_param('members', m[i], true);
        for (k in ass)
            this.members[ass[k]] = true;
        i++;
    }
    // manage group
    this.managers = {};
    i = 0;
    m = t.match(/\{\{\s*\#manage\s+group\s*:\s*[^\}]*?\}\}/ig) || [];
    while (m[i])
    {
        ass = this.pf_param('assigned to', m[i], true);
        for (k in ass)
            this.managers[ass[k]] = true;
        i++;
    }
    this.check_errors();
};

// Check for errors (now: at least 1 manager defined, at least 1 member defined)
HACLGroupEditor.prototype.check_errors = function()
{
    var has_managers = false, has_members = false;
    for (var m in this.members)
    {
        has_members = true;
        break;
    }
    for (var m in this.managers)
    {
        has_managers = true;
        break;
    }
    document.getElementById('grp_define_member').style.display = has_members ? 'none' : '';
    document.getElementById('grp_define_manager').style.display = has_managers ? 'none' : '';
};

// fill group closure cache with AJAX data
HACLGroupEditor.prototype.get_ajax_groups = function(request)
{
    if (request.status != 200)
        return;
    var d = eval('('+request.responseText+')'); // JSON parse
    if (d && d['groups'])
        for (var g in d['groups'])
            this.group_cache[g] = d['groups'][g];
};

// fill indirect members
// h = { members: [ 'group1', ... ], managers: [ 'group1', ... ] }
HACLGroupEditor.prototype.fill_indirect = function(h)
{
    var changed = false;
    for (var x in h) // x = members, managers
    {
        for (var g in h[x])
        {
            g = h[x][g];
            changed = true;
            if (this.group_cache[g])
                for (var m in this.group_cache[g])
                    this['ind_'+x][this.group_cache[g][m]] = g;
        }
    }
    return changed;
};

// Refresh hints
HACLGroupEditor.prototype.refresh_hints = function(what)
{
    var x = [ ['member', 'user'], ['member', 'group'], ['manager', 'user'], ['manager', 'group'] ];
    var hint;
    for (var i in x)
    {
        if (what === true || what == x[i][1])
        {
            hint = this['hint_'+x[i][0]+'_'+x[i][1]+'s'];
            if (!hint.element.value.trim().length)
                hint.change_ajax(this.get_empty_hint(x[i][0], x[i][1]));
            else
                this.find_set(x[i][0], x[i][1] == 'group' ? 'Group/' : 'User:', hint, hint.tip_div);
        }
    }
};

// parse ACL and re-fill closure
HACLGroupEditor.prototype.parse_fill_indirect = function()
{
    this.parse();
    this.reload_indirect(true);
};

// re-fill group closure
HACLGroupEditor.prototype.reload_indirect = function(refresh_hints)
{
    var fetch = [];
    // members
    var g = [];
    for (var k in this.members)
    {
        if (k.substr(0, 6) == 'Group/')
        {
            g.push(k);
            if (!this.group_cache[k])
                fetch.push(k);
        }
    }
    // managers
    var mg = [];
    for (var k in this.managers)
    {
        if (k.substr(0, 6) == 'Group/')
        {
            mg.push(k);
            // avoid duplicates
            if (!this.group_cache[k] && !this.members[k])
                fetch.push(k);
        }
    }
    // fetch and/or fill
    this.ind_members = {};
    this.ind_managers = {};
    if (fetch.length)
    {
        var ge = this;
        sajax_do_call(
            'haclGroupClosure',
            [ fetch.join(','), '' ],
            function(request)
            {
                ge.get_ajax_groups(request);
                var chg = ge.fill_indirect({ 'members' : g, 'managers' : mg });
                if (chg && refresh_hints)
                    ge.refresh_hints(refresh_hints);
            }
        );
    }
    else
    {
        if (g.length || mg.length)
            this.fill_indirect({ 'members' : g, 'managers' : mg });
        if (refresh_hints)
            this.refresh_hints(refresh_hints);
    }
};

// save group definition text into textbox
HACLGroupEditor.prototype.save = function()
{
    // remove old definitions
    var t = document.getElementById('grp_def').value;
    t = t.replace(/\{\{\s*\#(member|manage\s+group):\s*[^\}]*?\}\}\s*/ig, '').trim();
    if (t.length)
        t = t + '\n';
    // build {{#member: }}
    var m = [];
    for (var j in this.members)
        m.push(j);
    if (m.length)
        t = t + "{{#member: members = "+m.join(", ")+"}}\n";
    // build {{#manage group: }}
    m = [];
    for (var j in this.managers)
        m.push(j);
    if (m.length)
        t = t + "{{#manage group: assigned to = "+m.join(", ")+"}}\n";
    // save text
    document.getElementById('grp_def').value = t;
    this.check_errors();
};

// get autocomplete html code for the case when to_name is empty
HACLGroupEditor.prototype.get_empty_hint = function(who, what)
{
    var pref = who[1]+what[0];
    var current_hash = {};
    if (who == 'member')
    {
        for (var i in this.members)
            current_hash[i] = false;
        for (var i in this.ind_members)
            current_hash[i] = this.ind_members[i]; // true = cannot revoke
    }
    else
    {
        for (var i in this.managers)
            current_hash[i] = false;
        for (var i in this.ind_managers)
            current_hash[i] = this.ind_managers[i]; // true = cannot revoke
    }
    var cur_names = [];
    for (var i in current_hash)
        if (i != '*' && i != '#')
            cur_names.push(i);
    var empty = !cur_names.length;
    if (what == 'user')
    {
        // add all users, registered users
        if (current_hash['*'] !== undefined || current_hash['#'] !== undefined)
        {
            var g = current_hash['*'] !== undefined ? '*' : '#';
            for (var i in current_hash)
                if (i != '*' && i != '#')
                    current_hash[i] = g;
        }
        cur_names.push('*');
        cur_names.push('#');
    }
    var j = 0;
    var current = [];
    for (var i in cur_names)
    {
        i = cur_names[i];
        if ((what == 'group') == (i.substr(0, 6) == 'Group/'))
        {
            var n = i, rn;
            if (i == '*')
                rn = '*: '+this.msg.edit_all;
            else if (i == '#')
                rn = '#: '+this.msg.edit_reg;
            else
                rn = n = htmlspecialchars(i.replace(/^User:|^Group\//, ''));
            current.push(
                '<div id="'+pref+'_'+j+'" class="hacl_ti'+(current_hash[i] ? ' hacl_dis' : '')+'" title="'+n+
                '"><input style="cursor: pointer" type="checkbox" id="c'+pref+'_'+j+
                '"'+(current_hash[i] ? ' disabled="disabled"' : '')+
                (current_hash[i] !== undefined ? ' checked="checked"' : '')+' /> '+
                rn+' <span id="t'+pref+'_'+j+'">'+(current_hash[i] ? this.msg.indirect_through.replace('$1', current_hash[i]) : '')+'</span></div>');
            j++;
        }
    }
    var ht = '<div class="hacl_tt">'+(current.length == 0
        ? this.msg['no_'+who+'_'+what]+' '+this.msg['start_typing_'+what]
        : this.msg['current_'+who+'_'+what])+'</div>'+current.join('');
    return ht;
};

// autocomplete load handler for all autocompleters
HACLGroupEditor.prototype.load_handler = function(ge, h, v)
{
    var what = h.element.id == 'member_groups' || h.element.id == 'manager_groups' ? 'group' : 'user';
    var who = h.element.id == 'member_groups' || h.element.id == 'member_users' ? 'member' : 'manager';
    if (!v.length)
        h.change_ajax(ge.get_empty_hint(who, what));
    else
        sajax_do_call('haclAutocomplete', [ what, v, ge.limit, who[1]+what[0] ],
            function (request)
            {
                if (request.status == 200)
                {
                    h.change_ajax(request.responseText);
                    ge.find_set(who, what == 'group' ? 'Group/' : 'User:', h, h.tip_div);
                }
            });
};

// called after loading AJAX autocomplete data, sets checked and disabled status for all checkboxes
HACLGroupEditor.prototype.find_set = function(who, prefix, h, e)
{
    var c, g;
    for (var i in e.childNodes)
    {
        c = e.childNodes[i];
        if (c.className && c.className.indexOf(h.style_prefix+'_ti') >= 0)
        {
            var chk = document.getElementById('c'+c.id);
            if ((g = this['ind_'+who+'s'][prefix+c.title]) ||
                (g = this[who+'s']['*'] && '*') ||
                (g = this[who+'s']['#'] && '#'))
            {
                chk.checked = true;
                chk.disabled = true;
                c.className = 'hacl_ti hacl_dis';
                document.getElementById('t'+c.id).innerHTML =
                    this.msg.indirect_through.replace('$1', g);
            }
            else if (this[who+'s'][prefix+c.title])
            {
                chk.checked = true;
                chk.disabled = false;
                c.className = 'hacl_ti';
            }
            else
            {
                chk.checked = false;
                chk.disabled = false;
                c.className = 'hacl_ti';
            }
        }
        else
            this.find_set(who, prefix, h, c);
    }
};

// handler for selection of autocomplete item
HACLGroupEditor.prototype.set_handler = function(ge, hint, ev, e)
{
    var old_target = ev ? ev.target || ev.srcElement : null;
    var chk = document.getElementById('c'+e.id);
    if (chk.disabled)
        return;
    if (chk != old_target)
        chk.checked = !chk.checked;
    var grp = hint.element.id == 'member_groups' || hint.element.id == 'manager_groups';
    var hash = hint.element.id == 'member_groups' || hint.element.id == 'member_users'
        ? this.members : this.managers;
    to = e.title == '*' || e.title == '#' ? e.title : (grp ? 'Group/' : 'User:')+e.title;
    if (chk.checked)
        hash[to] = true;
    else
        delete hash[to];
    ge.save();
    if (grp || to == '*' || to == '#')
        ge.reload_indirect('user');
};

// Initialize group editor
// Events which are set outside:
// id="grp_name" onchange="GE.name_change(true)" onkeyup="GE.name_change()"
// id="grp_def" onchange="GE.parse_fill_indirect()"
HACLGroupEditor.prototype.init = function(initial_group)
{
    // use ge.XX instead of this.XX because methods are often called in element or SHint context
    var ge = this;
    // create autocompleters
    ge.hint_member_users = new SHint('member_users', 'hacl', function(h, v) { ge.load_handler(ge, h, v) });
    ge.hint_member_groups = new SHint('member_groups', 'hacl', function(h, v) { ge.load_handler(ge, h, v) });
    ge.hint_manager_users = new SHint('manager_users', 'hacl', function(h, v) { ge.load_handler(ge, h, v) });
    ge.hint_manager_groups = new SHint('manager_groups', 'hacl', function(h, v) { ge.load_handler(ge, h, v) });
    ge.hint_member_users.set = function(ev, e) { ge.set_handler(ge, ge.hint_member_users, ev, e) };
    ge.hint_member_groups.set = function(ev, e) { ge.set_handler(ge, ge.hint_member_groups, ev, e) };
    ge.hint_manager_users.set = function(ev, e) { ge.set_handler(ge, ge.hint_manager_users, ev, e) };
    ge.hint_manager_groups.set = function(ev, e) { ge.set_handler(ge, ge.hint_manager_groups, ev, e) };
    ge.hint_member_users.init();
    ge.hint_member_groups.init();
    ge.hint_manager_users.init();
    ge.hint_manager_groups.init();
    // init group name
    document.getElementById('grp_name').value = initial_group;
    ge.name_change(false);
    // parse definition
    ge.parse_fill_indirect();
};
