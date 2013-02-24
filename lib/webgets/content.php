<?php
require_once(NAVIGATE_PATH.'/lib/external/force-utf8/Encoding.php');

function nvweb_content($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $template;
	global $structure;
	
	$out = '';	
	switch(@$vars['mode'])
	{
		case 'title':
			if($current['type']=='structure')
			{
                $rs = nvweb_content_items($current['object']->id, true, 1);
				$texts = webdictionary::load_element_strings('item', $rs[0]->id);
				$out = $texts[$current['lang']]['title'];				
			}
			else
			{				
				$texts = webdictionary::load_element_strings($current['type'], $current['object']->id);				
				$out = $texts[$current['lang']]['title'];
			}
				
			if(!empty($vars['function']))	eval('$out = '.$vars['function'].'("'.$out.'");');
			break;

        case 'date':
		case 'date_post':
			$ts = $current['object']->date_created;
			// if date_created < date_published, choose the latest
			if($current['object']->date_published > $ts) 
				$ts = $current['object']->date_published;
			$out = nvweb_content_date_format(@$vars['format'], $ts);
			break;
			
		case 'date_created':
			$ts = $current['object']->date_created;
			$out = $vars['format'];
			$out = nvweb_content_date_format($out, $ts);			
			break;
			
		case 'comments':
			// display published comments number for the current item
			$out = nvweb_content_comments_count();
			break;
			
		case 'summary':
			$texts = webdictionary::load_element_strings('item', $current['object']->id);				
			$text = $texts[$current['lang']]['main'];
			$out = core_string_cut($text, 300, '&hellip;');
			break;
			
		case 'structure':

            $structure_id = 0;
            if($current['type']=='item')
                $structure_id = $current['object']->category;
            else if($current['type']=='structure')
                $structure_id = $current['object']->id;

			switch($vars['return'])
			{
				case 'path':
					$out = $structure['routes'][$structure_id];
					break;
					
				case 'title':
					$out = $structure['dictionary'][$structure_id];
					break;
					
				case 'action':
					$out = nvweb_menu_action($structure_id);
					break;
					
				default:
			}
			break;
		
		case 'section':
		default:
			if(empty($vars['section'])) $vars['section'] = 'main';
			$section = "section-".$vars['section'];

			if($current['type']=='item')
			{
				// check publishing is enabled
				$enabled = nvweb_object_enabled($current['object']);				
								
				if($_REQUEST['preview']=='true' && $current['navigate_session']==1)
				{
					// retrieve last saved text (is a preview request from navigate)
					$texts = webdictionary_history::load_element_strings('item', $current['object']->id, 'latest');	
					
					foreach($template->sections as $tsection)
					{
						if($tsection['code'] == $vars['section'])
						{
							switch($tsection['editor'])
							{
								case 'raw':
									$out = nl2br($texts[$current['lang']][$section]);
									break;								
								case 'html':
								case 'tinymce':								
								default:
									$out = $texts[$current['lang']][$section];	
									break;
							}
							break;	
						}
					}
				}
				else if($enabled)	// last approved text
				{
					$texts = webdictionary::load_element_strings('item', $current['object']->id);

					foreach($template->sections as $tsection)
					{
						if($tsection['code'] == $vars['section'])
						{
							switch($tsection['editor'])
							{
								case 'raw':
									$out = nl2br($texts[$current['lang']][$section]);
									break;								
								case 'html':
								case 'tinymce':								
								default:
									$out = $texts[$current['lang']][$section];	
									break;
							}
							break;	
						}
					}
				}
			}
			else if($current['type']=='structure')
			{
                $rs = nvweb_content_items($current['object']->id);

				foreach($rs as $category_item)
				{
					$enabled = nvweb_object_enabled($category_item);

					if(!$enabled)
						continue;
					else
					{
						$texts = webdictionary::load_element_strings('item', $category_item->id);

                        foreach($template->sections as $tsection)
                        {
                            if($tsection['code'] == $vars['section'])
                            {
                                switch($tsection['editor'])
                                {
                                    case 'raw':
                                        $texts[$current['lang']][$section] = nl2br($texts[$current['lang']][$section]);
                                        break;
                                    case 'html':
                                    case 'tinymce':
                                    default:
                                        $texts[$current['lang']][$section] = $texts[$current['lang']][$section];
                                        break;
                                }
                                break;
                            }
                        }

						$out .= '<div id="navigate-content-'.$category_item->id.'">'.$texts[$current['lang']][$section].'</div>';
					}
				}
			}
			$out = nvweb_content_fix_paths($out);
			break;
	}

	return $out;
}

// change navigate_download.php paths to base_domain/object uris
function nvweb_content_fix_paths($in)
{
	$regex = '/https?\:\/\/[^\" ]+/i';
    preg_match_all($regex, $in, $data);
	
	$out = $in;
	$nv_download = basename(NAVIGATE_DOWNLOAD);

	if(is_array($data[0]))
	{
		foreach($data[0] as $url)
		{
            $url_decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
			$url_parsed = parse_url($url_decoded);

			if(strpos($url_parsed['path'], $nv_download) !== false)
			{
                $new_url = NVWEB_OBJECT.'?'.$url_parsed['query'];
				$out = str_replace($url, $new_url, $out, $c);
			}
		}
	}

    return $out;
}

function nvweb_content_comments_count($object_id=NULL)
{
	global $DB;
	global $website;
	global $current;

    $element = $current['object'];
    if($current['type']=='structure')
    {
        if(empty($current['structure_elements']))
            $current['structure_elements'] = $element->elements();
        $element = $current['structure_elements'][0];
    }

	if(empty($object_id))
		$object_id = $element->id;
	
	$DB->query('SELECT COUNT(*) as total
				  FROM nv_comments
				 WHERE website = '.protect($website->id).'
				   AND item = '.protect($object_id).'
				   AND status = 0'
				);
													
	$out = $DB->result('total');
	
	return $out[0];
}

function nvweb_content_date_format($format="", $ts)
{
    global $website;
    global $session;

    $out = '';

    setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

    if(empty($format))
        $out = date($website->date_format, $ts);
    else if(strpos($format, '%day')!==false || strpos($format, '%month')!==false || strpos($format, '%year4'))
    {
        // deprecated: used until Navigate CMS 1.6.7
        $out = str_replace('%br', '<br />', $format);
        $out = str_replace('%day', date("d", $ts), $out);
        $out = str_replace('%month_abr', Encoding::toUTF8(strtoupper(strftime("%b", $ts))), $out);
        $out = str_replace('%month', date("m", $ts), $out);
        $out = str_replace('%year4', date("Y", $ts), $out);
    }
    else
    {
        $out = Encoding::toUTF8(strftime($format, $ts));
    }

	return $out;
}

function nvweb_content_items($categories=array(), $only_published=false, $max=NULL)
{
    global $website;
    global $DB;

    if(!is_array($categories))
        $categories = array($categories);

    $where = ' category IN ('.implode(",", $categories).')
               AND embedding = 1';

    // TODO: add "access" and "permission" checks, add orderby as nvlists
    if($only_published)
        $where .= ' AND (date_published = 0 OR date_published < '.core_time().')
                    AND (date_unpublish = 0 OR date_unpublish > '.core_time().')';

    if(!empty($max))
        $limit = 'LIMIT '.$max;

    $DB->query('
            SELECT *
            FROM nv_items
            WHERE '.$where.'
            ORDER BY date_created ASC
            '.$limit
    );
    $rs = $DB->result();

    return $rs;
}

?>