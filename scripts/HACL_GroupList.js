function change_filter(chk)
{
    haclt_ajax('haclGrouplist',
        [ document.getElementById('acl_filter').value, document.getElementById('acl_not_filter').value ],
        function(result) { document.getElementById('acl_list').innerHTML = result; }, 'html'
    );
}
$(document).ready(function() { change_filter() });
