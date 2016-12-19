<?php
require_once(NAVIGATE_PATH.'/lib/packages/permissions/permissions.functions.php');

function run()
{
    global $DB;

	switch(@$_REQUEST['act'])
	{
        case 'list':
            $object_type    = $_REQUEST['object'];
            $page           = intval($_REQUEST['page']);
            $max	        = intval($_REQUEST['rows']);
            $object_id      = intval($_REQUEST['object_id']);
            $ws_id          = intval($_REQUEST['website']);
            $offset         = ($page - 1) * $max;

            $rows = nvweb_permissions_rows($ws_id, $object_type, $object_id);

            navitable::jqgridJson($rows, $page, $offset, $max, count($rows));
            core_terminate();
            break;

		default:
	}
}

?>