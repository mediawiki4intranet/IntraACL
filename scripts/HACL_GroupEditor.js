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
//   NS_ACL:        ACL namespace name
//   group_prefix:  Prefix for group articles
//   initial_group: name of group we are currently editing
window.HACLGroupEditor = function(NS_ACL, group_prefix, initial_group)
{
    // properties
    this.NS_ACL = NS_ACL;
    this.group_prefix = group_prefix;
    this.group_name = '';
    this.members = {};
    this.managers = {};
    this.ind_members = {};
    this.ind_managers = {};
    this.group_cache = {};
    this.limit = 11;
    this.regexp_user = mw.msg('hacl_regexp_user');
    this.regexp_user = this.regexp_user ? new RegExp(this.regexp_user, 'gi') : '';
    this.regexp_group = mw.msg('hacl_regexp_group');
    this.regexp_group = this.regexp_group ? new RegExp(this.regexp_group, 'gi') : '';

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
        var t = this.NS_ACL+':'+this.group_prefix+'/'+name;
        pn.innerHTML = t;
        pn.href = mw.config.get('wgScript')+'/'+t;
        document.getElementById('wpTitle').value = t;
        document.getElementById('grp_delete_link').href = mw.config.get('wgScript') + '?title=' + encodeURI(t) + '&action=delete';
        if (total_change)
        {
            var ge = this;
            haclt_ajax('haclGroupExists', [ name ], function(result) { ge.exists_ajax(result) });
        }
    }
    if (!name.length || !total_change)
        document.getElementById('grp_exists_hint').style.display = 'none';
    document.getElementById('grp_delete_link').style.display = name.length ? '' : 'none';
    document.getElementById('grp_pns').style.display = name.length ? '' : 'none';
    document.getElementById('grp_pnhint').style.display = name.length ? 'none' : '';
};

// react to group existence check
HACLGroupEditor.prototype.exists_ajax = function(exists)
{
    if (exists)
        document.getElementById('grp_exists_hint').style.display = '';
    else
        document.getElementById('grp_delete_link').style.display = 'none';
    document.getElementById('wpSave').value = mw.msg(exists ? 'hacl_grp_save' : 'hacl_grp_create');
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
HACLGroupEditor.prototype.get_ajax_groups = function(d)
{
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
        if (what === true || what == x[i][1])
            this['hint_'+x[i][0]+'_'+x[i][1]+'s'].onChange(true);
};

// parse ACL and re-fill closure
HACLGroupEditor.prototype.parse_fill_indirect = function()
{
    this.parse();
    this.reload_indirect(true);
};

// re-fill group closure
HACLGroupEditor.prototype.reload_indirect = function(refresh_hints_what)
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
        var self = this;
        haclt_ajax(
            'haclGroupClosure', [ fetch.join(','), '' ], function(result)
            {
                self.get_ajax_groups(result);
                var chg = self.fill_indirect({ 'members' : g, 'managers' : mg });
                if (chg && refresh_hints_what)
                    self.refresh_hints(refresh_hints_what);
            }
        );
    }
    else
    {
        if (g.length || mg.length)
            this.fill_indirect({ 'members' : g, 'managers' : mg });
        if (refresh_hints_what)
            this.refresh_hints(refresh_hints_what);
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

// show current members/groups when user has not yet entered anything to autocomplete
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
    var current = [];
    for (var i in cur_names)
    {
        i = cur_names[i];
        if ((what == 'group') == (i.substr(0, 6) == 'Group/'))
        {
            var n = i, rn;
            if (i == '*')
                rn = '*: '+mw.msg('hacl_edit_all');
            else if (i == '#')
                rn = '#: '+mw.msg('hacl_edit_reg');
            else
                rn = n = htmlspecialchars(i.replace(/^User:|^Group\//, ''));
            current.push([
                n + ' ' + (current_hash[i] ? mw.msg('hacl_indirect_through', current_hash[i]) : ''), n,
                current_hash[i] && true, current_hash[i] !== undefined
            ]);
        }
    }
    var prompt = (current.length == 0
        ? mw.msg('hacl_no_'+who+'_'+what)+' '+mw.msg('hacl_start_typing_'+what)
        : mw.msg('hacl_current_'+who+'_'+what));
    return [ prompt, current ];
};

// autocomplete load handler for all autocompleters
HACLGroupEditor.prototype.load_handler = function(h, v)
{
    var what = h.input.id == 'member_groups' || h.input.id == 'manager_groups' ? 'group' : 'user';
    var who = h.input.id == 'member_groups' || h.input.id == 'member_users' ? 'member' : 'manager';
    if (!v.length)
    {
        var o = this.get_empty_hint(who, what);
        h.prompt = h.emptyText = o[0];
        h.replaceItems(o[1]);
    }
    else
    {
        h.prompt = '';
        h.emptyText = mw.msg('hacl_autocomplete_no_'+what+'s');
        var self = this;
        haclt_ajax('haclAutocomplete', [ what, v, self.limit ], function(result)
        {
            self.set_item_status(who, what == 'group' ? 'Group/' : 'User:', result);
            h.replaceItems(result);
        });
    }
};

// called after loading AJAX autocomplete data, sets checked and disabled status for all checkboxes
HACLGroupEditor.prototype.set_item_status = function(who, prefix, items)
{
    var c, g;
    for (var i in items)
    {
        c = items[i];
        if ((g = this['ind_'+who+'s'][prefix+c[1]]) ||
            (g = this[who+'s']['*'] && '*') ||
            (g = this[who+'s']['#'] && '#'))
        {
            c[0] = c[1] + ' ' + mw.msg('hacl_indirect_through', g);
            c[2] = c[3] = true;
        }
        else if (this[who+'s'][prefix+c[1]])
        {
            c[2] = false;
            c[3] = true;
        }
        else
        {
            c[2] = c[3] = false;
        }
    }
};

// handler for selection of autocomplete item
HACLGroupEditor.prototype.set_handler = function(hint, index, item)
{
    var grp = hint.input.id == 'member_groups' || hint.input.id == 'manager_groups';
    var hash = hint.input.id == 'member_groups' || hint.input.id == 'member_users'
        ? this.members : this.managers;
    var to = item[1] == '*' || item[1] == '#' ? item[1] : (grp ? 'Group/' : 'User:')+item[1];
    if (item[3])
        hash[to] = true;
    else
        delete hash[to];
    this.save();
    if (grp || to == '*' || to == '#')
        this.reload_indirect('user');
};

// Initialize group editor
// Events which are set outside:
// id="grp_name" onchange="GE.name_change(true)" onkeyup="GE.name_change()"
// id="grp_def" onchange="GE.parse_fill_indirect()"
HACLGroupEditor.prototype.init = function(initial_group)
{
    var self = this;
    // create autocompleters
    var set = function(hint, index, item) { self.set_handler(hint, index, item) };
    var load = function(h, v) { self.load_handler(h, v) };
    self.hint_member_users = new SimpleAutocomplete('member_users', load, { multipleListener: set });
    self.hint_member_groups = new SimpleAutocomplete('member_groups', load, { multipleListener: set });
    self.hint_manager_users = new SimpleAutocomplete('manager_users', load, { multipleListener: set });
    self.hint_manager_groups = new SimpleAutocomplete('manager_groups', load, { multipleListener: set });
    // init group name
    document.getElementById('grp_name').value = initial_group;
    self.name_change(false);
    // parse definition
    self.parse_fill_indirect();
};

window.GE = new HACLGroupEditor(mw.config.get('aclGroupEditor').NS_ACL, 'Group', mw.config.get('aclGroupEditor').grpName);
