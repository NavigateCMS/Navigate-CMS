<?php
/**
 *
 * Navigate CMS miscellaneous functions
 *
 * @copyright All rights reserved to each function author.
 * @author Various (PHP Community)
 * @license GPLv2 License
 * @version 1.5
 * @updated 2020-05-30
 * @note if you are the creator of one of this functions and your name is not here send an email to info@navigatecms.com to be properly credited :)
 *
 */

/* THANK YOU ALL */

/**
 * Multi-byte Unserialize
 *
 * UTF-8 will screw up a serialized string, this function tries to fix the string before unserializing
 * (this happens when serialize puts the wrong length for a utf-8 string), for example here:
 * 'a:2:{i:0;s:5:"héllö";i:1;s:5:"wörld";}'
 *
 * @param string UTF-8 or ASCII string to be unserialized
 * @return string
 *
 */

function mb_unserialize($var)
{
    $out = $var;
    if(!is_object($var) && !is_array($var))
    {
        $out = unserialize($var);

        if(empty($out) && !is_array($out) && !is_object($out))
        {
            $out = preg_replace_callback(
                '!s:(\d+):"(.*?)";!s',
                function($matches)
                {
                    return 's:'.strlen($matches[2]).':"'.$matches[2].'";';
                },
                $var
            );

	        $out = unserialize($out);
        }
    }

    return $out;
}


/**
 * Cleans unwanted quotes from the superglobals GET, POST, COOKIE and REQUEST. If PHP has magic_quotes off, no cleaning is done.
 * @author Unknown
 */
function disable_magic_quotes()
{
	if(function_exists("get_magic_quotes_gpc"))
	{
		if(get_magic_quotes_gpc())
		{
			function stripslashes_gpc(&$value)
			{
				$value = stripslashes($value);
			}

			array_walk_recursive($_GET, 'stripslashes_gpc');
			array_walk_recursive($_POST, 'stripslashes_gpc');
			array_walk_recursive($_COOKIE, 'stripslashes_gpc');
			array_walk_recursive($_REQUEST, 'stripslashes_gpc');
		}
	}
}

function core_recursive_file_search($folder, $pattern)
{
    $dir = new RecursiveDirectoryIterator($folder);
    $ite = new RecursiveIteratorIterator($dir);
    $files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);
    $fileList = array();
    foreach($files as $file)
    {
        $fileList = array_merge($fileList, $file);
    }
    return $fileList;
}

/**
 * Return the real IP address of the current visitor
 *
 * @return bool|mixed The real IP of the visitor or FALSE when an exception is found
 */
function core_ip()
{
     $ip = false;
     if(!empty($_SERVER['HTTP_CLIENT_IP']))
     {
          $ip = $_SERVER['HTTP_CLIENT_IP'];
     }
     if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
     {
          $ips = explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
          if($ip)
          {
               array_unshift($ips, $ip);
               $ip = false;
          }
          for($i = 0; $i < count($ips); $i++)
          {
               if(!preg_match("/^(10|172\.16|192\.168)\./i", $ips[$i]))
               {
                    if(version_compare(phpversion(), "5.0.0", ">="))
                    {
                         if(ip2long($ips[$i]) != false)
                         {
                              $ip = $ips[$i];
                              break;
                         }
                    }
                    else
                    {
                         if(ip2long($ips[$i]) != - 1)
                         {
                              $ip = $ips[$i];
                              break;
                         }
                    }
               }
          }
     }
     return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
}  

/**
 * Retrieve all the files from a folder and subfolders (uses php glob internally)
 *
 * rglob("*", GLOB_MARK, NAVIGATE_PATH.'/themes');
 * @param string $pattern Glob pattern to filter results
 * @param int $flags Glob flags
 * @param string $path Absolute path to begin finding files
 * @return array Absolute paths to all files found matching the criteria
 */
function rglob($pattern = '*', $flags = 0, $path = '')
{
    if (!$path && ($dir = dirname($pattern)) != '.')
	{
		if ($dir == '\\' || $dir == '/') $dir = '';
      	return rglob(basename($pattern), $flags, $dir . '/');
	}

    $paths = glob($path . '*', GLOB_ONLYDIR | GLOB_NOSORT);
    $files = glob($path . $pattern, $flags);

    foreach ($paths as $p) $files = array_merge($files, rglob($pattern, $flags, $p . '/'));
    return $files;
}


/**
 * extract_tags() [renamed to nvweb_tags_extract]
 * Extract specific HTML tags and their attributes from a string.
 *
 * You can either specify one tag, an array of tag names, or a regular expression that matches the tag name(s). 
 * If multiple tags are specified you must also set the $selfclosing parameter and it must be the same for 
 * all specified tags (so you can't extract both normal and self-closing tags in one go).
 * 
 * The function returns a numerically indexed array of extracted tags. Each entry is an associative array
 * with these keys :
 * 	tag_name	- the name of the extracted tag, e.g. "a" or "img".
 *	offset		- the numberic offset of the first character of the tag within the HTML source.
 *	contents	- the inner HTML of the tag. This is always empty for self-closing tags.
 *	attributes	- a name -> value array of the tag's attributes, or an empty array if the tag has none.
 *	full_tag	- the entire matched tag, e.g. '<a href="http://example.com">example.com</a>'. This key 
 *		          will only be present if you set $return_the_entire_tag to true.	   
 *
 * @param string $html The HTML code to search for tags.
 * @param string|array $tag The tag(s) to extract.							 
 * @param bool $selfclosing	Whether the tag is self-closing or not. Setting it to null will force the script to try and make an educated guess. 
 * @param bool $return_the_entire_tag Return the entire matched tag in 'full_tag' key of the results array.  
 * @param string $charset The character set of the HTML code. Defaults to UTF-8.
 *
 * @return array An array of extracted tags, or an empty array if no matching tags were found. 
 */
function nvweb_tags_extract( $html, $tag, $selfclosing = null, $return_the_entire_tag = false, $charset = 'UTF-8')
{
 
	if ( is_array($tag) ){
		$tag = implode('|', $tag);
	}
 
	//If the user didn't specify if $tag is a self-closing tag we try to auto-detect it
	//by checking against a list of known self-closing tags.
	$selfclosing_tags = array( 'area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta', 'col', 'param' );
	if ( is_null($selfclosing) ){
		$selfclosing = in_array( $tag, $selfclosing_tags );
	}
 
	//The regexp is different for normal and self-closing tags because I can't figure out 
	//how to make a sufficiently robust unified one.
	if ( $selfclosing ){
		$tag_pattern =
			'@<(?P<tag>'.$tag.')			# <tag
			(?P<attributes>\s[^>]+)?		# attributes, if any
			\s*/?>					        # /> or just >, being lenient here
			@xsi';
	} else {
		$tag_pattern = 
			'@<(?P<tag>'.$tag.')			# <tag
			(?P<attributes>\s[^>]+)?		# attributes, if any
			\s*>					        # >
			(?P<contents>.*?)		        # tag contents
			</(?P=tag)>				        # the closing </tag>
			@xsi';
	}
 
	$attribute_pattern = 
		'@
		(?P<name>[a-zA-Z0-9.\-_]+)							# attribute name
		\s*=\s*
		(
			(?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)	# a quoted value
			|							# or
			(?P<value_unquoted>[^\s"\']+?)(?:\s+|$)			# an unquoted value (terminated by whitespace or EOF) 
		)
		@xsi';
 
	//Find all tags 
	if ( !preg_match_all($tag_pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ){
		//Return an empty array if we didn't find anything
		return array();
	}

	$tags = array();
	foreach ($matches as $match)
	{
		//Parse tag attributes, if any
		$attributes = array();
		if ( !empty($match['attributes'][0]) ){ 
 
			if ( preg_match_all( $attribute_pattern, $match['attributes'][0], $attribute_data, PREG_SET_ORDER ) ){
				//Turn the attribute data into a name->value array
				foreach($attribute_data as $attr){
					if( !empty($attr['value_quoted']) ){
						$value = $attr['value_quoted'];
					} else if( !empty($attr['value_unquoted']) ){
						$value = $attr['value_unquoted'];
					} else {
						$value = '';
					}
 
					//Passing the value through html_entity_decode is handy when you want
					//to extract link URLs or something like that. You might want to remove
					//or modify this call if it doesn't fit your situation.
					$value = html_entity_decode( $value, ENT_QUOTES, $charset );
 
					$attributes[$attr['name']] = $value;
				}
			}
 
		}
 
		$tag = array(
			'tag_name' => $match['tag'][0],
			'offset' => $match[0][1], 
			'contents' => !empty($match['contents'])?$match['contents'][0]:'', //empty for self-closing tags
			'attributes' => $attributes, 
		);
		if ( $return_the_entire_tag ){
			$tag['full_tag'] = $match[0][0]; 			
		}
 
		$tags[] = $tag;
	}
 
	return $tags;
}

/* strpos that takes an array of values to match against a string */
function stripos_array($haystack, $needles=array(), $offset=0)
{
    $chr = array();
    foreach($needles as $needle)
    {
        $res = stripos($haystack, $needle, $offset);
        if ($res !== false) $chr[$needle] = $res;
    }
    if(empty($chr)) return false;
    return min($chr);
}

/**
 * Sort the elements of an associative array by one of its field values
 *
 * The function allows passing more than one sorting criteria, for example:
 * $sorted = array_orderby($data, 'volume', SORT_DESC, 'edition', SORT_ASC);
 *
 * @author: jimpoz@jimpoz.com
 *
 * @param array $data Array to be sorted
 * @param string $field Name of the field to get as base for sorting
 * @param integer $sort_type SORT_ASC or SORT_DESC (sort ascending or descending)
 * @return array
 */
function array_orderby()
{
    $args = func_get_args();
    $data = array_shift($args);
    foreach ($args as $n => $field) 
	{
        if(is_string($field)) 
		{
            $tmp = array();
            foreach($data as $key => $row)
			{
                $tmp[$key] = mb_strtolower($row[$field]);
			}
            $args[$n] = $tmp;
        }
    }
    $args[] = &$data;
    call_user_func_array('array_multisort', $args);
    return array_pop($args);
}


if(!function_exists('gzdecode'))
{
    /**
     * gzdecode is not present in PHP before version 6
     * @author: Aaron G. 07-Aug-2004 01:29
     * @source: http://www.php.net/manual/es/function.gzencode.php
     * @param string $data
     * @return bool|null|string
     */
    function gzdecode($data)
	{
	  $len = strlen($data);
	  if ($len < 18 || strcmp(substr($data,0,2),"\x1f\x8b")) {
		return null;  // Not GZIP format (See RFC 1952)
	  }
	  $method = ord(substr($data,2,1));  // Compression method
	  $flags  = ord(substr($data,3,1));  // Flags
	  if ($flags & 31 != $flags) {
		// Reserved bits are set -- NOT ALLOWED by RFC 1952
		return null;
	  }
	  // NOTE: $mtime may be negative (PHP integer limitations)
	  $mtime = unpack("V", substr($data,4,4));
	  $mtime = $mtime[1];
	  $xfl   = substr($data,8,1);
	  $os    = substr($data,8,1);
	  $headerlen = 10;
	  $extralen  = 0;
	  $extra     = "";
	  if ($flags & 4) {
		// 2-byte length prefixed EXTRA data in header
		if ($len - $headerlen - 2 < 8) {
		  return false;    // Invalid format
		}
		$extralen = unpack("v",substr($data,8,2));
		$extralen = $extralen[1];
		if ($len - $headerlen - 2 - $extralen < 8) {
		  return false;    // Invalid format
		}
		$extra = substr($data,10,$extralen);
		$headerlen += 2 + $extralen;
	  }
	
	  $filenamelen = 0;
	  $filename = "";
	  if ($flags & 8) {
		// C-style string file NAME data in header
		if ($len - $headerlen - 1 < 8) {
		  return false;    // Invalid format
		}
		$filenamelen = strpos(substr($data,8+$extralen),chr(0));
		if ($filenamelen === false || $len - $headerlen - $filenamelen - 1 < 8) {
		  return false;    // Invalid format
		}
		$filename = substr($data,$headerlen,$filenamelen);
		$headerlen += $filenamelen + 1;
	  }
	
	  $commentlen = 0;
	  $comment = "";
	  if ($flags & 16) {
		// C-style string COMMENT data in header
		if ($len - $headerlen - 1 < 8) {
		  return false;    // Invalid format
		}
		$commentlen = strpos(substr($data,8+$extralen+$filenamelen),chr(0));
		if ($commentlen === false || $len - $headerlen - $commentlen - 1 < 8) {
		  return false;    // Invalid header format
		}
		$comment = substr($data,$headerlen,$commentlen);
		$headerlen += $commentlen + 1;
	  }
	
	  $headercrc = "";
	  if ($flags & 1) {
		// 2-bytes (lowest order) of CRC32 on header present
		if ($len - $headerlen - 2 < 8) {
		  return false;    // Invalid format
		}
		$calccrc = crc32(substr($data,0,$headerlen)) & 0xffff;
		$headercrc = unpack("v", substr($data,$headerlen,2));
		$headercrc = $headercrc[1];
		if ($headercrc != $calccrc) {
		  return false;    // Bad header CRC
		}
		$headerlen += 2;
	  }
	
	  // GZIP FOOTER - These be negative due to PHP's limitations
	  $datacrc = unpack("V",substr($data,-8,4));
	  $datacrc = $datacrc[1];
	  $isize = unpack("V",substr($data,-4));
	  $isize = $isize[1];
	
	  // Perform the decompression:
	  $bodylen = $len-$headerlen-8;
	  if ($bodylen < 1) {
		// This should never happen - IMPLEMENTATION BUG!
		return null;
	  }
	  $body = substr($data,$headerlen,$bodylen);
	  $data = "";
	  if ($bodylen > 0) {
		switch ($method) {
		  case 8:
			// Currently the only supported compression method:
			$data = gzinflate($body);
			break;
		  default:
			// Unknown compression method
			return false;
		}
	  } else {
		// I'm not sure if zero-byte body content is allowed.
		// Allow it for now...  Do nothing...
	  }
	
	  // Verifiy decompressed size and CRC32:
	  // NOTE: This may fail with large data sizes depending on how
	  //       PHP's integer limitations affect strlen() since $isize
	  //       may be negative for large sizes.
	  if ($isize != strlen($data) || crc32($data) != $datacrc) {
		// Bad format!  Length or CRC doesn't match!
		return false;
	  }
	  return $data;
	}
}

/**
 * Search the first row of an associative that match a key => value criteria
 *
 * @author: unkwown
 * #source: http://www.php.net/manual/en/function.array-search.php#106107
 *
 * @param array $parents array( 0 => array( 'a' => 3, 'key' => 'value', 'b' => 3), 1 => array( 'a' => 5, 'key' => 'foo') )
 * @param array $searched array('key' => 'value')
 * @return bool|int|string Index of the parent array (in the example, '0')
 */
function array_multidimensional_search($parents, $searched)
{
    if (empty($searched) || empty($parents))
    {
        return false;
    }

    foreach ($parents as $key => $value)
    {
        $exists = true;
        foreach ($searched as $skey => $svalue)
        {
            $exists = ($exists && IsSet($parents[$key][$skey]) && $parents[$key][$skey] == $svalue);
        }
        if($exists)
        {
            return $key;
        }
  }
 
  return false;
} 

/**
 * Calculate the size of a folder and its files
 *
 * @author: Toni Widodo (toni.widodo@clearsyntax.com)
 *
 * @param string $dirname
 * @return int Size of the folder in bytes
 */
function foldersize($dirname)
{
    $folderSize = 0;
    
    // open the directory, if the script cannot open the directory then return folderSize = 0
    $dir_handle = opendir($dirname);
    if (!$dir_handle) return 0;

    // traversal for every entry in the directory
    while ($file = readdir($dir_handle))
    {
        // ignore '.' and '..' directory
        if  ($file  !=  "."  &&  $file  !=  "..")
        {
            // if entry is directory then go recursive !
            if  (is_dir($dirname."/".$file))
            {
                $folderSize += foldersize($dirname.'/'.$file);
            }
            else // if file then accumulate the size
            {
                $folderSize += filesize($dirname."/".$file);
            }
        }
    }
    // chose the directory
    closedir($dir_handle);

    // return $dirname folder size
    return $folderSize ;
}

/**
 * Search for a text in an array, removing the rows which haven't got it
 *
 * @param array $array
 * @param string $text Fragment of text to look for (search is case insensitive)
 * @return array $array The filtered array
 */
function array_filter_quicksearch($array, $text)
{
    $out = array();
    $text = mb_strtolower($text);

    foreach($array as $key => $value)
    {
        $keep = false;

        if(is_array($value))
        {
            $keep = array_filter_quicksearch($value, $text);
        }
        else
        {
            $keep = (mb_strpos(strtolower($value), $text) !== false);
        }

        if($keep)
        {
            $out[$key] = $value;
        }
    }

    $out = array_filter($out);

    return $out;
}

/**
 * Convert a PHP Date format string to its jQuery UI Date picker compatible version
 *
 * @author roblynch
 * @website http://snipplr.com/view/41329/convert-php-date-style-dateformat-to-the-equivalent-jquery-ui-datepicker-string/
 * @param string $dateString
 * @return string String in jQuery UI date picker format
*/
function php_date_to_jquery_ui_datepicker_format($dateString)
{
    $pattern = array(

        //day
        'd',	//day of the month
        'j',	//3 letter name of the day
        'l',	//full name of the day
        'z',	//day of the year

        //month
        'F',	//Month name full
        'M',	//Month name short
        'n',	//numeric month no leading zeros
        'm',	//numeric month leading zeros

        //year
        'Y', //full numeric year
        'y'	//numeric year: 2 digit
    );
    $replace = array(
        'dd','d','DD','o',
        'MM','M','m','mm',
        'yy','y'
    );
    foreach($pattern as &$p)
    {
        $p = '/'.$p.'/';
    }
    return preg_replace($pattern,$replace,$dateString);
}

function hex2rgb($hexColor)
{
	$shorthand = (strlen($hexColor) == 4);

	list($r, $g, $b) = $shorthand? sscanf($hexColor, "#%1s%1s%1s") : sscanf($hexColor, "#%2s%2s%2s");

	return array(
		"r" => hexdec($shorthand? "$r$r" : $r),
		"g" => hexdec($shorthand? "$g$g" : $g),
		"b" => hexdec($shorthand? "$b$b" : $b)
	);
}


/**
 * Determines if a command exists on the current environment
 *
 * source: http://stackoverflow.com/questions/12424787/how-to-check-if-a-shell-command-exists-from-php
 *
 * @param string $command The command to check
 * @return bool True if the command has been found ; otherwise, false.
 */
function command_exists($command)
{
    $whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';

    $process = proc_open(
        "$whereIsCommand $command",
        array(
            0 => array("pipe", "r"), //STDIN
            1 => array("pipe", "w"), //STDOUT
            2 => array("pipe", "w"), //STDERR
        ),
        $pipes
    );
    if ($process !== false)
    {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $stdout != '';
    }

    return false;
}


/**
 * Creates a "slug" from a UTF-8 string
 *
 * source: http://stackoverflow.com/questions/7530238/convert-ascii-and-utf-8-to-non-special-characters-with-one-function
 *
 * @param string $input The string to convert
 * @return string The generated slug
 */
function slug($input)
{
    $string = html_entity_decode($input, ENT_COMPAT, "UTF-8");

    $oldLocale = setlocale(LC_CTYPE, '0');

    setlocale(LC_CTYPE, 'en_US.UTF-8');
    $string = iconv("UTF-8", "ASCII//TRANSLIT", $string);

    setlocale(LC_CTYPE, $oldLocale);

    return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $string));
}

function value_or_default($value, $default="")
{
	if((is_null($value) || $value=="") && !is_numeric($value))
    {
        return $default;
    }
	else
    {
        return $value;
    }
}

/**
 * Returns a alphabetic string for an integer
 *
 * Example: 1 => a, 2 => b ... then aa, ab, ac... aaa, aab, aac...
 *
 * @param string $input The intenger to convert
 * @return string The generated alphabetic string
 */
function number2alphabet($n)
{
    for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
        $r = chr($n%26 + 0x61) . $r;    // upercase --> 0x41
    return $r;
}

/**
 * Remove HTML tags AND its contents except certain ones (or the inverse)
 *
 * source: http://php.net/manual/es/function.strip-tags.php#86964
 * Thanks to Mariusz Tarnaski
 * note: fails removing closing tags in some cases; this function is deprecated and not used anymore in navigate
 *
 * @param string $text source html
 * @param string $tags list of tags to allow or deny
 * @param boolean $invert allow tags listed or remove them
 * @return string
 */
function strip_tags_content($text, $tags = '', $invert = FALSE)
{
    preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
    $tags = array_unique($tags[1]);

    if(is_array($tags) AND count($tags) > 0)
    {
        if($invert == FALSE)
        {
            return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
        }
        else
        {
            return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
        }
    }
    else if($invert == FALSE)
    {
        return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
    }

    return $text;
}



/**
 * Find a tag by ID and append/replace content
 *
 * source: http://stackoverflow.com/a/17661043/1829145
 * Thanks to Rodolfo Buaiz (brasofilo)
 *
 * @param string $oDoc source html (passed by reference!)
 * @param string $s html code to insert
 * @param string $sId id of the tag to find
 * @param string $sHtml
 * @param boolean $bAppend append new code?
 * @param boolean $bInsert replace existing contents by the new source code?
 * @param boolean $bAddToOuter
 * @return boolean
 */

function brasofilo_suSetHtmlElementById( &$oDoc, &$s, $sId, $sHtml, $bAppend = false, $bInsert = false, $bAddToOuter = false )
{
    if( brasofilo_suIsValidString( $s ) && brasofilo_suIsValidString( $sId ) )
    {
        $bCreate = true;
        if( is_object( $oDoc ))
        {
            if( !( $oDoc instanceof DOMDocument ))
            {
	            return false;
            }
            $bCreate = false;
        }

        if( $bCreate )
        {
	        $oDoc = new DOMDocument();
        }

        libxml_use_internal_errors(true);
        $oDoc->loadHTML($s);
        libxml_use_internal_errors(false);
        $oNode = $oDoc->getElementById( $sId );

        if( is_object( $oNode ))
        {
            $bReplaceOuter = ( !$bAppend && !$bInsert );

            $sId = uniqid('NVCMS_SHEBI-');
            $aId = array( "<!-- $sId -->", "<!--$sId-->" );

            if( $bReplaceOuter )
            {
                if( brasofilo_suIsValidString( $sHtml ) )
                {
                    $oNode->parentNode->replaceChild( $oDoc->createComment( $sId ), $oNode );
                    $s = $oDoc->saveHtml();
                    $s = str_replace( $aId, $sHtml, $oDoc->saveHtml());
                }
                else
                {
	                $oNode->parentNode->removeChild( $oNode );
                    $s = $oDoc->saveHtml();
                }
                return true;
            }

            $bReplaceInner = ( $bAppend && $bInsert );
            $sThis = null;

            if( !$bReplaceInner )
            {
                $sThis = $oDoc->saveHTML( $oNode );
                $sThis = ($bInsert? $sHtml : '').($bAddToOuter? $sThis : (substr($sThis,strpos($sThis,'>')+1,-(strlen($oNode->nodeName)+3)))).($bAppend? $sHtml : '');
            }

            if( !$bReplaceInner && $bAddToOuter )
            {
                $oNode->parentNode->replaceChild( $oDoc->createComment( $sId ), $oNode );
                $sId = &$aId;
            }
            else
            {
	            $oNode->nodeValue = $sId;
            }

            $s = str_replace( $sId, $bReplaceInner?$sHtml:$sThis, $oDoc->saveHtml());
            return true;
        }
    }

    return false;
 }

function brasofilo_suIsValidString( &$s, &$iLen = null, $minLen = null, $maxLen = null )
{
	if( !is_string( $s ) || !isset( $s{0} ))
    {
	    return false;
    }

    if( $iLen !== null )
    {
	    $iLen = strlen( $s );
    }

    return (
        ( $minLen===null? true : ( $minLen > 0 && isset( $s{$minLen-1} ) ) ) &&
        $maxLen===null? true : ( $maxLen >= $minLen && !isset( $s{$maxLen} ) )
    );
}

function brasofilo_suAppendHtmlById( &$s, $sId, $sHtml, &$oDoc = null )
{
	return brasofilo_suSetHtmlElementById( $oDoc, $s, $sId, $sHtml, true, false );
}

function brasofilo_suInsertHtmlById( &$s, $sId, $sHtml, &$oDoc = null )
{
	return brasofilo_suSetHtmlElementById( $oDoc, $s, $sId, $sHtml, false, true );
}

function brasofilo_suAddHtmlBeforeById( &$s, $sId, $sHtml, &$oDoc = null )
{
	return brasofilo_suSetHtmlElementById( $oDoc, $s, $sId, $sHtml, false, true, true );
}

function brasofilo_suAddHtmlAfterById( &$s, $sId, $sHtml, &$oDoc = null )
{
	return brasofilo_suSetHtmlElementById( $oDoc, $s, $sId, $sHtml, true, false, true );
}

function brasofilo_suSetHtmlById( &$s, $sId, $sHtml, &$oDoc = null )
{
	return brasofilo_suSetHtmlElementById( $oDoc, $s, $sId, $sHtml, true, true );
}

function brasofilo_suReplaceHtmlElementById( &$s, $sId, $sHtml, &$oDoc = null )
{
	return brasofilo_suSetHtmlElementById( $oDoc, $s, $sId, $sHtml, false, false );
}

function brasofilo_suRemoveHtmlElementById( &$s, $sId, &$oDoc = null )
{
	return brasofilo_suSetHtmlElementById( $oDoc, $s, $sId, null, false, false );
}


function stylesheet_parse($css)
{
    preg_match_all( '/(?ims)([a-z0-9\s\.\:#_\-@,]+)\{([^\}]*)\}/', $css, $arr);
    $result = array();
    foreach ($arr[0] as $i => $x){
        $selector = trim($arr[1][$i]);
        $rules = explode(';', trim($arr[2][$i]));
        $rules_arr = array();
        foreach ($rules as $strRule){
            if (!empty($strRule)){
                $rule = explode(":", $strRule);
                $rules_arr[trim($rule[0])] = trim($rule[1]);
            }
        }

        $selectors = explode(',', trim($selector));
        foreach ($selectors as $strSel){
            $result[$strSel] = $rules_arr;
        }
    }
    return $result;
}

/**
* Returns a string with backslashes before characters that need to be escaped.
* As required by MySQL and suitable for multi-byte character sets
* Characters encoded are NUL (ASCII 0), \n, \r, \, ', ", and ctrl-Z.
*
* @param string $string String to add slashes to
* @return $string with `\` prepended to reserved characters
*
* @author Trevor Herselman
*/
if (function_exists('mb_ereg_replace'))
{
    function mb_escape($string)
    {
        return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x27\x5C]', '\\\0', $string);
    }
}
else
{
    function mb_escape($string)
    {
        return preg_replace('~[\x00\x0A\x0D\x1A\x22\x27\x5C]~u', '\\\$0', $string);
    }
}

// Generates a strong password of N length containing at least one lower case letter,
// one uppercase letter, one digit, and one special character. The remaining characters
// in the password are chosen at random from those four sets.
//
// The available characters in each set are user friendly - there are no ambiguous
// characters such as i, l, 1, o, 0, etc. This, coupled with the $add_dashes option,
// makes it much easier for users to manually type or speak their passwords.
//
// Note: the $add_dashes option will increase the length of the password by
// floor(sqrt(N)) characters.
// created by: tylerhall https://gist.github.com/tylerhall/521810
function generate_password($length = 8, $add_dashes = false, $available_sets = 'luds')
{
    $sets = array();
    if(strpos($available_sets, 'l') !== false)
    {
        $sets[] = 'abcdefghjkmnpqrstuvwxyz';
    }

    if(strpos($available_sets, 'u') !== false)
    {
        $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    }

    if(strpos($available_sets, 'd') !== false)
    {
        $sets[] = '23456789';
    }

    if(strpos($available_sets, 's') !== false)
    {
        $sets[] = '!@#$%&*?';
    }

    $all = '';
    $password = '';
    foreach($sets as $set)
    {
        $password .= $set[array_rand(str_split($set))];
        $all .= $set;
    }
    $all = str_split($all);
    for($i = 0; $i < $length - count($sets); $i++)
    {
        $password .= $all[array_rand($all)];
    }
    $password = str_shuffle($password);
    if(!$add_dashes)
    {
        return $password;
    }
    $dash_len = floor(sqrt($length));
    $dash_str = '';
    while(strlen($password) > $dash_len)
    {
        $dash_str .= substr($password, 0, $dash_len) . '-';
        $password = substr($password, $dash_len);
    }
    $dash_str .= $password;
    return $dash_str;
}

/**
 * Support samesite cookie flag in both php 7.2 (current production) and php >= 7.3 (when we get there)
 * From: https://github.com/GoogleChromeLabs/samesite-examples/blob/master/php.md and https://stackoverflow.com/a/46971326/2308553
 *
 * @param [type] $name
 * @param [type] $value
 * @param [type] $expire
 * @param [type] $path
 * @param [type] $domain
 * @param [type] $secure
 * @param [type] $httponly
 * @return void
 */
function setcookie_samesite($name, $value=NULL, $expire=0, $path='/', $domain='none', $secure=false, $httponly=true, $samesite='Lax')
{
    global $session_cookie_domain;
    if($domain == 'none')
    {
        $domain = $session_cookie_domain;
    }

    if(PHP_VERSION_ID < 70300)
    {
        setcookie($name, $value, $expire, "$path; samesite=".$samesite, $domain, $secure, $httponly);
    }
    else
    {
        setcookie(
            $name,
            $value,
            array(
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => $samesite,
                'secure' => $secure,
                'httponly' => $httponly,
            )
        );
    }
}


?>