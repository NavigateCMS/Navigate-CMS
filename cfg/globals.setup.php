<?php
/* NAVIGATE */
/* Globals configuration file */

/* App installation details */
define('APP_NAME', 'Navigate CMS');
define('APP_VERSION', '{APP_VERSION}');
define('APP_OWNER', "{APP_OWNER}");
define('APP_REALM', "{APP_REALM}"); // used for password encryption, do not change!
define('APP_DEBUG', false || isset($_REQUEST['debug']));

/* App installation paths */
define('NAVIGATE_PARENT', '{NAVIGATE_PARENT}');	// absolute URL to folder which contains the navigate folder
define('NAVIGATE_FOLDER', "{NAVIGATE_FOLDER}"); // name of the navigate folder (deafault: /navigate)
define('NAVIGATE_PATH', "{NAVIGATE_PATH}"); // absolute system path to navigate folder

define('NAVIGATE_PRIVATE', "{NAVIGATE_PRIVATE}");
define('NAVIGATE_MAIN', "{NAVIGATE_MAIN}");
define('NAVIGATE_DOWNLOAD', NAVIGATE_PARENT.NAVIGATE_FOLDER.'/navigate_download.php');

define('NAVIGATECMS_STATS', {NAVIGATECMS_STATS});
define('NAVIGATECMS_UPDATES', {NAVIGATECMS_UPDATES});

/* Optional Utility Paths */
define('JAVA_RUNTIME', '"{JAVA_RUNTIME}"');

/* Database connection */
define('PDO_HOSTNAME', "{PDO_HOSTNAME}");
define('PDO_PORT',     "{PDO_PORT}");
define('PDO_SOCKET',   "{PDO_SOCKET}");
define('PDO_DATABASE', "{PDO_DATABASE}");
define('PDO_USERNAME', "{PDO_USERNAME}");
define('PDO_PASSWORD', "{PDO_PASSWORD}");
define('PDO_DRIVER',   "{PDO_DRIVER}");

ini_set('magic_quotes_runtime', false);
mb_internal_encoding("UTF-8");	/* Set internal character encoding to UTF-8 */

ini_set('display_errors', false);
if(APP_DEBUG)
{
	ini_set('display_errors', true);
	error_reporting(E_ALL ^ E_NOTICE);		
}

?>