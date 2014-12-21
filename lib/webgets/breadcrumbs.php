<?php
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');

function nvweb_breadcrumbs($vars=array())
{
	global $website;
	global $current;
	global $DB;
	global $structure;

	$out = '';

	if(empty($vars['separator']))
		$vars['separator'] = '&nbsp;&gt;&nbsp;';

    if($vars['separator']=='image')
        $vars['separator'] = '<img src="'.$vars['image'].'" />';

    if($vars['separator']=='base64')
        $vars['separator'] = base64_decode($vars['base64']);
	
	if(empty($vars['from']))
		$vars['from'] = 0;

	// 2 options: we are displaying an element or a category
	$breadcrumbs = array();
	
	if($current['type']=='structure')	// the current category is included
	{
		$breadcrumbs[] = $current['id'];
	}
	else if($current['type']=='item') // start from item main category
	{
		$breadcrumbs[] = $current['object']->category;
	}
	
	if(!empty($breadcrumbs)) 
	{
		// start looking for each category parent
		$parent = $breadcrumbs[0];
		while($parent > 0)
		{
			$parent = nvweb_breadcrumbs_parent($parent);			
			$breadcrumbs[] = $parent;
		}
			
		$vars['from'] = intval($vars['from']) + 1;
		
		nvweb_menu_load_dictionary();
		
		$breadcrumbs = array_reverse($breadcrumbs);
		
		for($i = $vars['from']; $i < count($breadcrumbs); $i++)
		{
            if($vars['links']=='false')
                $out .= $structure['dictionary'][$breadcrumbs[$i]];
            else
			    $out .= '<a '.nvweb_menu_action($breadcrumbs[$i]).'>'.$structure['dictionary'][$breadcrumbs[$i]].'</a>';

			if($i + 1 < count($breadcrumbs))
				$out .= $vars['separator'];
		}
	}
	
	return $out;
}

function nvweb_breadcrumbs_parent($category_id)
{
	$parent = 0;

	$category = new structure();
	$category->load($category_id);
	$parent = $category->parent;	
	
	return $parent;
}
?>