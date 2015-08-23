<?php
require_once('../cfg/globals.php');
require_once(NAVIGATE_PATH.'/web/nvweb_common.php');

/* global variables */
global $DB;
global $webuser;
global $config;
global $website;
global $current;
global $dictionary;
global $plugins;
global $events;
global $webgets;

$idn = new idna_convert();
$events = new events();

// create database connection
$DB = new database();
if(!$DB->connect())
{
	die(APP_NAME.' # ERROR<br /> '.$DB->get_last_error());	
}

// global exception catcher
try	
{
	// which website do we have to load?
	$url = nvweb_self_url();

    if(!empty($_REQUEST['wid']))
	{
		$website = new website();
		$website->load(intval($_REQUEST['wid']));
	}
	else
		$website = nvweb_load_website_by_url($url);

	if(	($website->permission == 2) || 
		($website->permission == 1 && empty($_SESSION['APP_USER#'.APP_UNIQUE])))
    {
        if(!empty($website->redirect_to))
            header('location: '.$website->redirect_to);
        nvweb_clean_exit();
    }

    // global helper variables
	$session = array();		// webuser session
	$structure = array();	// web menu structure
	$webgets = array(); 	// webgets static data
	$webuser = new webuser();
	$theme = new theme();
	if(!empty($website->theme))
		$theme->load($website->theme);

    $route = $_REQUEST['route'];
    // remove last '/' in route if exists
    if(substr($route, -1)=='/')
        $route = substr($route, 0, -1);

    // remove the "folder" part of the route (only if this url is really under a folder)
    if(!empty($website->folder) && strpos('/'.$route, $website->folder)===0)
        $route = substr('/'.$route, strlen($website->folder)+1);

    $nvweb_absolute = $idn->encode($website->absolute_path());

	define('NVWEB_ABSOLUTE', $nvweb_absolute);
	define('NVWEB_OBJECT', $nvweb_absolute.'/object');
	define('NVWEB_AJAX', $nvweb_absolute.'/nvajax');
	define('NVWEB_THEME', $idn->encode($website->absolute_path(false)).NAVIGATE_FOLDER.'/themes/'.$theme->name);
    define('NAVIGATE_URL', NAVIGATE_PARENT.NAVIGATE_FOLDER);

	if(!isset($_SESSION['nvweb.'.$website->id]))
	{
		$_SESSION['nvweb.'.$website->id] = array();
		$session['lang'] = nvweb_country_language();
	}
	else
	{
		$session = $_SESSION['nvweb.'.$website->id];
		
		if(empty($session['lang'])) 
			$session['lang'] = nvweb_country_language();
	}

	if(isset($_REQUEST['lang']))
		$session['lang'] = $_REQUEST['lang'];

    // load dictionary, extensions and bind events (as soon as possible)
    $dictionary = nvweb_dictionary_load();

	// global data across webgets
	$current = array(
		'lang' 				=> $session['lang'],
		'route' 			=> $route,
		'object'			=> '',
		'template' 			=> '',
		'category' 			=> '',
		'webuser'  			=> '',
		'plugins'           => '',
		'plugins_called'    => '',
		'delayed_nvlists'   => array(),
		'delayed_nvsearches'=> array(),
		'navigate_session' 	=> !empty($_SESSION['APP_USER#'.APP_UNIQUE]),
		'html_after_body'	=> array(),
		'js_after_body'		=> array()
	);

    nvweb_plugins_load();

	$current['plugins'] = $plugins;
    $events->extension_backend_bindings();

	if(!empty($session['webuser']))
		$webuser->load($session['webuser']);
	else if(!empty($_COOKIE["webuser"]))
		$webuser->load_by_hash($_COOKIE['webuser']);

    // if the webuser was removed, it doesn't exist anymore,
    //  $session/$_COOKIE may have obsolete data, force a log out
    if(empty($webuser->id)  && (!empty($session['webuser']) || !empty($_COOKIE['webuser'])))
    {
        $webuser->unset_cookie();
        unset($webuser);
        $webuser = new webuser();
    }

    if(!empty($webuser->id))
    {
        $webuser->lastseen = core_time();
        $webuser->save();
    }

    // check if the webuser wants to sign out
    if(isset($_REQUEST['webuser_signout']))
    {
        $webuser->unset_cookie();
        unset($webuser);
        $webuser = new webuser();
    }

	$current['webuser'] = $session['webuser'];

    setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);
	date_default_timezone_set($webuser->timezone? $webuser->timezone : $website->default_timezone);

	// help developers to find problems
	if($current['navigate_session']==1 && APP_DEBUG)
	{
		error_reporting(E_ALL ^ E_NOTICE);
		ini_set('display_errors', true);
	}

	// parse route
	nvweb_route_parse($current['route']);
	$permission = nvweb_check_permission();

    // if no preview & permission not allowed
    // if preview but no navigate_session active
	if( ($_REQUEST['preview']=='true' && $current['navigate_session']!=1) ||
        (empty($_REQUEST['preview']) && !$permission))
    {
        nvweb_route_parse('***nv.not_allowed***');
		nvweb_clean_exit();
    }

    $template = nvweb_template_load();
    $events->trigger('theme', 'template_load', array('template' => &$template));

    if(empty($template))
        throw new Exception('Navigate CMS: no template found!');


    // parse the special tag "include"
    // also convert curly brackets tags {{nv object=""}} to <nv object="" /> version
    // we do it now because new nv tags could be added
    $html = nvweb_template_parse_special($template->file_contents);

    $current['plugins_called'] = nvweb_plugins_called_in_template($html);
    $html = nvweb_plugins_event('before_parse', $html);

    $html = nvweb_theme_settings($html);

    $html = nvweb_template_parse_lists($html);
	$html = nvweb_template_parse($html);

    // if we have a delayed nv list we need to parse it now
    if(!empty($current['delayed_nvlists']) || !empty($current['delayed_nvsearches']))
    {
        $html = nvweb_template_parse_lists($html, true);
        $html = nvweb_template_parse($html);
    }

    $html = nvweb_template_oembed_parse($html);

	$end = nvweb_after_body('html');
	$end.= nvweb_after_body('js');
	$end.= "\n\n";
	$end.= '</body>';	
	$html = str_replace('</body>', $end, $html);
	
	$html = nvweb_template_tweaks($html);
    $events->trigger('theme', 'after_parse', array('html' => &$html));
    $html = nvweb_plugins_event('after_parse', $html);

	$_SESSION['nvweb.'.$website->id] = $session;
	session_write_close();

	if($current['navigate_session']==1 && APP_DEBUG)
	{
		echo $html;

        echo "\n\r<!--\n\r".'$current:'."\n\r";
		print_r($current);
        echo "\n\r!--><!--\n\r".'$_SESSION:'."\n\r";

        $stmp = $_SESSION;
        foreach($stmp as $key => $val)
        {
            if(substr($key, 0, 4)=='PMA_') // hide phpMyAdmin single signon settings!!
                continue;

            echo '['.$key.'] => '.print_r($val, true)."\n";
        }
        echo "!-->";
	}
	else
	{
        // close any previous output buffer
        // some PHP configurations open ALWAYS a buffer
        while(ob_get_level() > 0)
            ob_end_flush();

		// open gzip buffer
		ob_start("ob_gzhandler");
		echo $html;
		ob_end_flush();
	}
}
catch(Exception $e)
{
	?>
    <html>
    	<body>
        	ERROR
            <br /><br />
            <?php
                echo $e->getMessage();
                echo '<br />';
                echo $e->getFile().' '.$e->getLine();
            ?>
        </body>
    </html>
	<?php
}

$DB->disconnect();
?>