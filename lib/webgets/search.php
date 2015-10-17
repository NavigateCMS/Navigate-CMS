<?php
require_once(NAVIGATE_PATH.'/lib/webgets/list.php');
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');

function nvweb_search($vars=array())
{
	global $website;
    global $webuser;
	global $DB;
	global $current;
	global $cache;
	global $structure;
    global $theme;

	$out = array();

	$search_what = $_REQUEST[$vars['request']];
    $search_archive = array();

    if(!empty($_REQUEST['archive']))
        $search_archive = explode("-", $_REQUEST['archive']);  // YEAR, MONTH, CATEGORIES (separated by commas)

	if(!empty($search_what) || (!empty($search_archive[0]) && !empty($search_archive[1])))
	{
        // LOG search request
        $wu_id = 0;
        if(!empty($webuser->id))
            $wu_id = $webuser->id;

        $DB->execute('
            INSERT INTO nv_search_log
              (id, website, date, webuser, origin, text)
            VALUES
              (0, :website, :date, :webuser, :origin, :text)
        ', array(
            'website' => $website->id,
            'date' => time(),
            'webuser' => $wu_id,
            'origin' => $_SERVER['HTTP_REFERER'],
            'text' => $search_what,
        ));

        // prepare and execute the search
		$search_what = explode(' ', $search_what);
		$search_what = array_filter($search_what);

        if(empty($search_what))
           $search_what = array();

		$likes = array();
		foreach($search_what as $what)
        {
            if(substr($what, 0, 1)=='-')
            {
                $likes[] = 'd.text NOT LIKE '.protect('%'.substr($what, 1).'%').
                           'AND p.value NOT LIKE '.protect('%'.substr($what, 1).'%');
            }
            else
            {
			    $likes[] = 'd.text LIKE '.protect('%'.$what.'%').
			               ' OR p.value LIKE '.protect('%'.$what.'%');
            }
        }

        if(!empty($search_archive)) // add the conditions
        {
            $start_date = gmmktime(0, 0, 0, $search_archive[1], 1, $search_archive[0]);
            $end_date   = gmmktime(0, 0, 0, $search_archive[1]+1, 1, $search_archive[0]);

            $likes[] = ' (i.date_to_display >= '.$start_date.')';
            $likes[] = ' (i.date_to_display <= '.$end_date.')';
        }

        if(!empty($search_archive[2]))
            $vars['categories'] = $search_archive[2];

        if(isset($vars['categories']))
		{
			$categories = explode(',', $vars['categories']);
			$categories = array_filter($categories); // remove empty elements
			
			if($vars['children']=='true')
				$categories = nvweb_menu_get_children($categories);
		}

		// retrieve entries
		$permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);
        $access     = (!empty($current['webuser'])? 1 : 2);

		if(empty($_GET['page'])) $_GET['page'] = 1;
		$offset = intval($_GET['page'] - 1) * $vars['items'];

        // get order type: PARAMETER > NV TAG PROPERTY > DEFAULT (priority given in CMS)
        $order      = @$_REQUEST['order'];
        if(empty($order))
            $order  = @$vars['order'];
        if(empty($order))   // default order: latest
            $order = 'latest';

        $orderby = nvweb_list_get_orderby($order);

        if(empty($vars['items']) || $vars['items']=='0')
            $vars['items'] = 500; //2147483647; // maximum integer
        // TODO: try to optimize nvlist generation to use less memory and increase the maximum number of items
        // NOTE: anyway, having >500 items on a page without a paginator is probably a bad idea... disagree? Contact Navigate CMS team!

		$DB->query('	
			SELECT SQL_CALC_FOUND_ROWS i.id as id, i.permission, i.date_published, i.date_unpublish,
			        i.date_to_display, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate,
			        i.position as position, wd.text as title
			  FROM nv_items i, nv_webdictionary d
			  LEFT JOIN nv_webdictionary wd
			    ON wd.node_id = d.node_id
			   AND wd.lang =  '.protect($current['lang']).'
			   AND wd.node_type = "item"
			   AND wd.website = '.protect($website->id).'
			  LEFT JOIN nv_properties_items p
			    ON p.node_id = d.node_id
			   AND p.element = "item"
			   AND p.website = '.protect($website->id).'
			 WHERE i.website = '.$website->id.'
			   AND i.permission <= '.$permission.'
			   AND (i.date_published = 0 OR i.date_published < '.core_time().')
			   AND (i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
			   AND (i.access = 0 OR i.access = '.$access.')
			   AND d.website = '.protect($website->id).'
			   AND d.node_id = i.id
			   AND d.lang =  '.protect($current['lang']).'
			   AND (d.node_type = "item" OR d.node_type = "tags")
			   AND (
			   	'.implode(' AND ', $likes).'
			   )
			   '.(empty($categories)? '' : 'AND category IN('.implode(",", $categories).')').'
          GROUP BY i.id
			 '.$orderby.'
			 LIMIT '.$vars['items'].'
			OFFSET '.$offset
		);


		$rs = $DB->result();
		$total = $DB->foundRows();

		for($i = 0; $i < count($rs); $i++)
		{
			if(empty($rs[$i]->id)) break;
			$item = new item();
			$item->load($rs[$i]->id);

            // get the nv list template
            $item_html = $vars['template'];

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

            // now parse the (remaining) common nvlist tags
            $template_tags = nvweb_tags_extract($item_html, 'nvlist', true, true, 'UTF-8'); // selfclosing = true
		
			if(empty($item_html)) // apply a default template if no one is defined
			{		
				$item_html = array();
				$item_html[] = '<div class="search-result-item">';
				$item_html[] = '	<div class="search-result-title"><a href="'.$website->absolute_path().$item->paths[$current['lang']].'">'.$item->dictionary[$current['lang']]['title'].'</a></div>';	
				$item_html[] = '	<div class="search-result-summary">'.core_string_cut($item->dictionary[$current['lang']]['section-main'], 300, '&hellip;').'</div>';
				$item_html[] = '</div>';
				
				$item_html = implode("\n", $item_html);
				
				$out[] = $item_html;					
			}
			else
			{
				// parse special template tags
				foreach($template_tags as $tag)
				{
					$content = nvweb_list_parse_tag($tag, $item);
					$item_html = str_replace($tag['full_tag'], $content, $item_html);	
				}
				
				$out[] = $item_html;
			}
		}

        $archive = $_REQUEST['archive'];
        if(!empty($archive))
           $archive = 'archive='.$archive.'&';
		
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


            $out[] = '<div class="paginator">';
			
			if($page > 1) $out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page='.($page - 1).'" rel="prev">'.$paginator_text_prev.'</a>';
			
			if($page == 4) 
				$out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page=1">1</a>';
			else if($page > 3) 
				$out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page=1">1</a><span class="paginator-etc">...</span>';
			
			for($p = $page - 2; $p < $page + 3; $p++)
			{					
				if($p < 1) continue;
	
				if($p > $pages) break;
				
				if($p==$page)
					$out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page='.$p.'" class="paginator-current">'.$p.'</a>';
				else
					$out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page='.$p.'">'.$p.'</a>';
			}
	
			if($page + 3 == $pages)
				$out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page='.$pages.'">'.$pages.'</a>';
			else if($page + 3 < $pages)
				$out[] = '<span class="paginator-etc">...</span><a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page='.$pages.'">'.$pages.'</a>';
			
			if($page < $pages) $out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page='.($page + 1).'" rel="next">'.$paginator_text_next.'</a>';
			
			$out[] = '<div style=" clear: both; "></div>';
			
			$out[] = '</div>';
	
		}
	}
	
	return implode("\n", $out);
}
?>