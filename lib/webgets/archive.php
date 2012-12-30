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

    $permission = (!empty($_SESSION['APP_USER'])? 1 : 0);
    $access     = (!empty($current['webuser'])? 1 : 2);

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

    if($vars['search_page_type']=='theme')
    {
        $archive_url = $website->absolute_path() . '/nvsearch';
    }
    else
        $archive_url = nvweb_source_url($vars['search_page_type'], $vars['search_page_id']);

    if(strpos($vars['nvweb_html'], 'jquery')===false)
		$out[] = '<script language="javascript" type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>';

    // retrieve posts number by year and month
    $DB->query('
        SELECT COUNT(id) AS total,
               MONTH(FROM_UNIXTIME(GREATEST(date_published, date_created))) as month,
               YEAR(FROM_UNIXTIME(GREATEST(date_published, date_created))) as year
          FROM nv_items
         WHERE website = '.$website->id.'
           AND permission <= '.$permission.'
           AND (date_published = 0 OR date_published < '.core_time().')
           AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
           AND category IN('.implode(",", $categories).')
           AND (access = 0 OR access = '.$access.')
         GROUP BY year, month
         ORDER BY year DESC, month DESC
    ');

    $dataset = $DB->result();

	switch(@$vars['mode'])
	{	
		case 'month':
            $out[] = nvweb_archive_render('month', $dataset, $archive_url);
			break;
			
		case 'year':
            $out[] = nvweb_archive_render('year', $dataset, $archive_url);
			break;
			
		case 'adaptive':
        default:
            // let the webget decide the render type
            // ---> less or equal than 12 months in the list: month view
            if(count($dataset) <= 12)
                $out[] = nvweb_archive_render('month', $dataset, $archive_url);
            else // year view
                $out[] = nvweb_archive_render('year', $dataset, $archive_url);
            break;
    }

    $out = implode("\n", $out);
	
	return $out;
}

function nvweb_archive_render($type, $dataset, $archive_url)
{
    global $website;
    global $session;
    $out = array();

    setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

    if($type=='year')
    {
        $year_months = array();
        $year_stats = array();
        foreach($dataset as $row)
        {
            $year_months[$row->year][] =
                '<div>
                    <a href="'.$archive_url.'?archive='.$row->year.'-'.$row->month.'">'.
                        Encoding::toUTF8(ucfirst(strftime('%B', mktime(0,0,0,$row->month,1,2000)))).' ('.$row->total.')
                    </a>
                 </div>';
            $year_stats[$row->year]['total'] += $row->total;
        }

        $first = '';
        foreach($year_months as $year => $months)
        {
            $out[] = '<div class="nv-year"><a href="#" style=" display: block;" onclick=" return false; ">&raquo; '.$year.' ('.$year_stats[$year]['total'].')</a></div>';
            $out[] = '<div style="'.$first.' margin-left: 20px;" class="nv-year-months">';
            $out[] = implode("\n", $months);
            $out[] = '</div>';
            $first = 'display: none;';
        }

        nvweb_after_body('js', '
            jQuery(".nv-year").bind("click", function() { $(this).next().toggle() });
        ');
    }
    else if($type=='month')
    {
        foreach($dataset as $row)
        {
            $out[] =
                '<div>
                    <a href="'.$archive_url.'?archive='.$row->year.'-'.$row->month.'">'.
                        Encoding::toUTF8(ucfirst(strftime('%B', mktime(0,0,0,$row->month,1,2000)))).' '.$row->year.' ('.$row->total.')
                    </a>
                 </div>';
        }
    }

    $out = implode("\n", $out);

    return $out;
}

?>