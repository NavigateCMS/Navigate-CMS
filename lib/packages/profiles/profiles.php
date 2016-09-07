<?php
require_once(NAVIGATE_PATH.'/lib/packages/menus/menu.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/profiles/profile.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/permissions/permissions.functions.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	
	$out = '';
	$item = new profile();
			
	switch($_REQUEST['act'])
	{
        case 'json':
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

        case 'edit':
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
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = profiles_form($item);
			break;

        case 'delete':
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

        case 'list':
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

	$navibars->add_actions(
		array(
			'<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
		)
	);
	
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
	global $layout;
    global $current_version;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item->id))
		$navibars->title(t(243, 'Profiles').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(243, 'Profiles').' / '.t(170, 'Edit').' ['.$item->id.']');		

	if(empty($item->id))
	{
		$navibars->add_actions(
			array(
				'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'
			)
		);
	}
	else
	{
		$navibars->add_actions(
			array(
				'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
				'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
			)
		);

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=profiles&act=delete&id='.$item->id.'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}
	
	$navibars->add_actions(
		array(
			(!empty($item->id)? '<a href="?fid=profiles&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=profiles&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
		)
	);

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(
		array(
			'<label>ID</label>',
			'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
		)
	);

	$navibars->add_tab_content_row(
		array(
			'<label>'.t(159, 'Name').'</label>',
			$naviforms->textfield('name', $item->name),
		)
	);

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
    if(is_array($menus))
    {
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
    }
	
	$sortable_profile[] = '</ul>';
	$sortable_unassigned[] = '</ul>';

	//$navibars->add_tab_content('<pre>'.print_r($item->menus, true).'</pre>');
	$navibars->add_tab_content($naviforms->hidden("profile-menu", implode('#', $item->menus)));	
	$navibars->add_tab_content_row(
		array(
			'<label>'.t(244, 'Menus').'</label>',
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

	$websites = website::all();
	$navibars->add_tab(t(17, "Permissions"));

    $navibars->add_tab_content($naviforms->hidden('navigate_permissions_changes', ''));

	$ws_tabs = '<div id="navigate-permissions-websites-tabs"><ul>';

	foreach($websites as $ws_id => $ws_name)
	{
		$ws_tabs .= '<li><a href="#navigate-permissions-websites-tab-'.$ws_id.'">'.$ws_name.'</a></li>';
	}

	$ws_tabs.= '</ul>';

	foreach($websites as $ws_id => $ws_name)
	{
		$ws_tabs .= '<div id="navigate-permissions-websites-tab-'.$ws_id.'" data-website="'.$ws_id.'">';

		$navitable = new navitable("permissions_list_website_".$ws_id);

		$navitable->setURL('?fid=permissions&act=list&website='.$ws_id.'&object=profile&object_id='.$item->id);
	    $navitable->setDataIndex('name');
	    $navitable->disableSelect();
		$navitable->disableStatusBar();

	    $navitable->addCol('id', 'id', "100", "false", "left", false, "true");
	    $navitable->addCol(t(159, 'Name'), 'name', "100", "false", "left");
	    $navitable->addCol(t(467, 'Scope'), 'scope', "40", "false", "left");
	    $navitable->addCol(t(160, 'Type'), 'type', "40", "false", "left");
	    $navitable->addCol(t(193, 'Value'), 'value', "100", "false", "left", array('type' => 'custom'));

	    $navitable->setLoadCallback("navigate_permissions_list_callback(this);");

	    $ws_tabs .= $navitable->generate();

		$ws_tabs .= '</div>';

		$layout->add_script('
			$("#permissions_list_website_'.$ws_id.'").data("website", '.$ws_id.');
		');

		$navibars->add_content(navigate_permissions_structure_selector($ws_id, $ws_name));
	}

	$ws_tabs.= '</div>';

	$navibars->add_tab_content($ws_tabs);

	$layout->add_script('
		$("#navigate-permissions-websites-tabs").tabs({
			heightStyle: "fill",
			activate: function() {
				$(window).trigger("resize");
			}
		});
	');

	$layout->add_script('
		$.getScript("lib/packages/permissions/permissions.js?r='.$current_version->revision.'", function()
		{
			navigate_window_resize();
		});
	');

    return $navibars->generate();
}

?>