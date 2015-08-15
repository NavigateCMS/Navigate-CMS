<?php
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block_group.class.php');
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
                        {
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
                            // special case
                            if( strpos($where, 'title LIKE')!== false)
                            {
                                $where = substr_replace($where, 'd.text', strpos($where, 'title LIKE'), 5);
                            }
                        }
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

                    $dataset = grid_notes::summary($dataset, 'block', 'id');
									
					// we need to format the values and retrieve the needed strings from the dictionary
					$out = array();								
					for($i=0; $i < count($dataset); $i++)
					{
						if(empty($dataset[$i])) continue;
						
						$access = array(
                            0 => '<img src="img/icons/silk/page_white_go.png" align="absmiddle" title="'.t(254, 'Everybody').'" />',
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
							$dataset[$i]['category'] = $DB->query_single(
                                'text',
                                'nv_webdictionary',
                                ' 	node_type = "structure" AND
                                    node_id = "'.$dataset[$i]['category'].'" AND
                                    subtype = "title" AND
                                    lang = "'.$website->languages_list[0].'"
                                '
                            );

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1 	=> $block_types_list[$dataset[$i]['type']],
                            2 	=> '<div class="list-row" data-enabled="'.$dataset[$i]['enabled'].'">'.$dataset[$i]['title'].'</div>',
							3	=> $dataset[$i]['date_published'].' - '.$dataset[$i]['date_unpublish'],
							4	=> $access[$dataset[$i]['access']],
							5	=> (($dataset[$i]['enabled']==1)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />'),
                            6 	=> $dataset[$i]['_grid_notes_html']
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
                    if(!empty($item->type) && !empty($_REQUEST['blocks-order']))
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

        case 'block_groups_list':
            $out = block_groups_list();
            break;

        case 'block_groups_json':	// block groups: json data retrieval
			$page = intval($_REQUEST['page']);
			$max	= intval($_REQUEST['rows']);
			$offset = ($page - 1) * $max;

			list($rs, $total) = block_group::paginated_list($offset, $max, $_REQUEST['sidx'], $_REQUEST['sord']);

            // translate $rs to an array of ordered fields
            foreach($rs as $row)
            {
                $row['blocks'] = mb_unserialize($row['blocks']);
                $dataset[] = array(
                    'id' => $row['id'],
                    'code' => $row['code'],
                    'title' => $row['title'],
                    'blocks' => count($row['blocks'])
                );
            }

			navitable::jqgridJson($dataset, $page, $offset, $max, $total, 'id');

			session_write_close();
			exit;
			break;

        case 'block_group_edit':
            $item = new block_group();

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
                    $id = $item->id;
                    $layout->navigate_notification(t(53, "Data saved successfully."), false);
                }
                catch(Exception $e)
                {
                    $layout->navigate_notification($e->getMessage(), true, true);
                }
                users_log::action($_REQUEST['fid'], $item->id, 'save', $item->title, json_encode($_REQUEST));
            }
            else if(!empty($_REQUEST['id']))
                users_log::action($_REQUEST['fid'], $item->id, 'edit', $item->title);

			$out = block_group_form($item);
			break;

        case 'block_group_delete':
            $item = new block_group();
            if(!empty($_REQUEST['id']))
            {
                $item->load(intval($_REQUEST['id']));
                if($item->delete() > 0)
                {
                    $layout->navigate_notification(t(55, 'Item removed successfully.'), false);
                    $out = block_groups_list();
                }
                else
                {
                    $layout->navigate_notification(t(56, 'Unexpected error.'), false);
                    $out = block_group_form($item);
                }
                users_log::action($_REQUEST['fid'], $item->id, 'remove', $item->title);
            }
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

				$item['type'] = $_REQUEST['type'];
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

        case 'block_group_block_options':
            $status = null;
            $block_group = $_REQUEST['block_group'];
            $block_code = $_REQUEST['code'];

            if(isset($_REQUEST['form-sent']))
                $status = property::save_properties_from_post('block_group_block', $block_code, $block_group, $block_code);

            $out = block_group_block_options($block_group, $block_code, $status);

            echo $out;

            core_terminate();
            break;

        case 'grid_note_background':
            grid_notes::background('block', $_REQUEST['id'], $_REQUEST['background']);
            core_terminate();
            break;

        case 'grid_notes_comments':
            $comments = grid_notes::comments('block', $_REQUEST['id'], false);
            echo json_encode($comments);
            core_terminate();
            break;

        case 'grid_notes_add_comment':
            echo grid_notes::add_comment('block', $_REQUEST['id'], $_REQUEST['comment'], $_REQUEST['background']);
            core_terminate();
            break;

        case 'grid_note_remove':
            echo grid_notes::remove($_REQUEST['id']);
            core_terminate();
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
    global $events;

	$navibars = new navibars();
	$navitable = new navitable("blocks_list");
	
	$navibars->title(t(23, 'Blocks'));

    // retrieve block groups, if more than 10, do not show quickmenu

    $group_blocks_links = array();
    list($bg_rs, $bg_total) = block_group::paginated_list(0, 10, 'title', 'desc');

    if($bg_total > 0 && $bg_total <= 10)
    {
        foreach($bg_rs as $bg)
            $group_blocks_links[] = '<a class="ui-menu-action-bigger" href="?fid='.$_REQUEST['fid'].'&act=block_group_edit&id='.$bg['id'].'"><i class="fa fa-fw fa-caret-right"></i> '.$bg['title'].'</a>';

        $events->add_actions(
            'blocks',
            array(
                'item' => null,
                'navibars' => &$navibars
            ),
            $group_blocks_links,
            '<a class="content-actions-submenu-trigger" href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_link.png"> '.t(506, 'Groups').' &#9662;</a>'
        );
    }

    $navibars->add_actions(
        array(
            (!empty($group_blocks_links)? '' : '<a href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_link.png"> '.t(506, 'Groups').'</a>'),
            '<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_edit.png"> '.t(167, 'Types').'</a>'
        )
    );

	$navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
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
    $navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
}

function blocks_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
    global $events;
    global $theme;
	
	$navibars = new navibars();
	$naviforms = new naviforms();	
	$layout->navigate_media_browser();	// we can use media browser in this function
		
	if(empty($item->id))
		$navibars->title(t(23, 'Blocks').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(23, 'Blocks').' / '.t(170, 'Edit').' ['.$item->id.']');			

	$navibars->add_actions(
        array(
            '<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'
        )
    );

    if(!empty($item->id))
    {
        $notes = grid_notes::comments('block', $item->id);
        $navibars->add_actions(
            array(
                '<a href="#" onclick="javascript: navigate_display_notes_dialog();"><span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span><img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'</a>'
            )
        );
    }

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

    $group_blocks_links = array();
    list($bg_rs, $bg_total) = block_group::paginated_list(0, 10, 'title', 'desc');

    if($bg_total > 0 && $bg_total <= 10)
    {
        foreach($bg_rs as $bg)
            $group_blocks_links[] = '<a href="?fid='.$_REQUEST['fid'].'&act=block_group_edit&id='.$bg['id'].'"><i class="fa fa-fw fa-caret-right"></i> '.$bg['title'].'</a>';

        $events->add_actions(
            'blocks',
            array(
                'item' => null,
                'navibars' => &$navibars
            ),
            $group_blocks_links,
            '<a class="content-actions-submenu-trigger" href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_link.png"> '.t(506, 'Groups').' &#9662;</a>'
        );
    }

    $navibars->add_actions(
        array(
            (!empty($group_blocks_links)? '' : '<a href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_link.png"> '.t(506, 'Groups').'</a>'),
            '<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_edit.png"> '.t(167, 'Types').'</a>'
        )
    );
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

    if(!empty($item->id))
        $layout->navigate_notes_dialog('block', $item->id);

	$navibars->form();

    $navibars->add_content('<script type="text/javascript" src="lib/packages/blocks/blocks.js"></script>');

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(
        array(
            '<label>ID</label>',
			'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
        )
    );


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
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(160, 'Type').'</label>',
			$naviforms->selectfield('type', $block_types_keys, $block_types_info, $item->type)
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(85, 'Date published').'</label>',
			$naviforms->datefield('date_published', $item->date_published, true),
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(90, 'Date unpublished').'</label>',
			$naviforms->datefield('date_unpublish', $item->date_unpublish, true),
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(168, 'Notes').'</label>',
			$naviforms->textarea('notes', $item->notes)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(364, 'Access').'</label>',
            $naviforms->selectfield(
                'access',
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
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(65, 'Enabled').'</label>',
		    $naviforms->checkbox('enabled', $item->enabled),
	    )
    );

	$navibars->add_tab(t(9, "Content"));

    switch($item->class)
    {
        case 'poll':
            $options = array();
            foreach($website->languages_list as $lang)
                $options[$lang] = language::name_by_code($lang);

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(63, 'Languages').'</label>',
                    $naviforms->buttonset('language_selector', $options, $website->languages_list[0], "navigate_items_select_language(this);")
                )
            );

            foreach($website->languages_list as $lang)
            {
                $navibars->add_tab_content('
                    <div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">
                ');

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(558, 'Question').'</label>',
                        $naviforms->textfield('title-'.$lang, @$item->dictionary[$lang]['title']),
                        ''
                    )
                );

                // Poll options
                $table = new naviorderedtable("poll_answers_table_".$lang);
                $table->setWidth("330px");
                $table->setHiddenInput("poll-answers-table-order-".$lang);
                $navibars->add_tab_content(
                    $naviforms->hidden("poll-answers-table-order-".$lang, "")
                );

                $table->addHeaderColumn(t(67, 'Title'), 200);
                //$table->addHeaderColumn(t(237, 'Code'), 120);
                $table->addHeaderColumn(t(352, 'Votes'), 80);
                $table->addHeaderColumn(t(35, 'Remove'), 50);

                if(!empty($item->trigger[$lang]))
                {
                    $poll_answers = $item->trigger[$lang];

                    foreach($poll_answers as $pa)
                    {
                        $uid = uniqid();
                        $table->addRow(
                            "poll-answers-table-row-".$uid,
                            array(
                                array('content' => '<input type="text" name="poll-answers-table-title-'.$lang.'['.$uid.']" value="'.$pa['title'].'" style="width: 200px;" />', 'align' => 'left'),
                                //array('content' => '<input type="text" name="poll-answers-table-code-'.$lang.'['.$uid.']" value="'.$pa['code'].'" style="width: 120px;" />', 'align' => 'left'),
                                array('content' => '<input type="text" name="poll-answers-table-votes-'.$lang.'['.$uid.']" value="'.intval($pa['votes']).'" style="width: 80px;" />', 'align' => 'left'),
                                array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" style="cursor: pointer;" onclick="navigate_blocks_poll_answers_table_row_remove(this);" />', 'align' => 'center')
                            )
                        );
                    }
                }

                $uid = uniqid();
                $table->addRow(
                    "poll-answers-table-row-model-".$lang,
                    array(
                        array('content' => '<input type="text" name="poll-answers-table-title-'.$lang.'['.$uid.']" value="" style="width: 200px;" />', 'align' => 'left'),
                        //array('content' => '<input type="text" name="poll-answers-table-code-'.$lang.'['.$uid.']" value="'.$uid.'" style="width: 120px;" />', 'align' => 'left'),
                        array('content' => '<input type="text" name="poll-answers-table-votes-'.$lang.'['.$uid.']" value="0" style="width: 80px;" />', 'align' => 'left'),
                        array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" style="cursor: pointer;" onclick="navigate_blocks_poll_answers_table_row_remove(this);" />', 'align' => 'center')
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(559, "Answers").'</label>',
                        '<div id="poll-answers-'.$lang.'">'.$table->generate().'</div>',
                        '<label>&nbsp;</label>',
                        '<button id="poll-answers-table-add-'.$lang.'" data-lang="'.$lang.'"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>'
                    )
                );

                $navibars->add_tab_content('
                    </div>
                ');
            }

            foreach($website->languages_list as $alang)
            {
                $layout->add_script('
                    $(window).on("load", function()
                    {
                        poll_answers_table_row_models["'.$alang.'"] = $("#poll-answers-table-row-model-'.$alang.'").html();
                        if($("#poll_answers_table_'.$alang.'").find("tr").not(".nodrag").length > 1)
                            $("#poll-answers-table-row-model-'.$alang.'").hide();
                        navigate_naviorderedtable_poll_answers_table_'.$alang.'_reorder();
                    });
                ');
            }

            $layout->add_script('
                var active_languages = ["'.implode('", "', array_keys($options)).'"];
                navigate_items_select_language("'.$website->languages_list[0].'");
            ');
            break;

        case 'block':
        case 'theme':
        default:
            $options = array();
            foreach($website->languages_list as $lang)
                $options[$lang] = language::name_by_code($lang);

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(63, 'Languages').'</label>',
                    $naviforms->buttonset('language_selector', $options, $website->languages_list[0], "navigate_items_select_language(this);")
                )
            );

            foreach($website->languages_list as $lang)
            {
                $navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">');

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(67, 'Title').'</label>',
                        $naviforms->textfield('title-'.$lang, @$item->dictionary[$lang]['title']),
                        ''
                    )
                );

                $navibars->add_tab_content_row(array(
                        '<label>'.t(160, 'Type').'</label>',
                        $naviforms->selectfield('trigger-type-'.$lang,
                            array(
                                0 => '',
                                1 => 'title',
                                2 => 'image',
                                3 => 'rollover',
                                4 => 'video',
                                5 => 'flash',
                                6 => 'html',
                                7 => 'links',
                                8 => 'content'
                            ),
                            array(
                                0 => t(181, 'Hidden'),
                                1 => t(67, 'Title'),
                                2 => t(157, 'Image'),
                                3 => t(182, 'Rollover'),
                                4 => t(272, 'Video'),
                                5 => 'Flash',
                                6 => 'HTML',
                                7 => t(549, 'Links'),
                                8 => t(9, 'Content')
                            ),
                            $item->trigger['trigger-type'][$lang],
                            "navigate_blocks_trigger_change('".$lang."', this);"
                        )
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(157, 'Image').'</label>',
                        $naviforms->dropbox('trigger-image-'.$lang, @$item->trigger['trigger-image'][$lang], 'image')
                    )
                );


                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(182, 'Rollover').' (off / on)</label>',
                        $naviforms->dropbox('trigger-rollover-'.$lang, @$item->trigger['trigger-rollover'][$lang], 'image'),
                        $naviforms->dropbox('trigger-rollover-active-'.$lang, @$item->trigger['trigger-rollover-active'][$lang], 'image'),
                        ''
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(272, 'Video').'</label>',
                        $naviforms->dropbox('trigger-video-'.$lang, @$item->trigger['trigger-video'][$lang], 'video')
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>Flash (SWF)</label>',
                        $naviforms->dropbox('trigger-flash-'.$lang, @$item->trigger['trigger-flash'][$lang], 'flash'),
                        ''
                    )
                );

                /* links list */

                // check if navigate must show an icon selector
                $links_icons = '';
                if(!empty($theme) && !empty($theme->blocks))
                {
                    foreach($theme->blocks as $tb)
                    {
                        if($item->type == $tb->id)
                        {
                            $links_icons = @$tb->icons;
                            break;
                        }
                    }
                }

$links_icons = 'fontawesome';

                $table = new naviorderedtable("trigger_links_table_".$lang);
                $table->setWidth("600px");
                $table->setHiddenInput("trigger-links-table-order-".$lang);
                $navibars->add_tab_content( $naviforms->hidden("trigger-links-table-order-".$lang, "") );

                $table->addHeaderColumn(t(242, 'Icon'), 50);
                $table->addHeaderColumn(t(67, 'Title'), 200);
                $table->addHeaderColumn(t(197, 'Link'), 200);
                $table->addHeaderColumn('<i class="fa fa-external-link" title="'.t(324, 'New window').'"></i>', 16);
                $table->addHeaderColumn(t(35, 'Remove'), 50);


				if(empty($item->trigger['trigger-links'][$lang]['link']))
				{
					// create a default entry
					$item->trigger['trigger-links'][$lang] = array(
						'order' => '',
						'icon' => '',
						'title' => array('0' => ''),
						'link' => array('0' => '')
					);
				}

                if(!empty($item->trigger['trigger-links'][$lang]))
                {
                    $tlinks = $item->trigger['trigger-links'][$lang];

                    foreach($tlinks['link'] as $key => $link)
                    {
                        $uid = uniqid();
                        $table->addRow(
                            uniqid('trigger-links-table-row-'),
                            array(
                                empty($links_icons)? array('content' => '-', 'align' => 'center') : array('content' => '<input type="text" name="trigger-links-table-icon-'.$lang.'['.$uid.']" value="'.$tlinks['icon'][$key].'" style="width: 54px;" />', 'align' => 'left'),
                                array('content' => '<input type="text" name="trigger-links-table-title-'.$lang.'['.$uid.']" value="'.$tlinks['title'][$key].'" style="width: 200px;" />', 'align' => 'left'),
                                array('content' => '<input type="text" name="trigger-links-table-link-'.$lang.'['.$uid.']" value="'.$tlinks['link'][$key].'" style="width: 200px;" />', 'align' => 'left'),
                                array('content' => '<input type="checkbox" name="trigger-links-table-new_window-'.$lang.'['.$uid.']" value="1" '.($tlinks['new_window'][$key]=='1'? 'checked="checked"' : '').' />', 'align' => 'left'),
                                array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" style="cursor: pointer;" onclick="navigate_blocks_trigger_links_table_row_remove(this);" />', 'align' => 'center')
                            )
                        );
                    }
                }

                $uid = uniqid();
                $table->addRow(
                    "trigger-links-table-row-model-".$lang,
                    array(
                        empty($links_icons)? array('content' => '-', 'align' => 'center') : array('content' => '<input type="text" name="trigger-links-table-icon-'.$lang.'['.$uid.']" value="" style="width: 54px;" />', 'align' => 'left'),
                        array('content' => '<input type="text" name="trigger-links-table-title-'.$lang.'['.$uid.']" value="" style="width: 200px;" />', 'align' => 'left'),
                        array('content' => '<input type="text" name="trigger-links-table-link-'.$lang.'['.$uid.']" value="" style="width: 200px;" />', 'align' => 'left'),
                        array('content' => '<input type="checkbox" name="trigger-links-table-new_window-'.$lang.'['.$uid.']" value="1" />', 'align' => 'left'),
                        array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" style="cursor: pointer;" onclick="navigate_blocks_trigger_links_table_row_remove(this);" />', 'align' => 'center')
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(549, "Links").'</label>',
                        '<div id="trigger-links-'.$lang.'">'.$table->generate().'</div>',
                        '<label>&nbsp;</label>',
                        '<button id="trigger-links-table-add-'.$lang.'" data-lang="'.$lang.'"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>'
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>HTML</label>',
                        $naviforms->scriptarea('trigger-html-'.$lang, @$item->trigger['trigger-html'][$lang]),
                        ''
                    )
                );

                if(!empty($block_type_width))
                    $block_type_width .= 'px';

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(9, "Content").'</label>',
                        $naviforms->editorfield('trigger-content-'.$lang, @$item->trigger['trigger-content'][$lang], $block_type_width, $lang),
                        ''
                    )
                );


                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(172, 'Action').'</label>',
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

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(184, 'Webpage').'</label>',
                        $naviforms->autocomplete('action-web-'.$lang, @$item->action['action-web'][$lang], '?fid='.$_REQUEST['fid'].'&act=5'),
                        ''
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(82, 'File').'</label>',
                        $naviforms->dropbox('action-file-'.$lang, @$item->action['action-file'][$lang]),
                        ''
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(157, 'Image').'</label>',
                        $naviforms->dropbox('action-image-'.$lang, @$item->action['action-image'][$lang], 'image'),
                        ''
                    )
                );

	            // copy from other language
	            if(count($website->languages) > 1)
	            {
		            $block_copyfrom_titles = array();
		            $block_copyfrom_actions = array();

		            foreach($website->languages as $bcpl)
		            {
			            if($bcpl['language'] == $lang)
				            continue;

			            $block_copyfrom_titles[] = language::name_by_code($bcpl['language']);
			            $block_copyfrom_actions[] = 'javascript: navigate_blocks_copy_from_language(\''.$bcpl['language'].'\', \''.$lang.'\');';
		            }

		            $copy_from_menu = $naviforms->splitbutton(
			            'block_copyfrom_'.$lang,
			            '<img src="img/icons/silk/comment.png" align="absmiddle"> '.t(189, 'Copy from').'...',
			            $block_copyfrom_actions,
			            $block_copyfrom_titles
		            );

		            $navibars->add_tab_content_row('<label>&nbsp;</label>'.$copy_from_menu);
	            }

	            $navibars->add_tab_content('</div>');
            }

	        $layout->add_script('
				function navigate_blocks_copy_from_language(from, to)
				{
					// copy title (if destination is empty)
					if($("#title-" + to).val()=="")
						$("#title-" + to).val($("#title-" + from).val());

					// copy trigger type
					$("#trigger-type-" + to)
						.val($("#trigger-type-" + from).val())
						.trigger("change");


					// copy trigger value, depending on the trigger type
					switch($("#trigger-type-" + to).val())
					{
						case "image":
							navigate_dropbox_clone_value("trigger-image-" + from, "trigger-image-" + to);
							break;

						case "rollover":
							navigate_dropbox_clone_value("trigger-rollover-" + from, "trigger-rollover-" + to);
							navigate_dropbox_clone_value("trigger-rollover-active-" + from, "trigger-rollover-active-" + to);
							break;

						case "video":
							navigate_dropbox_clone_value("trigger-video-" + from, "trigger-video-" + to);
							break;

						case "flash":
							navigate_dropbox_clone_value("trigger-flash-" + from, "trigger-flash-" + to);
							break;

						case "html":
							// ncid: navigate codemirror instance destination
							// ncio: navigate codemirror instance destination
							for(ncid in navigate_codemirror_instances)
							{
								if($(navigate_codemirror_instances[ncid].getTextArea()).attr("id") == "trigger-html-" + to)
								{
									for(ncio in navigate_codemirror_instances)
									{
										if($(navigate_codemirror_instances[ncio].getTextArea()).attr("id") == "trigger-html-" + from)
										{
											navigate_codemirror_instances[ncid].setValue(navigate_codemirror_instances[ncio].getValue());
										}
									}
								}
							}
							$(navigate_codemirror_instances).each(function() { this.refresh(); } );
							break;

						case "links":
							// remove previous links (if any)
							$("#trigger-links-" + to).find("tr").not("#trigger-links-table-row-model-" + to).not(":first").remove();
							// copy each link in the origin language
							$("#trigger-links-" + from).find("tr").not("#trigger-links-table-row-model-" + from).not(":first").each(function()
							{
								// add a row
								$("#trigger-links-table-add-" + to).trigger("click");
								$(this).find("td").each(function(i)
								{
									if($(this).find(".select2-container").length > 0)
									{
										// select2 field

										var input_name = $("#trigger_links_table_" + to).find("tr:visible:last").find("td").eq(i).find("input.select2-offscreen:last").attr("name");
										var input_value = $(this).find("input.select2-offscreen:last").val();

										if(input_name)
										{
											$("input[name=\""+input_name+"\"]").select2("val", input_value);
											$("#trigger_links_table_" + to).find("tr:visible:last").find("td").eq(i).find("span:first").html("<i class=\"fa fa-fw fa-2x "+input_value+"\"></i>");
										}
									}
									else
									{
										// standard input or checkbox field
										$("#trigger_links_table_" + to).find("tr:visible:last").find("td").eq(i).find("input").val($(this).find("input").val());
										if($(this).find("input").attr("checked"))
											$("#trigger_links_table_" + to).find("tr:visible:last").find("td").eq(i).find("input").attr("checked", "checked");
									}
								});
							});
							break;

						case "content":
							tinyMCE.getInstanceById("trigger-content-" + to).setContent(
								tinyMCE.getInstanceById("trigger-content-" + from).getContent()
							);
							break;

						case "title":
						case "":
						default:
							// nothing to do
							break;
					}

					// copy action type
					$("#action-type-" + to)
						.val($("#action-type-" + from).val())
						.trigger("change");

					// copy action value
					switch($("#action-type-" + to).val())
					{
						case "web":
						case "web-n":
							$("#action-web-" + to).val($("#action-web-" + from).val());
							break;

						case "file":
							navigate_dropbox_clone_value("action-file-" + from, "action-file-" + to);
							break;

						case "image":
							navigate_dropbox_clone_value("action-image-" + from, "action-image-" + to);
							break;

						case "":
						default:
							// nothing to do
							break;
					}
				}
			');

            // right now, only fontawesome icon set is supported
            $fontawesome_classes = '';
            if($links_icons == 'fontawesome')
            {
                $fontawesome_classes = block::fontawesome_list();
                array_unshift($fontawesome_classes, '');
                $fontawesome_classes = array_map(
                    function($v)
                    {
                        $x = new stdClass();
                        $x->id = $v;
                        if(!empty($v))
                            $x->text = substr($v, 3);
                        else
                            $x->text = '';
                        return $x;
                    },
                    $fontawesome_classes
                );
            }

            $layout->add_script('
                var active_languages = ["'.implode('", "', array_keys($options)).'"];
                navigate_items_select_language("'.$website->languages_list[0].'");
                navigate_fontawesome_classes = '.json_encode($fontawesome_classes).';
            ');

            foreach($website->languages_list as $alang)
            {
                $layout->add_script('
					$(window).on("load", function()
					{
						$("#trigger-type-'.$alang.'").select2("val", "'.$item->trigger['trigger-type'][$alang].'");
						$("#action-type-'.$alang.'").select2("val", "'.$item->action['action-type'][$alang].'");
						navigate_blocks_trigger_change("'.$alang.'", $("<input type=\"hidden\" value=\"'.$item->trigger['trigger-type'][$alang].'\" />"));

						links_table_row_models["'.$alang.'"] = $("#trigger-links-table-row-model-'.$alang.'").html();
						if($("#trigger_links_table_'.$alang.'").find("tr").not(".nodrag").length > 1)
							$("#trigger-links-table-row-model-'.$alang.'").hide();

						// prepare select2 to select icons
						if('.($links_icons=='fontawesome'? 'true' : 'false').')
						{
							$("[id^=trigger_links_table_").find("tr").each(function()
							{
								// do not apply select2 to head row
								if(!$(this).find("input")) return;

								// do not apply select2 to model row
								if($(this).attr("id") && ($(this).attr("id")).indexOf("table-row-model") > 0) return;
								navigate_blocks_trigger_links_table_icon_selector(this);

								// display icon value on load
								if($(this).find("td:first").find("input:last").val()!="")
								{
									$(this).find("a.select2-choice:first")
										.find("span.select2-chosen:first")
										.html("<i class=\"fa fa-fw fa-2x "+$(this).find("td:first").find("input:last").val()+"\"></i>");
								}
							});
						}
					});
            	');
            }
	        break;
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

    $default_value = 1;
    if(!empty($item->categories))
        $default_value = 0;
    else if(!empty($item->exclusions))
        $default_value = 2;

    $navibars->add_tab_content_row(array(
        '<label>'.t(330, 'Categories').'</label>',
        $naviforms->buttonset(
            'all_categories',
            array(
                '1' => t(396, 'All categories'),
                '0' => t(405, 'Selection'),
                '2' => t(552, 'Exclusions')
            ),
            $default_value
        )
    ));
/*
	$navibars->add_tab_content_row(array(	'<label>'.t(396, 'All categories').'</label>',
											$naviforms->checkbox('all_categories', empty($item->categories)),
										));	
*/
	$hierarchy = structure::hierarchy(0);
	$categories_list = structure::hierarchyList($hierarchy, $item->categories);
	$exclusions_list = structure::hierarchyList($hierarchy, $item->exclusions);

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
		    '<div class="category_tree" id="category-tree-parent"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.$categories_list.'</div>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
		    '<div class="category_tree" id="exclusions-tree-parent"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.$exclusions_list.'</div>'
        )
    );
										
	if(!is_array($item->categories))
		$item->categories = array();

    if(!is_array($item->exclusions))
        $item->exclusions = array();

    $navibars->add_tab_content($naviforms->hidden('categories', implode(',', $item->categories)));

    $navibars->add_tab_content($naviforms->hidden('exclusions', implode(',', $item->exclusions)));
										
	$layout->add_script('

	    function navigate_blocks_all_categories_switch()
	    {
	        $("#category-tree-parent").parent().hide();
	        $("#exclusions-tree-parent").parent().hide();

            if($("#all_categories_2").is(":checked"))
	            $("#exclusions-tree-parent").parent().show();
	         else if($("#all_categories_0").is(":checked"))
	            $("#category-tree-parent").parent().show();
	    }

	    $("#all_categories_0,#all_categories_1,#all_categories_2").on("click", navigate_blocks_all_categories_switch);

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

		$("#exclusions-tree-parent ul:first").kvaTree(
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

				$("#exclusions-tree-parent span.active").parent().each(function()
				{
					categories.push($(this).attr("value"));
				});

				if(categories.length > 0)
					$("#exclusions").val(categories);
				else
					$("#exclusions").val("");
			}
		});

		$("#exclusions-tree-parent li").find("span:first").css("cursor", "pointer");

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
		
		$navibars->add_tab_content_row(
            array(
                '<label>'.t(23, 'Blocks').'</label>',
				'<div>'.$table->generate().'</div>',
				'<div class="subcomment"><img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'</div>',
                '<div class="subcomment"><span class="ui-icon ui-icon-lightbulb" style=" float: left; margin-right: 4px; "></span> '.t(395, '"Fixed" assigns a static position when the order is random').'</div>'
            )
        );
											
	}
	return $navibars->generate();
}

function blocks_types_list()
{
	global $user;
	global $DB;
	global $website;
    global $events;
	
	$navibars = new navibars();
	$navitable = new navitable('blocks_types_list');
	
	$navibars->title(t(23, 'Blocks').' / '.t(167, 'Types'));

    $group_blocks_links = array();
    list($bg_rs, $bg_total) = block_group::paginated_list(0, 10, 'title', 'desc');

    if($bg_total > 0 && $bg_total <= 10)
    {
        foreach($bg_rs as $bg)
            $group_blocks_links[] = '<a href="?fid='.$_REQUEST['fid'].'&act=block_group_edit&id='.$bg['id'].'"><i class="fa fa-fw fa-caret-right"></i> '.$bg['title'].'</a>';

        $events->add_actions(
            'blocks',
            array(
                'item' => null,
                'navibars' => &$navibars
            ),
            $group_blocks_links,
            '<a class="content-actions-submenu-trigger" href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_link.png"> '.t(506, 'Groups').' &#9662;</a>'
        );
    }

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>',
            (!empty($group_blocks_links)? '' : '<a href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_link.png"> '.t(506, 'Groups').'</a>')
        )
    );

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
    global $events;
	
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
		$navibars->add_actions(
            array(
                '<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
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
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=block_type_delete&id='.$item['id'].'";
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}

    $group_blocks_links = array();
    list($bg_rs, $bg_total) = block_group::paginated_list(0, 10, 'title', 'desc');

    if($bg_total > 0 && $bg_total <= 10)
    {
        foreach($bg_rs as $bg)
            $group_blocks_links[] = '<a href="?fid='.$_REQUEST['fid'].'&act=block_group_edit&id='.$bg['id'].'"><i class="fa fa-fw fa-caret-right"></i> '.$bg['title'].'</a>';

        $events->add_actions(
            'blocks',
            array(
                'item' => null,
                'navibars' => &$navibars
            ),
            $group_blocks_links,
            '<a class="content-actions-submenu-trigger" href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_link.png"> '.t(506, 'Groups').' &#9662;</a>'
        );
    }

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>',
            (!empty($group_blocks_links)? '' : '<a href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_link.png"> '.t(506, 'Groups').'</a>')
        )
    );


	$navibars->add_actions(
        array(
            (!empty($item->id)? '<a href="?fid='.$_REQUEST['fid'].'&act=82"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item['id']));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item['id'])? $item['id'] : t(52, '(new)')).'</span>' ));

    // TODO: in Navigate 1.9 add several block types (p.e. Ad (Google adsense, ...), Map (Bing, Yahoo, Google, ...))
    $block_modes = block::modes();
    $navibars->add_tab_content_row(
        array(
            '<label>'.t(491, 'Class').'</label>',
   			$naviforms->selectfield('type',
                array_keys($block_modes),
                array_values($block_modes),
   			    $item['type'],
   			    '',
   			    false
            )
   		)
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(67, 'Title').'</label>',
   			$naviforms->textfield('title', $item['title'])
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(237, 'Code').'</label>',
    		$naviforms->textfield('code', $item['code']),
            '<div class="subcomment">
            <span style=" float: left; margin-left: -3px; " class="ui-icon ui-icon-lightbulb"></span>'.
            t(436, 'Used as a class in HTML elements').
            '</div>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(168, 'Notes').'</label>',
			$naviforms->textarea('notes', $item['notes'])
        )
    );

    $navibars->add_tab(t(145, "Size").' & '.t(438, "Order"));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(155, 'Width').'<sup>*</sup></label>',
   			$naviforms->textfield('width', $item['width']),
   			'px'
        )
    );

   	$navibars->add_tab_content_row(
        array(
            '<label>'.t(156, 'Height').'<sup>*</sup></label>',
   			$naviforms->textfield('height', $item['height']),
   			'px'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<div class="subcomment italic">* '.t(169, 'You can leave blank a field to not limit the size').'</div>'
   		)
    );

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
			'<a id="wikipedia_web_banner_entry" class="italic" href="http://en.wikipedia.org/wiki/Web_banner" target="_blank"><span class="ui-icon ui-icon-info" style=" float: left;"></span> '.t(393, 'Standard banner sizes').' (Wikipedia)</a>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(404, 'Order by').'</label>',
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
        )
    );

   	$navibars->add_tab_content_row(
        array(
            '<label>'.t(397, 'Maximum').'</label>',
   			$naviforms->textfield('maximum', $item['maximum']),
   			'<div class="subcomment"><span class="ui-icon ui-icon-lightbulb" style=" float: left; margin-left: -3px; "></span> '.t(400, 'Enter 0 to display all').'</div>'
        )
    );

    $layout->add_script('
        function navigate_blocks_code_generate(el)
        {
            if($("#code").val()!="")
                return;
            var title = $("#title").val();
			title = title.replace(/([\'"?:\+\&!¿#\\\\])/g, "");
			title = title.replace(/[.\s]+/g, "_");
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

function block_groups_list()
{
    global $user;
    global $DB;
    global $website;

    $navibars = new navibars();
    $navitable = new navitable('block_groups_list');

    $navibars->title(t(23, 'Blocks').' / '.t(506, 'Groups'));

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>',
            '<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_edit.png"> '.t(167, 'Types').'</a>'
        )
    );

    $navibars->add_actions(	array(
        '<a href="?fid='.$_REQUEST['fid'].'&act=block_group_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
        '<a href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
    ));

    $navitable->setURL('?fid='.$_REQUEST['fid'].'&act=block_groups_json');
    $navitable->sortBy('id');
    $navitable->setDataIndex('id');
    $navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=block_group_edit&id=');

    $navitable->addCol("ID", 'id', "80", "true", "left");
    $navitable->addCol(t(237, 'Code'), 'code', "120", "true", "left");
    $navitable->addCol(t(67, 'Title'), 'title', "200", "true", "left");
    $navitable->addCol(t(23, 'Blocks'), 'blocks', "80", "true", "left");

    $navibars->add_content($navitable->generate());

    return $navibars->generate();
}

function block_group_form($item)
{
    global $DB;
    global $website;
    global $layout;
    global $theme;

    $navibars = new navibars();
    $naviforms = new naviforms();

    if(empty($item->id))
        $navibars->title(t(23, 'Blocks').' / '.t(506, 'Groups').' / '.t(38, 'Create'));
    else
        $navibars->title(t(23, 'Blocks').' / '.t(506, 'Groups').' / '.t(170, 'Edit').' ['.$item->id.']');

    if(empty($item->id))
    {
        $navibars->add_actions(
            array(	'<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
        );
    }
    else
    {
        $navibars->add_actions(
            array(
                '<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
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
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=block_group_delete&id='.$item->id.'";
								}
							}
						});';
        $delete_html[] = '}';
        $delete_html[] = '</script>';

        $navibars->add_content(implode("\n", $delete_html));
    }

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>',
            '<a href="?fid='.$_REQUEST['fid'].'&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_edit.png"> '.t(167, 'Types').'</a>'
        )
    );

    $navibars->add_actions(
        array(
        (!empty($item->id)? '<a href="?fid='.$_REQUEST['fid'].'&act=block_group_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
        '<a href="?fid='.$_REQUEST['fid'].'&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
    ));

    $navibars->form();

    $navibars->add_tab(t(43, "Main"));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->hidden('id', $item->id));

    $navibars->add_tab_content_row(array(
        '<label>ID</label>',
        '<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' )
    );

    $navibars->add_tab_content_row(array(
        '<label>'.t(67, 'Title').'</label>',
        $naviforms->textfield('title', $item->title)
    ));

    $blgroups = array();
    for($blg=0; $blg < count($theme->block_groups); $blg++)
    {
        $blgroups[$theme->block_groups[$blg]->id] = '';
        if(!empty($theme->block_groups[$blg]->description))
            $blgroups[$theme->block_groups[$blg]->id] = $theme->t($theme->block_groups[$blg]->description);
    }

    if(!in_array($item->code, $blgroups))
        $blgroups[$item->code] = $item->code;

    $navibars->add_tab_content_row(array(
        '<label>'.t(237, 'Code').'</label>',
        $naviforms->selectfield(
            'code',
            array_keys($blgroups),
            array_keys($blgroups),
            $item->code,
            NULL,
            NULL,
            array_values($blgroups),
            "",
            true,
            true
        )
    ));

    $navibars->add_tab_content_row(array(
        '<label>'.t(168, 'Notes').'</label>',
        $naviforms->textarea('notes', $item->notes)
    ));

    $navibars->add_tab(t(23, "Blocks"));

    // blocks by ID
    // block types

    if(!is_array($item->blocks))
        $item->blocks = array();

    $navibars->add_tab_content($naviforms->hidden('blocks_group_selection', implode('#', $item->blocks)));

    $table = new naviorderedtable("blocks_group_table");
    $table->setWidth("800px");
    $table->setHiddenInput("blocks-order");
    $table->setReorderCallback("blocks_selection_update");

    $navibars->add_tab_content( $naviforms->hidden('blocks-order', "") );

    $table->addHeaderColumn('ID', 200);
    $table->addHeaderColumn(t(160, 'Type'), 250);
    $table->addHeaderColumn(t(67, 'Title'), 300);
    $table->addHeaderColumn(t(170, 'Edit'), 50);
    $table->addHeaderColumn(t(35, 'Remove'), 50);

    $block_types = block::types();
    $lang = $website->languages_published[0];

    for($p=0; $p < count($item->blocks); $p++)
    {
        unset($block);
        if(is_numeric($item->blocks[$p]))
        {
            $block = new block();
            $block->load($item->blocks[$p]);

            if(empty($block) || empty($block->type))
                continue;

            $table->addRow($p, array(
                array('content' => '<span>'.$item->blocks[$p].'</span>', 'align' => 'left'),
                array('content' => '<span>'.$theme->t($block->type).'</span>', 'align' => 'left'),
                array('content' => '<span>'.$block->dictionary[$lang]['title'].'</span>', 'align' => 'left'),
                array('content' => '<a href="?fid=blocks&act=edit&id='.$block->id.'"><img src="'.NAVIGATE_URL.'/img/icons/silk/pencil.png" /></a>', 'align' => 'center'),
                array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_blocks_selection_remove(this);" />', 'align' => 'center')
            ));

        }
        else if(!empty($item->blocks[$p]))
        {
            // maybe a block group type?
            if(is_array($theme->block_groups))
            {
                foreach($theme->block_groups as $key => $bg)
                {
                    for($i=0; $i < count($bg->blocks); $i++)
                    {
                        if($bg->blocks[$i]->id==$item->blocks[$p])
                        {
                            $block = array(
                                'code' => $bg->blocks[$i]->id,
                                'type' => $bg->blocks[$i]->id,
                                'title' => $theme->t($bg->blocks[$i]->title),
                                'description'  => $theme->t($bg->blocks[$i]->description),
                                'properties'  => $bg->blocks[$i]->properties,
                                'block_group' => $bg->id
                            );
                            break;
                        }
                    }
                }
            }

            // maybe a block type?
            if(empty($block))
            {
                for($bt=0; $bt < count($block_types); $bt++)
                {
                    if($block_types[$bt]['id']==$item->blocks[$p])
                    {
                        $block = $block_types[$bt];
                        break;
                    }
                }
            }

            $table->addRow($p, array(
                array('content' => '<span>'.$block['code'].'</span>', 'align' => 'left'),
                array('content' => '<span>'.$theme->t($block['type']).'</span>', 'align' => 'left'),
                array('content' => '<span title="'.$block['description'].'">'.$block['title'].'</span>', 'align' => 'left'),
                array('content' => (empty($block['properties'])? '':'<a href="#" data-block-group="'.$block['block_group'].'" data-block-group-block="'.$block['code'].'" data-block-group-action="settings"><img src="'.NAVIGATE_URL.'/img/icons/silk/cog.png" /></a>'), 'align' => 'center'),
                array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_blocks_selection_remove(this);" />', 'align' => 'center')
            ));
        }
    }

    $navibars->add_tab_content_row(array(
        '<label>'.t(405, 'Selection').'</label>',
        '<div>'.$table->generate().'</div>',
        '<div class="subcomment">
            <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').
        '</div>'
    ));

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;</label>',
        '<button id="block-selection-add-block"><i class="fa fa-plus-square-o"></i> '.t(437, 'Block').'</button>',
        '<button id="block-selection-add-block_type"><i class="fa fa-plus-square"></i> '.t(543, 'Block type').'</button>',
        '<button id="block-selection-add-block_from_group"><i class="fa fa-puzzle-piece"></i> '.t(556, 'Block from group').' ['.$theme->t($item->code).']</button>'
        )
    );

    // **** ADD BLOCK FROM GROUP dialog ****

    $block_types_from_group_assoc = array();
    $block_types_from_group_titles = array();
    foreach($theme->block_groups as $key => $bg)
    {
        for($i=0; $i < count($bg->blocks); $i++)
        {
            $block_types_from_group_titles[$bg->blocks[$i]->id] = $theme->t($bg->blocks[$i]->title);
            $block_types_from_group_assoc[$bg->blocks[$i]->id] = (array)$bg->blocks[$i];
        }
    }

    $layout->add_script('var blocks_selection_block_types_from_group = '.json_encode($block_types_from_group_assoc));

    $layout->add_content('
        <div id="block-selection-add-block_from_group-dialog" style="display: none;">
            <form action="#" method="post" onsubmit="return false;">
                '.$naviforms->selectfield('block-selection-add-block_from_group-dialog-type', array_keys($block_types_from_group_titles), array_values($block_types_from_group_titles)).'
            </form>
        </div>
    ');

    $layout->add_script('
        $("#block-selection-add-block_from_group").on("click", function()
        {
            $("#block-selection-add-block_from_group-dialog").dialog(
            {
                title: "'.t(472, 'Add').': '.t(556, 'Block from group').' ['.$theme->t($item->code).']",
                modal: true,
                width: 430,
                height: 130,
                buttons:
                {
                    "'.t(190, 'Ok').'": function()
                    {
                        var bts = $("#block-selection-add-block_from_group-dialog-type").val();

                        if(!bts) return;

                        var settings_column = "";
                        if(blocks_selection_block_types_from_group[bts].properties)
                            settings_column = \'<a href="#" data-block-group="'.$item->code.'" data-block-group-block="\'+bts+\'" data-block-group-action="settings"><img src="'.NAVIGATE_URL.'/img/icons/silk/cog.png" /></a>\';

                        var tr = \'<tr id="\'+(new Date().getTime())+\'">\';
                        tr += \'<td>\'+bts+\'</td>\';
                        tr += \'<td>\'+blocks_selection_block_types_from_group[bts].id+\'</td>\';
                        tr += \'<td>\'+blocks_selection_block_types_from_group[bts].title+\'</td>\';
                        tr += \'<td align="center">\'+settings_column+\'</td>\';
                        tr += \'<td align="center"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_blocks_selection_remove(this);" style="cursor:pointer;" /></td>\';
                        tr += \'</tr>\';

                        $("#blocks_group_table").find("tbody:last").append(tr);
                        $("#blocks_group_table").tableDnD(
                        {
                            onDrop: function(table, row)
                            {
                                navigate_naviorderedtable_blocks_group_table_reorder();
                            }
                        });

                        // force a table refresh
                        navigate_naviorderedtable_blocks_group_table_reorder();

                        $("#block-selection-add-block_from_group-dialog").dialog("close");
                    },
                    "'.t(58, 'Cancel').'": function()
                    {
                        $("#block-selection-add-block_from_group-dialog").dialog("close");
                    }
                }
            });

            return false;
        });
    ');


    // **** ADD BLOCK TYPE dialog ****

    $block_types_assoc = array();
    $block_types_titles = array();
    for($i=0; $i < count($block_types); $i++)
    {
        $block_types_titles[$block_types[$i]['code']] = $block_types[$i]['title'];
        $block_types_assoc[$block_types[$i]['code']] = $block_types[$i];
    }

    $layout->add_script('var blocks_selection_block_types = '.json_encode($block_types_assoc));

    $layout->add_content('
        <div id="block-selection-add-block_type-dialog" style="display: none;">
            <form action="#" method="post" onsubmit="return false;">
                '.$naviforms->selectfield('block-selection-add-block_type-dialog-type', array_keys($block_types_titles), array_values($block_types_titles)).'
            </form>
        </div>
    ');

    $layout->add_script('
        $("#block-selection-add-block_type").on("click", function()
        {
            $("#block-selection-add-block_type-dialog").dialog(
            {
                title: "'.t(472, 'Add').': '.t(543, 'Block type').'",
                modal: true,
                width: 430,
                height: 130,
                buttons:
                {
                    "'.t(190, 'Ok').'": function()
                    {
                        var bts = $("#block-selection-add-block_type-dialog-type").val();

                        var tr = \'<tr id="\'+(new Date().getTime())+\'">\';
                        tr += \'<td>\'+bts+\'</td>\';
                        tr += \'<td>\'+blocks_selection_block_types[bts].type+\'</td>\';
                        tr += \'<td>\'+blocks_selection_block_types[bts].title+\'</td>\';
                        tr += \'<td></td>\';
                        tr += \'<td align="center"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_blocks_selection_remove(this);" style="cursor:pointer;" /></td>\';
                        tr += \'</tr>\';

                        $("#blocks_group_table").find("tbody:last").append(tr);
                        $("#blocks_group_table").tableDnD(
                        {
                            onDrop: function(table, row)
                            {
                                navigate_naviorderedtable_blocks_group_table_reorder();
                            }
                        });

                        // force a table refresh
                        navigate_naviorderedtable_blocks_group_table_reorder();

                        $("#block-selection-add-block_type-dialog").dialog("close");
                    },
                    "'.t(58, 'Cancel').'": function()
                    {
                        $("#block-selection-add-block_type-dialog").dialog("close");
                    }
                }
            });

            return false;
        });
    ');

    // **** ADD specific BLOCK dialog ****

    $sql = '
         SELECT b.type, b.id, d.text as title
           FROM nv_blocks b
      LEFT JOIN nv_webdictionary d
                 ON b.id = d.node_id
                AND d.node_type = "block"
                AND d.subtype = "title"
                AND d.lang = "'.$website->languages_list[0].'"
                AND d.website = '.$website->id.'
          WHERE b.website = '.$website->id.'
       ORDER BY b.id DESC';

    $DB->query($sql);
    $blocks = $DB->result();

    $block_elements = array();
    $block_elements_titles = array();
    for($i=0; $i < count($blocks); $i++)
    {
        $block_elements[$blocks[$i]->id] = $blocks[$i];
        $block_elements_titles[$blocks[$i]->id] = $blocks[$i]->title.' ('.$blocks[$i]->type.')';
    }

    $layout->add_script('var blocks_selection_block_elements = '.json_encode($block_elements));

    $layout->add_content('
        <div id="block-selection-add-block-dialog" style="display: none;">
            <form action="#" method="post" onsubmit="return false;">
                '.$naviforms->selectfield('block-selection-add-block-dialog-type', array_keys($block_elements_titles), array_values($block_elements_titles)).'
            </form>
        </div>
    ');

    $layout->add_script('
        $("#block-selection-add-block").on("click", function()
        {
            $("#block-selection-add-block-dialog").dialog(
            {
                title: "'.t(472, 'Add').': '.t(437, 'Block').'",
                modal: true,
                width: 430,
                height: 130,
                buttons:
                {
                    "'.t(190, 'Ok').'": function()
                    {
                        var bs = $("#block-selection-add-block-dialog-type").val();

                        var tr = \'<tr id="\'+(new Date().getTime())+\'">\';
                        tr += \'<td>\'+bs+\'</td>\';
                        tr += \'<td>\'+blocks_selection_block_elements[bs].type+\'</td>\';
                        tr += \'<td>\'+blocks_selection_block_elements[bs].title+\'</td>\';
                        tr += \'<td style="text-align: center;"><a href="?fid=blocks&act=edit&id=\'+blocks_selection_block_elements[bs].id+\'"><img src="'.NAVIGATE_URL.'/img/icons/silk/pencil.png" /></a></td>\';
                        tr += \'<td align="center"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_blocks_selection_remove(this);" style="cursor:pointer;" /></td>\';
                        tr += \'</tr>\';

                        $("#blocks_group_table").find("tbody:last").append(tr);
                        $("#blocks_group_table").tableDnD(
                        {
                            onDrop: function(table, row)
                            {
                                navigate_naviorderedtable_blocks_group_table_reorder();
                            }
                        });

                        // force a table refresh
                        navigate_naviorderedtable_blocks_group_table_reorder();

                        $("#block-selection-add-block-dialog").dialog("close");
                    },
                    "'.t(58, 'Cancel').'": function()
                    {
                        $("#block-selection-add-block-dialog").dialog("close");
                    }
                }
            });

            return false;
        });
    ');

    $layout->add_script('
        function blocks_selection_update()
        {
            $("#blocks_group_selection").val("");
            var blocks_id_ordered = [];
            var blocks_ts_ordered = $("#blocks-order").val().split("#");
            for(bto in blocks_ts_ordered)
            {
                if(!blocks_ts_ordered[bto])
                    continue;
                var id = $("tr#"+blocks_ts_ordered[bto]).find("td:first").text();
                if(id && id!="")
                {
                    blocks_id_ordered.push(id);
                    $("#blocks_group_selection").val(blocks_id_ordered.join(","));
                }
            }
        }

        function navigate_blocks_selection_remove(el)
        {
            $(el).parent().parent().remove();
            navigate_naviorderedtable_blocks_group_table_reorder();
            blocks_selection_update();
        }

        // update table result onload
        navigate_naviorderedtable_blocks_group_table_reorder();
        blocks_selection_update();
    ');

    $navibars->add_content('<script type="text/javascript" src="lib/packages/blocks/blocks.js"></script>');

    return $navibars->generate();
}

function block_group_block_options($block_group, $code, $status)
{
    global $layout;
    global $website;
    global $theme;

    $block = block::block_group_block($block_group, $code);
    $properties = $block->properties;

    if(empty($properties))
        return;

    $layout = null;
    $layout = new layout('navigate');

    if($status!==null)
    {
        if($status)
            $layout->navigate_notification(t(53, "Data saved successfully."), false);
        else
            $layout->navigate_notification(t(56, "Unexpected error"), true, true);
    }

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(556, 'Block from group').' ['.$theme->t($block_group).']');

    $layout->navigate_media_browser();	// we can use media browser in this function

    $navibars->add_actions(
        array(	'<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	)
    );

    $navibars->add_actions(
        array(	'<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
    );

    $navibars->form();

    $navibars->add_tab(t(200, 'Options'));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));

    // show a language selector (only if it's a multi language website)
    if(count($website->languages) > 1)
    {
        $website_languages_selector = $website->languages();
        $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(63, 'Languages').'</label>',
                $naviforms->buttonset('language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
            )
        );
    }

    $properties_values = property::load_properties($code, $block_group, 'block_group_block', $code);

    foreach($properties as $option)
    {
        $property = new property();
        $property_value = '';

        foreach($properties_values as $pv)
        {
            if($pv->id == $option->id)
                $property_value = $pv->value;
        }

        $property->load_from_object($option, $property_value, $theme);

        if($property->type == 'tab')
        {
            $navibars->add_tab($property->name);
            if(count($website->languages) > 1)
            {
                $website_languages_selector = $website->languages();
                $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(63, 'Languages').'</label>',
                        $naviforms->buttonset('language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
                    )
                );
            }
        }

        $navibars->add_tab_content(navigate_property_layout_field($property));
    }

    $layout->add_content('<div id="navigate-content" class="navigate-content ui-corner-all">'.$navibars->generate().'</div>');
    $layout->navigate_additional_scripts();
    $layout->add_script('
        $("html").css("background", "transparent");
    ');

    $out = $layout->generate();

    return $out;
}

?>