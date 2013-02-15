<?php
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new block();
			
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
					// translation of request search & order fields
					switch($_REQUEST['searchField'])
					{
						case 'id':
							$_REQUEST['searchField'] = 'b.id';
							break;
						case 'type':
							$_REQUEST['searchField'] = 'b.type';
							break;							
						case 'title':
							$_REQUEST['searchField'] = 'd.text';
							break;
						case 'category':
							$_REQUEST['searchField'] = 'b.category';						
							break;
						case 'dates':
							$_REQUEST['searchField'] = 'b.date_published';
							break;
						case 'enabled':
							$_REQUEST['searchField'] = 'b.enabled';
							break;
						default:
					}
								
					if($_REQUEST['sidx']=='dates')
						$_REQUEST['sidx'] = 'b.date_published';
				
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
										
					$sql = ' SELECT SQL_CALC_FOUND_ROWS b.*, d.text as title 
							   FROM nv_blocks b
						  LEFT JOIN nv_webdictionary d
						  		 	 ON b.id = d.node_id
								 	AND d.node_type = "block"
									AND d.subtype = "title"
									AND d.lang = "'.$website->languages_list[0].'"
									AND d.website = '.$website->id.'
							  WHERE '.$where.'
							    AND b.website = '.$website->id.' 
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;
				
					if(!$DB->query($sql, 'array'))
					{
						throw new Exception($DB->get_last_error());	
					}
					
					$dataset = $DB->result();
					$total = $DB->foundRows();

                    $block_types = block::types();
                    $block_types_list = array();

                    for($i=0; $i < count($block_types); $i++)
                        $block_types_list[$block_types[$i]['code']] = $block_types[$i]['title'];
									
					// we need to format the values and retrieve the needed strings from the dictionary
					$out = array();								
					for($i=0; $i < count($dataset); $i++)
					{
						if(empty($dataset[$i])) continue;
						
						$access = array(	0 => '<img src="img/icons/silk/page_white_go.png" align="absmiddle" title="'.t(254, 'Everybody').'" />',
											1 => '<img src="img/icons/silk/lock.png" align="absmiddle" title="'.t(361, 'Web users only').'" />',
											2 => '<img src="img/icons/silk/user_gray.png" align="absmiddle" title="'.t(363, 'Users who have not yet signed up or signed in').'" />',
                                            3 => '<img src="img/icons/silk/group_key.png" align="absmiddle" title="'.t(512, "Selected web user groups").'" />'
						);						
						
						if(empty($dataset[$i]['date_published'])) 
							$dataset[$i]['date_published'] = '&infin;';
						else
							$dataset[$i]['date_published'] = core_ts2date($dataset[$i]['date_published'], false);
							
						if(empty($dataset[$i]['date_unpublish'])) 
							$dataset[$i]['date_unpublish'] = '&infin;';	
						else
							$dataset[$i]['date_unpublish'] = core_ts2date($dataset[$i]['date_unpublish'], false);
							
						if($dataset[$i]['category'] > 0)
							$dataset[$i]['category'] = $DB->query_single('text', 'nv_webdictionary', 
																		 ' 	node_type = "structure" AND
																		 	node_id = "'.$dataset[$i]['category'].'" AND 
																			subtype = "title" AND
																			lang = "'.$website->languages_list[0].'"');

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1 	=> $block_types_list[$dataset[$i]['type']],
							2	=> $dataset[$i]['title'],
							3	=> $dataset[$i]['date_published'].' - '.$dataset[$i]['date_unpublish'],
							4	=> $access[$dataset[$i]['access']],
							5	=> (($dataset[$i]['enabled']==1)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />')
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);
					break;
			}
			
			session_write_close();
			exit;
			break;
		
		case 'load':
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
                    property::save_properties_from_post('block', $item->id);
					$id = $item->id;
					// set block order
					block::reorder($item->type, $_REQUEST['blocks-order'], $_REQUEST['blocks-order-fixed']);

					unset($item);					
					$item = new block();
					$item->load($id);
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
		
			$out = blocks_form($item);
			break;	

        case 'delete':
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
                    $layout->navigate_notification(t(55, 'Item removed successfully.'), false);
                    $out = blocks_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = blocks_form($item);
				}
				users_log::action($_REQUEST['fid'], $item->id, 'remove', $item->dictionary[$website->languages_list[0]]['title']);
			}
			break;

        case 'path':
		case 5:	// search an existing path
			$DB->query('SELECT path as id, path as label, path as value
						  FROM nv_paths
						 WHERE path LIKE '.protect('%'.$_REQUEST['term'].'%').' 
						   AND website = '.$website->id.'
				      ORDER BY path ASC
					     LIMIT 30',
						'array');
						
			echo json_encode($DB->result());
							  
			core_terminate();		
			break;

        case 'block_types_list':
			$out = blocks_types_list();
			break;
			
		case 'block_types_json':	// block types: json data retrieval
			$page = intval($_REQUEST['page']);
			$max	= intval($_REQUEST['rows']);
			$offset = ($page - 1) * $max;
		
			$rs = block::types($_REQUEST['sidx'], $_REQUEST['sord']);

            $block_modes = block::modes();

            // translate $rs to an array of ordered fields
            foreach($rs as $row)
            {
                $dataset[] = array(
                    'id' => $row['id'],
                    'type' => $block_modes[$row['type']],
                    'code' => $row['code'],
                    'title' => $row['title'],
                    'width' => $row['width'],
                    'height' => $row['height']
                );
            }

			$total = count($dataset);
			navitable::jqgridJson($dataset, $page, $offset, $max, $total, 'id');
		
			session_write_close();
			exit;
			break;			

        case 'block_type_edit':
		case 82: // edit/create block type		
			$dataset = block::custom_types();
			$item = NULL;
            $position = NULL;
            $max_id = 0;
			
			for($i=0; $i < count($dataset); $i++)
			{
                if($dataset[$i]['id'] > $max_id)
                    $max_id = $dataset[$i]['id'];

				if($dataset[$i]['id'] == $_REQUEST['id'])
				{
					$item = $dataset[$i];
                    $position = $i;
				}
			}

    		if(isset($_REQUEST['form-sent']))
			{
				if(empty($item))
					$item = array('id' => $max_id + 1);

				$item['title'] = $_REQUEST['title'];
                $item['code'] = $_REQUEST['code'];
				$item['width'] = $_REQUEST['width'];
				$item['height'] = $_REQUEST['height'];
				$item['order'] = $_REQUEST['order'];
				$item['maximum'] = $_REQUEST['maximum'];
				$item['notes'] = pquotes($_REQUEST['notes']);

				if(!is_null($position))
					$dataset[$position] = $item;
                else
                    $dataset[] = $item;

				try
				{
					// save
					$ok = block::types_update($dataset);
					$layout->navigate_notification(t(53, "Data saved successfully."), false);	
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
			
			$out = blocks_type_form($item);
			break;

        case 'block_type_delete':
		case 84: // remove block type		
            $dataset = block::custom_types();
			$item = NULL;
			
			for($i=0; $i < count($dataset); $i++)
			{
				if($dataset[$i]['id'] == $_REQUEST['id'])
				{
					unset($dataset[$i]);
					break;
				}
			}
			
			try
			{													
				block::types_update($dataset);
                $layout->navigate_notification(t(55, 'Item removed successfully.'), false);
                $out = blocks_types_list();
			}
			catch(Exception $e)
			{
				$out = $layout->navigate_message("error", t(23, 'Blocks'), t(56, 'Unexpected error.'));			
			}
			break;

        case 'block_property_load':
            $property = new property();

            if(!empty($_REQUEST['id']))
            {
                if(is_numeric($_REQUEST['id']))
                    $property->load(intval($_REQUEST['id']));
                else
                    $property->load_from_theme($_REQUEST['id'], null, 'block', $_REQUEST['block']);
            }

            header('Content-type: text/json');

            $types = property::types();
            $property->type_text = $types[$property->type];

            echo json_encode($property);

            session_write_close();
            exit;
            break;

        case 'block_property_save': // save property details

            $property = new property();

            if(!empty($_REQUEST['property-id']))
                $property->load(intval($_REQUEST['property-id']));

            $property->load_from_post();
            $property->save();

            header('Content-type: text/json');

            $types = property::types();
            $property->type_text = $types[$property->type];

            echo json_encode($property);

            session_write_close();
            exit;
            break;

        case 'block_property_remove': // remove property

            $property = new property();

            if(!empty($_REQUEST['property-id']))
                $property->load(intval($_REQUEST['property-id']));

            $property->delete();

            session_write_close();
            exit;
            break;
		
		case 0: // list / search result
		default:			
			$out = blocks_list();
			break;
	}
	
	return $out;
}

function blocks_list()
{
	$navibars = new navibars();
	$navitable = new navitable("blocks_list");
	
	$navibars->title(t(23, 'Blocks'));

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/wrench_orange.png"> '.t(167, 'Types').'</a>' ) );

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));
	
	if(@$_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=2&id=');
	$navitable->enableSearch();
	$navitable->enableDelete();	
	
	$navitable->addCol("ID", 'id', "40", "true", "left");	
	$navitable->addCol(t(160, 'Type'), 'type', "120", "true", "center");	
	$navitable->addCol(t(67, 'Title'), 'title', "400", "true", "left");	
	$navitable->addCol(t(85, 'Date published'), 'dates', "100", "true", "center");	
	$navitable->addCol(t(364, 'Access'), 'access', "40", "true", "center");	
	$navitable->addCol(t(65, 'Enabled'), 'enabled', "40", "true", "center");		
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
}

function blocks_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();	
	$layout->navigate_media_browser();	// we can use media browser in this function
		
	if(empty($item->id))
		$navibars->title(t(23, 'Blocks').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(23, 'Blocks').' / '.t(170, 'Edit').' ['.$item->id.']');			

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

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/wrench_orange.png"> '.t(167, 'Types').'</a>' ) );
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));


	$block_types = block::types();
	$block_types_keys = array();
	$block_types_info = array();	
	
	for($i=0; $i < count($block_types); $i++)
	{
        if($item->type == $block_types[$i]['code'])
            $block_type_width = $block_types[$i]['width'];

        if(empty($block_types[$i]['width'])) $block_types[$i]['width'] = '***';
		if(empty($block_types[$i]['height'])) $block_types[$i]['height'] = '***';		
		
		$block_types_keys[] = $block_types[$i]['code'];
		$block_types_info[] = $block_types[$i]['title'].' ('.$block_types[$i]['width'].' x '.$block_types[$i]['height'].' px)';
	}
										
	$navibars->add_tab_content_row(array(	'<label>'.t(160, 'Type').'</label>',
											$naviforms->selectfield('type', $block_types_keys, $block_types_info, $item->type)
										)
									);				
										
	$navibars->add_tab_content_row(array(	'<label>'.t(85, 'Date published').'</label>',
											$naviforms->datefield('date_published', $item->date_published, true),
										));		
										
	$navibars->add_tab_content_row(array(	'<label>'.t(90, 'Date unpublished').'</label>',
											$naviforms->datefield('date_unpublish', $item->date_unpublish, true),
										));												
										
	$navibars->add_tab_content_row(array(	'<label>'.t(168, 'Notes').'</label>',
											$naviforms->textarea('notes', $item->notes)
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

	if(empty($item->id)) $item->enabled = true;
										
	$navibars->add_tab_content_row(array(	'<label>'.t(65, 'Enabled').'</label>',
											$naviforms->checkbox('enabled', $item->enabled),
										));	

	$navibars->add_tab(t(9, "Content"));

    // TODO: Navigate 1.9 show block edit form depending on its type (block, map, ad...)

	$options = array();
	foreach($website->languages_list as $lang)
        $options[$lang] = language::name_by_code($lang);

	$navibars->add_tab_content_row(array(	'<label>'.t(63, 'Languages').'</label>',
											$naviforms->buttonset('language_selector', $options, $website->languages_list[0], "navigate_items_select_language(this);")
										));	
										
	foreach($website->languages_list as $lang)
	{		
		$navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">');
		
		$navibars->add_tab_content_row(array(	'<label>'.t(67, 'Title').'</label>',
												$naviforms->textfield('title-'.$lang, @$item->dictionary[$lang]['title']),
												''
											));		
											
		$navibars->add_tab_content_row(array(	'<label>'.t(180, 'Item').'</label>',
												$naviforms->selectfield('trigger-type-'.$lang,
													array(
															0 => '',
															1 => 'image',
															2 => 'rollover',
															3 => 'flash',
															4 => 'html',
															5 => 'content'
														),
													array(
															0 => t(181, 'Hidden'),
															1 => t(157, 'Image'),
															2 => t(182, 'Rollover'),
															3 => 'Flash',
															4 => 'HTML',
															5 => t(9, 'Content'),
														),
													$item->trigger['trigger-type'][$lang],
													"navigate_blocks_trigger_change('".$lang."', this);"
												)
											)
										);
										
		$navibars->add_tab_content_row(array(	'<label>'.t(157, 'Image').'</label>',
												$naviforms->dropbox('trigger-image-'.$lang, @$item->trigger['trigger-image'][$lang], 'image')
											));			
																									

		$navibars->add_tab_content_row(array(	'<label>'.t(182, 'Rollover').'</label>',
												$naviforms->dropbox('trigger-rollover-'.$lang, @$item->trigger['trigger-rollover'][$lang], 'image'),
												'<br />',
												'<label>&nbsp;</label>',
												$naviforms->dropbox('trigger-rollover-active-'.$lang, @$item->trigger['trigger-rollover-active'][$lang], 'image'),
												''
											));											
											
		$navibars->add_tab_content_row(array(	'<label>Adobe Flash</label>',
												$naviforms->dropbox('trigger-flash-'.$lang, @$item->trigger['trigger-flash'][$lang], 'flash'),
												''
											));										
											
		$navibars->add_tab_content_row(array(	'<label>HTML</label>',
												$naviforms->scriptarea('trigger-html-'.$lang, @$item->trigger['trigger-html'][$lang]),
												''
											));

        if(!empty($block_type_width))
            $block_type_width .= 'px';

		$navibars->add_tab_content_row(array(	'<label>'.t(9, 'Content').'</label>',
												$naviforms->editorfield('trigger-content-'.$lang, @$item->trigger['trigger-content'][$lang], $block_type_width, $lang),
												''
											));																														
																				
																			
		$navibars->add_tab_content_row(array(	'<label>'.t(172, 'Action').'</label>',
												$naviforms->selectfield('action-type-'.$lang,
													array(
															0 => '',
															1 => 'web',
															2 => 'web-n',
															3 => 'file',
															4 => 'image'
														),
													array(
															0 => t(183, 'Do nothing'),
															1 => t(173, 'Open URL'),
															2 => t(174, 'Open URL (new window)'),
															3 => t(175, 'Download file'),
															4 => t(176, 'View image')
														),
													$item->action['action-type'][$lang],
													"navigate_blocks_action_change('".$lang."', this);"
												)
											)
										);			
										
		/* show/hide appropiate row type by action */

		$navibars->add_tab_content_row(array(	'<label>'.t(184, 'Webpage').'</label>',
												$naviforms->autocomplete('action-web-'.$lang, @$item->action['action-web'][$lang], '?fid='.$_REQUEST['fid'].'&act=5'),
												''
											));	
											
		$navibars->add_tab_content_row(array(	'<label>'.t(82, 'File').'</label>',
												$naviforms->dropbox('action-file-'.$lang, @$item->action['action-file'][$lang]),
												''
											));	
											
		$navibars->add_tab_content_row(array(	'<label>'.t(157, 'Image').'</label>',
												$naviforms->dropbox('action-image-'.$lang, @$item->action['action-image'][$lang], 'image'),
												''
											));		
											
		$layout->add_script('
			function navigate_blocks_action_change(lang, el)
			{
				$("#action-web-"+lang).parent().hide();
				$("#action-file-"+lang).parent().hide();
				$("#action-image-"+lang).parent().hide();
				
				var action_type = $(el).val();
				
				if(action_type == "web" || action_type == "web-n")
					$("#action-web-" + lang).parent().show();
				else
					$("#action-" + action_type + "-" + lang).parent().show();
			}
			
			function navigate_blocks_trigger_change(lang, el)
			{
				$("#trigger-image-"+lang).parent().hide();
				$("#trigger-rollover-"+lang).parent().hide();
				$("#trigger-flash-"+lang).parent().hide();
				$("#trigger-html-"+lang).parent().hide();
				$("#trigger-content-"+lang).parent().hide();
				
				$("#trigger-" + $(el).val() + "-" + lang).parent().show();
				$(navigate_codemirror_instances).each(function() { this.refresh(); } );					
			}
		');																																							

																							
		$navibars->add_tab_content('</div>');		
	}
	
	$layout->add_script('
		var active_languages = ["'.implode('", "', array_keys($options)).'"];
	
		function navigate_items_select_language(el)
		{
			var code;
			if(typeof(el)=="string") 
				code = el;
			else 
				code = $("#"+$(el).attr("for")).val();	
				
			$(".language_fields").css("display", "none");
			$("#language_fields_" + code).css("display", "block");
			
			$("#language_selector_" + code).attr("checked", "checked");
		}
	');
	
	$layout->add_script('navigate_items_select_language("'.$website->languages_list[0].'");');
	
	foreach($website->languages_list as $alang)
	{
		$layout->add_script('navigate_blocks_trigger_change("'.$alang.'", $("<input type=\"text\" value=\"'.$item->trigger['trigger-type'][$alang].'\" />"));');
		$layout->add_script('navigate_blocks_action_change("'.$alang.'", $("<input type=\"text\" value=\"'.$item->action['action-type'][$alang].'\" />"));');
	}

    if(!empty($item->type))
    {
        // we need to know if the block is defined in the active theme or in the database (numeric ID)
        foreach($block_types as $bt)
        {
            if($bt['code']==$item->type)
            {
                $block_type_id = $bt['id'];
                break;
            }
        }

        $properties_html = navigate_property_layout_form('block', $block_type_id, 'block', $item->id);

        if(!empty($properties_html))
        {
            $navibars->add_tab(t(77, "Properties"));
            $navibars->add_tab_content($properties_html);
        }
    }

    $navibars->add_tab(t(330, "Categories"));

    $navibars->add_tab_content_row(array(
        '<label>'.t(330, 'Categories').'</label>',
        $naviforms->buttonset(
            'all_categories',
            array(
                '1' => t(396, 'All categories'),
                '0' => t(405, 'Selection')
            ),
            (empty($item->categories)? '1' : '0')
        )
    ));
/*
	$navibars->add_tab_content_row(array(	'<label>'.t(396, 'All categories').'</label>',
											$naviforms->checkbox('all_categories', empty($item->categories)),
										));	
*/
	$hierarchy = structure::hierarchy(0);
	$categories_list = structure::hierarchyList($hierarchy, $item->categories);

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<div class="category_tree" id="category-tree-parent"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.$categories_list.'</div>'
										));		
										
	if(!is_array($item->categories))
		$item->categories = array();
		
	$navibars->add_tab_content($naviforms->hidden('categories', implode(',', $item->categories)));		
										
	$layout->add_script('

	    function navigate_blocks_all_categories_switch()
	    {
            if($("#all_categories_1").is(":checked"))
	            $("#category-tree-parent").parent().slideUp();
	         else
	            $("#category-tree-parent").parent().slideDown();
	    }

	    $("#all_categories_0,#all_categories_1").bind("click", navigate_blocks_all_categories_switch);

		$("#category-tree-parent ul:first").kvaTree(
		{
	        imgFolder: "js/kvatree/img/",
			dragdrop: false,
			background: "#f2f5f7",
			overrideEvents: true,
			onClick: function(event, node)
			{
				if($(node).find("span:first").hasClass("active"))
					$(node).find("span:first").removeClass("active");
				else
					$(node).find("span:first").addClass("active");
				
				var categories = new Array();
				
				$("#category-tree-parent span.active").parent().each(function()
				{
					categories.push($(this).attr("value"));
				});
				
				if(categories.length > 0)								
					$("#categories").val(categories);
				else
					$("#categories").val("");		
			}
		});
		
		$("#category-tree-parent li").find("span:first").css("cursor", "pointer");

		navigate_blocks_all_categories_switch();
		
	');	

							
	if(!empty($item->type))
	{				
		$navibars->add_tab(t(171, 'Order'));	// order blocs of the same type
		
		$DB->query('SELECT b.id as id, d.text as title, b.fixed as fixed
					  FROM nv_blocks b, nv_webdictionary d
					 WHERE b.type = "'.$item->type.'"
					   AND d.node_type = "block"
					   AND d.subtype = "title"
					   AND d.lang = "'.$website->languages_list[0].'"
					   AND d.node_id = b.id
					   AND d.website = '.$website->id.'
					   AND b.website = '.$website->id.'
					ORDER BY b.position ASC');				
										  
		$block_ids = $DB->result('id');
		$blocks = $DB->result();

		$navibars->add_tab_content($naviforms->hidden('blocks-order', implode('#', $block_ids)));

		$table = new naviorderedtable("blocks_order_table");
		$table->setWidth("408px");
		$table->setHiddenInput("blocks-order");

		$table->addHeaderColumn('ID', 50);			
		$table->addHeaderColumn(t(67, 'Title'), 350);
        $table->addHeaderColumn('<div style=" text-align: center; ">'.t(394, 'Fixed').'</div>', 50);
		
		foreach($blocks as $block)
		{
			$table->addRow($block->id, array(
				array('content' => $block->id, 'align' => 'left'),
				array('content' => $block->title, 'align' => 'left'),
                array('content' => '<input type="checkbox" name="blocks-order-fixed['.$block->id.']" value="1" '.(($block->fixed=='1')? 'checked="checked"' : '').' />', 'align' => 'center')
			));
		}	
		
		$navibars->add_tab_content_row(array(	'<label>'.t(23, 'Blocks').'</label>',
												'<div>'.$table->generate().'</div>',
												'<div class="subcomment"><img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'</div>',
                                                '<div class="subcomment"><span class="ui-icon ui-icon-lightbulb" style=" float: left; margin-right: 4px; "></span> '.t(395, '"Fixed" assigns a static position when the order is random').'</div>'
                                       ));
											
	}
	return $navibars->generate();
}

function blocks_types_list()
{
	global $user;
	global $DB;
	global $website;
	
	$navibars = new navibars();
	$navitable = new navitable('blocks_types_list');
	
	$navibars->title(t(23, 'Blocks').' / '.t(167, 'Types'));
	
	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>' ) );		
	
	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=82"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
								));


	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=block_types_json');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=82&id=');
	
	$navitable->addCol("ID", 'id', "80", "true", "left");
    $navitable->addCol(t(491, 'Class'), 'type', "80", "true", "left");
    $navitable->addCol(t(237, 'Code'), 'code', "120", "true", "left");
	$navitable->addCol(t(67, 'Title'), 'title', "200", "true", "left");
	$navitable->addCol(t(155, 'Width').' (px)', 'width', "80", "true", "left");	
	$navitable->addCol(t(156, 'Height').' (px)', 'height', "80", "true", "left");		
	
	$navibars->add_content($navitable->generate());
	
	return $navibars->generate();
}

function blocks_type_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item['id']))
		$navibars->title(t(23, 'Blocks').' / '.t(167, 'Types').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(23, 'Blocks').' / '.t(167, 'Types').' / '.t(170, 'Edit').' ['.$item['id'].']');	

    $readonly = false;

	if(empty($item['id']))
	{
		$navibars->add_actions(
            array(	'<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
		);
	}
    else if(!empty($item['id']) && !is_numeric($item['id']))
    {
        $layout->navigate_notification(t(432, "Read only mode"), false, true);
        $readonly = true;
    }
	else
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
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
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=84&id='.$item['id'].'";
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>' ) );	
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid='.$_REQUEST['fid'].'&act=82"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
									));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item['id']));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item['id'])? $item['id'] : t(52, '(new)')).'</span>' ));

    // TODO: in Navigate 1.8 add different block types (p.e. Ad (Google adsense, ...), Map (Bing, Yahoo, Google, ...))
    $block_modes = block::modes();
    $navibars->add_tab_content_row(array(	'<label>'.t(491, 'Class').'</label>',
   											$naviforms->selectfield('type',
                                                array_keys($block_modes),
                                                array_values($block_modes),
   												$item['type'],
   												'',
   												false
   											)
   										)
   									);

    $navibars->add_tab_content_row(array(	'<label>'.t(67, 'Title').'</label>',
   											$naviforms->textfield('title', $item['title'])
   										));

    $navibars->add_tab_content_row(array(	'<label>'.t(237, 'Code').'</label>',
    									    $naviforms->textfield('code', $item['code']),
                                            '<div class="subcomment">
                                                <span style=" float: left; margin-left: -3px; " class="ui-icon ui-icon-lightbulb"></span>'.
                                                t(436, 'Used as a class in HTML elements').
                                            '</div>'
    									));

	$navibars->add_tab_content_row(array(	'<label>'.t(168, 'Notes').'</label>',
											$naviforms->textarea('notes', $item['notes'])
										));

    $navibars->add_tab(t(145, "Size").' & '.t(438, "Order"));

    $navibars->add_tab_content_row(array(	'<label>'.t(155, 'Width').'<sup>*</sup></label>',
   											$naviforms->textfield('width', $item['width']),
   											'px'
   										));

   	$navibars->add_tab_content_row(array(	'<label>'.t(156, 'Height').'<sup>*</sup></label>',
   											$naviforms->textfield('height', $item['height']),
   											'px'
   										));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
   											'<div class="subcomment italic">* '.t(169, 'You can leave blank a field to not limit the size').'</div>'
   										));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a id="wikipedia_web_banner_entry" class="italic" href="http://en.wikipedia.org/wiki/Web_banner" target="_blank"><span class="ui-icon ui-icon-info" style=" float: left;"></span> '.t(393, 'Standard banner sizes').' (Wikipedia)</a>'
										));

    $navibars->add_tab_content_row(array(	'<label>'.t(404, 'Order by').'</label>',
   											$naviforms->selectfield(
   												'order',
   												array(
   													'theme',
   													'priority',
   													'random'
   												),
   												array(
   													t('368', 'Theme'),
   													t('66', 'Priority'),
   													t('399', 'Random')
   												),
   												$item['order']
   											)
   										));

   	$navibars->add_tab_content_row(array(	'<label>'.t(397, 'Maximum').'</label>',
   											$naviforms->textfield('maximum', $item['maximum']),
   											'<div class="subcomment"><span class="ui-icon ui-icon-lightbulb" style=" float: left; margin-left: -3px; "></span> '.t(400, 'Enter 0 to display all').'</div>'
   										));

    $layout->add_script('
        function navigate_blocks_code_generate(el)
        {
            if($("#code").val()!="")
                return;
            var title = $("#title").val();
            title = title.replace(/\s+/g, "_");
            title = title.replace(/([\'"?:!¿#\\\\])/g, "");
            $("#code").val(title.toLowerCase());
        }

        $("#code").bind("focus", function()
        {
            if($(this).val() == "")
                navigate_blocks_code_generate();
        });
        '
    );

    if(!empty($item['id']))
    {
        $navibars->add_tab(t(77, "Properties"));

        $table = new naviorderedtable("block_properties_table");
        $table->setWidth("550px");
        $table->setHiddenInput("block-properties-order");
        $table->setDblclickCallback("navigate_block_edit_property");

        $navibars->add_tab_content( $naviforms->hidden('block-properties-order', "") );

        $table->addHeaderColumn(t(159, 'Name'), 350, true);
        $table->addHeaderColumn(t(160, 'Type'), 150);
        $table->addHeaderColumn(t(65, 'Enabled'), 50);

        $properties = property::elements($item['id'], 'block');
        $types		= property::types();

        for($p=0; $p < count($properties); $p++)
        {
            $table->addRow($properties[$p]->id, array(
                array('content' => $properties[$p]->name, 'align' => 'left'),
                array('content' => $types[$properties[$p]->type], 'align' => 'left'),
                array('content' => '<input type="checkbox" name="property-enabled[]" value="'.$properties[$p]->id.'" '.(($properties[$p]->enabled=='1'? ' checked=checked ' : '')).' />', 'align' => 'center'),
            ));
        }

        if($readonly)
        {
            $navibars->add_tab_content_row(array(	'<label>'.t(77, 'Properties').'</label>',
                '<div>'.$table->generate().'</div>'));
        }
        else
        {
            $navibars->add_tab_content_row(array(	'<label>'.t(77, 'Properties').'</label>',
                '<div>'.$table->generate().'</div>',
                '<div class="subcomment">
                    <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'.
                     '.t(192, 'Double click any row to edit').'
                </div>' ));

            $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                '<button id="block-properties-create"><img src="img/icons/silk/add.png" align="absmiddle" /> '.t(38, 'Create').'</button>'));
        }

        $navibars->add_content('
		<form id="block-properties-edit-dialog" style="display: none;">
			<div class="navigate-form-row">
				<label>ID</label>
				<span id="property-id-span">'.t(52, '(new)').'</span>
				'.$naviforms->hidden('property-id', '').'
				'.$naviforms->hidden('property-template', $item->id).'
				'.$naviforms->hidden('property-element', "block").'
			</div>
			<div class="navigate-form-row">
				<label>'.t(67, 'Title').'</label>
				'.$naviforms->textfield('property-name', '').'
			</div>
			<div class="navigate-form-row">
				<label>'.t(160, 'Type').'</label>
				'.$naviforms->selectfield(
                    'property-type',
                    array_keys($types),
                    array_values($types),
                    'value',
                    'navigate_block_property_type_change()'
                ).'
			</div>
			<div class="navigate-form-row">
				<label>'.t(200, 'Options').'</label>
				'.$naviforms->textarea('property-options', '').'
				<div class="subcomment">
					'.t(201, 'One line per option, formatted like this: value#title').'
				</div>
			</div>
			<div class="navigate-form-row">
				<label>'.t(199, 'Default value').'</label>
				'.$naviforms->textfield('property-dvalue', '').'
				<div class="subcomment">
				    <span id="property-comment-boolean">'.t(426, 'Enter "1" for true, "0" for false').'</span>
					<span id="property-comment-option">'.t(202, 'Enter only the value').'</span>
					<span id="property-comment-moption">'.t(212, 'Enter the selected values separated by commas').': 3,5,8</span>
					<span id="property-comment-text">'.t(203, 'Same value for all languages').'</span>
					<span id="property-comment-rating">'.t(223, 'Default is 5 stars, if you want a different number: default_value#number_of_stars').' 5#10</span>
					<span id="property-comment-date">'.t(50, 'Date format').': '.date($user->date_format).'</span>
					<span id="property-comment-color">'.t(442, 'Hexadecimal color code').': #ffffff</span>
					<span id="property-comment-country">'.t(225, 'Alpha-2 country code').' (es, us, uk...)</span>
					<span id="property-comment-file">'.t(204, 'ID of the file').'</span>
					<span id="property-comment-coordinates">'.t(298, 'Latitude').'#'.t(299, 'Longitude').': 40.689231#-74.044505</span>
				</div>
			</div>
			<div class="navigate-form-row">
				<label>'.t(65, 'Enabled').'</label>
				'.$naviforms->checkbox('property-enabled', 1).'
			</div>
		</form>');

        $layout->add_script('
			$("#block-properties-create").bind("click", function()
			{
				navigate_block_edit_property();
				return false;
			});

			function navigate_block_edit_property(el)
			{
				if(!el)	// new property
				{
					$("#property-id").val("");
					$("#property-id-span").html("'.t(52, '(new)').'");
					$("#property-template").val("'.$item['id'].'");
					$("#property-name").val("");
					$("#property-type").val("value");
					$("#property-element").val("block");
					$("#property-options").val("");
					$("#property-dvalue").val("");
				    $("#property-enabled").attr("checked", "checked");
				}
				else
				{
					$.ajax({
					   type: "GET",
					   async: false,
					   dateType: "json",
					   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=block_property_load&block='.$item->id.'&id=" + $(el).attr("id"),
					   success: function(data)
					   {
						   $("#property-id-span").html(data.id);
						   $("#property-id").val(data.id);
						   $("#property-template").val(data.template);
						   $("#property-name").val(data.name);
						   $("#property-type").val(data.type);
						   $("#property-element").val("block");
						   $("#property-options").val(data.options);
						   $("#property-dvalue").val(data.dvalue);
						   if(data.enabled=="1")
							   $("#property-enabled").attr("checked", "checked");
							else
							   $("#property-enabled").removeAttr("checked");

						   var options = "";
						   for(var o in data.options)
						   {
							   options += o + "#" + data.options[o] + "\n";
						   }
						   $("#property-options").val(options);

					   }
					 });
				}

				navigate_block_property_type_change();

				if('.($readonly? 'true' : 'false').')
				{
                    $("#block-properties-edit-dialog").dialog(
                    {
                        title: \'<img src="img/icons/silk/pencil.png" align="absmiddle" /> '.t(170, 'Edit').'\',
                        resizable: true,
                        height: 360,
                        width: 650,
                        modal: true,
                    });
				}
				else // show dialog with action buttons
				{
                    $("#block-properties-edit-dialog").dialog(
                    {
                        title: \'<img src="img/icons/silk/pencil.png" align="absmiddle" /> '.t(170, 'Edit').'\',
                        resizable: true,
                        height: 410,
                        width: 650,
                        modal: true,
                        buttons: {
                            "'.t(58, 'Cancel').'": function() {
                                $(this).dialog("close");
                            },
                            "'.t(35, 'Delete').'": function() {
                                $.ajax({
                                   type: "POST",
                                   async: false,
                                   dateType: "text",
                                   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=block_property_remove",
                                   data: $("#block-properties-edit-dialog").serialize(),
                                   success: function(msg)
                                   {
                                     $("#block_properties_table").find("#" + $("#property-id").val()).remove();
                                     navigate_naviorderedtable_block_properties_table_reorder();
                                     $("#block-properties-edit-dialog").dialog("close");
                                   }
                                 });
                            },
                            "'.t(190, 'Ok').'": function()
                            {
                                $.ajax({
                                   type: "POST",
                                   async: false,
                                   dateType: "text",
                                   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=block_property_save",
                                   data: $("#block-properties-edit-dialog").serialize(),
                                   success: function(data)
                                   {
                                       if($("#property-id").val() > 0)
                                       {
                                           // update
                                           var tr = $("#block_properties_table").find("#" + $("#property-id").val());
                                           tr.find("td").eq(0).html(data.name);
                                           tr.find("td").eq(1).html(data.type_text);
                                           tr.find("input[type=checkbox]").attr("checked", (data.enabled==1));
                                       }
                                       else
                                       {
                                           // insert
                                           var checked = "";

                                           if(data.enabled) checked = \' checked="checked" \';
                                           var tr = \'<tr id="\'+data.id+\'"><td>\'+data.name+\'</td><td>\'+data.type_text+\'</td><td align="center"><input name="property-enabled[]" type="checkbox" value="\'+data.id+\'" \'+checked+\' /></td></tr>\';
                                           $("#block_properties_table").find("tbody:last").append(tr);
                                           $("#block_properties_table").find("tr:last").bind("dblclick", function() { navigate_block_edit_property(this); });
                                           $("#block_properties_table").tableDnD(
                                            {
                                                onDrop: function(table, row)
                                                {		navigate_naviorderedtable_block_properties_table_reorder();		}
                                            });
                                       }
                                       navigate_naviorderedtable_block_properties_table_reorder();
                                       $("#block-properties-edit-dialog").dialog("close");
                                   }
                                 });
                            }
                        }
                    });
                }
			}

			function navigate_block_property_type_change()
			{
				$("#property-options").parent().hide();
				$("#property-dvalue").next().find("span").hide();

				switch($("#property-type").val())
				{
					case "option":
						$("#property-options").parent().show();
						$("#property-comment-option").show();
						break;

					case "moption":
						$("#property-options").parent().show();
						$("#property-comment-moption").show();
						break;

					case "text":
					case "textarea":
					case "link":
						$("#property-comment-text").show();
						break;

					case "date":
					case "datetime":
						$("#property-comment-date").show();
						break;

					case "image":
					case "file":
						$("#property-comment-file").show();
						break;

					case "rating":
						$("#property-comment-rating").show();
						break;

                    case "color":
						$("#property-comment-color").show();
						break;

					case "coordinates":
						$("#property-comment-coordinates").show();
						break;

					case "country":
						$("#property-comment-country").show();
						break;

                    case "boolean":
						$("#property-comment-boolean").show();
						break;

					case "comment":
					case "value":
					default:
				}
			}

			navigate_naviorderedtable_block_properties_table_reorder();
		');

    }

	return $navibars->generate();
}
?>