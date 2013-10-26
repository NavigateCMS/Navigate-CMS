<?php
/**
 * 
 * Navigate CMS common core functions
 * 
 * @copyright Copyright (C) 2010-2013 Naviwebs. All rights reserved.
 * @author Naviwebs (http://www.naviwebs.com/) 
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 License
 * @version 1.7.7 2013-09-07
 *
 */

/**
 * Returns the translated version of an internal CMS string
 *
 * Given a certain ID, function t returns the associated string
 * in the current active language.
 *
 * @param integer $id The ID of the string on Navigate CMS dictionary
 * @param string $default The default string to be returned if no translation found
 * @param array $replace Array of substitutions; example: "Your name is {your_name}" [ 'your_name' => 'Navigate' ]
 * @return string
 */
function t($id, $default='', $replace=array())
{
	global $lang;

	if(!method_exists($lang, 't'))	
		return $default;
	
	return $lang->t($id, $default, $replace);	
}

/**
 * Protects a string before inserting it into the database
 *
 * @param string $text
 * @return string
 */
function protect($text)
{
	global $DB;
	return $DB->protect($text);	
}

/**
 * Encodes " character to &quot; (HTML char)
 *
 * @param string $text
 * @return string
 */
function pquotes($text)
{
	return str_replace('"', '&quot;', $text);	
}

/**
 * Executes a Navigate CMS function taking the 'fid' url parameter
 * fid can be the name of the package (p.e. "dashboard") or its numeric assignment (p.e. "6")
 *
 * @return mixed Navigate CMS package output
 */
function core_run()
{
	global $layout;
	
	$content = "";
	$fid = 'dashboard'; // default function

	if(isset($_REQUEST['fid']))
		$fid = $_REQUEST['fid'];

	$f = core_load_function($fid);

	if(file_exists('lib/packages/'.$f->codename.'/'.$f->codename.'.php'))
	{
		include('lib/packages/'.$f->codename.'/'.$f->codename.'.php');
		$content = run();
	}
	else
		$content = 'function '.$fid.': <strong>'.$f->codename.'</strong> has not been found!';
		
	return $content;	
}

/**
 * Finish Navigate CMS execution sending a flush, writing the session and disconnecting the database
 *
 */
function core_terminate()
{
	global $DB;
	
	flush();
	session_write_close();	
	if($DB)
		$DB->disconnect();
	exit;
}

/**
 * Loads the metadata of a function giving its name or numeric code
 *
 * @param mixed $fid
 * @return object $function
 */
function core_load_function($fid)
{
	global $DB;
    global $menu_layout;
	
    if(is_numeric($fid))
        $where = 'id = '.intval($fid);
    else
        $where = 'codename = '.protect($fid);

	$DB->query('SELECT * 
				  FROM nv_functions
				 WHERE '.$where.'
				   AND enabled = 1');	
				  
	$func = $DB->first();

    if(!$menu_layout->function_is_displayed($func->id))
        $func = false;

    return $func;
}


/**
 * Converts a user formatted date to a unix timestamp
 *
 * @param string $date
 * @return integer
 */
function core_date2ts($date)
{
	global $user;
    global $website;
	
	$ts = 0;
	
	$aDate = explode(" ", $date); // hour is always the last part
    $aDate = array_values(array_filter($aDate));
    list($date, $time) = $aDate;

	if(!empty($time))
        list($hour, $minute) = explode(":", $time);
	else			  
	{
		$hour = 0;
        $minute = 0;
	}

    if(empty($user->timezone))
        $user->timezone = 'UTC';
	
	switch($user->date_format)
	{
		case "Y-m-d H:i":
			list($year, $month, $day) = explode("-", $date);
			break;
			
		case "d-m-Y H:i":
			list($day, $month, $year) = explode("-", $date);
			break;

		case "m-d-Y H:i":
			list($month, $day, $year) = explode("-", $date);		
			break;

		case "Y/m/d H:i":
			list($year, $month, $day) = explode("/", $date);		
			break;

		case "d/m/Y H:i":
			list($day, $month, $year) = explode("/", $date);		
			break;

		case "m/d/Y H:i":
			list($month, $day, $year) = explode("/", $date);				
			break;
	}

    // works on PHP 5.2+
    $userTimezone = new DateTimeZone($user->timezone);
    $utcTimezone = new DateTimeZone('UTC');
    $date = new DateTime($year.'-'.$month.'-'.$day.' '.$hour.':'.$minute, $userTimezone);
    $offset = $utcTimezone->getOffset($date);
    $ts = $date->format('U') + $offset;

	return $ts;	
}

/**
 * Converts a UNIX timestamp to a user formatted date
 *
 * @param integer $timestamp
 * @param boolean $time Set true to add the time after the date
 * @return string
 */
function core_ts2date($timestamp, $time=false)
{
	global $user;
	
	$format = $user->date_format;
	
	if(!$time) $format = str_replace('H:i', '', $format);

    if(empty($user->timezone))
        $user->timezone = 'UTC';

	$date = new DateTime();		
	if(version_compare(PHP_VERSION, '5.3.0') < 0)
	{
		$datets = getdate( ( int ) $timestamp );
		$date->setDate( $datets['year'] , $datets['mon'] , $datets['mday'] );
		$date->setTime( $datets['hours'] , $datets['minutes'] , $datets['seconds'] );
	}
	else
	{
		$date->setTimestamp($timestamp);
		$date->setTimezone(new DateTimeZone($user->timezone));
	}
	
	return $date->format($format);
}

/**
 * Returns the current UNIX timestamp (UTC)
 *
 * @return integer
 */
function core_time()
{	
	$ts = new DateTime();
	return $ts->format("U");
}

/**
 * Sends an e-mail using the account details entered in the website settings form
 * Note: if this function is called when the url has the parameter "debug", a log of the process is dumped
 *
 * @param string $subject
 * @param string $body
 * @param mixed $recipients An e-mail address string or an array of recipients [name => address, name => ...]
 * @param array $attachments Files or data to be attached. [0][file => "/path/to/file", name => "name_of_the_file_in_the_email"]... [1][data => "binary data", name => "name_of_the_file_in_the_email"]
 * @return boolean True if the mail has been sent, false otherwise
 */
function navigate_send_email($subject, $body, $recipients=array(), $attachments=array())
{
	global $website;

    $mail = new PHPMailer(true); // the true param means it will throw exceptions on errors, which we need to catch
    $mail->CharSet = 'UTF-8';
    $mail->IsSMTP(); // telling the class to use SMTP

    try
    {
        $mail->Host       = $website->mail_server;
        $mail->SMTPAuth   = true;
        $mail->Port       = $website->mail_port;
        if($website->mail_security=='1')
            $mail->SMTPSecure = "ssl";
        $mail->Username   = $website->mail_user;
        $mail->Password   = $website->mail_password;

        if(APP_DEBUG)
            $mail->SMTPDebug = 1;

        if(empty($recipients))
        {
            // if no recipients given, assign the website contacts
            $recipients = $website->contact_emails;
        }

        if(!is_array($recipients))	// single recipient or several emails (multiline)
        {
            if(strpos($recipients, "\n")!=false)
                $recipients = explode("\n", $recipients);
            else
                $recipients = array($recipients);
        }

        foreach($recipients as $name => $email)
        {
            if(empty($email) && !empty($name))
                $email = $name;
            $mail->AddAddress($email, $name);
        }

        $mail->SetFrom($website->mail_address, $website->name);

        $mail->IsHTML(true);

        $mail->Subject = $subject;
        $mail->MsgHTML($body);
        $mail->AltBody = strip_tags($body);

        if(is_array($attachments))
        {
            for($i=0; $i< count($attachments); $i++)
            {
                if(!empty($attachments[$i]['file']))
                {
                    $mail->AddAttachment($attachments[$i]['file'], $attachments[$i]['name']);
                }
                else
                {
                    $mail->AddStringAttachment($attachments[$i]['data'], $attachments[$i]['name']);
                }
            }
        }

        $mail->Send();

        $ok = true; // no exceptions => mail sent
    }
    catch (phpmailerException $e)
    {
        echo $e->errorMessage(); //Pretty error messages from PHPMailer
        $ok = false;
    }
    catch (Exception $e)
    {
        echo $e->getMessage(); //Boring error messages from anything else!
        $ok = false;
    }

	return $ok;
}

/**
 * Checks if a string is not empty after a trim
 *
 * @param string $text
 * @return boolean
 */
function is_not_empty($text) 
{ 
	$text = trim($text);
	return !empty($text); 
}

/**
 * Cleans a string of: tags, new lines and duplicated spaces
 *
 * @param string $text
 * @return string
 */
function core_string_clean($text="")
{
	$text = strip_tags($text);
	$text = str_replace("\n", " ", $text);
	$text = str_replace("\r", " ", $text);	
	$text = preg_replace('/(\s+)/', " ", $text);
	return $text;	
}

/**
 * Cuts a text string to a certain length; any HTML tag is removed and text is cutted without breaking words
 *
 * @param string $text
 * @param string $maxlen Maximum character length
 * @param string $morechar Append string if the original text is cutted somewhere
 * @return string
 */
function core_string_cut($text, $maxlen, $morechar='&hellip;')
{
	$text = strip_tags($text);
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	$olen = strlen($text);
	
	if($olen < $maxlen) return $text;
	
	$pos = strrpos( substr( $text , 0 , $maxlen), ' ') ;
	$text = substr( $text , 0 , $pos );
	if($olen > $maxlen) $text.= $morechar;
		
	return $text;
}

/**
 * Translate a number of bytes to a human readable format (from Bytes to PetaBytes)
 *
 * @param integer $bytes
 * @return string
 */
function core_bytes($bytes) 
{
    $unim = array("Bytes", "KB", "MB", "GB", "TB", "PB");
    $c = 0;
    while ($bytes >= 1024) 
    {
        $c++;
        $bytes = $bytes / 1024;
    }
    return number_format($bytes, ($c ? 2 : 0),",",".")." ".$unim[$c];
}

/**
 * Executes a simple GET HTTP request using CURL if available, file_get_contents otherwise
 *
 * @param string $url
 * @param integer $timeout
 * @return string Body of the response
 */
function core_http_request($url, $timeout=8) 
{	
	if(function_exists('curl_init'))
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); 
		$data = curl_exec($ch);
		curl_close($ch);
	}
	else
	{
		$data = file_get_contents($url);	
	}
	
	return $data;
}

/**
 * Execute a CURL HTTP POST request with parameters
 * Author: Mahesh Chari
 * Website: http://www.maheshchari.com/simple-curl-function-to-send-data-remote-server/
 *
 * @param string $url
 * @param mixed $postdata Array of parameter => value or POST string
 * @param string $header HTTP header
 * @param mixed $timeout Number of seconds to wait for a HTTP response
 * @return string Body of the response
 */
function core_curl_post($url, $postdata = NULL, $header = NULL, $timeout = 60, $method="post")
{
	$s = curl_init();
	// initialize curl handler 
	
	curl_setopt($s, CURLOPT_URL, $url);
	//set option URL of the location 
	if ($header) 
		curl_setopt($s, CURLOPT_HTTPHEADER, $header);
		
	//set headers if presents
	curl_setopt($s, CURLOPT_TIMEOUT, $timeout);
	//time out of the curl handler  		
	curl_setopt($s, CURLOPT_CONNECTTIMEOUT, $timeout);
	//time out of the curl socket connection closing 
	curl_setopt($s, CURLOPT_MAXREDIRS, 3);
	//set maximum URL redirections to 3 
	curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
	// set option curl to return as string, don't output directly
    // on some configurations: CURLOPT_FOLLOWLOCATION cannot be activated when safe_mode is enabled or an open_basedir is set
	@curl_setopt($s, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($s, CURLOPT_COOKIEJAR, 'cache/cookie.curl.txt');
	curl_setopt($s, CURLOPT_COOKIEFILE, 'cache/cookie.curl.txt');
	//set a cookie text file, make sure it is writable chmod 777 permission to cookie.txt
	
	if(strtolower($method) == 'post')
	{
		curl_setopt($s,CURLOPT_POST, true);
		//set curl option to post method
		curl_setopt($s,CURLOPT_POSTFIELDS, $postdata);	// can be a string or an associative array
		//if post data present send them.
	}
	else if(strtolower($method) == 'delete')
	{
		curl_setopt($s,CURLOPT_CUSTOMREQUEST, 'DELETE');
		//file transfer time delete
	}
	else if(strtolower($method) == 'put')
	{
		curl_setopt($s,CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($s,CURLOPT_POSTFIELDS, $postdata);
		//file transfer to post ,put method and set data
	}
	
	curl_setopt($s,CURLOPT_HEADER, 0);			 
	// curl send header 
	curl_setopt($s,CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1');
	//proxy as Mozilla browser 
	curl_setopt($s, CURLOPT_SSL_VERIFYPEER, false);
	// don't need to SSL verify ,if present it need openSSL PHP extension
	
	$html = curl_exec($s);
	//run handler
	
	$status = curl_getinfo($s, CURLINFO_HTTP_CODE);
	// get the response status
	
	curl_close($s);
	//close handler

    @unlink('cache/cookie.curl.txt');
	
	return $html;
	//return output	
}

/**
 * Retrieves the size of a remote file doing a CURL request
 *
 * @param string $file URL of the file
 * @return integer|boolean Size of the file in bytes or false
 */
function core_filesize_curl($file)
{
    $ch = curl_init($file);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    // on some configurations: CURLOPT_FOLLOWLOCATION cannot be activated when safe_mode is enabled or an open_basedir is set
    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    curl_close($ch);

    if ($data === false)
      return false;

    if (preg_match('/Content-Length: (\d+)/', $data, $matches))
      return (float)$matches[1];
}

/**
 * Retrieves a remote file doing a CURL request
 *
 * @param string $url URL of the file
 * @param string $file Absolute system path where the file will be saved
 * @return string $contents File contents
 */
function core_file_curl($url, $file)
{
    // prepare URL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    // on some configurations: CURLOPT_FOLLOWLOCATION cannot be activated when safe_mode is enabled or an open_basedir is set
    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $header = curl_exec($ch);
    curl_close($ch);

    $header = str_replace(array("\n", "\r"), ' ', $header);
    $redirect = strpos($header, 'Location:');
    if($redirect!==false)
        $url = substr($header, $redirect + strlen('Location:') + 1, strpos($header, " ", $redirect)+1);

    $ch = curl_init($url);
    $fp = fopen($file, 'w');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

/**
 * Removes a folder on the disk and its subfolders
 *
 * @param string $dir Path of the folder to remove
 */
function core_remove_folder($dir) 
{
   if(is_dir($dir)) 
   {
     $objects = scandir($dir);
     foreach ($objects as $object) 
	 {
       if($object != "." && $object != "..") 
	   {
         if(filetype($dir."/".$object) == "dir") 
         	core_remove_folder($dir."/".$object); 
		 else 
		 	unlink($dir."/".$object);
       }
     }
     reset($objects);
     rmdir($dir);
   }
} 

/**
 * Changes the UNIX permissions of a file or folder and its contents
 *
 * @param string $path Absolute path to the file or folder to change
 * @param string $filemode UNIX permissions code, p.e. 0755
 * @return boolean TRUE if no problem found, FALSE otherwise. Note: on Windows systems this function is always FALSE.
 */
function core_chmodr($path, $filemode) 
{
    if (!is_dir($path))
        return chmod($path, $filemode);

    $dh = opendir($path);

    while (($file = readdir($dh)) !== false) 
    {
        if($file != '.' && $file != '..') 
        {
            $fullpath = $path.'/'.$file;
            if(is_link($fullpath))
                return FALSE;
            else if(!is_dir($fullpath) && !chmod($fullpath, $filemode))
                return FALSE;
            else if(!core_chmodr($fullpath, $filemode))
                return FALSE;
        }
    }

    closedir($dh);

    if(chmod($path, $filemode))
        return TRUE;
    else
        return FALSE;
}

/**
 * Decodes a JSON string removing new lines, tabs, spaces...
 *
 * @param string $json
 * @return object
 */
function core_javascript_json($json)
{
	$json = trim(substr($json, strpos($json, '{'), -1));
	$json = str_replace("\n", "", $json);
	$json = str_replace("\r", "", $json);
	$json = str_replace("\t", "", $json);			
	$json = str_replace('"+"', "", $json);
	$json = str_replace('" +"', "", $json);			
	$json = str_replace('"+ "', "", $json);			
	$json = str_replace('" + "', "", $json);			
	
	return json_decode($json);	
}

/**
 * Completely removes the current session, even the created cookie
 *
 */
function core_session_remove()
{
	if(ini_get("session.use_cookies"))
	{
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params["path"], $params["domain"],
			$params["secure"], $params["httponly"]
		);
	}
	session_destroy();	
}

/**
 * Shows the last PHP JSON decoding error.
 * This function needs APP_DEBUG enabled or the url parameter "debug=true".
 * The error is sent to the Firebug FirePHP plugin within Mozilla Firefox.
 *
 * @param string $prepend String to prepend before the error (if exists)
 */
function debug_json_error($prepend='')
{
    $error = '';
    if(!empty($prepend))
        $prepend .= ' - ';

    if(function_exists('json_last_error'))
    {
        switch (json_last_error())
        {
            case JSON_ERROR_NONE:
                $error = '';
                break;

            case JSON_ERROR_DEPTH:
                $error = 'Maximum stack depth exceeded';
                break;

            case JSON_ERROR_STATE_MISMATCH:
                $error = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $error = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $error = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $error = 'Unknown error';
                break;
        }
    }

    if(!empty($error) && (APP_DEBUG || $_GET['debug']=='true'))
        firephp_nv::log($prepend.$error);
}


function navigate_compose_email($data, $style = array('background' => '#E5F1FF', 'title-color' => '#595959', 'content-color' => '#595959'))
{
    $body = array();

    $body[] = '<div style=" background: '.$style['background'].'; width: 600px; border-radius: 6px; margin: 10px auto; padding: 1px 20px 20px 20px;">';

    foreach($data as $section)
    {
        if(!empty($section['title']))
        {
            $body[] = '<div style="margin: 25px 0px 10px 0px;">';
            $body[] = '    <div style="color: '.$style['title-color'].'; font-size: 17px; font-weight: bold; font-family: Verdana;">'.$section['title'].'</div>';
            $body[] = '</div>';
        }

        if(!empty($section['content']))
        {
            $body[] = '<div style=" background: #fff; border-radius: 6px; padding: 10px; margin-top: 5px; line-height: 25px; text-align: justify; ">';
            $body[] = '    <div class="text" style="color: '.$style['content-color'].'; font-size: 16px; font-style: italic; font-family: Verdana;">'.$section['content'].'</div>';
            $body[] = '</div>';
        }

        if(!empty($section['footer']))
        {
            $body[] = '<br /><br />';
            $body[] = '<div style="color: '.$style['title-color'].';">'.$section['footer'].'</div>';
        }
    }

    $body[] = '</div>';

    $body = implode("\n", $body);

    return $body;
}

?>