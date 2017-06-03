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
				
					$DB->queryLimit('id,lid,category,codename,icon,enabled', 
									'nv_functions', 
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
							1	=> $dataset[$i]['category'],
							2	=> $dataset[$i]['codename'],
							3	=> '<img src="'.NAVIGATE_URL.'/'.$dataset[$i]['icon'].'" />',		
							4 	=> '['.$dataset[$i]['lid'].'] '.t($dataset[$i]['lid'], $dataset[$i]['lid']),							
							5	=> (($dataset[$i]['enabled']==1)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />')
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
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = functions_form($item);
			break;
					
		case 4: // remove 
			if(!empty($_REQUEST['id']))
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
	$navibars = new navibars();
	$navitable = new navitable("functions_list");
	
	$navibars->title(t(240, 'Functions'));

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
        $delete_html[] = '$("#navigate-delete-dialog").removeClass("hidden");';
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
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=functions&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=functions&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(78, 'Category').'</label>',
											$naviforms->textfield('category', $item->category),
										));																				

	$navibars->add_tab_content_row(array(	'<label>'.t(237, 'Code').'</label>',
											$naviforms->textfield('codename', $item->codename),
										));																				

	$navibars->add_tab_content_row(array(	'<label>'.t(242, 'Icon').'</label>',
											$naviforms->textfield('icon', $item->icon),
											'<img src="'.NAVIGATE_URL.'/'.$item->icon.'" align="absmiddle" />'
										));																				

	$navibars->add_tab_content_row(array(	'<label>#'.t(67, 'Title').' (lid)</label>',
											$naviforms->textfield('lid', $item->lid),
											(empty($item->lid)? '' : '<em>'.$item->lid.': <strong>'.t($item->lid, $item->lid).'</strong></em>')
										));																				
										
	$navibars->add_tab_content_row(array(	'<label>'.t(65, 'Enabled').'</label>',
											$naviforms->checkbox('enabled', $item->enabled),
										));	

	return $navibars->generate();
}
?>