<?php
if(!empty($_REQUEST['sid']))
{
    session_id($_REQUEST['sid']);
}

require_once('cfg/globals.php');
require_once('cfg/common.php');
require_once('lib/packages/files/file.class.php');
require_once('web/nvweb_objects.php');

/* global variables */
global $DB;
global $config;
global $website;

if(empty($_REQUEST['id']))
{
    exit;
}

// create database connection
$DB = new database();
if(!$DB->connect())
{
    exit;
}

if(empty($_SESSION['APP_USER#'.APP_UNIQUE]))
{
    exit;
}

$website = new website();
if(!empty($_GET['wid']))
{
    $website->load(intval($_GET['wid']));
}
else if($item->website > 0)
{
    $website->load($item->website);
}
else
{
    $website->load();
}

$item = new file();

$id = $_REQUEST['id'];
if(!empty($_REQUEST['id']))
{
    if(is_int($id))
    {
        $item->load($id);
    }
    else
    {
        // sanitize "id" parameter to avoid XSS problems
        // note: if the "id" parameter is not numeric, then it could be an external URL request
        $url = $_REQUEST['id'];
        $url = filter_var($url, FILTER_SANITIZE_URL);

        // disallow use of < > chars in a URL
        $url = str_replace(array('<', '>'), '', $url);

        // prevent directory traversal
        $url_dtr = core_remove_directory_traversal($url);
        if($url != $url_dtr)
        {
            $url = "";
        }

        // make sure it is an external URL
        if( strpos($url, 'http://')===0 ||
            strpos($url, 'https://')===0
        )
        {
            $item->load($url);
        }
        else
        {
            header("HTTP/1.1 404 Not Found");
            core_terminate();
        }
    }
}

if(!$item->id)
{
    echo 'Error: no item found with id '.$_REQUEST['id'].'.';
    core_terminate();
}

$path = NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$item->id;

// pass control to usual website download (ignoring enabled check)
//$_REQUEST['type'] = $item->type;
$_REQUEST['force_resize'] = 'true';

nvweb_object(true, true, $item); // ignore all permissions

?>