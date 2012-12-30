<?php
require_once(NAVIGATE_PATH.'/lib/webgets/list.php');

function nvweb_search($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $cache;
	global $structure;

	$out = array();
		
	$search_what = $_REQUEST[$vars['request']];
    $search_archive = array();

    if(!empty($_REQUEST['archive']))
        $search_archive = explode("-", $_REQUEST['archive']);

	if(!empty($search_what) || (!empty($search_archive[0]) && !empty($search_archive[1])))
	{
		$search_what = explode(' ', $search_what);
		$search_what = array_filter($search_what);

        if(empty($search_what))
           $search_what = array();

		$likes = array();
		foreach($search_what as $what)
        {
            if(substr($what, 0, 1)=='-')
                $likes[] = 'd.text NOT LIKE '.protect('%'.substr($what, 1).'%');
            else
			    $likes[] = 'd.text LIKE '.protect('%'.$what.'%');
        }

        if(!empty($search_archive)) // add the conditions
        {
            $start_date = gmmktime(0, 0, 0, $search_archive[1], 1, $search_archive[0]);
            $end_date   = gmmktime(0, 0, 0, $search_archive[1]+1, 1, $search_archive[0]);

            $likes[] = ' (i.date_published >= '.$start_date.' OR (i.date_published=0 AND i.date_created >= '.$start_date.'))';
            $likes[] = ' (i.date_published <= '.$end_date.' AND i.date_created <= '.$end_date.')';
        }

		if(isset($vars['categories']))
		{
			$categories = explode(',', $vars['categories']);
			$categories = array_filter($categories); // remove empty elements
			
			if($vars['children']=='true')
				$categories = nvweb_menu_get_children($categories);			
		}

		// retrieve entries
		$permission = (!empty($_SESSION['APP_USER'])? 1 : 0);
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

		$DB->query('	
			SELECT SQL_CALC_FOUND_ROWS DISTINCT(i.id), i.permission, i.date_published, i.date_unpublish,
			        GREATEST(i.date_published, i.date_created) as pdate, i.position as position
			  FROM nv_items i, nv_webdictionary d
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
			 '.$orderby.'
			 LIMIT '.$vars['items'].'
			OFFSET '.$offset
		);

		$rs = $DB->result();
		
		$total = $DB->foundRows();
			
		$template_tags = nvweb_tags_extract($vars['template'], 'nvlist', true, true, 'UTF-8');
					
		for($i = 0; $i < count($rs); $i++)
		{
			if(empty($rs[$i]->id)) break;
			$item = new item();
			$item->load($rs[$i]->id);
			$item->date_public = $rs[$i]->pdate;
		
			if(empty($template_tags))
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
				$item_html = $vars['template'];
		
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
		
		if($vars['paginator']==true)
		{
			$pages = ceil($total / $vars['items']);
			$page = $_GET['page'];
			
			$out[] = '<div class="paginator">';
			
			if($page > 1) $out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page='.($page - 1).'">&lt;&lt;</a>';
			
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
			
			if($page < $pages) $out[] = '<a href="?'.$archive.$vars['request'].'='.$_REQUEST[$vars['request']].'&page='.($page + 1).'">&gt;&gt;</a>';
			
			$out[] = '<div style=" clear: both; "></div>';
			
			$out[] = '</div>';
	
		}
	}
	
	return implode("\n", $out);
}
?>