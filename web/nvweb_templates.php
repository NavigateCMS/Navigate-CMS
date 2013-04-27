<?php

/**
 * Return the contents of the physical template file from the website private folder or from theme folder.
 *
 * The file is determined by the URL requested and the information in the database.
 *
 * @return string $template
 */
function nvweb_template_load()
{
	global $current;
	global $DB;
	global $website;
	
	$template = '';

	if(!empty($current['template']))
	{
		$template = new template();
		$template->load($current['template']);

		if(!$template->enabled) 
			nvweb_clean_exit();
			
		if($template->permission == 2) 
			nvweb_clean_exit();
		else if($template->permission == 1 && empty($_SESSION['APP_USER']))	
			nvweb_clean_exit();
			
		if(file_exists($template->file))
			$template->file_contents = @file_get_contents($template->file);	// from theme
		else		
			$template->file_contents = @file_get_contents(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$template->file);
	}
	
	return $template;
}

/**
 * Loads the webdictionary for the current website
 *
 * The function takes the current language for the source
 *
 * @return array $dictionary
 */
function nvweb_dictionary_load()
{
	global $DB;
	global $current;
	global $website;
	global $theme;
	
	$dictionary = array();	
	// the dictionary is an array merged from the following sources (- to + preference)
	// theme dictionary (json)
	// theme dictionary on database
	// webdictionary custom entries
	
	// theme dictionary
	if(!empty($theme))
		$theme->t(); // force theme dictionary load
	
	if(!empty($theme->dictionary))
		$dictionary = $theme->dictionary;

	// webdictionary custom entries
	$DB->query('SELECT node_id, text
				  FROM nv_webdictionary 
				 WHERE node_type = "global"
				   AND lang = '.protect($current['lang']).'
				   AND website = '.$website->id.'
				 UNION
				 SELECT subtype AS node_id, text
				 FROM nv_webdictionary
				 WHERE node_type = "theme"
				   AND theme = '.protect($website->theme).' 
				   AND lang = '.protect($current['lang']).'
				   AND website = '.$website->id
			   );		
						
	$data = $DB->result();
	
	if(!is_array($data)) $data = array();
	
	foreach($data as $item)
	{
		$dictionary[$item->node_id] = $item->text;
	}
	
	return $dictionary;
}

/**
 * Sets or prints a certain code that will be placed just before closing the </body> tag
 *
 * @param string $type "js" or "html", depending on the code type to return / append
 * @param string $code actual source code that will be appended, if empty all source code saved will be returned
 * @return string the source code previously appended or empty
 */
function nvweb_after_body($type="js", $code="")
{
	global $current;
	
	if(empty($code))
	{
		if(!empty($current[$type.'_after_body']))
		{
			if($type=='js')
			{
				array_unshift($current[$type.'_after_body'], '<script language="javascript" type="text/javascript">');
				$current[$type.'_after_body'][] = '</script>';
			}
			return implode("\n", $current[$type.'_after_body']);	
		}
	}
	else
		$current[$type.'_after_body'][] = $code;

	return "";
}

/**
 * Parses a template looking for nv tags and replacing them with the generated HTML code
 *
 * @param $template string HTML code to parse
 * @return string HTML page generated
 */
function nvweb_template_parse($template)
{
	global $dictionary;
	global $DB;
	global $current;
	global $website;
    global $structure;
	global $session;
	
	$html = $template;
	
	// now parse autoclosing tags
	$tags = nvweb_tags_extract($html, 'nv', true, true, 'UTF-8');
	
	foreach($tags as $tag)
	{
		$content = '';
		
		switch($tag['attributes']['object'])
		{
			// MAIN OBJECT TYPES
			case 'nvweb':
			case 'widget':
			case 'webget':
				// webgets on lib/webgets have priority over private/webgets
				nvweb_webget_load($tag['attributes']['name']);
				
				$fname = 'nvweb_'.$tag['attributes']['name'];

				$tag['attributes']['nvweb_html'] = $html;	// always pass the current buffered output to the webget
				
				if(function_exists($fname))
					$content = $fname($tag['attributes']);			
				break;
				
			case 'root':
				$content = NVWEB_ABSOLUTE;
				break;

			case 'url':
                $content = '';
                if(!empty($tag['attributes']['lang']))
                    $lang = $tag['attributes']['lang'];
                else
                    $lang = $current['lang'];

				if(!empty($tag['attributes']['type']) && !empty($tag['attributes']['id']))
				{
					$url = nvweb_source_url($tag['attributes']['type'], $tag['attributes']['id'], $lang);
															   
					if(!empty($url)) $content .= $url;
				}
                else if(!empty($tag['attributes']['type']) && empty($tag['attributes']['id']))
                {
                    // get structure parent for this element and return its path
                    if($current['type']=='structure')
                    {
                        $category = $current['object']->parent;
                        if(empty($category))
                            $category = $current['object']->id;
                    }
                    else
                        $category = $current['object']->category;

                    $url = nvweb_source_url($tag['attributes']['type'], $category, $lang);

                    if(!empty($url)) $content .= $url;
                }
				else
				{
					$content .= '/'.$current['route'];	
				}

                $content = nvweb_prepare_link($content);
				break;
				
			case 'dict':
			case 'dictionary':
                if(!empty($tag['attributes']['type']))
                {
                    if($tag['attributes']['type']=='structure' || $tag['attributes']['type']=='category')
                    {
                        // force loading dictionary for all elements in structure (for the current language)
                        nvweb_menu_load_dictionary();
                        $content = $structure['dictionary'][$tag['attributes']['id']];
                    }
                    else if($tag['attributes']['type']=='item')
                    {
                        $tmp = webdictionary::load_element_strings('item', $tag['attributes']['id']);
                        $content = $tmp[$current['lang']]['title'];
                    }
                }
                else
                    $content = $dictionary[$tag['attributes']['id']];

                if(empty($content))
                    $content = $tag['attributes']['label'];
                if(empty($content))
                    $content = $tag['attributes']['default'];
				break;
				
			case 'request':
                if(!empty($tag['attributes']['name']))
				    $content = $_REQUEST[$tag['attributes']['name']];
                else // deprecated: use "request" as attribute [will be removed on navigate cms 2.0]
                    $content = $_REQUEST[$tag['attributes']['request']];
				break;				
				
			case 'constant':
			case 'variable':
				switch($tag['attributes']['name'])
				{
                    case "structure":
					case "category":
						// retrieve the category ID from current session
						$tmp = NULL;
						if($current['type']=='structure')
							$tmp = $current['id'];
						else if(!empty($current['category']))
							$tmp = $current['category'];
						else if(!empty($current['object']->category))
							$tmp = $current['object']->category;
														
						if(empty($tmp))
							$content = '';
						else
						{
							$content = $DB->query_single('text', 'nv_webdictionary', ' 
																   node_type = "structure" 
															   AND subtype = "title"
															   AND node_id = '.$tmp.' 
															   AND lang = '.protect($current['lang']).'
															   AND website = '.$website->id);								
						}
						break;
						
					case "year":
						$content = date('Y');
						break;

                    case "website_name":
                        $content = $website->name;
                        break;
						
					case "lang_code":
						$content = $current['lang'];
						break;
												
					default:
						break;
				}
				break;
				
			case 'php':
				eval('$content = '.$tag['attributes']['code'].';');
				break;
				
			default: 
				//var_dump($tag['attributes']['object']);
				break; 
		}
		
		$html = str_replace($tag['full_tag'], $content, $html);
	}
	
	return $html;	
}

/**
 * Parse special Navigate CMS tags like:
 * <ul>
 * <li>&lt;nv object="include" file="" id="" /&gt;</li>
 * <li>curly bracket tags {{nv object=""}}</li>
 * </ul>
 *
 * Generate the final HTML code for these special tags or convert them
 * to a simpler nv tags.
 *
 * @param $html
 * @return mixed
 */
function nvweb_template_parse_special($html)
{
	global $website;

    $changed = false;

    // translate "{{nv }}" tags to "<nv />" version
    preg_match_all("/{{nv\s([^}]+)}}/ixsm", $html, $curly_tags);
    for($c=0; $c < count($curly_tags[0]); $c++)
    {
        $tmp = str_replace(array('{{nv ', '}}'), array('<nv ', ' />'), $curly_tags[0][$c]);
        $html = str_ireplace($curly_tags[0][$c], $tmp, $html);
        $changed = true;
    }

    // translate "{{nvlist }}" tags to "<nvlist />" version
    preg_match_all("/{{nvlist\s([^}]+)}}/ixsm", $html, $curly_tags);
    for($c=0; $c < count($curly_tags[0]); $c++)
    {
        $tmp = str_replace(array('{{nvlist ', '}}'), array('<nvlist ', ' />'), $curly_tags[0][$c]);
        $html = str_ireplace($curly_tags[0][$c], $tmp, $html);
        $changed = true;
    }

    if($changed)
        return nvweb_template_parse_special($html);

    // parse includes (we must do it before parsing list or search)
    $tags = nvweb_tags_extract($html, 'nv', true, true, 'UTF-8');
    foreach($tags as $tag)
	{
        $content = '';
        $changed = false;
        $tag['length'] = strlen($tag['full_tag']);

        if($tag['attributes']['object']=='include')
        {
            $tid = $tag['attributes']['id'];
            $file = $tag['attributes']['file'];

            if(!empty($tid))
            {
                $template = new template();
                $template->load($tid);
                if($template->website == $website->id) // cross-website security
                {
                    $content = file_get_contents(NAVIGATE_PRIVATE.'/'.$website->id.'/templates/'.$template->file);
                }
            }
            else if(!empty($file))
            {
                $content = file_get_contents(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$file);
            }

            $html = substr_replace($html, $content, $tag['offset'], $tag['length']);
            $changed = true;
        }

        // if an object="include" has been found, we need to restart the parse_special tags function
        // as it may contain other "includes" or "{{nv" tags that need transformation
        if($changed)
        {
            $html = nvweb_template_parse_special($html);
            break;
        }
    }

    return $html;
}

/**
 * Parse Navigate CMS tags like:
 * <ul>
 * <li>&lt;nv object="list"&gt;&lt;/nv&gt;</li>
 * <li>&lt;nv object="search"&gt;&lt;/nv&gt;</li>
 * </ul>
 *
 * Generate the final HTML code for these special tags or convert them
 * to a simpler nv tags.
 *
 * @param $html
 * @return mixed
 */
function nvweb_template_parse_lists($html, $process_delayed=false)
{
    global $current;

    if($process_delayed)
    {
        // time to process delayed nvlists and nvsearches
        foreach($current['delayed_nvlists'] as $uid => $vars)
        {
            $content = nvweb_list($vars);
            $html = str_replace('<!--#'.$uid.'#-->', $content, $html);
        }

        foreach($current['delayed_nvsearches'] as $uid => $vars)
        {
            $content = nvweb_search($vars);
            $html = str_replace('<!--#'.$uid.'#-->', $content, $html);
        }

        return $html;
    }

	// parse special navigate tags (includes, lists, searchs...)
	$tags = nvweb_tags_extract($html, 'nv', true, true, 'UTF-8');	
	
	foreach($tags as $tag)
	{
		$content = '';
		$changed = false;

		switch($tag['attributes']['object'])
		{
			case 'list':
				$template_end = strpos($html, '</nv>', $tag['offset']);
				$tag['length'] = $template_end - $tag['offset'] + strlen('</nv>'); // remove tag characters
				$list = substr($html, ($tag['offset'] + strlen($tag['full_tag'])), ($tag['length'] - strlen('</nv>') - strlen($tag['full_tag'])));

                @include_once(NAVIGATE_PATH.'/lib/webgets/list.php');
				$vars = array_merge($tag['attributes'], array('template' => $list));

                if($tag['attributes']['delayed']=='true')
                {
                    $list_uid = uniqid('nvlist-');
                    $current['delayed_nvlists'][$list_uid] = $vars;
                    $html = substr_replace($html, '<!--#'.$list_uid.'#-->', $tag['offset'], $tag['length']);
                    $changed = true;
                    continue;
                }

				$content = nvweb_list($vars);
				
				$html = substr_replace($html, $content, $tag['offset'], $tag['length']);
				$changed = true;
				break;	

			case 'search':
				$template_end = strpos($html, '</nv>', $tag['offset']);
				$tag['length'] = $template_end - $tag['offset'] + strlen('</nv>'); // remove tag characters
				$search = substr($html, ($tag['offset'] + strlen($tag['full_tag'])), ($tag['length'] - strlen('</nv>') - strlen($tag['full_tag'])));
								
				@include_once(NAVIGATE_PATH.'/lib/webgets/search.php');				
				$vars = array_merge($tag['attributes'], array('template' => $search));

                if($tag['attributes']['delayed']=='true')
                {
                    $search_uid = uniqid('nvsearch-');
                    $current['delayed_nvsearches'][$search_uid] = $vars;
                    $html = substr_replace($html, '<!--#'.$search_uid.'#-->', $tag['offset'], $tag['length']);
                    $changed = true;
                    continue;
                }

                $content = nvweb_search($vars);
				
				$html = substr_replace($html, $content, $tag['offset'], $tag['length']);
				$changed = true;
				break;
		}
		
		if($changed)
		{
			// ok, we've found and processed ONE special tag
			// now the HTML has changed, so the original positions of the special <nv> tags have also changed
			// we must finish the current loop and start a new one
			$html = nvweb_template_parse_lists($html);
			break;
		}
	}

	return $html;
}

/**
 * Apply current website theme settings
 *
 * Example: <HORIZON_LOGO />    -->   Theme logo URL
 *
 * @param $template string HTML of the current page
 * @return string $template HTML of the current page with the theme settings applied
 */
function nvweb_theme_settings($template)
{
    global $website;

    if(!empty($website->theme))
    {
        nvweb_webget_load($website->theme);

        if(function_exists('nvweb_'.$website->theme))
        {
            $out = call_user_func(
                'nvweb_'.$website->theme,
                array(
                    'mode' => 'theme',
                    'html' => $template
                )
            );

            if(!empty($out))
               $template = $out;
        }
    }

    return $template;
}

/**
 * Returns the current visitor real IP or false if couldn't be located
 *
 * @return mixed|mixed Real IP of the current visitor or false
 */
function nvweb_real_ip()
{
     $ip = false;
     if(!empty($_SERVER['HTTP_CLIENT_IP']))
          $ip = $_SERVER['HTTP_CLIENT_IP'];

     if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
     {
          $ips = explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
          if($ip)
          {
               array_unshift($ips, $ip);
               $ip = false;
          }
          for($i = 0; $i < count($ips); $i++)
          {
               if(!preg_match("/^(10|172\.16|192\.168)\./i", $ips[$i]))
               {
                    if(version_compare(phpversion(), "5.0.0", ">="))
                    {
                         if(ip2long($ips[$i]) != false)
                         {
                              $ip = $ips[$i];
                              break;
                         }
                    }
                    else
                    {
                         if(ip2long($ips[$i]) != - 1)
                         {
                              $ip = $ips[$i];
                              break;
                         }
                    }
               }
          }
     }
     return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
}


/**
 * Determine the default language to show to the user reading its browser preferences
 *
 * Note: if the language is already setted in a cookie or in the session this function is never called
 *
 * @return string $lang 2-letter code of the language
 */
function nvweb_country_language()
{
    global $website;

    $lang = '';

    if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
    {
        preg_match_all( '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'],
            $lang_parse);

        if (count($lang_parse[1]))
        {
            $langs = array_combine($lang_parse[1], $lang_parse[4]);
            foreach ($langs as $lang => $val)
                if($val === '') $langs[$lang] = 1;
            arsort($langs, SORT_NUMERIC);
        }

        $found = false;

        foreach($langs as $language_browser => $val)
        {
            foreach($website->languages_list as $language_available)
            {
                if($language_available == $language_browser)
                {
                    $lang = $language_browser;
                    $found = true;
                    break;
                }
            }
            if($found)
                break;
        }
    }

    if(empty($lang))
    {
        // no user defined language matches the website available languages, so take the website's default one
        $lang = $website->languages_list[0];
    }

    return $lang;
}

/**
 * Apply some template tweaks to improve Navigate CMS theme developing experience like:
 *
 * <ul>
 * <li>Guess absolute paths to images, stylesheets and scripts (even on urls without http, "//")</li>
 * <li>Convert &lt;a rel="video"&gt; and &lt;a rel="audio"&gt;  to &lt;video&gt; and &lt;audio&gt; tags</li>
 * <li>Process &lt;img&gt; tags to generate optimized images</li>
 * <li>Add Navigate CMS content default styles</li>
 * </ul>
 *
 * @param $html string Original HTML template content
 * @return string HTML template tweaked
 */
function nvweb_template_tweaks($html)
{
	global $website;
	// apply some tweaks to the generated html code
	
	// tweak 1: try to make absolute all image, css and script paths not starting by http	
	if(!empty($website->theme))
		$website_absolute_path = NAVIGATE_URL.'/themes/'.$website->theme;
	else
		$website_absolute_path = $website->absolute_path(false);
	
	// stylesheets
	$tags = nvweb_tags_extract($html, 'link', NULL, true, 'UTF-8');
	foreach($tags as $tag)
	{		
		if(!isset($tag['attributes']['href'])) continue;	
		if(substr($tag['attributes']['href'], 0, 7)!='http://' &&
		   substr($tag['attributes']['href'], 0, 8)!='https://')
		{
            // treat "//" paths (without http or https)
            if(substr($tag['attributes']['href'], 0, 2)=='//')
                $src = $website->protocol.substr($tag['attributes']['href'], 2);
            else
			    $src = $website_absolute_path.'/'.$tag['attributes']['href'];

			$tag['new'] = '<link href="'.$src.'" ';
			foreach($tag['attributes'] as $name => $value)
			{
				if($name!='href') $tag['new'] .= $name.'="'.$value.'" ';
			}
			$tag['new'] .= '/>';
			
			$html = str_replace($tag['full_tag'], $tag['new'], $html);
		}
	}
	
	// scripts
	$tags = nvweb_tags_extract($html, 'script', NULL, true, 'UTF-8');
	foreach($tags as $tag)
	{
		if(!isset($tag['attributes']['src'])) continue;
		if(substr($tag['attributes']['src'], 0, 7)!='http://' && 
		   substr($tag['attributes']['src'], 0, 8)!='https://')
		{
            if(substr($tag['attributes']['src'], 0, 2)=='//')
                $src = $website->protocol.substr($tag['attributes']['src'], 2);
            else
                $src = $website_absolute_path.'/'.$tag['attributes']['src'];

			$tag['new'] = '<script src="'.$src.'" ';
			foreach($tag['attributes'] as $name => $value)
			{
				if($name!='src') $tag['new'] .= $name.'="'.$value.'" ';
			}
			$tag['new'] .= '></script>';
			
			$html = str_replace($tag['full_tag'], $tag['new'], $html);
		}
	}
	
	// images
	$tags = nvweb_tags_extract($html, 'img', NULL, true, 'UTF-8');	
				
	foreach($tags as $tag)
	{
		if(!isset($tag['attributes']['src'])) continue;
		if(substr($tag['attributes']['src'], 0, 7)!='http://' && 
		   substr($tag['attributes']['src'], 0, 8)!='https://' &&
		   substr($tag['attributes']['src'], 0, 5)!='data:')
		{
            if(substr($tag['attributes']['src'], 0, 2)=='//')
                $src = $website->protocol.substr($tag['attributes']['src'], 2);
            else
			    $src = $website_absolute_path.'/'.$tag['attributes']['src'];
			
			$tag['new'] = '<img src="'.$src.'" ';
			foreach($tag['attributes'] as $name => $value)
			{
				if($name!='src') $tag['new'] .= $name.'="'.$value.'" ';
			}			
			$tag['new'] .= '/>';
			
			$html = str_replace($tag['full_tag'], $tag['new'], $html);
		}
	}
	
	// tweak 2: convert <a rel="video"> to <video> and <a rel="audio"> to <audio> tags
	$tags = nvweb_tags_extract($html, 'a', NULL, true, 'UTF-8');

	foreach($tags as $tag)
	{
		if($tag['attributes']['rel']=='video' && $tag['attributes']['navigate']=='navigate')
		{
			$content = array();
			$content[] = '<video controls="controls">';
			$content[] = '	<source src="'.$tag['attributes']['href'].'" />';
			$content[] = '	'.$tag['full_tag'];
			$content[] = '</video>';
				
			$html = str_replace($tag['full_tag'], implode("\n", $content), $html);
		}
		
		if($tag['attributes']['rel']=='audio' && $tag['attributes']['navigate']=='navigate')
		{
			$content = array();
			$content[] = '<audio controls="controls">';
			$content[] = '	<source src="'.$tag['attributes']['href'].'" type="'.$tag['attributes']['type'].'" />';
			$content[] = '	'.$tag['full_tag'];
			$content[] = '</audio>';			
			
			$html = str_replace($tag['full_tag'], implode("\n", $content), $html);
		}		
	}
	
	// tweak 3: let navigate generate a resized image/thumbnail if width/height is given in the img tag
	$tags = nvweb_tags_extract($html, 'img', NULL, true, 'UTF-8');	
				
	foreach($tags as $tag)
	{
		if(!isset($tag['attributes']['src'])) continue;
		$src = $tag['attributes']['src'];
		
		$tag['new'] = '';

		foreach($tag['attributes'] as $name => $value)
		{
			if($name!='src')
                $tag['new'] .= $name.'="'.$value.'" ';
			
			if($name=='width' && strpos($src, '?')!==false)
				$src .= '&width='.$value;
				
			if($name=='height' && strpos($src, '?')!==false)
				$src .= '&height='.$value;	
		}
		
		$tag['new'] = '<img src="'.$src.'" '.$tag['new'].'/>';
		
		$html = str_replace($tag['full_tag'], $tag['new'], $html);
	}

    // tweak 4: add Navigate CMS content default styles
    $default_css = file_get_contents(NAVIGATE_URL.'/css/tools/tinymce.defaults.css');
    $default_css = str_replace(array("\n", "\r", "\s\s", "  "), " ", $default_css);
    $default_css = substr($default_css, strpos($default_css, '/* nvweb */')+11);
    $default_css = '<style type="text/css">'.$default_css.'</style>';
    $html = str_replace('</title>', "</title>\n\t".$default_css."\n", $html);

	return $html;
}

/**
 * Autoload a webget when needed, the source can be:
 * <ul>
 *  <li>navigate cms default webgets</li>
 *  <li>website private folder</li>
 *  <li>navigate cms plugins folder</li>
 *  <li>website theme folder</li>
 * </ul>
 * @param $webget_name string
 */
function nvweb_webget_load($webget_name)
{				
	global $website;
	
	$fname = 'nvweb_'.$webget_name;
	if(!function_exists($fname))
	{
		if(file_exists(NAVIGATE_PATH.'/lib/webgets/'.$webget_name.'.php'))
			@include_once(NAVIGATE_PATH.'/lib/webgets/'.$webget_name.'.php');
		else if(file_exists(NAVIGATE_PRIVATE.'/'.$website->id.'/webgets/'.$webget_name.'.php'))
			@include_once(NAVIGATE_PRIVATE.'/'.$website->id.'/webgets/'.$webget_name.'.php');
		else if(file_exists(NAVIGATE_PATH.'/plugins/'.$webget_name.'/'.$webget_name.'.php'))
			@include_once(NAVIGATE_PATH.'/plugins/'.$webget_name.'/'.$webget_name.'.php');
        else if(file_exists(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$website->theme.'.nvweb.php'))
            @include_once(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$website->theme.'.nvweb.php');
	}
}

/**
 * Alias of navigate_send_email
 *
 * @param $subject
 * @param $message
 * @param string|array $recipients e-mail address of the recipient or array of e-mail addresses
 * @param array $attachments
 * @return bool
 */
function nvweb_send_email($subject, $message, $recipients=array(), $attachments=array())
{	
	return navigate_send_email($subject, $message, $recipients, $attachments);
}


?>