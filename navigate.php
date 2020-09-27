<?php
// +------------------------------------------------------------------------+
// | NAVIGATE CMS                                                           |
// +------------------------------------------------------------------------+
// | Copyright (c) Naviwebs 2010-2020. All rights reserved.                 |
// | Last modified 2020-06-17                                               |
// | Email         info@naviwebs.com                                        |
// | Web           http://www.navigatecms.com                               |
// +------------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify   |
// | it under the terms of the GNU General Public License version 2 as      |
// | published by the Free Software Foundation.                             |
// |                                                                        |
// | This program is distributed in the hope that it will be useful,        |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of         |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the          |
// | GNU General Public License for more details.                           |
// |                                                                        |
// | You should have received a copy of the GNU General Public License      |
// | along with this program; if not, write to the                          |
// |   Free Software Foundation, Inc., 59 Temple Place, Suite 330,          |
// |   Boston, MA 02111-1307 USA                                            |
// +------------------------------------------------------------------------+
//

// security fix: force creating a secure $_REQUEST global variable giving priority to $_POST and ignoring $_COOKIE
$_REQUEST = array_merge($_GET, $_POST);

require_once('cfg/globals.php');

if(isset($_REQUEST['debug']) || APP_DEBUG)
{
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 1);
}

require_once('cfg/common.php');

core_define_navigate_url('navigate.php');

/* global variables */
global $DB;
global $user;
global $config;
global $layout;
global $website;
global $theme;
global $events;
global $current_version;
global $world_languages; // filled in language.class.php

// is a simple keep alive request?
if(@$_REQUEST['fid']=='keep_alive')
{
	session_write_close();
	echo 'true';
	exit;
}

// is an extension run request? (special fid 'ext_extensionname')
if(substr($_REQUEST['fid'], 0, '4')=='ext_')
{
    $_REQUEST['act'] = 'run';
    $_REQUEST['extension'] = substr($_REQUEST['fid'], 4);
    $_REQUEST['fid'] = 'extensions';
    $fid = 'extensions';
}

// create database connection
$DB = new database();
if(!$DB->connect())
{
	die(APP_NAME.' # ERROR<br /> '.$DB->get_last_error());
}

$events = new events();
$events->extension_preinit_bindings();
debugger::init();
debugger::dispatch();

// session checking
if(ini_get("session.use_cookies") && !empty($_COOKIE['navigate-session-id']))
{
    if($_COOKIE['navigate-session-id'] != session_id())
    {
        unset($_SESSION);
        core_session_remove();
    }
}

if(empty($_SESSION['APP_USER#'.APP_UNIQUE]) || isset($_GET['logout']))
{	
    if(!empty($_SESSION['APP_USER#'.APP_UNIQUE]))
    {
        $user = new user();
        $user->load($_SESSION['APP_USER#'.APP_UNIQUE]);
        $user->remove_cookie();
    }

    if(isset($_GET['logout']) && @!empty($user->id))
    {
        users_log::action(0, $user->id, 'logout', $user->username);
    }

    // reset session
	core_session_remove();
	session_start();
	
	if($_SERVER['QUERY_STRING'] != 'logout')
    {
        // save URL query to be applied once the user is logged in
        $_SESSION["login_request_uri"] = $_SERVER['QUERY_STRING'];
    }

	core_terminate('login.php');
}
else
{
	$user = new user();
	$user->load($_SESSION['APP_USER#'.APP_UNIQUE]);

	if(empty($user->id))
    {
        header('location: '.NAVIGATE_MAIN.'?logout');
    }
}

$current_version = update::latest_installed();

// new updates check -> only Administrator (profile=1)
if($user->profile==1 && empty($_SESSION['latest_update']) && NAVIGATECMS_UPDATES!==false)
{
	$_SESSION['latest_update'] = @update::latest_available();
    $_SESSION['extensions_updates'] = @extension::latest_available();
    $_SESSION['themes_updates'] = @theme::latest_available();
}

$idn = new \Algo26\IdnaConvert\ToIdn();
$lang = new language();
$lang->load($user->language);

if(@$_COOKIE['navigate-language'] != $user->language)
{
    setcookie_samesite('navigate-language', $user->language, time() + 86400 * 30);
}

@set_time_limit(0);

$menu_layout = new menu_layout();
$menu_layout->load();

// load the working website
$website = new website();

if((@$_GET['act']=='0' || @$_GET['quickedit']=='true') && !empty($_GET['wid']))
{
	$website->load(intval($_GET['wid']));
}
else if(!empty($_SESSION['website_active']))
{
    $ws_active = $_SESSION['website_active'];
	$website->load($ws_active);
}
else	
{
	$url = nvweb_self_url();
	$website = nvweb_load_website_by_url($url, false);
	if(!$website)
	{
		$website = new website();
		$website->load();
	}
}


// if there are no websites, auto-create the first one
if(empty($website->id))
{
    $website->create_default();
}

// check allowed websites for this user
$wa = $user->websites;
if(!empty($wa))
{
    if(array_search($website->id, $wa)===false)
    {
        $website = new website();
        if(!empty($wa[0])) // load first website allowed
        {
            $website->load(intval($wa[0]));
        }

        if(empty($website->id) && $user->permission('websites.edit')=='false')
        {
            // NO website allowed AND can't create websites, so auto sign out
            core_session_remove();
            core_terminate('login.php');
        }
    }
}

$_SESSION['website_active'] = $website->id;

$events->load_extensions_installed();
$events->extension_backend_bindings(null, false);
$website->bind_events();

// no valid website found; show Create first website wizard
if(empty($_SESSION['website_active']) && $_REQUEST['fid']!='websites')
{
	header('location: '.NAVIGATE_MAIN.'?fid=websites&act=wizard');
	core_terminate();
}

// load website basics
$nvweb_absolute = (empty($website->protocol)? 'http://' : $website->protocol);
if(!empty($website->subdomain))
{
    $nvweb_absolute .= $website->subdomain.'.';
}
$nvweb_absolute .= $website->domain.$website->folder;

define('NVWEB_ABSOLUTE', $nvweb_absolute);
define('NVWEB_OBJECT', $nvweb_absolute.'/object');	

// prepare layout
$layout = new layout('navigate');

$layout->add_content('<div class="navigate-top"></div>');

$layout->navigate_logo();
$layout->navigate_session();
$layout->navigate_title();

$menu_html = $menu_layout->generate_html();

// load website theme
if(!empty($website->theme))
{
	$theme = new theme();
	$theme->load($website->theme);

    if(!empty($website->theme) && empty($theme->title))
    {
        $layout->navigate_notification(t(439, 'Error loading theme').' '.$website->theme, true);
        debug_json_error($website->theme.': JSON ERROR ');
    }
}

$layout->add_content('<div id="navigate-menu">'.$menu_html.'</div>');

$content = core_run();

$layout->navigate_footer();

$layout->add_content('<div id="navigate-content" class="navigate-content ui-corner-all">'.$content.'</div>');

$layout->navigate_additional_scripts();

// print layout
if(!isset($_GET['mute']))
{
	if(!APP_DEBUG && headers_sent())
    {
        ob_start("ob_gzhandler");
    }

    echo $layout->generate();

    if(!APP_DEBUG)
    {
        ob_end_flush();
    }
}

core_terminate();

?>