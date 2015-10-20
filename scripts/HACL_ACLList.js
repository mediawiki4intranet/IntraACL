window.change_filter = function(chk)
{
    document.getElementById('acl_page').value = 0;
    reload_acl(chk);
};

window.reload_acl = function(chk)
{
    var aclTypeGroups = mw.config.get('aclTypeGroups');
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
    haclt_ajax(
        'haclAcllist', [ types.join(','), document.getElementById('acl_filter').value, pg*limit, limit ],
        function(result)
        {
            document.getElementById('acl_list').innerHTML = result;
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
        },
        'html'
    );
};

window.change_page = function(n)
{
    document.getElementById('acl_page').value = n;
    reload_acl();
};

$(document).ready(function() { reload_acl() });
