<?php
require_once(NAVIGATE_PATH.'/lib/external/force-utf8/Encoding.php');

function nvweb_archive($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $webgets;
	
	$webget = "archive";

	$out = array();

    $permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);
    $access     = (!empty($current['webuser'])? 1 : 2);

	if(empty($vars['categories']))
	{
		if($current['type']=='structure')
			$categories = array($current['id']);
		else
			$categories = array($current['object']->category);

        $categories = nvweb_menu_get_children($categories);
	}
	else if(!empty($vars['categories']))
	{
        if(!is_numeric($vars['categories']) && strpos($vars['categories'], ',')===false)
        {
            // we want to get the categories from a specific property of the current page
            $categories = nvweb_properties(array(
                'property'	=> 	$vars['categories']
            ));

            if(empty($categories) && (@$vars['nvlist_parent_vars']['source'] == 'block_group'))
            {
                $categories = nvweb_properties(array(
                    'mode'	    =>	'block_group_block',
                    'property'  => $vars['categories'],
                    'id'        =>	$vars['nvlist_parent_item']->id,
                    'uid'       => $vars['nvlist_parent_item']->uid
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

        if($vars['children']=='true')
            $categories = nvweb_menu_get_children($categories);
	}

    if($vars['search_page_type']=='theme')
    {
        $archive_url = $website->absolute_path() . '/nvsearch';
    }
    else
        $archive_url = nvweb_source_url($vars['search_page_type'], $vars['search_page_id']);

    if(strpos($vars['nvweb_html'], 'jquery')===false)
		$out[] = '<script language="javascript" type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>';

    // retrieve posts number by year, month, and...
    // checking if there are available in the current language (items must have custom paths assigned)
    $DB->query('
        SELECT COUNT(i.id) AS total,
               MONTH(FROM_UNIXTIME(COALESCE(NULLIF(i.date_to_display, 0), i.date_created))) as month,
               YEAR(FROM_UNIXTIME(COALESCE(NULLIF(i.date_to_display, 0), i.date_created))) as year
          FROM nv_items i
         WHERE i.website = '.$website->id.'
           AND i.permission <= '.$permission.'
           AND (i.date_published = 0 OR i.date_published < '.core_time().')
           AND (i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
           AND i.category IN('.implode(",", $categories).')
           AND (i.access = 0 OR i.access = '.$access.')
           AND 0 < ( SELECT COUNT(p.id)
                       FROM nv_paths p
                      WHERE p.website = '.$website->id.'
                        AND p.type = "item"
                        AND p.object_id = i.id
                        AND p.lang = "'.$current['lang'].'" )
         GROUP BY year, month
         ORDER BY year DESC, month DESC
    ');

    $dataset = $DB->result();

	switch(@$vars['mode'])
	{	
		case 'month':
            $out[] = nvweb_archive_render('month', $dataset, $archive_url, $categories);
			break;
			
		case 'year':
            $type = 'year';
            if($vars['collapsed']=='true')
                $type = 'year-collapsed';
            $out[] = nvweb_archive_render($type, $dataset, $archive_url, $categories);
			break;
			
		case 'adaptive':
        default:
            // let the webget decide the render type
            // ---> less or equal than 12 months in the list: month view
            if(count($dataset) <= 12)
                $out[] = nvweb_archive_render('month', $dataset, $archive_url, $categories);
            else // year view
                $out[] = nvweb_archive_render('year', $dataset, $archive_url, $categories);
            break;
    }

    $out = implode("\n", $out);
	
	return $out;
}

function nvweb_archive_render($type, $dataset, $archive_url, $categories)
{
    global $website;
    global $session;
    $out = array();

    setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

    if($type=='year' || $type=='year-collapsed')
    {
        $year_months = array();
        $year_stats = array();
        foreach($dataset as $row)
        {
            $year_months[$row->year][] =
                '<div>
                    <a href="'.$archive_url.'?archive='.$row->year.'-'.$row->month.'-'.implode(',', $categories).'">'.
                        Encoding::toUTF8(ucfirst(strftime('%B', mktime(0,0,0,$row->month,1,2000)))).' ('.$row->total.')
                    </a>
                 </div>';
            $year_stats[$row->year]['total'] += $row->total;
        }

        $first = ''; // default: show months of the first year in the list
        if($type=='year-collapsed') // alternative: hide months for the first year, too
            $first = 'display: none;';

        foreach($year_months as $year => $months)
        {
            $out[] = '<div class="nv-year"><a href="#" style=" display: block;" onclick=" return false; ">&raquo; '.$year.' ('.$year_stats[$year]['total'].')</a></div>';
            $out[] = '<div style="'.$first.' margin-left: 20px;" class="nv-year-months">';
            $out[] = implode("\n", $months);
            $out[] = '</div>';
            $first = 'display: none;';
        }

        nvweb_after_body('js', '
            jQuery(".nv-year").on("click", function() { $(this).next().toggle() });
        ');
    }
    else if($type=='month')
    {
        foreach($dataset as $row)
        {
            $out[] =
                '<div>
                    <a href="'.$archive_url.'?archive='.$row->year.'-'.$row->month.'-'.implode(',', $categories).'">'.
                        Encoding::toUTF8(ucfirst(strftime('%B', mktime(0,0,0,$row->month,1,2000)))).' '.$row->year.' ('.$row->total.')
                    </a>
                 </div>';
        }
    }

    $out = implode("\n", $out);
    return $out;
}

?>