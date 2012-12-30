<?php
function nvweb_statistics($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $webgets;
	
	$webget = "statistics";

	$out = array();

	if(empty($vars['number'])) $vars['number'] = 5;
	
	if(empty($vars['categories']))
	{
		if($current['type']=='structure')
			$categories = array($current['id']);
		else
			$categories = array($current['object']->category);
	}
	else if(!empty($vars['categories']))
	{
		$categories = explode(',', $vars['categories']);
		$categories = array_filter($categories); // remove empty elements
	}

	if($vars['children']=='true')
		$categories = nvweb_menu_get_children($categories);		
		
	switch(@$vars['mode'])
	{	
		case 'most_seen':		
			$DB->query('SELECT i.id, p.path, d.text, i.views, i.category
			  			  FROM nv_items i, nv_paths p, nv_webdictionary d
						WHERE i.website = '.$website->id.'
						  AND i.permission = 0
						  AND i.access = 0
						  AND i.association = "category"
						  AND i.category IN ('.implode(',', $categories).')
						  AND ( i.date_published = 0 OR i.date_published < '.core_time().')
						  AND ( i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
						  AND p.object_id = i.id
						  AND p.type = "item"
						  AND p.lang = '.protect($current['lang']).'
						  AND p.website = '.$website->id.'
						  AND d.node_id = i.id
						  AND d.node_type = "item"
						  AND d.subtype = "title"
						  AND d.lang = '.protect($current['lang']).'
  						  AND d.website = '.$website->id.'
						ORDER BY i.views DESC, i.date_created DESC
						LIMIT '.intval($vars['number']));

			$top = $DB->result();					
			
			if($vars['return']=='objects')
				return $top;
			
			for($i=0; $i < count($top); $i++)
				$out[] = '<div><a href="'.NVWEB_ABSOLUTE.$top[$i]->path.'">'.$top[$i]->text.'</a></div>';
			
			break;			
			
		case 'most_commented':
			$DB->query('SELECT i.id, p.path, d.text, i.views, i.category, COUNT(c.id) as comments
			  			  FROM nv_items i, nv_paths p, nv_webdictionary d, nv_comments c
						WHERE i.website = '.$website->id.'
						  AND i.permission = 0
						  AND i.access = 0
						  AND i.association = "category"		
						  AND i.category IN ('.implode(',', $categories).')
						  AND ( i.date_published = 0 OR i.date_published < '.core_time().')
						  AND ( i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
						  AND p.object_id = i.id
						  AND p.type = "item"
						  AND p.lang = '.protect($current['lang']).'
						  AND p.website = '.$website->id.'
						  AND d.node_id = i.id
						  AND d.node_type = "item"
						  AND d.subtype = "title"
						  AND d.lang = '.protect($current['lang']).'
  						  AND d.website = '.$website->id.'
						  AND c.item = i.id
						  AND c.website = '.$website->id.'
						  AND c.status = 0
						GROUP BY i.id
						ORDER BY comments DESC, i.date_created DESC
						LIMIT '.intval($vars['number']));
						
			$top = $DB->result();
			
			if($vars['return']=='objects')
				return $top;			
			
			for($i=0; $i < count($top); $i++)
				$out[] = '<div><a href="'.NVWEB_ABSOLUTE.$top[$i]->path.'">'.$top[$i]->text.'</a></div>';
						
			break;

        case 'latest':  // NOT TESTED
            $DB->query('SELECT i.id, p.path, d.text, i.views, i.category
			  			  FROM nv_items i, nv_paths p, nv_webdictionary d
						WHERE i.website = '.$website->id.'
						  AND i.permission = 0
						  AND i.access = 0
						  AND i.association = "category"
						  AND i.category IN ('.implode(',', $categories).')
						  AND ( i.date_published = 0 OR i.date_published < '.core_time().')
						  AND ( i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
						  AND p.object_id = i.id
						  AND p.type = "item"
						  AND p.lang = '.protect($current['lang']).'
						  AND p.website = '.$website->id.'
						  AND d.node_id = i.id
						  AND d.node_type = "item"
						  AND d.subtype = "title"
						  AND d.lang = '.protect($current['lang']).'
  						  AND d.website = '.$website->id.'
						GROUP BY i.id
						ORDER BY comments DESC, i.date_created DESC
						LIMIT '.intval($vars['number']));

			$top = $DB->result();

			if($vars['return']=='objects')
				return $top;

			for($i=0; $i < count($top); $i++)
				$out[] = '<div><a href="'.NVWEB_ABSOLUTE.$top[$i]->path.'">'.$top[$i]->text.'</a></div>';
            break;
	}
	
	$out = implode("\n", $out);		
	
	return $out;
}

?>