<?php
require_once(NAVIGATE_PATH.'/lib/packages/functions/nv_function.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	
	$out = '';
	$item = new nv_function();
			
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
					$offset = ($page - 1) * $max;
					$where = " 1=1 ";
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
                            $where .= navitable::jqgridsearch($_REQUEST['filters']);
                        }
						else	// single search
                        {
                            $where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
                        }
					}

                    // filter orderby vars
                    if( !in_array($_REQUEST['sord'], array('', 'desc', 'DESC', 'asc', 'ASC')) ||
                        !in_array($_REQUEST['sidx'], array('id', 'category', 'codename', 'icon', 'lid', 'enabled'))
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];

				
					$DB->queryLimit(
					    'id,lid,category,codename,icon,enabled',
                        'nv_functions',
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
											
					for($i=0; $i < count($dataset); $i++)
					{													
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $dataset[$i]['category'],
							2	=> core_special_chars($dataset[$i]['codename']),
							3	=> '<img src="'.NAVIGATE_URL.'/'.value_or_default($dataset[$i]['icon'], 'img/transparent.gif').'" />',
							4 	=> '['.$dataset[$i]['lid'].'] '.core_special_chars(t($dataset[$i]['lid'], $dataset[$i]['lid'])),
							5	=> (($dataset[$i]['enabled']==1)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />')
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
                    naviforms::check_csrf_token();

					$item->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = functions_form($item);
			break;

        case 'delete':
		case 4:
            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
            }
            else if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = functions_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = functions_form($item);
				}
			}
			break;
					
		case 0: // list / search result
		default:			
			$out = functions_list();
			break;
	}
	
	return $out;
}

function functions_list()
{
    $fid = core_purify_string($_REQUEST['fid'], true);

	$navibars = new navibars();
	$navitable = new navitable("functions_list");
	
	$navibars->title(t(240, 'Functions'));

	$navibars->add_actions(
	    array(
	        '<a href="?fid='.$fid.'&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid='.$fid.'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
    {
        $nv_qs_text = core_purify_string($_REQUEST['navigate-quicksearch'], true);
        $navitable->setInitialURL("?fid=".$fid.'&act=json&_search=true&quicksearch='.$nv_qs_text);
    }
	
	$navitable->setURL('?fid='.$fid.'&act=1');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$fid.'&act=edit&id=');
	
	$navitable->addCol("ID", 'id', "80", "true", "left");	
	$navitable->addCol(t(78, 'Category'), 'category', "100", "true", "left");	
	$navitable->addCol(t(237, 'Code'), 'codename', "100", "true", "left");		
	$navitable->addCol(t(242, 'Icon'), 'icon', "50", "true", "center");		
	$navitable->addCol(t(67, 'Title'), 'lid', "200", "true", "left");	
	$navitable->addCol(t(65, 'Enabled'), 'enabled', "80", "true", "center");		
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function functions_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item->id))
		$navibars->title(t(240, 'Functions').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(240, 'Functions').' / '.t(170, 'Edit').' ['.$item->id.']');		

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
                    function() { window.location.href = "?fid='.$_REQUEST['fid'].'&act=delete&id='.$item->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');

	}
	
	$navibars->add_actions(
	    array(
	        (!empty($item->id)? '<a href="?fid=functions&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
            '<a href="?fid=functions&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
            'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));
    $navibars->add_tab_content($naviforms->csrf_token());
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(78, 'Category').'</label>',
			$naviforms->textfield('category', $item->category),
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(237, 'Code').'</label>',
			$naviforms->textfield('codename', $item->codename),
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(242, 'Icon').'</label>',
			$naviforms->textfield('icon', $item->icon),
			'<img src="'.NAVIGATE_URL.'/'.value_or_default($item->icon, 'img/transparent.gif').'" align="absmiddle" />'
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>#'.t(67, 'Title').' (lid)</label>',
			$naviforms->decimalfield('lid', $item->lid, 0),
			(empty($item->lid)? '' : '<em>'.$item->lid.': <strong>'.core_special_chars(t($item->lid, $item->lid)).'</strong></em>')
        )
    );
										
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(65, 'Enabled').'</label>',
			$naviforms->checkbox('enabled', $item->enabled),
        )
    );

	return $navibars->generate();
}
?>