<?php
require_once(NAVIGATE_PATH.'/lib/packages/extensions/extension.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new extension();
			
	switch($_REQUEST['act'])
	{
        case 'extension_info':
            echo '<iframe src="'.NAVIGATE_URL.'/plugins/'.$_REQUEST['extension'].'/'.$_REQUEST['extension'].'.info.html'.'" scrolling="auto" frameborder="0"  width="100%" height="100%"></iframe>';
            core_terminate();
            break;

        case 'disable':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $extension->enabled = 0;
            $ok = $extension->save();
            echo json_encode($ok);
            core_terminate();
            break;

        case 'enable':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $extension->enabled = 1;
            $ok = $extension->save();
            echo json_encode($ok);
            core_terminate();
            break;

        // TODO: rework favorite extensions as user's favorite (not global)
        /*
        case 'favorite':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $extension->favorite = intval($_REQUEST['value']);
            $ok = $extension->save();
            echo json_encode($ok);
            core_terminate();
            break;
        */

        case 'remove':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $status = $extension->delete();
            echo json_encode($status);
            core_terminate();
            break;

        case 'options':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);

            $status = null;
            if(isset($_REQUEST['form-sent']))
            {
                $extension->load_from_post();
                $status = $extension->save();
            }

            $out = extensions_options($extension, $status);
            echo $out;

            core_terminate();
            break;

        case 'dialog':
            $extension = new extension();
            $extension->load($_REQUEST['extension']);
            $out = extensions_dialog($extension, $_REQUEST['function'], $_REQUEST);
            echo $out;

            core_terminate();
            break;

        case 'process':
            $extension = trim($_GET['extension']);
            call_user_func("nvweb_".$extension."_plugin", $_REQUEST);
            core_terminate();
            break;

        case 'run':
            $extension = trim($_GET['extension']);
            if(file_exists(NAVIGATE_PATH.'/plugins/'.$extension.'/run.php'))
            {
                include_once(NAVIGATE_PATH.'/plugins/'.$extension.'/run.php');
                if(function_exists($extension.'_run'))
                {
                    eval('$out = '.$extension.'_run();');
                }
            }
            break;

        case 'extension_upload':
            if($_FILES['extension-upload']['error']==0)
            {
                // uncompress ZIP and copy it to the extensions dir
                $tmp = trim(substr($_FILES['extension-upload']['name'], 0, strpos($_FILES['extension-upload']['name'], '.')));
                $extension_name = filter_var($tmp, FILTER_SANITIZE_EMAIL);

                if($tmp!=$extension_name) // INVALID file name
                {
                    $layout->navigate_notification(t(344, 'Security error'), true, true);
                }
                else
                {
                    @mkdir(NAVIGATE_PATH.'/plugins/'.$extension_name);

                    $zip = new ZipArchive;
                    if($zip->open($_FILES['extension-upload']['tmp_name']) === TRUE)
                    {
                        $zip->extractTo(NAVIGATE_PATH.'/plugins/'.$extension_name);
                        $zip->close();

                        $layout->navigate_notification(t(374, "Item installed successfully."), false);
                    }
                    else // zip extraction failed
                    {
                        $layout->navigate_notification(t(262, 'Error uploading file'), true, true);
                    }
                }
            }

		default:
            $list = extension::list_installed();
			$out = extensions_grid($list);
			break;
	}

	return $out;
}

function extensions_grid($list)
{
    global $layout;
    global $website;

    $navibars = new navibars();
    $navibars->title(t(327, 'Extensions'));

    $navibars->add_actions(	array(	'<a href="#" id="extension-upload-button"><img height="16" align="absmiddle" width="16" src="img/icons/silk/package_add.png"> '.t(461, 'Install from file').'</a>'));

    $navibars->add_actions(	array ( 'search_form' ));

    $grid = new navigrid('extensions');

    $grid->set_header('
		<div class="navibrowse-path ui-corner-all">
			<a href="http://extensions.navigatecms.com" target="_blank"><img src="img/icons/silk/world.png" width="16px" height="16px" align="absbottom" /> '.t(369, 'More on').' Navigate CMS</a>
		</div>
	');

    $grid->item_size(170, 170);
    $grid->thumbnail_size(160, 100);

    $extensions = array();

    for($i=0; $i < count($list); $i++)
    {
        $extensions[] = array(
            'id'	=>  $list[$i]['code'],
            'name'	=>	'<div class="navigrid-item-title">'.$list[$i]['title'].'<br />v'.$list[$i]['version'].'</div>',
            'thumbnail' => NAVIGATE_URL.'/plugins/'.$list[$i]['code'].'/thumbnail.png',
            'description' => $list[$i]['description'],
            'header' => '',
            'footer' => '
				<div class="buttonset navigrid-item-buttonset" style=" font-size: 0.6em; margin-top: 5px; visibility: hidden; "
				     extension="'.$list[$i]['code'].'" extension-title="'.$list[$i]['title'].'"
				     run="'.$list[$i]['run'].'" enabled="'.$list[$i]['enabled'].'"  favorite="'.$list[$i]['favorite'].'">
				    <button class="navigrid-extensions-info" title="'.t(457, 'Information').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/information.png"></button>'.
                    //(empty($list[$i]['run'])?       '' : '<button class="navigrid-extensions-favorite" title="'.t(464, 'Favorite').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/heart_'.($list[$i]['favorite']=='1'? 'delete' : 'add').'.png"></button>').
                    (empty($list[$i]['options'])?   '' : '<button class="navigrid-extensions-settings" title="'.t(459, 'Settings').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cog.png"></button>').
			        (empty($list[$i]['update'])?    '' : '<button class="navigrid-extensions-update" title="'.t(463, 'Update available').': '.$list[$i]['update'].'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/asterisk_orange.png"></button>').
                    '<button '.(($list[$i]['enabled']==='0')? 'style="display: none;"' : '').' class="navigrid-extensions-disable" title="'.t(460, 'Disable').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/delete.png"></button>'.
                    '<button '.(($list[$i]['enabled']==='1')? 'style="display: none;"' : '').' class="navigrid-extensions-enable" title="'.t(462, 'Enable').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"></button>'.
				    '<button '.(($list[$i]['enabled']==='1')? 'style="display: none;"' : '').' class="navigrid-extensions-remove" title="'.t(35, 'Delete').'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cross.png"></button>
				</div>
			'
        );
    }

    $grid->items($extensions);

    $navibars->add_content($grid->generate());

    $navibars->add_content('<div id="navigrid-extension-information" title="" style=" display: none; "></div>');
    $navibars->add_content('<div id="navigrid-extension-options" title="" style=" display: none; "></div>');

	$navibars->add_content('
		<div id="navigrid-extensions-remove-confirmation" title="'.t(59, 'Confirmation').'" style=" display: none; ">
			'.t(57, 'Do you really want to delete the item?').'
		</div>'
    );

    $out = $navibars->generate();

    $layout->add_script('
        $(window).on("load", function()
        {
            $(".navigrid-item-buttonset").each(function(i, el)
            {
                $(el).hide().css("visibility", "visible");
                $(el).fadeIn();
                $(".navigrid-extensions-disable").addClass("ui-corner-right");
            });
        });

		$.getScript("lib/packages/extensions/extensions.js", function()
		{
			navigate_extensions_refresh();
		});

		function navitable_quicksearch(value)
		{
		    $(".navigrid-item").hide();

		    if(value=="")
		        $(".navigrid-item").show();
		    else
		    {
	            $(".navigrid-item").each(function(i, el)
	            {
	                var item_text = $(el).text().toLowerCase();
	                if( item_text.indexOf(value.toLowerCase()) >= 0 )
                        $(el).fadeIn();
	            });
		    }
		}
		$("#extension-upload-button").bind("click", function()
		{
		    $("#extension-upload-button").parent().find("form").remove();
            $("#extension-upload-button").after(\'<form action="?fid=extensions&act=extension_upload" enctype="multipart/form-data" method="post"><input type="file" name="extension-upload" style=" display: none;" /></form>\');
            $("#extension-upload-button").next().find("input").bind("change", function()
            {
                if($(this).val()!="")
                    $(this).parent().submit();
            });
            $("#extension-upload-button").next().find("input").trigger("click");

	        return false;
		});

	');

    return $out;
}

function extensions_options($extension, $saved=null)
{
    global $layout;
    global $website;

    $layout = null;
    $layout = new layout('navigate');

    if($saved!==null)
    {
        if($saved)
            $layout->navigate_notification(t(53, "Data saved successfully."), false);
        else
            $layout->navigate_notification(t(56, "Unexpected error"), true, true);
    }

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(327, 'Extensions'));

    $layout->navigate_media_browser();	// we can use media browser in this function

    $navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));

    $navibars->add_actions(	array(	'<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)    );

    $navibars->form();

    $navibars->add_tab(t(7, 'Configuration'));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));

    // show a language selector (only if it's a multi language website and has properties)
    if(!empty($extension->definition->options) && count($website->languages) > 1)
    {
        $website_languages_selector = $website->languages();
        $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

        $navibars->add_tab_content_row(array(	'<label>'.t(63, 'Languages').'</label>',
            $naviforms->buttonset('language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
        ));
    }

    foreach($extension->definition->options as $option)
    {
        $property = new property();
        $property->load_from_object($option, $extension->settings[$option->id], $extension);

        if($property->type == 'tab')
        {
            $navibars->add_tab($property->name);
            if(count($website->languages) > 1)
            {
                $website_languages_selector = $website->languages();
                $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(63, 'Languages').'</label>',
                        $naviforms->buttonset('language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
                    )
                );
            }
        }

        if($property->type == 'function')
        {
            if(!function_exists($option->value))
                continue;

            call_user_func(
                $option->value,
                array(
                    'extension' => $extension,
                    'navibars' => $navibars,
                    'naviforms' => $naviforms
                )
            );
        }
        else
        {
            $navibars->add_tab_content(navigate_property_layout_field($property));
        }
    }

    $layout->add_content('<div id="navigate-content" class="navigate-content ui-corner-all">'.$navibars->generate().'</div>');
    $layout->navigate_additional_scripts();
    $layout->add_script('
        $("html").css("background", "transparent");
    ');

    $out = $layout->generate();

    return $out;
}

function extensions_dialog($extension, $function, $params)
{
    global $layout;

    $layout = null;
    $layout = new layout('navigate');

    if(function_exists($function))
        call_user_func($function, $params);

    $out = $layout->generate();

    return $out;
}
?>