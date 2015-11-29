<?php
function nvweb_self_url()
{ 
	if(!isset($_SERVER['REQUEST_URI']))
		$serverrequri = $_SERVER['PHP_SELF']; 
	else
		$serverrequri = $_SERVER['REQUEST_URI'];

	$s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : ""; 
	$s1 = strtolower($_SERVER["SERVER_PROTOCOL"]);
	
	$protocol = substr($s1, 0, strpos($s1, "/")).$s; 

	$port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]); 

    // some shared hostings need $_SERVER['HTTP_HOST']
    // but $_SERVER['SERVER_NAME'] should work if the server is configured properly
	$url = $protocol."://".$_SERVER['HTTP_HOST'].$port.$serverrequri;
	
	// decode %chars
	$url = urldecode($url);

    // remove last '?' in url if exists
    if(substr($url, -1)=='?')
        $url = substr($url, 0, -1);

	// remove last '/' in url if exists
    if(substr($url, -1)=='/')
        $url = substr($url, 0, -1);

	return $url;
}

function nvweb_load_website_by_url($url, $exit=true)
{
	global $DB;
    global $idn;
	
	$website = new website();
	
	$parsed = parse_url($url);
    $scheme = $parsed['scheme']; // http, https...
	$host = $parsed['host']; // subdomain.domain.tld
	$path = $parsed['path']; // [/folder]page
		
	//$query = $parsed['query']; // not really needed, already in $_GET
    if(function_exists('idn_to_utf8'))
        $host = idn_to_utf8($host);
    else
        $host = $idn->decode($host);

    // look for website aliases
    $DB->query('SELECT aliases FROM nv_websites', 'array');
    $ars = $DB->result('aliases');

    $aliases = array();
    foreach($ars as $ajson)
    {
        if(!is_array($aliases))
            $aliases = array();

        $ajson = json_decode($ajson, true);
        if(!is_array($ajson))
            continue;

        $aliases = array_merge($aliases, $ajson);
    }

    if(!is_array($aliases))
        $aliases = array();

    foreach($aliases as $alias => $real)
    {
        $alias_parsed = parse_url($alias);

        if( $alias_parsed['host'] == $host )
        {
            // check the path section
            if(strpos($path, $alias_parsed['path'], 0)!=0)
                continue;

            // alias path is included in the requested path
            // identify the extra part
            // EXAMPLE
            //
            //    ALIAS           http://themes.navigatecms.com
            //    REQUEST         http://themes.navigatecms.com/en/introduction
            //        EXTRA           /en/introduction
            //
            //    REAL PATH       http://www.navigatecms.com/en/documentation/themes
            //    REAL + EXTRA    http://www.navigatecms.com/en/documentation/themes/introduction
            //
            // note that the language part "en" is placed in different order
            // so our approach is to IGNORE the path sections already existing in the real path

            $extra = substr($path, strlen($alias_parsed['path']));

            $real_parsed = parse_url($real);
            $real_path = explode('/', $real_parsed['path']);
            $extra_path = explode('/', $extra);

            if(!is_array($extra_path))
                $extra_path = array();

            $add_to_real = '';
            foreach($extra_path as $part)
            {
                if($part=='nvweb.home')
                    continue;

                if(in_array($part, $real_path))
                    continue;

                $add_to_real .= '/' . $part;
            }

            // TO DO: maybe in a later version full ALIAS support could be implemented
            //        right now we only redirect to the real path
            $url = $real . $add_to_real;

            header('location: '.$url);
            nvweb_clean_exit();
        }
    }

	// the host is an IP address or a full domain?
	$isIP = filter_var($host, FILTER_VALIDATE_IP);
	if($isIP)
	{
		$domain = $host;
		$subdomain = "";
	}
	else
	{
		// do we have a subdomain in the url?
		preg_match('/(?:http[s]*\:\/\/)*(.*?)\.(?=[^\/]*\..{2,5})/i', $url, $parts);
		$subdomain = $parts[1];
		$domain = $host;
		if(empty($subdomain)) // may be NULL
			$subdomain = "";
		else
			$domain = substr($host, strlen($subdomain)+1);
	}

	$DB->query('
		SELECT id, folder
		  FROM nv_websites
		 WHERE subdomain = '.protect($subdomain).'
		   AND domain = '.protect($domain).'
		 ORDER BY folder DESC
	 ');
	$websites = $DB->result();

	if(empty($websites))
	{
        // no 'real' website found using this address

		if($subdomain == 'nv')
		{
            /*
			$website->load(); // first available, it doesn't matter
			$nvweb_absolute = (empty($website->protocol)? 'http://' : $website->protocol);
			if(!empty($website->subdomain))
				$nvweb_absolute .= $website->subdomain.'.';
			$nvweb_absolute .= $website->domain.$website->folder;
            */
			$nvweb_absolute = NAVIGATE_PARENT.NAVIGATE_FOLDER;
			header('location: '.$nvweb_absolute);
			nvweb_clean_exit();
		}
		else
		{		
			header("HTTP/1.1 404 Not Found");
			if($exit)
			{
				nvweb_clean_exit();
			}
			else
			{
				return false;
			}
		}
	}

    // choose which website based on folder name
	foreach($websites as $web)
	{
        // there can only be one subdomain.domain.tld without folder
		if(empty($web->folder))
		{
			$website->load($web->id);
            break;
		}
        else
        {
            $path_segments = explode('/', $path);
            $folder_segments = explode('/', $web->folder);

            $folder_coincidence = true;
            for($fs=0; $fs < count($folder_segments); $fs++)
                $folder_coincidence = $folder_coincidence && ($folder_segments[$fs]==$path_segments[$fs]);

            if($folder_coincidence)
            {
                $website->load($web->id);
                break;
            }
        }
	}

	// website could not be identified, just load the first available
	if(empty($website->id))
		$website->load();

	return $website;
}

function nvweb_prepare_link($path)
{
	$path = trim($path);

    if(	substr(strtolower($path), 0, 7)=='http://' ||
		substr(strtolower($path), 0, 8)=='https://' ||
		substr(strtolower($path), 0, 1)=='#'
	)
        $url = $path;
    else
        $url = NVWEB_ABSOLUTE.$path;

    return $url;
}

function nvweb_route_parse($route="")
{
	global $website;
	global $DB;
	global $current;
	global $session;
    global $theme;
	global $events;
	global $dictionary;

	// node route types
	if(substr($route, 0, 5)=='node/')
	{
		$node  =  substr($route, 5);
		$route = 'node';		
	}

	switch($route)
	{			
		case 'object':
			nvweb_object();
			nvweb_clean_exit();
			break;

        case 'nvajax':
            nvweb_ajax();
            nvweb_clean_exit();
            break;

        case 'nvtags':
        case 'nvsearch':
            $current['template'] = 'search';
            break;
			
		case 'node':
			if($node > 0)
			{
				$current['id'] = $node;
				
				$DB->query('SELECT * FROM nv_items 
							 WHERE id = '.protect($current['id']).'
							   AND website = '.$website->id);
				$current['object'] = $DB->first();
				
				// let's count a hit (except admin)
				if($current['navigate_session']!=1 && !nvweb_is_bot())
				{
					$DB->execute(' UPDATE nv_items SET views = views + 1 
								   WHERE id = '.$current['id'].' 
									 AND website = '.$website->id);
				}

				$current['type'] = 'item';
				$current['template'] = $current['object']->template;

				if($current['navigate_session']==1 && !empty($_REQUEST['template']))
					$current['template'] = $_REQUEST['template'];
			}
			break;

        case 'sitemap.xml':
            nvweb_webget_load('sitemap');
            echo nvweb_sitemap(array('mode' => 'xml'));
            nvweb_clean_exit();
            break;
			
		// redirect to home page of the current website
		case 'nvweb.home':
		case 'nv.home':
			header('location: '.NVWEB_ABSOLUTE.$website->homepage());
			nvweb_clean_exit();
			break;

        // empty route, we'll just load the homepage defined
        // for example: www.domain.com => www.domain.com/home (without redirection)
        case '':
        case '/':
            if(empty($route) || $route=='/')
                $route = substr($website->homepage(), 1); // remove first slash
            // don't break, continue to default case (url has a route)

        // no special route, look for the path on navigate routing table
		default:

			$DB->query('SELECT * FROM nv_paths 
						 WHERE path = '.protect('/'.$route).' 
						   AND website = '.$website->id.'
						 ORDER BY id DESC');
			$rs = $DB->result();

    		if(empty($rs))
			{
				// no valid route found
                switch($website->wrong_path_action)
                {
                    case 'homepage':
                        header('location: '.NVWEB_ABSOLUTE.$website->homepage());
                        nvweb_clean_exit();
                        break;

                    case 'http_404':
                        header("HTTP/1.0 404 Not Found");
                        nvweb_clean_exit();
                        break;

                    case 'theme_404':
                        $current['template'] = 'not_found';
                        $current['type']	 = 'structure';
                        $current['id'] 		 = 0;
                        $current['object']   = new structure();
                        return;
                        break;

                    case 'blank':
                    default:
                        nvweb_clean_exit();
                        break;
                }
			}
			else
			{
				// route found!
				// let's count a hit (except admin)
				if($current['navigate_session']!=1 && !nvweb_is_bot())
				{
					$DB->execute(' UPDATE nv_paths SET views = views + 1 
								   WHERE id = '.$rs[0]->id.' 
								     AND website = '.$website->id);
				}
				
				// set the properties found
				
				// set the default language for this route
				if(!isset($_REQUEST['lang']))
				{
					$current['lang'] 	 = $rs[0]->lang;
					$session['lang']	 = $rs[0]->lang;

					// force reloading the dictionary
					$dictionary = nvweb_dictionary_load();
				}
					
				$current['type']	 = $rs[0]->type;
				$current['id'] 		 = $rs[0]->object_id;
				
				// look for the template associated with this item

				if($current['type']=='structure')
				{
                    $obj = new structure();
                    $obj->load($current['id']);

                    // check if it is a direct access to a "jump to another branch" path
                    if($obj->dictionary[$current['lang']]['action-type']=='jump-branch')
                    {
                        $current['id'] = $obj->dictionary[$current['lang']]['action-jump-branch'];
                        $obj = new structure();
                        $obj->load($current['id']);
                        header('location: '.NVWEB_ABSOLUTE.$obj->paths[$current['lang']]);
                        nvweb_clean_exit();
                    }
                    else if($obj->dictionary[$current['lang']]['action-type']=='jump-item')
                    {
                        $current['id'] = $obj->dictionary[$current['lang']]['action-jump-item'];
                        $obj = new item();
                        $obj->load($current['id']);
                        header('location: '.NVWEB_ABSOLUTE.$obj->paths[$current['lang']]);
                        nvweb_clean_exit();
                    }

					$current['object'] = $obj;
					$current['category'] = $current['id'];
					
					if($current['navigate_session']!=1 && !nvweb_is_bot())
					{
						$DB->execute(' UPDATE nv_structure SET views = views + 1 
									    WHERE id = '.protect($current['id']).' 
										  AND website = '.$website->id);
					}					
				}
				else if($current['type']=='item')
				{
					$DB->query('SELECT * FROM nv_items 
								 WHERE id = '.protect($current['id']).'
								   AND website = '.$website->id);

					$current['object'] = $DB->first();
					
					// let's count a hit (except admin)
					if($current['navigate_session']!=1 && !nvweb_is_bot())
					{
						$DB->execute(' UPDATE nv_items SET views = views + 1 
									   WHERE id = '.$current['id'].' 
									     AND website = '.$website->id);
					}							
				}
				else if($current['type']=='feed')
				{
					$out = feed::generate_feed($current['id']);
                    if($current['navigate_session']!=1 && !nvweb_is_bot())
					{
                        $DB->execute(' UPDATE nv_feeds SET views = views + 1
                                           WHERE id = '.$current['id'].'
                                             AND website = '.$website->id);
                    }
					echo $out;
					nvweb_clean_exit();
				}
				else
				{
					// path exists, but the object type is unknown
					// maybe the path belongs to an extension?
					$events->trigger(
						'nvweb',
						'routes',
						array(
							'path' => $rs[0]
						)
					);
				}

				$current['template'] = $current['object']->template;
			}
			break;			
	}
}


function nvweb_check_permission()
{
	global $current;
    global $webuser;
	
	$permission = true;
	
	switch($current['object']->permission)
	{
		case 2:	// hidden to ANYONE
			$permission = false;
			break;
			
		case 1:	// hidden to ANYBODY except NAVIGATE users
			$permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE]));
			break;
			
		case 0:	// visible to EVERYBODY if publishing dates allow it
		default:
			$permission = (empty($current['object']->date_published) || ($current['object']->date_published < core_time()));
			$permission = $permission && (empty($current['object']->date_unpublish) || ($current['object']->date_unpublish > core_time()));		
	}
	
	// check access
	if(isset($current['object']->access))
	{
		$access = true;
		
		switch($current['object']->access)
		{
            case 3: // accessible to SELECTED WEB USER GROUPS only
                $access = false;
                $groups = $current['object']->groups;
                if( !empty($current['webuser']) )
                {
                    $groups = array_intersect($webuser->groups, $groups);
                    if(count($groups) > 0)
                        $access = true;
                }
                break;

            case 2:	// accessible to NOT SIGNED IN visitors
				$access = empty($current['webuser']);
				break;
			
			case 1: // accessible to WEB USERS ONLY
				$access = !empty($current['webuser']);
				break;
			
			case 0:	// accessible to EVERYBODY 
			default:
				$access = true;
		}
		
		$permission = $permission && $access;
	}
		
	return $permission;
}

function nvweb_object_enabled($object)
{
	global $current;
    global $webuser;
	
	$enabled = true;

	switch($object->permission)
	{
		case 2:	//
			$enabled = false;
			break;
			
		case 1:
			$enabled = (!empty($_SESSION['APP_USER#'.APP_UNIQUE]));
			break;
			
		case 0:
		default:
			$enabled = true;
	}
		
	$enabled = $enabled && (empty($object->date_published) || ($object->date_published < core_time()));
	$enabled = $enabled && (empty($object->date_unpublish) || ($object->date_unpublish > core_time()));

	// check access
	if(isset($object->access))
	{
		$access = true;
		
		switch($object->access)
		{
            case 3: // accessible to SELECTED WEB USER GROUPS only
                $access = false;
                $groups = $object->groups;

                if( !empty($current['webuser']) )
                {
                    $groups = array_intersect($webuser->groups, $groups);
                    if(count($groups) > 0)
                        $access = true;
                }
                break;

            case 2:	// accessible to NOT SIGNED IN visitors ONLY
				$access = empty($current['webuser']);
				break;
			
			case 1: // accessible to WEB USERS ONLY
				$access = !empty($current['webuser']);
				break;
			
			case 0:	// accessible to EVERYBODY 
			default:
				$access = true;
		}
		
		$enabled = $enabled && $access;
	}

	return $enabled;
}

function nvweb_source_url($type, $id, $lang='')
{
	global $DB;
	global $website;
	global $current;
    global $theme;
	
	if(empty($lang)) 
		$lang = $current['lang'];

    if($type=='theme')
    {
        // find the first PUBLIC & PUBLISHED article / item / structure element
        // that is using the template type given in $id
        $template_type = $id;
        $id = '';

        //TODO: a) search products
        if(empty($id))
        {
            // $DB->query_single('id', 'nv_products')
            if(!empty($id))
                $type = 'product';
        }

        // b) search items
        if(empty($id))
        {
            $id = $DB->query_single(
                'id',
                'nv_items',
                'website = '.protect($website->id).'
                 AND template = '.protect($template_type).'
                 AND permission = 0
                 AND access = 0
                 AND (date_published = 0 OR date_published < '.core_time().')
                 AND (date_unpublish = 0 OR date_unpublish > '.core_time().')'
            );
            if(!empty($id))
                $type = 'item';
        }

        // c) search structure elements
        if(empty($id))
        {
            $id = $DB->query_single(
                'id',
                'nv_structure',
                'website = '.protect($website->id).'
                 AND template = '.protect($template_type).'
                 AND permission = 0
                 AND access = 0
                 AND (date_published = 0 OR date_published < '.core_time().')
                 AND (date_unpublish = 0 OR date_unpublish > '.core_time().')'
            );
            if(!empty($id))
                $type = 'structure';
        }

        if(empty($id))
            return "";
    }

    $url = $DB->query_single(
		'path',
		'nv_paths',
		' type = '.protect($type).'
		   AND object_id = '.protect($id).'
		   AND lang = '.protect($lang).'
		   AND website = '.$website->id
	);

    $url = nvweb_prepare_link($url);

	return $url;										   
}

function nvweb_ajax()
{
    global $website;
    global $theme;

    nvweb_webget_load($theme->name);
    $fname = 'nvweb_'.$theme->name.'_nvajax';

    if(function_exists($fname))
        $content = $fname();
}

// the following function checks if a request comes from a search bot
// author: Pavan Gudhe
// http://www.phpclasses.org/package/7026-PHP-Determine-if-the-current-user-is-a-bot.html
function nvweb_is_bot()
{
    $arrstrBotMatches = array();
    
    $arrstrBots = array (
         'googlebot'        => '/Googlebot/',
         'msnbot'           => '/MSNBot/',
         'slurp'            => '/Inktomi/',
         'yahoo'            => '/Yahoo/',
         'askjeeves'        => '/AskJeeves/',
         'fastcrawler'      => '/FastCrawler/',
         'infoseek'         => '/InfoSeek/',
         'lycos'            => '/Lycos/',
         'yandex'           => '/YandexBot/',
         'geohasher'        => '/GeoHasher/',
         'gigablast'        => '/Gigabot/',
         'baidu'            => '/Baiduspider/',
         'spinn3r'          => '/Spinn3r/'
    );

    //check if bot request
    if( true == isset( $_SERVER['HTTP_USER_AGENT'] ))
    {
        $arrstrBotMatches = preg_filter( $arrstrBots, array_fill( 1, count( $arrstrBots ), '$0' ), array( trim( $_SERVER['HTTP_USER_AGENT'] )));
    }

    //isBot() can be used to check if the request is bot request before incrementing the visit count.
    //check if bot request.
    return ( true == is_array( $arrstrBotMatches ) && 0 < count( $arrstrBotMatches )) ? 1 : 0;
}

function nvweb_clean_exit($url='')
{
	global $session;
	global $DB;
	global $website;

    if(!empty($website->id))
	    $_SESSION['nvweb.'.$website->id] = $session;
	
	session_write_close();
	$DB->disconnect();

    if(!empty($url))
        header('Location: '.$url);

	exit;
}
?>