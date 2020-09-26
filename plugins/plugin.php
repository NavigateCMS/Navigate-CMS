<?php
// get the current working directory
// and find the distance from navigate root directory
global $plugins_directory;

if(empty($plugins_directory))
{
    $plugins_directory = '';
    $current_directory = getcwd();

    while(basename($current_directory)!='plugins')
    {
        $current_directory = dirname(($current_directory));
        $plugins_directory .= '../';
    }
}

include_once($plugins_directory.'../cfg/globals.php');
include_once(NAVIGATE_PATH.'/web/nvweb_common.php');
include_once(NAVIGATE_PATH.'/lib/core/language.class.php');

/* global variables */
global $DB;
global $webuser;
global $config;
global $website;
global $current;
global $dictionary;
global $session;
global $webgets;
global $events;
global $idn;

function nv_plugin_init()
{
	global $DB;
	global $webuser;
	global $config;
	global $website;
	global $current;
	global $dictionary;
	global $session;
    global $events;
    global $idn;

	// create database connection
	$DB = new database();
	if(!$DB->connect())
	{
		die(APP_NAME.' # ERROR<br /> '.$DB->get_last_error());
	}

	// global exception catcher
	try
	{
		$idn = new \Mso\IdnaConvert\IdnaConvert();

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
            nvweb_clean_exit();
        }

        // global helper variables
		$session = array();		// user session
		$webuser = new webuser();

		$nvweb_absolute = (empty($website->protocol)? 'http://' : $website->protocol);
		if(!empty($website->subdomain))
        {
            $nvweb_absolute .= $website->subdomain.'.';
        }
		$nvweb_absolute .= $website->domain.$website->folder;

		define('NVWEB_ABSOLUTE', $nvweb_absolute);
		define('NVWEB_OBJECT', $nvweb_absolute.'/object');
		if(!defined('NAVIGATE_URL'))
        {
            define('NAVIGATE_URL', NAVIGATE_PARENT.NAVIGATE_FOLDER);
        }

		if(!isset($_SESSION['nvweb.'.$website->id]))
		{
			$_SESSION['nvweb.'.$website->id] = array();
			$session['lang'] = nvweb_country_language();
		}
		else
		{
			$session = $_SESSION['nvweb.'.$website->id];
			if(empty($session['lang']))
            {
                $session['lang'] = nvweb_country_language();
            }
		}

        if(isset($_REQUEST['lang']))
        {
            $session['lang'] = $_REQUEST['lang'];
        }

        // verify requested language is enabled for the current website,
        // if not, just use the default language
        if(!in_array($session['lang'], array_keys($website->languages())))
        {
            $session['lang'] = $website->languages_published[0];
        }

		if(!empty($session['webuser']))
        {
            $webuser->load($session['webuser']);
        }
		else if(!empty($_COOKIE["webuser"]))
        {
            $webuser->load_by_hash($_COOKIE['webuser']);
        }

        @setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

        // remove the "folder" part of the route
        $route = '';
        if(!empty($_REQUEST['route']))
        {
            $route = $_REQUEST['route'];
            // remove the "folder" part of the route (only if this url is really under a folder)
            if(!empty($website->folder) && strpos('/'.$route, $website->folder)===0)
                $route = substr('/'.$route, strlen($website->folder)+1);
        }

		// global data across webgets
		$current = array(
			'lang' 				=> $session['lang'],
			'route' 			=> $route,
			'object'			=> '',
			'template' 			=> '',
			'category' 			=> '',
			'webuser'  			=> @$session['webuser'],
			'navigate_session' 	=> !empty($_SESSION['APP_USER#'.APP_UNIQUE]),
			'html_after_body'	=> array(),
			'js_after_body'		=> array()
		);

        $dictionary = nvweb_dictionary_load();
		$_SESSION['nvweb.'.$website->id] = $session;
	}
	catch(Exception $e)
	{
		?>
		<html>
			<body>
				ERROR
				<br /><br />
				<?php echo $e->getMessage();?>
			</body>
		</html>
		<?php
	}

    $events = new events();
    nvweb_plugins_load();
    $events->extension_backend_bindings();
}

function nv_plugin_end($redirect_to="")
{
	global $DB;
	global $session;
	global $website;

	$_SESSION['nvweb.'.$website->id] = $session;

	session_write_close();
	$DB->disconnect();

    if(!empty($redirect_to))
    {
        header('Location: '.$redirect_to);
    }

	exit;
}

?>