<?php
error_reporting(E_ALL ^ E_NOTICE);
@ini_set('display_errors', 1);
@ini_set('magic_quotes_runtime', 0);
@session_start();
date_default_timezone_set("UTC");

if(!empty($_POST['NAVIGATE_FOLDER']))
    $_SESSION['NAVIGATE_FOLDER'] = $_POST['NAVIGATE_FOLDER'];
if(empty($_SESSION['NAVIGATE_FOLDER']))
    $_SESSION['NAVIGATE_FOLDER'] = '/navigate';

if(!file_exists(basename($_SESSION['NAVIGATE_FOLDER']).'/cfg/globals.php'))
{
	define('APP_NAME', 'Navigate CMS');
	define('APP_VERSION', '2.4');
    define('NAVIGATE_FOLDER', $_SESSION['NAVIGATE_FOLDER']);

	@session_start();
}
else
{
    // save session values... when including common.php a new session will be created and then the values must be restored
	$session_values = json_encode($_SESSION);

	include_once(basename($_SESSION['NAVIGATE_FOLDER']).'/cfg/globals.php');
	include_once(basename($_SESSION['NAVIGATE_FOLDER']).'/cfg/common.php');
    if(file_exists(basename($_SESSION['NAVIGATE_FOLDER']).'/lib/packages/themes/theme.class.php'))
	    include_once(basename($_SESSION['NAVIGATE_FOLDER']).'/lib/packages/themes/theme.class.php');
    define('NAVIGATE_URL', NAVIGATE_PARENT.NAVIGATE_FOLDER);

	// restore session values
	if(!isset($_SESSION['NAVIGATE-SETUP']))
        $_SESSION = json_decode($session_values, true);
	else if(!is_array($_SESSION['NAVIGATE-SETUP']))
	{
		$_SESSION['NAVIGATE-SETUP'] = unserialize($_SESSION['NAVIGATE-SETUP']);
	}
}

if($_REQUEST['step']=='cleaning')
{
    // remove installation files
    @unlink('navigate.sql');
    @unlink('package.zip');
    @unlink(NAVIGATE_PATH.'/themes/theme_kit.zip');
    @unlink(NAVIGATE_PATH.'/cfg/globals.setup.php');
    @unlink('setup.php');

    header('location: '.NAVIGATE_PARENT.NAVIGATE_FOLDER.'/'.NAVIGATE_MAIN);
}

/* global variables */
global $DB;
global $user;
global $config;
global $layout;
global $website;
global $theme;
global $events;

if(!empty($_REQUEST['process']))
    process();

$lang = navigate_install_load_language();

// Installation steps
// 0: check if navigate is already installed
// 1: check php requirements
// 2: decompress package
// 3: configuration values & checking
// 4: database creation (import empty tables), create admin user and default website
// 5: installation completed
// 6: clean setup files and redirect to navigate login

// check if navigate is already installed
//if(file_exists('cfg/globals.php') && file_exists('img/empty.png'))	die(APP_NAME.' is already installed!');

?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title><?php echo APP_NAME;?> v<?php echo APP_VERSION;?> - <?php echo $lang['install'];?></title>
        <script language="javascript" type="text/javascript" src="https://cdn.jsdelivr.net/jquery/1.11.3/jquery.min.js"></script>
        <script language="javascript" type="text/javascript" src="https://cdn.jsdelivr.net/jquery.ui/1.11.4/jquery-ui.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/jquery.ui/1.11.4/themes/cupertino/jquery-ui.min.css" type="text/css" />
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo navigate_favicon(); ?>">
        <style type="text/css">
            html
            {
                filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#60A5E4', endColorstr='#ffffff'); /* for IE */
                background-image: -webkit-gradient(linear, left top, left bottom, from(#60A5E4), to(#fff)); /* for webkit browsers */
                background-image: -moz-linear-gradient(top,  #60A5E4,  #fff); /* for firefox 3.6+ */
                background-repeat: no-repeat;
                height: 100%;
                width: 100%;
                background-color: #fff; /* for non-css3 browsers */
            }
            body  				{ font-size: 12px; font-family: Verdana; }
            a					{ outline: 0px; }
            h2					{ color: #fff;	}
            img					{ border: none; }
            label 				{ display: block;float: left;width: 250px; font-weight: bold; }
            input 				{ width: 500px;font-weight: normal; font-family: Verdana; }
            div.progressbar		{ width: 200px; clear: right; margin-left: 250px;  }
            input[type=submit] 	{ width: auto; font-family: Verdana; }
            #navigate-footer   	{ margin-top: 70px; text-align: center; font-size: 11px;  }
            #tabs 				{ height: 440px; }
            #tabs div > div 	{ margin-bottom: 10px; }
            #install_language	{ background: #AED0EA; border: solid 1px #CCF; color: #33D; font-weight: bold; }
            .help				{ float: right; margin-top: -5px; }
            .navigate-logo a:hover,
            .help:hover			{ opacity: 0.8; }
            .field-help-text	{ margin-left: 250px; margin-bottom: 0px; color: #777; font-size: 10px; }
            .green				{ background-color: #BFB; border: solid 1px #ccc; padding: 3px; height: 14px; font-family: Verdana; }
            .red				{ background-color: #FBB; border: solid 1px #ccc; padding: 3px; height: 14px; font-family: Verdana; }
            .orange				{ background-color: #FC7; border: solid 1px #ccc; padding: 3px; height: 14px; font-family: Verdana; }
            /*.ui-progressbar-value { background-image: url(http://www.preloaders.net/generator.php?image=16&speed=3&fore_color=60A5E4&back_color=FFFFFF&size=200x24&transparency=1&reverse=1&orig_colors=0); }*/
            .ui-progressbar-value { background-image: url(http://tools.navigatecms.com/object?id=132&disposition=inline); }
            .ui-widget 			{ font-family: Verdana; font-size: 11px; }
            .ui-widget input, .ui-widget select, .ui-widget button	{ font-family: Verdana; font-size: 11px; }

	        /* CHECKBOX STYLING     Thanks to Geoffrey Crofte http://codepen.io/CreativeJuiz/ */
			div [type="checkbox"]:not(.ui-helper-hidden-accessible) + label
			{
				width: auto;
				vertical-align: middle;
				display: inline-block;
				float: none;
				margin-top: 0px;
				padding-left: 0;
				font-weight: normal;
			}

			/* Base for label styling */
			[type="checkbox"]:not(:checked):not(.ui-helper-hidden-accessible),
			[type="checkbox"]:checked:not(.ui-helper-hidden-accessible)
			{
				position: absolute;
				left: -9999px;
			}
			[type="checkbox"]:not(:checked):not(.ui-helper-hidden-accessible) + label,
			[type="checkbox"]:checked:not(.ui-helper-hidden-accessible) + label {
				position: relative;
				padding-left: 25px;
				cursor: pointer;
			}

			/* checkbox aspect */
			[type="checkbox"]:not(:checked):not(.ui-helper-hidden-accessible) + label:before,
			[type="checkbox"]:checked:not(.ui-helper-hidden-accessible) + label:before {
				content: '';
				position: absolute;
				left:0;
				top: 0px;
				width: 15px;
				height: 15px;
				border-radius: 3px;
				background: #fff;
				border: solid 1px #ccc;
				color: #116;
				outline: none;
				font-weight: bold;
			}

			td[align="center"] [type="checkbox"]:not(:checked):not(.ui-helper-hidden-accessible) + label:before,
			td[align="center"] [type="checkbox"]:checked:not(.ui-helper-hidden-accessible) + label:before
			{
				left: -8px;
			}

			/* checked mark aspect */
			[type="checkbox"]:not(:checked):not(.ui-helper-hidden-accessible) + label:after,
			[type="checkbox"]:checked:not(.ui-helper-hidden-accessible) + label:after
			{
				content: '✔';
				position: absolute;
				top: -1px;
				left: 3px;
				font-size: 13px;
				color: #116;
				transition: all .2s;
				outline: none;
			}

			td[align="center"] [type="checkbox"]:not(:checked):not(.ui-helper-hidden-accessible) + label:after,
			td[align="center"] [type="checkbox"]:checked:not(.ui-helper-hidden-accessible) + label:after
			{
				left: -5px;
			}

			/* checked mark aspect changes */
			[type="checkbox"]:not(:checked):not(.ui-helper-hidden-accessible) + label:after
			{
				opacity: 0;
				transform: scale(0);
			}
			[type="checkbox"]:checked:not(.ui-helper-hidden-accessible) + label:after
			{
				opacity: 1;
				transform: scale(1);
			}
			/* disabled checkbox */
			[type="checkbox"]:disabled:not(:checked):not(.ui-helper-hidden-accessible) + label:before,
			[type="checkbox"]:disabled:checked:not(.ui-helper-hidden-accessible) + label:before
			{
				box-shadow: none;
				border-color: #bbb;
				background-color: #ddd;
			}

			[type="checkbox"]:disabled:checked + label:after
			{
				color: #999;
			}

			[type="checkbox"]:disabled + label
			{
				color: #aaa;
			}

			/* accessibility */
			[type="checkbox"]:checked:focus:not(.ui-helper-hidden-accessible) + label:before,
			[type="checkbox"]:not(:checked):focus:not(.ui-helper-hidden-accessible) + label:before
			{
				border: 1px dotted #44A7E8;
			}

			/* hover style just for information */
			label:hover:before
			{
				border: 1px solid #44A7E8!important;
			}

			[type="checkbox"]:not(.ui-helper-hidden-accessible) + label + span.navigate-form-row-info
			{
				margin-left: 24px;
				margin-top: 3px;
				display: inline-block;
				height: auto;
			}
        </style>
    </head>

<body>

<div id="navigate-install" style=" width: 960px; margin-left: auto; margin-right: auto; ">
    <div class="navigate-logo">
       <a href="http://www.navigatecms.com" target="_blank"><img src="<?php echo navigate_install_logo();?>" width="150" height="70" /></a>
    </div>
    <?php
	switch($_REQUEST['step'])
	{
        case 5:
			navigate_install_completed();
			break;
			
		case 4:
			navigate_install_create_database();
			break;
			
		case 3:	
			navigate_install_configuration();
			break;
			
		case 2:
			navigate_install_decompress();
			break;
			
		case 1:
		default:
			navigate_install_requirements();
	}
	?>
    <div id="navigate-footer">
        <?php echo APP_NAME;?> v<?php echo APP_VERSION;?>, &copy; <?php echo date('Y');?> <a href="http://www.naviwebs.com">NaviWebs</a>
    </div>
    <br />
</div>

<script language="javascript">
    $(window).load(function()
    {
        $("#tabs").tabs();
        $("button, input:submit, .ui-button").button();
    });
</script>


</body>

</html>

<?php

function navigate_install_requirements()
{
	global $lang;

	$checks = array();

	$checks['diskspace'] = floor(disk_free_space(dirname($_SERVER['SCRIPT_FILENAME'])) / (1024*1024)) > 50;
	$checks['server'] = true; //(stripos($_SERVER['SERVER_SOFTWARE'], 'apache')!==false);
	$checks['php5.3'] = (version_compare(PHP_VERSION, '5.3.0') >= 0);
	$checks['gd'] = extension_loaded('gd');
	//$checks['imap'] = extension_loaded('imap');
	$checks['json'] = extension_loaded('json');
	$checks['mbstring'] = extension_loaded('mbstring');
	$checks['pdo'] = extension_loaded('pdo');	
	$checks['pdo_mysql'] = extension_loaded('pdo_mysql');	
	$checks['simplexml'] = extension_loaded('simplexml');	
	$checks['zip'] = extension_loaded('zip');	
	$checks['safemode'] = ini_get('safe_mode'); // must be disabled! (0)

	$size = navigate_install_decodesize(disk_free_space(dirname($_SERVER['SCRIPT_FILENAME'])));

    /* pre folder path detection (only needed in step 1) */
    $pre_folder_path = str_replace('//', '/', $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']));
    $pre_folder_path = 'http://'.$pre_folder_path;
    $pre_folder_path = rtrim($pre_folder_path, '/');
    $pre_folder_path = rtrim($pre_folder_path, '\\');
	?>
    <h2>
	    <a href="http://www.navigatecms.com/help?lang=<?php echo $_SESSION['navigate_install_lang'];?>&fid=setup_requirements" target="_blank" class="help"><img src="<?php echo navigate_help_icon(); ?>" width="32" height="32" /></a>
		<?php echo $lang['navigate_installer'];?> [1/5]: <?php echo $lang['requirements'];?>
    </h2>
	<form action="?step=2" method="post">
    	<div style=" position: absolute;  margin-left: 718px;  margin-top: -36px; width: 200px; text-align: right; font-size: 15px; color: #fff; ">
        	<select id="install_language" name="install_language">
            	<option value="en-en_US" <?php echo ($_SESSION['navigate_install_lang']=='en')? 'selected="selected"' : '';?>>English</option>
                <option value="es-es_ES" <?php echo ($_SESSION['navigate_install_lang']=='es')? 'selected="selected"' : '';?>>Español</option>
                <option value="de-de_DE" <?php echo ($_SESSION['navigate_install_lang']=='de')? 'selected="selected"' : '';?>>Deutsch</option>
                <option value="ca-ca_ES" <?php echo ($_SESSION['navigate_install_lang']=='ca')? 'selected="selected"' : '';?>>Català</option>
                <option value="pl-pl_PL" <?php echo ($_SESSION['navigate_install_lang']=='pl')? 'selected="selected"' : '';?>>Polski</option>
            </select>
        </div>
        <div id="tabs">
            <ul>
                <li><a href="#tabs-1"><?php echo $lang['requirements'];?></a></li>
            </ul>    
            <div id="tabs-1">
                <div>
                    <label><?php echo $lang['app_folder'];?></label>
                    <input type="text" name="NAVIGATE_FOLDER" value="<?php echo NAVIGATE_FOLDER;?>" />
                    <div class="field-help-text"><?php echo $pre_folder_path;?><strong id="app_folder_example"><?php echo NAVIGATE_FOLDER;?></strong></div>
                </div>
                <div>
                    <label><?php echo $lang['disk_space'];?></label>
                    <input type="text" value="<?php echo $size;?> (> 50 MB)" class="<?php echo ($checks['diskspace']? 'green' : 'red');?>" />
                </div>
                <div>
                    <label><?php echo $lang['server'];?></label>
                    <input type="text" value="<?php echo $_SERVER['SERVER_SOFTWARE'];?>" class="<?php echo ($checks['server']? 'green' : 'red');?>" />
                </div>                 
                <div>
                    <label>PHP &ge; 5.3</label>
                    <input type="text" value="<?php echo ($checks['php5.3']? $lang['found'] : $lang['not_found']);?> (<?php echo PHP_VERSION;?>)" class="<?php echo ($checks['php5.3']? 'green' : 'red');?>" />
                </div>                    
                <div>
                    <label>&raquo; GD</label>
                    <input type="text" value="<?php echo ($checks['gd']? $lang['found'] : $lang['not_found']);?>" class="<?php echo ($checks['gd']? 'green' : 'red');?>" />
                </div> 
                <!--                                                      
                <div>
                    <label>&raquo; IMAP</label>
                    <input type="text" value="<?php echo ($checks['imap']? $lang['found'] : $lang['not_found']);?>" class="<?php echo ($checks['imap']? 'green' : 'red');?>" />
                </div>    
                -->        
                <div>
                    <label>&raquo; JSON</label>
                    <input type="text" value="<?php echo ($checks['json']? $lang['found'] : $lang['not_found']);?>" class="<?php echo ($checks['json']? 'green' : 'red');?>" />
                </div>                                                       
                <div>
                    <label>&raquo; MBString</label>
                    <input type="text" value="<?php echo ($checks['mbstring']? $lang['found'] : $lang['not_found']);?>" class="<?php echo ($checks['mbstring']? 'green' : 'red');?>" />
                </div>                                                       
                <div>
                    <label>&raquo; PDO</label>
                    <input type="text" value="<?php echo ($checks['pdo']? $lang['found'] : $lang['not_found']);?>" class="<?php echo ($checks['pdo']? 'green' : 'red');?>" />
                </div>         
                <div>
                    <label>&raquo; PDO_MYSQL</label>
                    <input type="text" value="<?php echo ($checks['pdo_mysql']? $lang['found'] : $lang['not_found']);?>" class="<?php echo ($checks['pdo_mysql']? 'green' : 'red');?>" />
                </div>
                <div>
                    <label>&raquo; SimpleXML</label>
                    <input type="text" value="<?php echo ($checks['simplexml']? $lang['found'] : $lang['not_found']);?>" class="<?php echo ($checks['simplexml']? 'green' : 'red');?>" />
                </div>                
                <div>
                    <label>&raquo; ZIP</label>
                    <input type="text" value="<?php echo ($checks['zip']? $lang['found'] : $lang['not_found']);?>" class="<?php echo ($checks['zip']? 'green' : 'red');?>" />
                </div>
                <div>
                    <label>&raquo; Safe mode</label>
                    <input type="text" value="<?php echo ($checks['safemode'] > 0? $lang['enabled'].' '.$lang['must_be_disabled'] : $lang['disabled']);?>" class="<?php echo ($checks['safemode'] > 0? 'red' : 'green');?>" />
                </div>
            </div>
        </div>
        <br />
        <input type="submit" value="<?php echo $lang['proceed_step_2'];?>" />
    </form>    
    <script language="javascript" type="text/javascript">
		$('#install_language').on('change', function()
		{
			window.location = '?lang=' + $(this).val();
		});

        $('input[name="NAVIGATE_FOLDER"]').on('keyup', function()
        {
            // remove invalid characters
            var tmp = $(this).val();
            tmp = tmp.replace(/[^a-zA-Z0-9]/g, '');
            tmp = '/' + tmp;
            $(this).val(tmp);
            $('#app_folder_example').html(tmp);
        });
	</script>
    <?php
}

function navigate_install_configuration()
{
	global $lang;

	$error = false;

	$navigate_parent_folder = str_replace('\\', '/', dirname(realpath(__FILE__)));
	$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
	//$navigate_root = str_replace($document_root, '', $navigate_parent_folder);

    // absolute URL to the folder that contains navigate folder,
    // f.e. //www.yourwebsite.com (for www.yourwebsite.com/navigate)
    // keep the protocol agnostic and do not add a final slash
    $navigate_parent_url = '//'.str_replace("//", "/", $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']));
    $navigate_parent_url = rtrim($navigate_parent_url, '/');
    $navigate_parent_url = rtrim($navigate_parent_url, '\\');

    $app_owner_default = substr($_SERVER['HTTP_HOST'], 0, strrpos($_SERVER['HTTP_HOST'], '.'));
    $app_owner_default = str_replace('www.', '', $app_owner_default);

	$defaults = array(
		'APP_OWNER' 		=> (empty($_REQUEST['APP_OWNER']))? $app_owner_default : $_REQUEST['APP_OWNER'],
		'APP_REALM' 		=> 'NaviWebs-NaviGate',		

		'NAVIGATE_PARENT'	=> (empty($_REQUEST['NAVIGATE_PARENT']))? $navigate_parent_url : $_REQUEST['NAVIGATE_PARENT'],
		'NAVIGATE_PATH'		=> (empty($_REQUEST['NAVIGATE_PATH']))? $navigate_parent_folder.$_SESSION['NAVIGATE_FOLDER'] : $_REQUEST['NAVIGATE_PATH'],
		'NAVIGATE_FOLDER'	=> $_SESSION['NAVIGATE_FOLDER'],
		'NAVIGATE_PRIVATE'	=> (empty($_REQUEST['NAVIGATE_PRIVATE']))? $navigate_parent_folder.$_SESSION['NAVIGATE_FOLDER'].'/private' : $_REQUEST['NAVIGATE_PRIVATE'],
		'NAVIGATE_MAIN'		=> (empty($_REQUEST['NAVIGATE_MAIN']))? 'navigate.php' : $_REQUEST['NAVIGATE_MAIN'],
		
		'PDO_HOSTNAME'		=> (empty($_REQUEST['PDO_HOSTNAME']))? 'localhost' : $_REQUEST['PDO_HOSTNAME'],		
		'PDO_PORT'		    => (empty($_REQUEST['PDO_PORT']))? '3306' : $_REQUEST['PDO_PORT'],
		'PDO_SOCKET'		=> (empty($_REQUEST['PDO_SOCKET']))? '/tmp/mysql.sock' : $_REQUEST['PDO_SOCKET'],
		'PDO_DATABASE'		=> (empty($_REQUEST['PDO_DATABASE']))? '' : $_REQUEST['PDO_DATABASE'],
		'PDO_USERNAME'		=> (empty($_REQUEST['PDO_USERNAME']))? 'root' : $_REQUEST['PDO_USERNAME'],		
		'PDO_PASSWORD'		=> (empty($_REQUEST['PDO_PASSWORD']))? '' : $_REQUEST['PDO_PASSWORD'],		
		'PDO_DRIVER'		=> (empty($_REQUEST['PDO_DRIVER']))? 'mysql' : $_REQUEST['PDO_DRIVER'],		
		
		'NAVIGATECMS_STATS'	=> (empty($_REQUEST['NAVIGATECMS_STATS']))? 'false' : 'true',
		'NAVIGATECMS_UPDATES'=>(empty($_REQUEST['NAVIGATECMS_UPDATES']))? 'false' : 'true',
		
		'JAVA_RUNTIME'		=> (empty($_REQUEST['JAVA_RUNTIME']))? 'java' : $_REQUEST['JAVA_RUNTIME'],		
		'ADMIN_USERNAME'	=> (empty($_REQUEST['ADMIN_USERNAME']))? 'admin' : $_REQUEST['ADMIN_USERNAME'],	
		'ADMIN_PASSWORD'	=> (empty($_REQUEST['ADMIN_PASSWORD']))? '' : $_REQUEST['ADMIN_PASSWORD'],
        'ADMIN_EMAIL'	    => (empty($_REQUEST['ADMIN_EMAIL']))? '' : $_REQUEST['ADMIN_EMAIL']
	);

    if(!file_exists('.'.$defaults['NAVIGATE_FOLDER'].'/cfg/globals.setup.php'))
        die('Setup files missing: cfg/globals.setup.php');

    if(!empty($_POST))
	{
		// create a configuration file		
		$globals = file_get_contents('.'.$defaults['NAVIGATE_FOLDER'].'/cfg/globals.setup.php');

		if(	!empty($defaults['APP_OWNER'])	&&
			!empty($defaults['APP_REALM'])  &&
			(!empty($defaults['PDO_HOSTNAME']) || !empty($defaults['PDO_SOCKET']))  &&
			!empty($defaults['PDO_DATABASE'])  &&
			!empty($defaults['PDO_USERNAME'])  &&
			!empty($defaults['ADMIN_USERNAME'])  &&
            !empty($defaults['ADMIN_EMAIL'])  &&
			!empty($defaults['ADMIN_PASSWORD'])  &&						
			!empty($defaults['PDO_DRIVER'])																		
		)
		{
            $app_unique = substr(md5($_SERVER["HTTP_USER_AGENT"]), 2, 10);
            $app_unique = uniqid('nv_'.$app_unique, true);

			$globals = str_replace('{APP_OWNER}', 			$defaults['APP_OWNER'], $globals);
			$globals = str_replace('{APP_REALM}', 			$defaults['APP_REALM'], $globals);
			$globals = str_replace('{APP_UNIQUE}', 			$app_unique,            $globals);

			$globals = str_replace('{NAVIGATE_PARENT}', 	$defaults['NAVIGATE_PARENT'], $globals);
			$globals = str_replace('{NAVIGATE_PATH}', 		$defaults['NAVIGATE_PATH'], $globals);
			$globals = str_replace('{NAVIGATE_FOLDER}', 	$defaults['NAVIGATE_FOLDER'], $globals);			

			$globals = str_replace('{NAVIGATE_PRIVATE}',	$defaults['NAVIGATE_PRIVATE'], $globals);				
			$globals = str_replace('{NAVIGATE_MAIN}', 		$defaults['NAVIGATE_MAIN'], $globals);

			$globals = str_replace('{NAVIGATECMS_STATS}', 	$defaults['NAVIGATECMS_STATS'], $globals);			
			$globals = str_replace('{NAVIGATECMS_UPDATES}', $defaults['NAVIGATECMS_UPDATES'], $globals);

            if($defaults['PDO_DRIVER']=='mysql-socket')
            {
                $defaults['PDO_DRIVER'] = 'mysql';
                $defaults['PDO_HOSTNAME'] = '';
                $defaults['PDO_PORT'] = '';
            }
            else
            {
                $defaults['PDO_SOCKET'] = '';
            }


            $globals = str_replace('{PDO_DRIVER}', 			$defaults['PDO_DRIVER'], $globals);
			$globals = str_replace('{PDO_HOSTNAME}', 		$defaults['PDO_HOSTNAME'], $globals);				
			$globals = str_replace('{PDO_PORT}', 		    $defaults['PDO_PORT'], $globals);
			$globals = str_replace('{PDO_SOCKET}', 		    $defaults['PDO_SOCKET'], $globals);
			$globals = str_replace('{PDO_DATABASE}', 		$defaults['PDO_DATABASE'], $globals);
			$globals = str_replace('{PDO_USERNAME}', 		$defaults['PDO_USERNAME'], $globals);				
			$globals = str_replace('{PDO_PASSWORD}', 		$defaults['PDO_PASSWORD'], $globals);										

			$globals = str_replace('{APP_VERSION}', 		APP_VERSION, $globals);

			$ok = file_put_contents('.'.$defaults['NAVIGATE_FOLDER'].'/cfg/globals.php', $globals);

			if($ok)
			{
				$_SESSION['NAVIGATE-SETUP'] = serialize(
					array(
						'ADMIN_USERNAME' => $_REQUEST['ADMIN_USERNAME'],
						'ADMIN_PASSWORD' => $_REQUEST['ADMIN_PASSWORD'],
						'ADMIN_EMAIL' => $_REQUEST['ADMIN_EMAIL'],
						'DEFAULT_THEME' => $_REQUEST['DEFAULT_THEME']
					)
				);
				session_write_close();

				?>
                <script language="javascript" type="text/javascript">
					window.location.replace('?step=4');
				</script>
                <?php
			}
			
			$error = !$ok;
		}		
	}

	if($error)
		die($lang['unexpected_error'].' could not create file (cfg/globals.php).');
		
	?>
    <h2>
        <a href="http://www.navigatecms.com/help?lang=<?php echo $_SESSION['navigate_install_lang'];?>&fid=setup_configuration" target="_blank" class="help"><img src="<?php echo navigate_help_icon(); ?>" width="32" height="32" /></a>
		<?php echo $lang['navigate_installer'];?> [3/5]: <?php echo $lang['configuration'];?>
    </h2>
	<form action="?step=3" method="post" onsubmit=" return false; ">
        <div id="tabs">
            <ul>
                <li><a href="#tabs-1"><?php echo $lang['owner_administrator'];?></a></li>
                <!--<li><a href="#tabs-2">Paths</a></li>-->
                <li><a href="#tabs-2"><?php echo $lang['database'];?></a></li>
            </ul>
            
            <div id="tabs-1">
                <div>
                    <label><?php echo $lang['app_owner'];?></label>
                    <input type="text" name="APP_OWNER" value="<?php echo $defaults['APP_OWNER'];?>" />
                    <div class="field-help-text"><?php echo $lang['app_owner_detail'];?></div>
                </div>
                <input type="hidden" name="APP_REALM" value="NaviWebs-NaviGate" />
	            <br />
	            <div>
                    <label><?php echo $lang['theme'];?></label>
                    <input style=" width: auto; " type="checkbox" name="DEFAULT_THEME" id="DEFAULT_THEME" value="theme_kit" checked="checked" /><label for="DEFAULT_THEME"><?php echo $lang['theme_default_install'];?>: <strong>Theme Kit</strong></label>
                </div>
                <br />
                <div>
                	<label><?php echo $lang['admin'];?></label>
                    <br />
                    <br />
                </div>
                <div>
                    <label style=" padding-left: 20px; margin-right: -20px; "><?php echo $lang['email'];?></label>
                    <input type="text" name="ADMIN_EMAIL" value="<?php echo $defaults['ADMIN_EMAIL'];?>" style=" width: 200px; " />
                </div>
                <div>
                    <label style=" padding-left: 20px; margin-right: -20px; "><?php echo $lang['username'];?></label>
                    <input type="text" name="ADMIN_USERNAME" value="<?php echo $defaults['ADMIN_USERNAME'];?>" style=" width: 200px; " />
                </div>
                <div>
                    <label style=" padding-left: 20px;  margin-right: -20px; "><?php echo $lang['password'];?></label>
                    <input type="password" name="ADMIN_PASSWORD" value="<?php echo $defaults['ADMIN_PASSWORD'];?>" style=" width: 200px; " />
                </div>   
                <br />
                <br />
				<div>
                    <label>&nbsp;</label>
                    <input style=" width: auto; " type="checkbox" name="NAVIGATECMS_UPDATES" id="NAVIGATECMS_UPDATES" value="true" <?php echo ($defaults['NAVIGATECMS_UPDATES']? 'checked="checked"' : '');?> /><label for="NAVIGATECMS_UPDATES"><?php echo $lang['check_updates'];?></label>
                </div>
				<div>
                    <label>&nbsp;</label>
                    <input style=" width: auto; " type="checkbox" name="NAVIGATECMS_STATS" id="NAVIGATECMS_STATS" value="true" <?php echo ($defaults['NAVIGATECMS_STATS']? 'checked="checked"' : '');?> /><label for="NAVIGATECMS_STATS"><?php echo $lang['app_statistics'];?></label>
                </div>
            </div>
            <!--
            <div id="tabs-2">
                <div>
                    <label>Absolute path to application</label>
                    <input type="text" name="NAVIGATE_PATH" value="<?php echo $defaults['NAVIGATE_PATH'];?>" />
                </div>
                <div>
                    <label>Absolute path to private files</label>
                    <input type="text" name="NAVIGATE_PRIVATE" value="<?php echo $defaults['NAVIGATE_PRIVATE'];?>" />
                </div>        
                <div>
                    <label>Absolute URL to application parent folder</label>
                    <input type="text" name="NAVIGATE_PARENT" value="<?php echo $defaults['NAVIGATE_PARENT'];?>" disabled="disabled" />
                </div>    
                <div>
                    <label>Relative URL to application</label>
                    <input type="text" name="NAVIGATE_URL" value="<?php echo $defaults['NAVIGATE_URL'];?>" />
                </div>        
                <div>
                    <label>Application core (relative)</label>
                    <input type="text" name="NAVIGATE_MAIN" value="<?php echo $defaults['NAVIGATE_MAIN'];?>" />
                </div>      
            </div>
            -->            
            <div id="tabs-2">
                <div>
                    <label><?php echo $lang['driver'];?></label>
                    <select name="PDO_DRIVER" id="pdo_driver">
                    	<option value="mysql" <?php echo ($defaults['PDO_DRIVER']=='mysql'? 'selected="selected"' : '');?>>MySQL</option>
                    	<option value="mysql-socket" <?php echo ($defaults['PDO_DRIVER']=='mysql-socket'? 'selected="selected"' : '');?>>MySQL (unix socket)</option>
                    	<!--<option value="sqlite" <?php echo ($defaults['PDO_DRIVER']=='sqlite'? 'selected="selected"' : '');?>>SQLite</option>-->
                    </select>
                </div>
                <div class="pdo-hostname">
                    <label><?php echo $lang['hostname'];?></label>
                    <input type="text" name="PDO_HOSTNAME" value="<?php echo $defaults['PDO_HOSTNAME'];?>" />
                </div>
                <div class="pdo-port">
                    <label><?php echo $lang['port'];?></label>
                    <input type="text" name="PDO_PORT" value="<?php echo $defaults['PDO_PORT'];?>" />
                </div>
                <div class="pdo-socket" style="display: none;">
                    <label><?php echo $lang['socket'];?></label>
                    <input type="text" name="PDO_SOCKET" value="<?php echo $defaults['PDO_SOCKET'];?>" />
                </div>
                <div>
                    <label><?php echo $lang['username'];?></label>
                    <input type="text" name="PDO_USERNAME" value="<?php echo $defaults['PDO_USERNAME'];?>" />
                </div>               
                <div>
                    <label><?php echo $lang['password'];?></label>
                    <input type="password" name="PDO_PASSWORD" value="<?php echo $defaults['PDO_PASSWORD'];?>" />
                </div>
                <div>
                	<label>&nbsp;</label>
                    <div id="navigate-database-check">&nbsp;</div>
                </div>
                <div id="pdo_database_select" style=" visibility: hidden; ">
                    <label><?php echo $lang['database'];?></label>
                    <select name="PDO_DATABASE" id="PDO_DATABASE">
                    	<!--<option value="<?php echo $defaults['PDO_DATABASE'];?>"><?php echo $defaults['PDO_DATABASE'];?></option>-->
                    </select>
                </div>
                <div>
                    <div id="pdo_database_create_allowed" style=" display: none; clear: both; " class="field-help-text"><div class="green" style=" width: 8px; height: 8px; float: left; margin-right: 6px; "></div> <?php echo $lang['database_create_allowed'];?></div>
                    <div id="pdo_database_create_not_allowed" style=" display: none; clear: both; " class="field-help-text"><div class="red" style=" width: 8px; height: 8px; float: left; margin-right: 6px; "></div> <?php echo $lang['database_create_not_allowed'];?></div>
                    <div id="pdo_database_empty_warning" style=" display: none; clear: both; " class="field-help-text"><div class="orange" style=" width: 8px; height: 8px; float: left; margin-right: 6px; "></div> <?php echo $lang['database_will_be_emptied'];?></div>
                </div>                                                           
            </div>    
        </div>
        <br />
        <input type="submit" value="<?php echo $lang['proceed_step_4'];?>" style=" display: none; " />
    </form>
	<script language="javascript" type="text/javascript">
	var host_databases = [];
	$(window).load(function()
	{
		(function( $ ) {
				$.widget(
                    "ui.combobox",
                    {
                        _create: function()
                        {
                            var self = this,
                                select = this.element.hide(),
                                selected = select.children( ":selected" ),
                                value = selected.val() ? selected.text() : "";

                            this.input = $( "<input>" )
                                .insertAfter( select )
                                .val( value )
                                .autocomplete(
                                {
                                    delay: 0,
                                    minLength: 0,
                                    source: host_databases /*function( request, response ) {
                                        var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
                                        response( select.children( "option" ).map(function() {
                                            var text = $( this ).text();
                                            if ( this.value && ( !request.term || matcher.test(text) ) )
                                                return {
                                                    label: text.replace(
                                                        new RegExp(
                                                            "(?![^&;]+;)(?!<[^<>]*)(" +
                                                            $.ui.autocomplete.escapeRegex(request.term) +
                                                            ")(?![^<>]*>)(?![^&;]+;)", "gi"
                                                        ), "<strong>$1</strong>" ),
                                                    value: text,
                                                    option: this
                                                };
                                        }) );
                                    }*/,
                                    select: function( event, ui )
                                    {
                                        $('#ui-autocomplete-input').val(ui.item.value);
                                        $('#PDO_DATABASE').append('<option value="'+ui.item.value+'">'+ui.item.value+'</option>');
                                        $('#PDO_DATABASE').val(ui.item.value);
                                    },
                                    change: function( event, ui )
                                    {
                                        var value = $('.ui-autocomplete-input').val();
                                        $('#PDO_DATABASE').append('<option value="'+value+'">'+value+'</option>');
                                        $('#PDO_DATABASE').val(value);
                                    }
                                })
                                .addClass( "ui-widget" )	// ui-widget-content ui-corner-left" );
                                .css({'position': 'absolute'});

                            var input = this.input;

                            input.data( "ui-autocomplete" )._renderItem = function( ul, item )
                            {
                                return $( "<li></li>" )
                                    .data( "item.autocomplete", item )
                                    .append( "<a>" + item.label + "</a>" )
                                    .appendTo( ul );
                            };

                            this.button = $( "<button type='button'>&nbsp;</button>" )
                                .attr( "tabIndex", -1 )
                                .insertAfter( input )
                                .button({
                                    icons: {
                                        primary: "ui-icon-triangle-1-s"
                                    },
                                    text: false
                                })
                                .removeClass( "ui-corner-all" )
                                .addClass( "ui-corner-right ui-button-icon" )
                                .click(function() {
                                    // close if already visible
                                    if ( input.autocomplete( "widget" ).is( ":visible" ) ) {
                                        input.autocomplete( "close" );
                                        return;
                                    }

                                    // pass empty string as value to search for, displaying all results
                                    input.autocomplete( "search", "" );
                                    input.focus();
                                }).
                                css({ 'height': '20px',	'margin-left': '506px', 'width': '16px' });
					    },
                        destroy: function()
                        {
                            this.input.remove();
                            this.button.remove();
                            this.element.show();
                            $.Widget.prototype.destroy.call( this );
                        }
                    });
			    }
            )( jQuery );
		
		verify_database();
		
		$('#tabs-2').find('select,input').not('#PDO_DATABASE').on('change', verify_database);

        $('#pdo_driver').on('change', function()
        {
            $('.pdo-hostname,.pdo-port,.pdo-socket').hide();

            if($(this).val()=='mysql-socket')
                $('.pdo-socket').show();
            else
                $('.pdo-hostname,.pdo-port').show();
        });
	});
	
	function verify_database()
	{
		$('body').css('cursor', 'wait');
		$('#navigate-database-check').html('');
		$.ajax({
		  url: '<?php echo $_SERVER['PHP_SELF'];?>?process=verify_database',
		  dataType: 'json',
		  type: 'post',
		  async: false,
		  data: $('form').serialize(),
		  success: function(data)
		  {
            $('body').css('cursor', 'default');
            $('#pdo_database_create_allowed').hide();
            $('#pdo_database_create_not_allowed').hide();
            $('#pdo_database_empty_warning').hide();

            if(!data.error)
            {
                if(data.create_database_privilege)
                    $('#pdo_database_create_allowed').show();
                else
                    $('#pdo_database_create_not_allowed').show();

                $('#pdo_database_empty_warning').show();

                host_databases = data.databases;

                $('input[type=submit]').show();
                $('form').attr('onsubmit', '');
                $('#pdo_database_select').prev().hide();
                $('#pdo_database_select').css('visibility', 'visible');
                $('select[name="PDO_DATABASE"]').combobox();
                return true;
            }
            else
            {
                $('#navigate-database-check').html(data.error);
                $('input[type=submit]').hide();
                $('form').attr('onsubmit', ' return false;');
                $('#pdo_database_select').prev().show();
                $('#pdo_database_select').css('visibility', 'hidden');
                return false;
            }
		  }
		});
	}
	</script>    
	<?php
}

function navigate_install_decompress()
{		
	global $lang;
	?>
    <h2>
    	<a href="http://www.navigatecms.com/help?lang=<?php echo $_SESSION['navigate_install_lang'];?>&fid=setup_decompress" target="_blank" class="help"><img src="<?php echo navigate_help_icon(); ?>" width="32" height="32" /></a>
		<?php echo $lang['navigate_installer'];?> [2/5]: <?php echo $lang['decompress'];?>
    </h2>
	<form action="?step=3" method="post">
        <div id="tabs">
            <ul>
                <li><a href="#tabs-1"><?php echo $lang['decompress'];?></a></li>
            </ul>    
            <div id="tabs-1">
				<div class="navigate-install-decompress-check">
                    <label><?php echo $lang['verify_package'];?></label>
                    <!--<input type="text" name="" value="Ok!" class="green" />-->
                    <div class="progressbar"></div>
                </div>              
                <br />
				<div class="navigate-install-decompress-extraction" style=" display: none; ">
                    <label><?php echo $lang['file_extraction'];?></label>
                    <div class="progressbar"></div>
                </div>
                <br />              
				<div class="navigate-install-decompress-permissions" style=" display: none; ">
                    <label><?php echo $lang['change_permissions'];?></label>
                    <div class="progressbar"></div>
                </div>              
			</div>
        </div>
        <br />
        <input type="submit" value="<?php echo $lang['proceed_step_3'];?>" style=" display: none; " />
    </form>
    <script language="javascript" type="text/javascript">
	$(window).load(function()
	{
		$( ".progressbar" ).progressbar({
			value: 50
		});
		
		verify_zip();
	});
	
	function verify_zip()
	{
		$.ajax({
		  url: '<?php echo $_SERVER['PHP_SELF'];?>?process=verify_zip',
		  dataType: 'json',
		  data: {},
		  success: function(data)
		  {
			 if(data!=true)
			 {
	 			$('.navigate-install-decompress-check').find('div:first').removeClass().html('<input type="text" name="" value="'+data+'" class="red" />');
			 }
			 else
			 {
				$('.navigate-install-decompress-check').find('div:first').removeClass().html('<input type="text" name="" value="<?php echo $lang['done'];?>" class="green" />');
				extract_zip();
			 }
			 
		  }
		});		
	}
	
	function extract_zip()
	{
		$('.navigate-install-decompress-extraction').show();
		
		$.ajax({
		  url: '<?php echo $_SERVER['PHP_SELF'];?>?process=extract_zip',
		  dataType: 'json',
		  data: {},
		  success: function(data)
		  {
			 if(data!=true)
			 {
	 			$('.navigate-install-decompress-extraction').find('div:first').removeClass().html('<input type="text" name="" value="'+data+'" class="red" />');
			 }
			 else
			 {
				$('.navigate-install-decompress-extraction').find('div:first').removeClass().html('<input type="text" name="" value="<?php echo $lang['done'];?>" class="green" />');
				chmod_files();
			 }
			 
		  }
		});		
	}	
	
	
	function chmod_files()
	{
		$('.navigate-install-decompress-permissions').show();
		
		$.ajax({
		  url: '<?php echo $_SERVER['PHP_SELF'];?>?process=chmod',
		  dataType: 'json',
		  data: {},
		  success: function(data)
		  {
			 if(data!=true)
			 {
	 			$('.navigate-install-decompress-permissions').find('div:first').removeClass().html('<input type="text" name="" value="'+data+'" class="red" />');
                 $('input[type=submit]').show();
			 }
			 else
			 {
				$('.navigate-install-decompress-permissions').find('div:first').removeClass().html('<input type="text" name="" value="<?php echo $lang['done'];?>" class="green" />');
			 	$('input[type=submit]').show();
			 }
			 
		  }
		});		
	}	
	</script>
	<?php                			    
}

function navigate_install_decodesize( $bytes )
{
    $types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
    return( floor( $bytes ) . " " . $types[$i] );
}

function navigate_install_chmodr($path, $filemode) 
{
    if (!is_dir($path))
        return chmod($path, $filemode);

    $dh = opendir($path);
    while (($file = readdir($dh)) !== false) {
        if($file != '.' && $file != '..') {
            $fullpath = $path.'/'.$file;
            if(is_link($fullpath))
                return FALSE;
            elseif(!is_dir($fullpath) && !chmod($fullpath, $filemode))
                    return FALSE;
            elseif(!navigate_install_chmodr($fullpath, $filemode))
                return FALSE;
        }
    }

    closedir($dh);

    if(chmod($path, $filemode))
        return TRUE;
    else
        return FALSE;
}

function navigate_install_create_database()
{	
	global $DB;
	global $lang;

	?>
    <h2>
    	<a href="http://www.navigatecms.com/help?lang=<?php echo $_SESSION['navigate_install_lang'];?>&fid=setup_configuration" target="_blank" class="help"><img src="<?php echo navigate_help_icon(); ?>" width="32" height="32" /></a>
		<?php echo $lang['navigate_installer'];?> [4/5]: <?php echo $lang['database_import'];?>
    </h2>
	<form action="?step=5" method="post">
        <div id="tabs">
            <ul>
                <li><a href="#tabs-1"><?php echo $lang['database'];?></a></li>
            </ul>    
            <div id="tabs-1">
				<div class="navigate-install-database-create">
                    <label><?php echo $lang['database_check'];?></label>
                    <div class="progressbar"></div>
                </div>              
                <br />
				<div class="navigate-install-database-import" style=" display: none; ">
                    <label><?php echo $lang['database_import'];?></label>
                    <div class="progressbar"></div>
                </div>
                <br />              
				<div class="navigate-install-database-account" style=" display: none; ">
                    <label><?php echo $lang['account_create'];?></label>
                    <div class="progressbar"></div>
                </div>
                <br />
				<div class="navigate-install-default-theme" style=" display: none; ">
                    <label><?php echo $lang['theme_default_install'];?></label>
                    <div class="progressbar"></div>
                </div>
			</div>
        </div>
        <br />
        <input type="submit" value="<?php echo $lang['proceed_step_5'];?>" style=" display: none; " />
        <a class="ui-button" href="?step=4" style=" display: none; "><?php echo $lang['retry_step'];?></a>
    </form>
    <script language="javascript" type="text/javascript">
	$(window).load(function()
	{
		$( ".progressbar" ).progressbar({
			value: 50
		});
		
		database_create();
	});
	
	function database_create()
	{
		$.ajax({
		  url: '<?php echo $_SERVER['PHP_SELF'];?>?process=database_create',
		  dataType: 'json',
		  data: {},
		  success: function(data)
		  {
			 if(!data || data.error)
			 {
	 			$('.navigate-install-database-create').find('div:first').removeClass().html('<input type="text" name="" value="'+data.error+'" class="red" />');
				$('input[type=submit]').next().show();
			 }
			 else
			 {
				$('.navigate-install-database-create').find('div:first').removeClass().html('<input type="text" name="" value="'+data.ok+'" class="green" />');
				database_import();
			 }
		  }
		});		
	}
	
	function database_import()
	{
		$('.navigate-install-database-import').show();
		
		$.ajax({
		  url: '<?php echo $_SERVER['PHP_SELF'];?>?process=database_import',
		  dataType: 'json',
		  data: {},
		  success: function(data)
		  {
			 if(!data || data.error)
			 {
	 			$('.navigate-install-database-import').find('div:first').removeClass().html('<input type="text" name="" value="'+data.error+'" class="red" />');
				$('input[type=submit]').next().show();
			 }
			 else
			 {
				$('.navigate-install-database-import').find('div:first').removeClass().html('<input type="text" name="" value="'+data.ok+'" class="green" />');
				account_creation();
			 }
		  }
		});			
	}
	
	function account_creation()
	{
		$('.navigate-install-database-account').show();
		
		$.ajax({
		  url: '<?php echo $_SERVER['PHP_SELF'];?>?process=create_account',
		  dataType: 'json',
		  data: {},
		  success: function(data)
		  {
			 if(!data || data.error)
			 {
	 			$('.navigate-install-database-account').find('div:first').removeClass().html('<input type="text" name="" value="'+data.error+'" class="red" />');
			 }
			 else
			 {
				$('.navigate-install-database-account').find('div:first').removeClass().html('<input type="text" name="" value="'+data.ok+'" class="green" />');
				install_default_theme();
			 }
		  }
		});			
	}

    function install_default_theme()
	{
		$('.navigate-install-default-theme').show();

		$.ajax({
		  url: '<?php echo $_SERVER['PHP_SELF'];?>?process=install_default_theme',
		  dataType: 'json',
		  data: {},
		  success: function(data)
		  {
			 if(!data || data.error)
			 {
	 			$('.navigate-install-default-theme').find('div:first').removeClass().html('<input type="text" name="" value="'+data.error+'" class="red" />');
				$('input[type=submit]').next().show();
			 }
			 else
			 {
				$('.navigate-install-default-theme').find('div:first').removeClass().html('<input type="text" name="" value="'+data.ok+'" class="green" />');
				$('input[type=submit]').show();
			 }
		  }
		});
	}
	</script>
	<?php
}

function navigate_install_completed()
{
	global $lang;
	?>
    <h2>
    	<a href="http://www.navigatecms.com/help?lang=<?php echo $_SESSION['navigate_install_lang'];?>&fid=setup_completion" target="_blank" class="help"><img src="<?php echo navigate_help_icon(); ?>" width="32" height="32" /></a>
		<?php echo $lang['navigate_installer'];?> [5/5]: <?php echo $lang['install_completed'];?>
    </h2>
	<form action="setup.php?step=cleaning" method="post">
        <div id="tabs">
            <ul>
                <li><a href="#tabs-1">Navigate CMS</a></li>
            </ul>    
            <div id="tabs-1">
            	<div class="green"><?php echo $lang['navigate_installed'];?></div>
            	<div><div class="orange" style=" width: 8px; height: 8px; float: left; margin-right: 6px; "></div> <?php echo $lang['configure_your_server'];?></div>
                <br />
                <br />
	            <?php
				if(strpos(strtolower($_SERVER['SERVER_SOFTWARE']), 'apache')!==false ||
                   strpos(strtolower($_SERVER['SERVER_SOFTWARE']), 'litespeed')!==false)
				{
					?>
                    <p style=" font-weight: bold; "><?php echo $lang['apache_config'];?></p>
                    <p><?php echo $lang['copy_htaccess'];?></p>
                    <p><a id="htaccess_install" class="ui-button"><?php echo $lang['do_it_for_me'];?></a>
                    <br />
                    <br />
                   	<?php
				}
				else
				{
					?>
					<p style=" font-weight: bold; "><?php echo $lang['unknown_server'];?></p>
                    <div><div class="red" style=" width: 8px; height: 8px; float: left; margin-right: 6px; "></div> <?php echo $lang['check_navigate_website'];?></div>
					<br />
					<br />
                    <?php
                }
				?>
                <br />
                <br />
                <p><?php echo $lang['navigatecms_website_collection'];?></p>
                <p><a href="http://www.navigatecms.com" target="_blank">http://www.navigatecms.com</a></p>
                <br />
                <br />
				<p style=" font-weight: bold; "><?php echo $lang['thank_you'];?></p>
            </div>
        </div>
        <div id="htaccess_dialog" style=" display: none; ">
        	<?php echo $lang['htaccess_overwritten'];?>
        </div>
        <br />
		<input type="submit" value="<?php echo $lang['goto_navigate'];?>" />
    </form>    
    <script language="javascript" type="application/javascript">
		$('#htaccess_install').on('click', htaccess_install);
		
		function htaccess_install()
		{		
			$( "#htaccess_dialog" ).dialog(
			{
				title: "Navigate CMS",
				resizable: false,
				height:140,
				modal: true,
				buttons: {
					"<?php echo $lang['ok'];?>": function() 
					{
						$( this ).dialog( "close" );
						$.getJSON( '<?php echo $_SERVER['PHP_SELF'];?>?process=apache_htaccess', 
							{
							}, 
							function success(data, textStatus, jqXHR) 
							{
								if(data.error)
									data = data.error;
								else
									data = "<?php echo $lang['done'];?>";
									
								$('<div style=" margin: 10px; ">'+data+'</div>').dialog(
								{
									title: "Navigate CMS",
									resizable: false,
									modal: true,
									buttons: {
									"<?php echo $lang['ok'];?>": function() 
										{
											$( this ).dialog( "close" );
                                            $('#htaccess_install').button("disable").off("click");
										}
									}
								});
							});
					},
					"<?php echo $lang['cancel'];?>": function() 
					{
						$( this ).dialog( "close" );
					}
				}
			});
		}
		
	</script>
    <?php		
}

function process()
{
	global $DB;
    global $website;
    global $events;
    global $theme;
	
	set_time_limit(0);
	setlocale(LC_ALL, $_SESSION['navigate_install_locale']);
	$lang = navigate_install_load_language();
	
	switch($_REQUEST['process'])
	{
		case 'verify_zip':
			sleep(1);
			if(!file_exists('package.zip'))
				die(json_encode($lang['missing_package']));
			else
			{
				$zip = new ZipArchive;
				if($zip->open('package.zip') !== TRUE)
				{
					die(json_encode($lang['invalid_package']));
				}
				else
				{
					$zip->close();
					die(json_encode(true));
				}
			}
			break;	
			
		case 'extract_zip':
			$npath = getcwd().NAVIGATE_FOLDER;
			$npath = str_replace('\\', '/', $npath);

			if(!file_exists($npath)) mkdir($npath);
			
			if(file_exists($npath))
			{
				$zip = new ZipArchive;
				if ($zip->open('package.zip') === TRUE) 
				{
					$zip->extractTo($npath);
					$zip->close();
                    copy($npath . '/crossdomain.xml', dirname($npath).'/crossdomain.xml');
					die(json_encode(true));
				} 
				else 
				{
					die(json_encode($lang['extraction_failed']));
				}
			}
			die(json_encode($lang['folder_not_exists']));
		
			break;
			
		case 'chmod':
			sleep(1);
			// chmod the directories recursively
			$npath = getcwd().NAVIGATE_FOLDER;
			if(!navigate_install_chmodr($npath, 0755))
				die(json_encode($lang['chmod_failed']));
			else
				die(json_encode(true));
			break;
			
		case 'verify_database':
			if($_REQUEST['PDO_DRIVER']=='mysql' || $_REQUEST['PDO_DRIVER']=='mysql-socket')
			{
				try
				{
                    $dsn = "mysql:host=".$_REQUEST['PDO_HOSTNAME'].";port=".$_REQUEST['PDO_PORT'].';charset=utf8';
                    if($_REQUEST['PDO_DRIVER']=="mysql-socket")
                        $dsn = "mysql:unix_socket=".$_REQUEST['PDO_SOCKET'].";charset=utf8";

					$db_test = @new PDO($dsn, $_REQUEST['PDO_USERNAME'], $_REQUEST['PDO_PASSWORD']);
					
					if(!$db_test)
					{
						echo json_encode(array('error' => $lang['database_connect_error']));
					}
					else
					{
                        $create_database_privilege = false;
                        $drop_database_privilege = false;

						$stm = $db_test->query('SHOW DATABASES;');
						$rs = $stm->fetchAll(PDO::FETCH_COLUMN, 'Database');
						$rs = array_diff($rs, array('mysql', 'information_schema'));

						$stm = $db_test->query('SHOW PRIVILEGES;');
						$privileges = $stm->fetchAll(PDO::FETCH_ASSOC);

						for($p=0; $p < count($privileges); $p++)
						{
							if($privileges[$p]['Privilege'] == 'Create')
							{
								if(strpos($privileges[$p]['Context'], 'Databases')!==false)
									$create_database_privilege = true;
							}

                            if($privileges[$p]['Privilege'] == 'Drop')
                            {
                                if(strpos($privileges[$p]['Context'], 'Databases')!==false)
                                    $drop_database_privilege = true;
                            }
                        }

                        if($create_database_privilege && $drop_database_privilege)
                        {
                            // check if we are really allowed to create databases
                            $dbname = 'navigate_test_'.time();
                            $create_result = $db_test->exec('CREATE DATABASE '.$dbname);
                            if($create_result)
                                $db_test->exec('DROP DATABASE '.$dbname);

                            if(!$create_result)
                                $create_database_privilege = false;
                        }

						$db_test = NULL;
						
						echo json_encode(array(
                            'databases' => array_values($rs),
                            'create_database_privilege' => $create_database_privilege)
                        );
					}
				}
				catch(Exception $e)
				{
					echo json_encode(array('error' => $e->getMessage()));
				}
			}
			else
			{
				echo json_encode(array('error' => $lang['database_driver_error']));	
			}	
			exit;
			break;
			
		case 'database_create':	
			$DB = new database();
			if(!$DB->connect())
			{
				// try to create the database automatically
				if(PDO_DRIVER == 'mysql')
				{
					if(PDO_DATABASE!='')
					{
                        if(PDO_HOSTNAME!="")
                            $dsn = "mysql:host=".PDO_HOSTNAME.";port=".PDO_PORT.";charset=utf8";
                        else
                            $dsn = "mysql:unix_socket=".PDO_SOCKET.";charset=utf8";

						$db_test = new PDO($dsn, PDO_USERNAME, PDO_PASSWORD);
						$db_test->exec('CREATE DATABASE IF NOT EXISTS `'.PDO_DATABASE.'` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;');
						$db_test = NULL;
					}
				
					if(!$DB->connect())
						echo json_encode(array('error' => $DB->get_last_error()));				
					else
						echo json_encode(array('ok' => $lang['database_created']));	
				}
			}	
			else
			{
				echo json_encode(array('ok' => $lang['database_exists']));
			}
			exit;
			break;
			
		case 'database_import':
			$DB = new database();
			if(!$DB->connect())
				die(json_encode(array('error' => $DB->get_last_error())));		
			try
			{
				$sql = file_get_contents('navigate.sql');
                $sql = str_replace("{#!NAVIGATE_FOLDER!#}", NAVIGATE_PARENT.NAVIGATE_FOLDER, $sql);
				$sql = explode("\n\n", $sql);
				
				// can't do it in one step => SQLSTATE[HY000]: General error: 2014
				foreach($sql as $sqlline)
				{	
					$sqlline = trim($sqlline);
					if(empty($sqlline)) continue;		
					if(!@$DB->execute($sqlline))
						$error = $DB->get_last_error();
					if(!empty($error)) break;
				}
			}
			catch(Exception $e)
			{
				$error = $e->getMessage();	
			}
			
			if(!empty($error) && false)
				echo json_encode(array('error' => $error));
			else
				echo json_encode(array('ok' => $lang['done']));
			exit;
			break;
			
		case 'create_account':
			// create admin
			try
			{
				$DB = new database();
				if(!$DB->connect())
					die(json_encode(array('error' => $DB->get_last_error())));	
					
				$user = new user();
				$user->id = 0;
				$user->username = $_SESSION['NAVIGATE-SETUP']['ADMIN_USERNAME'];
				$user->set_password($_SESSION['NAVIGATE-SETUP']['ADMIN_PASSWORD']);
                $user->email = $_SESSION['NAVIGATE-SETUP']['ADMIN_EMAIL'];
				$user->profile = 1;
                $user->skin = 'cupertino';
				$user->language = $_SESSION['navigate_install_lang'];
				$user->blocked = 0;
				$user->timezone = 'UTC';
				$user->date_format = 'Y-m-d H:i';
                $user->decimal_separator = ',';
                $user->thousands_separator = '';
                $user->attempts = 0;
                $user->cookie_hash = '';
                $user->activation_key = '';

				$ok = $user->insert();
		
				if(!$ok) throw new Exception($lang['error']);

                // create default website details
                $website = new website();
                $website->create_default();
                $_SESSION['NAVIGATE-SETUP']['WEBSITE_DEFAULT'] = $website->id;

				echo json_encode(array('ok' => $lang['done']));	
			}
			catch(Exception $e)
			{
				echo json_encode(array('error' => $e->getMessage()));	
			}
			exit;

			break;


        case 'install_default_theme':
			try
			{
				$DB = new database();
				if(!$DB->connect())
					die(json_encode(array('error' => $DB->get_last_error())));

				if(@$_SESSION['NAVIGATE-SETUP']['DEFAULT_THEME']=='theme_kit')
				{
                    $website = new website();
                    $website->load($_SESSION['NAVIGATE-SETUP']['WEBSITE_DEFAULT']);

					$website->theme = 'theme_kit';
                    $website->languages = array(
                        'en' => array(
                            'language' => 'en',
                            'variant' => '',
                            'code' => 'en',
                            'system_locale' => 'en_US.utf8'
                        ),
                        'es' => array(
                            'language' => 'es',
                            'variant' => '',
                            'code' => 'es',
                            'system_locale' => 'es_ES.utf8'
                        )
                    );
                    $website->languages_published = array('en', 'es');
                    $website->save();

					// default objects (first user, no events bound...)
					$user = new user();
					$user->load(1);

                    $events = new events();

					$zip = new ZipArchive();
					$zip_open_status = $zip->open(NAVIGATE_PATH.'/themes/theme_kit.zip');

					if($zip_open_status === TRUE)
					{
						$zip->extractTo(NAVIGATE_PATH.'/themes/theme_kit');
						$zip->close();

                        $theme = new theme();
						$theme->load('theme_kit');
						$theme->import_sample($website);
					}

	                echo json_encode(array('ok' => $lang['done']));
				}
                else
                {
                    // user does not want to install the default theme
                    echo json_encode(array('ok' => $lang['not_selected']));
                }
			}
			catch(Exception $e)
			{
				echo json_encode(array('error' => $e->getMessage()));
			}
			exit;

			break;
			
		case 'apache_htaccess':
			try
			{
                $nvweb = dirname($_SERVER['REQUEST_URI']).NAVIGATE_FOLDER.'/web/nvweb.php';
                $nvweb = str_replace('//', '/', $nvweb);

				$data = array();
				$data[] = 'Options +FollowSymLinks';
                $data[] = 'Options -Indexes';
				$data[] = 'RewriteEngine On';
				$data[] = 'RewriteBase /';
				$data[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
				$data[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
				$data[] = 'RewriteRule ^(.+) '.$nvweb.'?route=$1 [QSA]';
				$data[] = 'RewriteRule ^$ '.$nvweb.'?route=nv.empty [L,QSA]';
				
				$ok = @file_put_contents(dirname(NAVIGATE_PATH).'/.htaccess', implode("\n", $data));
				if(!$ok) throw new Exception($lang['unexpected_error']);
				echo json_encode('true');
			}
			catch(Exception $e)
			{
 				echo json_encode(array('error' => $e->getMessage()));
			}			
			exit;
			break;
	}
	
}

function navigate_favicon()
{
    return 'data:image/x-icon;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAB3RJTUUH2wsFDS85seKkWQAAAAlwSFlzAAAewQAAHsEBw2lUUwAAAARnQU1BAACxjwv8YQUAAAFkSURBVHjaY2CgJtBzz2MiQk0fEDfC+CgaLu2c9A8oaQHE03BoZgRSuUCsgNUAKOAB4kyg4hAsciJA/BOIO2ACjDhsqgNSQUAXGQDZbEC2GhB/A+I0IOYHioMsYATS/7EaADVkM5ASB2JFqM0wcAuIs4Ga94A4zDg0KwGpKiDWAOI/QLwciK9A/Q7yYqq4ijnHy7sn9zJj0cwCVSwFxNeA+BjIRiAGhclhoM32QM0gQ8qAtDQjUMNSIEcfiOcD8SQgLgfiZiBuBSquAcr/Z2RiesjIwHjr55+/ev///++/sWdyp45rju6//wxPQLadgPozDIgToM4Ggc967vlr//37u+TKzkmxIAFr7yxldg6usBsgJ+6echkjFqAh/gGIzwPxaUZGptyfP74J3Tgw6yOuwEZPSL+gYoeA7AIGhv9AQxh5GPAAFixiJ4FYF5JKGIN+/fzxilQDQFEmDWJc3D7hGcOgBwBTznU8P2wFzgAAAABJRU5ErkJggg==';
}

function navigate_help_icon()
{
	return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAEtklEQVRYhe1XW4hVVRj+1t5nn8s+1/Y4o5lMpoEXIrQHQRPDghNDERXhe8REhMxDDBJCYFFRdKMp6mGCXnopoqIC8/QSKYoYDoiMKF5GB210mjNnzjn7tm5/D16YPXufkyOGL36wXtb+//V//2Wt/9/AXdxhsMUqfH1qqiSk2qJB6wgoEQAiNBlwwjBTB19Zu6z5vxD4/NiFqgaGlleK1XI2Y4ExKDAoAhRpSK0Rci6mm+0aVxgZ3tBfuy0EPj060S+0Hl2zzKlmszlckUBTApQgSwCyUNBBgNm5Zk0yY3D3I/0XbpnAB39NPFbIWD+s7nOc8yHg6iSzCUQI0EohdFt1HvLn9mxa+eeiCbx7+Ny2JSV7b0+paJ/1VMRjyTm454KIYFoWMvkCGIsfRUQI2m2P+/7AO4+uTiSRSOCtg6f7i7nsWJ9Tcc656sa+8lp4OOVjTSWLZeUCTMNAO+A4M9PE4YZCUOkDW3AkkYY3W68LITZ+uH1tLB1GEgEv4KN95aJzco6DS3Vj9WofT6xaihVOGSnTBGMMxVwGG1b04qV1fSjMXIzIc6kgFMG0C47gYvSmIjD8+/Hqyl5n35ROQ1I050v8GTz/QBlHppr4RzDYjLB1eRFLywUAQL3t4bPTHqx8MWaIt9sIPPfJLwYeityO1ELBkKshn6XgCRk7ZJJsfHLShZUv36B+fHwauzflYJkmeop5VNQMZqUd06VUGkI0hgBECERS8Oqvx0qVQqE62RTgXMeWQgpk2ZE9P13C5MzcVSNEsE0jUVdogFnZ6os/Hyt1jIAX8C25fMEKtY550AnCD1DMXg05YwwNX0CkVaKsgmkJ7m8GsC+RQCDUuoYvoVKJtRk9THBoHmBrRaO3dC8AoOH6mPAAk3UgoAhciPUdCYRSlRBKmBQrjQhSfgPDG3rQU7wHpVwGAKA14asjE9DpJdAiOYJKagipO6eACwUmNUwj2YPrKDGNVUsdEBHaAcfYhSv4baKFv80KWBddJRSEjH6PEBBSN5mQMFn3CLhcgDEGIsKBM5fxzUUDDGVc7UzdCXCpIt0ySkDROIT6zwj480LcFhoJNzYRkgtISeMdCUhFh3QQiJRhWd36VF2m8OWBU8iYBg5PeeCIPzxxEITnCtL60PzdSLnv37m9GbjtWhhycKE6rlAznJv1MeNLuLyzXEQn5Ag8r7Z/5+OdUwAAQuoR5blPmdlCR1/WpgO8//RGEBHOXq5jsHYJhpXp6r/y29AaIwv3Yxf+6K6BWtBq1sIg7OiNY1uga33CKdjoJsuFQhgECFqtfUd3DcSmpMRyV4RB2WqMmfmKk9Tnxy61MT3XRk/Rxh8nJqGMNLRMLlzSGspt1BnYy0nfO1bag2/+so1Ma6+RsW2w+MuoAxekJIysDWZayYeQhg5cjykxcHrPMzc/kFzH/W/8tE0T+9HIFRxmdH8bYraVgPbbdYPhufNvP7v4kew67nv9+36p1CjL5KvMyoIZ3fsEaQXiASj0ambKHLz03gu3PpTOR+/wt1Wt9BCsXJWZKQuMzVMngAikpCDh10zDHJn+aMftGcsXwnntuxKR3kya1oOhdM1+kxlsnDHjUP3jHYv6MbmLO45/AW883/Dpk14gAAAAAElFTkSuQmCCMTI2Mw==';	
}

function navigate_install_logo()
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAABGCAYAAAAuP23NAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA2RpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDpFNTJBMDI2QjA4MDdFMTExQThCRUE5QUE3MDYxNzI4RSIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo4QjREQjMyNjA3MDgxMUUxQkU0MEZCRTNGQTczMkU5RSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo4QjREQjMyNTA3MDgxMUUxQkU0MEZCRTNGQTczMkU5RSIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1IFdpbmRvd3MiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpFNTJBMDI2QjA4MDdFMTExQThCRUE5QUE3MDYxNzI4RSIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpFNTJBMDI2QjA4MDdFMTExQThCRUE5QUE3MDYxNzI4RSIvPiA8L3JkZjpEZXNjcmlwdGlv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';
}

function navigate_file_get_contents_curl($url)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

    $data = curl_exec($ch);

    curl_close($ch);

    return $data;
}

function navigate_install_load_language()
{
	$lang = array();

	if(!empty($_REQUEST['lang']))
	{
		$_SESSION['navigate_install_lang'] = substr($_REQUEST['lang'], 0, 2);	
		$_SESSION['navigate_install_locale'] = substr($_REQUEST['lang'], 3, 5);	
	}

	if($_SESSION['navigate_install_lang']!='en-en_US' && !empty($_SESSION['navigate_install_lang']))
	{
        $translation_url = 'http://tools.navigatecms.com/installer/translation/'.$_SESSION['navigate_install_lang'];

		// retrieve language strings from server	
		$lang_resource = @fopen($translation_url, "rb");
		$lang = @stream_get_contents($lang_resource);
		@fclose($lang_resource);

        // try an alternate way for language retrieving
        if(empty($lang))
            $lang = @file_get_contents($translation_url);

        // last try
        if(empty($lang))
            $lang = navigate_file_get_contents_curl($translation_url);

		$lang = (array)@json_decode($lang);
	}
	
	if(empty($lang['navigate_installer']))
	{	
		$_SESSION['navigate_install_lang'] = 'en';
		$_SESSION['navigate_install_locale'] = 'en_US';
		
		$lang = array(
			'install'				    =>	'Install',
			'navigate_installer'		=>	'Navigate Installer',
			'requirements'			  	=>	'Requirements',
			'server'					=>	'Server',
			'disk_space'	        	=>	'Disk space',
			'found'						=>	'found',
			'not_found'					=>	'NOT FOUND!',
			'enabled'					=>	'enabled',
			'disabled'					=>	'disabled',
			'must_be_disabled'			=>	'(must be disabled)',
			'proceed_step_2'			=>	'Proceed to step 2: Decompress',
			'unexpected_error'			=>	'Unexpected error',
			'configuration'				=>	'Configuration',
			'owner_administrator'		=>	'Owner & Administrator',
			'database'					=>	'Database',
			'app_owner'					=>	'Application owner',
			'app_owner_detail'			=>	'Your name or Company name',
			'app_folder'				=>	'Application folder',
			'admin'						=>	'Administrator',
            'email'						=>	'E-Mail',
			'username'					=>	'Username',
			'password'					=>	'Password',
			'driver'					=>	'Driver',
			'hostname'					=>	'Hostname',
            'port'                      =>  'Port',
            'socket'                    =>  'Socket',
			'proceed_step_4'			=>	'Proceed to step 4: Database import',
			'decompress'				=>	'Decompress',
			'verify_package'			=>	'Verify package.zip',
			'file_extraction'			=>	'File extraction',
			'change_permissions'		=>	'Change file permissions',
			'proceed_step_3'			=>	'Proceed to step 3: Configuration',
			'done'						=>	'done',
			'database_check'			=>	'Database check',
			'database_import'			=>	'Database import',
			'database_imported'			=>	'Database data imported successfully!',
			'account_create'			=>	'Create user account',
			'proceed_step_5'			=>	'Proceed to step 5: Security & Information',
			'retry_step'				=>	'Retry this step',
			'install_completed'			=>	'Install completed!',
			'navigate_installed'		=>	'Navigate has been installed successfully!',
			'goto_navigate' 			=>  'Go to Navigate CMS',
			'missing_package' 			=>  'Missing package.zip file!',
			'invalid_package'			=>  'package.zip is invalid, please download it again.',
			'extraction_failed'			=>  'Zip extraction failed!',
			'folder_not_exists'			=>  'navigate folder does not exist and could not be created.',
			'chmod_failed' 				=>  'Change file permissions failed!',
			'configure_your_server'		=>  'Please configure your web server to let Navigate generate your site.',
			'apache_config' 			=>  'Apache configuration',
			'copy_htaccess' 			=>  'Copy the file navigate/web/.htaccess to your site root (/www/.htaccess)',
			'do_it_for_me' 				=>  'Do it for me!',
			'htaccess_overwritten' 		=>  '(if .htaccess exists it will be overwritten!)',
			'unknown_server' 			=>  'Unknown web server detected',
			'check_navigate_website' 	=>  'Please check Navigate CMS website to help you prepare your server.',
			'thank_you' 				=>  'Thank you for using Navigate CMS. Enjoy!',
			'app_statistics'			=>	'Allow sending usage statistics to www.navigatecms.com',
			'check_updates'				=>	'Automatically check for new updates',
			'database_will_be_emptied'	=>	'Warning: Database tables will be REMOVED before importing',
			'database_create_allowed' 	=>	'You are allowed to create a new database in the server',
			'database_create_not_allowed' =>'You are NOT allowed to create a new database in the server',
			'navigatecms_website_collection'=>	'Don\'t miss the collection of website themes and extensions available at Navigate CMS website!',
			'theme'                     =>  'Theme',
			'theme_default_install'     =>  'Install the default theme',
			'not_selected'              =>  'Not selected'
		);
	}

	setlocale(LC_ALL, $_SESSION['navigate_install_locale']);
	return $lang;
}

//	$DB->disconnect();
?>