<?php
require_once(NAVIGATE_PATH.'/lib/packages/backups/backup.class.php');

function run()
{
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new backup();
			
	// Extract safe values for array access
	$act = value_or_default(array($_REQUEST, 'act'), '');
	switch($act)
	{
        case 'json':
	    case 1:	// json data retrieval & operations
			// Extract safe values for array access
			$oper = value_or_default(array($_REQUEST, 'oper'), '');
			switch($oper)
			{
				case 'del':	// remove rows
					$ids = value_or_default(array($_REQUEST, 'ids'), array());
					foreach($ids as $id)
					{
						$item->load($id);
						$item->delete();
					}
					echo json_encode(true);
					break;
					
				default: // list or search	
					$page = intval(value_or_default(array($_REQUEST, 'page'), 0));
					$max = intval(value_or_default(array($_REQUEST, 'rows'), 10));
					$offset = ($page - 1) * $max;
					$where = " i.website = ".$website->id;
					$parameters = array();
										
					if($_REQUEST['_search']=='true' || !empty(value_or_default(array($_REQUEST, 'quicksearch'), '')))
					{
						if(!empty(value_or_default(array($_REQUEST, 'quicksearch'), '')))
                        {
                            list($qs_where, $qs_params) = $item->quicksearch(value_or_default(array($_REQUEST, 'quicksearch'), ''));
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
                        !in_array($_REQUEST['sidx'], array('id', 'date_created', 'title', 'size', 'status'))
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];
								
					$sql = ' SELECT SQL_CALC_FOUND_ROWS i.*
							   FROM nv_backups i
							  WHERE '.$where.'	
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;	
				
					if(!$DB->query($sql, 'array', $parameters))
					{
						throw new Exception($DB->get_last_error());	
					}
					
					$dataset = $DB->result();	
					$total = $DB->foundRows();					
					
					$out = array();		
					
					if(empty($dataset))
                    {
                        $rows = 0;
                    }
					else
                    {
                        $rows = count($dataset);
                    }
	
					for($i=0; $i < $rows; $i++)
					{						
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1 	=> core_ts2date($dataset[$i]['date_created'], true),
							2 	=> core_special_chars($dataset[$i]['title']),
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
        case 'edit':
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
			}
							
			if(value_or_default(array($_REQUEST, 'form-sent'), '')=='true')
			{						
				$item->load_from_post();
                naviforms::check_csrf_token();

				try
				{
                    // update an existing backup
					$item->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = backups_form($item);
			break;
			
		case 4:
        case 'delete':
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

                if(ob_get_length()!==false)
                {
                    ob_end_flush();
                    ob_flush();
                }
                flush();
                session_write_close();
                // now the process is running in the server, the client thinks the http request has finished
                
				try
				{
					$item->load(intval($_REQUEST['id']));
                    $item->backup();
				}
				catch(Exception $e)
				{
					// Error already logged in backup() method
					// Log file created at: NAVIGATE_PRIVATE/{website}/backups/backup-error-*.log
				}
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

        case 'export_sql':
            // export database as SQL (admin only)
            global $user;
            
            // Check if user is administrator
            // Administrator profile typically has ID 1, but we also check for system-level permissions
            $is_admin = ($user->profile == 1);
            
            // Additional check: verify if user has access to system administration features
            if(!$is_admin)
            {
                header('HTTP/1.1 403 Forbidden');
                echo 'Access denied';
                core_terminate();
            }

            // Generate SQL export
            $sql_content = backup::export_database_sql();
            $filename = 'database_backup_'.$website->id.'_'.date('Y-m-d_His').'.sql';

            ob_end_flush();

            header('Content-type: application/sql');
            header("Content-Length: ".strlen($sql_content));
            header('Content-Disposition: attachment; filename="'.$filename.'"');

            echo $sql_content;

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
	global $user;
	
	$navibars->title(t(329, 'Backups'));

	// TODO
    /*
    $navibars->add_actions(
        array(	'<a href="#" onclick="navigate_restore_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/database_refresh.png"> '.t(412, 'Restore').'</a> ' )
    );
    */

        // Add SQL export button for admin users only
    // Administrator profile typically has ID 1
    $is_admin = ($user->profile == 1);
    
    if($is_admin)
    {
        $navibars->add_actions(
            array(
                '<a href="?fid=backups&act=export_sql"><img height="16" align="absmiddle" width="16" src="img/icons/silk/database.png"> '.t(845, 'Export database (SQL)').'</a>'
            )
        );
    }

	$navibars->add_actions(
	    array(
	        '<a href="?fid=backups&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
            '<a href="?fid=backups&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
            'search_form'
        )
    );
	
	if(value_or_default(array($_REQUEST, 'quicksearch'), '')=='true')
    {
        $nv_qs_text = core_purify_string(value_or_default(array($_REQUEST, 'navigate-quicksearch'), ''), true);
        $navitable->setInitialURL("?fid=backups&act=1&_search=true&quicksearch=".$nv_qs_text);
    }
	
	$navitable->setURL('?fid=backups&act=json');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=backups&act=edit&id=');
	
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
	global $layout;
	global $website;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we can use media browser in this function
	
	if(empty($item->id))
    {
        $navibars->title(t(329, 'Backups').' / '.t(38, 'Create'));
    }
	else
    {
        $navibars->title(t(329, 'Backups').' / '.t(170, 'Edit').' ['.$item->id.']');
    }

	if(empty($item->id))
	{
		$navibars->add_actions(
		    array(
		        '<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/database_save.png"> '.t(410, 'Begin backup').'</a>'
            )
        );
	}
	else
	{
        if($item->status=='completed')
        {
            // TODO
            /*
            $navibars->add_actions(		
	            array(	'<a href="#" onclick="navigate_restore_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/database_refresh.png"> '.t(412, 'Restore').'</a> ' )
            );
            */
        }

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
                    function() { window.location.href = "?fid=backups&act=delete&id='.$item->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}
	
	$navibars->add_actions(
	    array(
	        (!empty($item->id)? '<a href="?fid=backups&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
								'<a href="?fid=backups&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
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
            '<label>'.t(67, 'Title').'</label>',
            $naviforms->textfield('title', $item->title),
            ''
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(168, 'Notes').'</label>',
			$naviforms->textarea('notes', $item->notes),
        )
    );

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
    $navibars->add_tab_content_row(
        array(
            '<label>'.t(68, 'Status').'</label>',
            backup::status($item->status),
        )
    );

    if(empty($item->status))
    {
        $estimated_size = backup::estimated_size();

        $navibars->add_tab_content_row(array('<br />'));
        $navibars->add_tab_content_row(
            array(
                '<label>'.t(420, 'Estimated size').'</label>',
				core_bytes($estimated_size),
            )
        );
    }
    else if($item->status != 'completed' && $item->status != 'error') // process running, no errors found
    {
        $navibars->add_tab_content_row(
            array(
                '<label>&nbsp;</label>',
                '<button id="backup_refresh_status"><img src="'.NAVIGATE_URL.'/img/icons/silk/reload.png" align="absmiddle" /> '.t(423, "Refresh").'</button>'
            )
        );

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
        $navibars->add_tab_content_row(
            array(
                '<label>'.t(409, 'Size').'</label>',
				core_bytes($item->size)
            )
        );

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(421, 'Created on').'</label>',
                core_ts2date($item->date_created, true)
            )
        );

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(82, 'File').'</label>',
                '<a href="?fid=backups&act=download&id='.$item->id.'">'.$item->file.'</a>'
            )
        );

    }
    else if($item->status == 'error') // process failed with error
    {
        $navibars->add_tab_content_row(array('<br />'));
        
        // Display error message from error_message field
        $error_message = !empty($item->error_message) ? $item->error_message : '';
        
        $navibars->add_tab_content_row(
            array(
                '<label>'.t(854, 'Error details').'</label>',
                '<div class="ui-state-error ui-corner-all" style="padding: 10px;">'.
                '<span class="ui-icon ui-icon-alert" style="float: left; margin-right: 0.3em;"></span>'.
                '<strong>'.t(56, 'Unexpected error').':</strong><br />'.
                (!empty($error_message) ? core_special_chars($error_message) : t(855, 'An error occurred during the backup process. Check the log files for details.')).
                '</div>'
            )
        );
        
        // Show log file location if available
        $log_files = glob(NAVIGATE_PRIVATE.'/'.$website->id.'/backups/backup-error-*.log');
        if(!empty($log_files))
        {
            $latest_log = array_pop($log_files);
            $log_url = str_replace(NAVIGATE_PRIVATE, NAVIGATE_URL.'/private', $latest_log);
            
            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(366, 'Log').'</label>',
                    '<a href="'.$log_url.'" target="_blank" class="navigate-external-link">'.
                    '<i class="fa fa-external-link"></i> '.t(856, 'View error log file').
                    '</a>'
                )
            );
        }
    }

	return $navibars->generate();
}

?>