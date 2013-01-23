<?php
/* Navigate MAKE DISTRIBUTION v1.2 */
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
    4/ Dump default Ocean theme content
	5/ Pack folders and files in "package.zip"
		include: (root)
				 cache
				 cfg (without the valid globals.php),
				 css
				 docs
				 img
				 js
				 lib
				 plugins (empty folder)
				 private (empty folder)
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

// TO DO: clean AUTO_INCREMENT=3 DEFAULT	-->	AUTO_INCREMENT=0 DEFAULT

/*	4/ Dump main table rows
			nv_countries
			nv_functions
			nv_languages
			nv_menus
			nv_profiles
			nv_updates
*/

$tables = array('nv_countries',
				'nv_functions',
				'nv_languages',
				'nv_menus',
				'nv_profiles',
				'nv_updates');
				
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
		if($rcount % 500 == 0)
			$sql[] = 'INSERT INTO '.$table.' VALUES ('.$row.');';
		else
			$sql[count($sql)-1] = rtrim($sql[count($sql)-1], ';').', ('.$row.');';
			
		$rcount++;
	}	
}

/*  5/ Dump default Ocean theme content
        nv_structure
        nv_items
        nv_comments
        nv_blocks
        nv_webdictionary
        nv_properties_items
        nv_paths
        nv_notes
        nv_feeds
        nv_files (ids 119, 160..172)
*/

$tables = array(
    'nv_items',
    'nv_structure',
    'nv_comments',
    'nv_blocks',
    'nv_webdictionary',
    'nv_properties_items',
    'nv_properties_items',
    'nv_paths',
    'nv_notes',
    'nv_feeds',
    'nv_files',
    'nv_websites'
);

foreach($tables as $table)
{
    $extra = '';
    if($table=='nv_files')
        $extra .= ' WHERE (id = 119) OR (id >= 160 AND id <= 172) OR (id=190 OR id=189 OR id=188)';

    $DB->query('SELECT * FROM '.$table.$extra, 'array');
    $rs = $DB->result();

    $rcount = 0;
    foreach($rs as $row)
    {
        if($table=='nv_websites') // remove real private data
        {
            $row['domain'] = '';
            $row['folder'] = '';
            $row['default_timezone'] = 'UTC';
            $row['mail_server'] = '';
            $row['mail_port'] = '';
            $row['mail_security'] = '';
            $row['mail_user'] = '';
            $row['mail_address'] = '';
            $row['mail_password'] = '';
            $row['contact_emails'] = '';
        }

        $row = array_values($row);
        $row = array_map(protect, $row);
        $row = implode(',', $row);
        if($rcount % 500 == 0)
            $sql[] = 'INSERT INTO '.$table.' VALUES ('.$row.');';
        else
            $sql[count($sql)-1] = rtrim($sql[count($sql)-1], ';').', ('.$row.');';

        $rcount++;
    }
}

// prepare final SQL file
$sql = implode("\n\n", $sql);
file_put_contents('distribution/navigate.sql', $sql);

/*	5/ Pack folders and files in "package.zip"
		include: (root)
				 cache
				 cfg (without the valid globals.php),
				 css
				 docs
				 img
				 js
				 lib
				 plugins (at least "votes plugin" and "webuser_account_lite")
				 private (ocean files [161..172])
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
    if(substr($file, 0, strlen('private/'))=='private/')
    {
        if(!in_array(basename($file), array(
            '161',  '162',  '163',  '164',  '165',
            '166',  '167',  '168',  '169',  '170',
            '171',  '172',  '119'
            )
        ))
            continue;
    }
	if(substr($file, 0, strlen('themes/'))=='themes/')
    {
        if(substr($file, 0, strlen('themes/ocean/'))!='themes/ocean/')
            continue;
    }

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
$zipfile->addFile(file_get_contents(NAVIGATE_PATH.'/web/.htaccess.example'), 'web/.htaccess.example');
$zipfile->addFile('', 'updates/empty.txt');

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