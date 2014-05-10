<?php
require_once(NAVIGATE_PATH.'/lib/packages/profiles/profile.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
function run()
{
	global $user;	
	global $layout;
	global $DB;
	
	$out = '';
	$item = new user();
			
	switch($_REQUEST['act'])
	{
		case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
                    $deleted = 0;
					foreach($ids as $id)
					{
                        $item = new user();
						$item->load($id);
						$deleted = $deleted + $item->delete();
					}
					echo json_encode((count($ids)==$deleted));
					break;

				default: // list or search	
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby= $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = " 1=1 ";
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $item->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}
				
					$DB->queryLimit('id,username,email,profile,language,blocked', 
									'nv_users', 
									$where, 
									$orderby, 
									$offset, 
									$max);
									
					$dataset = $DB->result();
					$total = $DB->foundRows();
					
					//echo $DB->get_last_error();
					
					$out = array();				
										
					$profiles = profile::profile_names();
					$languages = language::language_names();
											
					for($i=0; $i < count($dataset); $i++)
					{													
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> '<strong>'.$dataset[$i]['username'].'</strong>',
							2	=> $dataset[$i]['email'],
							3 	=> $profiles[$dataset[$i]['profile']],
							4	=> $languages[$dataset[$i]['language']],		
							5	=> (($dataset[$i]['blocked']==1)? '<img src="img/icons/silk/cancel.png" />' : '')
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;
		
		case 2: // edit/new form		
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));
			}
		
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
					$item->save();
                    permission::update_permissions(json_decode($_REQUEST['navigate_permissions_changes'], true), 0, $item->id);
					$layout->navigate_notification(t(53, "Data saved successfully."), false);
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = users_form($item);
			break;
					
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = users_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = users_form($item);
				}
			}
			break;

        case 'permissions':
            $page   = intval($_REQUEST['page']);
            $max	= intval($_REQUEST['rows']);
            $user_id = intval($_REQUEST['user']);
            $offset = ($page - 1) * $max;

            $naviforms = new naviforms();

            $usr_obj = new user();
            $usr_obj->load($user_id);

            $permissions_definitions = permission::get_definitions();

            $permissions_values = permission::get_values('user', $usr_obj, $permissions_definitions);

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

                if($permissions_definitions[$i]['scope']=='functions')
                    $scope = t(240, 'Functions');
                else if($permissions_definitions[$i]['scope']=='extensions')
                    $scope = t(327, 'Extensions');

                switch($permissions_definitions[$i]['type'])
                {
                    case 'boolean':
                        $type = t(206, 'Boolean');
                        $control = $naviforms->buttonset(
                            $permissions_definitions[$i]['name'],
                            array(
                                'true' => '<span class="ui-icon ui-icon-circle-check"></span>',
                                'false' => '<span class="ui-icon ui-icon-circle-close"></span>'),
                            $permissions_values[$permissions_definitions[$i]['name']],
                            "navigate_permission_change_boolean(this);"
                        );
                        break;

                    case 'integer':
                        $type = t(468, 'Integer');
                        $control = $naviforms->textfield(
                            $permissions_definitions[$i]['name'],
                            $permissions_values[$permissions_definitions[$i]['name']],
                            '99%',
                            'navigate_permission_change_text(this);'
                        );
                        break;

                    case 'string':
                    default:
                        $type = t(469, 'String');
                        $control = $naviforms->textfield(
                            $permissions_definitions[$i]['name'],
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
                    1	=> '<span class="ui-icon ui-icon-float ui-icon-info" title="'.$permissions_definitions[$i]['description'].'"></span> <span title="'.$permissions_definitions[$i]['description'].'">'.$permissions_definitions[$i]['name'].'</span>',
                    2	=> $scope,
                    3   => $type,
                    4   => $control //$permissions_values[$permissions_definitions[$i]['name']]
                );

                $iRow++;
            }

            navitable::jqgridJson($out, $page, $offset, $max, count($out));
            core_terminate();
            break;

        /* unused (change permission value via AJAX)
        case 'permission_set':
            $permission = new permission();
            $permission->load($_REQUEST['name'], 0, $_REQUEST['user_id']);
            $permission->value = $_REQUEST['value'];
            echo $permission->save();
            core_terminate();
            break;
        */
					
		case 0: // list / search result
		default:			
			$out = users_list();
			break;
	}
	
	return $out;
}

function users_list()
{
	$navibars = new navibars();
	$navitable = new navitable("users_list");
	
	$navibars->title(t(15, 'Users'));

	$navibars->add_actions(	array(	'<a href="?fid=users&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid=users&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=users&act=1&_search=true&quicksearch=".$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid=users&act=1');
    $navitable->sortBy('id', 'DESC');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=users&act=2&id=');
    $navitable->enableDelete();
	
	$navitable->addCol("ID", 'id', "80", "true", "left");	
	$navitable->addCol(t(1, 'User'), 'username', "150", "true", "left");	
	$navitable->addCol(t(44, 'E-Mail'), 'email', "150", "true", "left");		
	$navitable->addCol(t(45, 'Profile'), 'profile', "100", "true", "left");		
	$navitable->addCol(t(46, 'Language'), 'language', "80", "true", "left");	
	$navitable->addCol(t(47, 'Blocked'), 'blocked', "50", "true", "center");		
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function users_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item->id))
		$navibars->title(t(15, 'Users').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(15, 'Users').' / '.t(170, 'Edit').' ['.$item->id.']');		

	if(empty($item->id))
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
									);
	}
	else
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
											'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
										)
									);		
								

		
		$delete_html = array();
		$delete_html[] = '<div id="navigate-delete-dialog" class="hidden">'.t(57, 'Do you really want to delete this item?').'</div>';
		$delete_html[] = '<script language="javascript" type="text/javascript">';
		$delete_html[] = 'function navigate_delete_dialog()';		
		$delete_html[] = '{';				
		$delete_html[] = '$("#navigate-delete-dialog").dialog({
							resizable: true,
							height: 150,
							width: 300,
							modal: true,
							title: "'.t(59, 'Confirmation').'",
							buttons: {
								"'.t(35, 'Delete').'": function() {
									$(this).dialog("close");
									window.location.href = "?fid=users&act=4&id='.$item->id.'";
								},
								"'.t(58, 'Cancel').'": function() {
									$(this).dialog("close");
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=users&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=users&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(1, 'User').'</label>',
											$naviforms->textfield('user-username', $item->username),
										));						

	$navibars->add_tab_content_row(array(	'<label>'.t(2, 'Password').'</label>',
												'<input type="password" name="user-password" value="" size="32" />',
												'<span class="navigate-form-row-info">'.t(48, "Leave blank to keep the current value").'</span>' ));
											
	$navibars->add_tab_content_row(array(	'<label>'.t(44, 'E-Mail').'</label>',
											'<input type="text" name="user-email" value="'.$item->email.'" size="64" />' ));

				
	// Profile selector
	$DB->query('SELECT id, name FROM nv_profiles');		
	$data = $DB->result();	
	$select = $naviforms->select_from_object_array('user-profile', $data, 'id', 'name', $item->profile);
	$navibars->add_tab_content_row(array(	'<label>'.t(45, 'Profile').'</label>',
											$select ));

	// Language selector
	$DB->query('SELECT code, name FROM nv_languages WHERE nv_dictionary != ""');		
	$data = $DB->result();	
	$select = $naviforms->select_from_object_array('user-language', $data, 'code', 'name', $item->language);
	$navibars->add_tab_content_row(array(	'<label>'.t(46, 'Language').'</label>',
											$select ));
											
	$timezones = property::timezones();
	
	if(empty($item->timezone))
		$item->timezone = date_default_timezone_get();	

	$navibars->add_tab_content_row(array(	'<label>'.t(97, 'Timezone').'</label>',
											$naviforms->selectfield("user-timezone", array_keys($timezones), array_values($timezones), $item->timezone)
										));												
																						
	// Decimal separator		
	$data = array(	0	=> json_decode('{"code": ",", "name": ", ---> 1234,25"}'),
					1	=> json_decode('{"code": ".", "name": ". ---> 1234.25"}'),
					2	=> json_decode('{"code": "\'", "name": "\' ---> 1234\'25"}'),
				);
				
	$select = $naviforms->select_from_object_array('user-decimal_separator', $data, 'code', 'name', $item->decimal_separator);
	$navibars->add_tab_content_row(array(	'<label>'.t(49, 'Decimal separator').'</label>',
											$select ));
											
	// Date format
	$data = array(	0	=> json_decode('{"code": "Y-m-d H:i", "name": "'.date(Y).'-12-31 23:59"}'),
					1	=> json_decode('{"code": "d-m-Y H:i", "name": "31-12-'.date(Y).' 23:59"}'),
					2	=> json_decode('{"code": "m-d-Y H:i", "name": "12-31-'.date(Y).' 23:59"}'),
					3	=> json_decode('{"code": "Y/m/d H:i", "name": "'.date(Y).'/12/31 23:59"}'),
					4	=> json_decode('{"code": "d/m/Y H:i", "name": "31/12/'.date(Y).' 23:59"}'),
					5	=> json_decode('{"code": "m/d/Y H:i", "name": "12/31/'.date(Y).' 23:59"}')
				);	

	$select = $naviforms->select_from_object_array('user-date_format', $data, 'code', 'name', $item->date_format);
	$navibars->add_tab_content_row(array(	'<label>'.t(50, 'Date format').'</label>',
											$select ));

    $navibars->add_tab_content($naviforms->hidden('user-skin', 'cupertino'));
										
	$navibars->add_tab_content_row(array(	'<label>'.t(47, 'Blocked').'</label>',
											$naviforms->checkbox('user-blocked', $item->blocked),
										));

    $navibars->add_tab(t(17, "Permissions"));

    $navibars->add_tab_content($naviforms->hidden('navigate_permissions_changes', ''));

    $navitable = new navitable("permissions_list");

    $navitable->setURL('?fid=users&act=permissions&user='.$item->id);
    $navitable->setDataIndex('name');
    $navitable->enableSearch();
    $navitable->disableSelect();

    $navitable->addCol('id', 'id', "100", "false", "left", false, "true");
    $navitable->addCol(t(159, 'Name'), 'name', "100", "false", "left");
    $navitable->addCol(t(467, 'Scope'), 'scope', "40", "false", "left");
    $navitable->addCol(t(160, 'Type'), 'type', "40", "false", "left");
    $navitable->addCol(t(193, 'Value'), 'value', "100", "false", "left", array(
        'type' => 'custom'
    ));

    $navitable->setLoadCallback('
        $("#permissions_list").find(".buttonset").each(function(i, el)
        {
            $(el).buttonset();
            $(el).css("white-space", "normal");
            $(el).children(".ui-button").css({"float": "left", "height": "24px", "margin": "2px 0px 2px"});
        });
    ');

    $layout->add_script('
        var navigate_permissions_changes = {};

        function navigate_permission_change_boolean(el)
		{
			var code = $(el).attr("for");
			// code is a string like this: navigatecms.privacy_mode_true
            var value = code.substr(code.lastIndexOf("_") + 1);
            code = code.substr(0, code.lastIndexOf("_"));

            navigate_permissions_changes[code] = value;
            navigate_permissions_update();

            /* ajax-save routine [unused]
            $(el).parents(".buttonset").addClass("ui-state-disabled").css("padding", "0px");
            //$(el).parents(".buttonset").buttonset("disable");
            // save new permission value
            $.post(
                "?fid=users&act=permission_set",
                {
                    name: code,
                    value: value,
                    user_id: '.$item->id.'
                },
                function()
                {
                    $(el).parents(".buttonset").removeClass("ui-state-disabled");
                }
            );
            */
		}

		function navigate_permission_change_text(el)
		{
		    var code = $(el).attr("name");
		    var value = $(el).val();

		    navigate_permissions_changes[code] = value;
            navigate_permissions_update();
		}

		function navigate_permissions_update()
		{
            var changes = phpjs_json_encode(navigate_permissions_changes);
            $("#navigate_permissions_changes").val(changes);
		}
	');

    $navibars->add_tab_content($navitable->generate());

    return $navibars->generate();
}
?>