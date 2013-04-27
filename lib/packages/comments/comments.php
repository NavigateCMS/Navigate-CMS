<?php
require_once(NAVIGATE_PATH.'/lib/packages/comments/comment.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new comment();
			
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
					$where = ' website = '.$website->id;
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $item->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}

					$DB->queryLimit(
                        'id,item,user,email,date_created,status,message',
                        'nv_comments',
                        $where,
                        $orderby,
                        $offset,
                        $max
                    );
								
					$dataset = $DB->result();
					$total = $DB->foundRows();
					
					//echo $DB->get_last_error();
					
					$out = array();					
					
					$permissions = array(	
							-1=> '<img src="img/icons/silk/new.png" align="absmiddle" /> '.t(257, 'To review'),	
							0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(64, 'Published'),
							1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(251, 'Private'),
							2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(181, 'Hidden'),
                            3 => '<img src="img/icons/silk/error.png" align="absmiddle" /> '.t(466, 'Spam'),
						);														
											
					for($i=0; $i < count($dataset); $i++)
					{						
						if(empty($dataset[$i])) continue;
					
						// retrieve webuser name
						$webuser = $DB->query_single('username', 'nv_webusers', ' id = '.$dataset[$i]['user']);
						
						// retrieve item title
						$item = new item();
						$item->load($dataset[$i]['item']);
						$title = $item->dictionary[$website->languages_list[0]]['title'];
					
						$message = core_string_clean($dataset[$i]['message']);
						$message = core_string_cut($message, 60, '&hellip;');
												
						
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $title,
							2	=> core_ts2date($dataset[$i]['date_created'], true),
							3	=> (empty($dataset[$i]['user'])? $dataset[$i]['email'] : $webuser),
							4 	=> strip_tags($message),
							5	=> $permissions[$dataset[$i]['status']]
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
					$layout->navigate_notification(t(53, "Data saved successfully."), false);	
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = comments_form($item);
			break;
					
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = comments_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = comments_form($item);
				}
			}
			break;
			
		case 91: // json search title request (for "item" autocomplete)
			$DB->query('SELECT DISTINCT node_id as id, text as label, text as value
						  FROM nv_webdictionary
						 WHERE node_type = "item"
						   AND subtype = "title"
						   AND website = '.$website->id.' 
						   AND text LIKE '.protect('%'.$_REQUEST['title'].'%').'
				      ORDER BY text ASC
					     LIMIT 30',
						'array');
						// AND lang = '.protect($_REQUEST['lang']).'
						
			echo json_encode($DB->result());
							  
			session_write_close();
			exit;
			break;			

		case 92: // json search user request (for "username" autocomplete)
			$DB->query('SELECT id, username as label, username as value
						  FROM nv_webusers
						 WHERE username LIKE '.protect('%'.$_REQUEST['username'].'%').' 
						   AND website = '.protect($website->id).' 
				      ORDER BY username ASC
					     LIMIT 30',
						'array');
						
			echo json_encode($DB->result());
							  
			session_write_close();
			exit;
			break;			
					
					
		case 0: // list / search result
		default:			
			$out = comments_list();
			break;
	}
	
	return $out;
}

function comments_list()
{
	$navibars = new navibars();
	$navitable = new navitable("comments_list");
	
	$navibars->title(t(250, 'Comments'));

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('date_created', 'desc');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=2&id=');
	$navitable->enableDelete();	
	
	$navitable->addCol("ID", 'id', "80", "true", "left");	
	$navitable->addCol(t(180, 'Item'), 'item', "200", "true", "left");	
	$navitable->addCol(t(226, 'Date created'), 'date_created', "100", "true", "left");	
	$navitable->addCol(t(1, 'User'), 'user', "100", "true", "left");		
	$navitable->addCol(t(54, 'Text'), 'message', "200", "true", "left");	
	$navitable->addCol(t(68, 'Status'), 'status', "80", "true", "center");		

	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function comments_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
    global $events;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item->id))
		$navibars->title(t(250, 'Comments').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(250, 'Comments').' / '.t(170, 'Edit').' ['.$item->id.']');		

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
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=comments&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=comments&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));


	$navibars->add_tab_content($naviforms->hidden('comment-item', $item->item));

	if($item->item > 0)
	{
		$content = new item();
		$content->load($item->item);
		$title = $content->dictionary[$website->languages_list[0]]['title'];
	}

	$navibars->add_tab_content_row(array(	'<label>'.t(180, 'Item').'</label>',
											$naviforms->textfield('comment-item-text', $title),
										));
																														
	$layout->add_script('
		$("#comment-item-text").autocomplete(
		{
			source: function(request, response)
			{
				var toFind = {	
					"title": request.term,
					"lang": "'.$website->languages[0].'",
					nd: new Date().getTime()
				};
				
				$.ajax(
					{
						url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=91",
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
				$("#comment-item").val(ui.item.id);
			}
		});
	');	

	if(!empty($item->user))
		$webuser = $DB->query_single('username', 'nv_webusers', ' id = '.$item->user);

	$navibars->add_tab_content($naviforms->hidden('comment-user', $item->user));
	
	$navibars->add_tab_content_row(array(	'<label>'.t(1, 'User').'</label>',
											$naviforms->textfield('comment-user-text', $webuser),
										));		
										
	$layout->add_script('
		$("#comment-user-text").autocomplete(
		{
			source: function(request, response)
			{
				var toFind = {	
					"username": request.term,
					nd: new Date().getTime()
				};
				
				$.ajax(
					{
						url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=92",
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
				$("#comment-user").val(ui.item.id);
				//$("#comment-user-text").val(ui.item.label);
			}
		});
	');																													

	$navibars->add_tab_content_row(array(	'<label>'.t(159, 'Name').'</label>',
											'<input type="text" name="comment-name" value="'.$item->name.'" size="64" />' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(44, 'E-Mail').'</label>',
											'<input type="text" name="comment-email" value="'.$item->email.'" size="64" />' ));
									
	$navibars->add_tab_content_row(array(	'<label>'.t(54, 'Text').'</label>',
											$naviforms->textarea('comment-message', $item->message, 10),
										));										
									
	if(!empty($item->ip))
	{					
		$navibars->add_tab_content_row(array(	'<label>IP</label>',
												$item->ip
											));			
	}
	
	if($item->date_created > 0)
	{												
		$navibars->add_tab_content_row(array(	'<label>'.t(226, 'Date created').'</label>',
												core_ts2date($item->date_created, true)
											));																														
	}
	
	if($item->date_modified > 0)
	{
		$navibars->add_tab_content_row(array(	'<label>'.t(227, 'Date modified').'</label>',
												core_ts2date($item->date_modified, true)
											));		
	}
			
	$navibars->add_tab_content_row(
        array(	'<label>'.t(68, 'Status').'</label>',
                $naviforms->selectfield('comment-status',
                array(
                        0 => 0,
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        4 => -1
                ),
                array(
                        0 => t(64, 'Published'),
                        1 => t(251, 'Private'),
                        2 => t(181, 'Hidden'),
                        3 => t(466, 'Spam'),
                        4 => t(257, 'To review')
                ),
                $item->status,
                '',
                false,
                array(
                        0 => t(360, 'Visible to everybody'),
                        1 => t(359, 'Visible only to Navigate CMS users'),
                        2 => t(358, 'Hidden to everybody'),
                        3 => t(358, 'Hidden to everybody'),
                        4 => t(358, 'Hidden to everybody')
                )
            )
        )
    );

    $events->trigger(
        'comments',
        'edit',
        array(
            'comment' => &$item,
            'navibars' => &$navibars,
            'naviforms' => &$naviforms
        )
    );
										
	return $navibars->generate();
}
?>