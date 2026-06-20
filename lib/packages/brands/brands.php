<?php
require_once(NAVIGATE_PATH.'/lib/packages/brands/brand.class.php');

function run()
{
    global $DB;
    global $website;
    global $layout;

	$out = '';
	$object = new brand();
			
	// Extract safe values for array access
	$act = value_or_default(array($_REQUEST, 'act'), '');
	switch($act)
	{
        case 'json':
			// Extract safe values for array access
			$oper = value_or_default(array($_REQUEST, 'oper'), '');
			switch($oper)
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

					if($_REQUEST['_search']=='true' || !empty(value_or_default(array($_REQUEST, 'quicksearch'), '')))
					{
						if(!empty(value_or_default(array($_REQUEST, 'quicksearch'), '')))
                        {
                            list($qs_where, $qs_params) = $object->quicksearch(value_or_default(array($_REQUEST, 'quicksearch'), ''));
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
					
        case 'priorities':
            // AJAX endpoint: get brands list or save reordered IDs
            if($_SERVER['REQUEST_METHOD'] === 'POST')
            {
                if(naviforms::check_csrf_token('_nv_csrf_token', false))
                {
                    $ids = isset($_REQUEST['brand_ids']) ? $_REQUEST['brand_ids'] : array();
                    if(is_array($ids) && !empty($ids))
                    {
                        brand::reorder($ids);
                        echo json_encode(array('status' => 'ok'));
                    }
                    else
                    {
                        echo json_encode(array('status' => 'error', 'message' => 'No IDs provided'));
                    }
                }
                else
                {
                    echo json_encode(array('status' => 'error', 'message' => 'CSRF token error'));
                }
            }
            else
            {
                // GET: return all brands ordered by position
                $DB->query('
                    SELECT id, name, image, position 
                      FROM nv_brands 
                     WHERE website = '.intval($website->id).'
                     ORDER BY position ASC, id ASC',
                    'object'
                );
                $brands = $DB->result();
                $out = array();
                foreach($brands as $b)
                {
                    $brand_image_html = '';
                    if(!empty($b->image))
                    {
                        $brand_image_html = '<img src="'.file::file_url($b->image, 'inline').'&width=48&height=36&border=true" style="vertical-align:middle;margin-right:6px;" />';
                    }
                    $out[] = array(
                        'id' => $b->id,
                        'name' => $b->name,
                        'image_html' => $brand_image_html
                    );
                }
                echo json_encode($out);
            }
            session_write_close();
            exit;
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
	global $layout;

	$navibars = new navibars();
	$navitable = new navitable("brands_list");
	
	$navibars->title(t(681, 'Brands'));

	$navibars->add_actions(
	    array(
			'<a href="#" onclick="brands_priorities_dialog(); return false;"><img height="16" align="absmiddle" width="16" src="img/icons/silk/table_sort.png"> '.t(857, 'Priorities').'</a>'
        )
    );

	$navibars->add_actions(
	    array(
	        '<a href="?fid=brands&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid=brands&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if(value_or_default(array($_REQUEST, 'quicksearch'), '')=='true')
    {
        $nv_qs_text = core_purify_string(value_or_default(array($_REQUEST, 'navigate-quicksearch'), ''), true);
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

	$layout->add_script('
		function brands_priorities_dialog()
		{
			// show loading state
			var $dialog = $("<div id=\"brands-priorities-dialog\"><p style=\"text-align:center;padding:20px;\"><img src=\"img/loader.gif\" /> '.t(6, "Loading") .'...</p></div>");

			$dialog.dialog({
				width: 600,
				height: 500,
				modal: true,
				title: "<img src=\"img/icons/silk/table_sort.png\" align=\"absmiddle\"> '.t(858, "Brand Priorities") .'",
				buttons: [
					{
						text: "'.t(34, "Save") .'",
						click: function() {
							var ids = [];
							$("#brands-priorities-list li").each(function() {
								ids.push($(this).data("brand-id"));
							});
							$.ajax({
								url: "?fid=brands&act=priorities",
								method: "POST",
								data: {
									brand_ids: ids,
									_nv_csrf_token: navigatecms.csrf_token
								},
								success: function(resp) {
									var data = (typeof resp === "string") ? JSON.parse(resp) : resp;
									if(data.status === "ok") {
										$dialog.dialog("close");
										navigate_notification("'.t(53, "Data saved successfully.") .'", false, false, "fa fa-check");
									} else {
										alert("Error: " + (data.message || "Unknown"));
									}
								},
								error: function() {
									alert("'.t(864, "Connection error") .'");
								}
							});
						}
					},
					{
						text: "'.t(58, "Cancel") .'",
						click: function() { $(this).dialog("close"); }
					}
				],
				close: function() { $(this).remove(); }
			});

			// fetch brands list
			$.getJSON("?fid=brands&act=priorities", function(brands) {
				var html = "";
				html += "<style>";
				html += "#brands-priorities-list { list-style:none; padding:0; margin:0; }";
				html += "#brands-priorities-list li { display:flex; align-items:center; padding:6px 8px; margin:2px 0; background:#fff; border:1px solid #ccc; border-radius:3px; cursor:move; }";
				html += "#brands-priorities-list li.ui-sortable-helper { background:#e8f4fd; border-color:#2196F3; box-shadow:0 2px 8px rgba(0,0,0,0.2); }";
				html += "#brands-priorities-list li .brand-name { flex:1; font-weight:500; }";
				html += "#brands-priorities-list li .brand-actions { white-space:nowrap; }";
				html += "#brands-priorities-list li .brand-actions button { margin-left:2px; padding:2px 6px; font-size:11px; cursor:pointer; }";
				html += "</style>";
				html += "<ul id=\"brands-priorities-list\">";

				for(var i = 0; i < brands.length; i++) {
					var b = brands[i];
					html += "<li data-brand-id=\"" + b.id + "\">";
					html += "<span class=\"brand-name\">" + b.image_html + core_special_chars_js(b.name) + " <small style=\"color:#999;\">(#" + b.id + ")</small></span>";
					html += "<span class=\"brand-actions\">";
					html += "<button type=\"button\" onclick=\"brand_move_top(this)\" title=\"'.t(859, "Move to top") .'\">&#x21C8;</button>";
					html += "<button type=\"button\" onclick=\"brand_move_up(this)\" title=\"'.t(860, "Move up") .'\">&#x25B2;</button>";
					html += "<button type=\"button\" onclick=\"brand_move_down(this)\" title=\"'.t(861, "Move down") .'\">&#x25BC;</button>";
					html += "<button type=\"button\" onclick=\"brand_move_bottom(this)\" title=\"'.t(862, "Move to bottom") .'\">&#x21CA;</button>";
					html += "</span>";
					html += "</li>";
				}
				html += "</ul>";

				if(brands.length === 0) {
					html = "<p style=\"text-align:center;color:#999;\">'.t(863, "No brands.") .'</p>";
				}

				$dialog.html(html);

				// init sortable
				$("#brands-priorities-list").sortable({
					placeholder: "ui-sortable-placeholder",
					axis: "y",
					tolerance: "pointer"
				});
			});
		}

		function core_special_chars_js(str) {
			if(!str) return "";
			return str.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
		}

		function brand_move_up(btn) {
			var $li = $(btn).closest("li");
			var $prev = $li.prev();
			if($prev.length) { $li.insertBefore($prev); }
		}

		function brand_move_down(btn) {
			var $li = $(btn).closest("li");
			var $next = $li.next();
			if($next.length) { $li.insertAfter($next); }
		}

		function brand_move_top(btn) {
			var $li = $(btn).closest("li");
			$li.parent().prepend($li);
		}

		function brand_move_bottom(btn) {
			var $li = $(btn).closest("li");
			$li.parent().append($li);
		}
	');

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
    {
        $navibars->title(t(681, 'Brands').' / '.t(38, 'Create'));
    }
	else
    {
		$navibars->title(t(681, 'Brands').' / '.t(170, 'Edit').' ['.$object->id.']');
    }

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
    {
        $layout->navigate_notes_dialog('brand', $object->id);
    }
	
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