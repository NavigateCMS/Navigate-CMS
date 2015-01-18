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
            $ts = $current['object']->date_to_display;
            // if no date, return nothing
            if(!empty($ts))
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

        case 'views':
            $out = $current['object']->views;
            break;
			
		case 'summary':
            $length = 300;
            if(!empty($vars['length']))
                $length = intval($vars['length']);
			$texts = webdictionary::load_element_strings('item', $current['object']->id);				
			$text = $texts[$current['lang']]['main'];
			$out = core_string_cut($text, 300, '&hellip;');
			break;

        case 'author':
            if(!empty($current['object']->author))
            {
                $nu = new user();
                $nu->load($current['object']->author);
                $out = $nu->username;
                unset($nu);
            }

            if(empty($out))
                $out = $website->name;
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

        case 'tags':
            $tags = array();
            $search_url = NVWEB_ABSOLUTE.'/nvtags?q=';
            $ids = array();
            if(empty($vars['separator']))
                $vars['separator'] = ' ';

            if(!empty($vars['id']))
            {
                $itm = new item();
                $itm->load($vars['id']);
                $enabled = nvweb_object_enabled($itm);
                if($enabled)
                {
                    $texts = webdictionary::load_element_strings('item', $itm->id);
                    $itags = explode(',', $texts[$current['lang']]['tags']);
                    if(!empty($itags))
                    {
                        for($i=0; $i < count($itags); $i++)
                            $tags[$i] = '<a class="item-tag" href="'.$search_url.$itags[$i].'">'.$itags[$i].'</a>';
                    }
                }
            }
            else if($current['type']=='item')
            {
                // check publishing is enabled
                $enabled = nvweb_object_enabled($current['object']);

                if($enabled)
                {
                    $texts = webdictionary::load_element_strings('item', $current['object']->id);
                    $itags = explode(',', $texts[$current['lang']]['tags']);
                    if(!empty($itags))
                    {
                        for($i=0; $i < count($itags); $i++)
                            $tags[$i] = '<a class="item-tag" href="'.$search_url.$itags[$i].'">'.$itags[$i].'</a>';
                    }
                }
            }
            else if($current['type']=='structure')
            {
                $rs = nvweb_content_items($current['object']->id);

                foreach($rs as $category_item)
                {
                    $enabled = nvweb_object_enabled($category_item);

                    if($enabled)
                    {
                        $texts = webdictionary::load_element_strings('item', $current['object']->id);
                        $itags = explode(',', $texts[$current['lang']]['tags']);
                        if(!empty($itags))
                        {
                            for($i=0; $i < count($itags); $i++)
                            {
                                $tags[$i] = '<a class="item-tag" href="'.$search_url.$itags[$i].'">'.$itags[$i].'</a>';
                            }
                        }
                    }
                }
            }
            $out = implode($vars['separator'], $tags);
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
                                        // we don't need to change a thing
                                        // $texts[$current['lang']][$section] = $texts[$current['lang']][$section];
                                        break;
                                }
                                break;
                            }
                        }

						$out .= '<div id="navigate-content-'.$category_item->id.'-'.$section.'">'.$texts[$current['lang']][$section].'</div>';
					}
				}
			}
			break;
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
    global $current;
    global $webuser;

    if(!is_array($categories))
        $categories = array(intval($categories));

    $where = ' website = '.$website->id.'
               AND category IN ('.implode(",", $categories).')
               AND embedding = 1';

    if($only_published)
        $where .= ' AND (date_published = 0 OR date_published < '.core_time().')
                    AND (date_unpublish = 0 OR date_unpublish > '.core_time().')';

    // status (0 public, 1 private (navigate cms users), 2 hidden)
    $permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);
    $where .= ' AND permission <= '.$permission;

    // access permission (0 public, 1 web users only, 2 unidentified users, 3 selected web user groups)
    $access = 2;
    $access_extra = '';
    if(!empty($current['webuser']))
    {
        $access = 1;
        if(!empty($webuser->groups))
        {
            $access_groups = array();
            foreach($webuser->groups as $wg)
            {
                if(empty($wg))
                    continue;
                $access_groups[] = 'groups LIKE "%g'.$wg.'%"';
            }
            if(!empty($access_groups))
                $access_extra = ' OR (access = 3 AND ('.implode(' OR ', $access_groups).'))';
        }
    }

    $where .= ' AND (access = 0 OR access = '.$access.$access_extra.')';

    if(!empty($max))
        $limit = 'LIMIT '.$max;

    $DB->query('
            SELECT *, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate
            FROM nv_items
            WHERE '.$where.'
            ORDER BY pdate ASC
            '.$limit
    );
    $rs = $DB->result();

    return $rs;
}

?>