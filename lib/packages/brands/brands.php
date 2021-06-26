<?php
require_once(NAVIGATE_PATH.'/lib/packages/brands/brand.class.php');

function run()
{
    global $DB;
    global $website;
    global $layout;

	$out = '';
	$object = new brand();
			
	switch($_REQUEST['act'])
	{
        case 'json':
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
                    if(naviforms::check_csrf_token('header'))
                    {
                        $ids = $_REQUEST['ids'];
                        foreach($ids as $id)
                        {
                            $object->load($id);
                            $object->delete();
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
                    $parameters = array();
                    $where = " website = ".intval($website->id)." ";

					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
                        {
                            list($qs_where, $qs_params) = $object->quicksearch($_REQUEST['quicksearch']);
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
                        !in_array($_REQUEST['sidx'], array('id', 'name'))
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];
				
					$DB->queryLimit(
					    'id,name,image',
                        'nv_brands',
                        $where,
                        $orderby,
                        $offset,
                        $max,
                        $parameters
                    );
									
					$dataset = $DB->result();
					$total = $DB->foundRows();

                    $dataset = grid_notes::summary($dataset, 'brand', 'id');

					$out = array();					
											
					for($i=0; $i < count($dataset); $i++)
					{
					    $brand_image = $dataset[$i]['image'];
                        if(!empty($brand_image))
                        {
                            $brand_image = '<img src="'.file::file_url($brand_image, 'inline').'&width=64&height=48&border=true" />';
                        }
                        else
                        {
                            $brand_image = '-';
                        }

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> $brand_image,
							2	=> core_special_chars($dataset[$i]['name']),
                            3 	=> $dataset[$i]['_grid_notes_html']
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
            {
                $object->load(intval($_REQUEST['id']));
            }

			if(isset($_REQUEST['form-sent']))
			{
				$object->load_from_post();
				try
				{
                    naviforms::check_csrf_token();
					$object->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = brands_form($object);
			break;
					
		case 'delete':
            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
            }
            else if(!empty($_REQUEST['id']))
			{
				$object->load(intval($_REQUEST['id']));	
				if($object->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = brands_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = brands_form($object);
				}
			}
			break;
					
		case 'list':
		default:			
			$out = brands_list();
			break;
	}
	
	return $out;
}

function brands_list()
{
	$navibars = new navibars();
	$navitable = new navitable("brands_list");
	
	$navibars->title(t(681, 'Brands'));

	$navibars->add_actions(
	    array(
	        '<a href="?fid=brands&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid=brands&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
    {
        $nv_qs_text = core_purify_string($_REQUEST['navigate-quicksearch'], true);
        $navitable->setInitialURL("?fid=brands&act=json&_search=true&quicksearch=".$nv_qs_text);
    }
	
	$navitable->setURL('?fid=brands&act=json');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=brands&act=edit&id=');
    $navitable->setGridNotesObjectName("brand");

    $navitable->addCol("ID", 'id', "40", "true", "left");
    $navitable->addCol(t(157, 'Image'), 'image', "64", "false", "center");
    $navitable->addCol(t(159, 'Name'), 'name', "320", "true", "left");
    $navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");
	
	$navibars->add_content($navitable->generate());

	return $navibars->generate();
}

function brands_form($object)
{
	global $layout;
	global $events;
	global $user;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
    $layout->navigate_media_browser();
    $layout->navigate_editorfield_link_dialog();
	
	if(empty($object->id))
		$navibars->title(t(681, 'Brands').' / '.t(38, 'Create'));
	else
		$navibars->title(t(681, 'Brands').' / '.t(170, 'Edit').' ['.$object->id.']');

    $navibars->add_actions(
        array(
            '<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+M">
				<img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'
			</a>'
        )
    );

    if(empty($object->id))
    {
        $navibars->add_actions(
            array(
                ($user->permission('brands.create')=='true'?
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : "")
            )
        );
    }
    else
    {
        $navibars->add_actions(
            array(
                ($user->permission('brands.edit')=='true'?
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : ""),
                ($user->permission("brands.delete") == 'true'?
                    '<a href="#" onclick="navigate_delete_dialog();">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'
				</a>' : "")
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=brands&act=delete&id='.$object->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}

    if(!empty($object->id))
    {
        $notes = grid_notes::comments('brand', $object->id);
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
        // we attach an event which will be fired by navibars to put an extra button
        $events->add_actions(
            'brand',
            array(
                'item' => &$object,
                'navibars' => &$navibars
            ),
            $extra_actions
        );
    }

    if(!empty($object->id))
        $layout->navigate_notes_dialog('brand', $object->id);
	
	$navibars->add_actions(
	    array(
	        (!empty($object->id)? '<a href="?fid=brands&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=brands&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $object->id));
    $navibars->add_tab_content($naviforms->csrf_token());
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($object->id)? $object->id : t(52, '(new)')).'</span>'
        )
    );

	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(159, 'Name').'</label>',
			$naviforms->textfield('name', $object->name)
        )
    );
										
	$navibars->add_tab_content_row(
	    array(
	        '<label>'.t(157, 'Image').'</label>',
			$naviforms->dropbox('image', $object->image, 'image')
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(197, 'Link').'</label>',
            $naviforms->pathfield('url', $object->url)
        )
    );

    $layout->add_script("
        $(document).on('keydown.ctrl_s', function (evt) { navigate_tabform_submit(1); return false; } );
        $(document).on('keydown.ctrl_m', function (evt) { navigate_media_browser(); return false; } );
    ");

	return $navibars->generate();
}

?>