<?php
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

            $naviforms = new naviforms();

            $object = new stdClass();
            if($object_type == 'user')
            {
                $object = new user();
                $object->load($object_id);
            }
			else if($object_type == 'profile')
			{
	            $prf_obj = new profile();
	            $prf_obj->load($object_id);
			}

            $permissions_definitions = permission::get_definitions();

            $permissions_values = permission::get_values($object_type, $object, $permissions_definitions, $ws_id);

            $permissions_definitions = array_merge(
                $permissions_definitions['system'],
                $permissions_definitions['functions'],
                $permissions_definitions['extensions']
            );

            $out = array();

            $iRow = 0;

            for($i=0; $i < count($permissions_definitions); $i++)
            {
                $control = '';
                $type = '';
                $scope = t(470, 'System');

                $field_name = "wid".$ws_id.".".$permissions_definitions[$i]['name'];

                if($permissions_definitions[$i]['scope']=='functions')
                    $scope = t(240, 'Functions');
                else if($permissions_definitions[$i]['scope']=='extensions')
                    $scope = t(327, 'Extensions');

                switch($permissions_definitions[$i]['type'])
                {
                    case 'boolean':
                        $type = t(206, 'Boolean');
                        $control = $naviforms->buttonset(
                            $field_name,
                            array(
                                'true' => '<span class="ui-icon ui-icon-circle-check"></span>',
                                'false' => '<span class="ui-icon ui-icon-circle-close"></span>'
                            ),
                            $permissions_values[$permissions_definitions[$i]['name']],
                            "navigate_permission_change_boolean(this);"
                        );
                        break;

                    case 'integer':
                        $type = t(468, 'Integer');
                        $control = $naviforms->textfield(
                            $field_name,
                            $permissions_values[$permissions_definitions[$i]['name']],
                            '99%',
                            'navigate_permission_change_text(this);'
                        );
                        break;

	                case 'option':
	                case 'moption':

                        $options = $permissions_definitions[$i]['options'];

                        switch($options)
						{
                            case "websites":
                                $options = array();
                                $DB->query("SELECT id, name FROM nv_websites");
                                $websites = $DB->result();
                                foreach($websites as $ws)
                                    $options[$ws->id] = $ws->name;
                                break;

                            case "structure":
                                $options = array();
                                $categories_count = '';
								if(count($permissions_values[$permissions_definitions[$i]['name']]))
	                                $categories_count = ' ('.count($permissions_values[$permissions_definitions[$i]['name']]).')';
                                $control = '<button data-permission-name="'.$permissions_definitions[$i]['name'].'" data-action="structure" data-value="'.$permissions_values[$permissions_definitions[$i]['name']].'"><i class="fa fa-sitemap fa-fw"></i> '.t(611, "Choose").$categories_count.'</button>';
                                break;

                            default:
						}

		                $type = t(200, 'Options');
                        if(empty($control))
                            $control = $naviforms->selectfield(
                                $field_name,
                                array_keys($options),
                                array_values($options),
                                $permissions_values[$permissions_definitions[$i]['name']],
                                'navigate_permission_change_option(this);',
                                ($permissions_definitions[$i]['type']=='moption') // multiple?
                            );
		                break;

                    case 'string':
                    default:
                        $type = t(469, 'String');
                        $control = $naviforms->textfield(
                            $field_name,
                            $permissions_values[$permissions_definitions[$i]['name']],
                            '99%',
                            'navigate_permission_change_text(this);'
                        );
                        break;
                }

                // search filters
                if(!empty($_REQUEST['filters']))
                {
                    $include = navitable::jqgridCheck(
                        array(
                            'name' => $permissions_definitions[$i]['name'],
                            'scope' => $scope,
                            'type' => $type,
                            'value' => $permissions_values[$permissions_definitions[$i]['name']]
                        ),
                        $_REQUEST['filters']
                    );

                    if(!$include)
                        continue;
                }

                $out[$iRow] = array(
                    0	=> $permissions_definitions[$i]['name'],
                    1	=> '<div data-description="'.$permissions_definitions[$i]['description'].'"><span class="ui-icon ui-icon-float ui-icon-info"></span>&nbsp;<span>'.$permissions_definitions[$i]['name'].'</span></div>',
                    2	=> $scope,
                    3   => $type,
                    4   => $control //$permissions_values[$permissions_definitions[$i]['name']]
                );

                $iRow++;
            }

            navitable::jqgridJson($out, $page, $offset, $max, count($out));
            core_terminate();
            break;

		default:
	}
	
	return $out;
}

?>