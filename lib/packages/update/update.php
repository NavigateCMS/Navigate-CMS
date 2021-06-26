<?php
function run()
{
	global $layout;

	// force no cache on this page. Thanks to: http://james.cridland.net/code/caching.html
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");	
	
	switch(@$_REQUEST['act'])
	{
		case 'manual_update':
			$ok = update::install_from_repository(intval($_POST['update_manual_file']));

			if($ok)
            {
				$layout->navigate_notification(t(293, "Application successfully updated"), false);
            }
			else
			{
				$files = glob(NAVIGATE_PATH.'/updates/update-*.log.txt');
				$log_location = array_pop($files);
				$log_location = str_replace(NAVIGATE_PATH, NAVIGATE_URL, $log_location);
				$layout->navigate_notification(t(294, "Error updating.")."<br /><a href='".$log_location."' target='_blank'>".t(366, "Log")."</a>", true, true);
			}

			$out = update_list();			
			break;
			
		case 'install_next_update':
			// install next update
            $updates = update::updates_available();
            $update_summary = base64_decode($updates[0]['text']);
			$ok = update::install_from_navigatecms($updates);

            if($ok)
            {
				$layout->navigate_notification(t(293, "Application successfully updated"), false);
                $layout->add_content('
                    <div style=" display: none; " id="navigate_installed_update_summary">'.$update_summary.'</div>
                ');
                $layout->add_script('
                    $("#navigate_installed_update_summary").dialog({
                        modal: true,
                        title: "Navigate v'.$updates[0]['Version'].' r'.$updates[0]['Revision'].'",
                        width: 650,
                        height: 400
                    });
                ');
            }
			else
			{
				$files = glob(NAVIGATE_PATH.'/updates/update-*.log.txt');
				$log_location = array_pop($files);
				$log_location = str_replace(NAVIGATE_PATH, NAVIGATE_URL, $log_location);
				$layout->navigate_notification(t(294, "Error updating.")."<br /><a href='".$log_location."' target='_blank'>".t(366, "Log")."</a>", true, true);
			}
			
			$out = update_list();
			break;

        case 'cache_clean':
            update::cache_clean();
            // don't break

        case 0:
		default:
			$out = update_list();
	}
	
	return $out;
}

function update_list()
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	$navibars->title(t(285, 'Update'));
	
	$navibars->form('', 'fid=update&act=manual_update&debug');
	$navibars->add_tab(t(0, 'Navigate'));	
	
	$updates_available = update::updates_available();
	$current_version = update::latest_installed();
	$latest_available = update::latest_available();

    if(empty($latest_available))
    {
        $layout->navigate_notification(t(578, "Sorry, could not connect to check updates"), true);
        $latest_available->Version = $current_version->version;
        $latest_available->Revision = $current_version->revision;
    }

	if($latest_available->Revision > $current_version->revision)
		$navibars->add_actions(	 array(	'<a href="?fid=update&act=install_next_update&debug"><img height="16" align="absmiddle" width="16" src="img/icons/silk/asterisk_orange.png"> '.t(289, 'Update Navigate').' <img src="img/icons/silk/bullet_go.png" align="absmiddle" /> '.$updates_available[0]['Version'].' r'.$updates_available[0]['Revision'].'</a>') );
		
	$current = array();
	$current[] = '<div class="navigate-panels-summary ui-corner-all" style=" width: 234px; height: 118px; ">';
	$current[] = '	<h2><img src="img/navigate-logo-150x70.png" /><br />' . $current_version->version . ' r'.$current_version->revision . '</h2>';
	$current[] = '</div>';

    $navibars->add_actions(	 array(	'<a href="?fid=update&act=cache_clean&debug"><img height="16" align="absmiddle" width="16" src="img/icons/silk/lightning_delete.png"> '.t(660, 'Clear cache').'</a>') );
	
	$navibars->add_tab_content_panel('<img src="img/navigate.png" width="16px" height="16px" align="absmiddle" /> '.t(290, 'Current version'), $current, 'navigate-panel-current-version', '250px', '184px');	

	// update list
	$updates = '';
    $elements_html = '';
	foreach($updates_available as $update)
	{
        $update['text'] = base64_decode($update['text']);
		$elements_html .= '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
							'<div class="navigate-panel-update-info" style=" cursor: pointer; " title="'.core_string_cut($update['text'], 200).'">'.
                                '<div style="display: none;">'.$update['text'].'</div>'.
								'<strong>'.$update['Version'].' r'.$update['Revision'].'</strong> <img align="absmiddle" src="img/icons/silk/bullet_green.png" align="absmiddle"> '.$update['Cause'].
							'</div>'.
						  '</div>';
	}

    $layout->add_script('
        $(".navigate-panel-update-info").on("click", function()
        {
            if($(this).children().eq(0).html()!="")
            {
                var html = $(this).children().eq(0).html();
                $("<div>"+html+"</div>").dialog({
                    modal: true,
                    title: $(this).children().eq(1).text(),
                    width: 650,
                    height: 400
                });
            }
        });
    ');

	$navibars->add_tab_content_panel('<img src="img/icons/silk/asterisk_yellow.png" align="absmiddle" /> '.t(292, 'Available updates'), $elements_html, 'navigate-panel-top-elements', '400px', '184px');

	$latest = array();
	$latest[] = '<div class="navigate-panels-summary ui-corner-all" style=" width: 234px; height: 118px; ">';
	$latest[] = '	<h2><img src="img/navigate-logo-150x70.png" /><br />' . $latest_available->Version . ' r'.$latest_available->Revision . '</h2>';
	$latest[] = '</div>';
	
	$navibars->add_tab_content_panel('<img src="img/icons/silk/asterisk_orange.png" align="absmiddle" /> '.t(291, 'Latest version'), $latest, 'navigate-panel-latest-version', '250px', '184px');	

	$manual_update = array();
	$manual_update[] = '<div class="navigate-panels-summary ui-corner-all" id="update_manual_dropbox" style=" width: 231px; line-height: 59px; ">';
	$manual_update[] = '	<h2><img src="img/icons/misc/dropbox.png" /></h2>';
	$manual_update[] = '</div>';
	$manual_update[] = '<input type="hidden" id="update_manual_file" name="update_manual_file" value="" />';
	/*
	$manual_update[] = '<div class="navigate-panels-summary ui-corner-all" style=" width: 231px; ">';
	$manual_update[] = '	<input type="file" />';		
	$manual_update[] = '</div>';	
	*/
	$manual_update[] = '<div style=" float: right; margin-right: 8px; "><input type="submit" disabled="disabled" class="uibutton" id="update_manual_install" value="'.t(365, 'Install').'" /></div>';
	
	$navibars->add_tab_content_panel('<img src="img/icons/silk/disk_upload.png" align="absmiddle" /> '.t(303, 'Manual update'), $manual_update, 'navigate-panel-manual-update', '250px', '184px');

	$layout->add_script('
        $(".navigate-panel").css({
            "visibility": "visible",
            "float": "left",
            "margin-right": "12px" 
        });
        
        $(".navigate-panels-summary").css({
            "max-width": "239px",
            "width": "100%"
        });

		navigate_file_drop("#update_manual_dropbox", 0, 
			{ 
				afterOne: function(file)
				{
					if(file!=false)
					{
						$("#update_manual_dropbox").removeClass("ui-state-highlight");
						$("#update_manual_dropbox").html("<strong>" + file.name + "</strong>");
						$("#update_manual_file").val(file.id);
						$("#update_manual_install").button("enable");
					}
				},
				dragOver: function()
				{
					$("#update_manual_dropbox").addClass("ui-state-highlight");
				},
				dragLeave: function()
				{
					$("#update_manual_dropbox").removeClass("ui-state-highlight"); 
				}
			}
        );
	');
	
	return $navibars->generate();
}


?>