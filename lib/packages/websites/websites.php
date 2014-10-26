<?php
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');

function run()
{
	global $user;
	global $layout;
	global $DB;
	global $website;

	$out = '';
	$item = new website();

	switch($_REQUEST['act'])
	{
		case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
                    if($user->permission('websites.delete')=='true')
                    {
                        $ids = $_REQUEST['ids'];
                        foreach($ids as $id)
                        {
                            $item->load($id);
                            $item->delete();
                        }
                        echo json_encode(true);
                    }
                    core_terminate();
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

					$DB->queryLimit('id,name,subdomain,domain,folder,homepage,permission,favicon',
									'nv_websites',
									$where,
									$orderby,
									$offset,
									$max);

					$dataset = $DB->result();
					$total = $DB->foundRows();

					//echo $DB->get_last_error();

					$out = array();

					$permissions = array(
							0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(69, 'Published'),
							1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(70, 'Private'),
							2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(81, 'Hidden')
						);

					for($i=0; $i < count($dataset); $i++)
					{
						$homepage = 'http://';
						if(!empty($dataset[$i]['subdomain']))
							$homepage .= $dataset[$i]['subdomain'].'.';
						$homepage .= $dataset[$i]['domain'].$dataset[$i]['folder'].$dataset[$i]['homepage'];

                        $favicon = '';
                        if(!empty($dataset[$i]['favicon']))
                            $favicon = '<img src="'.NVWEB_OBJECT.'?type=img&id='.$dataset[$i]['favicon'].'&width=16&height=16" align="absmiddle" />';

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $favicon,
							2	=> $dataset[$i]['name'],
							3	=> '<a href="'.$homepage.'" target="_blank"><img align="absmiddle" src="'.NAVIGATE_URL.'/img/icons/silk/house_link.png"></a> '.$homepage,
							4	=> $permissions[$dataset[$i]['permission']]
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

			if(isset($_REQUEST['form-sent']) && $user->permission('websites.edit')=='true')
			{
				$item->load_from_post();
				try
				{
					$item->save();
					$id = $item->id;
					unset($item);
					$item = new website();
					$item->load($id);

					$layout->navigate_notification(t(53, "Data saved successfully."), false);
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);
				}
			}

			$out = websites_form($item);
			break;

		case 4: // remove
			if(!empty($_REQUEST['id']) && ($user->permission('websites.delete')=='true'))
			{
				$item->load(intval($_REQUEST['id']));
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);

                    // if we don't have any websites, tell user a new one will be created
                    $test = $DB->query_single('id', 'nv_websites');

                    if(empty($test) || !$test)
                    {
                        $layout->navigate_notification(t(520, 'No website found; a default one has been created.'), false, true);
                        $nwebsite = new website();
                        $nwebsite->create_default();
                    }

                    $out = websites_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = websites_form($item);
				}
			}
			break;

		case 5:	// search an existing path
			$DB->query('SELECT path as id, path as label, path as value
						  FROM nv_paths
						 WHERE path LIKE '.protect('%'.$_REQUEST['term'].'%').'
						   AND website = '.protect($_REQUEST['wid']).'
				      ORDER BY path ASC
					     LIMIT 30',
						'array');

			echo json_encode($DB->result());

			core_terminate();
			break;
			
		case 'email_test':
			$website->mail_server = $_REQUEST['mail_server'];
			$website->mail_port = $_REQUEST['mail_port'];
			$website->mail_address = $_REQUEST['mail_address'];
			$website->mail_user = $_REQUEST['mail_user'];
            $website->mail_security = ($_REQUEST['mail_security']=="true" || $_REQUEST['mail_security']=="1")? "1" : "0";

			if(!empty($_REQUEST['mail_password']))
				$website->mail_password = $_REQUEST['mail_password'];

			$ok = navigate_send_email(APP_NAME, APP_NAME.'<br /><br />'.NAVIGATE_URL, $_REQUEST['send_to']);
			echo json_encode($ok);
			core_terminate();

			break;

        case 'reset_statistics':
            if($user->permission('websites.edit')=='true')
            {
                $DB->execute('UPDATE nv_items SET views = 0 WHERE website = '.$website->id);
                $DB->execute('UPDATE nv_paths SET views = 0 WHERE website = '.$website->id);
                $DB->execute('UPDATE nv_structure SET views = 0 WHERE website = '.$website->id);
                echo 'true';
            }
            core_terminate();
            break;

		case 0: // list / search result
		default:
			$out = websites_list();
			break;
	}

	return $out;
}

function websites_list()
{
    global $user;

	$navibars = new navibars();
	$navitable = new navitable("websites_list");

	$navibars->title(t(241, 'Websites'));

	$navibars->add_actions(
        array(
            (($user->permission('websites.edit')=='true')? '<a href="?fid='.$_REQUEST['fid'].'&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
            //(($user->permission('websites.edit')=='true')? '<a href="?fid='.$_REQUEST['fid'].'&act=wizard"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').' [Wizard]</a>' : ''),
            '<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
            'search_form'
        )
    );

	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);

	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=2&id=');

	$navitable->addCol("ID", 'id', "80", "true", "left");
    $navitable->addCol(t(328, 'Favicon'), 'favicon', "32", "true", "center");
	$navitable->addCol(t(159, 'Name'), 'name', "200", "true", "left");
	$navitable->addCol(t(187, 'Homepage'), 'homepage', "300", "true", "center");
	$navitable->addCol(t(68, 'Status'), 'permission', "100", "true", "center");

	$navibars->add_content($navitable->generate());

	return $navibars->generate();

}

function websites_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
    global $theme;
    global $events;

	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we want to use media browser in this function

	if(empty($item->id))
		$navibars->title(t(241, 'Websites').' / '.t(38, 'Create'));
	else
		$navibars->title(t(241, 'Websites').' / '.t(170, 'Edit').' ['.$item->id.']');

    if($user->permission('websites.edit')=='true')
    {
        $navibars->add_actions(		array(	'<a href="#" action="navigate_reset_statistics" onclick="javascript: navigate_reset_statistics();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/chart_line.png"> '.t(429, 'Reset statistics').'</a>'	));
        $layout->add_script('
            function navigate_reset_statistics()
            {
                var confirmation = "<div>'.t(430, 'Do you really want to remove all statistics of this website?').'</div>";
                $(confirmation).dialog({
                        resizable: true,
                        height: 150,
                        width: 300,
                        modal: true,
                        title: "'.t(59, 'Confirmation').'",
                        buttons: {
                            "'.t(190, 'Ok').'": function() {
                                $(this).dialog("close");

                                $.post(
                                    "?fid=websites&act=reset_statistics",
                                    {},
                                    function(data)
                                    {
                                        $("a[action=\'navigate_reset_statistics\']").parent().fadeOut();
                                    }
                                );
                            },
                            "'.t(58, 'Cancel').'": function() {
                                $(this).dialog("close");
                            }
                        }
                });
            }
        ');

        $navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));

        if(empty($item->id))
        {
            $navibars->add_actions(
                array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
            );
        }
        else
        {
            $navibars->add_actions(
                array(
                    '<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
                    (($user->permission('websites.delete')=='true')? '<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>' : '')
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
    }

	$navibars->add_actions(
        array(
            (($user->permission('websites.edit')=='true' && !empty($item->id))? '<a href="?fid=websites&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
            '<a href="?fid=websites&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
            'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(7, "Settings"));

	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));

	$navibars->add_tab_content_row(array(	'<label>'.t(67, 'Title').'</label>',
											$naviforms->textfield('title', $item->name) ));

	$navibars->add_tab_content_row(array(	'<label>'.t(287, 'Protocol').'</label>',
											$naviforms->selectfield('protocol',
												array(
														0 => 'http://',
														1 => 'https://'
													),
												array(
														0 => 'HTTP',
														1 => 'HTTPS ['.t(288, 'Secured site (requires certificate)').']'
													),
												$item->protocol
											)
										)
									);

	$navibars->add_tab_content_row(array(	'<label>'.t(228, 'Subdomain').'</label>',
											$naviforms->textfield('subdomain', $item->subdomain),
											'<span class="navigate-form-row-info">'.t(230, 'Ex.').' www</span>' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(229, 'Domain').'</label>',
											$naviforms->textfield('domain', $item->domain),
											'<span class="navigate-form-row-info">'.t(230, 'Ex.').' naviwebs.net</span>' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(141, 'Folder').'</label>',
											$naviforms->textfield('folder', $item->folder),
											'<span class="navigate-form-row-info">'.t(230, 'Ex.').' /new-website</span>' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(187, 'Homepage').'</label>',
											$naviforms->autocomplete('homepage', @$item->homepage, '?fid='.$_REQUEST['fid'].'&wid='.$item->id.'&act=5'),
											'<span class="navigate-form-row-info">'.t(230, 'Ex.').' /en/home</span>' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<div class="subcomment"><img src="img/icons/silk/house.png" align="absmiddle" /> <span id="navigate-website-home-url"></span></div>' ));

	$layout->add_script('
		$("#subdomain,#domain,#folder,#homepage").bind("keyup", navigate_website_update_home_url);
		$("#protocol").bind("change", navigate_website_update_home_url);

		function navigate_website_update_home_url()
		{
			var url = $("#protocol").val();
			if($("#subdomain").val().length > 0)
				url += $("#subdomain").val() + ".";
			url += $("#domain").val();
			url += $("#folder").val();
			url += $("#homepage").val();

			$("#navigate-website-home-url").html(url);
		}

		navigate_website_update_home_url();
	');


	if(!empty($item->theme))
	{
		$navibars->add_tab_content_row(array(
            '<label>'.t(368, 'Theme').' <a href="?fid=8&act=themes"><img height="16" align="absmiddle" width="16" src="img/icons/silk/rainbow.png" /></a></label>',
			'<strong>'.$theme->title.'</strong>'
		));
	}

    $navibars->add_tab_content_row(array(
            '<label>'.t(515, 'Not found paths').'...</label>',
            $naviforms->selectfield(
                'wrong_path_action',
                array(
                    0 => 'blank',
                    1 => 'homepage',
                    2 => 'theme_404',
                    3 => 'http_404'
                ),
                array(
                    0 => t(516, 'Show a blank page'),
                    1 => t(517, 'Redirect to home page'),
                    2 => t(518, 'Use the custom 404 template of a theme (if exists)'),
                    3 => t(519, 'Send a 404 HTTP error header')
                ),
                $item->wrong_path_action,
                '',
                false
            )
        )
    );

	$navibars->add_tab_content_row(array(
            '<label>'.t(68, 'Status').'</label>',
            $naviforms->selectfield(
                'permission',
                array(
                        0 => 0,
                        1 => 1,
                        2 => 2
                ),
                array(
                        0 => t(69, 'Published'),
                        1 => t(70, 'Private'),
                        2 => t(71, 'Closed')
                ),
                $item->permission,
                '',
                false,
                array(
                        0 => t(360, 'Visible to everybody'),
                        1 => t(359, 'Visible only to Navigate CMS users'),
                        2 => t(358, 'Hidden to everybody')
                )
            )
        )
    );

    $layout->add_script('
        $("#permission").on("change", function()
        {
            if($(this).val() > 0)
                $("#redirect_to").parent().show();
            else
                $("#redirect_to").parent().hide();
        });

        $("#permission").trigger("change");
    ');

    $navibars->add_tab_content_row(array(
            '<label>'.t(505, 'Redirect to').'</label>',
            $naviforms->textfield('redirect_to', $item->redirect_to),
            '<span class="navigate-form-row-info">'.t(230, 'Ex.').' /landing_page.html</span>'
        )
    );


	$navibars->add_tab(t(63, "Languages"));

    // system locales
    $locales = $item->unix_locales();
    $system = 'UNIX';
    if(empty($locales)) // seems like a MS Windows Server (c)
    {
        $locales = $item->windows_locales();
        $system = 'MS Windows';
    }

	/* Languages selector */
	if(!is_array($item->languages_list))
        $item->languages_list = array();

    $table = new naviorderedtable("website_languages_table");
    //$table->setWidth("600px");
    $table->setHiddenInput("languages-order");

    $navibars->add_tab_content($naviforms->hidden('languages-order', implode('#', $item->languages_list)));

    $table->addHeaderColumn(t(159, 'Name'), 160);
    $table->addHeaderColumn(t(237, 'Code'), 60);
    $table->addHeaderColumn(t(471, 'Variant').'/'.t(473, 'Region'), 120);
    $table->addHeaderColumn(t(474, 'System locale').' ('.$system.')', 150);
    $table->addHeaderColumn(t(64, 'Published'), 60);
    $table->addHeaderColumn(t(35, 'Remove'), 60);

    $DB->query('SELECT code, name FROM nv_languages');
    $languages_rs = $DB->result();
    $languages = array();

    foreach($languages_rs as $lang)
        $languages[$lang->name] = $lang->code;

    if(empty($item->languages))
    {
        // load default language settings
        $item->languages_list = array('en');
        $item->languages_published = array('en');
        $item->languages = array(
            'en' => array(
                'language' => 'en',
                'variant' => '',
                'code' => 'en',
                'system_locale' => ($system=='MS Windows'? 'ENU_USA' : 'en_US.utf8')
            )
        );
    }

    if(empty($item->languages))
        $item->languages = array();

    $p = 0;
    foreach($item->languages as $lcode => $ldef)
    {
        $p++;
        $published = (array_search($lcode, $item->languages_published)!==false);
        $variant = !empty($ldef['variant']);

        $select_language = $naviforms->select_from_object_array('language-id[]', $languages_rs, 'code', 'name', $ldef['language'], ' width: 150px; ');
        $select_locale   = $naviforms->selectfield('language-locale[]', array_keys($locales), array_values($locales), $ldef['system_locale'], '', false, array(), 'width: 300px;');

        $table->addRow($p, array(
            array('content' => $select_language, 'align' => 'left'),
            array('content' => '<div style=" white-space: nowrap; "><input type="text" name="language-code[]" value="'.$ldef['language'].'" style="width: 30px;" /></div>', 'align' => 'left'),
            array('content' => '<input type="checkbox" name="language-variant[]" value="1" '.($variant? 'checked="checked"': '').' style="float:left;" /> <input type="text" name="language-variant-code[]" value="'.$ldef['variant'].'" style="width: 75px;" />', 'align' => 'left'),
            array('content' => $select_locale, 'align' => 'left'),
            array('content' => '<input type="hidden" name="language-published[]" value="'.($published? '1' : '0').'" /><input type="checkbox" value="'.$lcode.'" '.($published? 'checked="checked"': '').' onclick=" if($(this).is(\':checked\')) { $(this).prev().val(1); } else { $(this).prev().val(0); }; " />', 'align' => 'center'),
            array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_websites_language_remove(this);" />', 'align' => 'center')
        ));
    }

    $navibars->add_tab_content_row(array(
            '<label>'.t(63, 'Languages').'</label>',
            '<div>'.$table->generate().'</div>',
            '<div class="subcomment">
                <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'
            </div>' )
    );

    $navibars->add_tab_content_row(array(
        '<label>&nbsp;</label>',
        '<button id="websites-languages-add"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>')
    );

    $layout->add_script('
        $("#website_languages_table tr").eq(1).find("td:last").children().hide();
        $(\'input[name="language-variant[]"]\').each(function(i, el)
        {
            if($(el).is(":checked"))
                $(el).next().removeClass("ui-state-disabled");
            else
                $(el).next().val("").addClass("ui-state-disabled");
        });

        $(\'input[name="language-variant-code[]"]\').on("click", function()
        {
            if(!$(this).prev().is(":checked"))
                $(this).prev().trigger("click");
        });

        $(\'select[name="language-id[]"]\').live("change", function()
        {
            var input = $(this).parent().next().find("input");
            $(input).val($(this).val());
            $(input).effect("highlight", {}, 2000);
        });

        $(\'input[name="language-variant[]"]\').live("change", function()
        {
            if($(this).is(":checked"))
                $(this).next().removeClass("ui-state-disabled");
            else
                $(this).next().val("").addClass("ui-state-disabled");
        });

        $("#websites-languages-add").on("click", function()
        {
            var tr = $("#website_languages_table").find("tr").eq(1).clone();
            $(tr).attr("id", new Date().getTime());

            $("#website_languages_table").find("tbody:last").append(tr);
            $("#website_languages_table").tableDnD({
                onDrop: function(table, row)
                {
                    navigate_naviorderedtable_website_languages_table_reorder();
                }
            });

            navigate_naviorderedtable_website_languages_table_reorder();

            $(tr).find("td:first").find("a,div").remove();
            $(tr).find("td").eq(3).find("a,div").remove();

            navigate_selector_upgrade($(tr).find("td:first").find("select"));
            navigate_selector_upgrade($(tr).find("td").eq(3).find("select"));

            return false;
        });

        function navigate_websites_language_remove(el)
        {
            $(el).parent().parent().remove();
        }

        function navigate_naviorderedtable_website_languages_table_reorder()
        {
            $("#website_languages_table tr").find("td:last").not(":first").children().show();
            $("#website_languages_table tr").eq(1).find("td:last").children().hide();
        }
    ');


    $navibars->add_tab(t(485, "Aliases"));

    $table = new naviorderedtable("website_aliases_table");

    $table->addHeaderColumn(t(486, 'Alias'), 160);
    $table->addHeaderColumn('', 24);
    $table->addHeaderColumn(t(487, 'Real URL'), 60);
    $table->addHeaderColumn(t(35, 'Remove'), 60);

    $table->addRow($lang->code, array(
        array('content' => '<div style="width: 308px;">http://example.domain.com/demo</div>', 'align' => 'left'),
        array('content' => '&rarr;', 'align' => 'center'),
        array('content' => '<div style="width: 308px;">http://www.domain.com/example/demo</div>', 'align' => 'left'),
        array('content' => '', 'align' => 'left')
    ));

    if(!is_array($item->aliases))
        $item->aliases = array();
    foreach($item->aliases as $alias => $realurl)
    {
        $table->addRow($lang->code, array(
            array('content' => '<input type="text" name="website-aliases-alias[]" value="'.$alias.'" style="width: 300px;" />', 'align' => 'left'),
            array('content' => '&rarr;', 'align' => 'center'),
            array('content' => '<input type="text" name="website-aliases-real[]" value="'.$realurl.'" style="width: 300px;" />', 'align' => 'left'),
            array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" onclick="navigate_websites_aliases_remove(this);" />', 'align' => 'center')
        ));
    }

    $navibars->add_tab_content_row(array(
            '<label>'.t(485, 'Aliases').'</label>',
            '<div>'.$table->generate().'</div>',
            '<div class="subcomment">
                <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'
            </div>' )
    );

    $navibars->add_tab_content_row(array(
            '<label>&nbsp;</label>',
            '<button id="websites-aliases-add"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>')
    );

    $layout->add_script('
        $("#websites-aliases-add").on("click", function()
        {
            var tr = $("<tr><td></td><td></td><td></td><td></td></tr>");
            $(tr).attr("id", new Date().getTime());
            $(tr).find("td").eq(0).html("<input type=\"text\" name=\"website-aliases-alias[]\" style=\"width: 300px;\" />");
            $(tr).find("td").attr("align", "center").eq(1).html("&rarr;");
            $(tr).find("td").eq(2).html("<input type=\"text\" name=\"website-aliases-real[]\" style=\"width: 300px;\" />");
            $(tr).find("td").attr("align", "center").eq(3).html("<img src=\"'.NAVIGATE_URL.'/img/icons/silk/cancel.png\" onclick=\"navigate_websites_aliases_remove(this);\" />");

            $("#website_aliases_table").find("tbody:last").append(tr);
            $("#website_aliases_table").tableDnD();
            return false;
        });

        function navigate_websites_aliases_remove(el)
        {
            $(el).parent().parent().remove();
        }
    ');


	$navibars->add_tab(t(9, "Content"));

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(50, 'Date format').'</label>',
			$naviforms->selectfield(
                'date_format',
                array(
                    0 => 'd/m/Y',
                    1 => 'd-m-Y',
                    2 => 'm/d/Y',
                    3 => 'm-d-Y',
                    4 => 'Y-m-d',
                    5 => 'Y/m/d'
                ),
                array(
                    0 => date('d/m/Y'),
                    1 => date('d-m-Y'),
                    2 => date('m/d/Y'),
                    3 => date('m-d-Y'),
                    4 => date('Y-m-d'),
                    5 => date('Y/m/d')
                ),
                $item->date_format
            )
        )
    );

	$timezones = property::timezones();

	if(empty($item->default_timezone))
		$item->default_timezone = date_default_timezone_get();

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(207, 'Default timezone').'</label>',
			$naviforms->selectfield("default_timezone", array_keys($timezones), array_values($timezones), $item->default_timezone)
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(433, 'Resize uploaded images').'</label>',
			$naviforms->selectfield('resize_uploaded_images',
                array(
                    0 => 0,
                    1 => 600,
                    2 => 800,
                    3 => 960,
                    4 => 1200,
                    5 => 1600,
                    6 => 2000
                ),
                array(
                    0 => t(434, 'Keep original file'),
                    1 => '600 px',
                    2 => '800 px',
                    3 => '960 px',
                    4 => '1200 px',
                    5 => '1600 px',
                    6 => '2000 px'
                ),
                $item->resize_uploaded_images
            ),
            '<span class="navigate-form-row-info">'.t(435, 'Maximum width or height').'</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>tinyMCE CSS</label>',
			$naviforms->textfield('tinymce_css', $item->tinymce_css),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' /css/style.content.css</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(328, 'Favicon').'</label>',
			$naviforms->dropbox('website-favicon', $item->favicon)
        )
    );

    // default comment options for elements

    $navibars->add_tab_content_row(array(
            '<label>'.t(252, 'Comments enabled for').'</label>',
            $naviforms->selectfield('comments_enabled_for',
                array(
                    0 => 0,
                    1 => 1,
                    2 => 2
                ),
                array(
                    0 => t(253, 'Nobody'),
                    1 => t(24, 'Registered users'),
                    2 => t(254, 'Everyone')
                ),
                $item->comments_enabled_for
            )
        )
    );

    $webuser_name = '';
    if($item->comments_default_moderator=="c_author")
        $webuser_name = t(545, 'Content author');
    else if(!empty($item->comments_default_moderator))
        $webuser_name = $DB->query_single('username', 'nv_users', ' id = '.intval($item->comments_default_moderator));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(255, 'Moderator').'</label>',
            $naviforms->textfield('comments_default_moderator-text', $webuser_name),
            $naviforms->hidden('comments_default_moderator', $item->comments_default_moderator),
            '<span style="display: none;" id="comments_default_moderator-helper">'.t(535, "Find user by name").'</span>',
            '<div class="subcomment"><img align="absmiddle" src="'.NAVIGATE_URL.'/img/icons/silk/information.png" /> '.t(256, 'Leave blank to accept all comments').'</div>'
        )
    );

    $layout->add_script('
        // comments moderator autocomplete
        $("#comments_default_moderator-text").select2(
        {
            placeholder: $("#comments_default_moderator-helper").text(),
            minimumInputLength: 0,
            ajax: {
                url: "?fid=items&act=json_find_user",
                dataType: "json",
                quietMillis: 100,
                data: function (term, page)
                {   // page is the one-based page number tracked by Select2
                    return {
                        username: term,
                        nd: new Date().getTime(),
                        page_limit: 30, // page size
                        page: page // page number
                    };
                },
                results: function (data, page)
                {
                    data.rows.unshift({id: "c_author", username: "{'.t(545, 'Content author').'}" });
                    var more = (page * 5) < data.total; // whether or not there are more results available
                    // notice we return the value of more so Select2 knows if more results can be loaded
                    return {results: data.rows, more: more};
                }
            },
            formatResult: function(row) { return row.username; },
            formatSelection: function(row) { return row.username + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>"; },
            triggerChange: true,
            allowClear: true,
            initSelection : function (element, callback)
            {
                var data = {
                    id: $("#comments_default_moderator").val(),
                    username: element.val()
                };

                callback(data);
            }
        });

        $("#comments_default_moderator-text").on("change", function(e)
        {
            $("#comments_default_moderator").val(e.val);
        });
    ');


    /* TAB EMAIL */

	$navibars->add_tab(t(44, "E-Mail"));

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(231, 'Server').'</label>',
			$naviforms->textfield('mail_server', $item->mail_server),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' localhost, mail.yourdomain.com</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(232, 'Port').'</label>',
			$naviforms->textfield('mail_port', $item->mail_port),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' 25</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(427, 'TLS/SSL required').'</label>',
			$naviforms->checkbox('mail_security', $item->mail_security)
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(1, 'User').'</label>',
			$naviforms->textfield('mail_user', $item->mail_user),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' web@yourdomain.com</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(233, 'Address').'</label>',
			$naviforms->textfield('mail_address', $item->mail_address),
			'<span class="navigate-form-row-info">'.t(230, 'Ex.').' web@yourdomain.com</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(2, 'Password').'</label>',
			'<input type="password" name="mail_password" id="mail_password"  value="" size="32" />',
			'<span class="navigate-form-row-info">'.t(48, "Leave blank to keep the current value").'</span>'
        )
    );


	if(empty($item->contact_emails))	$item->contact_emails = array();

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(263, 'Support E-Mails').'</label>',
			$naviforms->textarea('contact_emails', implode("\n", $item->contact_emails)),
			'<span class="navigate-form-row-info">'.t(264, "One entry per line").'</span>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
			'<button id="mail_test"><img src="'.NAVIGATE_URL.'/img/icons/silk/email_go.png" align="absmiddle" /> '.t(390, "Test").'</button>'
        )
    );

	$layout->add_script('
		$("#mail_test").bind("click", function()
		{
			navigate_status("'.t(391, "Trying to send a test e-mail...").'", "loader", true);
			$.ajax({
			  type: "POST",
			  url: "?fid='.$_GET['fid'].'&act=email_test",
			  data: {
				 mail_server: $("#mail_server").val(),
				 mail_port: $("#mail_port").val(),
				 mail_security: $("#mail_security").is(":checked"),
				 mail_user: $("#mail_user").val(),
				 mail_address: $("#mail_address").val(),
				 mail_password: $("#mail_password").val(),
				 send_to: $("#contact_emails").val()
			  },
			  success: function(data)
			  {
				  navigate_status(navigate_lang_dictionary[42], "ready"); 
				  
				  if(!data)
				  	navigate_notification("'.t(56, "Unexpected error.").'");
				  else
				  	navigate_notification("'.t(392, "E-Mail sent").'");
			  },
			  dataType: "json"
			});
			return false;
		});
	');

    /* METATAGS TAB */
    if(!empty($item->id) && !empty($item->languages))
    {
        $navibars->add_tab(t(513, "Metatags"));

        $website_languages_selector = $item->languages();
        $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

        $navibars->add_tab_content_row(array(	'<label>'.t(63, 'Languages').'</label>',
            $naviforms->buttonset('metatags_language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
        ));

        foreach($item->languages_list as $lang)
        {
            $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(334, 'Description').' '.$language_info.'</label>',
                    $naviforms->textfield('metatag_description-'.$lang, $item->metatag_description[$lang]),
                    '<span class="navigate-form-row-info">150-160</span>'
                ),
                '',
                'lang="'.$lang.'"'
            );


            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(536, 'Keywords').' '.$language_info.'</label>',
                    $naviforms->textfield('metatag_keywords-'.$lang, $item->metatag_keywords[$lang]),
                ),
                '',
                'lang="'.$lang.'"'
            );

            $layout->add_script('
                $("#metatag_keywords-'.$lang.'").tagsInput({
                    defaultText: "",
                    width: $("#metatag_keywords-'.$lang.'").width(),
                    height: 100
                });
                $("#metatag_keywords-'.$lang.'").parent().find("select").css("width", "auto");
            ');

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(514, "Additional metatags").' '.$language_info.'</label>',
                    $naviforms->scriptarea('metatags-'.$lang, $item->metatags[$lang], 'html', ' width: 75%; height: 100px; ' )
                ),
                '',
                'lang="'.$lang.'"'
            );
        }
    }


    /* SERVICES TAB */

    $navibars->add_tab(t(178, "Services"));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(498, 'Additional scripts').'</label>',
            $naviforms->scriptarea('additional_scripts', $item->additional_scripts, 'js', ' width: 600px; height: 250px; ' ),
            '<div style="clear: both;"><label>&nbsp;</label>&lt;script type="text/javascript"&gt;...&lt;/script&gt;</div>'
        )
    );


    if(!empty($item->theme))
    {
        $navibars->add_tab(t(368, 'Theme').': '.$theme->title);

        if(!is_array($theme->options))
            $theme->options = array();

        // show a language selector (only if it's a multilanguage website and has properties)
        if(!empty($theme->options) && count($item->languages) > 1)
        {
            $website_languages_selector = $item->languages();
            $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(63, 'Languages').'</label>',
                    $naviforms->buttonset('language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);")
                )
            );
        }

        // common property: style

        // 1: get available style IDs
        $styles_values = array_keys((array)$theme->styles);
        if(!is_array($styles_values))
            $styles_values = array();

        // 2: prepare array of style ID => style name
        $styles = array();
        foreach($styles_values as $sv)
        {
            $styles[$sv] = $theme->styles->$sv->name;
            if(empty($styles[$sv]))
                $styles[$sv] = $sv;

            $styles[$sv] = $theme->t($styles[$sv]);
        }

        $property = new property();
        $property->id = 'style';
        $property->name = t(431, 'Style');
        $property->type = 'option';
        $property->options = serialize($styles);
        $property->value = $item->theme_options->style;
        $navibars->add_tab_content(navigate_property_layout_field($property));

        foreach($theme->options as $theme_option)
        {
            $property = new property();
            $property->load_from_theme($theme_option, $item->theme_options->{$theme_option->id});
            $navibars->add_tab_content(navigate_property_layout_field($property));
        }
    }

    $events->trigger(
        'websites',
        'edit',
        array(
            'item' => &$item,
            'navibars' => &$navibars,
            'naviforms' => &$naviforms
        )
    );

	return $navibars->generate();
}

?>