<?php
nvweb_webget_load('properties');

function nvweb_menu($vars=array())
{
	global $website;
	global $DB;
	global $structure;
	global $current;

	$out = '';

	nvweb_menu_load_dictionary();
	nvweb_menu_load_routes();	
	nvweb_menu_load_structure();
	nvweb_menu_load_actions();
	
	$parent = intval(@$vars['parent']) + 0;
	$from = intval(@$vars['from']) + 0;
	$of	= intval(@$vars['of']) + 0;

    if(isset($vars['parent']) && !is_numeric($vars['parent']))
    {
        // assume parent attribute contains a property_id which has the category value
        $parent_property = nvweb_properties(array(
            'property' => $vars['parent']
        ));
        if(!empty($parent_property))
        {
            $parent = $parent_property;
        }
    }

	if($of > 0)
	{
		// get options of the parent x in the order of the structure
		// example:
		//	Home [5]	Products [6]	Contact [7]
		//					|
		//					-- Computers [8]	Mobile Phones [9]
		//							|
		//							-- Apple [10]	Dell [11]
		//
		//	we want the categories under Products [6]: "Computers" [8] and "Mobile Phones" [9]
		//	of: 2 (second item in the main structure)
		//  <nv object="nvweb" name="menu" of="2" />
		$parent = $structure['cat-0'][intval($of)-1]->id;
	}

	$exclude = array();
	if(isset($vars['exclude']))
    {
        $exclude = $vars['exclude'];
        if(strpos($exclude, ",")!==false)
        {
            $exclude = explode(",", $vars['exclude']);
        }
        else
        {
            $exclude = array(intval($exclude));
        }
    }

    if(empty($current['hierarchy']))	// calculate
    {
        $inverse_hierarchy = array();
        // discover the parent from which get the menu
        if(!empty($current['category']))
        {
            $inverse_hierarchy[] = $current['category'];
            $last = $current['category'];
        }
        else
        {
            $inverse_hierarchy[] = $current['object']->category;
            $last = $current['object']->category;
        }

        // get category parents until root (to know how many levels count from)
        while($last > 0)
        {
            $last = $DB->query_single('parent', 'nv_structure', ' id = '.intval($last));
            $inverse_hierarchy[] = $last;
        }
        $current['hierarchy'] = array_reverse($inverse_hierarchy);
    }

	if($from > 0)
	{
		// get a certain level of the menu based on the path to current item category with offset 
		// example:
		//	Home [5]	Products [6]	Contact [7]
		//					|
		//					-- Computers [8]	Mobile Phones [9]
		//							|
		//							-- Apple [10]	Dell [11]
		//
		//	current item is a Dell computer (category = 11)
		//  we want the menu from level 1
		//	from: 1	--> 8, 9		
		$parent = $current['hierarchy'][$from];

		if(is_null($parent))
        {
            // the requested level of menu does not exist under the current category
            return '';
        }
	}

	$option = -1;	
	if(isset($vars['option']))
    {
        $option = intval($vars['option']);
    }

	if(!isset($vars['active_class']))
    {
        $vars['active_class'] = 'menu_option_active';
    }

    if($vars['mode']=='next' || $vars['mode']=='previous')
    {
        $out = nvweb_menu_render_arrow($vars);
    }
    else
    {
        $params = array(
            'mode' => value_or_default($vars['mode'], 'ul'),
            'levels' => value_or_default($vars['levels'], 0),
            'level' => value_or_default($vars['level'], 0),
            'parent' => $parent,
            'option' => value_or_default($option, -1),
            'class' => value_or_default($vars['class'], ""),
            'active_class' => value_or_default($vars['active_class'], "menu_option_active"),
            'select_tag_name' => value_or_default($vars['select_tag_name'], uniqid("nvmenu-")),
            'select_submenu_separator' => value_or_default($vars['select_submenu_separator'], '&ndash;&nbsp;'),
            'exclude' => $exclude
        );

        $out = nvweb_menu_generate($params);

        if($vars['mode'] == 'select')
        {
            if(!isset($vars['auto_jump']) && !$vars['auto_jump']=='false')
            {
                nvweb_after_body('js', '
                    // jQuery required
                    $("select.menu_level_0").off("change").on("change", function()
                    {
                        var option = $(this).find("option[value=" + $(this).val() + "]");
                        if($(option).attr("target") == "_blank")
                        {
                            window.open($(option).attr("href"));
                        }
                        else
                        {
                            if($(option).attr("href")=="#")
                            {
                                window.location.replace($(option).attr("href") + "sid_" + $(option).attr("value"));
                            }
                            else
                            {
                                window.location.replace($(option).attr("href"));
                            }
                        }
                    });
                ');
            }
        }
    }

	return $out;
}

function nvweb_menu_generate($params)
{
	global $structure;
	global $current;

	$out = "";
	$params_default = array(
	    'mode' => 'ul',
        'levels' => 0,
        'level' => 0,
        'parent' => 0,
        'option' => -1,
        'class' => "",
        'active_class' => "menu_option_active",
        'select_tag_name' => uniqid("nvmenu-"),
        'select_submenu_separator' => '&ndash;&nbsp;',
        'exclude' => array()
    );

	$params = array_merge($params_default, $params);

	if($params['level'] >= $params['levels'] && $params['levels'] > 0)
    {
        return '';
    }
	
	nvweb_menu_load_structure($params['parent']);

	if(!empty($structure['cat-'.$params['parent']]))
	{	
		switch($params['mode'])
		{
            case 'category_title':
			case 'current_title':
                if($current['type']=='structure')
                {
                    $out = $structure['dictionary'][$current['category']];
                }
                else if($current['type']=='item')
                {
                    $out = $structure['dictionary'][$current['object']->category];
                }

                $out = core_special_chars($out);
				break;

            case 'category_link':
            case 'category_url':
                if($current['type']=='structure')
                {
                    $out = nvweb_source_url('structure', $current['category']);
                }
                else if($current['type']=='item')
                {
                    $out = nvweb_source_url('structure', $current['object']->category);
                }
                else
                {
                    $out = '#';
                }
                break;
			
			case 'a':
                $out = array();
				$out[] = '<div class="menu_level_'.$params['level'].' '.$params['class'].'">';
				for($m=0; $m < count($structure['cat-'.$params['parent']]); $m++)
				{
					if(!nvweb_object_enabled($structure['cat-'.$params['parent']][$m]))
                    {
                        continue;
                    }

					if($structure['cat-'.$params['parent']][$m]->visible == 0)
                    {
                        continue;
                    }

					$mid = $structure['cat-'.$params['parent']][$m]->id;

                    // hide menu items without a title
                    if(empty($structure['dictionary'][$mid]))
                    {
                        continue;
                    }

					$aclass = '';
                    if(in_array($mid, $current['hierarchy']))
                    {
                        $aclass = ' class="'.$params['active_class'].'"';
                    }

                    $menu_text = core_special_chars($structure['dictionary'][$mid]);

					$out[] = '<a'.$aclass.' '.nvweb_menu_action($mid).'>'.$menu_text.'</a>';
					if($params['option']==$m)
                    {
                        return array_pop($out);
                    }

                    $params_sub = array_merge(
                        $params,
                        array(
                            'parent' => $mid,
                            'level' => $params['level']+1
                        )
                    );

                    $out[] = nvweb_menu_generate($params_sub);
				}
				$out[] = '</div>';		
				$out = implode("\n", $out);	
				break;

            case 'select':
                $out = array();
                $out[] = '<select id="'.$params['select_tag_name'].'" name="'.$params['select_tag_name'].'" class="menu_level_'.$params['level'].' '.$params['class'].'">';
                for($m=0; $m < count($structure['cat-'.$params['parent']]); $m++)
                {
                    if( !nvweb_object_enabled($structure['cat-'.$params['parent']][$m]) ||
                        $structure['cat-'.$params['parent']][$m]->visible == 0
                    )
                    {
                        continue;
                    }

                    if(isset($structure['cat-'.$params['parent']][$m]))
                    {
                        $mid = $structure['cat-'.$params['parent']][$m]->id;
                    }
                    else
                    {
                        $mid = null;
                    }

                    // hide menu items without a title
                    if(empty($structure['dictionary'][$mid]))
                    {
                        continue;
                    }

                    if(in_array($mid, $params['exclude']))
                    {
                        continue;
                    }

                    $aclass = '';
                    if(in_array($mid, $current['hierarchy']))
                    {
                        $aclass = ' class="'.$params['active_class'].'" selected="selected"';
                    }

                    $target = '';
                    $act = nvweb_menu_action($mid, NULL, false, false);
                    if(strpos($act, 'target="_blank"')!==false)
                    {
                        $target = 'target="_blank"';
                    }
                    if(strpos($act, 'onclick')!==false)
                    {
                        $act = '#';
                    }

                    $act = str_replace('target="_blank"', '', $act);
                    $act = str_replace('data-sid', 'data_sid', $act);
                    $act = str_replace('href="', '', $act);
                    $act = str_replace('"', '', $act);
                    $act = trim($act);

                    $menu_text = $structure['dictionary'][$mid];
                    $menu_text = core_special_chars($menu_text);

                    $out[] = '<option'.$aclass.' value="'.$mid.'" href="'.$act.'" '.$target.'>'
                             .$menu_text
                             .'</option>';

                    if($params['option']==$m)
                    {
                        return array_pop($out);
                    }

                    $params_sub = array_merge(
                        $params,
                        array(
                            'parent' => $mid,
                            'level' => $params['level']+1
                        )
                    );

                    $submenu = nvweb_menu_generate($params_sub);
                    $submenu = strip_tags($submenu, '<option>');

                    $submenu_separator = value_or_default($params['select_submenu_separator'], "&ndash;&nbsp;");

                    $parts = explode('>', $submenu);
                    for($p=0; $p < count($parts); $p++)
                    {
                        if(strpos($parts[$p], '</option')!==false)
                        {
                            $parts[$p] = $submenu_separator.$parts[$p];
                        }
                    }
                    $submenu = implode('>', $parts);

                    $out[] = $submenu;
                }
                $out[] = '</select>';
                $out = implode("\n", $out);
                break;
	
			default:
			case 'ul':
                $ul_items = 0;
                $out = array();
				$out[] = '<ul class="menu_level_'.$params['level'].' '.$params['class'].'">';

				for($m=0; $m < count($structure['cat-'.$params['parent']]); $m++)
				{
					if( !nvweb_object_enabled($structure['cat-'.$params['parent']][$m])   ||
                        $structure['cat-'.$params['parent']][$m]->visible == 0
                    )
                    {
                        continue;
                    }

					$mid = $structure['cat-'.$params['parent']][$m]->id;

                    // hide menu items without a title
                    if(empty($structure['dictionary'][$mid]))
                    {
                        continue;
                    }

                    if(in_array($mid, $params['exclude']))
                    {
                        continue;
                    }

                    $aclass = '';
					if(in_array($mid, $current['hierarchy']))
                    {
                        $aclass = ' class="'.$params['active_class'].'"';
                    }

                    $menu_text = $structure['dictionary'][$mid];
                    $menu_text = core_special_chars($menu_text);

					$out[] = '<li'.$aclass.' data-structure-id="'.$mid.'">';
					$out[] = '<a'.$aclass.' '.nvweb_menu_action($mid).'>'.$menu_text.'</a>';
					if($params['option']==$m)
                    {
                        return array_pop($out);
                    }

					$params_sub = array_merge(
					    $params,
                        array(
                            'parent' => $mid,
                            'level' => $params['level']+1
                        )
                    );
					$out[] = nvweb_menu_generate($params_sub);
					$out[] = '</li>';
                    $ul_items++;
				}
                $out[] = '</ul>';

                if($ul_items==0) // no option found, remove the last two lines (<ul> and </ul>)
                {
                    array_pop($out);
                    array_pop($out);
                }

				$out = implode("\n", $out);			
				break;
		}
	}

	return $out;
	
}

function nvweb_menu_action($id, $force_type=NULL, $use_javascript=true, $include_datasid=true)
{
	global $structure; 
	global $current;
	
	$type = $structure['actions'][$id]['action-type'];
		
	if(!empty($force_type))
    {
        $type = $force_type;
    }
	
	switch($type)
	{
		case 'url':
			$url = $structure['routes'][$id];
			if(empty($url))
            {
                if($use_javascript)
                {
                    $url = 'javascript: return false;';
                }
                else
                {
                    $url = '#';
                }
            }
            else
            {
                $url = nvweb_prepare_link($url);
            }

			$action = ' href="'.$url.'" ';
            if($include_datasid)
            {
                $action.= ' data-sid="'.$id.'" ';
            }
			break;
			
		case 'jump-branch':
			// we force only one jump to avoid infinite loops (caused by user mistake)
			$action = nvweb_menu_action($structure['actions'][$id]['action-jump-branch'], 'url');
			break;
			
		case 'jump-item':
			$url = nvweb_source_url('item', $structure['actions'][$id]['action-jump-item'], $current['lang']);
			if(empty($url))
            {
                if($use_javascript)
                {
                    $url = 'javascript: return false;';
                }
                else
                {
                    $url = '#';
                }
            }
			else
            {
                $url = nvweb_prepare_link($url);
            }
			$action = ' href="'.$url.'" ';
            if($include_datasid)
                $action.= ' data-sid="'.$id.'" ';
			break;
			
		case 'do-nothing':
			$action = ' href="#" onclick="javascript: return false;" ';
            if($include_datasid)
            {
                $action.= ' data-sid="'.$id.'" ';
            }
			break;
			
		default:
			// Navigate CMS < 1.6.5 compatibility [deprecated]
            // will be removed by 3.0
			$url = $structure['routes'][$id];
			if(substr($url, 0, 7)=='http://' || substr($url, 0, 7)=='https://')
            {
                $action = ' href="'.$url.'" target="_blank" ';
                if($include_datasid)
                {
                    $action.= ' data-sid="'.$id.'" ';
                }
				return $action; // ;)
            }
			else if(empty($url))
            {
                $action = ' href="#" onclick="return false;" ';
                if($include_datasid)
                {
                    $action.= ' data-sid="'.$id.'" ';
                }
				return $action;
            }
			else
            {
                $action = ' href="'.NVWEB_ABSOLUTE.$url.'"';
                if($include_datasid)
                {
                    $action.= ' data-sid="'.$id.'" ';
                }
				return $action;
            }
			break;	
	}
	
	if($structure['actions'][$id]['action-new-window']=='1' && $type!='do-nothing')
    {
        $action .= ' target="_blank"';
    }
	
	return $action;	
}

function nvweb_menu_load_dictionary()
{
	global $DB;	
	global $structure;
	global $current;
	global $website;
			
	if(empty($structure['dictionary']))
	{
		$structure['dictionary'] = array();

		$DB->query(
		    'SELECT node_id, text
                  FROM nv_webdictionary 
                 WHERE node_type = "structure"
                   AND subtype = "title" 
                   AND lang = :lang
                   AND website = :wid',
            'object',
            array(
                ':wid' => $website->id,
                ':lang' => $current['lang']
            )
        );
					
		$data = $DB->result();
		
		if(!is_array($data))
        {
            $data = array();
        }

		foreach($data as $item)
		{
			$structure['dictionary'][$item->node_id] = $item->text;
		}
	}
}

function nvweb_menu_load_routes()
{
	global $DB;	
	global $structure;
	global $current;
	global $website;
			
	if(empty($structure['routes']))
	{
		$structure['routes'] = array();

		$DB->query(
		    'SELECT object_id, path
                  FROM nv_paths 
                 WHERE type = "structure"
                   AND lang = :lang
                   AND website = :wid',
            'object',
            array(
                ':wid' => $website->id,
                ':lang' => $current['lang']
            )
        );
					
		$data = $DB->result();
		
		if(!is_array($data))
        {
            $data = array();
        }

		foreach($data as $item)
		{
			$structure['routes'][$item->object_id] = $item->path;
		}			
	}
}

function nvweb_menu_load_actions()
{
	global $DB;	
	global $structure;
	global $current;
	global $website;
			
	if(empty($structure['actions']))
	{
		$structure['actions'] = array();

		$DB->query('
            SELECT node_id, subtype, text
			  FROM nv_webdictionary 
			 WHERE node_type = "structure"
			   AND lang = :lang
			   AND subtype IN("action-type", "action-jump-item", "action-jump-branch", "action-new-window")
			   AND website = :wid',
            'object',
            array(
                ':wid' => $website->id,
                ':lang' => $current['lang']
            )
        );
					
		$data = $DB->result();
		
		if(!is_array($data))
        {
            $data = array();
        }

		foreach($data as $row)
		{
			$structure['actions'][$row->node_id][$row->subtype] = $row->text;
		}
	}
}

function nvweb_menu_load_structure($parent=0)
{
	global $DB;	
	global $structure;
	global $website;

	if(!isset($structure['cat-'.$parent]))
	{
		$structure['cat-'.$parent] = array();
		
		$DB->query(
		    'SELECT * 
                  FROM nv_structure
                 WHERE parent = '.intval($parent).' 
                   AND website = '.intval($website->id).' 
                  ORDER BY position ASC'
        );
				  
		$structure['cat-'.$parent] = $DB->result();

        // parse some result values
        foreach($structure['cat-'.$parent] as $key => $value)
        {
            $value->groups = str_replace('g', '', $value->groups);
            $value->groups = array_filter(explode(',', $value->groups));
            $structure[$key] = clone $value;
        }
	}
}

function nvweb_menu_get_children($categories=array(), $sublevels=NULL)
{
	global $structure;

	// get all leafs from all categories that are children of the selected ones
	$categories_count = count($categories);

    $depth = array();

	for($c=0; $c < $categories_count; $c++)
	{
		$categories[$c] = trim($categories[$c]);
		if(empty($categories[$c]) && $categories[$c]!='0') 
        {
            continue;
        }
        
        if(!isset($depth[$categories[$c]]))
        {
            $depth[$categories[$c]] = 0;
        }

		nvweb_menu_load_structure($categories[$c]);

		for($s=0; $s < count($structure['cat-'.$categories[$c]]); $s++)
		{
            $depth[$structure['cat-'.$categories[$c]][$s]->id] = $depth[$categories[$c]] + 1;

            // the current category is beyond the allowed number of sublevels on hierarchy?
            if(isset($sublevels) && $depth[$structure['cat-'.$categories[$c]][$s]->id] > $sublevels)
            {
                continue;
            }

			array_push($categories, $structure['cat-'.$categories[$c]][$s]->id);
		}

		$categories = array_unique($categories); // remove duplicates
		$categories_count = count($categories);	 // recount elements
	}

	$categories = array_filter($categories);

	return $categories;		
}

function nvweb_menu_render_arrow($vars=array())
{
    global $DB;
    global $structure;
    global $current;
    global $website;

    $out = '';
    $char = $vars['character'];
    $link = '';
    $title = '';
    $previous = null;
    $next = null;
    $parent = null;

    // look for the category before and after the current one

    if($current['type']=='structure')
    {
        $parent = $current['object']->parent;
    }
    else if($current['category'] > 0)
    {
        $parent = $current['hierarchy'][count($current['category'])-1];
    }

    // if we have found the parent
    // AND
    // if the "from" option is not empty AND the number of levels until the current category is greater than "from"
    if( $parent >= 0 &&
        (!empty($vars['from']) && count($current['hierarchy']) >= $vars['from']))
    {
        nvweb_menu_load_structure($parent);

        for($c=0; $c < count($structure['cat-'.$parent]); $c++)
        {
            if($structure['cat-'.$parent][$c]->id == $current['category'])
            {
                if(isset($structure['cat-'.$parent][$c-1]))
                {
                    $previous = $structure['cat-'.$parent][$c-1]->id;
                }

                if(isset($structure['cat-'.$parent][$c+1]))
                {
                    $next = $structure['cat-'.$parent][$c+1]->id;
                }

                break;
            }
        }

        // TO DO: look for previous and next categories from the parent's brothers
        /*
        if(empty($previous))
        {
            // we have not found a PREVIOUS structure entry in the same level of the current category
            // we may look for the last child of the parent brother... example
            /*
             *  ROOT
             *      1
             *      2
             *        2.1
             *          2.1.1
             *          2.1.2
             *        2.2
             *          2.2.1 <- current category
             *          2.2.2
             *        2.3
             *          2.3.1
             *      3
             *
             *  in this example, the previous category of 2.2.1 is 2.1.2
             *  if the current category is 2.2.2, the next one will be the children of the next parent, so, 2.3.1
             */
        /*
        }
        /*
        if(empty($next))
        {

        }
        */

        if($vars['mode']=='next' && !empty($next))
        {
            if(empty($char))
            {
                $char = '&gt;';
            }

            $link = $structure['routes'][$next];
            $title = $structure['dictionary'][$next];
        }
        else if($vars['mode']=='previous' && !empty($previous))
        {
            if(empty($char))
            {
                $char = '&lt;';
            }

            $link = $structure['routes'][$previous];
            $title = $structure['dictionary'][$previous];
        }

        if(!empty($link))
        {
            $out = '<a href="'.$link.'" title="'.$title.'">'.$char.'</a>';
        }
    }

    return $out;
}

?>