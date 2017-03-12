<?php
require_once(NAVIGATE_PATH.'/lib/packages/update/update.class.php');
require_once(NAVIGATE_PATH.'/lib/webgets/breadcrumbs.php');

function nvweb_metatags($vars=array())
{
	global $website;
	global $current;
	global $DB;
    global $structure;
    global $events;

	// process page title and (to do: get specific metatags)
	$section = '';

    $separator = ' | ';
    if(!empty($vars['title_separator']))
        $separator = $vars['title_separator'];
		
	switch($current['type'])
	{
		case 'item':
            $section = 	$DB->query_single(
                'text',
                'nv_webdictionary',
                ' node_type = '.protect($current['type']).' AND
				    node_id = '.protect($current['object']->id).' AND
					subtype = '.protect('title').' AND
					website = '.$website->id.' AND
					   lang = '.protect($current['lang'])
            );
            $section = $separator.$section;
            break;

		case 'structure':
            $breadcrumbs = nvweb_breadcrumbs(
                array(
                    'separator' => $separator,
                    'links' => 'false'
                )
            );
            $section = $separator.$breadcrumbs;
			break;
					
		default:
				
	}

    // global website metatags
    $metatags = $website->metatags;
    if(is_array($metatags))
        $metatags = $metatags[$current['lang']];

    if(!empty($website->metatag_description[$current['lang']]))
        $metatags .= "\n".'<meta name="language" content="'.$current['lang'].'" />'."\n";

    if(!empty($website->metatag_description[$current['lang']]))
        $metatags .= "\n".'<meta name="description" content="'.$website->metatag_description[$current['lang']].'" />'."\n";

	// retrieve content tags and add it to the global metatags of the website	
    $tags_website = str_replace(', ', ',', $website->metatag_keywords[$current['lang']]);
    $tags_website = explode(',', $tags_website);
    $tags_website = array_filter($tags_website);

    $tags_content = webdictionary::load_element_strings($current['type'], $current['object']->id);
    $tags_content = str_replace(', ', ',', @$tags_content[$current['lang']]['tags']);
    $tags_content = explode(',', $tags_content);
    $tags_content = array_filter($tags_content);

    $tags = array_merge($tags_website, $tags_content);
    $tags = implode(',', $tags);

	if(strpos($metatags, '<meta name="keywords" content="')!==FALSE)
	{
        $metatags = str_replace(
            '<meta name="keywords" content="',
            '<meta name="keywords" content="'.$tags,
            $metatags
        );
	}
	else
	{
        $metatags .= '<meta name="keywords" content="'.$tags.'" />';
	}
	
	if(@$vars['generator']!='false')
	{
		$current_version = update::latest_installed();
        $metatags .= "\n".'<meta name="generator" content="Navigate CMS '.$current_version->version.'" />';
	}
	
	if($website->favicon > 0)
	{
		$favicon = NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$website->favicon.'&amp;disposition=inline';
        $metatags .= "\n".'<link rel="shortcut icon" href="'.$favicon.'" />';
	}
	
	// website public feeds
	$DB->query('SELECT id FROM nv_feeds 
				 WHERE website = '.$website->id.'
				   AND permission = 0
				   AND enabled = 1');
				   
	$feeds = $DB->result('id');
	
	for($f=0; $f < count($feeds); $f++)
	{
		$feed = new feed();
		$feed->load($feeds[$f]);
		
		if(strpos(strtolower($feed->format), 'rss')!==false)
			$mime = 'application/rss+xml';
		else if(strpos(strtolower($feed->format), 'atom')!==false)
			$mime = 'application/atom+xml';
		else
			$mime = 'text/xml';

        $metatags .= "\n".'<link rel="alternate" type="'.$mime.'" title="'.$feed->dictionary[$current['lang']]['title'].
                              '" href="'.$website->absolute_path().$feed->paths[$current['lang']].'" />';
	}

	$out = '<title>'.$website->name.$section.'</title>'."\n";
	$out.= $metatags;
		
	if(!empty($website->tracking_scripts) && empty($_SESSION['APP_USER#'.APP_UNIQUE]))
		nvweb_after_body('html', $website->tracking_scripts);

	if(!empty($website->additional_scripts))
		nvweb_after_body('html', $website->additional_scripts);

    if(!empty($website->additional_styles))
		nvweb_after_body('html', $website->additional_styles);

    $events->trigger(
        'metatags',
        'render',
        array(
            'out' => &$out,
            'default_title' => $website->name.$section,
            'section' => $section
        )
    );

	return $out;
}
?>