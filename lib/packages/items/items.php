<?php
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
		
	$out = '';
	$item = new item();
			
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
							$_REQUEST['searchField'] = 'i.id';
							break;
						case 'title':
							$_REQUEST['searchField'] = 'd.text';
							break;
						case 'language':
							$_REQUEST['searchField'] = 'd.lang';
							break;							
						case 'category':
							$_REQUEST['searchField'] = 'i.category';						
							break;
						case 'dates':
							$_REQUEST['searchField'] = 'i.date_published';
							break;
						case 'permission':
							$_REQUEST['searchField'] = 'i.permission';
							break;
						default:
					}
								
					if($_REQUEST['sidx']=='dates')
						$_REQUEST['sidx'] = 'i.date_published';
				
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = ' i.website = '.$website->id;
					
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $item->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
						{
							$filters = json_decode($_REQUEST['filters']);
							for($r=0; $r < count($filters->rules); $r++)
							{
								switch($filters->rules[$r]->field)
								{
									case 'id':
										$filters->rules[$r]->field = 'i.id';
										break;
									case 'title':
										$filters->rules[$r]->field = 'd.text';
										break;
									case 'language':
										$filters->rules[$r]->field = 'd.lang';
										break;							
									case 'category':
										$filters->rules[$r]->field = 'i.category';						
										break;
									case 'dates':
										$filters->rules[$r]->field = 'i.date_published';
										break;
									case 'permission':
										$filters->rules[$r]->field = 'i.permission';
										break;
									default:
								}								
							}
							$where .= navitable::jqgridsearch(json_encode($filters));
						}
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}
										
					$sql = ' SELECT SQL_CALC_FOUND_ROWS i.*, d.text as title, d.lang as language,
					                                    u.username as author_username, COUNT(c.id) as comments
							   FROM nv_items i
						  LEFT JOIN nv_comments c
						  			 ON i.id = c.item
								    AND c.website = '.$website->id.'
						  LEFT JOIN nv_webdictionary d
						  		 	 ON i.id = d.node_id
								 	AND d.node_type = "item"
									AND d.subtype = "title"
									AND d.lang = "'.$website->languages_list[0].'"
									AND d.website = '.$website->id.'
						  LEFT JOIN nv_users u
						  			 ON u.id = i.author
							  WHERE '.$where.'	
						   GROUP BY i.id 
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;	
							 				
					if(!$DB->query($sql, 'array'))
					{
						throw new Exception($DB->get_last_error());	
					}
					
					$dataset = $DB->result();	
					$total = $DB->foundRows();

					$dataset = grid_notes::summary($dataset, 'item', 'id');
														
					$access = array(		0 => '<img src="img/icons/silk/page_white_go.png" align="absmiddle" title="'.t(254, 'Everybody').'" />',
											1 => '<img src="img/icons/silk/lock.png" align="absmiddle" title="'.t(361, 'Web users only').'" />',
											2 => '<img src="img/icons/silk/user_gray.png" align="absmiddle" title="'.t(363, 'Users who have not yet signed up or signed in').'" />',
											3 => '<img src="img/icons/silk/group_key.png" align="absmiddle" title="'.t(512, "Selected web user groups").'" />'
										);
										
					$permissions = array(	0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(69, 'Published'),
											1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(70, 'Private'),
											2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(81, 'Hidden')
										);												
					
					// we need to format the values and retrieve the needed strings from the dictionary
					$out = array();								
					for($i=0; $i < count($dataset); $i++)
					{
						if(empty($dataset[$i])) continue;
						
						if(empty($dataset[$i]['date_published'])) 
							$dataset[$i]['date_published'] = '&infin;';
						else
							$dataset[$i]['date_published'] = core_ts2date($dataset[$i]['date_published'], false);
							
						if(empty($dataset[$i]['date_unpublish'])) 
							$dataset[$i]['date_unpublish'] = '&infin;';	
						else
							$dataset[$i]['date_unpublish'] = core_ts2date($dataset[$i]['date_unpublish'], false);
						
						// the following should be optimized (cached)
						if($dataset[$i]['category'] > 0)
							$dataset[$i]['category'] = $DB->query_single('text', 'nv_webdictionary', 
																		 ' 	node_type = "structure" AND
																		 	node_id = "'.$dataset[$i]['category'].'" AND 
																			subtype = "title" AND
																			website = '.$website->id.' AND
																			lang = "'.$website->languages_list[0].'"');
												
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1 	=> $dataset[$i]['title'],
							2 	=> '<img src="img/icons/silk/comments.png" align="absmiddle" width="12px" height="12px" /> '.
									'<span style="font-size: 90%;">'.$dataset[$i]['comments'].'</span>'.
								    '&nbsp;&nbsp;'.
								    '<img src="img/icons/silk/star.png" align="absmiddle" width="12px" height="12px" /> '.
								    '<span style="font-size: 90%;">'.$dataset[$i]['score'].' ('.$dataset[$i]['votes'].')</span>',
							3	=> (($dataset[$i]['association']=='free')? '[ '.strtolower(t(100, 'Free')).' ]' : $dataset[$i]['category']),
							4	=> $dataset[$i]['author_username'],
							5	=> $dataset[$i]['date_published'].' - '.$dataset[$i]['date_unpublish'],
							6	=> $access[$dataset[$i]['access']].' '.$permissions[$dataset[$i]['permission']],
							7 	=> $dataset[$i]['_grid_notes_html']
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);
					break;
			}
			
			core_terminate();
			break;
		
		case 'load':
        case 'create':
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
					property::save_properties_from_post('item', $item->id);

                    if(!empty($_REQUEST['items-order']))
                        item::reorder($_REQUEST['items-order']);

					$layout->navigate_notification(t(53, "Data saved successfully."), false);
					$item->load($item->id);
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
				
				users_log::action($_REQUEST['fid'], $item->id, 'save', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
			}
			else
				users_log::action($_REQUEST['fid'], $item->id, 'load', $item->dictionary[$website->languages_list[0]]['title']);
		
			$out = items_form($item);
			break;	

        case 'delete':
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{			
				$item->load(intval($_REQUEST['id']));	
				
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = items_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = items_form($item);
				}
				
				users_log::action($_REQUEST['fid'], $item->id, 'remove', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
			}
			break;

        case 'duplicate':
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));

                $properties = property::load_properties_associative('item', $item->template, 'item', $item->id);

                // try to duplicate
                $item->id = 0;
                $ok = $item->insert();

                if($ok)
                {
                    // duplicate item properties too (but don't duplicate comments)
                    $ok = property::save_properties_from_array('item', $item->id, $item->template, $properties);
                }

				if($ok)
				{
					$layout->navigate_notification(t(478, 'Item duplicated successfully.'), false);
                    $out = items_form($item);
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
                    $item = new item();
                    $item->load(intval($_REQUEST['id']));
                    $out = items_form($item);
				}

				users_log::action($_REQUEST['fid'], $item->id, 'duplicate', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
			}
			break;
		
		case 89:
			if(!empty($_REQUEST['id'])) 
			{
				$DB->execute('DELETE FROM nv_webdictionary_history WHERE id = '.intval($_REQUEST['id']).' LIMIT 1');
				echo 'true';
			}
			else
				echo 'false';
			core_terminate();
			break;
			
		case 90:
			$DB->query('SELECT id, date_created, autosave
						  FROM nv_webdictionary_history
						 WHERE node_type = "item"
						   AND subtype = '.protect('section-'.$_REQUEST['section']).'
						   AND lang = '.protect($_GET['lang']).'
						   AND node_id = '.protect($_REQUEST['id']).'
						   AND website = '.$website->id.' 
				      ORDER BY date_created DESC',
						'array');		
			
			$result = $DB->result();
			
			if(!is_array($result)) $result = array();
			for($i=0; $i < count($result); $i++)
			{
				$result[$i]['date'] = core_ts2date($result[$i]['date_created'], true);
				if($result[$i]['autosave']==1)
					$result[$i]['date'] .= ' ('.t(273, 'Autosave').')';
			}
			
			echo json_encode($result);
							  
			core_terminate();		
			break;
			
		case 91: // json search title request (for "copy from" dialog)
			$DB->query('SELECT node_id as id, text as label, text as value
						  FROM nv_webdictionary
						 WHERE node_type = "item"
						   AND subtype = "title"
						   AND lang = '.protect($_REQUEST['lang']).'
						   AND website = '.$website->id.' 
						   AND text LIKE '.protect('%'.$_REQUEST['title'].'%').'
				      ORDER BY text ASC
					     LIMIT 30',
						'array');
						
			echo json_encode($DB->result());
							  
			core_terminate();
			break;
			
		case 92: // return raw item contents
		
			if(empty($_REQUEST['section'])) $_REQUEST['section'] = 'main';
		
			if($_REQUEST['history']=='true')
			{
				$DB->query('SELECT text
							  FROM nv_webdictionary_history
							 WHERE node_type = "item"
							   AND website = '.$website->id.' 
							   AND id = '.protect($_REQUEST['id']),							   
							'array');	
							
				$data = $DB->first();				
				echo $data['text'];		
			}
			else
			{		
				$DB->query('SELECT text
							  FROM nv_webdictionary
							 WHERE node_type = "item"
							   AND subtype = '.protect('section-'.$_REQUEST['section']).'
							   AND lang = '.protect($_REQUEST['lang']).'
							   AND website = '.$website->id.' 
							   AND node_id = '.protect($_REQUEST['node_id']),
							'array');

				$data = $DB->first();				
				echo $data['text'];
			}
							  
			core_terminate();
			break;
			
		case 93: // return raw template content
			$DB->query('SELECT file
						  FROM nv_templates
						 WHERE enabled = 1
						   AND id = '.protect($_REQUEST['id']).'
						   AND website = '.$website->id,
						'array');
						
			$data = $DB->first();
			
			echo @file_get_contents(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$data['file']);
							  
			core_terminate();
			break;	
			
		case 94:
        case "template_sections":
            // return template sections for a content id
			$item = new item();
			$item->load(intval($_REQUEST['id']));
			$template = $item->load_template();

            for($ts=0; $ts < count($template->sections); $ts++)
            {
                if($template->sections[$ts]['name']=='#main#')
                    $template->sections[$ts]['name'] = t(238, 'Main content');
            }

			echo json_encode($template->sections);
							  
			core_terminate();
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
			
		case 96: // return category paths 
			echo json_encode(path::loadElementPaths('structure', intval($_REQUEST['id'])));
			core_terminate();
			break;

		case 97: // json search user request (for "moderator" autocomplete)
			$DB->query('SELECT id, username as label, username as value
						  FROM nv_users
						 WHERE username LIKE '.protect('%'.$_REQUEST['username'].'%').' 
				      ORDER BY username ASC
					     LIMIT 30',
						'array');
						
			echo json_encode($DB->result());
							  
			core_terminate();
			break;		
			
		case 98: // change comment status
			if(empty($_REQUEST['id']))
			{
				echo "false"; 
				core_terminate();
			}
			
			switch($_REQUEST['opt'])
			{
				case 'publish':
					$DB->execute('UPDATE nv_comments
									 SET status = 0
									WHERE website = '.$website->id.'
									  AND id = '.$_REQUEST['id']);
					break;
					
				case 'unpublish':
					$DB->execute('UPDATE nv_comments
									 SET status = 1
									WHERE website = '.$website->id.'
									  AND id = '.$_REQUEST['id']);					
					break;
					
				case 'delete':
					$DB->execute('DELETE FROM nv_comments
									WHERE website = '.$website->id.'
									  AND id = '.$_REQUEST['id']);				
					break;
			}
		
			$error = $DB->get_last_error();
			if(empty($error)) echo 'true';
			else			  echo 'false';
							  
			core_terminate();
			break;			
			
		case 'autosave':
			
			if(!empty($_REQUEST['id']))
			{					
				$iDictionary = array();
				
				foreach($_REQUEST as $key => $value)
				{
					if(strpos($key, 'section-')===0)
					{
						$lang = substr($key, -2, 2);
						$kname = substr($key, 0, strlen($key) - 3);
						$iDictionary[$lang][$kname] = $value;
					}
				}
				
				$changed = webdictionary_history::save_element_strings('item', intval($_REQUEST['id']), $iDictionary, true);
                if($changed)
                    echo 'changes_saved';
                else
                    echo 'no_changes';
				core_terminate();
			}

			echo 'false';
			core_terminate();			
			break;
			
		case 'votes_reset':
			webuser_vote::remove_object_votes('item', intval($_REQUEST['id']));
			
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
				
				webuser_vote::update_object_score('item', $vote->object_id);
					
				echo 'true';
				core_terminate();	
			}
		
			$max = intval($_GET['rows']);
			$page = intval($_GET['page']);
			$offset = ($page - 1) * $max;	
		
			if($_REQUEST['_search']=='false')
				list($dataset, $total) = webuser_vote::object_votes_by_webuser('item', intval($_REQUEST['id']), $_REQUEST['sidx'].' '.$_REQUEST['sord'], $offset, $max);
		
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

        case 'items_order':
            if(!empty($_POST['items-order']))
            {
                // save new order
                $response = item::reorder($_POST['items-order']);
                if($response!==true)
                {
                    echo $response['error'];
                }
                else
                    echo 'true';
            }
            else    // show ordered list
                echo items_order($_REQUEST['category']);

            core_terminate();
            break;

        case 'grid_note_background':
            grid_notes::background('item', $_REQUEST['id'], $_REQUEST['background']);
            core_terminate();
            break;

        case 'grid_notes_comments':
            $comments = grid_notes::comments('item', $_REQUEST['id']);
            echo json_encode($comments);
            core_terminate();
            break;

        case 'grid_notes_add_comment':
            echo grid_notes::add_comment('item', $_REQUEST['id'], $_REQUEST['comment'], $_REQUEST['background']);
            core_terminate();
            break;

        case 'grid_note_remove':
            echo grid_notes::remove($_REQUEST['id']);
            core_terminate();
            break;
		
		case 0: // list / search result
		default:			
			$out = items_list();
			break;
	}
	
	return $out;
}

function items_list()
{
    global $layout;
    
	$navibars = new navibars();
	$navitable = new navitable("items_list");
	
	$navibars->title(t(22, 'Items'));

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('date_modified', 'DESC');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=2&id=');
	$navitable->enableSearch();
	$navitable->enableDelete();
	
	$navitable->addCol("ID", 'id', "40", "true", "left");	
	$navitable->addCol(t(67, 'Title'), 'title', "350", "true", "left");	
	$navitable->addCol(t(309, 'Social'), 'comments', "80", "true", "center");
	$navitable->addCol(t(78, 'Category'), 'category', "150", "true", "center");	
	$navitable->addCol(t(266, 'Author'), 'author_username', "100", "true", "left");	
	$navitable->addCol(t(85, 'Date published'), 'dates', "100", "true", "center");
	$navitable->addCol(t(80, 'Permission'), 'permission', "80", "true", "center");		
	$navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");
	
	$navibars->add_content($navitable->generate());	

	return $navibars->generate();
}

function items_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
    global $theme;
    global $events;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we can use media browser in this function

	if(empty($item->id))
		$navibars->title(t(22, 'Items').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(22, 'Items').' / '.t(170, 'Edit').' ['.$item->id.']');	

	$navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+M"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));

    if(!empty($item->id))
    {
        $notes = grid_notes::comments('item', $item->id);
        $navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_items_display_notes();"><span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span><img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'</a>'	));
    }

	if(!empty($item->id))
    {
        $events->trigger(
            'elements',
            'edit',
            array()
        );
    }

	if(empty($item->id))
	{
		$navibars->add_actions(
            array(	'<a href="#" onclick="navigate_items_tabform_submit(1);" title="Ctrl+S"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>' )
        );
	}
	else
	{
		$navibars->add_actions(
            array(	'<a href="#" onclick="navigate_items_tabform_submit(1);" title="Ctrl+S"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
                    '<a href="#" onclick="navigate_items_preview();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/monitor.png"> '.t(274, 'Preview').'</a>',
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
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=delete&id='.$item->id.'";
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=items&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
                                    (!empty($item->id)? '<a href="?fid=items&act=duplicate&id='.$item->id.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/page_copy.png"> '.t(477, 'Duplicate').'</a>' : ''),
									'<a href="?fid=items&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	// Languages
    $ws_languages = $website->languages();

	$navibars->form('', 'fid=items&act=edit&id='.$item->id);

    $layout->add_script("
        $(document).on('keydown.Ctrl_s', function (evt) { navigate_items_tabform_submit(1); return false; } );
        $(document).on('keydown.Ctrl_m', function (evt) { navigate_media_browser(); return false; } );
    ");

	$layout->add_script('
		var template_sections = [];	
	');		

	$navibars->add_tab(t(43, "Main")); // tab #0
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

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
																		
									
	if(empty($item->author)) $item->author = $user->id;
	$author_webuser = $DB->query_single('username', 'nv_users', ' id = '.$item->author);	
	$navibars->add_tab_content($naviforms->hidden('item-author', $item->author));
	$navibars->add_tab_content_row(array(	'<label>'.t(266, 'Author').'</label>',
											$naviforms->textfield('item-author-text', $author_webuser)
										));	
	
	// script#1									

	if($item->date_modified > 0)
	{																		
		$navibars->add_tab_content_row(array(	'<label>'.t(227, 'Date modified').'</label>',
												core_ts2date($item->date_modified, true)
											));											
	}
	
	if($item->date_created > 0)
	{
		$navibars->add_tab_content_row(array(	'<label>'.t(226, 'Date created').'</label>',
												core_ts2date($item->date_created, true)
											));	
	}

    if(!empty($item->id))
    {
        $layout->add_script("
            function navigate_items_display_notes()
            {
                var row_id = ".$item->id.";
                // open item notes dialog
                $('<div><img src=\"".NAVIGATE_URL."/img/loader.gif\" style=\" top: 162px; left: 292px; position: absolute; \" /></div>').dialog({
                    modal: true,
                    width: 600,
                    height: 400,
                    title: '".t(168, "Notes")."',
                    open: function(event, ui)
                    {
                        var container = this;
                        $.getJSON('?fid=".$_REQUEST['fid']."&act=grid_notes_comments&id=' + row_id, function(data)
                        {
                            $(container).html('".
                                '<div><form action="#" onsubmit="return false;" method="post"><span class=\"grid_note_username\">'.$user->username.'</span><button class="grid_note_save">'.t(34, 'Save').'</button><br /><textarea id="grid_note_comment" class="grid_note_comment"></textarea></form></div>'
                            ."');

                            for(d in data)
                            {
                                var note = '<div class=\"grid_note ui-corner-all\" grid-note-id=\"'+data[d].id+'\" style=\" background: '+data[d].background+'; \">';
                                note += '<span class=\"grid_note_username\">'+data[d].username+'</span>';
                                note += '<span class=\"grid_note_remove\"><img src=\"".NAVIGATE_URL."img/icons/silk/decline.png\" /></span>';
                                note += '<span class=\"grid_note_date\">'+data[d].date+'</span>';
                                note += '<span class=\"grid_note_text\">'+data[d].note+'</span>';
                                note += '</div>';

                                $(container).append(note);
                            }

                            $(container).find('.grid_note_remove').bind('click', function()
                            {
                                var grid_note = $(this).parent();

                                $.get('?fid=".$_REQUEST['fid']."&act=grid_note_remove&id=' + $(this).parent().attr('grid-note-id'), function(result)
                                {
                                    if(result=='true')
                                    {
                                        $(grid_note).fadeOut();
                                        $('.navigate_grid_notes_span').html(parseInt($('.navigate_grid_notes_span').text()) - 1);
                                    }
                                });
                            });

                            $(container).find('.grid_note_save').button(
                            {
                                icons: { primary: 'ui-icon-disk' }
                            }).bind('click', function()
                            {
                                $.post('?fid=".$_REQUEST['fid']."&act=grid_notes_add_comment',
                                {
                                    comment: $(container).find('.grid_note_comment').val(),
                                    id: row_id,
                                    background: $('#' + row_id).find('.grid_color_swatch').attr('ng-background')
                                },
                                function(result)
                                {
                                    if(result=='true') // reload dialog and table
                                    {
                                        $(container).parent().remove();
                                        $('.navigate_grid_notes_span').html(parseInt($('.navigate_grid_notes_span').text()) + 1);
                                    }
                                });
                            });
                        });
                    }
                });
            };
        ");
    }
	
	$navibars->add_tab(t(87, "Association")); // tab #1

	$navibars->add_tab_content_row(array(	'<label>'.t(87, "Association").'</label>',
											$naviforms->buttonset(
                                                'association',
												array(  'free' => t(100, 'Free'),
												        'category' => t(78, 'Category')
                                                ),
                                                (empty($item->id)? 'category' : $item->association),
                                                "navigate_change_association(this);"
                                            )
										)
    );
										
	$hierarchy = structure::hierarchy(0);
	$categories_list = structure::hierarchyList($hierarchy, $item->category);

    if(empty($categories_list))
        $categories_list = '<ul><li value="0">'.t(428, '(no category)').'</li></ul>';

	$navibars->add_tab_content_row(array(	'<label>'.t(78, 'Category').'</label>',
                                            $naviforms->dropdown_tree('category', $categories_list, $item->category, 'navigate_item_category_change')
										),
									'div_category_tree');

    /*
	$navibars->add_tab_content($naviforms->hidden('category', $item->category));		

	$navibars->add_tab_content_row(array(	'<label>'.t(78, 'Category').'</label>',
											'<div class="category_tree"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.$categories_list.'</div>'
										),
									'div_category_tree');		
	
	$layout->add_script('
		$(".category_tree ul:first").kvaTree({
					imgFolder: "js/kvatree/img/",
					dragdrop: false,
					background: "#f2f5f7",
					onClick: function(event, node)
					{
						if($("input[name=category]").val()==$(node).attr("value"))
						{
							// already selected, dissasociate
							$("input[name=category]").val("");
							$(".category_tree").find(".active").removeClass("active");
							return false;
						}
						else
						{						
							$("input[name=category]").val($(node).attr("value"));
                            navigate_item_category_change($(node).attr("value"));
						}
					}
				});
	');
    */

    $layout->add_script('
        function navigate_item_category_change(id)
        {
            $.ajax(
            {
                url: NAVIGATE_APP + "?fid=" + navigate_query_parameter("fid") + "&act=96&id=" + id,
                dataType: "json",
                data: {},
                success: function(data, textStatus, xhr)
                {
                    item_category_path = data;
                }
            });
        }
    ');

	$navibars->add_tab_content_row(array(	'<label>'.t(162, 'Embedding').'</label>',
											$naviforms->buttonset(	'embedding', 
																	array( '1' => t(163, 'Embedded'),
																		   '0' => t(164, 'Own path')
																		 ),
																	(empty($item->id)? '1' : intval($item->embedding)), 
																	"navigate_change_association();"),
											'<span id="embedding_info" class="ui-icon ui-icon-lightbulb" style="float: left;"></span>'
										),
									'div_category_embedded');	

    $navibars->add_tab_content_row(array(   '<label>'.t(22, 'Elements').'</label>',
                                            '<button style="float: left;">'.t(171, 'Order').'</button>',
                                            '<span id="order_info" class="ui-icon ui-icon-info" style="float: left;"></span>',
                                            '<div id="items_order_window" style="display: none;"></div>'
                                   ),
                                   'div_category_order');

	$layout->add_script('
	    $("#div_category_order button").button(
	    {
	        icons:
	        {
                primary: "ui-icon-arrowthick-2-n-s"
            }
	    }).bind("click", function(e)
	    {
	        e.stopPropagation();
	        e.preventDefault();
	        navigate_status(navigate_t(6, "Loading") + "...", "loader");

	        $("#items_order_window").load("?fid=items&act=items_order&category=" + $("#category").val() + "&_bogus=" + new Date().getTime(), function()
	        {
	            navigate_status(navigate_t(42, "Ready"), "ready");
                $("#items_order_window").dialog({
                    modal: true,
                    title: "'.t(171, 'Order').'",
                    width: 600,
                    height: 500,
                    buttons:
                    {
                        "'.t(58, 'Cancel').'": function()
                        {
                            $(this).dialog("destroy");
                        },
                        "'.t(190, 'Ok').'": function()
                        {
                            var dialog = this;
                            // save
                            $.post(
                                "?fid=items&act=items_order&category=" + $("#category").val() + "&_bogus=" + new Date().getTime(),
                                {
                                    "items-order": $("#items-order").val()
                                },
                                function(response)
                                {
                                    if(response=="true")
                                    {
                                        $(dialog).dialog("destroy");
                                    }
                                    else
                                    {
                                        $("<div>"+response+"</div>").dialog({
                                            modal: true,
                                            title: "'.t(56, "Unexpected error").'"
                                        });
                                    }
                                }
                            );
                        }
                    }
                });
            });
	    });

		$("#embedding_info").qtip(
		{
		    content: \'<div><strong>'.t(163, 'Embedded').'</strong>: '.t(165, 'Full content is shown on category page. Ex. "Who we are?"').'<br /><br /><strong>'.t(164, 'Own path').'</strong>: '.t(166, 'The content is accessed through its own url. Ex. "News"').'</div>\',
		    show:
		    {
		        event: "mouseover"
            },
	        hide:
	        {
		        event: "mouseout"
            },
	        style:
	        {
		        tip: true,
		        width: 300,
		        classes: "qtip-cream"
	        },
	        position:
	        {
		        at: "top right",
		        my: "bottom left"
	        }
        });

		$("#order_info").qtip(
		{
		    content: \'<div>'.t(425, 'Order elements of a category (unless the template forces other sorting)').'</div>\',
		    show:
            {
                event: "mouseover"
            },
            hide:
            {
                event: "mouseout"
            },
            style:
            {
                tip: true,
                width: 300,
                classes: "qtip-cream"
            },
            position:
            {
                at: "top right",
                my: "bottom left"
            }
		});
	');

	$templates = template::elements();
	$template_select = $naviforms->select_from_object_array('template', $templates, 'id', 'title', $item->template);
										                    
	$navibars->add_tab_content_row(array(	'<label>'.t(79, 'Template').'</label>',
											$template_select,
										),
								   'div_template_select');							

	$layout->add_script('
		var last_check = [];
		var active_languages = ["'.implode('", "', array_keys($ws_languages)).'"];
		$("#div_template_select").hide();
	');

	// script#3	
	if(!empty($item->id))
	{									
		$navibars->add_tab(t(9, "Content"));	// tab #2
		
		$navibars->add_tab_content_row(array(	'<label>'.t(63, 'Languages').'</label>',
												$naviforms->buttonset('language_selector', $ws_languages, $website->languages_list[0], "navigate_items_select_language(this);")
											));	
		
		$template = $item->load_template();

        $translate_extensions = extension::list_installed('translate');

        foreach($website->languages_list as $lang)
		{
			$navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">');
			
			$navibars->add_tab_content_row(array(	'<label>'.t(67, 'Title').'</label>',
													$naviforms->textfield('title-'.$lang, @$item->dictionary[$lang]['title'])
												));		

			$open_live_site = '';												
			if(!empty($item->paths[$lang]))
				$open_live_site = ' <a target="_blank" href="'.$website->absolute_path(true).$item->paths[$lang].'"><img src="img/icons/silk/world_go.png" align="absmiddle" /></a>';
												
			$navibars->add_tab_content_row(array(	'<label>'.t(75, 'Path').$open_live_site.'</label>',
													$naviforms->textfield('path-'.$lang, @$item->paths[$lang], NULL, 'navigate_items_path_check(this);'),
													'<span>&nbsp;</span>'
												),
										   'div_path_'.$lang);
									   
			if(empty($template->sections)) 
				$template->sections[] = array(
                    0 => array(
                        'code' => 'main',
                        'name' => '#main#',
                        'editor' => 'tinymce',
                        'width' => '960px'
                    )
                );
				
			foreach($template->sections as $section)
			{								
				if(is_object($section))
					$section = (array)$section;

				if($section['editor']=='tinymce')
				{
                    $translate_menu = '';
                    if(!empty($translate_extensions))
                    {
                        $translate_extensions_titles = array();
                        $translate_extensions_actions = array();

                        foreach($translate_extensions as $te)
                        {
                            if($te['enabled']=='0') continue;
                            $translate_extensions_titles[] = $te['title'];
                            $translate_extensions_actions[] = 'javascript: navigate_tinymce_translate_'.$te['code'].'(\'section-'.$section['code'].'-'.$lang.'\', \''.$lang.'\');';
                        }

                        if(!empty($translate_extensions_actions))
                        {
                            $translate_menu = $naviforms->splitbutton(
                                'translate_'.$lang,
                                '<img src="img/icons/silk/comment.png" align="absmiddle"> '.t(188, 'Translate'),
                                $translate_extensions_actions,
                                $translate_extensions_titles
                            );
                        }
                    }

					$navibars->add_tab_content_row(array(	'<label>'.template::section_name($section['name']).'</label>',
															$naviforms->editorfield('section-'.$section['code'].'-'.$lang, @$item->dictionary[$lang]['section-'.$section['code']], ($section['width']+32).'px', $lang),
															'<div style="clear:both; margin-top:5px; float:left; margin-bottom: 10px;">',
															'<label>&nbsp;</label>',
                                                            $translate_menu,
															'<button onclick="navigate_items_copy_from_dialog(\'section-'.$section['code'].'-'.$lang.'\'); return false;"><img src="img/icons/silk/page_white_copy.png" align="absmiddle"> '.t(189, 'Copy from').'...</button> ',
															'<button onclick="navigate_items_copy_from_history_dialog(\'section-'.$section['code'].'-'.$lang.'\', \''.$section['code'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/time_green.png" align="absmiddle"> '.t(40, 'History').'</button> ',
                                                            (!empty($theme->content_samples)? '<button onclick="navigate_items_copy_from_theme_samples(\'section-'.$section['code'].'-'.$lang.'\', \''.$section['code'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/rainbow.png" align="absmiddle"> '.t(368, 'Theme').': '.$theme->title.'</button> ' : ''),
															'</div>',
															'<br />'
														));
				}
				else if($section['editor']=='html')	// html source code (codemirror)
				{
					$navibars->add_tab_content_row(array(	'<label>'.template::section_name($section['name']).'</label>',
															$naviforms->scriptarea('section-'.$section['code'].'-'.$lang, @$item->dictionary[$lang]['section-'.$section['code']], 'html', ' width: '.$section['width'].'px'),
															'<div style="clear:both; margin-top:5px; float:left; margin-bottom: 10px;">',
															'<label>&nbsp;</label>',
															'<button onclick="navigate_items_copy_from_history_dialog(\'section-'.$section['code'].'-'.$lang.'\', \''.$section['code'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/time_green.png" align="absmiddle"> '.t(40, 'History').'</button> ',
                                                            (!empty($theme->content_samples)? '<button onclick="navigate_items_copy_from_theme_samples(\'section-'.$section['code'].'-'.$lang.'\', \''.$section['code'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/rainbow.png" align="absmiddle"> '.t(368, 'Theme').': '.$theme->title.'</button> ' : ''),
															'</div>',
															'<br />'
														));					
				}
				else	// plain textarea (raw)
				{
					$navibars->add_tab_content_row(
                        array(
                            '<label>'.template::section_name($section['name']).'</label>',
                            $naviforms->textarea('section-'.$section['code'].'-'.$lang, @$item->dictionary[$lang]['section-'.$section['code']], 8, 48, ' width: '.$section['width'].'px'),
                            '<div style="clear:both; margin-top:5px; margin-bottom: 10px; ">',
                            '<label>&nbsp;</label>',
                            '<button onclick="navigate_textarea_translate($(\'#section-'.$section['code'].'-'.$lang.'\'), \''.$lang.'\'); return false;"><img src="img/icons/silk/comment.png" align="absmiddle"> '.t(188, 'Translate').'</button> ',
                            '<button onclick="navigate_items_copy_from_history_dialog(\'section-'.$section['code'].'-'.$lang.'\', \''.$section['code'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/time_green.png" align="absmiddle"> '.t(40, 'History').'</button> ',
                            (!empty($theme->content_samples)? '<button onclick="navigate_items_copy_from_theme_samples(\'section-'.$section['code'].'-'.$lang.'\', \''.$section['code'].'\', \''.$lang.'\', \''.$section['editor'].'\'); return false;"><img src="img/icons/silk/rainbow.png" align="absmiddle"> '.t(368, 'Theme').': '.$theme->title.'</button> ' : ''),
                            '</div>'
                        )
                    );
				}
			}
			
			if($template->tags==1)
			{
				$tags_copy_select = '';
				if(count($website->languages_list) > 1)
                    $tags_copy_select = $naviforms->selectfield('', array_keys($ws_languages), array_values($ws_languages), '', '', false, '', ' width: 150px; ', false);
					//$tags_copy_select = $naviforms->select_from_object_array('', $website->languages_list, 'code', 'name', '', ' width: auto; ', array($lang));
				
				$navibars->add_tab_content_row(
                    array(
                        '<label>'.t(265, 'Tags').'</label>',
                        $naviforms->textfield('tags-'.$lang, @$item->dictionary[$lang]['tags']),		// foo,bar,baz
                        (empty($tags_copy_select)? '' : '<div style=" position: relative; margin-left: 600px; margin-top: -57px; width: 200px; height: 68px; ">'),
                        (empty($tags_copy_select)? '' : '<img src="img/icons/misc/locale.png" width="16" height="16" align="absmiddle"
                              style=" cursor: pointer; " onclick=" $(\'#tags-'.$lang.'\').importTags($(\'#tags-\' + $(this).next().val()).val()); $(\'#tags-'.$lang.'\').removeTag();" />'),
                        $tags_copy_select,
                        (empty($tags_copy_select)? '' : '</div>')
                    )
                );
			}
												
			$layout->add_script('
				$("#tags-'.$lang.'").tagsInput({
					defaultText: "",
					width: $("#tags-'.$lang.'").width(),
					height: 100
				});
                $("#tags-'.$lang.'").parent().find("select").css("width", "auto");
			');
				
			$layout->add_script('
				var template_sections = '.json_encode($template->sections).';
			    var theme_content_samples = '.json_encode($theme->content_samples).';
			    var website_theme = "'.$website->theme.'";
			');	
			
			// script#4
	
			$navibars->add_tab_content('</div>');		
		}

		$category = new structure();		
		$category->paths = array();
		if(!empty($item->category))
			$category->load($item->category);
			
		$layout->add_script('
			var item_category_path = '.json_encode($category->paths).';
			var item_id = "'.$item->id.'";
		');

		// script#5
				
		$layout->add_content('
			<div id="navigate_items_copy_from" style=" display: none; ">
				<div class="navigate-form-row">
					<label>'.t(191, 'Source').'</label>
					'.$naviforms->buttonset(	'navigate_items_copy_from_type', 
												array( 'language' => t(46, 'Language'),
													   'template' => t(79, 'Template'),
													   'item'	  => t(180, 'Item')
													 ),
												'0',
												"navigate_items_copy_from_change_origin(this);").'
				</div>
				<div class="navigate-form-row" style=" display: none; ">
					<label>'.t(46, 'Language').'</label>
					'.$naviforms->selectfield(	'navigate_items_copy_from_language_selector', 
												array_keys($ws_languages),
												array_values($ws_languages),
												$data[0]->code).'
				</div>
				<div class="navigate-form-row" style=" display: none; ">
					<label>'.t(79, 'Template').'</label>
					'.$naviforms->select_from_object_array('navigate_items_copy_from_template', $templates, 'id', 'title', '', '').'
				</div>			
				<div class="navigate-form-row" style=" display: none; ">		
					<label>'.t(67, 'Title').'</label>			
					'.$naviforms->textfield('navigate_items_copy_from_title').'
					'.$naviforms->hidden('navigate_items_copy_from_item_id', '').'
				</div>
				<div class="navigate-form-row" style=" display: none; ">
					<label>'.t(239, 'Section').'</label>
					'.$naviforms->select_from_object_array('navigate_items_copy_from_section', array(), 'code', 'name', '').'
				</div>			
			</div>
			
			<div id="navigate_items_copy_from_history" style=" display: none; ">
				<div class="navigate-form-row">
					<label>'.t(196, 'Date & time').'</label>
					<select id="navigate_items_copy_from_history_options" 
							name="navigate_items_copy_from_history_options" 
							onchange="navigate_items_copy_from_history_preview(this.value, $(this).attr(\'type\'));">
					</select>
					<a href="#" onclick="navigate_items_copy_from_history_remove();"><img src="img/icons/silk/cancel.png" align="absmiddle"></a>
				</div>			
				<div class="navigate-form-row">
					<div id="navigate_items_copy_from_history_text" 
						 name="navigate_items_copy_from_history_text"
						 style="border: 1px solid #CCCCCC; float: left; height: auto; min-height: 20px; overflow: auto; width: 97%; padding: 3px; background: #f7f7f7;">
					</div>
					<div id="navigate_items_copy_from_history_text_raw" style=" display: none; "></div>
				</div>			
			</div>

			<div id="navigate_items_copy_from_theme_samples" style=" display: none; ">
				<div class="navigate-form-row">
					<label>'.t(79, 'Template').'</label>
					<select id="navigate_items_copy_from_theme_samples_options"
							name="navigate_items_copy_from_theme_samples_options"
							onchange="navigate_items_copy_from_theme_samples_preview(this.value, $(this).attr(\'type\'));">
					</select>
				</div>
				<div class="navigate-form-row">
					<div id="navigate_items_copy_from_theme_samples_text"
						 name="navigate_items_copy_from_theme_samples_text"
						 style="border: 1px solid #CCCCCC; float: left; height: auto; min-height: 20px; overflow: auto; width: 97%; padding: 3px; background: #f7f7f7;">
					</div>
					<div id="navigate_items_copy_from_theme_samples_text_raw" style=" display: none; "></div>
				</div>
			</div>
		');
		
		// script will be binded to onload event at the end of this php function (after getScript is done)
		$onload_language = $_REQUEST['tab_language'];
		if(empty($onload_language))
			$onload_language = $website->languages_list[0];
			
		$layout->add_script('
			function navigate_items_onload()
			{
				navigate_items_select_language("'.$onload_language.'");
				navigate_change_association("'.(empty($item->id)? 'category' : $item->association).'");
				setTimeout(function()
				{
					$(navigate_codemirror_instances).each(function() { this.refresh(); } );
				}, 500);
			};
		');	
		
		/* IMAGE GALLERIES */

		if($template->gallery > 0 || $template->gallery==='true')
		{
			$navibars->add_tab(t(210, "Gallery")); // tab #3
				
			if(!is_array($item->galleries[0])) $item->galleries[0] = array();
			$gallery_elements_order = implode('#', array_keys($item->galleries[0]));
			
			$navibars->add_tab_content(
					$naviforms->hidden('items-gallery-elements-order', $gallery_elements_order)
			);
					
			$gallery = '<ul id="items-gallery-elements" class="items-gallery">';
			
			$ids = array_keys($item->galleries[0]);
			
			for($g=0; $g < count($ids); $g++)
			{
				// $naviforms->dropbox("items-gallery-item-".$p, '', "image")
				$gallery .= '<li>
								<div id="items-gallery-item-'.$ids[$g].'-droppable" class="navigate-droppable ui-corner-all">
									<img title="'.$ids[$g].'" src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$ids[$g].'&amp;disposition=inline&amp;width=75&amp;height=75" />
								</div>
								<div class="navigate-droppable-cancel"><img src="img/icons/silk/cancel.png" /></div>
							</li>';
			}
		
			// empty element
			$gallery .= '<li>
							<div id="items-gallery-item-empty-droppable" class="navigate-droppable ui-corner-all">
								<img src="img/icons/misc/dropbox.png" vspace="18" />
							</div>
						 </li>';	
			
			$gallery.= '</ul>';
			
			// now the image captions	
			foreach($item->galleries[0] as $image_id => $image_dictionary)
			{		
				if(!is_array($image_dictionary)) $image_dictionary = array();	
				foreach($website->languages_list as $lang)
				{
					$gallery .= $naviforms->hidden('items-gallery-item-'.$image_id.'-dictionary-'.$lang, $image_dictionary[$lang]);
				}
			}	
			
			$navibars->add_tab_content_row(array(	'<label>'.t(210, 'Gallery').'</label>',
																	 '<div>'.$gallery.'</div>'));		
			// script#6
			
			$layout->add_script('	
			
				$("#items-gallery-elements .navigate-droppable").live("dblclick", function()
				{
					var id = $(this).attr("id");
					id = id.replace("items-gallery-item-", "");
					id = id.replace("-droppable", "");
					
					if(!id || id=="" || id=="empty") return;
					
					$("#navigate_items_gallery_captions_form_image-droppable img")
						.attr("src", NAVIGATE_DOWNLOAD + "?wid=" + $(this).attr("website_id") + "&id=" + id + "&disposition=inline&width=75&height=75") // navigate["website_id"]
						.attr("vspace", 0);
					
					for(lang in active_languages)
					{
						$("#navigate_items_gallery_captions_form_image_" + active_languages[lang])
							.val($("#items-gallery-item-" + id + "-dictionary-" + active_languages[lang]).val());
					}
					
					$("#navigate_items_gallery_captions_form").dialog(
					{
						title: "<img src=\"img/icons/silk/image_edit.png\" align=\"absmiddle\"> '.t(77, 'Properties').'",
						modal: true,
						buttons: 
						{ 
							"'.t(58, 'Cancel').'": function() 
							{ 
								$(this).dialog("close"); 
								$(window).trigger("resize");
							},	
							"'.t(190, 'Ok').'": function() 
							{ 
								for(lang in active_languages)
								{
									var image_caption_id = "items-gallery-item-" + id + "-dictionary-" + active_languages[lang];
																											
									$("#"+image_caption_id)
										.val($("#navigate_items_gallery_captions_form_image_" + active_languages[lang]).val());
								}						
								$(this).dialog("close"); 
								$(window).trigger("resize");
							}
						},
						width: 650,
						height: 300
					});	
				});
			
			');
						
			$captions_form = '
				<div id="navigate_items_gallery_captions_form" style=" display: none; ">
					<div class="navigate-form-row">
						<label>'.t(157, 'Image').'</label>
						'.$naviforms->dropbox('navigate_items_gallery_captions_form_image', '', 'image', true).'
					</div>
			';
				
			$caption_langs = array_values($website->languages_list);
			
			foreach($caption_langs as $caption_language)
			{
				$captions_form .= '
					<div class="navigate-form-row">
						<label>'.language::name_by_code($caption_language).'</label>
						'.$naviforms->textfield('navigate_items_gallery_captions_form_image_'.$caption_language, '').'
					</div>
				';
			}
			$captions_form .= '
				</div>
			';
		
			$layout->add_content($captions_form);
		}
		
		// Properties TAB (only if needed)
		$properties_html = '';

		if($item->association == 'free' && !empty($item->template) && $item->template != '0')
		{
			// we already know the properties to show: template is set on item
			$properties_html = navigate_property_layout_form('item', $item->template, 'item', $item->id);
		}
		else if($item->association == 'category' && ($item->embedding==0) && !empty($item->template))
		{
			// we already know the properties to show: template is set on item
			$properties_html = navigate_property_layout_form('item', $item->template, 'item', $item->id);
		}
		else if($item->association == 'category' && ($item->category > 0))
		{
			// we have to get the template set in the category of the item
			$template_id = $DB->query_single('template', 'nv_structure', ' id = '.protect($item->category).' AND website = '.$website->id);
			$properties_html = navigate_property_layout_form('item', $template_id, 'item', $item->id);
		}

		if(!empty($properties_html))
		{
			$navibars->add_tab(t(77, "Properties")); // tab #4
			$navibars->add_tab_content($properties_html);
		}

		if($template->comments > 0 || $template->comments==='true')
		{
			$navibars->add_tab(t(250, "Comments"));	 // tab #5
			
			$navibars->add_tab_content_row(array(	'<label>'.t(252, 'Comments enabled to').'</label>',
													$naviforms->selectfield('item-comments_enabled_to', 
														array(
                                                            0 => 0,
                                                            1 => 1,
                                                            2 => 2
														),
														array(
                                                            0 => t(253, 'Nobody'),
                                                            1 => t(24, 'Registered users'),
                                                            2 => t(254, 'Everyone')
														),
														$item->comments_enabled_to
													)
												)
											);
															
			if(!empty($item->comments_moderator))
				$webuser = $DB->query_single('username', 'nv_users', ' id = '.$item->comments_moderator);
		
			$navibars->add_tab_content($naviforms->hidden('item-comments_moderator', $item->comments_moderator));
			
			$navibars->add_tab_content_row(array(	'<label>'.t(255, 'Moderator').'</label>',
													$naviforms->textfield('item-comments_moderator-text', $webuser),
													'<div class="subcomment"><img align="absmiddle" src="'.NAVIGATE_URL.'/img/icons/silk/information.png" /> '.t(256, 'Leave blank to accept all comments').'</div>'
												));		
			
			// script#7
			
			// comments list
			// removed filter: AND nvwu.website = nvc.website ... reason: the webuser could be from another website if sharing webusers is enabled
			// to do: retrieve in blocks of 30 comments
			$DB->query('SELECT nvc.*, nvwu.username, nvwu.avatar
						  FROM nv_comments nvc
						 LEFT OUTER JOIN nv_webusers nvwu 
						 			  ON nvwu.id = nvc.user
						 WHERE nvc.website = '.protect($website->id).'
						   AND nvc.item = '.protect($item->id).'
						ORDER BY nvc.date_created ASC');
												
			$comments = $DB->result();
						
			$comments_total = count($comments);
			
			for($c=0; $c < $comments_total; $c++)
			{				
				if($comments[$c]->status==2)		$comment_status = 'hidden';
				else if($comments[$c]->status==1)	$comment_status = 'private';
				else if($comments[$c]->status==-1)	$comment_status = 'new';		
				else								$comment_status = 'public';		
			
				$navibars->add_tab_content_row(array(
					'<span class="items-comment-label">'.
						core_ts2date($comments[$c]->date_created, true).'<br />'.
						'<strong>'.(empty($comments[$c]->username)? $comments[$c]->name : $comments[$c]->username).'</strong>'.
						'<br />'.
						$comments[$c]->ip.
					'</span>',
					'<div id="items-comment-'.$comments[$c]->id.'" class="items-comment-message items-comment-status-'.$comment_status.'">'.nl2br($comments[$c]->message).'</div>',
					(empty($comments[$c]->avatar)? '' : '<img style=" margin-left: 5px; " src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$comments[$c]->avatar.'&amp;disposition=inline&amp;width=46&amp;height=46" />')
					)
				);
			}	

			$navibars->add_tab_content('
				<div id="items-comments-toolbar">
					<img id="items-comments-toolbar-publish" src="'.NAVIGATE_URL.'/img/icons/silk/accept.png" title="'.t(258, 'Publish').'" />
					<img id="items-comments-toolbar-unpublish" src="'.NAVIGATE_URL.'/img/icons/silk/delete.png" title="'.t(259, 'Unpublish').'" />
					<img id="items-comments-toolbar-delete" src="'.NAVIGATE_URL.'/img/icons/silk/decline.png" title="'.t(35, 'Delete').'" />				
				</div>
			');
			
			// script#8
			// comments moderation
		}
	
		if($item->votes > 0)
		{
			$navibars->add_tab(t(352, "Votes"));	 // tab #6
			
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
				$("#items_votes_reset").on("click", function()
				{
                    $("<div>'.t(497, "Do you really want to erase this data?").'</div>").dialog(
                    {
                        resizable: true,
                        height: 150,
                        width: 300,
                        modal: true,
                        title: "'.t(59, 'Confirmation').'",
                        buttons:
                        {
						    "'.t(58, 'Cancel').'": function()
						    {
                                $(this).dialog("close");
                            },
							"'.t(354, 'Reset').'": function()
							{
							    $.post("?fid='.$_REQUEST['fid'].'&act=votes_reset&id='.$item->id.'", function(data)
					            {
						            $("#navigate-panel-web-summary").addClass("ui-state-disabled");
						            navigate_notification("'.t(355, 'Votes reset').'");
					            });
                                $(this).dialog("close");
							}
						}
					});
				});
				
				$("#items_votes_webuser").on("click", function()
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
			
			
			$navibars->add_tab_content_panel(
                '<img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(353, 'Score'),
				array(	'<div id="navigate-panel-web-score-graph" style=" margin: 8px; height: 150px; width: 360px; "></div>' ),
				'navigate-panel-web-score',
                '385px',
                '200px'
            );
											 											 
			$votes_by_score = webuser_vote::object_votes_by_score('item', $item->id);
			
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
			
			$navibars->add_tab_content_panel(
                '<img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(352, 'Votes').' ('.t(356, 'last 90 days').')',
                 array(	'<div id="navigate-panel-web-votes-graph" style=" margin: 8px; height: 150px; width: 360px; "></div>' ),
                 'navigate-panel-web-votes',
                '385px',
                '200px'
            );

			$votes_by_date = webuser_vote::object_votes_by_date('item', $item->id, 90);

			
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
										// when we don\'t set yaxis, the rectangle automatically
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
								interactive: false // mousewheel problems
							},
							pan: 
							{
								interactive: true
							}
						});

					});					
			
			');
			
		}		

        $nvweb_preview = NAVIGATE_PARENT.NAVIGATE_FOLDER.'/web/nvweb.php?preview=true&wid='.$website->id.'&route=';

		$layout->add_script('
			function navigate_items_preview()
			{
				navigate_items_disable_spellcheck();
				navigate_periodic_event_delegate(); // save current data in history
				var url = "'.$nvweb_preview.'";
				var active_language = $("input[name=\'language_selector[]\']:checked").val();

				if($("#template").parent().css("display")=="block")
					url = url + "node/'.$item->id.'&lang=" + active_language + "&template=" + $("#template").val();
			    else // category URL
			        url = url + item_category_path[active_language].slice(1);

				setTimeout(function() { window.open(url); }, 1000);
			}
		');
    }
	
	$layout->add_script('
		$.getScript("lib/packages/items/items.js", function()
		{
			if(typeof navigate_items_onload == "function")
				navigate_items_onload();
		});
	');

	return $navibars->generate();
}

function items_order($category)
{
    global $website;
    global $DB;
    global $layout;

    $out = array();
    $layout = new layout('free');
    $naviforms = new naviforms();

    // order blocks of the same type (for lists with priority ordering)
    $DB->query('SELECT i.id as id, d.text as title
                  FROM nv_items i, nv_webdictionary d
                 WHERE i.association = "category"
                   AND i.category = "'.$category.'"
                   AND d.node_type = "item"
                   AND d.subtype = "title"
                   AND d.lang = "'.$website->languages_list[0].'"
                   AND d.node_id = i.id
                   AND i.website = '.$website->id.'
                   AND d.website = '.$website->id.'
                ORDER BY i.position ASC');

    $item_ids = $DB->result('id');
    $items = $DB->result();

    $out[] = $naviforms->hidden('items-order', implode('#', $item_ids));

    $table = new naviorderedtable("items_order_table");
    $table->setWidth("560px");
    $table->setHiddenInput("items-order");

    $table->addHeaderColumn('ID', 50);
    $table->addHeaderColumn(t(67, 'Title'), 450);

    foreach($items as $row)
    {
        $table->addRow($row->id, array(
            array('content' => $row->id, 'align' => 'left'),
            array('content' => $row->title, 'align' => 'left')
        ));
    }

    $out[] = '<div class="subcomment" style=" margin-left: 0px; margin-bottom: 10px; "><img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'</div>';
    $out[] = '<div>'.$table->generate().'</div>';
    $out[] = '<div class="subcomment" style=" margin-left: 0px; margin-top: 10px; "><span class="ui-icon ui-icon-alert" style="float: left; margin-right: 4px; "></span> '.t(408, 'Order is only used on lists ordered by priority').'</div>';

    $out[] = $layout->generate();

    return implode("\n", $out);
}

?>