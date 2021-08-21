<?php
function nvweb_object($ignoreEnabled=false, $ignorePermissions=false, $item=NULL)
{
	global $website;
    global $DB;
	
	session_write_close();
	ob_end_clean();
	
	header('Cache-Control: private');
	header('Pragma: private');

    $type = @$_REQUEST['type'];
	$id = @$_REQUEST['id'];

	if(empty($item) && !empty($id))
	{
        $item = new file();

		if(is_numeric($id))
        {
            $item->load($id);
        }
		else
        {
            // sanitize "id" parameter to avoid XSS problems
            // note: if the "id" parameter is not numeric, then it could be the path of a navigate theme file
            $url = $_REQUEST['id'];

            $url = filter_var($url, FILTER_SANITIZE_URL);

            // disallow use of < > chars in a URL
            $url = str_replace(array('<', '>'), '', $url);

            // prevent directory traversal attacks
            $url = core_remove_directory_traversal($url);

            // additional checking inside
            $item->load($url);
        }
	}
	
	if(empty($type) && !empty($item->type)) 
    {
        $type = $item->type;
    }

    // if the type requested is not a special type, check its access permissions
    if(!in_array($type, array("blank", "transparent", "flag")))
    {
        $enabled = nvweb_object_enabled($item);
        if(!$enabled && !$ignorePermissions)
        {
            $type = 'not_allowed';
        }
    }

    switch($type)
	{
		case 'not_allowed':
			header("HTTP/1.0 405 Method Not Allowed");
			break;

		case 'blank':
		case 'transparent':
			$path = NAVIGATE_PATH.'/img/transparent.gif';
			
			header('Content-Disposition: attachment; filename="transparent.gif"');
			header('Content-Type: image/gif');
			header('Content-Disposition: inline; filename="transparent.gif"');			
			header("Content-Transfer-Encoding: binary\n");
			
			$etag = base64_encode($path.filemtime($path));
			header('ETag: "'.$etag.'"');

			// check the browser cache and stop downloading again the file
			$cached = file::cacheHeaders(filemtime($path), $etag);			

			if(!$cached)
            {
                readfile($path);
            }
			break;
				
		case 'flag':
			if($_REQUEST['code']=='ca')
            {
                $_REQUEST['code'] = 'catalonia';
            }
				
			header('Content-Disposition: attachment; filename="'.$_REQUEST['code'].'.png"');
			header('Content-Type: image/png');
			header('Content-Disposition: inline; filename="'.$_REQUEST['code'].'.png"');			
			header("Content-Transfer-Encoding: binary\n");

			$path = NAVIGATE_PATH.'/img/icons/flags/'.$_REQUEST['code'].'.png';
            if(!file_exists($path))
            {
                $path = NAVIGATE_PATH.'/img/transparent.gif';
            }
			
			$etag = base64_encode($path.filemtime($path));
			header('ETag: "'.$etag.'"');
			
			// check the browser cache and stop downloading again the file
			$cached = file::cacheHeaders(filemtime($path), $etag);			

			if(!$cached)
            {
                readfile($path);
            }
			break;
	
		case 'image':
		case 'img':
        case 'thumbnail':
            // keep executing even the user or browser cancels the connection (to avoid ending with partial thumbnails)
            @ignore_user_abort(true);

			if(!$item->enabled && !$ignoreEnabled) 
            {
                nvweb_clean_exit();
            }

			$path = $item->absolute_path();

			$etag_add = '';		
		
			// calculate aspect ratio if width or height are given...
			$width = intval(@$_REQUEST['width']) + 0;
			$height = intval(@$_REQUEST['height']) + 0;

		    // check size requested and ignore the empty values (or equal to zero)
		    if(empty($width))
            {
                $width = "";
            }
		    if(empty($height))
            {
                $height = "";
            }

            // get target quality (only for jpeg thumbnails!)
            $quality = @$_REQUEST['quality'];
            if(empty($quality))
            {
                $quality = 95;
            }

			$resizable = true;

			if($item->mime == 'image/gif')
            {
                $resizable = !(file::is_animated_gif($path));
            }

			if($item->mime == 'image/svg+xml')
            {
                $resizable = false;
            }

			if( isset($_GET['force']) ||
                (   (!empty($width) || !empty($height)) &&
                    ($resizable || @$_REQUEST['force_resize']=='true')
                )
            )
			{
			    if($item->mime == 'image/svg+xml')
                {
                    // TODO: in the future, try to apply border and opacity modifiers in the XML
                    //       right now just return the original svg
                }
                else
                {
                    $border = (@$_REQUEST['border'] == 'false' ? false : true);
                    $opacity = value_or_default(@$_REQUEST['opacity'], NULL);
                    $scale_up_force = value_or_default(@$_REQUEST['force_scale'], NULL);

                    $path = file::thumbnail($item, $width, $height, $border, NULL, $quality, $scale_up_force, $opacity);
                    if(empty($path))
                    {
                        die();
                    }

                    $etag_add = '-'.$width.'-'.$height.'-'.$border.'-'.$quality.'-'.$scale_up_force.'-'.$opacity;
                    $item->name = $width . 'x' . $height . '-' . $item->name;
                    $item->size = filesize($path);
                    $item->mime = 'image/png';
                    if(strpos(basename($path), '.jpg') !== false)
                    {
                        $item->mime = 'image/jpeg';
                    }
                }
			}

			$etag = $item->id.'-'.$item->name.'-'.$item->date_added.'-'.filesize($path).'-'.filemtime($path).'-'.$item->permission.$etag_add;
			$etag = base64_encode($etag);
			header('ETag: "'.$etag.'"');
			header('Content-type: '.$item->mime);
			header('Access-Control-Allow-Origin: *'); // allow external access (f.e. Photopea, Pixlr, etc.)
			header("Content-Length: ". $item->size);
            header("Accept-Ranges: bytes");
			if(empty($_REQUEST['disposition']))
            {
                $_REQUEST['disposition'] = 'inline';
            }
			header('Content-Disposition: '.$_REQUEST['disposition'].'; filename="'.$item->name.'"');						
			
			// check the browser cache and stop downloading again the file
			$cached = file::cacheHeaders(filemtime($path), $etag);

			// may return a 404 Error if misconfigured in htaccess
            if(isset($_SERVER['NV_MOD_X_SENDFILE_ENABLED']) || isset($_SERVER['REDIRECT_NV_MOD_X_SENDFILE_ENABLED']))
            {
                // relative path to private files (to avoid exposing absolute path in response headers)
                $path = 'private/'.$website->id.'/files/'.$item->id;
                header("X-Sendfile: ".$path);
            }
            if(!$cached)
            {
                $range = 0;
                $size = filesize($path);

                if(isset($_SERVER['HTTP_RANGE']))
                {
                    list($a, $range) = explode("=", $_SERVER['HTTP_RANGE']);
                    str_replace($range, "-", $range);
                    $size2 = $size - 1;
                    $new_length = $size - $range;
                    header("HTTP/1.1 206 Partial Content");
                    header("Content-Length: $new_length");
                    header("Content-Range: bytes $range$size2/$size");
                }
                else
                {
                    $size2 = $size - 1;
                    header("Content-Range: bytes 0-$size2/$size");
                    header("Content-Length: ".$size);
                }

                $fp = fopen($path, "rb");

                if(is_resource($fp))
                {
                    @fseek($fp, $range);
                    while(!@feof($fp) && (connection_status()==0))
                    {
                        set_time_limit(0);
                        print(@fread($fp, 1024 * 1024)); // 1 MB
                        flush();
                        if(ob_get_length()!==false)
                        {
                            ob_flush();
                        }
                        // wait for 1 second (so 1 MB/s)
                        usleep(1000000);
                    }
                    fclose($fp);
                }
            }
			break;
		
		case 'archive':
		case 'video':
		case 'file':
		default:
			if(!$item->enabled && !$ignoreEnabled)
            {
                nvweb_clean_exit();
            }

			if(is_numeric($item->id))
            {
                $path = NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$item->id;
            }
			else
            {
                // maybe a theme file
			    $file = new file();
			    $file->load($item->id);
			    $path = $file->absolute_path();

			    if(empty($path) || !file_exists($path) )
                {
                    nvweb_clean_exit();
                }
            }

			$etag_add = '';

            clearstatcache();
            $etag = $item->id.'-'.$item->name.'-'.$item->date_added.'-'.filemtime($path).'-'.$item->permission.$etag_add;
			$etag = base64_encode($etag);

			header('ETag: "'.$etag.'"');
            header('Content-type: '.$item->mime);
            header("Accept-Ranges: bytes");

            if(empty($_REQUEST['disposition']))
            {
                $_REQUEST['disposition'] = 'attachment';
            }
            header('Content-Disposition: '.$_REQUEST['disposition'].'; filename="'.$item->name.'"');

            // check the browser cache and stop downloading again the file
            $cached = file::cacheHeaders(filemtime($path), $etag);

            if(isset($_SERVER['NV_MOD_X_SENDFILE_ENABLED']) || isset($_SERVER['REDIRECT_NV_MOD_X_SENDFILE_ENABLED']))
            {
                // relative path to private files (to avoid exposing absolute path in response headers)
                $path = 'private/'.$website->id.'/files/'.$item->id;
                header("X-Sendfile: ".$path);
            }
            else if(!$cached)
            {
                $range = 0;
                $size = filesize($path);

                if(isset($_SERVER['HTTP_RANGE']))
                {
                    list($a, $range) = explode("=", $_SERVER['HTTP_RANGE']);
                    str_replace($range, "-", $range);
                    $size2 = $size - 1;
                    $new_length = $size - $range;
                    header("HTTP/1.1 206 Partial Content");
                    header("Content-Length: $new_length");
                    header("Content-Range: bytes $range$size2/$size");
                }
                else
                {
                    $size2 = $size - 1;
                    header("Content-Range: bytes 0-$size2/$size");
                    header("Content-Length: ".$size);
                }

                $fp = fopen($path, "rb");

                if(is_resource($fp))
                {
                    @fseek($fp, $range);
                    while(!@feof($fp) && (connection_status()==0))
                    {
                        set_time_limit(0);
                        print(@fread($fp, 1024 * 1024)); // 1 MB
                        flush();
                        if(ob_get_length()!==false)
                        {
                            ob_flush();
                        }
                        // wait for 1 second (so 1 MB/s)
                        usleep(1000000);
                    }
                    fclose($fp);
                }
            }
			break;
	}

    nvweb_clean_exit();
}

?>