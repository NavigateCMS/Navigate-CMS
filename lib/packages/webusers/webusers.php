<?php
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');

function run()
{
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new webuser();
			
	switch($_REQUEST['act'])
	{
		// json data retrieval & operations
		case 'json':
		case 1:
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
					$offset = ($page - 1) * $max;
					$where = ' website = '.$website->id;
					$parameters = array();
										
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
                            $filters = $_REQUEST['filters'];
                            if(is_array($filters))
                            {
                                $filters = json_encode($filters);
                            }
							$where .= navitable::jqgridsearch($filters);
                        }
						else	// single search
                        {
                            $where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
                        }
					}

                    // filter orderby vars
                    if( !in_array($_REQUEST['sord'], array('', 'desc', 'DESC', 'asc', 'ASC')) ||
                        !in_array($_REQUEST['sidx'], array('id', 'avatar', 'username', 'email', 'fullname', 'groups', 'joindate', 'newsletter', 'access'))
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];
				
					$DB->queryLimit(
					    'id,avatar,username,email,fullname,groups,joindate,access,access_begin,access_end,newsletter',
                        'nv_webusers',
                        $where,
                        $orderby,
                        $offset,
                        $max,
                        $parameters
                    );
									
					$dataset = $DB->result();
					$total = $DB->foundRows();

                    $dataset = grid_notes::summary($dataset, 'webuser', 'id');

                    global $webusers_groups_all;
                    $webusers_groups_all = webuser_group::all_in_array();

					//echo $DB->get_last_error();
					
					$out = array();					
											
					for($i=0; $i < count($dataset); $i++)
					{
                        $wug = str_replace('g', '', $dataset[$i]['groups']);
                        $wug = explode(',', $wug);
                        $wug = array_map(
                            function($in)
                            {
                                global $webusers_groups_all;
                                if(empty($in))
                                {
                                    return;
                                }
                                return core_special_chars($webusers_groups_all[$in]);
                            },
                            $wug
                        );

						$blocked = 1;
						if( $dataset[$i]['access'] == 0 ||
                            ( $dataset[$i]['access'] == 2 &&
                                ($dataset[$i]['access_begin']==0 ||$dataset[$i]['access_begin'] < time()) &&
                                ($dataset[$i]['access_end']==0 || $dataset[$i]['access_end'] > time())
                            )
                        )
						{
							$blocked = 0;
						}

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> empty($dataset[$i]['avatar'])? '' : '<img title="'.core_special_chars($dataset[$i]['username']).'" src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.urlencode($dataset[$i]['avatar']).'&amp;disposition=inline&amp;width=32&amp;height=32" />',
                            2 	=> '<div class="list-row" data-blocked="'.$blocked.'">'.core_special_chars($dataset[$i]['username']).'</div>',
                            3 	=> '<div class="list-row" data-blocked="'.$blocked.'" title="'.core_special_chars($dataset[$i]['email']).'">'.core_special_chars($dataset[$i]['email']).'</div>',
                            4	=> core_special_chars($dataset[$i]['fullname']),
							5	=> implode("<br />", $wug),
							6 	=> core_ts2date($dataset[$i]['joindate'], true),
                            7	=> ($dataset[$i]['newsletter']==1? '<img src="img/icons/silk/email_star.png" />' : '<img src="img/icons/silk/cancel.png" />'),
                            8	=> ($blocked==0? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />'),
                            9 	=> $dataset[$i]['_grid_notes_html']
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;

		case 2: // edit/new form
        case 'create':
		case 'edit':
            $webuser_id = 0;
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
                    $webuser_id = $item->id;
					property::save_properties_from_post('webuser', $item->id);
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
                    if(!empty($item->_new_password))
                    {
                        $layout->navigate_notification(t(831, "New password").': <strong>'.$item->_new_password.'</strong>', false, true, 'fa fa-password');
                    }

                    // reload object
                    $item = null;
                    $item = new webuser();
                    $item->load($webuser_id);
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}

				if(!empty($item->id))
                {
                    users_log::action($_REQUEST['fid'], $item->id, 'save', $item->username, json_encode($_REQUEST));
                }
			}
			else
			{
				if(!empty($item->id))
                {
                    users_log::action($_REQUEST['fid'], $item->id, 'load', $item->username);
                }
			}
		
			$out = webusers_form($item);
			break;

        case 'remove':
        case 4:
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
                    $out = webusers_list();
                    users_log::action($_REQUEST['fid'], $item->id, 'remove', $item->username, json_encode($_REQUEST));
                }
                else
                {
                    $layout->navigate_notification(t(56, 'Unexpected error.'), false);
                    $out = webusers_form($item);
                }
            }
            break;

        case 90: // json request: timezones by country
			$timezones = property::timezones($_REQUEST['country']);			
			
			if(empty($timezones))
            {
                $timezones = property::timezones();
            }
			
			echo json_encode($timezones);			
			core_terminate();
			break;

        case 'export':
            // export web users list to a CSV file
			users_log::action($_REQUEST['fid'], 0, 'export', "all", json_encode($_REQUEST));
			webuser::export();
            break;

        case 'username_available':
            $website_id = value_or_default(@$_POST['website_id'], $website->id);
            $available = webuser::available($_REQUEST['username'], $website_id);
            echo json_encode($available);
            core_terminate();
            break;

        case 'webuser_groups_list':
            $out = webuser_groups_list();
            break;

        case 'webuser_groups_json':
            $page   = intval($_REQUEST['page']);
            $max	= intval($_REQUEST['rows']);
            $offset = ($page - 1) * $max;

            // unused
            // $_REQUEST['sidx']
            // $_REQUEST['sord']
            $rs = webuser_group::all();

            $dataset = array();

            foreach($rs as $row)
            {
                $dataset[] = array(
                    'id' => $row->id,
                    'code' => core_special_chars($row->code),
                    'name' => core_special_chars($row->name)
                );
            }

            $total = count($dataset);
            navitable::jqgridJson($dataset, $page, $offset, $max, $total, 'id');

            session_write_close();
            exit;
            break;

        case 'webuser_group_edit':
            $webuser_group = new webuser_group();

            if(!empty($_REQUEST['id']))
            {
                $webuser_group->load(intval($_REQUEST['id']));
            }

            if(isset($_REQUEST['form-sent']))
            {
                $webuser_group->load_from_post();

                try
                {
                    naviforms::check_csrf_token();

                    $ok = $webuser_group->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
					users_log::action($_REQUEST['fid'], $webuser_group->id, 'save_webuser_group', $webuser_group->name, json_encode($_REQUEST));
                }
                catch(Exception $e)
                {
                    $layout->navigate_notification($e->getMessage(), true, true);
                }
            }
			else
			{
				users_log::action($_REQUEST['fid'], $webuser_group->id, 'load_webuser_group', $webuser_group->name, json_encode($_REQUEST));
			}

            $out = webuser_groups_form($webuser_group);
            break;

        case 'webuser_group_delete':
            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
                break;
            }
            else
            {
                $webuser_group = new webuser_group();

                if(!empty($_REQUEST['id']))
                {
                    $webuser_group->load(intval($_REQUEST['id']));
                }

                try
                {
                    $webuser_group->delete();
                    $layout->navigate_notification(t(55, 'Item removed successfully.'), false);
                    $out = webuser_groups_list();
                    users_log::action($_REQUEST['fid'], $webuser_group->id, 'remove_webuser_group', $webuser_group->name, json_encode($_REQUEST));
                }
                catch(Exception $e)
                {
                    $out = $layout->navigate_message("error", t(24, 'Web users').' / '.t(506, 'Groups'), t(56, 'Unexpected error.'));
                }
            }
            break;

        case "remove_old_unconfirmed":
            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
                break;
            }
            else
            {
                $number = webuser::remove_old_unconfirmed_accounts();
                if($number > 0)
                {
                    $layout->navigate_notification(t(524, 'Items removed successfully').' ('.$number.')', false);
                }
                else
                {
                    $layout->navigate_notification(t(645, 'No results found'), false);
                }
                $out = webusers_list();
            }
            break;
					
		case 0: // list / search result
        case 'list':
		default:			
			$out = webusers_list();
			break;
	}
	
	return $out;
}


function webusers_list()
{
    global $events;
    global $layout;

    $fid = core_purify_string($_REQUEST['fid'], true);

    $navibars = new navibars();
	$navitable = new navitable("webusers_list");
	
	$navibars->title(t(24, 'Web users'));

    $extra_actions = array(
        '<a href="?fid='.$fid.'&act=export"><img height="16" align="absmiddle" width="16" src="img/icons/silk/table_save.png"> '.t(475, 'Export').'</a>',
        '<a href="#" onclick="navigate_webusers_remove_old_unconfirmed();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/bin.png"> '.t(776, 'Remove old unconfirmed accounts').'</a>'
    );

    $layout->add_script('
            function navigate_webusers_remove_old_unconfirmed()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=webusers&act=remove_old_unconfirmed&rtk='.$_SESSION['request_token'].'"; }, 
                    "'.t(497, "Do you really want to erase this data?").'", 
                    null, "'.t(35, 'Delete').'"
                );
            }
        ');

    $events->add_actions(
        'webusers',
        array(
            'navibars' => &$navibars
        ),
        $extra_actions
    );

    $navibars->add_actions(
        array(
            '<a href="?fid='.$fid.'&act=webuser_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/group.png"> '.t(506, 'Groups').'</a>'
        )
    );

    $navibars->add_actions(
        array(
            '<a href="?fid='.$fid.'&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
            '<a href="?fid='.$fid.'&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
            'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
    {
        $nv_qs_text = core_purify_string($_REQUEST['navigate-quicksearch'], true);
        $navitable->setInitialURL("?fid=".$fid.'&act=1&_search=true&quicksearch='.$nv_qs_text);
    }
	
	$navitable->setURL('?fid='.$fid.'&act=1');
    $navitable->sortBy('id', 'DESC');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$fid.'&act=2&id=');
    $navitable->enableDelete();
	$navitable->setGridNotesObjectName("webuser");
	
	$navitable->addCol("ID", 'id', "32", "true", "left");
	$navitable->addCol(t(246, 'Avatar'), 'avatar', "32", "true", "center");
	$navitable->addCol(t(1, 'User'), 'username', "80", "true", "left");
	$navitable->addCol(t(44, 'E-Mail'), 'email', "100", "true", "left");
	$navitable->addCol(t(159, 'Name'), 'fullname', "150", "true", "left");
	$navitable->addCol(t(506, 'Groups'), 'groups', "120", "true", "left");
	$navitable->addCol(t(247, 'Date joined'), 'joindate', "60", "true", "left");
    $navitable->addCol(t(652, 'Subscribed'), 'newsletter', "32", "true", "center");
    $navitable->addCol(t(321, 'Allowed'), 'access', "32", "true", "center");
    $navitable->addCol(t(168, 'Notes'), 'note', "32", "false", "center");

    // webuser groups filter
    $navitable->setLoadCallback('
        if($("#jqgh_webusers_list_groups button").length < 1)
        {
            $("#jqgh_webusers_list_groups").prepend("<button>");
            $("#jqgh_webusers_list_groups button").button({
                icon: "ui-icon-gear",
                showLabel: false
            }).css({
                "float": "right",
                "margin-top": "-2px",
                "padding": "5px 10px 10px 0px"                
            }).on("click", webusers_list_choose_groups);
        }
    ');

    $groups = webuser_group::all_in_array();
    $groups_html = array_map(
        function($id, $name)
        {
            return '<li class="level1" data-value="g'.$id.'">'.core_special_chars($name).'</li>';
        },
        array_keys($groups),
        array_values($groups)
    );


    $navibars->add_content('
        <div id="filter_groups_window" style="display: none;">
            <ul data-name="filter_groups_field">'.
                implode("\n", $groups_html).'
            </ul>
        </div>
    ');

    $layout->add_script('
        $("#filter_groups_window ul").jAutochecklist({
            popup: false,
            absolutePosition: true,
            width: 0,
            valueAsHTML: true,
            listWidth: 400,
            listMaxHeight: 400,
            onItemClick: function(nval, li, selected_before, selected_after)
            {
                selected_after = selected_after.join(",");
                var filters = {
                    "groupOp" : "AND",
                    "rules": [
                        {   "field" : "groups",
                            "op" : "in",
                            "data" : selected_after
                        },
                        {   "field" : "username",
                            "op" : "cn",
                            "data" : $("#navigate-quicksearch").val()
                        }
                    ]
                };

                $("#webusers_list").jqGrid("setGridParam", {
                    search: true,
                    postData: { "filters": filters }
                    }
                ).trigger("reloadGrid");
            }
        });');

    $layout->add_script('
        function webusers_list_choose_groups()
        {            
            $("#navigate-quicksearch").parent().on("submit", function(){
                $("#filter_groups_window ul").jAutochecklist("deselectAll");
            });

            $("#filter_groups_window ul").jAutochecklist("open");
            $(".jAutochecklist_list").css({"position": "absolute"});
            $(".jAutochecklist_list").css($("#jqgh_webusers_list_groups button").offset());
            $(".jAutochecklist_dropdown_wrapper").hide();
                        
            $(".jAutochecklist_list").css({
                "border-radius": "8px",
                "margin-left": "-373px",
                "margin-top": "16px"
            });
            $(".jAutochecklist_list").addClass("navi-ui-widget-shadow ui-menu ui-widget ui-widget-content ui-corner-all");

            return false;
        }
    ');

	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}


function webusers_form($item)
{
    global $theme;
	global $layout;
    global $events;
    global $current_version;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we can use media browser in this function
	
	if(empty($item->id))
    {
        $navibars->title(t(24, 'Web users').' / '.t(38, 'Create'));
    }
	else
    {
        $navibars->title(t(24, 'Web users').' / '.t(170, 'Edit').' ['.$item->id.']');
    }

	$navibars->add_actions(
        array(
            '<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'
        )
    );

    $navibars->add_actions(
        array(
            '<a href="?fid=webusers&act=webuser_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/group.png"> '.t(506, 'Groups').'</a>'
        )
    );

    if(!empty($item->id))
    {
        $notes = grid_notes::comments('webuser', $item->id);
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

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=webusers&act=remove&id='.$item->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}
	
	$navibars->add_actions(
        array(
            (!empty($item->id)? '<a href="?fid=webusers&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=webusers&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab('<i class="fa fa-user-circle-o"></i> '.t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->csrf_token());
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(
        '<label>ID</label>',
		'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' )
    );

	$navibars->add_tab_content_row(array(
        '<label>'.t(246, 'Avatar').'</label>',
		$naviforms->dropbox('webuser-avatar', $item->avatar, "image"),
    ));

	$navibars->add_tab_content_row(array(
        '<label>'.t(1, 'User').'</label>',
		$naviforms->textfield('webuser-username', $item->username, false, false, 'autocomplete="off" data-message-not-available="'.t(840, "Not available").'"'),
        '<span id="webuser_username_info" class="ui-icon ui-icon-alert"
               data-message="'.t(839, 'A new password must be set when changing the username. If not provided, a new random password will be assigned.', false, true).'"
               style=" margin-left: 2px; display: none;">				   
		</span>',
    ));

	$navibars->add_tab_content_row(array(
        '<label>'.t(2, 'Password').'</label>',
		'<input type="password" name="webuser-password" autocomplete="new-password" value="" size="32" />',
		'<span class="navigate-form-row-info">'.t(48, "Leave blank to keep the current value").'</span>' )
    );

	$navibars->add_tab_content_row(
		array(
            '<label>'.t(44, 'E-Mail').'</label>',
            $naviforms->textfield('webuser-email', $item->email),
			($item->email_verification_date > 0)?
					'<span class="navigate-form-row-info" title="'.core_ts2date($item->email_verification_date, true).'"><img src="img/icons/silk/tick.png" align="absmiddle" /> '.t(37, "E-Mail confirmed").'</span>' :
					''
		)
    );

	if(!empty($item->joindate))
	{
		$navibars->add_tab_content_row(array(
            '<label>'.t(247, 'Date joined').'</label>',
			core_ts2date($item->joindate, true),
        ));
	}

    if(!empty($item->lastseen))
	{
		$navibars->add_tab_content_row(array(
            '<label>'.t(563, 'Last seen').'</label>',
			core_ts2date($item->lastseen, true),
        ));
	}
	
	$navibars->add_tab_content_row(array(
        '<label>'.t(249, 'Newsletter').'</label>',
		$naviforms->checkbox('webuser-newsletter', $item->newsletter),
    ));

	$webuser_access = array(
		'0' =>  t(321, "Allowed"),
		'1' =>  t(47, "Blocked"),
		'2' =>  t(622, "Date range")
	);
	
	$navibars->add_tab_content_row(array(
        '<label>'.t(364, 'Access').'</label>',
		$naviforms->selectfield(
			'webuser-access', 
			array_keys($webuser_access), 
			array_values($webuser_access), 
			$item->access,
			'navigate_webusers_change_access();'
		)
    ));

	if(empty($item->access_begin))
    {
        $item->access_begin = '';
    }

    $navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<img src="img/icons/silk/date_go.png" /> '.t(623, 'Begin').'</label>',
			$naviforms->datefield('webuser-access-begin', $item->access_begin, true, ' width:200px; ')
        )
    );

	if(empty($item->access_end))
    {
        $item->access_end = '';
    }

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;&nbsp;<img src="img/icons/silk/date_delete.png" /> '.t(624, 'End').'</label>',
			$naviforms->datefield('webuser-access-end', $item->access_end, true, ' width:200px; ')
        )
    );

	// private_comment deprecated in NV 2.0
	if(!empty($item->private_comment))
	{
	    $navibars->add_tab_content_row(array(
	        '<label>'.t(538, 'Private comment').'</label>',
	        $naviforms->textarea('webuser-private_comment', $item->private_comment)
	        )
	    );
	}

    $navibars->add_tab('<i class="fa fa-group"></i> '.t(506, "Groups"));

    $webuser_groups = webuser_group::all_in_array();

    $navibars->add_tab_content_row(
        array(
	        '<label>'.t(506, "Groups").'</label>',
            $naviforms->multiselect(
                'webuser-groups',
                array_keys($webuser_groups),
                array_values($webuser_groups),
                $item->groups
            )
        )
    );

	$navibars->add_tab('<i class="fa fa-user-secret"></i> '.t(308, "Personal"));
											
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(159, 'Name').'</label>',
            $naviforms->textfield('webuser-fullname', $item->fullname)
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(160, 'Type').' / '.t(304, 'Gender').'</label>',
            $naviforms->buttonset(
                'webuser-gender',
                array(
                    'male' => '<img src="img/icons/silk/male.png" align="absbottom" /> '.t(305, 'Male'),
                    'female' => '<img src="img/icons/silk/female.png" align="absbottom" /> '.t(306, 'Female'),
                    'company' => '<img src="img/icons/silk/building.png" align="absbottom" /> '.t(592, 'Company'),
                    '' => '<img src="img/icons/silk/help.png" align="absbottom" /> '.t(307, 'Unspecified')
                ),
                $item->gender
            )
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(248, 'Birthdate').'</label>',
		    $naviforms->datefield('webuser-birthdate', $item->birthdate, false),
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(827, 'NIN').'</label>',
		    $naviforms->textfield('webuser-nin', $item->nin),
            '<span class="navigate-form-row-info">'.t(778, 'National identification number').'</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(592, 'Company').'</label>',
		    $naviforms->textfield('webuser-company', $item->company),
        )
    );

	$countries = property::countries();
    $country_names = array_values($countries);
    $country_codes = array_keys($countries);
    // include "country not defined" item
    array_unshift($country_codes, '');
    array_unshift($country_names, '('.t(307, "Unspecified").')');

	$navibars->add_tab_content_row(array(
        '<label>'.t(224, 'Country').'</label>',
        $naviforms->selectfield("webuser-country", $country_codes, $country_names, strtoupper($item->country))
    ));

	$navibars->add_tab_content_row(array(
        '<label>'.t(473, 'Region').'</label>',
        $naviforms->countryregionfield("webuser-region", $item->region, 'webuser-country')
    ));
										
	$timezones = property::timezones();
	
	if(empty($item->timezone))
    {
        $item->timezone = date_default_timezone_get();
    }

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(97, 'Timezone').'</label>',
			$naviforms->selectfield("webuser-timezone", array_keys($timezones), array_values($timezones), $item->timezone)
        )
    );
										
	$layout->add_script('
		var webuser_country = "'.$item->country.'";		
	');											

	// Language selector
	$data = language::language_names(false);

	if(empty($item->id))
    {
        // default value for new accounts
        $item->language = 'en';
    }
		
	$select = $naviforms->selectfield('webuser-language', array_keys($data), array_values($data), $item->language);
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(46, 'Language').'</label>',
		    $select
        )
    );

	$navibars->add_tab('<i class="fa fa-address-card"></i> '.t(233, "Address"));
											
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(233, 'Address').'</label>',
			$naviforms->textfield('webuser-address', $item->address)
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(319, 'Location').'</label>',
			$naviforms->textfield('webuser-location', $item->location)
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(318, 'Zip code').'</label>',
			$naviforms->textfield('webuser-zipcode', $item->zipcode, NULL, NULL, 'maxlength="64"')
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(320, 'Phone').'</label>',
			$naviforms->textfield('webuser-phone', $item->phone)
        )
    );
											
	$navibars->add_tab('<i class="fa fa-share"></i> '.t(309, "Social"));
											
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(177, 'Website').'</label>',
			$naviforms->textfield('webuser-social_website', $item->social_website)
        )
    );

    if(!empty($theme->webusers['properties']))
    {
        $properties_html = navigate_property_layout_form('webuser', $theme->name, 'webuser', $item->id);

        if(!empty($properties_html))
        {
            $navibars->add_tab('<i class="fa fa-pencil-square-o"></i> '.t(77, "Properties"));
            $navibars->add_tab_content($properties_html);
        }
    }

	if(!empty($item->id))
    {
        $layout->navigate_notes_dialog('webuser', $item->id);
    }

    $layout->add_script('    	
	    $.ajax({
	        type: "GET",
	        dataType: "script",
	        cache: true,
	        url: "lib/packages/webusers/webusers.js?r='.$current_version->revision.'",
	        complete: function( data, textStatus )
	        {
                if(typeof navigate_webusers_onload == "function")
                {    
                    navigate_webusers_onload();
                }
	        }
	    });
	');

    $events->trigger(
        'webuser',
        'edit',
        array(
            'webuser' => &$item,
            'navibars' => &$navibars,
            'naviforms' => &$naviforms
        )
    );
											
	return $navibars->generate();
}


function webuser_groups_list()
{
    $navibars = new navibars();
    $navitable = new navitable('webuser_groups_list');

    $navibars->title(t(24, 'Web users').' / '.t(506, 'Groups'));

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/user.png"> '.t(24, 'Web users').'</a>'
        )
    );

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=webuser_group_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
            '<a href="?fid='.$_REQUEST['fid'].'&act=webuser_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
        )
    );

    $navitable->setURL('?fid='.$_REQUEST['fid'].'&act=webuser_groups_json');
    $navitable->sortBy('id');
    $navitable->setDataIndex('id');
    $navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=webuser_group_edit&id=');

    $navitable->addCol("ID", 'id', "80", "true", "left");
    $navitable->addCol(t(237, 'Code'), 'code', "100", "true", "left");
    $navitable->addCol(t(159, 'Name'), 'name', "300", "true", "left");

    $navibars->add_content($navitable->generate());

    return $navibars->generate();
}

function webuser_groups_form($item)
{
    global $layout;

    $navibars = new navibars();
    $naviforms = new naviforms();

    if(empty($item->id))
    {
        $navibars->title(t(24, 'Web users').' / '.t(506, 'Groups').' / '.t(38, 'Create'));
    }
    else
    {
        $navibars->title(t(24, 'Web users').' / '.t(506, 'Groups').' / '.t(170, 'Edit').' ['.$item->id.']');
    }

    if(empty($item->id))
    {
        $navibars->add_actions(
            array(
                '<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'
            )
        );
    }
    else
    {
        $navibars->add_actions(
            array(
                '<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
                '<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid='.$_REQUEST['fid'].'&act=webuser_group_delete&id='.$item->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
    }

    $navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/user.png"> '.t(24, 'Web users').'</a>' ) );

    $navibars->add_actions(
        array(
            (!empty($item->id)? '<a href="?fid='.$_REQUEST['fid'].'&act=webuser_group_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
            '<a href="?fid='.$_REQUEST['fid'].'&act=webuser_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
        )
    );

    $navibars->form();

    $navibars->add_tab(t(43, "Main"));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->csrf_token());
    $navibars->add_tab_content($naviforms->hidden('id', $item->id));

    $navibars->add_tab_content_row(array(	'<label>ID</label>',
        '<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(159, 'Name').'</label>',
            $naviforms->textfield('name', $item->name)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(237, 'Code').'</label>',
            $naviforms->textfield('code', $item->code)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(334, 'Description').'</label>',
            $naviforms->textarea('description', $item->description)
        )
    );

    return $navibars->generate();
}
?>