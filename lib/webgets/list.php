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
        $categories = explode(',', $vars['categories']);
        $categories = array_filter($categories); // remove empty elements
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
	if(empty($_GET['page'])) $_GET['page'] = 1;
	$offset = intval($_GET['page'] - 1) * $vars['items'];

	$permission = (!empty($_SESSION['APP_USER'])? 1 : 0);

    // public access / webuser based / webuser groups based
    $access     = 2;
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

		$DB->query('
			SELECT SQL_CALC_FOUND_ROWS s.id, s.permission,
			            s.date_published, s.date_unpublish, s.date_published as pdate,
			            d.text as title, s.position
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
    else if($vars['source']=='rss')
    {
        list($rs, $total) = nvweb_list_get_from_rss($vars['url'], @$vars['cache'], $offset, $vars['items'], $permission, $order);
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
			list($rs, $total) = $fname($offset, $vars['items'], $permission, $order);
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
			       GREATEST(i.date_published, i.date_created) as pdate, d.text as title, i.position as position
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

	for($i = 0; $i < count($rs); $i++)
	{
		if(empty($rs[$i]->id)) break;

		if($vars['source']=='comments' || $vars['source']=='comment')
		{
			$item = $rs[$i];
		}
		else if($vars['source']=='structure' || $vars['source']=='category')
		{
			$item = new structure();
			$item->load($rs[$i]->id);
			$item->date_public = $rs[$i]->pdate;
		}
        else if($vars['source']=='rss' || $vars['source']=='twitter')
        {
            // item is virtually created
            $item = $rs[$i];
        }
        else if($vars['source']=='block')
        {
            $item = $rs[$i];
        }
		else
		{
			$item = new item();
			$item->load($rs[$i]->id);
			$item->date_public = $rs[$i]->pdate;
            // if the item comes from a custom source, save the original query result
            // this allows getting a special field without extra work ;)
            $item->_query = $rs[$i];
		}

		$item_html = $vars['template'];

		// parse nvlist tags
        $a = 0;
        $template_tags = nvweb_tags_extract($vars['template'], 'nvlist', true, true, 'UTF-8');
        while(!empty($template_tags))
		{
            $tag = $template_tags[0];

            $a++;
            if($a > 100)
                exit;

            // parse special nvlist tags
            switch($tag['attributes']['source'])
            {
                case 'conditional':
                    if(intval($tag['attributes']['each']) > 0)
                    {
                        $tag_end = strpos($item_html, '</nvlist>', $tag['offset']);
                        // first element or syntax error, missing </nvlist>
                        if(!$tag_end || $i <= 0)
                        {
                            $item_html = str_replace($tag['full_tag'], '', $item_html);
                        }
                        else
                        {
                            if($i % $tag['attributes']['each']==0)
                            {
                                // remove nvlist tag but leave the tag content
                                $tag_content = substr($item_html, $tag['offset'] + strlen($tag['full_tag']), $tag_end - $tag['offset'] - strlen($tag['full_tag']));
                                $item_html = substr($item_html, 0, $tag['offset']) . $tag_content . substr($item_html, $tag_end + 9);
                            }
                            else
                            {
                                // remove nvlist tag completely
                                $item_html = substr($item_html, 0, $tag['offset']) . substr($item_html, $tag_end + 9);
                            }
                        }
                    }
                    break;

                default:
                    $content = nvweb_list_parse_tag($tag, $item, $vars['source']);
                    $item_html = str_replace($tag['full_tag'], $content, $item_html);
                    break;
            }
            // html template has changed, the nvlist tags changed positions
            $template_tags = nvweb_tags_extract($item_html, 'nvlist', true, true, 'UTF-8');
		}

		$out[] = $item_html;
	}

	if($vars['paginator']=='true')
	{
		$pages = ceil($total / $vars['items']);
		$page = $_GET['page'];

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

            if($page > 1) $out[] = '<a href="?page='.($page - 1).$url_suffix.'" rel="prev">&lt;&lt;</a>';

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

            if($page < $pages) $out[] = '<a href="?page='.($page + 1).$url_suffix.'" rel="next">&gt;&gt;</a>';

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
                    // Navigate CMS 1.6.6 compatability
                    if(empty($tag['attributes']['format']) && !empty($tag['attributes']['date_format']))
                        $tag['attributes']['format'] = $tag['attributes']['date_format'];

                    if(!empty($tag['attributes']['format'])) // NON-STANDARD date formats
                        $out = nvweb_content_date_format($tag['attributes']['format'], $item->date_created);
                    else
                        $out = date($website->date_format.' H:i', $item->date_created);
					break;
			}
			break;

        case 'block':
            switch($tag['attributes']['value'])
            {
                case 'title':
                    $out = $item->dictionary[$current['lang']]['title'];

                    if(!empty($tag['attributes']['length']))
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;');
                    break;

                case 'content':
                    $out = nvweb_blocks_render($item->type, $item->trigger, $item->action, 'content');
                    break;

                case 'url':
                case 'path':
                    $out = nvweb_prepare_link(nvweb_blocks_render_action($item->action, '', $current['lang'], true));
                    break;

                case 'property':
                    $out = nvweb_properties(array(
                        'mode'		=>	'block',
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

                default:
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
                        $out = core_string_cut($out, $tag['attributes']['length'], '&hellip;');
					break;

				case 'date':
					if(!empty($tag['attributes']['format'])) // NON-STANDARD date formats
						$out = nvweb_content_date_format($tag['attributes']['format'], $item->date_public);
					else
						$out = date($website->date_format, $item->date_public);
					break;

				case 'content':
				case 'section':
					$section = $tag['attributes']['section'];
					if(empty($section)) $section = 'main';
					$out = $item->dictionary[$current['lang']]['section-'.$section];

					if(!empty($tag['attributes']['length']))
						$out = core_string_cut($out, $tag['attributes']['length'], '&hellip;');
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
					$out = nvweb_prepare_link($item->paths[$current['lang']]);
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
            $orderby = ' AND GREATEST(i.date_published, i.date_created) > '.gmmktime(0,0,0,gmdate('m',$website->current_time()),gmdate('d',$website->current_time()),gmdate('Y',$website->current_time())).'
                         ORDER BY pdate ASC ';
            break;

        case 'priority':
            $orderby = ' ORDER BY position ASC ';
            break;

        case 'rating':
            $orderby = ' ORDER BY i.score DESC ';
            break;

        case 'votes':
            $orderby = ' ORDER BY i.votes DESC ';
            break;

        case 'newest':
        case 'latest':
        default:
            $orderby = 'ORDER BY pdate DESC';
            break;
    }

    return $orderby;
}

function nvweb_list_get_from_rss($url, $cache_time=3600, $offset=0, $items=null, $permission=null, $order=null)
{
    $feed = new feed_parser();

    $feed->set_cache($cache_time);
    $feed->load($url);
    list($channel, $articles) = $feed->parse($offset, $items, $order);

    $items = item::convert_from_rss($articles);

    return array($items, count($items));
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