<?php
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');

function nvweb_breadcrumbs($vars=array())
{
	global $current;
	global $structure;
    global $events;

	$out = '';

	if(!isset($vars['separator']))
    {
        $vars['separator'] = '&nbsp;&gt;&nbsp;';
    }

    if($vars['separator']=='image')
    {
        $vars['separator'] = '<img src="'.$vars['image'].'" />';
    }

    if($vars['separator']=='base64')
    {
        $vars['separator'] = base64_decode($vars['base64']);
    }
	
	if(empty($vars['from']))
    {
        $vars['from'] = 0;
    }

	// 2 options: we are displaying an element or a category
	$breadcrumbs = array();
	
	if($current['type']=='structure')	// the current category is included
	{
		$breadcrumbs[] = $current['id'];
	}
	else if(in_array($current['type'], array('item', 'element', 'product'))) // start from item main category
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

        $events->trigger(
            'breadcrumbs',
            'hierarchy',
            array(
                'hierarchy' => &$breadcrumbs
            )
        );
		
		for($i = $vars['from']; $i < count($breadcrumbs); $i++)
		{
		    $text = $structure['dictionary'][$breadcrumbs[$i]];
            $text = core_special_chars($text);

            if($vars['links']=='false')
            {
                $breadcrumb_item = $text;
            }
            else
            {
                $breadcrumb_item = '<a '.nvweb_menu_action($breadcrumbs[$i]).'>'.$text.'</a>';
            }

            if(isset($vars['wrapper']))
            {
                switch($vars['wrapper'])
                {
                    case 'li':
                        $breadcrumb_item = '<li>'.$breadcrumb_item.'</li>';
                        break;

                    case 'div':
                        $breadcrumb_item = '<div>'.$breadcrumb_item.'</div>';
                        break;

                    default:
                }

                $out .= $breadcrumb_item;
            }
            else
            {
                $out .= $breadcrumb_item;
            }

			if($i + 1 < count($breadcrumbs))
            {
                $out .= $vars['separator'];
            }
		}
	}
	
	return $out;
}

function nvweb_breadcrumbs_parent($category_id)
{
	$category = new structure();
	$category->load($category_id);
	return $category->parent;
}

?>