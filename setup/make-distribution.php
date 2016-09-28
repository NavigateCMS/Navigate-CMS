<?php
/* Navigate MAKE DISTRIBUTION v2.1.2 */
/* 		created by: Naviwebs   http://www.naviwebs.com	*/
/* creates a distribution package for Navigate */
/* Requirements: installed and functional copy of Navigate */

/* CREATE A DISTRIBUTION NAVIGATE PACKAGE */
/* ****************************************
	1/ Create temporary folder
	2/ Dump database structure
	3/ Dump main table rows
			nv_countries
			nv_functions
			nv_languages
			nv_menus
			nv_profiles
    4/ Ignore default theme content in development environment (Ocean / Theme Kit)
	5/ Pack folders and files in "package.zip"
		include: (root)
				 cache
				 cfg (without the valid globals.php),
				 css
				 docs
				 img
				 js
				 lib
				 plugins
				 private
                    sessions (empty folder)
                    oembed (empty folder)
				 web
	6/ Repack SQL, logo, setup.php and package.zip as Navigate.zip
	7/ Remove temporary files
*/

require_once('../cfg/globals.php');
require_once('../cfg/common.php');
require_once('../lib/core/misc.php');
require_once('../lib/external/misc/zipfile.php');

/* global variables */
global $DB;

set_time_limit(0);

// create database connection or exit
$DB = new database();
if(!$DB->connect())
{
	die(APP_NAME.' # ERROR<br /> '.$DB->get_last_error());	
}

$current_version = update::latest_installed();

/*	1/ Create temporary folder	*/
// we assume we are in navigate/setup folder
if(!@mkdir('distribution'))
    die(APP_NAME.' # ERROR<br /> '."Can't create distribution folder.");

/*	2/ Dump database structure	*/

$sql = array();

$DB->query('SHOW TABLES', 'array');
$tmp = array_keys($DB->first());
$tmp = $tmp[0];
$tables = array_values($DB->result($tmp));

foreach($tables as $table)
{
	$DB->query('SHOW CREATE TABLE '.$table, 'array');
	$sql[] = 'DROP TABLE IF EXISTS '.$table.';';
	$table = $DB->first();

    $create_table = $table['Create Table'];
    // force autoincrement = 0 on all tables
    $create_table = preg_replace('/AUTO_INCREMENT=(\d+)/i', 'AUTO_INCREMENT=0', $create_table);

	$sql[] = $create_table.';';
}

//	3/ Dump main table rows
$tables = array(
    'nv_countries',
	'nv_functions',
	'nv_languages',
	'nv_menus',
	'nv_profiles',
	'nv_updates'
);
				
foreach($tables as $table)
{
	$DB->query('SELECT * FROM '.$table, 'array');
	$rs = $DB->result();

	$rcount = 0;
	foreach($rs as $row)
	{
		$row = array_values($row);
		$row = array_map(protect, $row);
		$row = implode(',', $row);
        // every 100 rows, a new sentence
		if($rcount % 100 == 0)
			$sql[] = 'INSERT INTO '.$table.' VALUES ('.$row.');';
		else
			$sql[count($sql)-1] = rtrim($sql[count($sql)-1], ';').', ('.$row.');';
			
		$rcount++;
	}	
}

//  4/ Ignore default theme content in development environment -- leave data tables blank

// remove development paths
$sql = str_replace(NAVIGATE_PARENT.NAVIGATE_FOLDER, "{#!NAVIGATE_FOLDER!#}", $sql);

// prepare final SQL file
$sql = implode("\n\n", $sql);
file_put_contents('distribution/navigate.sql', $sql);

/*	5/ Pack folders and files in "package.zip"
		include: (root)
				 cache (ignore the contents)
				 cfg (without the valid globals.php),
				 css
				 docs
				 img
				 js
				 lib
				 plugins (at least "votes plugin" and "webuser_account_lite")
				 private [deprecated] (ocean files [161..172])
				 web
*/				 

$zipfile = new zipfile();

$navigate_files = rglob("*", GLOB_MARK, NAVIGATE_PATH.'/');
$total = count($navigate_files);
$index = 0;

foreach($navigate_files as $file)
{	
	//$index++;
	//echo 'Adding '.$index.'/'.$total.' ('.round(($index/$total*100),2).'%)<br />';
	//flush();
	
	if(!file_exists($file)) continue;	
	
	$file = substr($file, strlen(NAVIGATE_PATH.'/'));
	
	// file must be excluded from package?
	if($file=='cfg/globals.php') continue;
	if(substr($file, 0, strlen('setup/'))=='setup/') continue;
	if(substr($file, 0, strlen('docs/'))=='docs/') continue;
	if(substr($file, 0, strlen('updates/'))=='updates/') continue;
    if(substr($file, 0, strlen('private/'))=='private/') continue;
    if(substr($file, 0, strlen('themes/'))=='themes/') continue;
    if(substr($file, 0, strlen('cache/'))=='cache/') continue;
    // from 1.9.1, do not include ANY private file

    /* all files under plugins/ are safe to be added
    if(substr($file, 0, strlen('plugins/'))=='plugins/')
    {
        if(substr($file, 0, strlen('plugins/votes/'))!='plugins/votes/')
            continue;
    }
    */

    if(substr($file, 0, strlen('lib/external/tinymce/'))=='lib/external/tinymce/')
    {
        $pi = pathinfo($file);
        if( $pi['dirname']=='lib/external/tinymce'  &&
            $pi['extension']=='gz') // tinymce root && .gz extension => ignore
        continue;
    }
	if(substr($file, 0, strlen('setup/'))=='setup/') continue;
	if(substr($file, -1, 1)=="\\" || substr($file, -1, 1)=="/") continue;
	
	$file_data = file_get_contents(NAVIGATE_PATH.'/'.$file);
	
	if($file=='cfg/globals.setup.php')
	{
		$file_data = str_replace('{APP_VERSION}', $current_version->version." r".$current_version->revision, $file_data);	
	}
	
	$zipfile->addFile($file_data, $file);
}

// files that MUST be included but are excluded from the list by the rules
$zipfile->addFile(file_get_contents(NAVIGATE_PATH.'/.htaccess'), '.htaccess');
$zipfile->addFile(file_get_contents(NAVIGATE_PATH.'/private/.htaccess'), 'private/.htaccess');
$zipfile->addFile(file_get_contents(NAVIGATE_PATH.'/plugins/.htaccess'), 'plugins/.htaccess');
$zipfile->addFile(file_get_contents(NAVIGATE_PATH.'/private/sessions/.htaccess'), 'private/sessions/.htaccess');
$zipfile->addFile(file_get_contents(NAVIGATE_PATH.'/private/oembed/.htaccess'), 'private/oembed/.htaccess');
$zipfile->addFile(file_get_contents(NAVIGATE_PATH.'/web/.htaccess.example'), 'web/.htaccess.example');
$zipfile->addFile('', 'cache/empty.txt');
$zipfile->addFile('', 'updates/empty.txt');

$zipfile->addFile(file_get_contents(NAVIGATE_PATH.'/themes/theme_kit.zip'), 'themes/theme_kit.zip');

$contents = $zipfile->file();
file_put_contents("distribution/package.zip", $contents);

unset($zipfile);

/*	6/ Repack SQL, logo, setup.php and package.zip as Navigate.zip	*/

$zipfile = new zipfile();
$zipfile->addFile(file_get_contents('setup.php'), 'setup.php');
$zipfile->addFile(file_get_contents('distribution/navigate.sql'), 'navigate.sql');
$zipfile->addFile(file_get_contents('distribution/package.zip'), 'package.zip');
file_put_contents("navigate-".$current_version->version."r".$current_version->revision.".zip", $zipfile->file());

/*	7/ Remove temporary files	*/

rrmdir('distribution');

core_terminate();

function rrmdir($dir) 
{
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (filetype($dir."/".$object) == "dir")
             rrmdir($dir."/".$object);
         else unlink($dir."/".$object);
       }
     }
     reset($objects);
     rmdir($dir);
   }
}

?>