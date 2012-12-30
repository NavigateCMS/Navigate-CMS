<?php
require_once(NAVIGATE_PATH.'/lib/packages/menus/menu.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/profiles/profile.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	
	$out = '';
	$item = new profile();
			
	switch($_REQUEST['act'])
	{
		case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
					foreach($ids as $id)
					{
						$item->load($id);
						$item->delete();
					}
					echo json_encode(true);
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
				
					$DB->queryLimit('id,name', 
									'nv_profiles', 
									$where, 
									$orderby, 
									$offset, 
									$max);
									
					$dataset = $DB->result();
					$total = $DB->foundRows();
					
					//echo $DB->get_last_error();
					
					$out = array();					
											
					for($i=0; $i < count($dataset); $i++)
					{													
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $dataset[$i]['name']
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
                    permission::update_permissions(json_decode($_REQUEST['navigate_permissions_changes'], true), $item->id, 0);
					$layout->navigate_notification(t(53, "Data saved successfully."), false);	
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = profiles_form($item);
			break;
					
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = profiles_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = profiles_form($item);
				}
			}
			break;

        case 'permissions':
            $page   = intval($_REQUEST['page']);
            $max	= intval($_REQUEST['rows']);
            $profile_id = intval($_REQUEST['profile']);
            $offset = ($page - 1) * $max;

            $naviforms = new naviforms();

            $prf_obj = new profile();
            $prf_obj->load($profile_id);

            $permissions_definitions = permission::get_definitions();

            $permissions_values = permission::get_values('profile', $prf_obj, $permissions_definitions);

            $permissions_definitions = array_merge(
                $permissions_definitions['system'],
                $permissions_definitions['functions'],
                $permissions_definitions['extensions']);

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
					
		case 0: // list / search result
		default:			
			$out = profiles_list();
			break;
	}
	
	return $out;
}

function profiles_list()
{
	$navibars = new navibars();
	$navitable = new navitable("profiles_list");
	
	$navibars->title(t(243, 'Profiles'));

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=2&id=');
	
	$navitable->addCol("ID", 'id', "80", "true", "left");	
	$navitable->addCol(t(159, 'Name'), 'name', "400", "true", "left");	
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function profiles_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item->id))
		$navibars->title(t(243, 'Profiles').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(243, 'Profiles').' / '.t(170, 'Edit').' ['.$item->id.']');		

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
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=4&id='.$item->id.'";
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
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=profiles&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=profiles&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(159, 'Name').'</label>',
											$naviforms->textfield('name', $item->name),
										));

    $navibars->add_tab_content_row(array(	'<label>'.t(334, 'Description').'</label>',
        $naviforms->textarea('description', $item->description),
    ));

    $menus = menu::load_all_menus();

	$sortable_profile = array();
	$sortable_unassigned = array();
	
	$sortable_profile[] = '<ul id="sortable_profile" class="connectedSortable">';
	$sortable_unassigned[] = '<ul id="sortable_unassigned" class="connectedSortable">';	
	
	// already included menus on the profile
	foreach($item->menus as $m)
	{
		foreach($menus as $menu)
		{		
			if($menu->id == $m)
			{
				if($menu->enabled=='1')
					$sortable_profile[] = '<li class="ui-state-highlight" value="'.$menu->id.'" title="'.$menu->notes.'"><img src="'.NAVIGATE_URL.'/'.$menu->icon.'" align="absmiddle" /> '.t($menu->lid, $menu->lid).'</li>';
				else
					$sortable_profile[] = '<li class="ui-state-highlight ui-state-disabled" value="'.$menu->id.'" title="'.$menu->notes.'"><img src="'.NAVIGATE_URL.'/'.$menu->icon.'" align="absmiddle" /> '.t($menu->lid, $menu->lid).'</li>';
			}			
		}
	}
	
	// the other menus not included on the profile
	foreach($menus as $menu)
	{
		if(!in_array($menu->id, $item->menus))
		{
			if($menu->enabled=='1')
				$sortable_unassigned[] = '<li class="ui-state-default" value="'.$menu->id.'" title="'.$menu->notes.'"><img src="'.NAVIGATE_URL.'/'.$menu->icon.'" align="absmiddle" /> '.t($menu->lid, $menu->lid).'</li>';
			else
				$sortable_unassigned[] = '<li class="ui-state-default ui-state-disabled" value="'.$menu->id.'" title="'.$menu->notes.'"><img src="'.NAVIGATE_URL.'/'.$menu->icon.'" align="absmiddle" /> '.t($menu->lid, $menu->lid).'</li>';
		}
	}
	
	$sortable_profile[] = '</ul>';
	$sortable_unassigned[] = '</ul>';

	//$navibars->add_tab_content('<pre>'.print_r($item->menus, true).'</pre>');
	$navibars->add_tab_content($naviforms->hidden("profile-menu", implode('#', $item->menus)));	
	$navibars->add_tab_content_row(array(	'<label>'.t(244, 'Menus').'</label>',
											implode("\n", $sortable_profile),
											implode("\n", $sortable_unassigned)
										)
									);

    $layout->add_script('
		$("#sortable_profile").sortable({
				connectWith: ".connectedSortable",
				receive: function(event, ui)
				{
					$(ui.item).addClass("ui-state-highlight");
					$(ui.item).removeClass("ui-state-default");
				},
				update: function()
				{
					$("#profile-menu").val("");
					$("#sortable_profile li").each(function()
					{
						$("#profile-menu").val($("#profile-menu").val() + $(this).attr("value") + "#");					
					});
				}
			}).disableSelection();
			
		$("#sortable_unassigned").sortable({
				connectWith: ".connectedSortable",
				receive: function(event, ui)
				{
					$(ui.item).addClass("ui-state-default");
					$(ui.item).removeClass("ui-state-highlight");					
				}
			}).disableSelection();			
			
	');

    $navibars->add_tab(t(17, "Permissions"));

    $navibars->add_tab_content($naviforms->hidden('navigate_permissions_changes', ''));

    $navitable = new navitable("permissions_list");

    $navitable->setURL('?fid=profiles&act=permissions&profile='.$item->id);
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