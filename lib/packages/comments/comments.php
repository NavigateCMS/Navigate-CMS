<?php
require_once(NAVIGATE_PATH.'/lib/packages/comments/comment.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');

function run()
{
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new comment();
			
	switch($_REQUEST['act'])
	{
        case 'json':
		case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
                    if(naviforms::check_csrf_token('header'))
                    {
                        $ids = $_REQUEST['ids'];
                        foreach($ids as $id)
                        {
                            $item->load($id);
                            $item->delete();
                        }
                        echo json_encode(true);
                    }
                    else
                    {
                        echo json_encode(false);
                    }
					break;
					
				default: // list or search	
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = max(0, ($page - 1) * $max);
					$parameters = array();

					$where = ' website = '.$website->id;

					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
                        {
                            list($qs_where, $qs_params) = $item->quicksearch($_REQUEST['quicksearch']);
                            $where .= $qs_where;
                            $parameters = array_merge($parameters, $qs_params);
                        }
						else if(isset($_REQUEST['filters']))
                        {
                            $where .= navitable::jqgridsearch($_REQUEST['filters']);
                        }
						else	// single search
                        {
                            $where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
                        }
					}

					// filter orderby vars
					if( !in_array($_REQUEST['sord'], array('', 'desc', 'DESC', 'asc', 'ASC')) ||
                        !in_array($_REQUEST['sidx'], array('id', 'object_type', 'object_id', 'date_created', 'user', 'message', 'status'))
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];

					$DB->queryLimit(
                        'id,object_type,object_id,user,email,date_created,status,message',
                        'nv_comments',
                        $where,
                        $orderby,
                        $offset,
                        $max,
                        $parameters
                    );
								
					$dataset = $DB->result();
					$total = $DB->foundRows();
					
					//echo $DB->get_last_error();
					
					$out = array();

					$object_types = array(
					    'item' => t(630, "Element"),
                        'product' => t(198, "Product")
                    );
					
					$permissions = array(	
						   -1 => '<img src="img/icons/silk/new.png" align="absmiddle" /> '.t(257, 'To review'),
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
                        if($dataset[$i]['object_type'] == "item")
                        {
                            $item = new item();
                        }
                        else if($dataset[$i]['object_type'] == "product")
                        {
                            $item = new product();
                        }

                        $item->load($dataset[$i]['object_id']);
                        $title = $item->dictionary[$website->languages_list[0]]['title'];

						$message = core_string_clean($dataset[$i]['message']);
						$message = core_string_cut($message, 60, '&hellip;');

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $object_types[$dataset[$i]['object_type']],
							2	=> $title,
							3	=> core_ts2date($dataset[$i]['date_created'], true),
							4	=> core_special_chars((empty($dataset[$i]['user'])? $dataset[$i]['email'] : $webuser)),
							5 	=> core_special_chars($message),
							6	=> $permissions[$dataset[$i]['status']]
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;
		
		case 2: // edit/new form
		case 'edit':
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
			}
		
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
                    naviforms::check_csrf_token();
					$item->save();
                    property::save_properties_from_post('comment', $item->id);
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
				if(!empty($item->id))
					users_log::action($_REQUEST['fid'], $item->id, 'save', $item->name, json_encode($_REQUEST));
			}
			else
			{
				if(!empty($item->id))
					users_log::action($_REQUEST['fid'], $item->id, 'load', $item->name);
			}
		
			$out = comments_form($item);
			break;
					
		case 4:
        case 'delete':
		case 'remove':
            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
                break;
            }
            else if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = comments_list();

					if(!empty($item->id))
                    {
                        users_log::action($_REQUEST['fid'], $item->id, 'remove', $item->name, json_encode($_REQUEST));
                    }
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = comments_form($item);
				}
			}
			break;

        case 'remove_spam':
            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
                break;
            }
            else
            {
                $count = comment::remove_spam();
                $layout->navigate_notification(t(524, 'Items removed successfully').': <strong>'.$count.'</strong>', false);
                $out = comments_list();
			    users_log::action($_REQUEST['fid'], $website->id, 'remove_spam', "", json_encode($_REQUEST));
            }
            break;

        case 'json_find_webuser': // json find webuser by name (for "user" autocomplete)
            $DB->query(
                'SELECT id, username as text
                      FROM nv_webusers
                     WHERE username LIKE :username
                     AND website = :wid
                  ORDER BY username ASC
                     LIMIT 30',
                'array',
                array(
                    ':wid' => $website->id,
                    ':username' => '%' . $_REQUEST['username'] . '%'
                )
            );

            $rows = $DB->result();
            $total = $DB->foundRows();
            echo json_encode(array('items' => $rows, 'totalCount' => $total));

            core_terminate();
            break;

        case 'json_find_comment': // json find comment by text search (for "in reply to" autocomplete)
            $DB->query(
                'SELECT c.id, c.date_created, c.name, u.username, c.message
                      FROM nv_comments c
                      LEFT JOIN nv_webusers u ON c.user = u.id
                     WHERE
                        c.website = :wid AND
                        c.object_type = :object_type AND
                        c.object_id = :object_id AND
                        c.date_created <= :maxdate AND
                        c.id <> :exclude AND						     
                        (   c.name LIKE :text OR
                            c.message LIKE :text OR
                            u.username LIKE :text
                        )                          
                  ORDER BY c.date_created DESC
                     LIMIT 30',
                'array',
                array(
                    ':wid' => $website->id,
                    ':object_type' => $_REQUEST['object_type'],
                    ':object_id' => $_REQUEST['object_id'],
                    ':date_created' => $_REQUEST['maxdate'],
                    ':exclude' => $_REQUEST['exclude'],
                    ':text' => '%' . $_REQUEST['search'] . '%'
                )
            );

            $rows = $DB->result();
            $total = $DB->foundRows();

            for($r=0; $r < count($rows); $r++)
                $rows[$r]['text'] = '<span title="'.core_string_cut($rows[$r]['message'], 100).'"><i class="fa fa-user"></i> '.$rows[$r]['name'].$rows[$r]['username'].' <i class="fa fa-clock-o"></i> '.core_ts2date($rows[$r]['date_created'], true).'</span>';

            echo json_encode(array('items' => $rows, 'totalCount' => $total));

            core_terminate();
            break;

        case 'json_get_comment': // json get comment by ID
            $DB->query(
                'SELECT c.*
                      FROM nv_comments c
                      LEFT JOIN nv_webusers u ON c.user = u.id
                     WHERE
                        c.website = '.$website->id.' AND
                        c.id = '.intval($_REQUEST['id'])
            );

            $comment = $DB->first();

            echo json_encode($comment);

            core_terminate();
            break;

        case "find_object_titles":
            // json search title request (for "item" autocomplete)
			$DB->query(
			    'SELECT DISTINCT node_id as id, text as label, text as value
                      FROM nv_webdictionary
                      WHERE node_type = :object_type
                       AND subtype = "title"
                       AND website = :wid 
                       AND text LIKE :text
                      ORDER BY text ASC
                      LIMIT 30',
                    'array',
                    array(
                        ':object_type' => $_REQUEST['object_type'],
                        ':wid' => $website->id,
                        ':text' => '%'.$_REQUEST['title'].'%'
                    )
                    // AND lang = '.protect($_REQUEST['lang']).'
            );
						
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
    global $layout;

	$navibars = new navibars();
	$navitable = new navitable("comments_list");
	
	$navibars->title(t(250, 'Comments'));

    $navibars->add_actions(	array(
        '<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/comments_delete.png"> '.t(522, 'Remove Spam').'</a>'
        )
    );

    $layout->add_script('
       function navigate_delete_dialog()
       {
           navigate_confirmation_dialog(
               function() 
               {
                    window.location.href = "?fid='.$_REQUEST['fid'].'&act=remove_spam&rtk='.$_SESSION['request_token'].'";
               }, 
               "'.t(60, 'Do you really want to delete the selected items?').
                "<br /><br />".
                t(523, 'This function can NOT be undone.').
                '", 
                "'.t(522, 'Remove Spam').'", 
                "'.t(35, 'Delete').'"
           );
       }
   ');

	$navibars->add_actions(	array(
        '<a href="?fid='.$_REQUEST['fid'].'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
		'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
		'search_form' )
    );
	
	if($_REQUEST['quicksearch']=='true')
    {
        $navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=json&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
    }
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=json');
	$navitable->sortBy('date_created', 'desc');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=edit&id=');
	$navitable->enableDelete();	
	
	$navitable->addCol("ID", 'id', "50", "true", "left");
	$navitable->addCol(t(160, 'Type'), 'object_type', "50", "true", "left");
	$navitable->addCol(t(9, 'Content'), 'object_id', "220", "true", "left");
	$navitable->addCol(t(226, 'Date created'), 'date_created', "80", "true", "left");
	$navitable->addCol(t(1, 'User'), 'user', "100", "true", "left");		
	$navitable->addCol(t(54, 'Text'), 'message', "250", "true", "left");
	$navitable->addCol(t(68, 'Status'), 'status', "60", "true", "center");

	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function comments_form($item)
{
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
                '<a href="?fid=comments&act=edit&reply_to='.$item->id.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/comments_add.png"> '.t(747, 'Reply').'</a>'
            )
        );

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
                    function() { window.location.href = "?fid='.$_REQUEST['fid'].'&act=remove&id='.$item->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}
	
	$navibars->add_actions(
		array(
			(!empty($item->id)? '<a href="?fid=comments&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=comments&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
		)
	);

	if(empty($item->id) && !empty($_REQUEST['reply_to']))
    {
        $c = new comment();
        $c->load($_REQUEST['reply_to']);

        if($c->website == $website->id)
        {
            // we are creating a new comment in reply to another comment
            $item->object_type = $c->object_type;
            $item->object_id = $c->object_id;
            $item->reply_to = $c->id;
        }
    }

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));
    $navibars->add_tab_content($naviforms->csrf_token());
	
	$navibars->add_tab_content_row(array(
        '<label>ID</label>',
        '<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
        )
    );

	$navibars->add_tab_content_row(array(
            '<label>'.t(160, "Type").'</label>',
            $naviforms->selectfield(
                "comment-object_type",
                array("item", "product"),
                array(t(630, "Element"), t(198, "Product")),
                $item->object_type,
                'comment_object_type_changed(this);'
            )
        )
    );

	$layout->add_script('
        function comment_object_type_changed()
        {
            $("#comment-object_id").val(""); 
            $("#comment-object-text").val("");
            
            $("label[data-object_type=item]").hide();
            $("label[data-object_type=product]").hide();
            $("label[data-object_type="+$("#comment-object_type").val()+"]").show();
        }
	');

	$navibars->add_tab_content($naviforms->hidden('comment-object_id', $item->object_id));

	if($item->object_id > 0)
	{
	    if($item->object_type == "product")
        {
            $content = new product();
        }
	    else
        {
            $content = new item();
        }

		$content->load($item->object_id);
		$title = $content->dictionary[$website->languages_list[0]]['title'];
	}

	$navibars->add_tab_content_row(
		array(
			'<label data-object_type="item">'.t(9, 'Content').'</label>'.
            '<label data-object_type="product" style="display: none;">'.t(198, 'Product').'</label>',
			$naviforms->textfield('comment-object-text', $title),
		)
	);
																														
	$layout->add_script('
        if($("#comment-object_type").val()=="product")
        {
            $("label[data-object_type=item]")
                .hide()
                .next().show();
        }
	
		$("#comment-object-text").autocomplete(
		{
			source: function(request, response)
			{						
				$.ajax(
					{
						url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=comments&act=find_object_titles",
						dataType: "json",
						method: "GET",
						data: {	
                            "title": request.term,
                            "lang": "'.$website->languages[0].'",
                            "object_type": $("#comment-object_type").val(),
                            "nd": new Date().getTime()
                        },
						success: function( data ) 
						{
							response( data );
						}
					});
			},
			minLength: 1,
			select: function(event, ui) 
			{
				$("#comment-object_id").val(ui.item.id);
			}
		});
	');	


	$webuser_id = '';
	if(!empty($item->user))
	{
		$webuser_username = $DB->query_single('username', 'nv_webusers', ' id = '.$item->user);
		if(!empty($webuser_username))
		{
			$webuser_username = array($webuser_username);
			$webuser_id = array($item->user);
		}
	}

	$navibars->add_tab_content_row(array(
        '<label>'.t(1, 'User').'</label>',
		$naviforms->selectfield('comment-user', $webuser_id, $webuser_username, $item->user, null, false, null, null, false),
        '<span style="display: none;" id="comment-user-helper">'.t(535, "Find user by name").'</span>'
	));

    $layout->add_script('
        $("#comment-user").select2(
        {
            placeholder: $("#comment-user-helper").text(),
            minimumInputLength: 1,
            ajax: {
                url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'" + "&act=json_find_webuser",
                dataType: "json",
                delay: 100,
                data: function (params)
                {
                    return {
                        username: params.term,
                        nd: new Date().getTime(),
                        page_limit: 30, // page size
                        page: params.page // page number
                    };
                },
		        processResults: function (data, params)
		        {
		            params.page = params.page || 1;
		            return {
						results: data.items,
						pagination: { more: (params.page * 30) < data.total_count }
					};
                }
            },
            templateSelection: function(row)
			{
				if(row.id)
					return row.text + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>";
				else
					return row.text;
			},
			escapeMarkup: function (markup) { return markup; },
            triggerChange: true,
            allowClear: true
        });
    ');

	$navibars->add_tab_content_row(array(
        '<label>'.t(159, 'Name').'</label>',
        $naviforms->textfield('comment-name', $item->name))
    );

	$navibars->add_tab_content_row(array(
        '<label>'.t(44, 'E-Mail').'</label>',
        $naviforms->textfield('comment-email', $item->email))
    );

	$navibars->add_tab_content_row(array(
        '<label>'.t(177, 'Website').'</label>',
        $naviforms->textfield('comment-url', $item->url))
    );


    $reply_to_comment = '';
    if(empty($item->reply_to))
    {
        $item->reply_to = '';
    }
    else
    {
        $c = new comment();
        $c->load($item->reply_to);
        $reply_to_comment = $c->author_name().'&nbsp;&nbsp;&nbsp;'.core_ts2date($c->date_created, true);
    }

    $navibars->add_tab_content_row(array(
        '<label>'.t(649, 'In reply to').'</label>',
        $naviforms->selectfield('comment-reply_to', array($item->reply_to), array($reply_to_comment), $item->reply_to, null, false, null, null, false),
        '<img style="cursor: pointer;" id="comment-reply_to-open_window" height="16" align="absmiddle" width="16" src="img/icons/silk/application_xp.png" />'
    ));

    if(empty($item->date_created))
    {
        $item->date_created = time();
    }

    $layout->add_script('
        $("#comment-reply_to").select2(                                                         
        {
            placeholder: "",
            minimumInputLength: 1,
            ajax: {
                url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=comments&act=json_find_comment",
                dataType: "json",
                delay: 100,
                data: function (params)
                {
                    return {
                        search: params.term,
                        object_id: $("#comment-object_id").val(), 
                        object_type: $("#comment-object_type").val(),
                        maxdate: '.($item->date_created + 0).',
                        exclude: '.($item->id + 0).',
                        nd: new Date().getTime(),
                        page_limit: 30, // page size
                        page: params.page // page number
                    };
                },
		        processResults: function (data, params)
		        {	        
		            params.page = params.page || 1;
		            return {
						results: data.items,
						pagination: { more: (params.page * 30) < data.total_count }
					};
                }
            },
            templateSelection: function(row)
			{			
				if(row.id)
					return row.text + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>";
				else
					return row.text;
			},
			escapeMarkup: function (markup) { return markup; },
            triggerChange: true,
            allowClear: true
        });
        
        $("#comment-reply_to-open_window").on("click", function()
        {      
            var in_reply_to = $("#comment-reply_to").val();
            var title = $("#select2-comment-reply_to-container").html();
            title = title.replace(/>×/, ">");
             
            if(in_reply_to && in_reply_to > 0)
            {
                $.getJSON(
                    "?fid=comments&act=json_get_comment&id=" + in_reply_to,
                    function(data)
                    {
                        $("<div>"+data.message+"</div>").dialog({
                            title: title,
                            width: 480,
                            height: 320,
                            classes: 
                            {
                                "ui-dialog": "navi-ui-widget-shadow"
                            }
                        });
                    }
                );
            }            
        });
    ');
									
	$navibars->add_tab_content_row(array(
        '<label>'.t(54, 'Text').'</label>',
        $naviforms->textarea('comment-message', $item->message, 10)
    ));
									
	if(!empty($item->ip))
	{					
		$navibars->add_tab_content_row(array(
            '<label>IP</label>',
            $item->ip
        ));
	}

    $navibars->add_tab_content_row(array(
        '<label>'.t(652, 'Subscribed').'</label>',
        $naviforms->checkbox('comment-subscribed', $item->subscribed)
    ));


	if(empty($item->date_created))
    {
        $item->date_created = time();
    }

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(226, 'Date created').'</label>',
            $naviforms->datefield('comment-date_created', $item->date_created, true)
        )
    );
	
	if($item->date_modified > 0)
	{
		$navibars->add_tab_content_row(
            array(
                '<label>'.t(227, 'Date modified').'</label>',
			    core_ts2date($item->date_modified, true),
                (empty($item->last_modified_by)? '' : '('.user::username_of($item->last_modified_by).')')
            )
        );
	}
			
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(68, 'Status').'</label>',
            $naviforms->selectfield(
                'comment-status',
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

    if(!empty($item->object_id))
    {
        $template = $item->element_template();
        $properties_html = navigate_property_layout_form('comment', $template, 'comment', $item->id);

        if(!empty($properties_html))
        {
            $navibars->add_tab(t(77, "Properties"));
            $navibars->add_tab_content($properties_html);
        }
    }

    $events->trigger(
        'comment',
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