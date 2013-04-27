<?php
require_once(NAVIGATE_PATH.'/lib/packages/files/file.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');

function run()
{
	global $user;
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new file();
			
	switch($_REQUEST['act'])
	{
		case 1: // json retrieval & operations
        case "json":
			if($_REQUEST['op']=='upload')
			{
                $tmp_name = $_REQUEST['tmp_name'];
                if($tmp_name=="{{BASE64}}")
                    $tmp_name = base64_encode($_REQUEST['name']);

                $file = file::register_upload(
                    $tmp_name,
                    $_REQUEST['name'],
                    $_REQUEST['parent']
                );

				if(!empty($file))
                {
					echo json_encode(array('id' => $file->id, 'name' => $file->name));
				}
				else
				{
					echo json_encode(false);	
				}
			}

            if($_REQUEST['op']=='create_folder')
			{
                file::create_folder($_REQUEST['name'], $_REQUEST['mime'], $_REQUEST['parent']);
				echo json_encode(true);
			}
            else if($_REQUEST['op']=='edit_folder')
            {
                $f = new file();
                $f->load(intval($_REQUEST['id']));
                $f->name = $_REQUEST['name'];
                $f->mime = $_REQUEST['mime'];
                $ok = $f->save();
                echo json_encode($ok);
            }
            else if($_REQUEST['op']=='edit_file')
            {
                $f = new file();
                $f->load(intval($_REQUEST['id']));
                $f->name = $_REQUEST['name'];
                $ok = $f->save();
                echo json_encode($ok);
            }
			else if($_REQUEST['op']=='move')
			{
				if(is_array($_REQUEST['item']))
				{
					$ok = true;
					for($i=0; $i < count($_REQUEST['item']); $i++)
					{
						unset($item);
						$item = new file();
						$item->load($_REQUEST['item'][$i]);
						$item->parent = $_REQUEST['folder'];
						$ok = $ok & $item->update();
					}
					echo json_encode(($ok)? true : false);						
				}
				else
				{			
					$item->load($_REQUEST['item']);
					$item->parent = $_REQUEST['folder'];
					echo json_encode($item->update());	
				}
			}
			else if($_REQUEST['op']=='delete')
			{
				$item->load($_REQUEST['id']);
				echo json_encode($item->delete());	
			}
			session_write_close();
			$DB->disconnect;
			exit;
			break;
			
			
		case 2:	// show/edit item properties
        case "edit":
			$item->load($_REQUEST['id']);
			
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
					$item->save();
					unset($item);
					$item = new file();
					$item->load($_REQUEST['id']);	
					$layout->navigate_notification(t(53, "Data saved successfully."), false);	
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
			
			$out = files_item_properties($item);
			break;			
		
		case 10:
		case 'media_browser':
			files_media_browser($_GET['limit'], $_GET['offset']);
			break;
			
		case 92: // pixlr (image editor) overlay remover
			ob_clean();

			echo '
			<html>
			<head></head>
			<body>
			<script language="javascript" type="text/javascript">
				//window.parent.eval("$(\'#thumbnail-cache\').attr(\'src\', $(\'#thumbnail-cache\').attr(\'src\') + \'&refresh=\' + new Date().getTime());");
				window.parent.eval("pixlr.overlay.hide();");
			</script>
			</body>
			</html>	
			';
			
			core_terminate();
			break;
		/*	
		case 91: // picnik editing
			ob_clean();
			
			// $strPicnikUrl is the URL that we use to launch Picnik.
			$strPicnikUrl = "http://www.picnik.com/service";	
			// $aPicnikParams collects together all the params we'll give Picnik.  Start with an API key
			$aPicnikParams['_apikey'] = $website->picnik_api_key;
			// tell Picnik where to send the exported image
			$aPicnikParams['_export'] = NAVIGATE_URL.'/navigate_upload.php?wid='.$website->id.'&engine=picnik&id='.$_REQUEST['id'].'&engine=picnik&session_id='.session_id();
			// give the export button a title
			$aPicnikParams['_export_title'] = t(34, 'Save');
			// turn on the close button, and tell it to come back here
			//$aPicnikParams['_close_target'] = $strRoot;
			// send in the previous "king" image in case the user feels like decorating it
			$aPicnikParams['_import'] = NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$_REQUEST['id'].'&disposition=attachment&sid='.session_id();	
			// tell Picnik to redirect the user to the following URL after the HTTP POST instead of just redirecting to _export
			$aPicnikParams['_redirect'] = NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$_REQUEST['id'].'&disposition=inline&ts='.core_time(); //'javascript: return false;';
		
			// tell Picnik our name.  It'll use it in a few places as appropriate
			$aPicnikParams['_host_name'] = 'Navigate';
			// turn off the "Save &amp; Share" tab so users don't get confused
			$aPicnikParams['_exclude'] = "out";
		
			echo '<html><head></head><body>';
		
			echo '<form id="picnik_form" method="POST" action="'.$strPicnikUrl.'" style=" visibility: hidden; ">';
			
			// put all the API parameters into the form as hidden inputs
			foreach( $aPicnikParams as $key => $value ) {
				echo "<input type='hidden' name='$key' value='$value'/>\n";
			}
			
			//echo "<input type='text' name='address' value='Your Majesty'/>\n";
			echo "<input type='submit' value='Picnik'/>\n";
			echo "</form>";
			echo '<script language="javascript" type="text/javascript">
					document.forms[0].submit();
				  </script>';
			echo '</body></html>';

			core_terminate();
			break;
		*/
		
		case 0: // list / search result
		default:						
			// show requested folder or search
			$out = files_browser($_REQUEST['parent'], $_REQUEST['navigate-quicksearch']);
			break;
	}
	
	return $out;
}

function files_browser($parent, $search="")
{
	global $layout;
	global $DB;
	global $website;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$navibrowse = new navibrowse('files');
	
	$navibars->title(t(89, 'Files'));

	$navibars->add_actions(	array(	'<a href="#" onclick="navigate_files_uploader();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/page_white_get.png"> '.t(140, 'Upload').'</a>',
									'<a href="#" onclick="navigate_files_edit_folder();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/folder_add.png"> '.t(141, 'Folder').'</a>',
									'<a href="#" onclick="navigate_files_remove();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>' ));

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/folder_home.png"> '.t(18, 'Home').'</a>',
									'search_form' ));
	
	if(!empty($search))
	{
		$path = '/'.t(41, 'Search').': '.$search;
		$parent = 0;
		$previous = 0;
		$files = file::filesBySearch($search);
	}
	else
	{
		if(empty($parent)) 
		{
			$parent = 0;
			$previous = 0;
			$path = '/';
		}
		else
		{
			$previous = $DB->query_single('parent', 'nv_files', ' id = '.intval($parent).' AND website = '.$website->id);
			$path = file::getFullPathTo($parent);
		}
	
		$files = file::filesOnPath($parent);
	}
	
	$navibrowse->items($files);
	$navibrowse->path($path, $parent, $previous);	
	$navibrowse->setUrl('?fid='.$_REQUEST['fid'].'&parent=');
	$navibrowse->onDblClick('navigate_files_dblclick');
	$navibrowse->onRightClick('navigate_files_contextmenu');
	$navibrowse->onMove('navigate_files_move');
	
	$navibars->add_content($navibrowse->generate());

    $layout->add_script('
        navigate_file_drop(
            ".navibrowse",
            "'.$parent.'",
            {
                afterAll: function()
                {
                    location.replace("'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=files&parent='.$parent.'");
                }
            },
            true
        );'
    );

    $layout->add_script("
        $(document).on('keydown.del', function (evt)    { navigate_files_remove(); return false; } );
        $(document).on('keydown.home', function (evt)   { window.location.href = '?fid=files&act=0'; return false; } );
        $(document).on('keydown.Ctrl_j', function (evt) { navibrowse_folder_tree_dialog(0); return false; } );
    ");

	// PLUPLOAD	
	$navibars->add_content('<div id="navigate-files-uploader"></div>');

	$layout->add_script(' 
		plupload.addI18n(
		{
			"Select files" : "'.t(142, 'Select files').'",
			"Add files to the upload queue and click the start button." : "'.t(143, 'Add files to the upload queue and click the start button.').'",
			"Filename" : "'.t(144, 'Filename').'",
			"Status" : "'.t(68, 'Status').'",
			"Size" : "'.t(145, 'Size').'",
			"Add files" : "'.t(146, 'Select files').'",
			"Start upload":"'.t(147, 'Start upload').'",
			"Stop current upload" : "'.t(148, 'Stop current upload').'",
			"Start uploading queue" : "'.t(149, 'Start uploading queue').'",
			"Drag files here." : "'.t(150, 'Drag files here.').'",
			"Uploaded %d/%d files": "'.t(338, 'Uploaded %d/%d files').'",
			"N/A": "'.t(339, 'N/A').'",
			"File extension error.": "'.t(340, 'File extension error').'",
			"File size error.": "'.t(341, 'File size error').'",
			"Init error.": "'.t(342, 'Init error').'",
			"HTTP Error.": "'.t(343, 'HTTP Error').'",
			"Security error.": "'.t(344, 'Security error').'",
			"Generic error.": "'.t(345, 'Generic error').'",
			"IO error.": "'.t(346, 'IO error').'",
			"Stop Upload": "'.t(347, 'Stop upload').'",
			"Add Files": "'.t(348, 'Add files').'",
			"Start Upload": "'.t(349, 'Start upload').'",
			"%d files queued": "'.t(350, '%d files queued').'"
		});
	');
	
	$layout->add_script('
		function navigate_files_uploader()
		{
			$("#navigate-files-uploader").plupload(
			{
				// General settings
		        runtimes : "html5,flash,silverlight",
				url : "'.NAVIGATE_URL.'/navigate_upload.php?session_id='.session_id().'",
				max_file_size : "'.NAVIGATE_UPLOAD_MAX_SIZE.'mb",
				chunk_size : "384kb",
				unique_names: false,
				sortable: false,
				rename: true,
				preinit: attachCallbacks,
				flash_swf_url: "'.NAVIGATE_URL.'/lib/external/plupload/js/plupload.flash.swf",
		        silverlight_xap_url: "'.NAVIGATE_URL.'/lib/external/plupload/js/plupload.silverlight.xap"
			});
	
			function attachCallbacks(Uploader) 
			{
				Uploader.bind("FileUploaded", function(Up, File, Response) 
				{
					$.ajax(
					{
						async: true,
						url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=files&act=json&op=upload",
						success: function(data)
						{
		
						},
						type: "post",
						dataType: "json",
						data: {
						    tmp_name: "{{BASE64}}",
						    name: File.name,
						    parent: '.$parent.'
						}
					});
				});
			}								 
			
			$(".plupload_wrapper").removeClass("plupload_scroll");
			
			$("#navigate-files-uploader").dialog(
			{
				title: "'.t(142, 'Select files').'",
				height: 400,
				width: 600,
				modal: true,
				close: function()
				{
					window.location.reload();
				}
			});	
	}');
						 
	$layout->add_script('
		function navigate_files_remove()
		{
			if($(".ui-selected img").parent().length > 0)
			{
				$("<div>'.t(151, 'These items will be permanently deleted and cannot be recovered. Are you sure?').'</div>").dialog(
				{
					title: "'.t(59, 'Confirmation').'",
					resizable: false,
					height:140,
					modal: true,
					buttons: 
					{
						"'.t(58, 'Cancel').'": function() 
						{
							$(this).dialog("close");
						},
						"'.t(152, 'Continue').'": function() 
						{
							$(".ui-selected img").parent().each(function()
							{
								var itemId = this.id.substring(5);
								
								$.ajax(
								{
									async: false,
									url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=1&op=delete&id=" + itemId,
									success: function(data)
									{									
										$("#item-"+itemId).remove();
									}
								});
			
							});
							$(this).dialog("close");
						}									
					}
				});	
			}
		}
	');
						 
	$layout->add_script('
		function navigate_files_dblclick(el)
		{
		 	var itemId = el.id.substring(5);
		 	window.location.href = "?fid='.$_REQUEST['fid'].'&act=2&id=" + itemId;
		}
		
		function navigate_files_move(item_id, folder_id, element)
		{
			$.ajax(
			{
				async: false,
				type: "post",
				data: {
					item: item_id,
					folder: folder_id
				},
				url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=1&op=move",
				success: function(data)
				{
					if(data=="true")
					{
						if(typeof(element)=="array")
						{
							for(el in element)	
							{
								if($(el).attr("id").substring(5) != folder_id)
									$(el).remove();
							}
						}
						else					
						{
							$(element).remove();
						}
					}
				}
			});						
		}

        function navigate_files_contextmenu(el, ev)
        {

            var html = \'<ul id="navigate-files-contextmenu">\'+
                        \'<li action="open"><a href="#"><span class="ui-icon ui-icon-arrowreturnthick-1-e"></span>'.t(499, "Open").'</a></li>\'+
                        \'<li action="rename"><a href="#"><span class="ui-icon ui-icon-pencil"></span>'.t(500, "Rename").'</a></li>\'+
                        \'<li action="delete"><a href="#"><span class="ui-icon ui-icon-trash"></span>'.t(35, 'Delete').'</a></li>\'+
                        \'</ul>\';

            $("#navigate-files-contextmenu").remove();
            $(html).appendTo($("body"));

            $("#navigate-files-contextmenu").menu();

            $("#navigate-files-contextmenu").css({
                "top": ev.clientY,
                "left": ev.clientX,
                "z-index": 100000,
                "position": "absolute"
            }).addClass("navi-ui-widget-shadow").show();

            var type = "file";
            if($(el).hasClass("navibrowse-folder"))
                type = "folder";

            var id = $(el).attr("id").replace(/item-/, "");

            // attach events to type & id
            $("#navigate-files-contextmenu").find("li[action=\"open\"]").on("click", function()
            {
                $(el).trigger("dblclick");
            });

            $("#navigate-files-contextmenu").find("li[action=\"rename\"]").on("click", function()
            {
                if(type=="folder")
                {
                    navigate_files_edit_folder(id, $(el).find(".navibrowse-item-name").text(), $(el).attr("mime"));
                }
                else
                {
                    navigate_files_rename(id, $(el).find(".navibrowse-item-name").text());
                }
            });

            $("#navigate-files-contextmenu").find("li[action=\"delete\"]").on("click", function()
            {
                $(el).trigger("click");
                navigate_files_remove();
            });

        }
	');
	
	$navibars->add_content('
		<div id="navigate-edit-folder" style=" display: none; ">
		<form action="#" onsubmit="return false;">
			<input type="submit" value="" style=" display: none; " />
			<div class="navigate-form-row">
				<label>'.t(159, 'Name').'</label>
				'.$naviforms->textfield('folder-name', '').'
			</div>
			<div class="navigate-form-row">
				<label>'.t(160, 'Type').'</label>
				'.$naviforms->selectfield(
                    'folder-mime',
                    array(
                            0 => 'folder/generic',
                            1 => 'folder/images',
                            2 => 'folder/audio',
                            3 => 'folder/video',
                            4 => 'folder/flash',
                            5 => 'folder/documents'
                        ),
                    array(
                            0 => t(161, 'Generic'),
                            1 => t(29, 'Images'),
                            2 => t(31, 'Audio'),
                            3 => t(30, 'Video'),
                            4 => t(186, 'Adobe Flash'),
                            5 => t(32, 'Documents')
                        ),
                    'folder/generic'
                ).'
			</div>			
		</form>
		</div>

		<div id="navigate-edit-file" style=" display: none; ">
		<form action="#" onsubmit="return false;">
			<input type="submit" value="" style=" display: none; " />
			<div class="navigate-form-row">
				<label>'.t(159, 'Name').'</label>
				'.$naviforms->textfield('file-name', '').'
			</div>
		</form>
		</div>
	');
						 
	$layout->add_script('
		function navigate_files_edit_folder(id, name, mime)
		{
			$("#navigate-edit-folder").dialog(
			{
				title: "'.t(141, 'Folder').'",
				resizable: false,
				height: 200,
				width: 625,
				modal: true,
				buttons: 
				{
					"'.t(58, 'Cancel').'": function() 
					{
						$("#navigate-edit-folder").dialog("close");
					},
					"'.t(152, 'Continue').'": function() 
					{
					    var op = "edit_folder";
						if(!id)
						    op = "create_folder";

						$.ajax(
						{
							async: false,
							type: "post",
							data: {
								name: $("#folder-name").val(),
								mime: $("#folder-mime").val(),
								parent: "'.$parent.'"
							},
							url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=1&id=" + id + "&op=" + op,
							success: function(data)
							{
								$("#navigate-edit-folder").dialog("close");
								window.location.reload();
							}
						});						
					}									
				}			
			});

			$("#folder-name").val(name);
			$("#folder-mime").val(mime).trigger("change");
		}

		function navigate_files_rename(id, name)
		{
            $("#navigate-edit-file").dialog(
			{
				title: "'.t(82, 'File').': " + name,
				resizable: false,
				height: 200,
				width: 625,
				modal: true,
				buttons:
				{
					"'.t(58, 'Cancel').'": function()
					{
						$("#navigate-edit-file").dialog("close");
					},
					"'.t(152, 'Continue').'": function()
					{
						$.ajax(
						{
							async: false,
							type: "post",
							data: {
								name: $("#file-name").val(),
								id: id
							},
							url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=1&op=edit_file",
							success: function(data)
							{
								$("#navigate-edit-file").dialog("close");
								window.location.reload();
							}
						});
					}
				}
			});

			$("#file-name").val(name);
		}
	');
	
	// trying to allow file upload by drag and drop on full window
	/*
	$layout->add_script('
		$(function() 
		{
			var uploader_zone = new plupload.Uploader(
			{
				runtimes : "html5,flash,silverlight",
				max_file_size : "50mb",
				chunk_size : "1mb",
				unique_names : true,
				drop_element: "navigate-content",			
				url : "'.NAVIGATE_URL.'/navigate_upload.php?session_id='.session_id().'",
		
				// Flash settings
				flash_swf_url : "'.NAVIGATE_URL.'/js/plupload/js/plupload.flash.swf",
		
				// Silverlight settings
				silverlight_xap_url : "'.NAVIGATE_URL.'/js/plupload/js/plupload.silverlight.xap"

			});		 
		});	
	');
	*/	
	return $navibars->generate();
	
}

function files_item_properties($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	$navibars->title(t(89, 'Files'));

	//$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=0&parent='.$item->parent.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/clipboard.png"> NaviM+</a>'));
								
	$navibars->add_actions(	array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
									'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>' ));

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=0&parent='.$item->parent.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/folder_up.png"> '.t(139, 'Back').'</a>',
									'search_form' ));								
										
	$delete_html = array();
	$delete_html[] = '<script language="javascript" type="text/javascript">';
	$delete_html[] = 'function navigate_delete_dialog()';		
	$delete_html[] = '{';				
	$delete_html[] = '$("<div id=\"navigate-delete-dialog\" class=\"hidden\">'.t(57, 'Do you really want to delete this item?').'</div>").dialog(
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
								"'.t(35, 'Delete').'": function() 
								{
									$.ajax(
									{
										async: false,
										url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=1&op=delete&id='.$item->id.'",
										success: function(data)
										{
											window.location.href = "?fid='.$_REQUEST['fid'].'&act=0&parent='.$item->parent.'";	
										}
									});
									$(this).dialog("close");								
								}
							}
						});
					}';		
	$delete_html[] = '</script>';						
								
	$navibars->add_content(implode("\n", $delete_html));
	
	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.$item->id.'</span>'));

	$navibars->add_tab_content_row(array(	'<label>'.t(144, 'Filename').'</label>',
											$naviforms->textfield('name', $item->name),
										));		
										
	$navibars->add_tab_content_row(array(	'<label>'.t(145, 'Size').'</label>',
											'<span>'.core_bytes($item->size).'</span>'));

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
																														
	$navibars->add_tab_content_row(array(	'<label>'.t(80, 'Permission').'</label>',
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
												$item->permission
											)
										)
									);										
										
	$navibars->add_tab_content_row(array(	'<label>'.t(65, 'Enabled').'</label>',
											$naviforms->checkbox('enabled', $item->enabled),
										));	
																				
/*										
	$navibars->add_tab_content_row(array(	'<label>'.t(153, 'Embed link').'</label>',
											'<a href="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=inline" target="_blank">'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=inline</a>'));

	$navibars->add_tab_content_row(array(	'<label>'.t(154, 'Download link').'</label>',
											'<a href="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=attachment">'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=attachment</a>'));
*/

	$website_root = $website->absolute_path(true).'/object';
	if(empty($website_root)) $website_root = NVWEB_OBJECT;

	$navibars->add_tab_content_row(array(	'<label>'.t(153, 'Embed link').'</label>',
											'<a href="'.$website_root.'?id='.$item->id.'&disposition=inline" target="_blank">'.$website_root.'?id='.$item->id.'&disposition=inline</a>'));

	$navibars->add_tab_content_row(array(	'<label>'.t(154, 'Download link').'</label>',
											'<a href="'.$website_root.'?id='.$item->id.'&disposition=attachment">'.$website_root.'?id='.$item->id.'&disposition=attachment</a>'));
										
										
	if($item->type == 'image')
	{
		$navibars->add_tab(t(157, "Image"));	
		
		$navibars->add_tab_content_row(array(	'<label>'.t(155, 'Width').' (px)</label>',
												$naviforms->textfield('width', $item->width),
											));			
											
		$navibars->add_tab_content_row(array(	'<label>'.t(156, 'Height').' (px)</label>',
												$naviforms->textfield('height', $item->height),
											));						
		
		if(!empty($website->thumbnail_types))
		{
			$thumbnails = $website->thumbnail_types;
			
			$tmb_html = '';
			if(!is_array($thumbnails))
			{
				$tmb_html = '<img src="img/empty.png" width="120px" height="80px" />';
				$thumbnails = array();
			}
			
			foreach($thumbnails as $dimensions)
			{
				$dimensions = explode('x', $dimensions);
				$tmb_html .= '
					<div class="navigate-files-thumbnail">
						<div><img src="'.$website_root.'?id='.$item->id.'&disposition=inline&width='.$dimensions[0].'&height='.$dimensions[1].'" align="top" /></div>
						<a href="'.$website_root.'?id='.$item->id.'&disposition=inline&width='.$dimensions[0].'&height='.$dimensions[1].'" target="_blank">
							<img src="img/icons/silk/clipboard.png" align="absmiddle"> '.$dimensions[0].' x '.$dimensions[1].' px
						</a>
					</div>
				';
			}
																							
			$navibars->add_tab_content_row(array(	'<label>'.t(179, 'Thumbnails').'</label>',
													$tmb_html
												));
		}
		
		$navibars->add_tab_content_row(array('<label>'.t(170, 'Edit').'</label>', 
		'
			<script language="javascript" type="text/javascript">
				function navigate_pixlr_edit()
				{
					pixlr.overlay.show({
						service: "editor",
						loc: "'.$user->language.'",
						image:"'.$website_root.'?id='.$item->id.'&disposition=inline&sid='.session_id().'&seed='.core_time().'",
						title: "'.$item->name.'",
						target: "'.NAVIGATE_URL.'/navigate_upload.php?wid='.$website->id.'&engine=pixlr&id='.$item->id.'&session_id='.session_id().'&seed='.core_time().'",
						exit: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=92&id='.$item->id.'&ts='.core_time().'",
						credentials: true,
						method: "GET",
						referrer: "Navigate CMS",
						locktitle: true,
						locktype: "png",
						redirect: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=92&id='.$item->id.'&ts='.core_time().'"
					});
				}
			</script>
			<a href="#" class="button" onclick="navigate_pixlr_edit();"><img src="'.NAVIGATE_URL.'/img/logos/pixlr.png" width="100px" height="42px" /></a>
		'));			
	}	
	else if($item->type=='video')
	{
		$navibars->add_tab(t(272, "Video"));
		/*
		$navibars->add_tab_content_row(array(	'<label>'.t(272, 'Video').'</label>',
												'<div id="video_'.$item->id.'" style="display:block;width:640px;height:360px;float:left;" class="video">',
												'<video controls="controls">',
												'	<source src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=inline" type="'.$item->mime.'" />',
												'</video>',
												'</div>'
                                            ));	
																						
		$layout->add_script('         
			$("#video_'.$item->id.' video").mediaelementplayer(
			{
				pluginPath: "'.NAVIGATE_URL.'/lib/external/mediaelement/"
			});
		');				
		*/	
	
		$navibars->add_tab_content_row(array(	'<label>'.t(272, 'Video').'</label>',
												'<div id="video_'.$item->id.'" style="display:block;width:640px;height:360px;float:left;" class="video">
													<a href="http://www.adobe.com/go/getflashplayer"><img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" /></a>
												</div>',
												'<script language="javascript" type="text/javascript" src="http://bitcast-b.bitgravity.com/player/6/functions.js"></script>'
                                            ));
	
		$layout->add_script('         
			var flashvars = {};
			flashvars.AutoPlay = "false";
			flashvars.File = "'.urlencode(NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=inline').'";
			flashvars.Mode = "ondemand";
			var params = {};
			params.allowFullScreen = "true";
			params.allowScriptAccess = "always";
			var attributes = {};
			attributes.id = "bitgravity_player_6";
			swfobject.embedSWF(stablerelease, "video_'.$item->id.'", "640", "360", "9.0.115", "http://bitcast-b.bitgravity.com/player/expressInstall.swf", flashvars, params, attributes);	
		');
	}
	else if($item->type=='audio')
	{
		$navibars->add_tab(t(31, "Audio"));

		$navibars->add_tab_content_row(array(	'<label>'.t(31, 'Audio').'</label>',
												'<div id="audio_'.$item->id.'" style="display:block;float:left;" class="audio">',
												'<audio controls="controls">',
												'	<source src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=inline" type="'.$item->mime.'" />',
												'</audio>',
												'</div>'
                                            ));	
																						
		$layout->add_script('         
			$("#audio_'.$item->id.' audio").mediaelementplayer(
			{
				pluginPath: "'.NAVIGATE_URL.'/lib/external/mediaelement/"
			});

			$("#audio_'.$item->id.'").addClass("ui-state-default");
		');												
		
	}
										
	return $navibars->generate();

}

function files_media_browser($limit = 50, $offset = 0)
{
	global $DB;
    global $website;
	
	//$website = $_REQUEST['website'];
	
	$media = (empty($_REQUEST['media'])? 'image' : $_REQUEST['media']);
	$text = $_REQUEST['text'];
	
	$out = array();

    // TODO: optimize file list loading using REAL offsets and limits
    //       right now we only increase the limit and reload all files
        $limit = $offset + $limit;
        $offset = 0;
	
	if($media=='folder')
	{
		$parent = 0;
		$files = file::filesOnPath($_REQUEST['parent'], $website->id);
		if($_REQUEST['parent'] > 0)	// add "back" special folder
		{
			$previous = $DB->query_single('parent', 'nv_files', ' id = '.$_REQUEST['parent'].' AND website = '.$website->id);
			array_unshift($files, json_decode('{"id":"'.$previous.'","type":"folder","name":"'.t(139, 'Back').'","mime":"folder\/back","navipath":"/foo"}')); 			
		}
	}
	else
		$files = file::filesByMedia($media, 0, -1, $website->id);

	for($i=$offset; $i+$offset < $limit; $i++)
	{
		if(empty($files[$i])) break;
		
		if(!empty($text))
			if(stripos($files[$i]->name, $text)===false) continue;

        $website_root = $website->absolute_path(true).'/object';
        if(empty($website_root)) $website_root = NVWEB_OBJECT;
        $download_link = $website_root.'?id='.$files[$i]->id.'&disposition=attachment';

        if($files[$i]->type == 'image')
		{
			$icon = NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$files[$i]->id.'&disposition=inline&width=75&height=75';
			$out[] = '<div class="ui-corner-all draggable-'.$files[$i]->type.'"
			               mediatype="'.$files[$i]->type.'"
			               mimetype="'.$files[$i]->mime.'"
			               image-width="'.$files[$i]->width.'"
			               image-height="'.$files[$i]->height.'"
			               download-link="'.$download_link.'"
			               id="file-'.$files[$i]->id.'">
			               <img src="'.$icon.'" title="'.$files[$i]->name.'" />
                      </div>';
		}
		else
		{
			$icon = navibrowse::mimeIcon($files[$i]->mime, $files[$i]->type);
			$navipath = file::getFullPathTo($files[$i]->id);
			$out[] = '<div class="ui-corner-all draggable-'.$files[$i]->type.'"
			               mediatype="'.$files[$i]->type.'"
			               mimetype="'.$files[$i]->mime.'"
			               navipath="'.$navipath.'"
			               download-link="'.$download_link.'"
			               id="file-'.$files[$i]->id.'">
			               <img src="'.$icon.'" width="50" height="50" />
                           <span style="clear: both; display: block; height: 0px;"></span>'.
                           $files[$i]->name.'
                       </div>';
		}
	}
	
	if(count($files) > $limit + $offset)
	{
		$icon = navibrowse::mimeIcon($files[$i]->mime, $files[$i]->type);
		$out[] = '<div class="ui-corner-all" id="file-more">
                    <img src="'.NAVIGATE_URL.'/img/icons/ricebowl/actions/forward.png" width="32" height="32"  style="margin-top: 14px;" />'.
                    t(234, 'More elements').'
                  </div>';
	}
	
	echo implode("\n", $out);
	
	session_write_close();
	$DB->disconnect();
	exit;
		
}

?>