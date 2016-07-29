<?php
require_once(NAVIGATE_PATH.'/lib/packages/backups/backup.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new backup();
			
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
					$where = " i.website = ".$website->id;
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $item->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}
								
					$sql = ' SELECT SQL_CALC_FOUND_ROWS i.*
							   FROM nv_backups i
							  WHERE '.$where.'	
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;	
				
					if(!$DB->query($sql, 'array'))
					{
						throw new Exception($DB->get_last_error());	
					}
					
					$dataset = $DB->result();	
					$total = $DB->foundRows();					
					
					$out = array();		
					
					if(empty($dataset)) $rows = 0;
					else				$rows = count($dataset);
	
					for($i=0; $i < $rows; $i++)
					{						
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1 	=> core_ts2date($dataset[$i]['date_created'], true),
							2 	=> $dataset[$i]['title'],
							3 	=> core_bytes($dataset[$i]['size']),
							4	=> backup::status($dataset[$i]['status'])
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			core_terminate();
			break;
		
		case 2: // edit/new form	
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
			}
							
			if($_REQUEST['form-sent']=='true')
			{						
				$item->load_from_post();
								
				try
				{
                    // update an existing backup
					$item->save();
					$id = $item->id;

                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = backups_form($item);
			break;
			
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = backups_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = webdictionary_list();
				}
			}
			break;

        case 'backup':
            if(!empty($_REQUEST['id']))
			{
                // trick to generate a underground process ;)
                @set_time_limit(0);
                @ignore_user_abort(true);
                $foo = str_pad('Navigate CMS ', 2048, 'Navigate CMS  ');

                header("HTTP/1.1 200 OK");
                header("Content-Length: ".strlen($foo));
                echo $foo;
                header('Connection: close');

                ob_end_flush();
                ob_flush();
                flush();
                session_write_close();
                // now the process is running in the server, the client thinks the http request has finished
                
				$item->load(intval($_REQUEST['id']));
                $item->backup();
			}
            core_terminate();
            break;

        case 'restore':
            // TO DO: Restore
            break;

        case 'download':
            // download backup
            $item->load(intval($_REQUEST['id']));

			ob_end_flush();

            header('Content-type: application/zip');
			header("Content-Length: ".filesize(NAVIGATE_PRIVATE.$item->file));
			header('Content-Disposition: attachment; filename="'.basename($item->file).'"');

			readfile(NAVIGATE_PRIVATE.$item->file);

            core_terminate();
            break;
			
		case 0: // list / search result
		default:			
			$out = backups_list();
			break;
	}
	
	return $out;
}

function backups_list()
{
	$navibars = new navibars();
	$navitable = new navitable("backups_list");
	
	$navibars->title(t(329, 'Backups'));

    $navibars->add_actions(
        array(	'<a href="#" onclick="navigate_restore_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/database_refresh.png"> '.t(412, 'Restore').'</a> ' )
    );

	$navibars->add_actions(	array(	'<a href="?fid=backups&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid=backups&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=backups&act=1&_search=true&quicksearch=".$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid=backups&act=1');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=backups&act=2&id=');
	
	$navitable->addCol("ID", 'id', "80", "true", "left");	
	$navitable->addCol(t(196, 'Date and time'), 'date_created', "150", "true", "center");

    $navitable->addCol(t(67, 'Title'), 'title', "400", "true", "left");
	
	$navitable->addCol(t(409, 'Size'), 'size', "80", "true", "center");
	$navitable->addCol(t(68, 'Status'), 'status', "150", "true", "left");

	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
}

function backups_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we can use media browser in this function
	
	if(empty($item->id))
		$navibars->title(t(329, 'Backups').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(329, 'Backups').' / '.t(170, 'Edit').' ['.$item->id.']');		

	if(empty($item->id))
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/database_save.png"> '.t(410, 'Begin backup').'</a>'	)
									);
	}
	else
	{
        if($item->status=='completed')
        {
            $navibars->add_actions(		
	            array(	'<a href="#" onclick="navigate_restore_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/database_refresh.png"> '.t(412, 'Restore').'</a> ' )
            );
        }

        $navibars->add_actions(		
	        array(  '<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
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
									window.location.href = "?fid=backups&act=4&id='.$item->id.'";
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=backups&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=backups&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

    $navibars->add_tab_content_row(array(	'<label>'.t(67, 'Title').'</label>',
                                            $naviforms->textfield('title', $item->title),
                                            ''
                                        ));

    $navibars->add_tab_content_row(array(	'<label>'.t(168, 'Notes').'</label>',
											$naviforms->textarea('notes', $item->notes),
										));

    $navibars->add_tab_content_row(array('<br />'));

    if(empty($item->status) && !empty($item->id))
    {
        $item->status = 'prepare';
        if(!empty($item->id)) // first time the backup is saved --> start backup process
        {
            $layout->add_script('
                $.get("?fid=backups&act=backup&id='.$item->id.'");
            ');
        }
    }

    // show current backup status
    $navibars->add_tab_content_row(array(	'<label>'.t(68, 'Status').'</label>',
                                            backup::status($item->status),
                                        ));

    if(empty($item->status))
    {
        $estimated_size = backup::estimated_size();

        $navibars->add_tab_content_row(array('<br />'));
        $navibars->add_tab_content_row(array(	'<label>'.t(420, 'Estimated size').'</label>',
											    core_bytes($estimated_size),
										));
    }
    else if($item->status != 'completed' && $item->status != 'error') // process running, no errors found
    {
        $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                                '<button id="backup_refresh_status"><img src="'.NAVIGATE_URL.'/img/icons/silk/reload.png" align="absmiddle" /> '.t(423, "Refresh").'</button>'));
        $layout->add_script('
            $("#backup_refresh_status").bind("click", function(e)
            {
                e.stopPropagation();
                e.preventDefault();
                window.location.replace("?fid=backups&act=2&id='.$item->id.'");
            });
        ');
    }
    else if($item->status == 'completed') // process complete, no errors
    {
		$navibars->add_tab_content_row(array('<br />'));
        $navibars->add_tab_content_row(array(	'<label>'.t(409, 'Size').'</label>',
											    core_bytes($item->size)
										));    	

        $navibars->add_tab_content_row(array(	'<label>'.t(421, 'Created on').'</label>',
                                                core_ts2date($item->date_created, true)
										));

        $navibars->add_tab_content_row(array(   '<label>'.t(82, 'File').'</label>',
                                                '<a href="?fid=backups&act=download&id='.$item->id.'">'.$item->file.'</a>'
                                       )
        );

    }

	return $navibars->generate();
}
?>