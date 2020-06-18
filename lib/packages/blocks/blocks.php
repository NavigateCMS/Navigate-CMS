<?php
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block_group.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');
require_once(NAVIGATE_PATH.'/lib/packages/extensions/extension.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');

function run()
{
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new block();
			
	switch($_REQUEST['act'])
	{
        case 'json':
		case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
                    if(naviforms::check_csrf_token('header'))
                    {
                        $ids = $_REQUEST['ids'];
                        foreach($ids as $id)
                        {
                            $item->load($id);
                            $item->delete();
                        }
                        echo json_encode(true);
                    }
                    else
                    {
                        echo json_encode(false);
                    }
					break;
					
				default: // list or search	
					// translation of request search & order fields
					switch($_REQUEST['searchField'])
					{
						case 'id':
							$_REQUEST['searchField'] = 'b.id';
							break;

						case 'type':
							$_REQUEST['searchField'] = 'b.type';
							break;

						case 'title':
							$_REQUEST['searchField'] = 'd.text';
							break;

						case 'category':
							$_REQUEST['searchField'] = 'b.category';						
							break;

						case 'dates':
							$_REQUEST['searchField'] = 'b.date_published';
							break;

						case 'enabled':
							$_REQUEST['searchField'] = 'b.enabled';
							break;

						case 'date_modified':
						default:
							$_REQUEST['searchField'] = 'b.date_modified';
					}

					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$where = " 1=1 ";
					
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
                        {
                            $where .= $item->quicksearch($_REQUEST['quicksearch']);
                        }
						else if(isset($_REQUEST['filters']))
                        {
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
                            // special case
                            if( strpos($where, 'title LIKE')!== false)
                            {
                                $where = substr_replace($where, 'd.text', strpos($where, 'title LIKE'), 5);
                            }
                        }
						else	// single search
                        {
                            $where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
                        }
					}

                    // filter orderby vars
                    if( !in_array($_REQUEST['sord'], array('', 'desc', 'DESC', 'asc', 'ASC')) ||
                        !in_array($_REQUEST['sidx'], array('id', 'type', 'title', 'dates', 'date_modified', 'access', 'enabled'))
                    )
                    {
                        return false;
                    }
                    if($_REQUEST['sidx']=='dates')
                    {
                        $_REQUEST['sidx'] = 'b.date_published';
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];

										
					$sql = ' SELECT SQL_CALC_FOUND_ROWS b.*, d.text as title 
							   FROM nv_blocks b
						  LEFT JOIN nv_webdictionary d
						  		 	 ON b.id = d.node_id
								 	AND d.node_type = "block"
									AND d.subtype = "title"
									AND d.lang = "'.$website->languages_list[0].'"
									AND d.website = '.$website->id.'
							  WHERE '.$where.'
							    AND b.website = '.$website->id.' 
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;

					if(!$DB->query($sql, 'array'))
					{
						throw new Exception($DB->get_last_error());	
					}
					
					$dataset = $DB->result();
					$total = $DB->foundRows();

                    $block_types = block::types();
                    $block_types_list = array();

                    for($i=0; $i < count($block_types); $i++)
                    {
	                    if(is_numeric($block_types[$i]['id']))
                        {
                            $block_types_list[$block_types[$i]['code']] = $block_types[$i]['title'];
                        }
	                    else
                        {
                            $block_types_list[$block_types[$i]['id']] = $block_types[$i]['title'];
                        }
                    }

                    $dataset = grid_notes::summary($dataset, 'block', 'id');
									
					// we need to format the values and retrieve the needed strings from the dictionary
					$out = array();								
					for($i=0; $i < count($dataset); $i++)
					{
						if(empty($dataset[$i]))
                        {
                            continue;
                        }
						
						$access = array(
                            0 => '<img src="img/icons/silk/page_white_go.png" align="absmiddle" title="'.t(254, 'Everybody').'" />',
							1 => '<img src="img/icons/silk/lock.png" align="absmiddle" title="'.t(361, 'Web users only').'" />',
							2 => '<img src="img/icons/silk/user_gray.png" align="absmiddle" title="'.t(363, 'Users who have not yet signed up or signed in').'" />',
                            3 => '<img src="img/icons/silk/group_key.png" align="absmiddle" title="'.t(512, "Selected web user groups").'" />'
						);						
						
						if(empty($dataset[$i]['date_published'])) 
                        {
                            $dataset[$i]['date_published'] = '&infin;';
                        }
						else
                        {
                            $dataset[$i]['date_published'] = core_ts2date($dataset[$i]['date_published'], false);
                        }
							
						if(empty($dataset[$i]['date_unpublish'])) 
                        {
                            $dataset[$i]['date_unpublish'] = '&infin;';
                        }
						else
                        {
                            $dataset[$i]['date_unpublish'] = core_ts2date($dataset[$i]['date_unpublish'], false);
                        }
							
						if($dataset[$i]['category'] > 0)
                        {
                            $dataset[$i]['category'] = $DB->query_single(
                                'text',
                                'nv_webdictionary',
                                ' 	
                                    node_type = :node_type AND
                                    node_id = :node_id AND
                                    subtype = :subtype AND
                                    lang = :lang
                                ',
                                '',
                                array(
                                    ':node_type' => "structure",
                                    ':node_id' => $dataset[$i]['category'],
                                    ':subtype' => "title",
                                    ':lang' => $website->languages_list[0]
                                )
                            );
                        }

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1 	=> core_special_chars($block_types_list[$dataset[$i]['type']]),
                            2 	=> '<div class="list-row" data-enabled="'.$dataset[$i]['enabled'].'">'.core_special_chars($dataset[$i]['title']).'</div>',
							3	=> $dataset[$i]['date_published'].' - '.$dataset[$i]['date_unpublish'],
							4	=> $access[$dataset[$i]['access']],
							5	=> (($dataset[$i]['enabled']==1)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />'),
                            6 	=> $dataset[$i]['_grid_notes_html']
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);
					break;
			}
			
			session_write_close();
			exit;
			break;
		
		case 'load':
        case 'edit':
		case 2:
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
			}
		
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
                    naviforms::check_csrf_token();

					$item->save();
                    property::save_properties_from_post('block', $item->id);
					$id = $item->id;

					// set block order
                    if(!empty($item->type) && !empty($_REQUEST['blocks-order']))
                    {
                        block::reorder($item->type, $_REQUEST['blocks-order'], $_REQUEST['blocks-order-fixed']);
                    }

					unset($item);
					$item = new block();
					$item->load($id);
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
				users_log::action('blocks', $item->id, 'save', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
			}
			else
            {
                users_log::action('blocks', $item->id, 'load', $item->dictionary[$website->languages_list[0]]['title']);
            }
		
			$out = blocks_form($item);
			break;	

        case 'delete':
		case 4:
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
                    $out = blocks_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = blocks_form($item);
				}
				users_log::action('blocks', $item->id, 'remove', $item->dictionary[$website->languages_list[0]]['title']);
			}
			break;

        case 'duplicate':
            if(!empty($_REQUEST['id']))
            {
                $item->load(intval($_REQUEST['id']));

                $properties = property::load_properties_associative(
                    'block', $item->type,
                    'block', $item->id
                );

                // try to duplicate
                $item->id = 0;
                $ok = $item->insert();

                if($ok)
                {
                    // also duplicate block properties
                    $ok = property::save_properties_from_array('block', $item->id, $item->type, $properties);
                }

                if($ok)
                {
                    $layout->navigate_notification(t(478, 'Item duplicated successfully.'), false, false, 'fa fa-check');
                    $out = blocks_form($item);
                }
                else
                {
                    $layout->navigate_notification(t(56, 'Unexpected error.'), false);
                    $item = new block();
                    $item->load(intval($_REQUEST['id']));
                    $out = blocks_form($item);
                }

                users_log::action('blocks', $item->id, 'duplicate', $item->dictionary[$website->languages_list[0]]['title'], json_encode($_REQUEST));
            }
            break;

        case 'path':
		case 5:	// search an existing path
			$DB->query(
			    'SELECT path as id, path as label, path as value
						  FROM nv_paths
						 WHERE path LIKE :path 
						   AND website = '.$website->id.'
				      ORDER BY path ASC
					     LIMIT 10',
                'array',
                array(
                    ':path' => '%' . $_REQUEST['term'] . '%'
                ));
						
			echo json_encode($DB->result());
							  
			core_terminate();		
			break;

        case 'block_groups_list':
            $out = block_groups_list();
            break;

        case 'block_groups_json':	// block groups: json data retrieval
			$page = intval($_REQUEST['page']);
			$max	= intval($_REQUEST['rows']);
			$offset = ($page - 1) * $max;

			list($rs, $total) = block_group::paginated_list($offset, $max, $_REQUEST['sidx'], $_REQUEST['sord']);

            $rs = grid_notes::summary($rs, 'block_group', 'id');

            // translate $rs to an array of ordered fields
            foreach($rs as $row)
            {
                if(substr($row['blocks'], 0, 2)=='a:') // nv < 2.1
                {
                    $row['blocks']	= mb_unserialize($row['blocks']);
                }
                else // nv >= 2.1
                {
                    $row['blocks']  = json_decode($row['blocks'], true);
                }

                $dataset[] = array(
                    'id' => $row['id'],
                    'code' => $row['code'],
                    'title' => $row['title'],
                    'blocks' => count($row['blocks']),
                    'notes' => $row['_grid_notes_html']
                );
            }

			navitable::jqgridJson($dataset, $page, $offset, $max, $total, 'id');

			session_write_close();
			exit;
			break;

        case 'block_group_edit':
            $item = new block_group();

            if(!empty($_REQUEST['id']))
            {
                $item->load(intval($_REQUEST['id']));
            }

            if(isset($_REQUEST['form-sent']))
            {
                $item->load_from_post();
                try
                {
                    naviforms::check_csrf_token();
                    $item->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
                }
                catch(Exception $e)
                {
                    $layout->navigate_notification($e->getMessage(), true, true);
                }
                users_log::action('blocks', $item->id, 'save', $item->title, json_encode($_REQUEST));
            }
            else if(!empty($_REQUEST['id']))
            {
                users_log::action('blocks', $item->id, 'edit', $item->title);
            }

			$out = block_group_form($item);
			break;

        case 'block_group_delete':
            $item = new block_group();
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
                    $out = block_groups_list();
                }
                else
                {
                    $layout->navigate_notification(t(56, 'Unexpected error.'), false);
                    $out = block_group_form($item);
                }
                users_log::action('blocks', $item->id, 'remove', $item->title);
            }
			break;

        case 'block_types_list':
			$out = blocks_types_list();
			break;
			
		case 'block_types_json':	// block types: json data retrieval
			$page = intval($_REQUEST['page']);
			$max	= intval($_REQUEST['rows']);
			$offset = ($page - 1) * $max;
		
			$rs = block::types($_REQUEST['sidx'], $_REQUEST['sord']);

            $block_modes = block::modes();

            // translate $rs to an array of ordered fields
            foreach($rs as $row)
            {
                $dataset[] = array(
                    'id' => $row['id'],
                    'type' => core_special_chars($block_modes[$row['type']]),
                    'code' => core_special_chars($row['code']),
                    'title' => core_special_chars($row['title']),
                    'width' => core_special_chars($row['width']),
                    'height' => core_special_chars($row['height'])
                );
            }

			$total = count($dataset);
			navitable::jqgridJson($dataset, $page, $offset, $max, $total, 'id');
		
			session_write_close();
			exit;
			break;			

        case 'block_type_edit':
		case 82: // edit/create block type		

			$item = NULL;
            $position = NULL;
            $max_id = 0;

			$dataset = block::custom_types();
			for($i=0; $i < count($dataset); $i++)
			{
                if($dataset[$i]['id'] > $max_id)
                {
                    $max_id = $dataset[$i]['id'];
                }

				if($dataset[$i]['id'] == $_REQUEST['id'])
				{
					$item = $dataset[$i];
                    $position = $i;
				}
			}

			if(empty($item) && !empty($_REQUEST['id']))
			{
				$layout->navigate_notification(t(599, "Sorry, can't display a theme block type info."));
				$out = blocks_types_list();
			}
			else
			{
	            if(isset($_REQUEST['form-sent']))
				{
					if(empty($item))
                    {
                        $item = array('id' => $max_id + 1);
                    }

					$item['type'] = core_purify_string($_REQUEST['type']);
					$item['title'] = core_purify_string($_REQUEST['title']);
	                $item['code'] = core_purify_string($_REQUEST['code']);
					$item['width'] = core_purify_string($_REQUEST['width']);
					$item['height'] = core_purify_string($_REQUEST['height']);
					$item['order'] = core_purify_string($_REQUEST['order']);
					$item['maximum'] = core_purify_string($_REQUEST['maximum']);
					$item['notes'] = core_purify_string($_REQUEST['notes']);

					if(!is_null($position))
                    {
                        $dataset[$position] = $item;
                    }
	                else
                    {
                        $dataset[] = $item;
                    }

					try
					{
						// save
						$ok = block::types_update($dataset);
                        $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
					}
					catch(Exception $e)
					{
						$layout->navigate_notification($e->getMessage(), true, true);
					}
				}

				$out = blocks_type_form($item);
			}

			break;

        case 'block_type_delete':
		case 84: // remove block type

            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
            }
            else
            {
                $dataset = block::custom_types();
                $item = NULL;

                for($i=0; $i < count($dataset); $i++)
                {
                    if($dataset[$i]['id'] == $_REQUEST['id'])
                    {
                        unset($dataset[$i]);
                        break;
                    }
                }

                try
                {
                    block::types_update($dataset);
                    $layout->navigate_notification(t(55, 'Item removed successfully.'), false);
                    $out = blocks_types_list();
                }
                catch(Exception $e)
                {
                    $out = $layout->navigate_message("error", t(23, 'Blocks'), t(56, 'Unexpected error.'));
                }
            }
			break;

        case 'block_property_load':
            $property = new property();

            if(!empty($_REQUEST['id']))
            {
                if(is_numeric($_REQUEST['id']))
                {
                    $property->load(intval($_REQUEST['id']));
                }
                else
                {
                    $property->load_from_theme($_REQUEST['id'], null, 'block', $_REQUEST['block']);
                }
            }

            header('Content-type: text/json');

            $types = property::types();
            $property->type_text = $types[$property->type];

            echo json_encode($property);

            core_terminate();
            break;

        case 'block_property_save': // save property details

            $property = new property();

            if(!empty($_REQUEST['property-id']))
            {
                $property->load(intval($_REQUEST['property-id']));
            }

            naviforms::check_csrf_token();
            $property->load_from_post();
            $property->save();

            header('Content-type: text/json');

            $types = property::types();
            $property->type_text = $types[$property->type];

            echo json_encode($property);

            core_terminate();
            break;

        case 'block_property_remove': // remove property
            if(!naviforms::check_csrf_token('header'))
            {
                echo 'false';
            }
            else
            {
                $property = new property();

                if(!empty($_REQUEST['property-id']))
                {
                    $property->load(intval($_REQUEST['property-id']));
                }

                $property->delete();
            }
            core_terminate();
            break;

        case 'block_group_block_options':
            $status = null;
            $block_group = $_REQUEST['block_group'];
            $block_code = $_REQUEST['code'];
            $block_uid = $_REQUEST['block_uid'];

            if(isset($_REQUEST['form-sent']))
            {
                naviforms::check_csrf_token();
                $status = property::save_properties_from_post('block_group_block', $block_code, $block_group, $block_code, $block_uid);
            }

            $out = block_group_block_options($block_group, $block_code, $block_uid, $status);

            echo $out;

            core_terminate();
            break;

        case 'block_group_extension_block_options':
            $status = null;
            $block_group = core_purify_string($_REQUEST['block_group']);    // block_group type
            $block_id = core_purify_string($_REQUEST['block_id']);          // extension block id (type)
            $block_uid = core_purify_string($_REQUEST['block_uid']);        // extension block unique id
            $block_extension = core_purify_string($_REQUEST['block_extension']);    // extension name

            if(isset($_REQUEST['form-sent']))
            {
                naviforms::check_csrf_token();
                $status = property::save_properties_from_post('extension_block', $block_group, $block_id, null, $block_uid);
            }

            $out = block_group_extension_block_options($block_group, $block_extension, $block_id, $block_uid, $status);

            echo $out;

            core_terminate();
            break;

        case 'list':
		case 0:
		default:			
			$out = blocks_list();
			break;
	}
	
	return $out;
}

function blocks_list()
{
    global $layout;
    global $events;
    global $user;

	$navibars = new navibars();
	$navitable = new navitable("blocks_list");
	
	$navibars->title(t(23, 'Blocks'));

    // retrieve block groups, if more than 10, do not show quickmenu

    $group_blocks_links = array();
    list($bg_rs, $bg_total) = block_group::paginated_list(0, 10, 'title', 'desc');

    if($bg_total > 0 && $bg_total <= 10)
    {
        foreach($bg_rs as $bg)
        {
            $group_blocks_links[] = '<a class="ui-menu-action-bigger" href="?fid=blocks&act=block_group_edit&id='.$bg['id'].'"><i class="fa fa-fw fa-caret-right"></i> '.$bg['title'].'</a>';
        }

        $events->add_actions(
            'blocks',
            array(
                'item' => null,
                'navibars' => &$navibars
            ),
            $group_blocks_links,
            '<a class="content-actions-submenu-trigger" href="?fid=blocks&act=block_groups_list">
                <img height="16" align="absmiddle" width="16" src="img/icons/silk/bricks.png"> '.t(506, 'Groups').' &#9662;
            </a>'
        );
    }

    $navibars->add_actions(
        array(
            (!empty($group_blocks_links)? '' : '<a href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/bricks.png"> '.t(506, 'Groups').'</a>'),
            '<a href="?fid=blocks&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_edit.png"> '.t(167, 'Types').'</a>'
        )
    );

	$navibars->add_actions(
        array(
            '<a href="?fid=blocks&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid=blocks&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if(@$_REQUEST['quicksearch']=='true')
    {
        $navitable->setInitialURL("?fid=blocks&act=json&_search=true&quicksearch=".$_REQUEST['navigate-quicksearch']);
    }
	
	$navitable->setURL('?fid=blocks&act=json');
	$navitable->sortBy('date_modified', 'desc');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=blocks&act=edit&id=');
	$navitable->enableSearch();
	if($user->permission("blocks.delete") == 'true')
    {
        $navitable->enableDelete();
    }
	$navitable->setGridNotesObjectName("block");
	
	$navitable->addCol("ID", 'id', "40", "true", "left");	
	$navitable->addCol(t(160, 'Type'), 'type', "120", "true", "center");	
	$navitable->addCol(t(67, 'Title'), 'title', "400", "true", "left");	
	$navitable->addCol(t(85, 'Date published'), 'dates', "100", "true", "center");	
	$navitable->addCol(t(364, 'Access'), 'access', "40", "true", "center");	
	$navitable->addCol(t(65, 'Enabled'), 'enabled', "40", "true", "center");
    $navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");

    $navitable->setLoadCallback('
        if($("#jqgh_blocks_list_type button").length < 1)
        {
            $("#jqgh_blocks_list_type").prepend("<button><i class=\"fa fa-filter\"></i></button>");
            $("#jqgh_blocks_list_type button")
            	.button()
            	.css(
            	{
                	"float": "right"
            	})
            	.on("click", function(e)
            	{
            	    e.stopPropagation();
            	    e.preventDefault();
            	    setTimeout(blocks_list_choose_types, 150);
                });

            $("#jqgh_blocks_list_type span.ui-button-text").css({"padding-top": "0", "padding-bottom": "0"});
        }
    ');

    // add types filter
    $block_types = block::types('title');
    $hierarchy = array_map(
        function($bt) { return '<li data-value="'.$bt['id'].'">'.$bt['title'].'</li>';  },
        $block_types
    );
    //array_unshift($hierarchy, '<li data-value="">('.t(443, "All").')</li>');

    $navibars->add_content('<div id="filter_types_window" style="display: none;"><ul>'.implode("\n", $hierarchy).'</ul></div>');
    $layout->add_script('$("#filter_types_window ul").attr("data-name", "filter_types_field");');
    $layout->add_script('
        $("#filter_types_window ul").jAutochecklist({
            popup: false,
            absolutePosition: true,
            width: 0,
            listWidth: 300,
            listMaxHeight: 400,
            onItemClick: function(nval, li, selected_before, selected_after)
            {            
                selected_after = selected_after.join(",");
                var filters = {
                    "groupOp" : "AND",
                    "rules": [
                        {
                            "field" : "type",
                            "op" : "in",
                            "data" : selected_after
                        },
                        {
                            "field" : "title",
                            "op" : "cn",
                            "data" : $("#navigate-quicksearch").val()
                        }
                    ]
                };

                $("#blocks_list").jqGrid(
                    "setGridParam",
                    {
                        search: true,
                        postData: { "filters": filters }
                    }
                ).trigger("reloadGrid");
            }
        });');

    $layout->add_script('
        function blocks_list_choose_types()
        {
            $("#navigate-quicksearch").parent().on("submit", function()
            {
                $("#filter_types_window ul").jAutochecklist("deselectAll");
            });

            $("#filter_types_window ul").jAutochecklist("open");
            $(".jAutochecklist_list").css({"position": "absolute"});
            $(".jAutochecklist_list").css($("#jqgh_blocks_list_type button").offset());
            $(".jAutochecklist_dropdown_wrapper").hide();
            $(".jAutochecklist_list").css({
                "border-radius": "8px",
                "margin-left": "-150px",
                "margin-top": "16px"
            });
            $(".jAutochecklist_list").addClass("navi-ui-widget-shadow ui-menu ui-widget ui-widget-content ui-corner-all");

            return false;
        }
    ');
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
}

function blocks_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
    global $events;
    global $theme;

    $current_version = $_SESSION['current_version'];
    $extra_actions = array();
	
	$navibars = new navibars();
	$naviforms = new naviforms();	
	$layout->navigate_media_browser();	// we can use media browser in this function
    $layout->navigate_editorfield_link_dialog();
		
	if(empty($item->id))
    {
        $navibars->title(t(23, 'Blocks').' / '.t(38, 'Create'));
    }
	else
    {
        $navibars->title(t(23, 'Blocks').' / '.t(170, 'Edit').' ['.$item->id.']');
    }

    $navibars->add_actions(
		array(
			'<a href="#" onclick="javascript: navigate_media_browser();" title="Ctrl+M">
				<img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'
			</a>'
		)
	);

    $layout->add_script("
        $(document).on('keydown.ctrl_s', function (evt) { navigate_tabform_submit(1); return false; } );
        $(document).on('keydown.ctrl_m', function (evt) { navigate_media_browser(); return false; } );
    ");
	

    if(!empty($item->id))
    {
        $notes = grid_notes::comments('block', $item->id);
        $navibars->add_actions(
            array(
                '<a href="#" onclick="javascript: navigate_display_notes_dialog();"><span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span><img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'</a>'
            )
        );
    }

	if(empty($item->id))
	{
		$navibars->add_actions(
            array(
	            ($user->permission('blocks.create')=='true'?
	            '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : "")
            )
        );
	}
	else
	{
		$navibars->add_actions(
            array(
	            (($user->permission('blocks.edit') == 'true') ?
	            '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : ""),
	            ($user->permission("blocks.delete") == 'true' ?
                '<a href="#" onclick="navigate_delete_dialog();">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'
				</a>' : "")
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=blocks&act=delete&id='.$item->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');

        if($user->permission("blocks.create") == 'true')
        {
            $extra_actions[] = '<a href="?fid=blocks&act=duplicate&id='.$item->id.'" onclick="$(this).attr(\'#\');"><img height="16" align="absmiddle" width="16" src="img/icons/silk/page_copy.png"> '.t(477, 'Duplicate').'</a>';
        }
    }

    array_unshift($extra_actions, '<a href="?fid=blocks&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_edit.png"> '.t(167, 'Types').'</a>');

    $events->add_actions(
        'blocks',
        array(
            'item' => null,
            'navibars' => &$navibars
        ),
        $extra_actions
    );

    $group_blocks_links = array();
    list($bg_rs, $bg_total) = block_group::paginated_list(0, 10, 'title', 'desc');

    if($bg_total > 0 && $bg_total <= 10)
    {
        foreach($bg_rs as $bg)
        {
            $group_blocks_links[] = '<a href="?fid=blocks&act=block_group_edit&id='.$bg['id'].'"><i class="fa fa-fw fa-caret-right"></i> '.$bg['title'].'</a>';
        }

        $events->add_actions(
            'blocks',
            array(
                'item' => null,
                'navibars' => &$navibars
            ),
            $group_blocks_links,
            '<a class="content-actions-submenu-trigger" href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/bricks.png"> '.t(506, 'Groups').' &#9662;</a>'
        );
    }
    else
    {
        $navibars->add_actions(
            array(
                '<a href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/bricks.png"> '.t(506, 'Groups').'</a>'
            )
        );
    }
	
	$navibars->add_actions(
		array(
			(!empty($item->id)? '<a href="?fid=blocks&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=blocks&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
		)
	);

    if(!empty($item->id))
    {
        $layout->navigate_notes_dialog('block', $item->id);
    }

	$navibars->form(NULL, '?fid=blocks&act=edit&id='.$item->id);

    $navibars->add_content('
        <script type="text/javascript" src="lib/packages/blocks/blocks.js?r='.$current_version->revision.'"></script>
    ');

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

	$block_types = block::types();
	$block_types_keys = array();
	$block_types_info = array();	
	
	for($i=0; $i < count($block_types); $i++)
	{
        if($item->type == $block_types[$i]['code'])
        {
            $block_type_width = $block_types[$i]['width'];
        }

		$block_size_helper = '';

		if(!empty($block_types[$i]['width']) || !empty($block_types[$i]['height']))
		{
	        if(empty($block_types[$i]['width']))
            {
                $block_types[$i]['width'] = '***';
            }
			if(empty($block_types[$i]['height']))
            {
                $block_types[$i]['height'] = '***';
            }

			$block_size_helper = ' ('.$block_types[$i]['width'].' x '.$block_types[$i]['height'].' px)';
		}

		if(is_numeric($block_types[$i]['id']))
        {
            $block_types_keys[] = $block_types[$i]['code'];
        }     // block type created via navigate interface
		else
        {
            $block_types_keys[] = $block_types[$i]['id'];
        }       // block described in theme definition

		$block_types_info[] = $block_types[$i]['title'].$block_size_helper;
	}

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(160, 'Type').'</label>',
			$naviforms->selectfield('type', $block_types_keys, $block_types_info, $item->type)
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(85, 'Date published').'</label>',
			$naviforms->datefield('date_published', $item->date_published, true),
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(90, 'Date unpublished').'</label>',
			$naviforms->datefield('date_unpublish', $item->date_unpublish, true),
        )
    );

	// Notes field is deprecated, but we keep on showing the existing Notes
	if(!empty($item->notes))
	{
		$navibars->add_tab_content_row(
	        array(
	            '<label>'.t(168, 'Notes').'</label>',
				$naviforms->textarea('notes', $item->notes)
	        )
	    );
	}

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(364, 'Access').'</label>',
            $naviforms->selectfield(
                'access',
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

	if(empty($item->id))
    {
        $item->enabled = true;
    }
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(65, 'Enabled').'</label>',
		    $naviforms->checkbox('enabled', $item->enabled),
	    )
    );

	if($item->date_modified > 0)
	{
		$navibars->add_tab_content_row(
			array(
				'<label>'.t(227, 'Date modified').'</label>',
				core_ts2date($item->date_modified, true)
			)
		);
	}

	$navibars->add_tab(t(9, "Content"));

    switch($item->class)
    {
        case 'poll':
            $options = array();
            foreach($website->languages_list as $lang)
            {
                $options[$lang] = language::name_by_code($lang);
            }

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(63, 'Languages').'</label>',
                    $naviforms->buttonset('language_selector', $options, $website->languages_list[0], "navigate_items_select_language(this);")
                )
            );

            foreach($website->languages_list as $lang)
            {
                $navibars->add_tab_content('
                    <div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">
                ');

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(558, 'Question').'</label>',
                        $naviforms->textfield('title-'.$lang, @$item->dictionary[$lang]['title']),
                        ''
                    )
                );

                // Poll options
                $table = new naviorderedtable("poll_answers_table_".$lang);
                $table->setWidth("330px");
                $table->setHiddenInput("poll-answers-table-order-".$lang);
                $navibars->add_tab_content(
                    $naviforms->hidden("poll-answers-table-order-".$lang, "")
                );

                $table->addHeaderColumn(t(67, 'Title'), 200);
                //$table->addHeaderColumn(t(237, 'Code'), 120);
                $table->addHeaderColumn(t(352, 'Votes'), 80);
                $table->addHeaderColumn(t(35, 'Remove'), 50);

                if(!empty($item->trigger[$lang]))
                {
                    $poll_answers = $item->trigger[$lang];

                    foreach($poll_answers as $pa)
                    {
                        $uid = uniqid();
                        $table->addRow(
                            "poll-answers-table-row-".$uid,
                            array(
                                array('content' => '<input type="text" name="poll-answers-table-title-'.$lang.'['.$uid.']" value="'.$pa['title'].'" style="width: 200px;" />', 'align' => 'left'),
                                //array('content' => '<input type="text" name="poll-answers-table-code-'.$lang.'['.$uid.']" value="'.$pa['code'].'" style="width: 120px;" />', 'align' => 'left'),
                                array('content' => '<input type="text" name="poll-answers-table-votes-'.$lang.'['.$uid.']" value="'.intval($pa['votes']).'" style="width: 80px;" />', 'align' => 'left'),
                                array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" style="cursor: pointer;" onclick="navigate_blocks_poll_answers_table_row_remove(this);" />', 'align' => 'center')
                            )
                        );
                    }
                }

                $uid = uniqid();
                $table->addRow(
                    "poll-answers-table-row-model-".$lang,
                    array(
                        array('content' => '<input type="text" name="poll-answers-table-title-'.$lang.'['.$uid.']" value="" style="width: 200px;" />', 'align' => 'left'),
                        //array('content' => '<input type="text" name="poll-answers-table-code-'.$lang.'['.$uid.']" value="'.$uid.'" style="width: 120px;" />', 'align' => 'left'),
                        array('content' => '<input type="text" name="poll-answers-table-votes-'.$lang.'['.$uid.']" value="0" style="width: 80px;" />', 'align' => 'left'),
                        array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" style="cursor: pointer;" onclick="navigate_blocks_poll_answers_table_row_remove(this);" />', 'align' => 'center')
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(559, "Answers").'</label>',
                        '<div id="poll-answers-'.$lang.'">'.$table->generate().'</div>',
                        '<label>&nbsp;</label>',
                        '<button id="poll-answers-table-add-'.$lang.'" data-lang="'.$lang.'"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>'
                    )
                );

                $navibars->add_tab_content('
                    </div>
                ');
            }

            foreach($website->languages_list as $alang)
            {
                $layout->add_script('
                    $(window).on("load", function()
                    {
                        poll_answers_table_row_models["'.$alang.'"] = $("#poll-answers-table-row-model-'.$alang.'").html();
                        if($("#poll_answers_table_'.$alang.'").find("tr").not(".nodrag").length > 1)
                            $("#poll-answers-table-row-model-'.$alang.'").hide();
                        navigate_naviorderedtable_poll_answers_table_'.$alang.'_reorder();
                    });
                ');
            }

            $layout->add_script('
                var active_languages = ["'.implode('", "', array_keys($options)).'"];
                navigate_items_select_language("'.$website->languages_list[0].'");
            ');
            break;

        case 'block':
        case 'theme':
        default:
            $options = array();
            foreach($website->languages_list as $lang)
            {
                $options[$lang] = language::name_by_code($lang);
            }

            $navibars->add_tab_content_row(
                array(
                    '<label>'.t(63, 'Languages').'</label>',
                    $naviforms->buttonset('language_selector', $options, $website->languages_list[0], "navigate_items_select_language(this);")
                )
            );

            foreach($website->languages_list as $lang)
            {
                $navibars->add_tab_content('<div class="language_fields" id="language_fields_'.$lang.'" style=" display: none; ">');

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(67, 'Title').'</label>',
                        $naviforms->textfield('title-'.$lang, @$item->dictionary[$lang]['title']),
                        ''
                    )
                );

	            $block_trigger_types = array(
		            '' => t(181, 'Hidden'),
		            'title' => t(67, 'Title'),
		            'content' => t(9, 'Content'),
                    'image' => t(157, 'Image'),
                    'rollover' => t(182, 'Rollover'),
                    'video' => t(272, 'Video'),
                    'html' => 'HTML',
		            'links' => t(549, 'Links'),
		            'flash' => 'Flash'
	            );

	            // check block trigger restrictions in theme definition
	            if(is_array($theme->blocks))
	            {
		            foreach($theme->blocks as $tb)
		            {
                        // navigate 1.x compatibility
                        if(!isset($tb->id) && isset($tb->code))
                        {
                            $tb->id = $tb->code;
                        }

                        if($tb->id == $item->type && isset($tb->trigger))
			            {
				            if(!is_array($tb->trigger))
                            {
                                $tb->trigger = array($tb->trigger);
                            }

				            foreach($block_trigger_types as $btt_key => $btt_val)
				            {
					            if(empty($btt_key) || in_array($btt_key, $tb->trigger))
                                {
                                    continue;
                                }

					            unset($block_trigger_types[$btt_key]);
				            }

				            $block_trigger_types = array_filter($block_trigger_types);
			            }
		            }
	            }

                $navibars->add_tab_content_row(array(
                        '<label>'.t(160, 'Type').'</label>',
                        $naviforms->selectfield('trigger-type-'.$lang,
                            array_keys($block_trigger_types),
                            array_values($block_trigger_types),
                            $item->trigger['trigger-type'][$lang],
                            "navigate_blocks_trigger_change('".$lang."', this);"
                        )
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(157, 'Image').'</label>',
                        $naviforms->dropbox('trigger-image-'.$lang, @$item->trigger['trigger-image'][$lang], 'image')
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(182, 'Rollover').' (off / on)</label>',
                        $naviforms->dropbox('trigger-rollover-'.$lang, @$item->trigger['trigger-rollover'][$lang], 'image'),
                        $naviforms->dropbox('trigger-rollover-active-'.$lang, @$item->trigger['trigger-rollover-active'][$lang], 'image'),
                        ''
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(272, 'Video').'</label>',
                        $naviforms->dropbox('trigger-video-'.$lang, @$item->trigger['trigger-video'][$lang], 'video')
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>Flash (SWF)</label>',
                        $naviforms->dropbox('trigger-flash-'.$lang, @$item->trigger['trigger-flash'][$lang], 'flash'),
                        ''
                    )
                );

                /* links list */

                // check if navigate must show an icon selector
                $links_icons = '';
                if(!empty($theme) && !empty($theme->blocks))
                {
                    foreach($theme->blocks as $tb)
                    {
                        if($item->type == $tb->id)
                        {
                            $links_icons = @$tb->icons;
                            break;
                        }
                    }
                }

                $table = new naviorderedtable("trigger_links_table_".$lang);
                $table->setWidth("776px");
                $table->setHiddenInput("trigger-links-table-order-".$lang);
                $navibars->add_tab_content( $naviforms->hidden("trigger-links-table-order-".$lang, "") );

                $table->addHeaderColumn(t(242, 'Icon'), 50);
                $table->addHeaderColumn(t(67, 'Title'), 180);
                $table->addHeaderColumn(t(197, 'Link'), 390);
                $table->addHeaderColumn('<i class="fa fa-lg fa-fw fa-external-link" title="'.t(324, 'New window').'"></i>', 16);
                $table->addHeaderColumn('<i class="fa fa-lg fa-fw fa-globe" title="'.t(364, 'Access').'"></i>', 20);
                $table->addHeaderColumn(t(35, 'Remove'), 50);


				if(empty($item->trigger['trigger-links'][$lang]['link']))
				{
					// create a default entry
					$item->trigger['trigger-links'][$lang] = array(
						'order' => '',
						'icon' => '',
						'title' => array('0' => ''),
						'access' => array('0' => 0),
						'link' => array('0' => '')
					);
				}

                if(!empty($item->trigger['trigger-links'][$lang]))
                {
                    $tlinks = $item->trigger['trigger-links'][$lang];

                    foreach($tlinks['link'] as $key => $link)
                    {
                        $uid = uniqid();
                        $table->addRow(
                            uniqid('trigger-links-table-row-'),
                            array(
	                            ( empty($links_icons)?
	                                array('content' => '-', 'align' => 'center') :
	                                array('content' => '<select name="trigger-links-table-icon-'.$lang.'['.$uid.']" data-select2-value="'.$tlinks['icon'][$key].'"  data-role="icon" style="width: 190px;"></select>', 'align' => 'left')
                                ),
                                array('content' => '<input type="text" name="trigger-links-table-title-'.$lang.'['.$uid.']" value="'.$tlinks['title'][$key].'" data-role="title" style="width: 250px;" />', 'align' => 'left'),
                                array('content' => '<input type="text" name="trigger-links-table-link-'.$lang.'['.$uid.']" value="'.$tlinks['link'][$key].'" data-role="link" style="width: 260px;" />'.
                                                   '<a class="uibutton naviforms-pathfield-trigger"><i class="fa fa-sitemap"></i></a>',
                                      'align' => 'left',
                                      'style' => 'white-space: nowrap;'
                                ),
                                array('content' => '<input type="checkbox" name="trigger-links-table-new_window-'.$lang.'['.$uid.']" data-role="target" id="trigger-links-table-new_window-'.$lang.'['.$uid.']" value="1" '.($tlinks['new_window'][$key]=='1'? 'checked="checked"' : '').' />
                                                    <label for="trigger-links-table-new_window-'.$lang.'['.$uid.']" />',
                                      'align' => 'left'),
                                array('content' => '<input type="hidden" name="trigger-links-table-access-'.$lang.'['.$uid.']" data-role="access" id="trigger-links-table-access-'.$lang.'['.$uid.']" value="'.value_or_default($tlinks['access'][$key], 0).'" />
                                                    <i class="fa fa-fw fa-lg fa-eye '.($tlinks['access'][$key]=='1'? 'hidden' : '').'" onclick="navigate_blocks_trigger_links_table_row_access(this);" data-value="0" for="trigger-links-table-access-'.$lang.'['.$uid.']"></i>
                                                    <i class="fa fa-fw fa-lg fa-eye-slash '.($tlinks['access'][$key]=='1'? '' : 'hidden').'" onclick="navigate_blocks_trigger_links_table_row_access(this);" data-value="1" for="trigger-links-table-access-'.$lang.'['.$uid.']"></i>',
                                      'align' => 'center'),
                                array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" style="cursor: pointer;" onclick="navigate_blocks_trigger_links_table_row_remove(this);" />', 'align' => 'center')
                            )
                        );
                    }
                }

                $uid = uniqid();
                $table->addRow(
                    "trigger-links-table-row-model-".$lang,
                    array(
	                    ( empty($links_icons)?
                            array('content' => '-', 'align' => 'center') :
                            array('content' => '<select name="trigger-links-table-icon-'.$lang.'['.$uid.']" data-select2-value="" data-role="icon" style="width: 190px;"></select>', 'align' => 'left')
                        ),
                        array('content' => '<input type="text" name="trigger-links-table-title-'.$lang.'['.$uid.']" value="" data-role="title" style="width: 250px;" />', 'align' => 'left'),
                        array('content' => '<input type="text" name="trigger-links-table-link-'.$lang.'['.$uid.']" value="" data-role="link" style="width: 260px;" />'.
											 '<a class="uibutton naviforms-pathfield-trigger"><i class="fa fa-sitemap"></i></a>',
                              'align' => 'left'
						),
                        array('content' => '<input type="checkbox" name="trigger-links-table-new_window-'.$lang.'['.$uid.']"  data-role="target" id="trigger-links-table-new_window-'.$lang.'['.$uid.']" value="1" />
                                            <label for="trigger-links-table-new_window-'.$lang.'['.$uid.']" />',
                              'align' => 'left'),
                        array('content' => '<input type="hidden" name="trigger-links-table-access-'.$lang.'['.$uid.']" data-role="access" id="trigger-links-table-access-'.$lang.'['.$uid.']" value="0" />
                                                    <i class="fa fa-fw fa-lg fa-eye" onclick="navigate_blocks_trigger_links_table_row_visibility(this);" data-value="0"  for="trigger-links-table-access-'.$lang.'['.$uid.']"></i>
                                                    <i class="fa fa-fw fa-lg fa-eye-slash hidden" onclick="navigate_blocks_trigger_links_table_row_visibility(this);" data-value="1"  for="trigger-links-table-access-'.$lang.'['.$uid.']"></i>',
                              'align' => 'center'),
                        array('content' => '<img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" style="cursor: pointer;" onclick="navigate_blocks_trigger_links_table_row_remove(this);" />', 'align' => 'center')
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(549, "Links").'</label>',
                        '<div id="trigger-links-'.$lang.'">'.$table->generate().'</div>',
                        '<label>&nbsp;</label>',
                        '<button id="trigger-links-table-add-'.$lang.'" data-lang="'.$lang.'"><img src="img/icons/silk/add.png" align="absmiddle" style="cursor:pointer;" /> '.t(472, 'Add').'</button>'
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>HTML</label>',
                        $naviforms->scriptarea('trigger-html-'.$lang, @$item->trigger['trigger-html'][$lang]),
                        ''
                    )
                );

				$editor_width = "";
                if(!empty($block_type_width))
				{
					if($block_type_width > 500)
                    {
                        $editor_width = $block_type_width.'px';
                    }
					else
                    {
                        $editor_width = '500px';
                    }
				}

                $translate_menu = '';
                if(!empty($translate_extensions))
                {
                    $translate_extensions_titles = array();
                    $translate_extensions_actions = array();

                    foreach($translate_extensions as $te)
                    {
                        if($te['enabled']=='0') continue;
                        $translate_extensions_titles[] = $te['title'];
                        $translate_extensions_actions[] = 'javascript: navigate_tinymce_translate_'.$te['code'].'(\'trigger-content-'.$lang.'-'.$lang.'\', \''.$lang.'\');';
                    }

                    if(!empty($translate_extensions_actions))
                    {
                        $translate_menu = $naviforms->splitbutton(
                            'translate_'.$lang,
                            '<img src="img/icons/silk/comment.png" align="absmiddle"> '.t(188, 'Translate'),
                            $translate_extensions_actions,
                            $translate_extensions_titles
                        );
                    }
                }

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(9, "Content").'
							<span class="editor_selector" for="trigger-content-'.$lang.'">'.
								//'<i class="fa fa-border fa-fw fa-lg fa-th-large" data-action="composer" title="'.t(616, "Edit with NV Composer").'"></i> '.
								'<i class="fa fa-border fa-fw fa-lg fa-file-text-o active" data-action="tinymce" title="'.t(614, "Edit with TinyMCE").'"></i> '.
                                '<i class="fa fa-border fa-fw fa-lg fa-code" data-action="html" title="'.t(615, "Edit as source code").'"></i> '.
                                '<i class="fa fa-border fa-fw fa-lg fa-eraser" data-action="clear" title="'.t(208, "Remove all content").'"></i>'.
							'</span>'.
						'</label>',
                        $naviforms->editorfield('trigger-content-'.$lang, @$item->trigger['trigger-content'][$lang], $editor_width, $lang),
                        '<div style="clear:both; margin-top:5px; float:left; margin-bottom: 10px;">',
                        '<label>&nbsp;</label>',
                        $translate_menu,
                        (!empty($theme->content_samples)? '<button onclick="navigate_properties_copy_from_theme_samples(\'trigger-content-'.$lang.'\', \'trigger\', \''.$lang.'\', \''."tinymce".'\'); return false;"><img src="img/icons/silk/rainbow.png" align="absmiddle"> '.t(553, 'Fragments').' | '.$theme->title.'</button> ' : ''),
                        '</div>',
                        '<br />'
                    ),
					'',
					'lang="'.$lang.'"'
                );


                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(172, 'Action').'</label>',
                        $naviforms->selectfield('action-type-'.$lang,
                            array(
                                0 => '',
                                1 => 'web',
                                2 => 'web-n',
                                3 => 'javascript',
                                4 => 'file',
                                5 => 'image'
                            ),
                            array(
                                0 => t(183, 'Do nothing'),
                                1 => t(173, 'Open URL'),
                                2 => t(174, 'Open URL (new window)'),
                                3 => 'Javascript',
                                4 => t(175, 'Download file'),
                                5 => t(176, 'View image')
                            ),
                            $item->action['action-type'][$lang],
                            "navigate_blocks_action_change('".$lang."', this);"
                        )
                    )
                );

                /* show/hide appropiate row type by action */
                $selected_link_title = '';
                if(!empty($item->action['action-web'][$lang]))
                {
                    $path = explode('/', $item->action['action-web'][$lang]);
                    if(count($path) > 0 && $path[0]=='nv:')
                    {
                        if($path[2]=='structure')
                        {
                            $tmp = new structure();
                            $tmp->load($path[3]);
                            $selected_link_title = $tmp->dictionary[$lang]['title'];
                            $layout->add_script('
                                $(".nv_block_nv_link_info[data-lang='.$lang.']").find("img[data-type=structure]").removeClass("hidden");
                            ');
                        }
                        else if($path[2]=='element')
                        {
                            $tmp = new item();
                            $tmp->load($path[3]);
                            $selected_link_title = $tmp->dictionary[$lang]['title'];
                            $layout->add_script('
                                $(".nv_block_nv_link_info[data-lang='.$lang.']").find("img[data-type=element]").removeClass("hidden");
                            ');
                        }
                    }
                }

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(184, 'Webpage').'</label>',
                        $naviforms->pathfield('action-web-'.$lang, @$item->action['action-web'][$lang])
                    )
                );

                $layout->add_script('
                    $("input[name=action-web-'.$lang.']").on("keydown", function()
                    {
                        var div_info = $(this).parent().find(".nv_block_nv_link_info");
                        $(div_info).find("span").text("");
                        $(div_info).find("img").addClass("hidden");
                    });
                ');

	            $navibars->add_tab_content_row(
                    array(
                        '<label>Javascript</label>',
                        $naviforms->textfield('action-javascript-'.$lang, @$item->action['action-javascript'][$lang], NULL, "navigate_blocks_action_javascript_clean_quotes('action-javascript-".$lang."');"),
	                    '<div class="subcomment"><img src="img/icons/silk/information.png" align="absmiddle" /> '.t(606, 'Double quotes not allowed, use single quotes only').'</div>'
                    )
                );

	            $layout->add_script('
					function navigate_blocks_action_javascript_clean_quotes(id)
		            {
		                var content = $("#" + id).val();
		                content = content.replace(\'"\', "\'");
		                $("#" + id).val(content);
		            }
	            ');

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(82, 'File').'</label>',
                        $naviforms->dropbox('action-file-'.$lang, @$item->action['action-file'][$lang]),
                        ''
                    )
                );

                $navibars->add_tab_content_row(
                    array(
                        '<label>'.t(157, 'Image').'</label>',
                        $naviforms->dropbox('action-image-'.$lang, @$item->action['action-image'][$lang], 'image'),
                        ''
                    )
                );

	            // copy from other language
	            if(count($website->languages) > 1)
	            {
		            $block_copyfrom_titles = array();
		            $block_copyfrom_actions = array();

		            foreach($website->languages as $bcpl)
		            {
			            if($bcpl['language'] == $lang)
                        {
                            continue;
                        }

			            $block_copyfrom_titles[] = language::name_by_code($bcpl['language']);
			            $block_copyfrom_actions[] = 'javascript: navigate_blocks_copy_from_language(\''.$bcpl['language'].'\', \''.$lang.'\');';
		            }

		            $copy_from_menu = $naviforms->splitbutton(
			            'block_copyfrom_'.$lang,
			            '<img src="img/icons/silk/comment.png" align="absmiddle"> '.t(189, 'Copy from').'...',
			            $block_copyfrom_actions,
			            $block_copyfrom_titles
		            );

		            $navibars->add_tab_content_row('<label>&nbsp;</label>'.$copy_from_menu);
	            }

	            $navibars->add_tab_content('</div>');
            }

	        $layout->add_script('
				function navigate_blocks_copy_from_language(from, to)
				{
					// copy title (if destination is empty)
					if($("#title-" + to).val()=="")
						$("#title-" + to).val($("#title-" + from).val());

					// copy trigger type
					$("#trigger-type-" + to)
						.val($("#trigger-type-" + from).val())
						.trigger("change");

					// copy trigger value, depending on the trigger type
					switch($("#trigger-type-" + to).val())
					{
						case "image":
							navigate_dropbox_clone_value("trigger-image-" + from, "trigger-image-" + to);
							break;

						case "rollover":
							navigate_dropbox_clone_value("trigger-rollover-" + from, "trigger-rollover-" + to);
							navigate_dropbox_clone_value("trigger-rollover-active-" + from, "trigger-rollover-active-" + to);
							break;

						case "video":
							navigate_dropbox_clone_value("trigger-video-" + from, "trigger-video-" + to);
							break;

						case "flash":
							navigate_dropbox_clone_value("trigger-flash-" + from, "trigger-flash-" + to);
							break;

						case "html":
							// ncid: navigate codemirror instance destination
							// ncio: navigate codemirror instance destination
							for(ncid in navigate_codemirror_instances)
							{
								if($(navigate_codemirror_instances[ncid].getTextArea()).attr("id") == "trigger-html-" + to)
								{
									for(ncio in navigate_codemirror_instances)
									{
										if($(navigate_codemirror_instances[ncio].getTextArea()).attr("id") == "trigger-html-" + from)
										{
											navigate_codemirror_instances[ncid].setValue(navigate_codemirror_instances[ncio].getValue());
										}
									}
								}
							}
							$(navigate_codemirror_instances).each(function() { this.refresh(); } );
							break;

						case "links":
							// remove previous links (if any)
							$("#trigger-links-" + to).find("tr").not("#trigger-links-table-row-model-" + to).not(":first").remove();

							// copy each link in the origin language
							$("#trigger-links-" + from).find("tr").not("#trigger-links-table-row-model-" + from).not(":first").each(function()
							{
								// add a row
								$("#trigger-links-table-add-" + to).trigger("click");
								$(this).find("td").each(function(i)
								{
									if($(this).find(".select2-container").length > 0)
									{
										// select2 field

										var input_name = $("#trigger_links_table_" + to).find("tr:visible:last").find("td").eq(i).find("select").attr("name");
										var input_value = $(this).find("select").val();

										if(input_name)
                                        {
											$("select[name=\""+input_name+"\"]").val(input_value).trigger("change");
                                        }
									}
									else
									{
										// standard input or checkbox field
										$("#trigger_links_table_" + to).find("tr:visible:last").find("td").eq(i).find("input").val($(this).find("input").val());
										if($(this).find("input").attr("checked"))
										{
											$("#trigger_links_table_" + to).find("tr:visible:last").find("td").eq(i).find("input").attr("checked", "checked");
                                        }
									}
								});
							});
							break;

						case "content":
							tinyMCE.get("trigger-content-" + to).setContent(
								tinyMCE.get("trigger-content-" + from).getContent()
							);
							break;

						case "title":
						case "":
						default:
							// nothing to do
							break;
					}

					// copy action type
					$("#action-type-" + to)
						.val($("#action-type-" + from).val())
						.trigger("change");

					// copy action value
					switch($("#action-type-" + to).val())
					{
						case "web":
						case "web-n":
							$("#action-web-" + to).val($("#action-web-" + from).val());
							break;

						case "javascript":
							$("#action-javascript-" + to).val($("#action-javascript-" + from).val());
							break;

						case "file":
							navigate_dropbox_clone_value("action-file-" + from, "action-file-" + to);
							break;

						case "image":
							navigate_dropbox_clone_value("action-image-" + from, "action-image-" + to);
							break;

						case "":
						default:
							// nothing to do
							break;
					}
				}
			');

            // right now, only fontawesome icon set is supported
            $fontawesome_classes = '';
            if($links_icons == 'fontawesome')
            {
                $fontawesome_classes = block::fontawesome_list();
                $fontawesome_classes = array_map(
                    function($v)
                    {
                        $x = new stdClass();
                        $x->id = $v;
                        if(!empty($v))
                        {
                            $x->text = substr($v, 3);
                        }
                        return $x;
                    },
                    $fontawesome_classes
                );
            }

            $layout->add_script('
                var active_languages = ["'.implode('", "', array_keys($options)).'"];
                navigate_items_select_language("'.$website->languages_list[0].'");
                navigate_fontawesome_classes = '.json_encode($fontawesome_classes).';
            ');

            foreach($website->languages_list as $alang)
            {
                $layout->add_script('
					$(window).on("load", function()
					{
						$("#trigger-type-'.$alang.'").val("'.$item->trigger['trigger-type'][$alang].'");
						$("#action-type-'.$alang.'").val("'.$item->action['action-type'][$alang].'");
						navigate_blocks_trigger_change("'.$alang.'", $("<input type=\"hidden\" value=\"'.$item->trigger['trigger-type'][$alang].'\" />"));

						links_table_row_models["'.$alang.'"] = $("#trigger-links-table-row-model-'.$alang.'").html();
						if($("#trigger_links_table_'.$alang.'").find("tr").not(".nodrag").length > 1)
							$("#trigger-links-table-row-model-'.$alang.'").hide();

						// prepare select2 to select icons
						if('.($links_icons=='fontawesome'? 'true' : 'false').')
						{
							$("[id^=trigger_links_table_").find("tr").each(function(i, tr)
							{
								// do not apply select2 to head row
								if(!$(tr).find("select"))
									return;

								// do not apply select2 to model row
								if($(tr).attr("id") && ($(this).attr("id")).indexOf("table-row-model") > 0)
									return;

								navigate_blocks_trigger_links_table_icon_selector(tr);
							});
						}
					});
            	');
            }
	        break;
  	}

    if(!empty($item->type))
    {
        // we need to know if the block is defined in the active theme or in the database (numeric ID)
        foreach($block_types as $bt)
        {
            // navigate 1.x compatibility
            if(!isset($bt['id']) && isset($bt['code']))
            {
                $bt['id'] = $bt['code'];
            }

            if($bt['id'] == $item->type)
            {
                $block_type_id = $bt['id'];
                break;
            }
        }

        $properties_html = navigate_property_layout_form('block', $block_type_id, 'block', $item->id);

        if(!empty($properties_html))
        {
            $navibars->add_tab(t(77, "Properties"));
            $navibars->add_tab_content($properties_html);
        }
        else
        {
            // need to load auxiliary functions anyway
            navigate_property_layout_scripts();
        }
    }

    $navibars->add_tab(t(336, "Display"));

    $default_value = 1;
    if(!empty($item->categories))
    {
        $default_value = 0;
    }
    else if(!empty($item->exclusions))
    {
        $default_value = 2;
    }

    $navibars->add_tab_content_row(array(
        '<label>'.t(330, 'Categories').'</label>',
        $naviforms->buttonset(
            'all_categories',
            array(
                '1' => t(396, 'All categories'),
                '0' => t(405, 'Selection'),
                '2' => t(552, 'Exclusions')
            ),
            $default_value
        )
    ));

	$hierarchy = structure::hierarchy(0);
	$categories_list = structure::hierarchyList($hierarchy, $item->categories);
	$exclusions_list = structure::hierarchyList($hierarchy, $item->exclusions);

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
		    '<div class="category_tree" id="category-tree-parent">
                <img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.
                '<div class="tree_ul">'.$categories_list.'</div>'.
            '</div>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
		    '<div class="category_tree" id="exclusions-tree-parent">
                <img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.
                '<div class="tree_ul">'.$exclusions_list.'</div>'.
            '</div>'
        )
    );
										
	if(!is_array($item->categories))
    {
        $item->categories = array();
    }

    if(!is_array($item->exclusions))
    {
        $item->exclusions = array();
    }

    $navibars->add_tab_content($naviforms->hidden('categories', implode(',', $item->categories)));
    $navibars->add_tab_content($naviforms->hidden('exclusions', implode(',', $item->exclusions)));

	$elements_display = "all";
	if(!empty($item->elements['exclusions']))
    {
        $elements_display = "exclusions";
    }
	else if(!empty($item->elements['selection']))
    {
        $elements_display = "selection";
    }

    $navibars->add_tab_content_row(array(
        '<label>'.t(22, 'Elements').' '.t(428, '(no category)').'</label>',
        $naviforms->buttonset(
            'elements_display',
            array(
                'all' => t(443, 'All'),
                'selection' => t(405, 'Selection'),
                'exclusions' => t(552, 'Exclusions')
            ),
            $elements_display,
            "navigate_blocks_elements_display_change(this)"
        )
    ));

	$layout->add_script('
		function navigate_blocks_elements_display_change(el)
		{
			el = $(el).prev();
			if($(el).val()=="all")
				$("#elements_selection_wrapper").hide();
			else
				$("#elements_selection_wrapper").show();
		}

		navigate_blocks_elements_display_change($("label[for=elements_display_'.$elements_display.']"));
	');

	if(!is_array($item->elements))
    {
        $item->elements = array();
    }
	$items_ids = array_values($item->elements);
	$items_ids = $items_ids[0];
	if(empty($items_ids))
    {
        $items_ids = array();
    }
	$items_titles = array();
	for($i=0; $i < count($items_ids); $i++)
	{
		$item_title = $DB->query_single(
        'text',
        'nv_webdictionary',
        '
            node_type = "item" AND
            website = "'.$website->id.'" AND
            node_id = :node_id AND
            subtype = "title" AND
            lang = :lang',
        '',
            array(
                ':node_id' => $items_ids[$i],
                ':lang' => $website->languages_published[0]
            )
        );

		$items_titles[$i] = $item_title;
	}

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
	        $naviforms->selectfield("elements_selection", $items_ids, $items_titles, $items_ids, null, true, null, null, false)
        ),
		"elements_selection_wrapper"
    );

	$layout->add_script('
		$("#elements_selection").select2({
			placeholder: "'.t(533, "Find element by title").'",
	        minimumInputLength: 1,
	        ajax: {
	            url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=items&act=json_find_item",
	            dataType: "json",
	            delay: 100,

	            data: function(params)
	            {
	                return {
		                title: params.term,
		                //association: "free",
		                embedding: 0,
		                nd: new Date().getTime(),
		                page_limit: 30, // page size
		                page: params.page // page number
		            };
	            },
	            processResults: function (data, params)
		        {
		            params.page = params.page || 1;
		            return {
						results: data.items,
						pagination: { more: (params.page * 30) < data.total_count }
					};
		        }
	        },
	        templateSelection: function(row)
			{
				if(row.id)
					return row.text + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>";
				else
					return row.text;
			},
			escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
	        triggerChange: true
		});
		
		$("#elements_selection_wrapper").find(".select2-search__field").css("width", "408px");		
		$("#elements_selection_wrapper").find("li.select2-search").css("width", "auto");
	');

							
	if(!empty($item->type))
	{				
		$navibars->add_tab(t(171, 'Order'));	// order blocks of the same type
		
		$DB->query('SELECT b.id as id, d.text as title, b.fixed as fixed
					  FROM nv_blocks b, nv_webdictionary d
					 WHERE b.type = "'.$item->type.'"
					   AND d.node_type = "block"
					   AND d.subtype = "title"
					   AND d.lang = "'.$website->languages_list[0].'"
					   AND d.node_id = b.id
					   AND d.website = '.$website->id.'
					   AND b.website = '.$website->id.'
					ORDER BY b.position ASC');				
										  
		$block_ids = $DB->result('id');
		$blocks = $DB->result();

		$navibars->add_tab_content($naviforms->hidden('blocks-order', implode('#', $block_ids)));

		$table = new naviorderedtable("blocks_order_table");
		$table->setWidth("408px");
		$table->setHiddenInput("blocks-order");

		$table->addHeaderColumn('ID', 50);			
		$table->addHeaderColumn(t(67, 'Title'), 350);
        $table->addHeaderColumn('<div style=" text-align: center; ">'.t(394, 'Fixed').'</div>', 50);

		foreach($blocks as $block)
		{
			$table->addRow($block->id, array(
				array('content' => $block->id, 'align' => 'left'),
				array('content' => core_special_chars($block->title), 'align' => 'left'),
                array('content' => '<span class="checkbox-wrapper">
                                        <input type="checkbox" name="blocks-order-fixed['.$block->id.']" id="blocks-order-fixed['.$block->id.']" value="1" '.(($block->fixed=='1')? 'checked="checked"' : '').' />
                                        <label for="blocks-order-fixed['.$block->id.']" />
                                    </span>',
	                  'align' => 'center'
                )
			));
		}	
		
		$navibars->add_tab_content_row(
            array(
                '<label>'.t(23, 'Blocks').'</label>',
				'<div>'.$table->generate().'</div>',
				'<div class="subcomment"><img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'</div>',
                '<div class="subcomment"><span class="ui-icon ui-icon-lightbulb" style=" float: left; margin-right: 4px; "></span> '.t(395, '"Fixed" assigns a static position when the order is random').'</div>'
            )
        );
											
	}
	return $navibars->generate();
}

function blocks_types_list()
{
	global $user;
    global $events;
	
	$navibars = new navibars();
	$navitable = new navitable('blocks_types_list');
	
	$navibars->title(t(23, 'Blocks').' / '.t(167, 'Types'));

    $group_blocks_links = array();
    list($bg_rs, $bg_total) = block_group::paginated_list(0, 10, 'title', 'desc');

    if($bg_total > 0 && $bg_total <= 10)
    {
        foreach($bg_rs as $bg)
        {
            $group_blocks_links[] = '<a href="?fid=blocks&act=block_group_edit&id='.$bg['id'].'"><i class="fa fa-fw fa-caret-right"></i> '.$bg['title'].'</a>';
        }

        $events->add_actions(
            'blocks',
            array(
                'item' => null,
                'navibars' => &$navibars
            ),
            $group_blocks_links,
            '<a class="content-actions-submenu-trigger" href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/bricks.png"> '.t(506, 'Groups').' &#9662;</a>'
        );
    }

    $navibars->add_actions(
        array(
            '<a href="?fid=blocks&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>',
            (!empty($group_blocks_links)? '' : '<a href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/bricks.png"> '.t(506, 'Groups').'</a>')
        )
    );

	$navibars->add_actions(
		array(
			($user->permission("items.create") == 'true'?
				'<a href="?fid=blocks&act=block_type_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>'
				: ''),
			'<a href="?fid=blocks&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
		)
	);


	$navitable->setURL('?fid=blocks&act=block_types_json');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=blocks&act=block_type_edit&id=');
	
	$navitable->addCol("ID", 'id', "80", "true", "left");
    $navitable->addCol(t(491, 'Class'), 'type', "80", "true", "left");
    $navitable->addCol(t(237, 'Code'), 'code', "120", "true", "left");
	$navitable->addCol(t(67, 'Title'), 'title', "200", "true", "left");
	$navitable->addCol(t(155, 'Width').' (px)', 'width', "80", "true", "left");	
	$navitable->addCol(t(156, 'Height').' (px)', 'height', "80", "true", "left");		
	
	$navibars->add_content($navitable->generate());
	
	return $navibars->generate();
}

function blocks_type_form($item)
{
	global $user;
	global $layout;
    global $events;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	if(empty($item['id']))
    {
        $navibars->title(t(23, 'Blocks').' / '.t(167, 'Types').' / '.t(38, 'Create'));
    }
	else
    {
        $navibars->title(t(23, 'Blocks').' / '.t(167, 'Types').' / '.t(170, 'Edit').' ['.$item['id'].']');
    }

    $readonly = false;

	if(empty($item['id']))
	{
		$navibars->add_actions(
            array(	'<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
		);
	}
    else if(!empty($item['id']) && !is_numeric($item['id']))
    {
        $layout->navigate_notification(t(432, "Read only mode"), false, true);
        $readonly = true;
    }
	else
	{
		$navibars->add_actions(
            array(
                '<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
				'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=blocks&act=block_type_delete&id='.$item['id'].'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}

    $group_blocks_links = array();
    list($bg_rs, $bg_total) = block_group::paginated_list(0, 10, 'title', 'desc');

    if($bg_total > 0 && $bg_total <= 10)
    {
        foreach($bg_rs as $bg)
        {
            $group_blocks_links[] = '<a href="?fid=blocks&act=block_group_edit&id='.$bg['id'].'"><i class="fa fa-fw fa-caret-right"></i> '.$bg['title'].'</a>';
        }

        $events->add_actions(
            'blocks',
            array(
                'item' => null,
                'navibars' => &$navibars
            ),
            $group_blocks_links,
            '<a class="content-actions-submenu-trigger" href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/bricks.png"> '.t(506, 'Groups').' &#9662;</a>'
        );
    }

    $navibars->add_actions(
        array(
            '<a href="?fid=blocks&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>',
            (!empty($group_blocks_links)? '' : '<a href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/bricks.png"> '.t(506, 'Groups').'</a>')
        )
    );


	$navibars->add_actions(
        array(
            (!empty($item->id)? '<a href="?fid=blocks&act=block_type_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=blocks&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item['id']));
    $navibars->add_tab_content($naviforms->csrf_token());
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($item['id'])? $item['id'] : t(52, '(new)')).'</span>'
        )
    );

    // TODO: in Navigate CMS 2.0+ add several block types (p.e. Ad (Google adsense, ...), Map (Bing, Yahoo, Google, ...))
    $block_modes = block::modes();
    $navibars->add_tab_content_row(
        array(
            '<label>'.t(491, 'Class').'</label>',
   			$naviforms->selectfield('type',
                array_keys($block_modes),
                array_values($block_modes),
   			    $item['type'],
   			    '',
   			    false
            )
   		)
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(67, 'Title').'</label>',
   			$naviforms->textfield('title', $item['title'])
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(237, 'Code').'</label>',
    		$naviforms->textfield('code', $item['code']),
            '<div class="subcomment">
            <span style=" float: left; margin-left: -3px; " class="ui-icon ui-icon-lightbulb"></span>'.
            t(436, 'Used as a class in HTML elements').
            '</div>'
        )
    );

	$navibars->add_tab_content_row(
        array(
            '<label>'.t(168, 'Notes').'</label>',
			$naviforms->textarea('notes', $item['notes'])
        )
    );

    $navibars->add_tab(t(145, "Size").' & '.t(438, "Order"));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(155, 'Width').'<sup>*</sup></label>',
   			$naviforms->textfield('width', $item['width']),
   			'px'
        )
    );

   	$navibars->add_tab_content_row(
        array(
            '<label>'.t(156, 'Height').'<sup>*</sup></label>',
   			$naviforms->textfield('height', $item['height']),
   			'px'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<div class="subcomment italic">* '.t(169, 'You can leave blank a field to not limit the size').'</div>'
   		)
    );

	$navibars->add_tab_content_row(
        array(
            '<label>&nbsp;</label>',
			'<a id="wikipedia_web_banner_entry" class="italic" href="http://en.wikipedia.org/wiki/Web_banner" target="_blank"><span class="ui-icon ui-icon-info" style=" float: left;"></span> '.t(393, 'Standard banner sizes').' (Wikipedia)</a>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(404, 'Order by').'</label>',
            $naviforms->selectfield(
                'order',
                array(
                    'theme',
                    'priority',
                    'random'
                ),
                array(
                    t('368', 'Theme'),
                    t('66', 'Priority'),
                    t('399', 'Random')
                ),
                $item['order']
            )
        )
    );

   	$navibars->add_tab_content_row(
        array(
            '<label>'.t(397, 'Maximum').'</label>',
   			$naviforms->textfield('maximum', $item['maximum']),
   			'<div class="subcomment"><span class="ui-icon ui-icon-lightbulb" style=" float: left; margin-left: -3px; "></span> '.t(400, 'Enter 0 to display all').'</div>'
        )
    );

    $layout->add_script('
        function navigate_blocks_code_generate(el)
        {
            if($("#code").val()!="")
                return;
            var title = $("#title").val();
			title = title.replace(/([\'"“”«»?:\+\&!¿#\\\\])/g, "");
			title = title.replace(/[.\s]+/g, navigate["word_separator"]);
            $("#code").val(title.toLowerCase());
        }

        $("#code").on("focus", function()
        {
            if($(this).val() == "")
                navigate_blocks_code_generate();
        });
        '
    );

    if(!empty($item['id']))
    {
        $navibars->add_tab(t(77, "Properties"));

        $table = new naviorderedtable("block_properties_table");
        $table->setWidth("550px");
        $table->setHiddenInput("block-properties-order");
        $table->setDblclickCallback("navigate_block_edit_property");

        $navibars->add_tab_content( $naviforms->hidden('block-properties-order', "") );

        $table->addHeaderColumn(t(159, 'Name'), 350, true);
        $table->addHeaderColumn(t(160, 'Type'), 150);
        $table->addHeaderColumn(t(65, 'Enabled'), 50);

        $properties = property::elements($item['id'], 'block');
        $types		= property::types();

        for($p=0; $p < count($properties); $p++)
        {
            $table->addRow($properties[$p]->id, array(
                array('content' => $properties[$p]->name, 'align' => 'left'),
                array('content' => $types[$properties[$p]->type], 'align' => 'left'),
                array('content' => '<input type="checkbox" name="property-enabled[]" value="'.$properties[$p]->id.'" disabled="disabled" id="block-type-property-enabled-'.$properties[$p]->id.'" '.(($properties[$p]->enabled=='1'? ' checked=checked ' : '')).' />
                                    <label for="block-type-property-enabled-'.$properties[$p]->id.'"></label>',
	                  'align' => 'center'),
            ));
        }

        if($readonly)
        {
            $navibars->add_tab_content_row(array(	'<label>'.t(77, 'Properties').'</label>',
                '<div>'.$table->generate().'</div>'));
        }
        else
        {
            $navibars->add_tab_content_row(array(	'<label>'.t(77, 'Properties').'</label>',
                '<div>'.$table->generate().'</div>',
                '<div class="subcomment">
                    <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, 'Drag any row to assign priorities').'.
                     '.t(192, 'Double click any row to edit').'
                </div>' ));

            $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                '<button id="block-properties-create"><img src="img/icons/silk/add.png" align="absmiddle" /> '.t(38, 'Create').'</button>'));
        }

        $navibars->add_content('
		<form id="block-properties-edit-dialog" style="display: none;">
			<div class="navigate-form-row">
				<label>ID</label>
				<span id="property-id-span">'.t(52, '(new)').'</span>
				'.$naviforms->hidden('property-id', '').'
				'.$naviforms->hidden('property-template', $item->id).'
				'.$naviforms->hidden('property-element', "block").'
			</div>
			<div class="navigate-form-row">
				<label>'.t(67, 'Title').'</label>
				'.$naviforms->textfield('property-name', '').'
			</div>
			<div class="navigate-form-row">
				<label>'.t(160, 'Type').'</label>
				'.$naviforms->selectfield(
                    'property-type',
                    array_keys($types),
                    array_values($types),
                    'value',
                    'navigate_block_property_type_change()'
                ).'
			</div>
			<div class="navigate-form-row">
				<label>'.t(200, 'Options').'</label>
				'.$naviforms->textarea('property-options', '').'
				<div class="subcomment">
					'.t(201, 'One line per option, formatted like this: value#title').'
				</div>
			</div>
			<div class="navigate-form-row">
				<label>'.t(199, 'Default value').'</label>
				'.$naviforms->textfield('property-dvalue', '').'
				<div class="subcomment">
				    <span id="property-comment-boolean">'.t(426, 'Enter "1" for true, "0" for false').'</span>
					<span id="property-comment-option">'.t(202, 'Enter only the value').'</span>
					<span id="property-comment-moption">'.t(212, 'Enter the selected values separated by commas').': 3,5,8</span>
					<span id="property-comment-text">'.t(203, 'Same value for all languages').'</span>
					<span id="property-comment-rating">'.t(223, 'Default is 5 stars, if you want a different number: default_value#number_of_stars').' 5#10</span>
					<span id="property-comment-date">'.t(50, 'Date format').': '.date($user->date_format).'</span>
					<span id="property-comment-color">'.t(442, 'Hexadecimal color code').': #ffffff</span>
					<span id="property-comment-country">'.t(225, 'Alpha-2 country code').' (es, us, uk...)</span>
					<span id="property-comment-file">'.t(204, 'ID of the file').'</span>
					<span id="property-comment-coordinates">'.t(298, 'Latitude').'#'.t(299, 'Longitude').': 40.689231#-74.044505</span>
				</div>
			</div>
			<div class="navigate-form-row">
				<label>'.t(65, 'Enabled').'</label>
				'.$naviforms->checkbox('property-enabled', 1).'
			</div>
		</form>');

        $layout->add_script('
			$("#block-properties-create").bind("click", function()
			{
				navigate_block_edit_property();
				return false;
			});

			function navigate_block_edit_property(el)
			{
				if(!el)	// new property
				{
					$("#property-id").val("");
					$("#property-id-span").html("'.t(52, '(new)').'");
					$("#property-template").val("'.$item['id'].'");
					$("#property-name").val("");
					$("#property-type").val("value");
					$("#property-element").val("block");
					$("#property-options").val("");
					$("#property-dvalue").val("");
				    $("#property-enabled").attr("checked", "checked");
				}
				else
				{
					$.ajax({
					   type: "GET",
					   async: false,
					   dateType: "json",
					   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=blocks&act=block_property_load&block='.$item->id.'&id=" + $(el).attr("id"),
					   success: function(data)
					   {
						   $("#property-id-span").html(data.id);
						   $("#property-id").val(data.id);
						   $("#property-template").val(data.template);
						   $("#property-name").val(data.name);
						   $("#property-type").val(data.type);
						   $("#property-element").val("block");
						   $("#property-options").val(data.options);
						   $("#property-dvalue").val(data.dvalue);
						   if(data.enabled=="1")
							   $("#property-enabled").attr("checked", "checked");
							else
							   $("#property-enabled").removeAttr("checked");

						   var options = "";
						   for(var o in data.options)
						   {
							   options += o + "#" + data.options[o] + "\n";
						   }
						   $("#property-options").val(options);

					   }
					 });
				}

				navigate_block_property_type_change();

				if('.($readonly? 'true' : 'false').')
				{
                    $("#block-properties-edit-dialog").dialog(
                    {
                        title: \'<img src="img/icons/silk/pencil.png" align="absmiddle" /> '.t(170, 'Edit').'\',
                        resizable: true,
                        height: 360,
                        width: 650,
                        modal: true,
                    });
				}
				else // show dialog with action buttons
				{
                    $("#block-properties-edit-dialog").dialog(
                    {
                        title: \'<img src="img/icons/silk/pencil.png" align="absmiddle" /> '.t(170, 'Edit').'\',
                        resizable: true,
                        height: 410,
                        width: 650,
                        modal: true,
                        buttons: {
                            "'.t(58, 'Cancel').'": function() {
                                $(this).dialog("close");
                            },
                            "'.t(35, 'Delete').'": function() {
                                $.ajax({
                                   type: "POST",
                                   async: false,
                                   dateType: "text",
                                   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=blocks&act=block_property_remove",
                                   data: $("#block-properties-edit-dialog").serialize(),
                                   success: function(msg)
                                   {
                                     $("#block_properties_table").find("#" + $("#property-id").val()).remove();
                                     navigate_naviorderedtable_block_properties_table_reorder();
                                     $("#block-properties-edit-dialog").dialog("close");
                                   }
                                 });
                            },
                            "'.t(190, 'Ok').'": function()
                            {
                                $.ajax({
                                   type: "POST",
                                   async: false,
                                   dateType: "text",
                                   url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=blocks&act=block_property_save",
                                   data: $("#block-properties-edit-dialog").serialize(),
                                   success: function(data)
                                   {
                                       if($("#property-id").val() > 0)
                                       {
                                           // update
                                           var tr = $("#block_properties_table").find("#" + $("#property-id").val());
                                           tr.find("td").eq(0).html(data.name);
                                           tr.find("td").eq(1).html(data.type_text);
                                           tr.find("input[type=checkbox]").attr("checked", (data.enabled==1));
                                       }
                                       else
                                       {
                                           // insert
                                           var checked = "";

                                           if(data.enabled) checked = \' checked="checked" \';
                                           var tr = \'<tr id="\'+data.id+\'"><td>\'+data.name+\'</td><td>\'+data.type_text+\'</td><td align="center"><input name="property-enabled[]" id="block-type-property-enabled-\'+data.id+\'" type="checkbox" disabled="disabled" value="\'+data.id+\'" \'+checked+\' /><label for="block-type-property-enabled-\'+data.id+\'"></label></td></tr>\';
                                           $("#block_properties_table").find("tbody:last").append(tr);
                                           $("#block_properties_table").find("tr:last").on("dblclick", function() { navigate_block_edit_property(this); });
                                           $("#block_properties_table").tableDnD(
                                            {
                                                onDrop: function(table, row)
                                                {		navigate_naviorderedtable_block_properties_table_reorder();		}
                                            });
                                       }
                                       navigate_naviorderedtable_block_properties_table_reorder();
                                       $("#block-properties-edit-dialog").dialog("close");
                                   }
                                 });
                            }
                        }
                    });
                }
			}

			function navigate_block_property_type_change()
			{
				$("#property-options").parent().hide();
				$("#property-dvalue").next().find("span").hide();

				switch($("#property-type").val())
				{
					case "option":
						$("#property-options").parent().show();
						$("#property-comment-option").show();
						break;

					case "moption":
						$("#property-options").parent().show();
						$("#property-comment-moption").show();
						break;

					case "text":
					case "textarea":
					case "link":
						$("#property-comment-text").show();
						break;

					case "date":
					case "datetime":
						$("#property-comment-date").show();
						break;

					case "image":
					case "file":
						$("#property-comment-file").show();
						break;

					case "rating":
						$("#property-comment-rating").show();
						break;

                    case "color":
						$("#property-comment-color").show();
						break;

					case "coordinates":
						$("#property-comment-coordinates").show();
						break;

					case "country":
						$("#property-comment-country").show();
						break;

                    case "boolean":
						$("#property-comment-boolean").show();
						break;

					case "comment":
					case "value":
					default:
				}
			}

			navigate_naviorderedtable_block_properties_table_reorder();
		');

    }

	return $navibars->generate();
}

function block_groups_list()
{
    $navibars = new navibars();
    $navitable = new navitable('block_groups_list');

    $navibars->title(t(23, 'Blocks').' / '.t(506, 'Groups'));

    $navibars->add_actions(
        array(
            '<a href="?fid=blocks&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>',
            '<a href="?fid=blocks&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_edit.png"> '.t(167, 'Types').'</a>'
        )
    );

    $navibars->add_actions(	array(
        '<a href="?fid=blocks&act=block_group_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
        '<a href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
    ));

    $navitable->setURL('?fid=blocks&act=block_groups_json');
    $navitable->sortBy('id');
    $navitable->setDataIndex('id');
    $navitable->setEditUrl('id', '?fid=blocks&act=block_group_edit&id=');
    $navitable->setGridNotesObjectName("block_group");

    $navitable->addCol("ID", 'id', "80", "true", "left");
    $navitable->addCol(t(237, 'Code'), 'code', "120", "true", "left");
    $navitable->addCol(t(67, 'Title'), 'title', "200", "true", "left");
    $navitable->addCol(t(23, 'Blocks'), 'blocks', "80", "true", "left");
    $navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");

    $navibars->add_content($navitable->generate());

    return $navibars->generate();
}

function block_group_form($item)
{
    global $DB;
    global $website;
    global $layout;
    global $theme;
    global $current_version;

    $navibars = new navibars();
    $naviforms = new naviforms();

    if(empty($item->id))
    {
        $navibars->title(t(23, 'Blocks').' / '.t(506, 'Groups').' / '.t(38, 'Create'));
    }
    else
    {
        $navibars->title(t(23, 'Blocks').' / '.t(506, 'Groups').' / '.t(170, 'Edit').' ['.$item->id.']');
    }

    if(empty($item->id))
    {
        $navibars->add_actions(
            array(
                '<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'
            )
        );
    }
    else
    {
        $navibars->add_actions(
            array(
                '<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
                '<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=blocks&act=block_group_delete&id='.$item->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
    }

    $navibars->add_actions(
        array(
            '<a href="?fid=blocks&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick.png"> '.t(23, 'Blocks').'</a>',
            '<a href="?fid=blocks&act=block_types_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/brick_edit.png"> '.t(167, 'Types').'</a>'
        )
    );

    if(!empty($item->id))
    {
        $notes = grid_notes::comments('block_group', $item->id);
        $navibars->add_actions(
            array(
                '<a href="#" onclick="javascript: navigate_display_notes_dialog();"><span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span><img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'</a>'
            )
        );
    }

    $navibars->add_actions(
        array(
        (!empty($item->id)? '<a href="?fid=blocks&act=block_group_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
        '<a href="?fid=blocks&act=block_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
    ));

    $navibars->form();

    if(!empty($item->id))
    {
        $navibars->add_tab(t(23, "Blocks"));

        $allowed_types = array();
        if(!empty($item->code))
        {
            for($bg=0; $bg < count($theme->block_groups); $bg++)
            {
                if($theme->block_groups[$bg]->id == $item->code)
                {
                    if(isset($theme->block_groups[$bg]->allowed_types))
                    {
                        $allowed_types = $theme->block_groups[$bg]->allowed_types;
                    }
                    break;
                }
            }
        }

        $blocks_selected = array();

        if(!is_array($item->blocks))
        {
            $item->blocks = array();
        }

        $navibars->add_tab_content($naviforms->hidden('blocks_group_selection', json_encode($item->blocks)));
        $navibars->add_tab_content($naviforms->hidden('blocks-order', ""));

        $block_types = block::types();
        $lang = $website->languages_published[0];
        $extensions_blocks = extension::blocks();

        for($p=0; $p < count($item->blocks); $p++)
        {
            unset($block);

            switch($item->blocks[$p]['type'])
            {
                case "block":
                    $block = new block();
                    $block->load($item->blocks[$p]['id']);

                    if(empty($block) || empty($block->type))
                    {
                        continue;
                    }

                    $blocks_selected[] = '
                        <div class="block_group_block ui-state-default" data-block-id="'.$block->id.'" data-block-type="block" data-block-uid="'.$item->blocks[$p]['uid'].'">
                            <div class="actions">
                                <a href="?fid=blocks&act=edit&id='.$block->id.'"><img src="'.NAVIGATE_URL.'/img/icons/silk/pencil.png" /></a>
                                <a href="#" onclick="navigate_blocks_selection_remove(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" /></a>
                            </div>
                            <div class="title">'.$block->dictionary[$lang]['title'].'</div>
                            <div class="subcomment"><span style="float: right;">ID '.$block->id.'</span><img src="img/icons/silk/brick.png" /> '.$theme->t($block->type).'</div>
                        </div>
                    ';
                    break;

                case "block_type":
                    for($bt=0; $bt < count($block_types); $bt++)
                    {
                        if($block_types[$bt]['id']==$item->blocks[$p]['id'])
                        {
                            $block = $block_types[$bt];
                            break;
                        }
                    }

                    $blocks_selected[] = '
                        <div class="block_group_block ui-state-default" data-block-id="'.$block['code'].'" data-block-type="block_type" data-block-uid="'.$item->blocks[$p]['uid'].'">
                            <div class="actions">
                                <a href="#" data-block-group="'.$block['block_group'].'" data-block-type-code="'.$block['code'].'" data-block-type-title="(span)" onclick="navigate_blocks_block_type_title(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/text_horizontalrule.png" /><span class="hidden">'.$item->blocks[$p]['title'].'</span></a>
                                <a href="#" onclick="navigate_blocks_selection_remove(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" /></a>
                            </div>
                            <div class="title" title="'.$block['description'].'">'.$block['title'].'</div>
                            <div class="subcomment">
                                <span style="float: right;">ID '.$block['code'].'</span>
                                <img src="img/icons/silk/brick_link.png" /> '.$block['count'].' '.($block['count']==1? t(437, "Block") : t(23, "Blocks")).'
                            </div>
                        </div>
                    ';
                    break;

                case "block_group_block":
                    if(is_array($theme->block_groups))
                    {
                        foreach($theme->block_groups as $key => $bg)
                        {
                            for($i=0; $i < count($bg->blocks); $i++)
                            {
                                if($bg->blocks[$i]->id==$item->blocks[$p]['id'])
                                {
                                    $block = array(
                                        'code' => $bg->blocks[$i]->id,
                                        'type' => $bg->blocks[$i]->id,
                                        'title' => $theme->t($bg->blocks[$i]->title),
                                        'description'  => $theme->t($bg->blocks[$i]->description),
                                        'properties'  => $bg->blocks[$i]->properties,
                                        'block_group' => $bg->id
                                    );
                                    break;
                                }
                            }
                        }
                    }

                    $blocks_selected[] = '
                        <div class="block_group_block ui-state-default" data-block-id="'.$block['code'].'" data-block-type="block_group_block"  data-block-uid="'.$item->blocks[$p]['uid'].'">
                            <div class="actions">
                                '.(empty($block['properties'])? '':'<a href="#" data-block-group="'.$block['block_group'].'" data-block-group-block="'.$block['code'].'" data-block-group-action="settings" onclick="navigate_blocks_group_block_settings(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cog.png" /></a>').'
                                <a href="#" onclick="navigate_blocks_selection_remove(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" /></a>
                            </div>
                            <div class="title" title="'.$block['description'].'">'.$block['title'].'</div>
                            <div class="subcomment"><span style="float: right;">ID '.$block['type'].'</span><img src="img/icons/silk/bricks.png" /> '.$theme->t($block['type']).'</div>
                        </div>
                    ';
                    break;

                case "extension":
                    $block = $item->blocks[$p];

                    for($be=0; $be < count($extensions_blocks); $be++)
                    {
                        if($block['id'] == $extensions_blocks[$be]->id)
                        {
                            $extension = new extension();
                            $extension->load($block['extension']);

                            $blocks_selected[] = '
                                <div class="block_group_block ui-state-default" data-block-id="'.$block['id'].'" data-block-type="extension" data-block-extension="'.$block['extension'].'"  data-block-uid="'.$item->blocks[$p]['uid'].'">
                                    <div class="actions">
                                        '.(empty($extensions_blocks[$be]->properties)? '':'<a href="#" data-block-group="'.$item->code.'" data-block-id="'.$block['id'].'" data-block-extension="'.$block['extension'].'" data-block-group-action="settings" onclick="navigate_block_group_extension_block_settings(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cog.png" /></a>').'
                                        <a href="#" onclick="navigate_blocks_selection_remove(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" /></a>
                                    </div>
                                    <div class="title">'.$extension->t($extensions_blocks[$be]->title).'</div>
                                    <div class="subcomment"><span style="float: right;">ID '.$block['id'].'</span><img src="img/icons/silk/plugin.png" /> '.$extension->title.'</div>
                                </div>
                            ';
                            break;
                        }
                    }
                    break;
            }
        }

        $blocks_selected = implode("\n", $blocks_selected);

        $navibars->add_tab_content(
            '<div id="block_group_selected_blocks" style="width: 49%; float: left; margin-right: 2%;">
                <div class="ui-accordion ui-widget ui-helper-reset">
                    <h3 class="ui-accordion-header ui-state-default ui-accordion-icons ui-accordion-header-active ui-state-active ui-corner-top">
                        <img src="img/icons/silk/bricks.png" style="vertical-align: middle;" /> '.t(405, 'Selection').'
                    </h3>
                    <div class="ui-accordion-content ui-helper-reset ui-widget-content ui-corner-bottom ui-accordion-content-active">'.$blocks_selected.'</div>
                </div>
                <div class="subcomment">
                    <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(72, "Drag any row to assign priorities").'
                </div>
             </div>'
        );

        // **** ADD specific BLOCKS ****
        $sql = '
             SELECT b.type, b.id, d.text as title
               FROM nv_blocks b
          LEFT JOIN nv_webdictionary d
                     ON b.id = d.node_id
                    AND d.node_type = "block"
                    AND d.subtype = "title"
                    AND d.lang = "'.$website->languages_list[0].'"
                    AND d.website = '.$website->id.'
              WHERE b.website = '.$website->id.'
           ORDER BY b.id DESC';

        $DB->query($sql);
        $block_elements = $DB->result();

        $block_group_blocks = array();
        for($bg=0; $bg < count($theme->block_groups); $bg++)
        {
            if($theme->block_groups[$bg]->id == $item->code)
            {
                $block_group_blocks = $theme->block_groups[$bg]->blocks;
            }
        }

        // blocks available in the accordion
        $navibars->add_tab_content(
            '<div id="blocks_available_wrapper" style="float: left; width: 49%; ">
                <div id="blocks_available_accordion">
                    <h3><i class="fa fa-fw fa-cube"></i> '.t(437, 'Block').'</h3>
                    <div>
                    '.implode(
                        "\n",
                        array_map(
                            function($b) use ($allowed_types)
                            {
                                global $theme;

                                $classes = 'block_group_block ui-state-default';
                                if(!empty($allowed_types) && !in_array($b->type, $allowed_types))
                                {
                                    $classes .= ' ui-state-disabled hidden';
                                }

                                $html = '<div class="'.$classes.'" data-block-id="'.$b->id.'" data-block-type="block">'.
                                            '<div class="actions">
                                                <a href="?fid=blocks&act=edit&id='.$b->id.'"><img src="'.NAVIGATE_URL.'/img/icons/silk/pencil.png" /></a>
                                                <a href="#" onclick="navigate_blocks_selection_remove(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" /></a>
                                            </div>'.
                                            '<div class="title">'.$b->title.'</div>'.
                                            '<div class="subcomment"><span style="float: right;">ID '.$b->id.'</span><img src="img/icons/silk/brick.png" /> '.$theme->t($b->type).'</div>'.
                                        '</div>';
                                return $html;
                            },
                            $block_elements
                        )
                    ).'
                        <div class="navigate-block_group-accordion-info-link hidden"><i class="fa fa-eye-slash"></i>&nbsp;&nbsp;<a href="#">'.t(646, "Show all unselectable blocks").'</a></div>
                    </div>
                    <h3><i class="fa fa-fw fa-cubes"></i> '.t(543, 'Block type').'</h3>
                    <div>
                    '.implode(
                        "\n",
                        array_map(
                            function($b) use ($allowed_types)
                            {
                                $classes = 'block_group_block ui-state-default';
                                if(!empty($allowed_types) && !in_array($b['id'], $allowed_types))
                                    $classes .= ' ui-state-disabled';

                                $html = '<div class="'.$classes.'" data-block-id="'.$b['id'].'" data-block-type="block_type">'.
                                    '<div class="actions">
                                        <a href="#" data-block-group="'.$b['block_group'].'" data-block-type-code="'.$b['code'].'" data-block-type-title="(span)" onclick="navigate_blocks_block_type_title(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/text_horizontalrule.png" /><span class="hidden">'.$b['block_type_title'].'</span></a>
                                        <a href="#" onclick="navigate_blocks_selection_remove(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" /></a>
                                    </div>'.
                                    '<div class="title">'.$b['title'].'</div>'.
                                    '<div class="subcomment">
                                        <span style="float: right;">'.$b['count'].' '.($b['count']==1? t(437, "Block") : t(23, "Blocks")).'</span>
                                        <img src="img/icons/silk/brick_link.png" /> ID '.$b['id'].'</div>'.
                                    '</div>';
                                return $html;
                            },
                            $block_types
                        )
                    ).'
                    </div>
                    <h3><i class="fa fa-fw fa-plus-square-o"></i> '.t(556, 'Block from group').' ['.$theme->t($item->code).']</h3>
                    <div>                    
                        '.implode(
                            "\n",
                            array_map(
                                function($b) use ($item)
                                {
                                    global $theme;

                                    $html = '<div class="block_group_block ui-state-default" data-block-id="'.$b->id.'" data-block-type="block_group_block" title="'.$theme->t(@$b->description).'">'.
                                                '<div class="actions">
                                                    '.(empty($b->properties)? '':'<a href="#" data-block-group="'.$item->code.'" data-block-group-block="'.$b->id.'" data-block-group-action="settings" onclick="navigate_blocks_group_block_settings(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cog.png" /></a>').'
                                                    <a href="#" onclick="navigate_blocks_selection_remove(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" /></a>
                                                </div>'.
                                                '<div class="title">'.$theme->t($b->title).'</div>'.
                                                '<div class="subcomment">
                                                    <span style="float: right;">ID '.$b->id.'</span>
                                                    <img src="img/icons/silk/bricks.png" />'.
                                                '</div>'.
                                            '</div>';
                                    return $html;
                                },
                                $block_group_blocks
                            )
                        ).'
                    </div>
                    <h3><i class="fa fa-fw fa-puzzle-piece"></i> '.t(327, 'Extensions').'</h3>
                    <div>
                        '.implode(
                            "\n",
                            array_map(
                                function($b) use ($allowed_types, $item)
                                {
                                    $classes = 'block_group_block ui-state-default';
                                    $extension = new extension();
                                    $extension->load($b->_extension);

                                    $html = '<div class="'.$classes.'" data-block-id="'.$b->id.'" data-block-type="extension" data-block-extension="'.$b->_extension.'">'.
                                        '<div class="actions">
                                            '.(empty($b->properties)? '':'<a href="#" data-block-group="'.$item->code.'" data-block-group-block="'.$b->id.'" data-block-group-action="settings" onclick="navigate_block_group_extension_block_settings(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cog.png" /></a>').'
                                            <a href="#" onclick="navigate_blocks_selection_remove(this);"><img src="'.NAVIGATE_URL.'/img/icons/silk/cancel.png" /></a>
                                        </div>'.
                                        '<div class="title">'.$extension->t($b->title).'</div>'.
                                        '<div class="subcomment"><span style="float: right;">ID '.$b->id.'</span><img src="img/icons/silk/plugin.png" /> '.$extension->title.'</div>'.
                                        '</div>';
                                    return $html;
                                },
                                $extensions_blocks
                            )
                        ).'
                    </div>
                </div>
                <div class="subcomment">
                    <img src="img/icons/silk/information.png" align="absmiddle" /> '.t(638, "Disabled blocks are not compatible with the current block group type").'
                </div>
            </div>'
        );


        $block_group_block_types_form = "";
        foreach($website->languages_list as $lang)
        {
            $block_group_block_types_form .= ' 
                <div data-lang="'.$lang.'" class="navigate-form-row">
                    <label style="width: 48px; "><span title="'.language::name_by_code($lang).'" class="navigate-form-row-language-info"><img align="absmiddle" src="img/icons/silk/comment.png">'.$lang.'</span></label>
                    <input type="text" style=" width: 340px;" name="block_type_title_value['.$lang.']" value="">
                </div>
            ';
        }
        $navibars->add_tab_content('
            <div id="navigate-block-groups-block-type-title" class="hidden">
                '.$block_group_block_types_form.'
                <div class="subcomment" style="margin-left: 0;"><img src="img/icons/silk/information.png" /> '.t(641, "It will only be shown if the template supports it").'</div>
            </div>
        ');

        $layout->add_script('                       
            function navigate_blocks_block_type_title(el)
            {
                var title = $(el).find("span").text();
                
                try 
                {
                   title = jQuery.parseJSON(title);
                } 
                catch(e) 
                {
                    // not json; do nothing                    
                }                    
                
                $("#navigate-block-groups-block-type-title").find("input[type=text]").each(function()
                {                     
                    if(typeof(title)=="object")
                        $(this).val(title[$(this).parent().data("lang")]);
                    else
                        $(this).val(title);
                });
                
                $("#navigate-block-groups-block-type-title").removeClass("hidden");
                $("#navigate-block-groups-block-type-title").dialog({
                    title: navigate_t(67, "Title"),
                    modal: true,
                    width: 428,
                    buttons: [
                        {
                            text: navigate_t(190, "Ok"),
                            icon: "ui-icon-check",
                            click: function()
                            {
                                var new_value = {};
                                
                                $("#navigate-block-groups-block-type-title")
                                    .find(\'input[type="text"]\').each(
                                        function()
                                        {
                                            new_value[$(this).parent().data("lang")] = $(this).val(); 
                                        }
                                    );
                                
                                $(el).find("span").text(JSON.stringify(new_value));
                                                            
                                blocks_selection_update();
                                
                                $( this ).dialog( "close" );
                            }
                        },
                        {
                            text: navigate_t(58, "Cancel"),
                            icon: "ui-icon-close",
                            click: function()
                            {
                                $( this ).dialog( "close" );
                            }
                        }
                    ]
                });
            }
        ');
    }

    $navibars->add_tab(t(457, "Information"));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->hidden('id', $item->id));
    $navibars->add_tab_content($naviforms->csrf_token());

    $navibars->add_tab_content_row(
        array(
            '<label>ID</label>',
            '<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>'
        )
    );

    $navibars->add_tab_content_row(array(
        '<label>'.t(67, 'Title').'</label>',
        $naviforms->textfield('title', $item->title)
    ));

    $blgroups = array();
    for($blg=0; $blg < count($theme->block_groups); $blg++)
    {
        $blgroups[$theme->block_groups[$blg]->id] = '';
        if(!empty($theme->block_groups[$blg]->description))
        {
            $blgroups[$theme->block_groups[$blg]->id] = $theme->t($theme->block_groups[$blg]->description);
        }
    }

    if(!in_array($item->code, $blgroups))
    {
        $blgroups[$item->code] = $item->code;
    }

    $navibars->add_tab_content_row(array(
        '<label>'.t(237, 'Code').'</label>',
        $naviforms->selectfield(
            'code',
            array_keys($blgroups),
            array_keys($blgroups),
            $item->code,
            NULL,
            NULL,
            array_values($blgroups),
            "",
            true,
            true
        )
    ));

    // DEPRECATED field, will be removed. Please use the Notes feature
    if(!empty($item->notes))
    {
        $navibars->add_tab_content_row(array(
            '<label>'.t(168, 'Notes').'</label>',
            $naviforms->textarea('notes', $item->notes)
        ));
    }

    if(!empty($item->id))
    {
        $layout->navigate_notes_dialog('block_group', $item->id);
    }

    $layout->add_script('
	    $.ajax({
	        type: "GET",
	        dataType: "script",
	        cache: true,
	        url: "lib/packages/blocks/blocks.js?r='.$current_version->revision.'",
	        complete: function()
	        {
                block_groups_onload();
	        }
	    });
	');

    return $navibars->generate();
}

function block_group_block_options($block_group, $code, $block_uid, $status)
{
    global $layout;
    global $website;
    global $theme;

    $block = block::block_group_block($block_group, $code);
    $properties = $block->properties;

    if(empty($properties))
    {
        return;
    }

    $layout = null;
    $layout = new layout('navigate');

    if($status!==null)
    {
        if($status)
        {
            $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
        }
        else
        {
            $layout->navigate_notification(t(56, "Unexpected error"), true, true);
        }
    }

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(556, 'Block from group').' ['.$theme->t($block_group).']');

    $layout->navigate_media_browser();	// we can use media browser in this function

    $navibars->add_actions(
        array(
            '<a href="#" onclick="javascript: navigate_media_browser();">
                <img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').
            '</a>'
        )
    );

    $navibars->add_actions(
        array(
            '<a href="#" onclick="navigate_tabform_submit(0);">
                <img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').
            '</a>'
        )
    );

    $navibars->form();

    $navibars->add_tab(t(200, 'Options'));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->csrf_token());

    // show a language selector (only if it's a multi language website)
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

    $properties_values = property::load_properties($code, $block_group, 'block_group_block', $code, $block_uid);

    foreach($properties as $option)
    {
        $property = new property();
        $property_value = '';

        foreach($properties_values as $pv)
        {
            if($pv->id == $option->id)
            {
                $property_value = $pv->value;
            }
        }

        $property->load_from_object($option, $property_value, $theme);

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

        $navibars->add_tab_content(navigate_property_layout_field($property));
    }

    $layout->add_content('<div id="navigate-content" class="navigate-content ui-corner-all">'.$navibars->generate().'</div>');
    navigate_property_layout_scripts();
    $layout->navigate_additional_scripts();
    $layout->add_script('
        $("html").css("background", "transparent");
    ');

    $out = $layout->generate();

    return $out;
}

function block_group_extension_block_options($block_group, $block_extension, $block_id, $block_uid, $status)
{
    global $layout;
    global $website;

    if(empty($block_extension))
    {
        throw new Exception("Unknown extension: {".$block_extension."} for block with uid:".$block_uid);
    }

    $extension = new extension();
    $extension->load($block_extension);

    $block = block::extension_block($extension, $block_id);
    $properties = $block->properties;

    if(empty($properties))
    {
        return;
    }

    $layout = null;
    $layout = new layout('navigate');

    if($status!==null)
    {
        if($status)
        {
            $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
        }
        else
        {
            $layout->navigate_notification(t(56, "Unexpected error"), true, true);
        }
    }

    $navibars = new navibars();
    $naviforms = new naviforms();

    $navibars->title(t(437, 'Block').' ['.$block_extension.' / '.$block_id.']');

    $layout->navigate_media_browser();	// we can use media browser in this function

    $navibars->add_actions(
        array(
            '<a href="#" onclick="javascript: navigate_media_browser();">
                <img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').
            '</a>'
        )
    );

    $navibars->add_actions(
        array(
            '<a href="#" onclick="navigate_tabform_submit(0);">
                <img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').
            '</a>'
        )
    );

    $navibars->form();

    $navibars->add_tab(t(200, 'Options'));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->csrf_token());

    // show a language selector (only if it's a multi language website)
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

    $properties_values = property::load_properties(NULL, $block_id, "extension_block", $block_group, $block_uid);

    foreach($properties as $option)
    {
        $property = new property();
        $property_value = '';

        foreach($properties_values as $pv)
        {
            if($pv->id == $option->id)
            {
                $property_value = $pv->value;
            }
        }

        $property->load_from_object($option, $property_value, $extension);

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

        $navibars->add_tab_content(
            navigate_property_layout_field($property, $extension)
        );
    }

    $layout->add_content('<div id="navigate-content" class="navigate-content ui-corner-all">'.$navibars->generate().'</div>');
    navigate_property_layout_scripts();
    $layout->navigate_additional_scripts();
    $layout->add_script('
        $("html").css("background", "transparent");
    ');

    $out = $layout->generate();

    return $out;
}

?>