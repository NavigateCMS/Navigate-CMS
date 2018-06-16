<?php
require_once(NAVIGATE_PATH.'/lib/packages/payment_methods/payment_method.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');

function run()
{
    global $DB;
    global $website;
    global $layout;

	$out = '';
	$object = new payment_method();
			
	switch($_REQUEST['act'])
	{
        case 'json':
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
					foreach($ids as $id)
					{
						$object->load($id);
						$object->delete();
					}
					echo json_encode(true);
					break;
					
				default: // list or search	
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby= $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = " pm.website = ".intval($website->id)." ";

                    $permissions = array(
                        0 => '<img src="img/icons/silk/world.png" align="absmiddle" /> '.t(69, 'Published'),
                        1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" /> '.t(70, 'Private'),
                        2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" /> '.t(81, 'Hidden')
                    );

					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $object->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}

                    $sql = ' SELECT SQL_CALC_FOUND_ROWS
					                pm.id, pm.codename, pm.extension, pm.image, pm.permission, d.text as title                                    
							   FROM nv_payment_methods pm
						  LEFT JOIN nv_webdictionary d
						  		 	 ON pm.id = d.node_id
								 	AND d.node_type = "payment_method"
									AND d.subtype = "title"
									AND d.lang = "'.$website->languages_list[0].'"
									AND d.website = '.$website->id.'
							  WHERE '.$where.'						   
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;

                    if(!$DB->query($sql, 'array'))
                        throw new Exception($DB->get_last_error());
									
					$dataset = $DB->result();
					$total = $DB->foundRows();

                    $dataset = grid_notes::summary($dataset, 'payment_method', 'id');

					$out = array();					
											
					for($i=0; $i < count($dataset); $i++)
					{
					    $payment_method_image = $dataset[$i]['image'];
                        if(!empty($payment_method_image))
                            $payment_method_image = '<img src="'.file::file_url($payment_method_image, 'inline').'&width=64&height=48&border=true" />';
                        else
                            $payment_method_image = '-';

                        $extension_name = "";
                        if(!empty($dataset[$i]['extension']))
                        {
                            $extension = new extension();
                            $extension->load($dataset[$i]['extension']);
                            $extension_name = $extension->title;
                        }

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
                            1	=> $dataset[$i]['codename'],
                            2	=> $extension_name,
                            3	=> $payment_method_image,
                            4   => $dataset[$i]['title'],
                            5   => $permissions[$dataset[$i]['permission']],
                            6 	=> $dataset[$i]['_grid_notes_html']
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;

        case 'create':
		case 'edit':
			if(!empty($_REQUEST['id']))
				$object->load(intval($_REQUEST['id']));

			if(isset($_REQUEST['form-sent']))
			{
				$object->load_from_post();
				try
				{
					$object->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = payment_methods_form($object);
			break;
					
		case 'delete':
			if(!empty($_REQUEST['id']))
			{
				$object->load(intval($_REQUEST['id']));	
				if($object->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = payment_methods_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = payment_methods_form($object);
				}
			}
			break;
					
		case 'list':
		default:			
			$out = payment_methods_list();
			break;
	}
	
	return $out;
}

function payment_methods_list()
{
	$navibars = new navibars();
	$navitable = new navitable("payment_methods_list");
	
	$navibars->title(t(783, 'Payment methods'));

	$navibars->add_actions(
	    array(
	        '<a href="?fid=payment_methods&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid=payment_methods&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=payment_methods&act=json&_search=true&quicksearch=".$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid=payment_methods&act=json');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=payment_methods&act=edit&id=');
    $navitable->setGridNotesObjectName("payment_method");

    $navitable->addCol("ID", 'id', "40", "true", "left");
    $navitable->addCol(t(237, 'Code'), 'codename', "64", "true", "left");
    $navitable->addCol(t(617, 'Extension'), 'extension', "64", "true", "left");
    $navitable->addCol(t(157, 'Image'), 'image', "64", "false", "center");
    $navitable->addCol(t(67, 'Title'), 'title', "320", "true", "left");
    $navitable->addCol(t(68, 'Status'), 'permission', "80", "true", "center");
    $navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");

	$navibars->add_content($navitable->generate());

	return $navibars->generate();
}

function payment_methods_form($object)
{
    global $layout;
    global $events;
    global $user;
    global $website;
    global $current_version;

	$navibars = new navibars();
	$naviforms = new naviforms();
    $layout->navigate_media_browser();
	
	if(empty($object->id))
		$navibars->title(t(783, 'Payment methods').' / '.t(38, 'Create'));
	else
		$navibars->title(t(783, 'Payment methods').' / '.t(170, 'Edit').' ['.$object->id.']');

    $navibars->add_actions(
        array(
            '<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+M">
				<img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'
			</a>'
        )
    );

    if(empty($object->id))
    {
        if($user->permission('payment_methods.create')=='true')
        {
            $navibars->add_actions(
                array(
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					    <img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> ' . t(34, 'Save') . '
				    </a>'
                )
            );
        }
    }
    else
    {
        $navibars->add_actions(
            array(
                ($user->permission('payment_methods.edit')=='true'?
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : ""),
                ($user->permission("payment_methods.delete") == 'true'?
                    '<a href="#" onclick="navigate_delete_dialog();">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'
				</a>' : "")
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=payment_methods&act=delete&id='.$object->id.'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}

    if(!empty($object->id))
    {
        $notes = grid_notes::comments('payment_method', $object->id);
        $navibars->add_actions(
            array(
                '<a href="#" onclick="javascript: navigate_display_notes_dialog();">
					<span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span>
					<img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'
				</a>'
            )
        );
    }

	$extra_actions = array();
    if(!empty($object->id))
    {
        $events->add_actions(
            'payment_method',
            array(
                'item' => &$object,
                'navibars' => &$navibars
            ),
            $extra_actions
        );
    }

    if(!empty($object->id))
        $layout->navigate_notes_dialog('payment_method', $object->id);
	
	$navibars->add_actions(
	    array(
	        (!empty($object->id)? '<a href="?fid=payment_methods&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=payment_methods&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"), "", 'fa fa-database');
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $object->id));	
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($object->id)? $object->id : t(52, '(new)')).'</span>'
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(237, 'Code').'</label>',
			$naviforms->textfield('codename', $object->codename)
        )
    );

	$extensions = extension::list_installed('payment_method');

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(617, 'Extension').'</label>',
            $naviforms->select_from_object_array('extension', $extensions, 'code', 'title', $object->extension)
        )
    );
										
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(157, 'Image').'</label>',
			$naviforms->dropbox('image', $object->image, 'image')
        )
    );

    $permission_options = array(
        0 => t(69, 'Published'),
        1 => t(70, 'Private'),
        2 => t(81, 'Hidden')
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(68, 'Status').'</label>',
            $naviforms->selectfield(
                'permission',
                array_keys($permission_options),
                array_values($permission_options),
                $object->permission,
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

    $ws_languages = $website->languages();
    $default_language = array_keys($ws_languages);
    $default_language = $default_language[0];

    $navibars->add_tab('<i class="fa fa-pencil"></i> '.t(9, "Content"));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(63, 'Languages').'</label>',
            $naviforms->buttonset('language_selector', $ws_languages, $website->languages_list[0], "navigate_payment_methods_select_language(this);")
        )
    );

    foreach($website->languages_list as $lang)
    {
        $navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">');

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(67, 'Title').'</label>',
                $naviforms->textfield('title-'.$lang, @$object->dictionary[$lang]['title'])
            )
        );

        $navibars->add_tab_content_row(
            array(
                '<label>'.t(334, "Description").'</label>',
                $naviforms->editorfield('description-'.$lang, @$object->dictionary[$lang]['description'], NULL, $lang),
                '<br />'
            ),
            '',
            'lang="'.$lang.'"'
        );

        $navibars->add_tab_content('</div>');
    }

    // script will be bound to onload event at the end of this php function (after getScript is done)
    $onload_language = $_REQUEST['tab_language'];
    if(empty($onload_language))
        $onload_language = $website->languages_list[0];

    $layout->add_script('        
        $(document).on("keydown.ctrl_s", function (evt) { navigate_tabform_submit(1); return false; } );
        $(document).on("keydown.ctrl_m", function (evt) { navigate_media_browser(); return false; } );
    ');

    $layout->add_script('
        $.ajax({ 
            type: "GET",
	        dataType: "script",
	        url: "lib/packages/payment_methods/payment_methods.js?r='.$current_version->revision.'",
	        cache: true,
	        complete: function()
	        {
                if(typeof navigate_payment_methods_onload == "function")
				    navigate_payment_methods_onload("'.$onload_language.'");
	        }
	    });
    ');

	return $navibars->generate();
}
?>