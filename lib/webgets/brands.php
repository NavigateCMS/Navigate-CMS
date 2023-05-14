<?php
require_once(NAVIGATE_PATH.'/lib/packages/brands/brand.class.php');

function nvweb_brands($vars=array())
{
	global $current;
	global $structure;
    global $events;

	$out = '';

	switch($vars['mode'])
    {
        case 'alphabetical_grouped':
        default:
            $out = nvweb_brands_alphabetical_grouped($vars);
            break;
    }

	return $out;
}

function nvweb_brands_alphabetical_grouped($vars)
{
    $out = array();

    $params = array(
        'orderby' => 'ORDER BY name ASC',
        'offset' => 0,
        'items' => 9999
    );

    list($brands, $total) = nvweb_list_source_brand($vars, $params);

    $groups = array();

    foreach($brands as $b)
    {
        $initial = substr($b->name, 0, 1);
        $initial = strtoupper($initial);

        if(!isset($groups[$initial]))
        {
            $groups[$initial] = array();
        }

        $groups[$initial][] = $b;
    }

    foreach($groups as $initial => $brands)
    {
        $out[] = '<div>';
        $out[] = '  <a name="brands_'.$initial.'">'.$initial.'</a>';
        $out[] = '  <ul data-initial="'.$initial.'">';

        foreach($brands as $brand)
        {
            $link = $brand->url;
            if($vars['link_prefix'])
            {
                $link = $vars['link_prefix'] . $brand->id;
            }

            $out[] = '      <li data-brand-id="'.$brand->id.'"><a href="'.$link.'">'.$brand->name.'</a></li>';
        }

        $out[] = '  </ul>';
        $out[] = '</div>';
    }

    $out = implode("\n", $out);

    return $out;
}

?>