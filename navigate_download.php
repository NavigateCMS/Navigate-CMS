<?php
if(!empty($_REQUEST['sid']))
    session_id($_REQUEST['sid']);

require_once('cfg/globals.php');
require_once('cfg/common.php');
require_once('lib/packages/files/file.class.php');
require_once('web/nvweb_objects.php');

/* global variables */
global $DB;
global $config;
global $website;

if(empty($_REQUEST['id'])) exit;

// create database connection
$DB = new database();
if(!$DB->connect())	exit;

if(empty($_SESSION['APP_USER']))
    exit;

$item = new file();

$id = $_REQUEST['id'];
if(!empty($_REQUEST['id']))
{
	if(is_int($id))
		$item->load($id);
	else
		$item->load($_REQUEST['id']);
}

if(!$item->id)
{
	echo 'Error: no item found with id '.$_REQUEST['id'].'.';	
	session_write_close();
	$DB->disconnect(); 	// we don't need the database anymore (we'll see)
	exit;
}

$website = new Website();
if(!empty($_GET['wid']))
    $website->load(intval($_GET['wid']));
else if($item->website > 0)
	$website->load($item->website);
else
	$website->load();

$path = NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$item->id;

// pass control to usual website download (ignoring enabled check)
$_REQUEST['type'] = $item->type;
$_REQUEST['force_resize'] = 'true';

nvweb_object(true, true); // ignore all permissions

?>