<?php
require_once(NAVIGATE_PATH.'/lib/packages/themes/theme.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');

function run()
{
	global $user;	
	global $layout;
	global $website;
    global $theme;
	
	$out = '';

	switch($_REQUEST['act'])
	{
        case 'theme_info':
            echo '<iframe src="'.NAVIGATE_URL.'/themes/'.$_REQUEST['theme'].'/'.$_REQUEST['theme'].'.info.html'.'" scrolling="auto" frameborder="0"  width="100%" height="100%"></iframe>';
            core_terminate();
            break;

        case 'remove':
            $theme = new theme();
            $theme->load($_REQUEST['theme']);
            $status = $theme->delete();
            echo json_encode($status);
            core_terminate();
            break;

        /*
        case 'export':
            $out = themes_export_form();
            break;
        */

        case 'theme_sample_content_import':
            try
            {
                $theme->import_sample();
                $layout->navigate_notification(t(374, "Item installed successfully."), false);
            }
            catch(Exception $e)
            {
                $layout->navigate_notification($e->getMessage(), true, true);
            }

            $themes = theme::list_available();
            $out = themes_grid($themes);
            break;

        case 'theme_sample_content_export':
            if(empty($_POST))
                $out = themes_sample_content_export_form();
            else
            {
                $categories = explode(',', $_POST['categories']);
                $folder = $_POST['folder'];
                $items = explode(',', $_POST['elements']);
                $blocks = explode(',', $_POST['blocks']);
                $comments = explode(',', $_POST['comments']);

                theme::export_sample($categories, $items, $blocks, $comments, $folder);

                core_terminate();
            }
            break;

        case 'theme_upload':
            if($_FILES['theme-upload']['error']==0)
            {
                // uncompress ZIP and copy it to the themes dir
                $tmp = trim(substr($_FILES['theme-upload']['name'], 0, strpos($_FILES['theme-upload']['name'], '.')));
                $theme_name = filter_var($tmp, FILTER_SANITIZE_EMAIL);

                if($tmp!=$theme_name) // INVALID file name
                {
                    $layout->navigate_notification(t(344, 'Security error'), true, true);
                }
                else
                {
                    @mkdir(NAVIGATE_PATH.'/themes/'.$theme_name);

                    $zip = new ZipArchive;
                    if($zip->open($_FILES['theme-upload']['tmp_name']) === TRUE)
                    {
                        $zip->extractTo(NAVIGATE_PATH.'/themes/'.$theme_name);
                        $zip->close();

                        $layout->navigate_notification(t(374, "Item installed successfully."), false);
                    }
                    else // zip extraction failed
                    {
                        $layout->navigate_notification(t(262, 'Error uploading file'), true, true);
                    }
                }
            }
            // don't break, we want to show the themes grid right now

        case 'themes':
        default:
            if(@$_REQUEST['opt']=='install')
            {
                $website->theme = $_REQUEST['theme'];
                try
                {
                    $website->update();
                    $layout->navigate_notification(t(374, "Item installed successfully."), false);
                }
                catch(Exception $e)
                {
                    $layout->navigate_notification($e->getMessage(), true, true);
                }
            }

            $themes = theme::list_available();

            $out = themes_grid($themes);
            break;

    }
	
	return $out;
}

function themes_grid($list)
{
	global $layout;
	global $website;
	
	$navibars = new navibars();	
	$navibars->title(t(367, 'Themes'));

    $navibars->add_actions(	array(	'<a href="#" id="theme-upload-button"><img height="16" align="absmiddle" width="16" src="img/icons/silk/package_add.png"> '.t(461, 'Install from file').'</a>'));

    //$navibars->add_actions(	array(	'<a href="?fid=themes&act=export" id="theme-export-button"><img height="16" align="absmiddle" width="16" src="img/icons/silk/package_go.png"> '.t(475, 'Export').'</a>'));

    $navibars->add_actions(	array(	'<a href="?fid=themes&act=theme_sample_content_export" id="theme-sample-content-export-button"><img height="16" align="absmiddle" width="16" src="img/icons/silk/server_compressed.png"> '.t(480, 'Export sample content').'</a>'));

	$grid = new navigrid('themes');	
	
	$grid->set_header('
		<div class="navibrowse-path ui-corner-all">
			<a href="http://themes.navigatecms.com" target="_blank"><img src="img/icons/silk/world.png" width="16px" height="16px" align="absbottom" /> '.t(369, 'More on').' Navigate CMS</a>
		</div>
	');
	
	$grid->item_size(220, 220);
	$grid->thumbnail_size(138, 150);
    $grid->highlight_on_click = false;
	
	$themes = array();

	// current website theme
	if(!empty($website->theme))
	{
        $theme = new theme();
        $theme->load($website->theme);

		$themes[] = array(
			'id'	=>  $website->theme,
			'name'	=>	'<div class="navigrid-themes-title navigrid-themes-installed">'.$theme->title.'</div>',
			'thumbnail' => NAVIGATE_URL.'/themes/'.$website->theme.'/thumbnail.png',
			'header' => '
				<a href="#" class="navigrid-themes-info" theme="'.$website->theme.'" theme-title="'.$theme->title.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/information.png"></a>
			',
			'footer' => '
				<a href="?fid=websites&act=edit&id='.$website->id.'&tab=6" class="uibutton navigrid-themes-button navigrid-theme-configure"><img height="16" align="absmiddle" width="16" src="img/icons/silk/wrench_orange.png"> '.t(200, 'Options').'</a>
            '.(
                !file_exists(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$website->theme.'_sample.zip')?
                    '' : '<a href="#" class="uibutton navigrid-themes-button navigrid-theme-install-demo"><img height="16" align="absmiddle" width="16" src="img/icons/silk/wand.png"> '.t(484, 'Install demo').'</a>'
            )
		);
	}
	
	for($t=0; $t < count($list); $t++)
	{
		if($website->theme==$list[$t]['code']) continue;

		$themes[] = array(
			'id'	=>  $list[$t]['code'],
			'name'	=>	'<div class="navigrid-themes-title">'.$list[$t]['title'].'</div>',
			'thumbnail' => NAVIGATE_URL.'/themes/'.$list[$t]['code'].'/thumbnail.png',
			'header' => '
			    <a href="#" class="navigrid-themes-remove" theme="'.$list[$t]['code'].'" theme-title="'.$list[$t]['title'].'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"></a>
				<a href="#" class="navigrid-themes-info" theme="'.$list[$t]['code'].'" theme-title="'.$list[$t]['title'].'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/information.png"></a>
			',
			'footer' => '
				<a href="'.NAVIGATE_URL.'/themes/'.$list[$t]['code'].'/demo.html'.'" class="uibutton navigrid-themes-button" target="_blank"><img height="16" align="absmiddle" width="16" src="img/icons/silk/monitor.png"> '.t(274, 'Preview').'</a>
                <a href="#" class="uibutton navigrid-themes-button navigrid-themes-install" theme="'.$list[$t]['code'].'" target="_blank" style=" margin-left: 5px; "><img height="16" align="absmiddle" width="16" src="img/icons/silk/world_go.png"> '.t(365, 'Install').'</a>
            '
		);
	}
		
	$grid->items($themes);
	
	$navibars->add_content($grid->generate());

	$navibars->add_content('
		<div id="navigrid-themes-install-confirmation" title="'.t(59, 'Confirmation').'" style=" display: none; ">
			'.t(371, 'Installing a new theme removes the settings of the old one.').'<br />
			'.t(372, 'The list of available block types may also change.').'<br /><br />
			'.t(373, 'Are you sure you want to continue?').'					
		</div>
		
		<div id="navigrid-themes-information" title="" style=" display: none; "></div>
	');

    $navibars->add_content('
		<div id="navigrid-themes-install-demo-confirmation" title="'.t(59, 'Confirmation').'" style=" display: none; ">
			'.t(483, 'Do you really want to import the default website for the theme selected?').'
		</div>'
    );

    $navibars->add_content('
		<div id="navigrid-themes-remove-confirmation" title="'.t(59, 'Confirmation').'" style=" display: none; ">
			'.t(57, 'Do you really want to delete the item?').'
		</div>'
    );

    $out = $navibars->generate();

	$layout->add_script('
		$.getScript("lib/packages/themes/themes.js", function()
		{
			navigate_themes_init();
		});
	');
	
	return $out;
}

function themes_sample_content_export_form()
{
    // templates, blocks, files, properties
    global $user;
    global $DB;
    global $website;
    global $layout;
    global $theme;

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(367, 'Themes').' / '.t(480, 'Export sample content'));

    $layout->navigate_media_browser();	// we can use media browser in this function

    $navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+M"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));

    $navibars->add_actions(
        array(	'<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
    );

    $navibars->form();

    /*
    $navibars->add_tab(t(43, "Main"));

    $navibars->add_tab_content_row(array(
        '<label>'.t(67, 'Title').'</label>',
        $naviforms->textfield('theme-title', $website->name)
    ));
    */

    $navibars->add_tab(t(16, "Structure"));
    // select structure points to export
    $hierarchy = structure::hierarchy(0);
    $categories_list = structure::hierarchyList($hierarchy);

    $navibars->add_tab_content_row(array(
        '<label>'.t(330, 'Categories').'<br /></label>',
        '<div class="category_tree" id="category-tree-parent"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.$categories_list.'</div>',
        '<label>&nbsp;</label>',
        '<button id="theme_export_sample_content_select_all_categories">'.t(481, 'Select all').'</button>'
    ));

    $navibars->add_tab_content($naviforms->hidden('categories', ''));

    $layout->add_script('
		$("#category-tree-parent ul:first").kvaTree({
	        imgFolder: "js/kvatree/img/",
			dragdrop: false,
			background: "#f2f5f7",
			overrideEvents: true,
			onClick: function(event, node)
			{
				if($(node).find("span:first").hasClass("active"))
					$(node).find("span:first").removeClass("active");
				else
					$(node).find("span:first").addClass("active");

				var categories = new Array();

				$("#category-tree-parent span.active").parent().each(function()
				{
					categories.push($(this).attr("value"));
				});

				if(categories.length > 0)
					$("#categories").val(categories);
				else
					$("#categories").val("");
			}
		});

		$("#category-tree-parent li").find("span:first").css("cursor", "pointer");

        $("#theme_export_sample_content_select_all_categories").on("click", function()
        {
            var categories = new Array();

            $("#category-tree-parent").find("li").not(".separator").each(function(i, el)
            {
                $(el).find("span:first").addClass("active");
                categories.push($(el).attr("value"));
            });

    		$("#categories").val(categories);

            return false
        });
	');

    $navibars->add_tab(t(22, "Elements"));
    // select elements to export
    $navitable_items = new navitable("items_list");
    $navitable_items->setURL('?fid=items&act=1');
    $navitable_items->sortBy('date_modified', 'DESC');
	$navitable_items->setDataIndex('id');
    $navitable_items->addCol("ID", 'id', "40", "true", "left");
    $navitable_items->addCol(t(67, 'Title'), 'title', "350", "true", "left");
    $navitable_items->addCol(t(309, 'Social'), 'comments', "80", "true", "center");
    $navitable_items->addCol(t(78, 'Category'), 'category', "150", "true", "center");
    $navitable_items->addCol(t(266, 'Author'), 'author_username', "100", "true", "left");
    $navitable_items->addCol(t(85, 'Date published'), 'dates', "100", "true", "center");
    $navitable_items->addCol(t(80, 'Permission'), 'permission', "80", "true", "center");
    $navitable_items->after_select_callback = ' $("#elements").val(navitable_items_list_selected_rows); ';
    $navibars->add_tab_content($naviforms->hidden('elements', ''));
    $navibars->add_tab_content($navitable_items->generate());


    $navibars->add_tab(t(23, "Blocks"));
    // select block types to export
    $navitable_blocks = new navitable("blocks_list");
    $navitable_blocks->setURL('?fid=blocks&act=1');
    $navitable_blocks->sortBy('id', 'DESC');
    $navitable_blocks->setDataIndex('id');
    $navitable_blocks->addCol("ID", 'id', "40", "true", "left");
    $navitable_blocks->addCol(t(160, 'Type'), 'type', "120", "true", "center");
    $navitable_blocks->addCol(t(67, 'Title'), 'title', "400", "true", "left");
    $navitable_blocks->addCol(t(85, 'Date published'), 'dates', "100", "true", "center");
    $navitable_blocks->addCol(t(364, 'Access'), 'access', "40", "true", "center");
    $navitable_blocks->addCol(t(65, 'Enabled'), 'enabled', "40", "true", "center");
    $navitable_blocks->after_select_callback = ' $("#blocks").val(navitable_blocks_list_selected_rows); ';
    $navibars->add_tab_content($naviforms->hidden('blocks', ''));
    $navibars->add_tab_content($navitable_blocks->generate());

    $navibars->add_tab(t(250, "Comments"));
    // select block types to export
    $navitable_comments = new navitable("comments_list");
    $navitable_comments->setURL('?fid=comments&act=1');
    $navitable_comments->sortBy('date_created', 'desc');
    $navitable_comments->setDataIndex('id');
    $navitable_comments->addCol("ID", 'id', "80", "true", "left");
    $navitable_comments->addCol(t(180, 'Item'), 'item', "200", "true", "left");
    $navitable_comments->addCol(t(226, 'Date created'), 'date_created', "100", "true", "left");
    $navitable_comments->addCol(t(1, 'User'), 'user', "100", "true", "left");
    $navitable_comments->addCol(t(54, 'Text'), 'message', "200", "true", "left");
    $navitable_comments->addCol(t(68, 'Status'), 'status', "80", "true", "center");
    $navitable_comments->after_select_callback = ' $("#comments").val(navitable_comments_list_selected_rows); ';
    $navibars->add_tab_content($naviforms->hidden('comments', ''));
    $navibars->add_tab_content($navitable_comments->generate());


    $navibars->add_tab(t(89, "Files"));
    $navibars->add_tab_content_row(
        array(
            '<label>'.t(141, 'Folder').'</label>',
            $naviforms->dropbox('folder', 0, 'folder')
        )
    );

    $navibars->add_tab_content_row(
        '<div class="subcomment"><span class="ui-icon ui-icon-info" style="float: left;"></span> '.
            t(482, 'All sample files should be placed in a folder. Navigate CMS will also add files used in contents.').
        '</div>'
    );

    return $navibars->generate();
}

/* TODO: generate a theme from custom templates and blocks... maybe in NVCMS2.0?
function themes_export_form()
{
    // templates, blocks, files, properties
    global $user;
    global $DB;
    global $website;
    global $layout;
    global $theme;

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(367, 'Themes').' / '.t(475, 'Export'));

    $navibars->add_actions(
        array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
    );

    $navibars->form();

    $navibars->add_tab(t(43, "Main"));

    $navibars->add_tab_content(
        '<div class="subcomment"><span class="ui-icon ui-icon-info" style="float: left;"></span></div>'
    );

    $navibars->add_tab_content_row(array(
        '<label>'.t(67, 'Title').'</label>',
        $naviforms->textfield('theme-title', $website->name)
    ));

    $navibars->add_tab_content_row(array(
        '<label>'.t(237, 'Code').'</label>',
        $naviforms->textfield('theme-name', $website->name)
    ));

    $layout->add_script('
        $("#theme-name").on("keyup", function()
        {
            var title = $(this).val();
            title = title.replace(/\s+/g, "_");
            title = title.replace(/([\'"?:¿!#\\\\])/g, \'\');
            $(this).val(title.toLowerCase());
        });
        $("#theme-name").trigger("keyup");
    ');

    $navibars->add_tab_content_row(array(
        '<label>'.t(220, 'Version').'</label>',
        $naviforms->textfield('theme-version', '1.0')
    ));


    $navibars->add_tab_content_row(array(
        '<label>'.t(266, 'Author').'</label>',
        $naviforms->textfield('theme-author', $user->username)
    ));

    $navibars->add_tab_content_row(array(
        '<label>'.t(177, 'Website').'</label>',
        $naviforms->textfield('theme-website', $website->absolute_path())
    ));

    // languages (+auto create dictionary)
    // styles

    $navibars->add_tab(t(200, "Properties"));
    // similar to template properties

    $navibars->add_tab(t(20, "Templates"));
    // select templates to export

    $navibars->add_tab(t(23, "Blocks"));
    // select block types to export

    $navibars->add_tab(t(89, "Files"));
    // upload JS files
    // upload CSS files
    // upload IMG files
    // select files from database to be included

    // + demo structure, content & blocks?

    return $navibars->generate();
}
*/
?>