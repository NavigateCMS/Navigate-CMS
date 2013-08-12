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
		($website->permission == 1 && empty($_SESSION['APP_USER'])))
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

    // remove the "folder" part of the route
    $route = $_REQUEST['route'];
    if(!empty($website->folder))
        $route = substr($route, strlen($website->folder));

	$nvweb_absolute = $idn->encode($website->absolute_path());

	define('NVWEB_ABSOLUTE', $nvweb_absolute);
	define('NVWEB_OBJECT', $nvweb_absolute.'/object');
	define('NVWEB_AJAX', $nvweb_absolute.'/nvajax');

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
		
	if(!empty($session['webuser']))
		$webuser->load($session['webuser']);
	else if(!empty($_COOKIE["webuser"]))
		$webuser->load_by_hash($_COOKIE['webuser']);

    // check if the webuser wants to sign out
    if(isset($_REQUEST['webuser_signout']))
    {
        $webuser->unset_cookie();
        unset($webuser);
    }

    setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);
	date_default_timezone_set($webuser->timezone? $webuser->timezone : $website->default_timezone);

	// global data across webgets
	$current = array(
		'lang' 				=> $session['lang'],
		'route' 			=> $route,
		'object'			=> '',
		'template' 			=> '',
		'category' 			=> '',
		'webuser'  			=> $session['webuser'],
        'plugins'           => $plugins,
        'plugins_called'    => '',
        'delayed_nvlists'   => array(),
        'delayed_nvsearches'=> array(),
		'navigate_session' 	=> !empty($_SESSION['APP_USER']),
		'html_after_body'	=> array(),
		'js_after_body'		=> array()
	);

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
		nvweb_clean_exit();

    define('NAVIGATE_URL', NAVIGATE_PARENT.NAVIGATE_FOLDER);
			
	$dictionary = nvweb_dictionary_load();

	$template = nvweb_template_load();

	if(empty($template)) 
		throw new Exception('Navigate CMS: no template found!');
		
	nvweb_plugins_load();
    $events->extension_backend_bindings();

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

	$end = nvweb_after_body('html');
	$end.= nvweb_after_body('js');
	$end.= "\n\n";
	$end.= '</body>';	
	$html = str_replace('</body>', $end, $html);
	
	$html = nvweb_template_tweaks($html);
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