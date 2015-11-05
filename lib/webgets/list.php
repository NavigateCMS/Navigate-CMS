<?php
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');
require_once(NAVIGATE_PATH.'/lib/webgets/properties.php');
require_once(NAVIGATE_PATH.'/lib/webgets/content.php');
require_once(NAVIGATE_PATH.'/lib/webgets/gallery.php');
require_once(NAVIGATE_PATH.'/lib/webgets/votes.php');
require_once(NAVIGATE_PATH.'/lib/webgets/blocks.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/feeds/feed_parser.class.php');

function nvweb_list($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $cache;
	global $structure;
	global $webgets;
    global $theme;
    global $webuser;

	$out = array();

	$webget = 'list';

    $categories = array();

    $exclude = '';

    if($current['type']=='item')
	    $categories = array($current['object']->category);
    else
        $categories = array($current['object']->id);

	if(isset($vars['categories']))
	{
        if($vars['categories']=='all')
        {
            $categories = array(0);
            $vars['children'] = 'true';
        }
        else if($vars['categories']=='parent')
        {
            $parent = $DB->query_single('parent', 'nv_structure', 'id = '.intval($categories[0]));
            $categories = array($parent);
        }
        else if(!is_numeric($vars['categories']))
        {
            // if "categories" attribute has a comma, then we suppose it is a list of comma separated values
            // if not, then maybe we want to get the categories from a specific property of the current page
            if(strpos($vars['categories'], ',')===false)
            {
                $categories = nvweb_properties(array(
                    'property'	=> 	$vars['categories']
                ));
            }

            if(empty($categories) && (@$vars['nvlist_parent_vars']['source'] == 'block_group'))
            {
                $categories = nvweb_properties(array(
                    'mode'	=>	'block_group_block',
                    'property' => $vars['categories']
                ));
            }

            if(!is_array($categories))
            {
                $categories = explode(',', $categories);
                $categories = array_filter($categories); // remove empty elements
            }
        }
        else
        {
            $categories = explode(',', $vars['categories']);
            $categories = array_filter($categories); // remove empty elements
        }
	}


	if($vars['children']=='true')
        $categories = nvweb_menu_get_children($categories);

    // if we have categories="x" children="true" [to get the children of a category, but not himself]
    if($vars['children']=='only')
    {
        $children = nvweb_menu_get_children($categories);
        for($c=0; $c < count($categories); $c++)
            array_shift($children);
        $categories = $children;
    }

    if(!empty($vars['children']) && intval($vars['children']) > 0)
    {
        $children = nvweb_menu_get_children($categories, intval($vars['children']));

        for($c=0; $c < count($categories); $c++)
            array_shift($children);
        $categories = $children;
    }

	if(empty($vars['items']) || $vars['items']=='0')
		$vars['items'] = 500; //2147483647; // maximum integer
    // TODO: try to optimize nvlist generation to use less memory and increase the maximum number of items
    // NOTE: anyway, having >500 items on a page without a paginator is probably a bad idea... disagree? Contact Navigate CMS team!

    if(!empty($vars['exclude']))
    {
        $exclude = str_replace('current', $current['object']->id, $vars['exclude']);
        $exclude = explode(',', $exclude);
        $exclude = array_filter($exclude);

        if(!empty($exclude))
        {
            if($vars['source']=='structure' || $vars['source']=='category')
                $exclude = 'AND s.id NOT IN('.implode(',', $exclude).')';
            else // item
                $exclude = 'AND i.id NOT IN('.implode(',', $exclude).')';
        }
        else
            $exclude = '';
    }

	// retrieve entries

    // calculate the offset of the first element to retrieve
    // Warning: the paginator applies on all paginated lists on a page
	if(empty($_GET['page']))
        $_GET['page'] = 1;
	$offset = intval($_GET['page'] - 1) * $vars['items'];

    // this list does not use paginator, so offset must be always zero
    if(!isset($vars['paginator']) || $vars['paginator']=='false')
        $offset = 0;

	$permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);

    // public access / webuser based / webuser groups based
    $access = 2;
    $access_extra = '';
    if(!empty($current['webuser']))
    {
        $access = 1;
        if(!empty($webuser->groups))
        {
            $access_groups = array();
            foreach($webuser->groups as $wg)
            {
                if(empty($wg))
                    continue;
                $access_groups[] = 's.groups LIKE "%g'.$wg.'%"';
            }
            if(!empty($access_groups))
                $access_extra = ' OR (s.access = 3 AND ('.implode(' OR ', $access_groups).'))';
        }
    }

    // get order type: PARAMETER > NV TAG PROPERTY > DEFAULT (priority given in CMS)
    $order      = @$_REQUEST['order'];
    if(empty($order))
        $order  = @$vars['order'];
    if(empty($order))   // default order: latest
        $order = 'latest';

    $orderby = nvweb_list_get_orderby($order);

    $rs = NULL;

	if(($vars['source']=='structure' || $vars['source']=='category') && !empty($categories))
	{
        $orderby = str_replace('i.', 's.', $orderby);

        $visible = '';
        if($vars['filter']=='menu')
            $visible = ' AND s.visible = 1 ';

		$DB->query('
			SELECT SQL_CALC_FOUND_ROWS s.id, s.permission,
			            s.date_published, s.date_unpublish, s.date_published as pdate,
			            d.text as title, s.position as position
			  FROM nv_structure s, nv_webdictionary d
			 WHERE s.id IN('.implode(",", $categories).')
			   AND s.website = '.$website->id.'
			   AND s.permission <= '.$permission.'
			   AND (s.date_published = 0 OR s.date_published < '.core_time().')
			   AND (s.date_unpublish = 0 OR s.date_unpublish > '.core_time().')
			   AND (s.access = 0 OR s.access = '.$access.$access_extra.')
			   AND d.website = s.website
			   AND d.node_type = "structure"
			   AND d.subtype = "title"
			   AND d.node_id = s.id
			   AND d.lang = '.protect($current['lang']).'
			 '.$visible.'
			 '.$exclude.'
			 '.$orderby.'
			 LIMIT '.$vars['items'].'
			OFFSET '.$offset
		);

		$rs = $DB->result();
		$total = $DB->foundRows();
	}
    else if($vars['source']=='block')
    {
        list($rs, $total) = nvweb_blocks(array(
            'type' => $vars['type'],
            'number' => $vars['items'],
            'mode' => ($order=='random'? 'random' : 'ordered'), // blocks webget has two sorting methods only
            'zone' => 'object'
        ));
    }
    else if($vars['source']=='block_group')
    {
        $bg = new block_group();
        if(!empty($vars['type']))
            $bg->load_by_code($vars['type']);

        if(!empty($bg))
        {
            $rs = array();
            foreach($bg->blocks as $bgb)
            {
                unset($bgbo);

                if(is_numeric($bgb))
                {
                    $bgbo = new block();
                    $bgbo->load($bgb);

                    if(empty($bgbo) || empty($bgbo->type))
                        continue;

                    // check if we can display this block
                    if(nvweb_object_enabled($bgbo))
                    {
                        // check categories / exclusions
                        if(!empty($bgbo->categories))
                        {
                            $bgbo_cat_found = false;

                            foreach($categories as $list_cat)
                            {
                                if(in_array($list_cat, $bgbo->categories))
                                    $bgbo_cat_found = true;
                            }
                            if(!$bgbo_cat_found) // block categories don't match the current list categories, skip this block
                                continue;
                        }
                        if(!empty($bgbo->exclusions))
                        {
                            foreach($categories as $list_cat)
                            {
                                if(in_array($list_cat, $bgbo->exclusions))
                                    continue; // skip this block
                            }
                        }
                        $rs[] = $bgbo;
                    }
                }
                else
                {
                    // is block group block type?
                    $bgba = $theme->block_group_blocks($vars['type']);

                    if(!empty($bgba[$bgb]))
                    {
                        $bgbo = $bgba[$bgb];
                        $rs[] = $bgbo;
                    }
                    else // then is block type
                    {
                        list($bgbos, $foo) = nvweb_blocks(array(
                            'type' => $bgb,
                            'mode' => ($order=='random'? 'random' : 'ordered'),
                            'zone' => 'object'
                        ));

                        for($i=0; $i < count($bgbos); $i++)
                            $rs[] = $bgbos[$i];
                    }
                }
            }
            $total = count($rs);
        }
    }
    else if($vars['source']=='gallery')
    {
        if(!isset($vars['nvlist_parent_type']))
        {
            // get gallery of the current item
            if($current['type']=='item')
            {
                $galleries = $current['object']->galleries;
                if(!is_array($galleries))
                    $galleries = mb_unserialize($galleries);
                $rs = $galleries[0];
                $total = count($rs);
            }
            else if($current['type']=='structure')
            {
                // we need the first item assigned to the structure
                $access_extra_items = str_replace('s.', 'i.', $access_extra);

                // default source for retrieving items (embedded or not)
                $DB->query('
                    SELECT SQL_CALC_FOUND_ROWS i.id
                      FROM nv_items i, nv_structure s, nv_webdictionary d
                     WHERE i.category IN('.implode(",", $categories).')
                       AND i.website = '.$website->id.'
                       AND i.permission <= '.$permission.'
                       AND (i.date_published = 0 OR i.date_published < '.core_time().')
                       AND (i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
                       AND s.id = i.category
                       AND (s.date_published = 0 OR s.date_published < '.core_time().')
                       AND (s.date_unpublish = 0 OR s.date_unpublish > '.core_time().')
                       AND s.permission <= '.$permission.'
                       AND (s.access = 0 OR s.access = '.$access.$access_extra.')
                       AND (i.access = 0 OR i.access = '.$access.$access_extra_items.')
                       AND d.website = i.website
                       AND d.node_type = "item"
                       AND d.subtype = "title"
                       AND d.node_id = i.id
                       AND d.lang = '.protect($current['lang']).'
                     '.$exclude.'
                     ORDER BY i.position ASC
                     LIMIT 1
                ');

                $rs = $DB->result();
                $tmp = new item();
                $tmp->load($rs[0]->id);

                $rs = $tmp->galleries[0];
                $total = count($rs);
            }
        }
        else if($vars['nvlist_parent_type'] == 'item')
        {
            $pitem = $vars['nvlist_parent_item'];
            $rs = $pitem->galleries[0];
            $total = count($rs);
        }

        if($total > 0)
        {
            $order = 'priority'; // display images using the assigned priority
            if(!empty($vars['order']))
                $order = $vars['order'];

            $rs = nvweb_gallery_reorder($rs, $order);

            // prepare format to be parsed by nv list iterator
            $rs = array_map(
                function($k, $v)
                {
                    $v['file'] = $k;
                    return $v;
                },
                array_keys($rs),
                array_values($rs)
            );
        }
    }
    else if($vars['source']=='rss')
    {
        // url may be a property
        $rss_url = $vars['url'];
        if(strpos($vars['url'], "http")!==0)
        {
            $rss_url = nvweb_properties(array(
                'property'	=> 	$vars['url']
            ));
        }
        list($rs, $total) = nvweb_list_get_from_rss($rss_url, @$vars['cache'], $offset, $vars['items'], $permission, $order);
    }
    else if($vars['source']=='twitter')
    {
        list($rs, $total) = nvweb_list_get_from_twitter($vars['username'], @$vars['cache'], $offset, $vars['items'], $permission, $order);
    }
	else if(!empty($vars['source']))
	{
		// CUSTOM data source
        if($vars['source']=='comment')
            $vars['source'] = 'comments';

		$fname = 'nvweb_'.$vars['source'].'_list';

        if($vars['source']=='website_comments')
            $vars['source'] = 'comments';

		nvweb_webget_load($vars['source']);
		if(function_exists($fname))
			list($rs, $total) = $fname($offset, $vars['items'], $permission, $order, $vars);
    }

    $categories = array_filter($categories);

	// DATA SOURCE not given or ERROR
	if((empty($vars['source']) || !is_numeric($total)) && !empty($categories))
	{
        /*
         * TO DO: design decision ... lists should show items from published categories which has unpublished parent?
         *
         * Navigate CMS 1.6.7: NO
         *

        // we have to check all website UNPUBLISHED categories to keep the list query efficient
        // there are some cases:
        //  a) Permission is beyond user's level [0=>public, 1=>private, 2=>hidden]
        //  b) Date published is set and the value is before the current time (not yet published)
        //  c) Date unpublish is set and the value is before the current time (no more published)
        //  d) User account level not allowed [0=>everyone, 1=>signed in users, 2=>users NOT signed in]
        $DB->query('
            SELECT id
              FROM nv_structure
             WHERE website = '.protect($website->id).'
               AND (    permission > '.$permission.'
                     OR (date_published > 0 AND '.$website->current_time().' > date_published)
                     OR (date_unpublish > 0 AND '.$website->current_time().' > date_unpublish)
                     OR (access <> 0 AND access <> '.$access.')
               )
        ');

        $hidden_categories = $DB->result('id');

        // now we would have to mark the children categories also as unpublished

        */

        // reuse structure.access permission
        $access_extra_items = str_replace('s.', 'i.', $access_extra);

        $embedded = ($vars['embedded']=='true'? '1' : '0');

		// default source for retrieving items
		$DB->query('
			SELECT SQL_CALC_FOUND_ROWS i.id, i.permission, i.date_published, i.date_unpublish,
                    i.date_to_display, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate,
                    d.text as title, i.position as position, s.position
			  FROM nv_items i, nv_structure s, nv_webdictionary d
			 WHERE i.category IN('.implode(",", $categories).')
			   AND i.website = '.$website->id.'
			   AND i.permission <= '.$permission.'
			   AND i.embedding = '.$embedded.'
			   AND (i.date_published = 0 OR i.date_published < '.core_time().')
			   AND (i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
			   AND s.id = i.category
			   AND (s.date_published = 0 OR s.date_published < '.core_time().')
			   AND (s.date_unpublish = 0 OR s.date_unpublish > '.core_time().')
			   AND s.permission <= '.$permission.'
			   AND (s.access = 0 OR s.access = '.$access.$access_extra.')
			   AND (i.access = 0 OR i.access = '.$access.$access_extra_items.')
               AND d.website = i.website
			   AND d.node_type = "item"
			   AND d.subtype = "title"
			   AND d.node_id = i.id
			   AND d.lang = '.protect($current['lang']).'
			 '.$exclude.'
			 '.$orderby.'
			 LIMIT '.$vars['items'].'
			OFFSET '.$offset
		);

		$rs = $DB->result();
		$total = $DB->foundRows();
	}


    // now we have all elements that will be shown in the list
    // let's apply the nvlist template to each one
	for($i = 0; $i < count($rs); $i++)
	{
        // ignore empty objects
		if(
            ($vars['source']!='gallery' && empty($rs[$i]->id))  ||
            ($vars['source']=='gallery' && empty($rs[$i]['file']))
        )
            continue;

        // prepare a standard  $item  with the current element
		if($vars['source']=='comments' || $vars['source']=='comment')
		{
			$item = $rs[$i];
		}
		else if($vars['source']=='structure' || $vars['source']=='category')
		{
			$item = new structure();
			$item->load($rs[$i]->id);
			$item->date_to_display = $rs[$i]->pdate;
		}
        else if($vars['source']=='rss' || $vars['source']=='twitter')
        {
            // item is virtually created
            $item = $rs[$i];
        }
        else if($vars['source']=='block' || $vars['source']=='block_group')
        {
            $item = $rs[$i];
        }
        else if($vars['source']=='gallery')
        {
            $item = $rs[$i];
        }
		else
		{
			$item = new item();
			$item->load($rs[$i]->id);
            // if the item comes from a custom source, save the original query result
            // this allows getting a special field without extra work ;)
            $item->_query = $rs[$i];
		}

        // get the nv list template
		$item_html = $vars['template'];

        // first we need to isolate the nested nv lists/searches
        unset($nested_lists_fragments);
        list($item_html, $nested_lists_fragments) = nvweb_list_isolate_lists($item_html);

        // now, parse the nvlist_conditional tags (with html source code inside (and other nvlist tags))
        unset($nested_condition_fragments);
        list($item_html, $nested_conditional_fragments) = nvweb_list_isolate_conditionals($item_html);

        $conditional_placeholder_tags = nvweb_tags_extract($item_html, 'nvlist_conditional_placeholder', true, true, 'UTF-8'); // selfclosing = true

        while(!empty($conditional_placeholder_tags))
        {
            $tag = $conditional_placeholder_tags[0];
            $conditional = $nested_conditional_fragments[$tag["attributes"]["id"]];

            $conditional_html_output = nvweb_list_parse_conditional(
                $conditional,
                $item,
                $conditional['nvlist_conditional_template'],
                $i,
                count($rs)
            );

            $item_html = str_replace(
                $tag["full_tag"],
                $conditional_html_output,
                $item_html
            );

            $conditional_placeholder_tags = nvweb_tags_extract($item_html, 'nvlist_conditional_placeholder', true, true, 'UTF-8'); // selfclosing = true
        }


        // now, parse the (remaining) common nvlist tags (selfclosing tags)
        $template_tags_processed = 0;
        $template_tags = nvweb_tags_extract($item_html, 'nvlist', true, true, 'UTF-8'); // selfclosing = true
        while(!empty($template_tags))
		{
            $tag = $template_tags[0];

            // protect the "while" loop, maximum 500 nvlist tags parsed!
            $template_tags_processed++;
            if($template_tags_processed > 500)
                break;

            $content = nvweb_list_parse_tag($tag, $item, $vars['source']);
            $item_html = str_replace($tag['full_tag'], $content, $item_html);

            // html template has changed, the nvlist tags may have changed its positions
            $template_tags = nvweb_tags_extract($item_html, 'nvlist', true, true, 'UTF-8');
		}

        // restore & process nested lists (if any)
        foreach($nested_lists_fragments as $nested_list_uid => $nested_list_vars)
        {
            $nested_list_vars['nvlist_parent_vars'] = $vars;
            $nested_list_vars['nvlist_parent_type'] = $vars['source'];
            $nested_list_vars['nvlist_parent_item'] = $item;
            $content = nvweb_list($nested_list_vars);
            $item_html = str_replace('<!--#'.$nested_list_uid.'#-->', $content, $item_html);
        }

		$out[] = $item_html;
	}

	if($vars['paginator']=='true')
	{
		$pages = ceil($total / $vars['items']);
		$page = $_GET['page'];

        $paginator_text_prev = '&#10092;';
        $paginator_text_next = '&#10093;';

		if(!empty($vars['paginator_prev']))
			$paginator_text_prev = $theme->t($vars['paginator_prev']);

		if(!empty($vars['paginator_next']))
			$paginator_text_next = $theme->t($vars['paginator_next']);

        // keep existing URL variables except "page" and "route" (route is an internal navigate variable)
        $url_suffix = '';
        if(!is_array($_GET)) $_GET = array();
        foreach($_GET as $key => $val)
        {
            if($key=='page' || $key=='route') continue;
            $url_suffix .= '&'.$key.'='.$val;
        }

        if($pages > 1)
        {
            $out[] = '<div class="paginator">';

            if($page > 1) $out[] = '<a href="?page='.($page - 1).$url_suffix.'" rel="prev">'.$paginator_text_prev.'</a>'; // ❬

            if($page == 4)
                $out[] = '<a href="?page=1'.$url_suffix.'">1</a>';
            else if($page > 3)
                $out[] = '<a href="?page=1'.$url_suffix.'">1</a><span class="paginator-etc">...</span>';

            for($p = $page - 2; $p < $page + 3; $p++)
            {
                if($p < 1) continue;

                if($p > $pages) break;

                if($p==$page)
                    $out[] = '<a href="?page='.$p.$url_suffix.'" class="paginator-current">'.$p.'</a>';
                else
                    $out[] = '<a href="?page='.$p.$url_suffix.'">'.$p.'</a>';
            }

            if($page + 3 == $pages)
                $out[] = '<a href="?page='.$pages.$url_suffix.'">'.$pages.'</a>';
            else if($page + 3 < $pages)
                $out[] = '<span class="paginator-etc">...</span><a href="?page='.$pages.$url_suffix.'">'.$pages.'</a>';

            if($page < $pages) $out[] = '<a href="?page='.($page + 1).$url_suffix.'" rel="next">'.$paginator_text_next.'</a>'; // ❭

            $out[] = '<div style=" clear: both; "></div>';

            $out[] = '</div>';
        }
	}

	return implode("\n", $out);
}

function nvweb_list_parse_tag($tag, $item, $source='item')
{
	global $current;
	global $website;
	global $structure;

	$out = '';

	switch($tag['attributes']['source'])
	{
        // special condition, return direct query result values
		case 'query':
            $out = $item->_query->$tag['attributes']['value'];
            break;

		// NOTE: the following refers to structure information of an ITEM, useless if the source are categories!
		case 'structure':
		case 'category':
			nvweb_menu_load_dictionary(); // load menu translations if not already done
			nvweb_menu_load_routes(); // load menu paths if not already done
			switch($tag['attributes']['value'])
			{
				case 'title':
                    if($source=='structure' || $source=='category')
					    $out = $structure['dictionary'][$item->id];
                    else
                        $out = $structure['dictionary'][$item->category];

                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;');
					break;

				case 'property':
                    $id = $item->id;
                    if($source!='structure' && $source!='category')
                        $id = $item->category;

                    $out = nvweb_properties(array(
                        'mode'		=>	(($source=='structure' || $source=='category')? 'structure' : 'item'),
                        'id'		=>	$id,
                        'property'	=> 	(!empty($tag['attributes']['property'])? $tag['attributes']['property'] : $tag['attributes']['name']),
                        'option'	=>	$tag['attributes']['option'],
                        'border'	=>	$tag['attributes']['border'],
                        'class'		=>	$tag['attributes']['class'],
                        'width'		=>	$tag['attributes']['width'],
                        'height'	=>	$tag['attributes']['height'],
                        'return'	=>  $tag['attributes']['return'],
                        'format'	=>  $tag['attributes']['format'],
                        'link'	    =>  $tag['attributes']['link'],
                        'floor'	    =>  $tag['attributes']['floor']
                    ));
					break;

				case 'url':
				case 'path':
                    if($source=='structure' || $source=='category')
                        $out = nvweb_prepare_link($structure['routes'][$item->id]);
                    else
                        $out = nvweb_prepare_link($structure['routes'][$item->category]);
					break;

                case 'id':
                    if($source=='structure' || $source=='category')
                        $out = $item->id;
                    else // source = 'item'?
                        $out = $item->category;
                    break;

				default:
					break;
			}
			break;

		// ITEM comments
		case 'comment':
        case 'comments':
            switch($tag['attributes']['value'])
			{
				case 'avatar':
					$size = '48';
                    $extra = '';
					if(!empty($tag['attributes']['size']))
						$size = intval($tag['attributes']['size']);

                    if(!empty($tag['attributes']['border']))
						$extra .= '&border='.$tag['attributes']['border'];

					if(!empty($item->avatar))
						$out = '<img class="'.$tag['attributes']['class'].'" src="'.NVWEB_OBJECT.'?type=image'.$extra.'&id='.$item->avatar.'" width="'.$size.'px" height="'.$size.'px"/>';
					else if(!empty($tag['attributes']['default']))
                    {
                        // the comment creator has not an avatar, but the template wants to show a default one
                        // 3 cases:
                        //  numerical   ->  ID of the avatar image file in Navigate CMS
                        //  absolute path (http://www...)
                        //  relative path (/img/avatar.png) -> path to the avatar file included in the THEME used
                        if(is_numeric($tag['attributes']['default']))
                            $out = '<img class="'.$tag['attributes']['class'].'" src="'.NVWEB_OBJECT.'?type=image'.$extra.'&id='.$tag['attributes']['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                        else if(strpos($tag['attributes']['default'], 'http://')===0)
                            $out = '<img class="'.$tag['attributes']['class'].'" src="'.$tag['attributes']['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                        else if($tag['attributes']['default']=='none')
                            $out = ''; // no image
                        else
                            $out = '<img class="'.$tag['attributes']['class'].'"src="'.NAVIGATE_URL.'/themes/'.$website->theme.'/'.$tag['attributes']['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                    }
                    else // empty avatar
						$out = '<img class="'.$tag['attributes']['class'].'" src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" width="'.$size.'px" height="'.$size.'px"/>';
					break;

				case 'username':
					$out = (!empty($item->username)? $item->username : $item->name);
					break;

				case 'message':
                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($item->message, $tag['attributes']['length'], '&hellip;');
                    else
					    $out = nl2br($item->message);
					break;

				case 'date':
                    // Navigate CMS 1.6.6 compatibility
                    if(empty($tag['attributes']['format']) && !empty($tag['attributes']['date_format']))
                        $tag['attributes']['format'] = $tag['attributes']['date_format'];

                    if(!empty($tag['attributes']['format'])) // NON-STANDARD date formats
                        $out = nvweb_content_date_format($tag['attributes']['format'], $item->date_created);
                    else
                        $out = date($website->date_format.' H:i', $item->date_created);
					break;

                case 'item_url':
                    $out = nvweb_source_url('item', $item->item, $current['lang']);
                    break;

                case 'item_title':
                    $out = $item->item_title;
                    break;
			}
			break;

        case 'block':
            switch($tag['attributes']['value'])
            {
                case 'id':
                    $out = $item->id;
                    break;

                case 'block':
                    // generate the full block code
                    $out = nvweb_blocks_render($item->type, $item->trigger, $item->action);
                    break;

                case 'title':
                    $out = $item->dictionary[$current['lang']]['title'];
                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;');
                    break;

                case 'content':
                    $out = nvweb_blocks_render($item->type, $item->trigger, $item->action, 'content', $item, $tag['attributes']);
                    break;

                case 'url':
                case 'path':
                    $out = nvweb_blocks_render_action($item->action, '', $current['lang'], true);
                    if(empty($out))
                        $out = '#';
                    else
                        $out = nvweb_prepare_link($out);
                    break;

                case 'target':
                    if($item->action['action-type'][$current['lang']]=='web-n')
                        $out = '_blank';
                    else
                        $out = '_self';
                    break;

                case 'property':
                    $properties_mode = 'block';

                    if(!is_numeric($item->id))
                        $properties_mode = 'block_group_block';

                    $out = nvweb_properties(array(
                        'mode'		=>	$properties_mode,
                        'id'		=>	$item->id,
                        'property'	=> 	(!empty($tag['attributes']['property'])? $tag['attributes']['property'] : $tag['attributes']['name']),
                        'option'	=>	$tag['attributes']['option'],
                        'border'	=>	$tag['attributes']['border'],
                        'class'		=>	$tag['attributes']['class'],
                        'width'		=>	$tag['attributes']['width'],
                        'height'	=>	$tag['attributes']['height'],
                        'return'	=>  $tag['attributes']['return'],
                        'format'	=>  $tag['attributes']['format'],
                        'link'	    =>  $tag['attributes']['link'],
                        'floor'	    =>  $tag['attributes']['floor']
                    ));
                    break;

                case 'poll_answers':
                    $out = nvweb_blocks_render_poll($item);
                    break;

                default:
                    break;
            }
            break;

        case 'gallery':
            switch($tag['attributes']['value'])
            {
                case 'url':
                case 'path':
                    $out = NVWEB_OBJECT.'?wid='.$website->id.'&id='.$item['file'].'&amp;disposition=inline';
                    break;

                case 'thumbnail':
                    $out = '<img src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$item['file'].'&amp;disposition=inline&amp;width='.$tag['attributes']['width'].'&amp;height='.$tag['attributes']['height'].'&amp;border='.$tag['attributes']['border'].'"
									 alt="'.$item[$current['lang']].'" title="'.$item[$current['lang']].'" />';
                    break;

                default:
                    $out = '<a href="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$item['file'].'&amp;disposition=inline">
                                <img src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$item['file'].'&amp;disposition=inline&amp;width='.$tag['attributes']['width'].'&amp;height='.$tag['attributes']['height'].'&amp;border='.$tag['attributes']['border'].'"
									 alt="'.$item[$current['lang']].'" title="'.$item[$current['lang']].'" />
                            </a>';
                    break;
            }
            break;

		case 'item':	// useful also for source="structure" (but some are nonsense (content, comments, etc)
		default:
			switch($tag['attributes']['value'])
			{
                case 'id':
                    $out = $item->id;
                    break;

				case 'title':
					$out = $item->dictionary[$current['lang']]['title'];
                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;', $tag['attributes']['length']);
					break;

                case 'author':
                    if(!empty($current['object']->author))
                    {
                        $nu = new user();
                        $nu->load($current['object']->author);
                        $out = $nu->username;
                        unset($nu);
                    }

                    if(empty($out))
                        $out = $website->name;
                    break;

				case 'date':
                case 'date_post':
					if(!empty($tag['attributes']['format'])) // NON-STANDARD date formats
						$out = nvweb_content_date_format($tag['attributes']['format'], $item->date_to_display);
					else
						$out = date($website->date_format, $item->date_to_display);
					break;


				case 'content':
				case 'section':
					$section = $tag['attributes']['section'];
					if(empty($section)) $section = 'main';
					$out = $item->dictionary[$current['lang']]['section-'.$section];

					if(!empty($tag['attributes']['length']))
                    {
                        $allowed_tags = '';
                        if(!empty($tag['attributes']['allowed_tags']))
                            $allowed_tags = explode(',', $tag['attributes']['allowed_tags']);
						$out = core_string_cut($out, $tag['attributes']['length'], '&hellip;', $allowed_tags);
                    }
					break;

				case 'comments':
					$out = nvweb_content_comments_count($item->id);
					break;

				case 'gallery':
					$params = array('item' => $item->id);
					$params = array_merge($params, $tag['attributes']);
					$out = nvweb_gallery($params);
					break;

				case 'image':
				case 'photo':
					$photo = @array_shift(array_keys($item->galleries[0]));
					if(empty($photo))
						$out = $website->absolute_path(false) . '/object?type=transparent';
					else
						$out = $website->absolute_path(false) . '/object?type=image&id='.$photo;
					break;

				case 'url':
				case 'path':
                    // rss -> full url
                    // item -> relative url
                    // embedded item -> category url
                    if($item->embedding==1 && $item->association=='category')
                    {
                        nvweb_menu_load_routes(); // load menu paths if not already done
                        $out = nvweb_prepare_link($structure['routes'][$item->category]);
                    }
                    else
                    {
                        $path = $item->paths[$current['lang']];
                        if(empty($path))
                            $path = '/node/'.$item->id;
                        $out = nvweb_prepare_link($path);
                    }
					break;

                case 'tags':
                    $out = nvweb_content(array(
                        'mode' => 'tags',
                        'separator' => $tag['attributes']['separator'],
                        'id' => $item->id
                    ));
                    break;

				case 'score':
                    $out = nvweb_votes_calc($item, $tag['attributes']['round'], $tag['attributes']['half'], $tag['attributes']['min'], $tag['attributes']['max']);
					break;

				case 'votes':
					$out = intval($item->votes);
					break;

                case 'views':
                    $out = intval($item->views);
                    break;

				case 'property':
					$out = nvweb_properties(array(
						'mode'		=>	(($source=='structure' || $source=='category')? 'structure' : 'item'),
						'id'		=>	$item->id,
						'template'	=>	$item->template,
						'property'	=> 	(!empty($tag['attributes']['property'])? $tag['attributes']['property'] : $tag['attributes']['name']),
						'option'	=>	$tag['attributes']['option'],
						'border'	=>	$tag['attributes']['border'],
						'class'		=>	$tag['attributes']['class'],
						'width'		=>	$tag['attributes']['width'],
						'height'	=>	$tag['attributes']['height'],
						'quality'	=>	$tag['attributes']['quality'],
						'return'	=>  $tag['attributes']['return'],
                        'format'	=>  $tag['attributes']['format'],
                        'link'	    =>  $tag['attributes']['link'],
                        'floor'	    =>  $tag['attributes']['floor']
					));
					break;

				default:
                    // maybe a special tag not related to a source? (unimplemented)
			}
			break;
	}

	return $out;
}

function nvweb_list_get_orderby($order)
{
    global $website;

    // convert order type to "order by" clause
    switch($order)
    {
        case 'random':
            $orderby = 'ORDER BY RAND()';
            break;

        case 'oldest':
            $orderby = 'ORDER BY pdate ASC';
            break;

        case 'alphabetical':
        case 'abc':
            $orderby = 'ORDER BY title ASC';
            break;

        case 'reverse_alphabetical':
        case 'zyx':
            $orderby = 'ORDER BY title DESC';
            break;

        case 'future':
        case 'from_today':
            $orderby = ' AND i.date_to_display > '.gmmktime(0,0,0,gmdate('m',$website->current_time()),gmdate('d',$website->current_time()),gmdate('Y',$website->current_time())).'
                         ORDER BY pdate ASC ';
            break;

        case 'priority':
            $orderby = ' ORDER BY IFNULL(i.position, 0) ASC, IFNULL(s.position,0) ASC ';
            break;

        case 'rating':
            $orderby = ' ORDER BY i.score DESC ';
            break;

        case 'votes':
            $orderby = ' ORDER BY i.votes DESC ';
            break;

        case 'views':
            $orderby = ' ORDER BY i.views DESC ';
            break;

        case 'newest':
        case 'latest':
        default:
            $orderby = 'ORDER BY pdate DESC';
            break;
    }

    return $orderby;
}

function nvweb_list_isolate_lists($item_html)
{
    $nested_lists_fragments = array();
    $nested_lists_tags = nvweb_tags_extract($item_html, 'nv', true, true, 'UTF-8');

    foreach($nested_lists_tags as $tag)
    {
        $changed = false;

        switch($tag['attributes']['object'])
        {
            case 'list':
            case 'search':
                $template_end = nvweb_templates_find_closing_list_tag($item_html, $tag['offset'] + strlen($tag['full_tag']));
                $tag['length'] = $template_end - $tag['offset'] + strlen('</nv>'); // remove tag characters
                $list_template = substr($item_html, ($tag['offset'] + strlen($tag['full_tag'])), ($tag['length'] - strlen('</nv>') - strlen($tag['full_tag'])));

                $nested_list_vars = array_merge($tag['attributes'], array('template' => $list_template));
                $nested_list_uid = uniqid('nvlist-');
                $nested_lists_fragments[$nested_list_uid] = $nested_list_vars;

                $item_html = substr_replace($item_html, '<!--#'.$nested_list_uid.'#-->', $tag['offset'], $tag['length']);
                $changed = true;
                break;
        }

        // offsets may change due the replace
        if($changed)
        {
            list($item_html, $nested_sub_lists_fragments) = nvweb_list_isolate_lists($item_html);
            $nested_lists_fragments = array_merge($nested_lists_fragments, $nested_sub_lists_fragments);
            break;
        }
    }

    return array($item_html, $nested_lists_fragments);
}

function nvweb_list_isolate_conditionals($item_html)
{
    $nested_conditionals_fragments = array();
    $conditional_tags = nvweb_tags_extract($item_html, 'nvlist_conditional', true, true, 'UTF-8');

    if(!empty($conditional_tags))
    {
        $tag = $conditional_tags[0];

        $template_end = nvweb_list_find_closing_conditional_tag($item_html, $tag['offset'] + strlen($tag['full_tag']));
        $tag['length'] = $template_end - $tag['offset'] + strlen('</nvlist_conditional>'); // remove tag characters
        $conditional_template = substr($item_html, ($tag['offset'] + strlen($tag['full_tag'])), ($tag['length'] - strlen('</nvlist_conditional>') - strlen($tag['full_tag'])));

        // find inner conditionals before replacing the conditional found
        list($conditional_template, $nested_sub_conditionals_fragments) = nvweb_list_isolate_conditionals($conditional_template);
        $nested_conditionals_fragments = array_merge($nested_sub_conditionals_fragments, $nested_conditionals_fragments);

        $nested_conditional_vars = array_merge($tag, array('nvlist_conditional_template' => $conditional_template));
        $nested_conditional_uid = uniqid('nvlist_conditional-');
        $nested_conditionals_fragments[$nested_conditional_uid] = $nested_conditional_vars;
        $item_html = substr_replace($item_html, '<nvlist_conditional_placeholder id="'.$nested_conditional_uid.'" />', $tag['offset'], $tag['length']);

        // process other conditionals
        list($item_html, $nested_sub_conditionals_fragments) = nvweb_list_isolate_conditionals($item_html);
        $nested_conditionals_fragments = array_merge($nested_sub_conditionals_fragments, $nested_conditionals_fragments);
    }

    return array($item_html, $nested_conditionals_fragments);
}

function nvweb_list_find_closing_conditional_tag($html, $offset)
{
    $loops = 0;
    $found = false;

    while(!$found)
    {
        // find next '</nv>' occurrence from offset
        $next_closing = stripos($html, '</nvlist_conditional>', $offset);

        // check if there is a special '<nv>' opening tag (list, search, conditional) before the next closing found tag
        $next_opening = stripos_array(
            $html,
            array(
                '<nvlist_conditional ',
            ),
            $offset
        );

        if(!$next_opening)
        {
            $found = true;
        }
        else
        {
            $found = $next_opening > $next_closing;

            if(!$found)
            {
                $offset = $next_closing + strlen('</nvlist_conditional>');
                $loops++;
            }
        }

        if(!$found && ($offset > strlen($html) || $loops > 1000))
            break;
    }

    if(!$found)
        $next_closing = false;

    return $next_closing;
}

function nvweb_list_parse_conditional($tag, $item, $item_html, $position, $total)
{
    $out = '';

    if($tag['attributes']['by']=='property')
    {
        $property_name = $tag['attributes']['property_id'];
        if(empty($property_name))
            $property_name = $tag['attributes']['property_name'];

        $property_value = $item->property($property_name);
        $property_definition = $item->property_definition($property_name);
        $condition_value = $tag['attributes']['property_value'];

        if(in_array($property_definition->type, array('image', "file")))
        {
            if($property_value == '0')
                $property_value = "";
        }

        // process special comparing values
        switch($property_definition->type)
        {
            case 'date':
                if($condition_value == 'today')
                {
                    $now = getdate(core_time());
                    $condition_value = mktime(0, 0, 0, $now['mon'], $now['mday'], $now['year']);
                }
                else if($condition_value == 'now')
                {
                    $condition_value = core_time();
                }
                break;
        }

        $condition = false;
        switch($tag['attributes']['property_compare'])
        {
            case '>':
            case 'gt':
                $condition = ($property_value > $condition_value);
                break;

            case '<':
            case 'lt':
                $condition = ($property_value < $condition_value);
                break;

            case '>=':
            case '=>':
            case 'gte':
                $condition = ($property_value >= $condition_value);
                break;

            case '<=':
            case '=<':
            case 'lte':
                $condition = ($property_value <= $condition_value);
                break;

            case '!=':
            case 'neq':
                $condition = ($property_value != $condition_value);
                break;

            case '=':
            case '==':
            case 'eq':
            default:
                $condition = ($property_value == $condition_value);
        }

        if($condition)
        {
            // parse the contents of this condition on this round
            $out = $item_html;
        }
        else
        {
            // remove this conditional html code on this round
            $out = '';
        }
    }
    else if($tag['attributes']['by']=='template' || $tag['attributes']['by']=='templates')
    {
        $templates = array();
        if(isset($tag['attributes']['templates']))
            $templates = explode(",", $tag['attributes']['templates']);
        else if(isset($tag['attributes']['template']))
            $templates = array($tag['attributes']['template']);

        if(in_array($item->template, $templates))
        {
            // the template matches the condition, apply
            $out = $item_html;
        }
        else
        {
            // remove this conditional html code on this round
            $out = '';
        }
    }
    else if($tag['attributes']['by']=='position')
    {
        if(isset($tag['attributes']['each']))
        {
            if($position % $tag['attributes']['each'] == 0) // condition applies
                $out = $item_html;
            else // remove the full nvlist_conditional tag, doesn't apply here
                $out = '';
        }
        else if(isset($tag['attributes']['range']))
        {
            list($pos_min, $pos_max) = explode('-', $tag['attributes']['range']);

            if(($position+1) >= $pos_min && ($position+1) <= $pos_max)
                $out = $item_html;
            else
                $out = '';
        }
        else if(isset($tag['attributes']['position']))
        {
            switch($tag['attributes']['position'])
            {
                case 'first':
                    if($position == 0)
                        $out = $item_html;
                    else
                        $out = '';
                    break;

                case 'not_first':
                    if($position > 0)
                        $out = $item_html;
                    else
                        $out = '';
                    break;

                case 'last':
                    if($position == ($total-1))
                        $out = $item_html;
                    else
                        $out = '';
                    break;

                case 'not_last':
                    if($position != ($total-1))
                        $out = $item_html;
                    else
                        $out = '';
                    break;

                default:
                    // position "x"?
                    if($tag['attributes']['position']==='0')
                        $tag['attributes']['position'] = 1;
                    if(($position+1) == $tag['attributes']['position'])
                        $out = $item_html;
                    else
                        $out = '';
                    break;
            }
        }
    }
    else if($tag['attributes']['by']=='block')
    {
        // $item may be a block object or a block group block type
        if( $tag['attributes']['type'] == $item->type || $tag['attributes']['type'] == $item->id )
        {
            $out = $item_html;
        }
        else
        {
            // no match, discard this conditional
            $out = '';
        }
    }
    else if($tag['attributes']['by']=='access')
    {
        $access = 0;
        switch($tag['attributes']['access'])
        {
            case 3:
            case 'webuser_groups':
                $access = 3;
                break;

            case 2:
            case 'not_signed_in':
                $access = 2;
                break;

            case 1:
            case 'signed_in':
                $access = 1;
                break;

            case 0:
            case 'everyone':
            default:
                $access = 0;
                break;
        }

        if($item->access == $access)
            $out = $item_html;
        else
            $out = '';
    }
    else // unknown nvlist_conditional, discard
    {
        $out = '';
    }

    return $out;
}

function nvweb_list_get_from_rss($url, $cache_time=3600, $offset=0, $items=null, $permission=null, $order=null)
{
    $feed = new feed_parser();

    $feed->set_cache($cache_time);
    $feed->load($url);
    list($channel, $articles, $count) = $feed->parse($offset, $items, $order);

    $items = item::convert_from_rss($articles);

    return array($items, $count);
}

function nvweb_list_get_from_twitter($username, $cache_time=3600, $offset, $items=10, $permission, $order)
{
    $url = 'https://api.twitter.com/1/statuses/user_timeline.rss?include_rts=true&contributor_details=false&screen_name='.$username.'&count='.$items;

    $feed = new feed_parser();

    $feed->set_cache($cache_time);
    $feed->load($url);
    list($channel, $articles) = $feed->parse('items', $offset, $order);

    $items = item::convert_from_rss($articles);

    return array($items, count($items));
}

?>