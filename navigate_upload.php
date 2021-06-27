<?php
@ini_set('max_execution_time','3600');
@ini_set('max_input_time','3600');

header("Content-Type: application/json");

if(empty($_REQUEST['session_id']))
{
    die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to retrieve session_id."}, "id" : "id"}');
}

session_id($_REQUEST['session_id']);

require_once('cfg/globals.php');
require_once('cfg/common.php');
require_once(NAVIGATE_PATH.'/lib/packages/files/file.class.php');

/* global variables */
global $DB;
global $user;
global $config;
global $layout;
global $website;

// create database connection
$DB = new database();
if(!$DB->connect())
{
    die(APP_NAME.' # ERROR<br /> '.$DB->get_last_error());
}

// session checking
if(empty($_SESSION['APP_USER#'.APP_UNIQUE]))
{
	echo '{"jsonrpc": "2.0", "error": {"code": 100, "message": "No user logged in."}, "id": "id"}';
	core_terminate();
}
else if(!naviforms::check_csrf_token('_nv_csrf_token', false))
{
    echo '{"jsonrpc": "2.0", "error": {"code": 100, "message": "Security error."}, "id": "id"}';
    core_terminate();
}
else
{
	$user = new user();
	$user->load($_SESSION['APP_USER#'.APP_UNIQUE]);
}

// load the working website
$website = new Website();
if(!empty($_SESSION['website_active']))
{
    $website->load($_SESSION['website_active']);
}
else	
{
    $website->load();
} // load the first available

// force loading user permissions before disconnecting from the database
$user->permission("");

// save session
session_write_close();
// disconnect from database (if needed, we will reconnect later)
$DB->disconnect();

if($user->permission("files.upload")=="true")
{
    // HTTP headers for no cache etc
    /*
    header('Content-type: text/plain; charset=UTF-8');
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    */

    // Settings
    $targetDir = NAVIGATE_PRIVATE.'/'.$website->id.'/files';
    $maxFileAge = 24 * 60 * 60; // temporary file age in seconds (1 day)

    // no maximum uploading/execution time
    @set_time_limit(0);

    // filedrop drag'n'drop engine
    switch($_REQUEST['engine'])
    {
        case 'dropzone':
            $tmpfilename = tempnam($targetDir, "upload-") . '.tmp';
            $tmpfilename = basename($tmpfilename);
            $tmpfilename = strtolower($tmpfilename);

            if(count($_FILES) > 0)
            {
                if(!file_exists($_FILES['upload']['tmp_name']))
                {
                    die('{"jsonrpc": "2.0", "error": {"code": 100, "message": "Uploaded file missing."}, "id" : "id"}');
                }

                $original_filename = mb_convert_encoding($_FILES['upload']['name'], 'UTF-8', value_or_default($_SERVER['HTTP_ACCEPT_CHARSET'], 'UTF-8'));

                if(move_uploaded_file($_FILES['upload']['tmp_name'], $targetDir.'/'.$tmpfilename))
                {
                    echo json_encode(
                        array(
                            "filename" => $original_filename,
                            'temporary' => $tmpfilename,
                            'size' => filesize($targetDir.'/'.$tmpfilename)
                        )
                    );
                }

                navigate_upload_remove_temporary($targetDir, $maxFileAge);
                core_terminate();
            }
            else if(isset($_GET['up']))
            {
                if(isset($_GET['base64']))
                {
                    $content = base64_decode(file_get_contents('php://input'));
                }
                else
                {
                    $content = file_get_contents('php://input');
                }

                $headers = getallheaders();
                $headers = array_change_key_case($headers, CASE_UPPER);

                if(file_put_contents($targetDir.'/'.$tmpfilename, $content))
                {
                    echo json_encode(
                        array(
                            "filename" => $headers['UP-FILENAME'],
                            'temporary' => $tmpfilename,
                            'size' => filesize($targetDir.'/'.$tmpfilename)
                        )
                    );
                }

                navigate_upload_remove_temporary($targetDir, $maxFileAge);
                core_terminate();
            }
            break;

        case 'photopea':
            $file_id = intval($_REQUEST['id']);
            if(!empty($file_id) && file_exists($targetDir.'/'.$file_id))
            {
                if(!empty($_POST['p']))
                {
                    // download the file even if the user loads a different page
                    @ignore_user_abort(true);

                    $p = json_decode( $_POST["p"] );	// parse JSON
                    $image = $p->versions[0]->data; // always PNG
                    unset($p); // free memory
                    $image = base64_decode($image);

                    file_put_contents($targetDir.'/'.$file_id.'.photopea', $image);
                    unset($image);

                    $filesize = filesize($targetDir.'/'.$file_id.'.photopea');

                    if(file_exists($targetDir.'/'.$file_id.'.photopea') && $filesize > 128)
                    {
                        unlink($targetDir.'/'.$file_id);
                        rename($targetDir.'/'.$file_id.'.photopea', $targetDir.'/'.$file_id);

                        header("HTTP/1.1 200 OK");
                        header('Access-Control-Allow-Origin: *');
                        echo 'Image saved! ('.core_bytes($filesize).')'; // output content
                        header('Connection: close');

                        // update file info and remove old thumbnails
                        $DB = new database();
                        $DB->connect();
                        $file = new file();
                        $file->load($file_id);
                        $file->refresh();
                    }
                    else
                    {
                        @unlink($targetDir.'/'.$_REQUEST['id'].'.photopea'); // too small, ignore image

                        header("HTTP/1.1 200 OK");
                        header('Access-Control-Allow-Origin: *');
                        echo 'ERROR!'; // output content
                        header('Connection: close');
                    }
                }
            }
            core_terminate();
            break;

        case 'tinymce':
            $DB = new database();
            $DB->connect();

            $file = file::register_upload(
                $_FILES['file']['tmp_name'],
                $_FILES['file']['name'],
                0,
                NULL,
                true
            );

            if(!empty($file))
            {
                echo json_encode(array('location' => file::file_url($file->id)));
            }
            else
            {
                echo json_encode(false);
            }

            core_terminate();
            break;

        default:
            // plUpload engine
            // Get parameters
            $chunk = isset($_REQUEST["chunk"]) ? $_REQUEST["chunk"] : 0;
            $chunks = isset($_REQUEST["chunks"]) ? $_REQUEST["chunks"] : 0;
            $fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

            // clean the fileName for security reasons
            $tmp_name = strtolower(base64_encode($fileName));
            $tmp_name = trim(str_replace(array('..', '\\', '/', '//', ' '), '', $tmp_name));
            $tmp_name = rtrim($tmp_name, ".\t\n\r\0\x0B");
            $tmp_name = ltrim($tmp_name, ".\t\n\r\0\x0B");
            if(PHP_MAJOR_VERSION >= 7)
            {
                $tmp_name = URLify::filter($tmp_name, 128, 'en', true, false, true, '-');
            }

            // remove old temp files
            if(is_dir($targetDir) && ($dir = opendir($targetDir)))
            {
                navigate_upload_remove_temporary($targetDir, $maxFileAge);
            }
            else
            {
                die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
            }

            // Look for the content type header
            if(isset($_SERVER["HTTP_CONTENT_TYPE"]))
            {
                $contentType = $_SERVER["HTTP_CONTENT_TYPE"];
            }

            if(isset($_SERVER["CONTENT_TYPE"]))
            {
                $contentType = $_SERVER["CONTENT_TYPE"];
            }

            if(strpos($contentType, "multipart") !== false)
            {
                if(isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']))
                {
                    // Open temp file
                    $out = fopen($targetDir.DIRECTORY_SEPARATOR.$tmp_name, ($chunk == 0 ? "wb" : "ab"));
                    if($out)
                    {
                        // Read binary input stream and append it to temp file
                        $in = fopen($_FILES['file']['tmp_name'], "rb");

                        if($in)
                        {
                            while ($buff = fread($in, 4096))
                            {
                                fwrite($out, $buff);
                            }
                        }
                        else
                        {
                            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
                        }

                        fclose($out);
                        @unlink($_FILES['file']['tmp_name']);

                        // save meta file info into database (need a new db connection, we do this in the caller script)
                    }
                    else
                    {
                        echo '{"jsonrpc": "2.0", "error": {"code": 102, "message": "Failed to open output stream."}, "id": "id"}';
                        core_terminate();
                    }
                }
                else
                {
                    echo '{"jsonrpc": "2.0", "error": {"code": 103, "message": "Failed to move uploaded file."}, "id": "id"}';
                    core_terminate();
                }
            }
            else
            {
                // Open temp file
                $out = fopen($targetDir . DIRECTORY_SEPARATOR . $tmp_name, $chunk == 0 ? "wb" : "ab");
                if($out)
                {
                    // Read binary input stream and append it to temp file
                    $in = fopen("php://input", "rb");

                    if($in)
                    {
                        while ($buff = fread($in, 4096))
                        {
                            fwrite($out, $buff);
                        }
                    }
                    else
                    {
                        echo '{"jsonrpc": "2.0", "error": {"code": 101, "message": "Failed to open input stream."}, "id": "id"}';
                        core_terminate();
                    }

                    fclose($out);
                }
                else
                {
                    echo '{"jsonrpc": "2.0", "error": {"code": 102, "message": "Failed to open output stream."}, "id": "id"}';
                    core_terminate();
                }
            }

            // Return JSON-RPC response, null => upload ok
            echo '{"jsonrpc" : "2.0", "result" : null, "id" : "id"}';
            core_terminate();
            break;
    }
}


function navigate_upload_remove_temporary($targetDir, $maxFileAge=86400)
{
	if(is_dir($targetDir) && ($dir = opendir($targetDir)))
	{
		while (($file = readdir($dir)) !== false)
		{
			$filePath = $targetDir . DIRECTORY_SEPARATOR . $file;

			// Remove temp files if they are older than the max age
			if (preg_match('/\\.tmp$/', $file) && (filemtime($filePath) < time() - $maxFileAge))
            {
                @unlink($filePath);
            }
		}
		closedir($dir);
	}
}

?>