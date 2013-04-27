<?php
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/themes/theme.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
		
	$out = '';
	$item = new structure();
			
	switch($_REQUEST['act'])
	{
		/*
		case 1:	// json data retrieval & operations
			echo '  [
						{ "data" : "A node", 
						  "children" : 
						  [ 
						  	{ "data" : "Only child", 
							  "state" : "closed",
							  "attr" : { "id" : "5" }
							} 
						  ], 
						  "state" : "open" },
						"Ajax node"
					]	';
			
			session_write_close();
			exit;
			break;
		*/
		
		case 'load':
        case 'edit':
		case 2: // edit/new form		
			if(!empty($_REQUEST['id']))	
				$item->load(intval($_REQUEST['id']));	
																
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
					$item->save();
					property::save_properties_from_post('structure', $item->id);
					$item = $item->reload();

                    // reorder associated category elements
                    if(!empty($_POST['elements-order']))
                    {
                        $response = item::reorder($_POST['elements-order']);
                        if($response!==true)
                            throw new Exception($response);
                    }

					$layout->navigate_notification(t(53, "Data saved successfully."), false);	
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
				
				users_log::action($_REQUEST['fid'], $item->id, 'save', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
			}
			else
				users_log::action($_REQUEST['fid'], $item->id, 'load', $item->dictionary[$website->languages_list[0]]['title']);
		
			$out = structure_form($item);
			break;	
			
		case 3: // reorder
			$ok = structure::reorder($_REQUEST['parent'], $_REQUEST['children_order']);
			echo json_encode($ok);
			core_terminate();
			break;
			
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$structure = structure::hierarchy(-1); // root level (0) including Web node (-1)
					$out = structure_tree($structure);
                    users_log::action($_REQUEST['fid'], $item->id, 'remove');
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = structure_form($item);
				}
			}
			break;
			
		case 95: // free path checking
			
			$path = $_REQUEST['path'];
			$id = $_REQUEST['id'];
			
			$DB->query('SELECT type, object_id, lang
	 					  FROM nv_paths
						 WHERE path = '.protect($path).'
						   AND website = '.$website->id);
						 
			$rs = $DB->result();
			
			echo json_encode($rs);
			core_terminate();		
			break;			
		
		case "category_path": // return category paths
			echo json_encode(path::loadElementPaths('structure', intval($_REQUEST['id'])));
			core_terminate();		
			break;
			
		case 'json_find_item':
			$DB->query('SELECT nvw.node_id as id, nvw.text as label, nvw.text as value
						  FROM nv_webdictionary nvw, nv_items nvi
						 WHERE nvw.node_type = "item"
						   AND nvw.node_id = nvi.id
						   AND nvw.subtype = "title"
						   AND (	nvi.association = "free" OR 
						   			(nvi.association = "category" AND nvi.embedding=0)
						   )
						   AND nvw.lang = '.protect($_REQUEST['lang']).'
						   AND nvw.website = '.$website->id.' 
						   AND nvw.website = nvi.website 
						   AND nvw.text LIKE '.protect('%'.$_REQUEST['title'].'%').'
				      ORDER BY nvw.text ASC
					     LIMIT 30',
						'array');
						
			echo json_encode($DB->result());
			core_terminate();					
			break;
			
		case 'votes_reset':
			webuser_vote::remove_object_votes('structure', intval($_REQUEST['id']));
			echo 'true';
			core_terminate();
			break;

		case 'votes_by_webuser':
			if($_POST['oper']=='del')
			{
				$ids = explode(',', $_POST['id']);
				
				for($i=0; $i < count($ids); $i++)
				{
					if($ids[$i] > 0)	
					{
						$vote = new webuser_vote();
						$vote->load($ids[$i]);
						$vote->delete();	
					}
				}
				
				webuser_vote::update_object_score('structure', $vote->object_id);
					
				echo 'true';
				core_terminate();	
			}
		
			$max = intval($_GET['rows']);
			$page = intval($_GET['page']);
			$offset = ($page - 1) * $max;	
		
			if($_REQUEST['_search']=='false')
				list($dataset, $total) = webuser_vote::object_votes_by_webuser('structure', intval($_REQUEST['id']), $_REQUEST['sidx'].' '.$_REQUEST['sord'], $offset, $max);
		
			$out = array();								
			for($i=0; $i < count($dataset); $i++)
			{
				if(empty($dataset[$i])) continue;
														
				$out[$i] = array(
					0	=> $dataset[$i]['id'],
					1 	=> core_ts2date($dataset[$i]['date'], true),
					2	=> $dataset[$i]['username']
				);
			}

			navitable::jqgridJson($out, $page, $offset, $max, $total);
			core_terminate();
			break;

		
		case 0: // tree / search result
		default:			
			$structure = structure::hierarchy(-1); // root level (0) including Web node (-1)
			$out = structure_tree($structure);
			break;
	}
	
	return $out;
}

function structure_tree($hierarchy)
{
	global $layout;
	global $website;
		
	$navibars = new navibars();
	$navitree = new navitree("structure-".$website->id);
	
	$navibars->title(t(16, 'Structure'));

	$navibars->add_actions(	array(	'<a href="#" onclick="javascript: navigate_structure_expand(); "><img height="16" align="absmiddle" width="16" src="img/icons/silk/arrow_out.png"> '.t(295, 'Expand all').'</a>'	));

	$navibars->add_actions(	array(	'<a href="?fid=structure&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'search_form' ));
	
	$navitree->setURL('?fid=structure&act=edit&id=');
	$navitree->addURL('?fid=structure&act=edit&parent=');
	$navitree->orderURL('?fid=structure&act=3');

	$access = array(		0 => '<img src="img/icons/silk/page_white_go.png" align="absmiddle" title="'.t(254, 'Everybody').'" />',
							1 => '<img src="img/icons/silk/lock.png" align="absmiddle" title="'.t(361, 'Web users only').'" />',
							2 => '<img src="img/icons/silk/user_gray.png" align="absmiddle" title="'.t(363, 'Users who have not yet signed up or signed in').'" />',
                            3 => '<img src="img/icons/silk/group_key.png" align="absmiddle" title="'.t(512, "Selected web user groups").'" />'
	);	
	
	$permissions = array(	0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(69, 'Published'),
							1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(70, 'Private'),
							2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(81, 'Hidden')
	);

    /* LANGUAGE SELECTOR */

    $lang_selector[] = '<ul id="structure-language-selector">';

    foreach($website->languages_list as $lang)
    {
        $lang_selector[] = '<li><a href="#" language="'.$lang.'">'.language::name_by_code($lang).'</a></li>';
    }

    $lang_selector[] = '</ul>';
    $lang_selector = '&nbsp;<span id="structure-language-selector-icon" style="cursor: pointer;">'.
                        '<img src="img/icons/silk/comment.png" align="absmiddle" />&#9662;'.
                        '</span>'.
                        implode("\n", $lang_selector);

    $layout->add_script('
        $("#structure-language-selector").menu();

        $("#structure-language-selector").css({
            "position": "absolute",
            "top": $("#structure-language-selector-icon").offset().top + 16,
            "left": $("#structure-language-selector-icon").offset().left,
            "z-index": 1000
        });
        $("#structure-language-selector").addClass("navi-ui-widget-shadow");

        $("#structure-language-selector a").each(function(i, el)
        {
            $(el).on("click", function()
            {
                var lang = $(this).attr("language");
                $(".structure-label").hide();
                $(".structure-label[lang="+lang+"]").show();
            });
        });

        $("#structure-language-selector").hide();

        $("#structure-language-selector-icon").on("click", function()
        {
            $("#structure-language-selector").show();
        });
    ');

	$columns = array();
	$columns[] = array(	'name'	=>	'ID',				'property'	=> 'id', 		'type'	=> 'text', 		'width' => '8%', 	'align' => 'left' );
	$columns[] = array(	'name'	=>	t(67, 'Title').' '.$lang_selector,		'property'	=> 'label',		'type'	=> 'text', 		'width' => '56%', 	'align' => 'left'	);
	$columns[] = array(	'name'	=>	t(73, 'Children'),	'property'	=> 'children', 	'type'	=> 'count', 	'width' => '5%', 	'align' => 'center'	);
	$columns[] = array(	'name'	=>	t(85, 'Date published'), 'property'	=> 'dates', 'type'	=> 'text', 		'width' => '13%', 	'align' => 'center'	);			
	$columns[] = array(	'name'	=>	'<span title="'.t(283, 'Show in menus').'">'.t(76, 'Visible').'</span>', 'property'	=> 'visible', 'type'	=> 'boolean',	'width' => '5%', 	'align' => 'center'	);			
	$columns[] = array(	'name'	=>	t(364, 'Access'), 'property'	=> 'access',	'type'	=> 'option', 	'width' => '5%', 	'align' => 'center', 	'options' => $access);
	$columns[] = array(	'name'	=>	t(17, 'Permission'), 'property'	=> 'permission',	'type'	=> 'option', 	'width' => '8%', 	'align' => 'center', 	'options' => $permissions);
	
	$navitree->setColumns($columns);
		
	if(!empty($_REQUEST['navigate-quicksearch']))
	{
		$navitree->setState('expanded');
		$layout->add_script('
			$("#structure").find("tr").each(function()
			{	
				if($(this).find("th").length > 0) return;
				
				var find_string = "'.$_REQUEST['navigate-quicksearch'].'";
				find_string = find_string.toLowerCase();
				
				var td_string = $(this).find("td").eq(1).html().toLowerCase();
				
				if(td_string.indexOf(find_string) < 0)
				{
					navigate_structure_hide_table_row(this);
				}
			});
			
			function navigate_structure_hide_table_row(el)
			{
				setTimeout(function()
				{
					$(el).css("display", "none");	
				}, 100);
			}
		');
	}

	$navitree->setData($hierarchy);
	
	$navitree->setTreeColumn(1);
	
	$navibars->add_content('<div id="navigate-content-safe" class="ui-corner-all">'.$navitree->generate().'</div>');	
	
	$layout->add_script('
		function navigate_structure_expand()
		{
			var parents = 0;
			
			while(parents < $(".parent").length)
			{
				parents = $(".parent").length;
				$(".parent").expand();
			}
		}	
	');

	return $navibars->generate();
	
}

function structure_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	$layout->navigate_media_browser();	// we can use media browser in this function
	
	$navibars->title(t(16, 'Structure'));

	$navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));

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
								"'.t(58, 'Cancel').'": function() {
									$(this).dialog("close");
								},
								"'.t(35, 'Delete').'": function() {
									$(this).dialog("close");
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=4&id='.$item->id.'";
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=structure&act=edit&parent='.$item->parent.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=structure&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/sitemap_color.png"> '.t(61, 'Tree').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

	if(empty($item->id))
		$item->parent = $_GET['parent'];

	$navibars->add_tab_content($naviforms->hidden('id', $item->id));
	//$navibars->add_tab_content($naviforms->hidden('parent', $item->parent));

	$hierarchy = structure::hierarchy(0);
	$categories_list = structure::hierarchyList($hierarchy, $item->parent);

    if(empty($categories_list))
        $categories_list = '<ul><li value="0">'.t(428, '(no category)').'</li></ul>';

	$navibars->add_tab_content_row(array(	'<label>'.t(84, 'Parent').'</label>',
                                            $naviforms->dropdown_tree('parent', $categories_list, $item->parent, 'navigate_parent_category_change')
										),
									'category_tree');

    $layout->add_script('
        function navigate_parent_category_change(id)
        {
            $.ajax(
            {
                url: NAVIGATE_APP + "?fid=structure&act=category_path&id=" + id,
                dataType: "json",
                data: {},
                success: function(data, textStatus, xhr)
                {
                    item_category_path = data;
                }
            });
        }
    ');

    /*
	$navibars->add_tab_content_row(array(	'<label>'.t(84, 'Parent').'</label>',
											'<div class="category_tree" id="category-tree-parent"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.$categories_list.'</div>'
										));		
										
	$layout->add_script('$("#category-tree-parent ul:first").kvaTree({
	        imgFolder: "js/kvatree/img/",
			dragdrop: false,
			background: "#f2f5f7",
			onClick: function(event, node)
			{
				$("input[name=\"parent\"]").val($(node).attr("value"));	
				$.ajax({
				  url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=category_path&id=" + $(node).attr("value"),
				  dataType: "json",
				  data: {},
				  success: function(data, textStatus, xhr)
				  {
					  item_category_path = data;
				  }
				});
			}
		});');
    */

	$templates = template::elements();
	$template_select = $naviforms->select_from_object_array('template', $templates, 'id', 'title', $item->template);
										                    
	$navibars->add_tab_content_row(array(	'<label>'.t(79, 'Template').'</label>',
											$template_select,
										));		
										
	$navibars->add_tab_content_row(array(	'<label>'.t(85, 'Date published').'</label>',
											$naviforms->datefield('date_published', $item->date_published, true),
										));		
										
	$navibars->add_tab_content_row(array(	'<label>'.t(90, 'Date unpublished').'</label>',
											$naviforms->datefield('date_unpublish', $item->date_unpublish, true),
										));																	
																							

	$navibars->add_tab_content_row(array(	'<label>'.t(364, 'Access').'</label>',
											$naviforms->selectfield('access', 
												array(
														0 => 0,
														1 => 2,
														2 => 1,
														3 => 3
													),
												array(
														0 => t(254, 'Everybody'),
                                                        1 => t(362, 'Not signed in'),
                                                        2 => t(361, 'Web users only'),
														3 => t(512, 'Selected web user groups')
													),
												$item->access,
												'navigate_webuser_groups_visibility($(this).val());',
												false,
												array(
														1 => t(363, 'Users who have not yet signed in')
												)
											)
										)
									);

    $webuser_groups = webuser_group::all_in_array();

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(506, "Groups").'</label>',
            $naviforms->multiselect(
                'groups',
                array_keys($webuser_groups),
                array_values($webuser_groups),
                $item->groups
            )
        ),
        'webuser-groups-field'
    );

    $layout->add_script('
        function navigate_webuser_groups_visibility(access_value)
        {
            if(access_value==3)
                $("#webuser-groups-field").show();
            else
                $("#webuser-groups-field").hide();
        }

        navigate_webuser_groups_visibility('.$item->access.');
    ');

																				
	$navibars->add_tab_content_row(array(	'<label>'.t(68, 'Status').'</label>',
											$naviforms->selectfield('permission', 
												array(
														0 => 0,
														1 => 1,
														2 => 2
													),
												array(
														0 => t(69, 'Published'),
														1 => t(70, 'Private'),
														2 => t(81, 'Hidden')
													),
												$item->permission,
												'',
												false,
												array(
														0 => t(360, 'Visible to everybody'),
														1 => t(359, 'Visible only to Navigate CMS users'),
														2 => t(358, 'Hidden to everybody')												
												)
											)
										)
									);	
									
	$navibars->add_tab_content_row(array(	'<label>'.t(283, 'Shown in menus').'</label>',
											$naviforms->checkbox('visible', $item->visible)
										));	
									
											
	$navibars->add_tab(t(54, "Text").' / '.t(74, "Paths"));

	$lang_selector = array();
	$lang_selector[] = '<div class="buttonset">';
	$checked = ' checked="checked" ';	

	foreach($website->languages_list as $lang_code)
	{	
		$lang_selector[] = '<input type="radio" id="language_selector_'.$lang_code.'" name="language_selector" value="'.$lang_code.'" '.$checked.' />
							<label for="language_selector_'.$lang_code.'"  onclick="navigate_structure_select_language(\''.$lang_code.'\');">'.language::name_by_code($lang_code).'</label>';
		$checked = "";
	}
	$lang_selector[] = '</div>';
	
	$navibars->add_tab_content_row(array(	'<label>'.t(63, 'Languages').'</label>',
											implode("\n", $lang_selector)
										));	
											
	foreach($website->languages_list as $lang_code)
	{		
		$navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang_code.'" style=" display: none; ">');
		
		$navibars->add_tab_content_row(array(	'<label>'.t(67, 'Title').'</label>',
												$naviforms->textfield('title-'.$lang_code, @$item->dictionary[$lang_code]['title'])
											));		
											
		$open_live_site = '';												
		if(!empty($item->paths[$lang_code]))
			$open_live_site = ' <a target="_blank" href="'.$website->absolute_path(true).$item->paths[$lang_code].'"><img src="img/icons/silk/world_go.png" align="absmiddle" /></a>';
											
											
		$navibars->add_tab_content_row(array(	'<label>'.t(75, 'Path').$open_live_site.'</label>',
												$naviforms->textfield('path-'.$lang_code, @$item->paths[$lang_code], NULL, 'navigate_structure_path_check(this);'),
												'<span>&nbsp;</span>'
											));	
		/*									
		$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
												'<div class="subcomment"><sup>*</sup> '.t(83, 'Leave blank to disable this item').'</div>',
											));		
		*/
											
		$navibars->add_tab_content_row(array(	'<label>'.t(172, 'Action').'</label>',
												$naviforms->selectfield('action-type-'.$lang_code, 
													array(
															0 => 'url',
															1 => 'jump-branch',
															2 => 'jump-item',
															3 => 'do-nothing'
														),
													array(
															0 => t(173, 'Open URL'),
															1 => t(322, 'Jump to another branch'),
															2 => t(323, 'Jump to an element'),
															3 => t(183, 'Do nothing')
														),
													$item->dictionary[$lang_code]['action-type'],
													"navigate_structure_action_change('".$lang_code."', this);"
												)
											)
										);

		// load item title if action was "jump to an element"				
		if(!empty($item->dictionary[$lang_code]['action-jump-item']))
		{
			$tmp = new Item();
			$tmp->load($item->dictionary[$lang_code]['action-jump-item']);
			$item_title = $tmp->dictionary[$lang_code]['title'];
		}
										
		$navibars->add_tab_content_row(array(	'<label>'.t(180, 'Item').' ['.t(67, 'Title').']</label>',
												$naviforms->textfield('action-jump-item_title-'.$lang_code, $item_title),
												$naviforms->hidden('action-jump-item-'.$lang_code, $item->dictionary[$lang_code]['action-jump-item'])
										));									

		$categories_list = structure::hierarchyList($hierarchy, $item->dictionary[$lang_code]['action-jump-branch']);
	
		$navibars->add_tab_content_row(array(	'<label>'.t(325, 'Branch').'</label>',
												'<div class="category_tree" id="category_tree_jump_branch_'.$lang_code.'"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.$categories_list.'</div>',
												$naviforms->hidden('action-jump-branch-'.$lang_code, $item->dictionary[$lang_code]['action-jump-branch'])
											));		
										
		$navibars->add_tab_content_row(array(	'<label>'.t(324, 'New window').'</label>',
												$naviforms->checkbox('action-new-window-'.$lang_code, $item->dictionary[$lang_code]['action-new-window'])
										));												
										
		$navibars->add_tab_content('</div>');		
	}
	
	$parent = new structure();
	$parent->paths = array();
	if(!empty($item->parent))
		$parent->load($item->parent);
	
	$layout->add_script('
		function navigate_structure_select_language(code)
		{
			$(".language_fields").css("display", "none");
			$("#language_fields_" + code).css("display", "block");			
		}
		
		var active_languages = ["'.implode('", "', $website->languages_list).'"];
		var last_check = [];
		var item_category_path = '.json_encode($parent->paths).';
		
		function navigate_structure_path_generate(el)
		{
			var language = $(el).attr("id").substr(5);
			var surl = "";
			if(item_category_path[language] && item_category_path[language]!="")
				surl = item_category_path[language];
			else
				surl = "/" + language;
			var title = $("#title-"+language).val();
			title = title.replace(/[.\s]+/g, "_");
			title = title.replace(/([\'"?:!¿#\\\\])/g, "");
			surl += "/" + title;
			$(el).val(surl.toLowerCase());
			navigate_structure_path_check(el);
		}		
		
		function navigate_structure_path_check(el)
		{
			var path = $(el).val();
			
			if(path=="") return;			
			if(path==last_check[$(el).id]) return;

			path = path.replace(/&/, "");
			$(el).val(path);
			
			last_check[$(el).id] = path;
			
			$(el).next().html("<img src=\"'.NAVIGATE_URL.'/img/loader.gif\" align=\"absmiddle\" />");
			
			$.ajax({
			  url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=95",
			  dataType: "json",
			  data: "id='.$item->id.'&path=" + $(el).val(),
			  type: "get",
			  success: function(data, textStatus)
			  {
				  var free = true;

				  if(data && data.length==1)
				  {
					 // same element?
					 if( data[0].object_id != "'.$item->id.'" ||
						 data[0].type != "structure" )
					 {
						free = false; 
					 }
				  }
				  else if(data && data.length > 1)
				  {
					  free = false;
				  }
				  
				  if(free)	free = "<img src=\"'.NAVIGATE_URL.'/img/icons/silk/tick.png\" align=\"absmiddle\" />";
				  else		free = "<img src=\"'.NAVIGATE_URL.'/img/icons/silk/cancel.png\" align=\"absmiddle\" />";

				  $(el).next().html(free);
			  }
			});
				
		}
				
		function navigate_structure_action_change(language, element)
		{			
			$("#action-new-window-" + language).parent().hide();
			$("#action-jump-item-" + language).parent().hide();
			$("#action-jump-branch-" + language).parent().hide();			
			
			switch(jQuery(element).val())
			{
				case "do-nothing":
				
					break;
					
				case "jump-branch":
					$("#action-new-window-" + language).parent().show();
					$("#action-jump-branch-" + language).parent().show();
					$("#category_tree_jump_branch_" + language+ " ul:first").kvaTree({
									imgFolder: "js/kvatree/img/",
									dragdrop: false,
									background: "#f2f5f7",
									onClick: function(event, node)
									{
										$("#action-jump-branch-" + language).val($(node).attr("value"));	
									}
								});					
					break;
					
				case "jump-item":
					$("#action-new-window-" + language).parent().show();
					$("#action-jump-item-" + language).parent().show();
					break;
					
				case "url":
					$("#action-new-window-" + language).parent().show();
					break;
			}
	
		}
		
		$(window).bind("load", function()
		{
			for(al in active_languages)
			{
				navigate_structure_path_check($("#path-" + active_languages[al]));
				
				$("#path-" + active_languages[al]).bind("focus", function()
				{
					if($(this).val() == "")
						navigate_structure_path_generate($(this));
				});

				$("#action-jump-item_title-" + active_languages[al]).autocomplete(
				{
					source: function(request, response)
					{
						var toFind = {	
							"title": request.term,
							"lang": $("input[name=\"language_selector\"]:checked").val(),
							nd: new Date().getTime()
						};
						
						$.ajax(
							{
								url: NAVIGATE_APP + "?fid=" + navigate_query_parameter(\'fid\') + "&act=json_find_item",
								dataType: "json",
								method: "GET",
								data: toFind,
								success: function( data ) 
								{
									response( data );
								}
							});
					},
					minLength: 1,
					select: function(event, ui) 
					{
						$("#action-jump-item-" + $("input[name=\"language_selector\"]:checked").val()).val(ui.item.id);
					}
				});	
				
				navigate_structure_action_change(active_languages[al], $("#action-type-" + active_languages[al]));
	
			}

		});
				
	');
	
	$layout->add_script('navigate_structure_select_language("'.$website->languages_list[0].'")');	

	if(!empty($item->template))
	{
		$properties_html = navigate_property_layout_form('structure', $item->template, 'structure', $item->id);

		if(!empty($properties_html))
		{
			$navibars->add_tab(t(77, "Properties"));
			$navibars->add_tab_content($properties_html);
		}
	}
		
	if($item->votes > 0)
	{
		$navibars->add_tab(t(352, "Votes"));
		
		$score = $item->score / $item->votes;			
		
		$navibars->add_tab_content_panel('<img src="img/icons/silk/chart_pie.png" align="absmiddle" /> '.t(337, 'Summary'), 
										 array(	'<div class="navigate-panels-summary ui-corner-all"><h2>'.$item->votes.'</h2><br />'.t(352, 'Votes').'</div>',
												'<div class="navigate-panels-summary ui-corner-all""><h2>'.$score.'</h2><br />'.t(353, 'Score').'</div>',
												'<div style=" float: left; margin-left: 8px; "><a href="#" class="uibutton" id="items_votes_webuser">'.t(15, 'Users').'</a></div>',
												'<div style=" float: right; margin-right: 8px; "><a href="#" class="uibutton" id="items_votes_reset">'.t(354, 'Reset').'</a></div>',
												'<div id="items_votes_webuser_window" style=" display: none; width: 600px; height: 350px; "></div>'
										 ), 
										 'navigate-panel-web-summary', '385px', '200px');	
											
										 
		$layout->add_script('
			$("#items_votes_reset").bind("click", function()
			{
				$.post("?fid='.$_REQUEST['fid'].'&act=votes_reset&id='.$item->id.'", function(data)
				{
					$("#navigate-panel-web-summary").addClass("ui-state-disabled");
					navigate_notification("'.t(355, 'Votes reset').'");
				});
			});
			
			$("#items_votes_webuser").bind("click", function()
			{
				$( "#items_votes_webuser_window" ).dialog(
				{
					title: "'.t(357, 'User votes').'",
					width: 700,
					height: 400,
					modal: true,
					open: function()
					{
						$( "#items_votes_webuser_window" ).html("<table id=\"items_votes_webuser_grid\"></table>");
						$( "#items_votes_webuser_window" ).append("<div id=\"items_votes_webuser_grid_pager\"></div>");
						
						jQuery("#items_votes_webuser_grid").jqGrid(
						{
						  url: "?fid='.$_REQUEST['fid'].'&act=votes_by_webuser&id='.$item->id.'",
						  editurl: "?fid='.$_REQUEST['fid'].'&act=votes_by_webuser&id='.$item->id.'",
						  datatype: "json",
						  mtype: "GET",
						  pager: "#items_votes_webuser_grid_pager",	
						  colNames:["ID", "'.t(86, 'Date').'", "'.t(1, 'Username').'"],
						  colModel:[
							{name:"id", index:"id", width: 75, align: "left", sortable:true, editable:false, hidden: true},
							{name:"date",index:"date", width: 180, align: "center", sortable:true, editable:false},
							{name:"username", index:"username", align: "left", width: 380, sortable:true, editable:false}
							
						  ],
						  scroll: 1,
						  loadonce: false,
						  autowidth: true,
						  forceFit: true,
						  rowNum: 12,
						  rowList: [12],	
						  viewrecords: true,
						  multiselect: true,		  
						  sortname: "date",
						  sortorder: "desc"
						});	
						
						$("#items_votes_webuser_grid").jqGrid(	"navGrid", 
																"#items_votes_webuser_grid_pager", 
																{
																	add: false,
																	edit: false,
																	del: true,
																	search: false
																}
															);
					}
				});
			});				
		');
		
		
		$navibars->add_tab_content_panel('<img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(353, 'Score'), 
										 array(	'<div id="navigate-panel-web-score-graph" style=" height: 171px; width: 385px; "></div>' ), 
										 'navigate-panel-web-score', '385px', '200px');	
																					 
		$votes_by_score = webuser_vote::object_votes_by_score('structure', $item->id);
		
		$gdata = array();
		
		$colors = array(
			'#0a2f42',				
			'#62bbe8',
			'#1d8ec7',
			'#44aee4',
			'#bbe1f5'
		);
		
		foreach($votes_by_score as $vscore)
		{
			$gdata[] = (object) array(
				'label' => $vscore->value, 
				'data' => (int)$vscore->votes,
				'color' => $colors[($vscore->value % count($colors))]
			);
		}		
												
		$layout->add_script('
			$(document).ready(function()
			{		
				var gdata = '.json_encode($gdata).';				
			
				$.plot($("#navigate-panel-web-score-graph"), gdata,
				{						
						series: 
						{
							pie: 
							{
								show: true,
								radius: 1,
								tilt: 0.5,
								startAngle: 3/4,
								label: 
								{
									show: true,
									formatter: function(label, series)
									{
										return \'<div style="font-size:12px;text-align:center;padding:2px;color:#fff;"><span style="font-size: 20px; font-weight: bold; ">\'+label+\'</span><br/>\'+Math.round(series.percent)+\'% (\'+series.data[0][1]+\')</div>\';
									},
									background: { opacity: 0.6 }
								},
								stroke: 
								{
									color: "#F2F5F7",
									width: 4
								},
							}
						},
						legend: 
						{
							show: false
						}
				});
		');		
		
		$navibars->add_tab_content_panel('<img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(352, 'Votes').' ('.t(356, 'last 90 days').')', 
										 array(	'<div id="navigate-panel-web-votes-graph" style=" height: 171px; width: 385px; "></div>' ), 
										 'navigate-panel-web-votes', '385px', '200px');												 

		$votes_by_date = webuser_vote::object_votes_by_date('structure', $item->id, 90);

		
		$layout->add_script('
								
				var plot = $.plot(
					$("#navigate-panel-web-votes-graph"), 
					['.json_encode($votes_by_date).'], 
					{
						series:
						{
							points: { show: true, radius: 3 }
						},
						xaxis: 
						{ 
							mode: "time", 
							tickLength: 5
						},
						yaxis:
						{
							tickDecimals: 0,
							zoomRange: false,
							panRange: false
						},
						grid: 
						{ 
							markings: function (axes) 
							{
								var markings = [];
								var d = new Date(axes.xaxis.min);
								// go to the first Saturday
								d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 1) % 7))
								d.setUTCSeconds(0);
								d.setUTCMinutes(0);
								d.setUTCHours(0);
								var i = d.getTime();
								do {
									// when we dont set yaxis, the rectangle automatically
									// extends to infinity upwards and downwards
									markings.push({ xaxis: { from: i, to: i + 2 * 24 * 60 * 60 * 1000 } });
									i += 7 * 24 * 60 * 60 * 1000;
								} while (i < axes.xaxis.max);
						
								return markings;
							},
							markingsColor: "#e7f5fc"								
						},
						zoom: 
						{
							interactive: true
						},
						pan: 
						{
							interactive: true
						}
					});

				});					
		
		');
		
	}

    $elements = $item->elements();

    if(count($elements) > 0)
    {
        $ids = array();

        $navibars->add_tab(t(22, "Elements"));

        $table = new naviorderedtable("structure_elements");

        $table->setDblclickCallback("structure_elements_open");
        $table->setHiddenInput('elements-order');

        $table->addHeaderColumn('ID', 24);
        $table->addHeaderColumn(t(486, 'Title'), 500);

        foreach($elements as $element)
        {
            $table->addRow($element->id,
                array(
                    array('content' => $element->id, 'align' => 'left'),
                    array('content' => $element->dictionary[$website->languages_list[0]]['title'], 'align' => 'left'),
                )
            );
            $ids[] = $element->id;
        }

        $navibars->add_tab_content_row(array(
                '<label>'.t(22, 'Elements').'</label>',
                '<div>'.$table->generate().'</div>',
                '<div class="subcomment">
                    <input type="hidden" name="elements-order" id="elements-order" value="'.implode("#", $ids).'" />
                    <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'
                </div>' )
        );

        $layout->add_script('
            function structure_elements_open(element)
            {
                window.location.replace("?fid=items&act=edit&id=" + $(element).attr("id") );
            }
        ');
    }
		
	return $navibars->generate();
}
?>