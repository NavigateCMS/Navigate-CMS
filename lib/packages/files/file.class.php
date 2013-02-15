<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/external/class.upload/class.upload.php');

// note, all files are saved in the private directory using ID as filename: NAVIGATE_PRIVATE

class file
{
	public $id;
	public $website;
	public $type;
	public $parent; // parent folder
	public $name;
	public $size;
	public $mime;
	public $width;
	public $height;
	public $date_added;
	public $uploaded_by;	
	public $access; // 0 => everyone, 1 => registered and logged in, 2 => not registered or not logged in
    public $groups;
	public $permission;
	public $enabled;
	
	public function load($id)
	{
		global $DB;
		global $website;
		
		if(!is_numeric($id))
		{
			// is it a path?, then create a virtual file object
			$id = urldecode($id);
			$this->id = $id;
			$this->parent = 0;
			$this->name = basename($id);
			$this->size = @filesize($this->absolute_path());
			$dimensions = $this->image_dimensions($this->absolute_path());
			$this->width = $dimensions['width'];
			$this->height = $dimensions['height'];
			$this->date_added = core_time();
			$this->uploaded_by = 'system';
			$this->permission = 0;
			$this->enabled = 1;
            $this->groups = array();
			$this->access = 0;
			$mime = $this->getMime($this->absolute_path());			
			$this->mime = $mime[0];
			$this->type = $mime[1];
		}
		else
		{		
			// we MUST load any valid id without website attached
			if($DB->query('SELECT * 
							 FROM nv_files 
							WHERE id = '.intval($id)))
			{
				$data = $DB->result();
				$this->load_from_resultset($data); // there will be as many entries as languages enabled
			}
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id			= $main->id;
		$this->website		= $main->website;
		$this->type			= $main->type;
		$this->parent		= $main->parent;
		$this->name			= $main->name;
		$this->size			= $main->size;
		$this->mime			= $main->mime;
		$this->width		= $main->width;
		$this->height		= $main->height;				
		$this->date_added	= $main->date_added;
		$this->uploaded_by	= $main->uploaded_by;	
	
		$this->access		= $main->access;			
		$this->permission	= $main->permission;
		$this->enabled		= $main->enabled;

        // to get the array of groups first we remove the "g" character
        $groups = str_replace('g', '', $main->groups);
        $this->groups = explode(',', $groups);
	}	
	
	public function load_from_post()
	{
		global $DB;
		
		// ? ==> should be changed?
	
		//$this->type			= $_REQUEST['type'];	// ?
		//$this->parent		= $_REQUEST['parent'];
		$this->name			= $_REQUEST['name'];
		//$this->size			= $_REQUEST['size'];	// ?
		//$this->mime			= $_REQUEST['mime'];	// ?
		$this->width		= $_REQUEST['width'];
		$this->height		= $_REQUEST['height'];				
		$this->date_added	= core_time();
		//$this->uploaded_by	= $_REQUEST['uploaded_by'];		// ?
	
		$this->access		= intval($_REQUEST['access']);

        $this->groups	    = $_REQUEST['groups'];
        if($this->access < 3)
            $this->groups = array();

		$this->permission	= intval($_REQUEST['permission']);
		$this->enabled		= intval($_REQUEST['enabled']);			
	}
	
	
	public function save()
	{
		if(!empty($this->id))
			return $this->update();
		else
			return $this->insert();			
	}

    /* folder types:
            folder/generic
            folder/images
            folder/audio
            folder/video
            folder/flash
            folder/documents
    */
    public static function create_folder($name, $type="folder/generic", $parent=0)
    {
        global $user;

        $file = new file();
        $file->id = 0;
        $file->mime = $type;
        $file->type = 'folder';
        $file->parent = intval($parent);
        $file->name = $name;
        $file->size = 0;
        $file->width = 0;
        $file->height = 0;
        $file->date_added = core_time();
        $file->uploaded_by = $user->id;
        $file->permission = 0;
        $file->access = 0;
        $file->enabled = 1;

        $file->save();

        return $file->id;
    }
	
	public function delete()
	{
		global $DB;
		global $website;

		if($this->type == 'folder')
		{
			$DB->query('SELECT id 
						  FROM nv_files
						 WHERE parent = '.intval($this->id).'
						   AND website = '.$website->id);
						  
			$all = $DB->result();
						
			for($i=0; $i < count($all); $i++)
			{
				unset($tmp);
				$tmp = new file();
				$tmp->load($all[$i]->id);
				$tmp->delete();	
			}
		}

		// remove all old entries
		if(!empty($this->id))
		{
			$DB->execute('DELETE 
							FROM nv_files
						   WHERE id = '.intval($this->id).'
						     AND website = '.$website->id
						);

			if($DB->get_affected_rows() == 1 && $this->type != 'folder')
				@unlink(NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$this->id);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;

        $groups = '';
        if(!empty($this->groups))
            $groups = 'g'.implode(',g', $this->groups);

        $ok = $DB->execute(' INSERT INTO nv_files
								(   id, website, type, parent, name, size, mime,
								    width, height, date_added, uploaded_by,
								    permission, access, groups, enabled)
								VALUES 
								( 0,
								  '.protect($website->id).',
								  '.protect($this->type).',
								  '.protect($this->parent).',
								  '.protect($this->name).',
								  '.protect($this->size).',
								  '.protect($this->mime).',
								  '.protect($this->width).',
								  '.protect($this->height).',								  								  
								  '.protect($this->date_added).',
								  '.protect($this->uploaded_by).',								  											  
								  '.protect($this->permission).',
								  '.protect($this->access).',
								  '.protect($groups).',
								  '.protect($this->enabled).'						  
								)');
			
		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		return true;
	}
	
	public function update()
	{
		global $DB;
		global $website;

        $groups = '';
        if(!empty($this->groups))
            $groups = 'g'.implode(',g', $this->groups);

        $ok = $DB->execute(' UPDATE nv_files
								SET 
									type		=	'.protect($this->type).',
									parent		=	'.protect($this->parent).',
									name		=	'.protect($this->name).',
									size		=	'.protect($this->size).',
									mime		=	'.protect($this->mime).',
									width		=	'.protect($this->width).',
									height		=	'.protect($this->height).',	
									date_added	=	'.protect($this->date_added).',
									uploaded_by	=	'.protect($this->uploaded_by).',
									access		=	'.protect($this->access).',
									groups      =   '.protect($groups).',
									permission	=	'.protect($this->permission).',
									enabled		=	'.protect($this->enabled).'
							WHERE id = '.$this->id.'
							  AND website = '.$website->id);
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		return true;
	}		
	
	public static function filesOnPath($parent, $wid=NULL)
	{
		global $DB;
		global $website;
		
		if(empty($wid))
			$wid = $website->id;
		
		$files = array();
		
		$DB->query('  SELECT * FROM nv_files
					   WHERE parent = '.intval($parent).'
					     AND website = '.$wid.'
						 AND type = "folder"
						ORDER BY name ASC
					');
					
		$files = $DB->result();		
		
		$DB->query('  SELECT * FROM nv_files
					   WHERE parent = '.intval($parent).'
					     AND website = '.$wid.'
						 AND type != "folder"
						ORDER BY date_added DESC, name ASC
					');
					
		$files = array_merge($files, $DB->result());		
		
		return $files;	
	}	
	
	public static function filesBySearch($text, $wid=NULL)
	{
		global $DB;
		global $website;

		if(empty($wid))
			$wid = $website->id;
		
		$files = array();
		
		$DB->query('  SELECT * FROM nv_files
					   WHERE name LIKE '.protect('%'.$text.'%').'
					     AND website = '.$wid.'
					ORDER BY name ASC');
					
		return $DB->result();		
		
		return $files;	
	}		
	
	public static function filesByMedia($media, $offset=0, $limit=-1, $wid=NULL)
	{
		global $DB;
		global $website;
		
		if(empty($wid))
			$wid = $website->id;		
		
		$files = array();
		
		if($limit < 1)
			$limit = 2147483647;
		
		$DB->query('  SELECT * FROM nv_files
					   WHERE type = '.protect($media).'
					     AND enabled = 1
						 AND website = '.$wid.' 
					ORDER BY date_added DESC, name ASC 
					   LIMIT '.$limit.' 
					  OFFSET '.$offset);		  

		return $DB->result();			
	}
		
	public static function getFullPathTo($parent)
	{
		global $DB;
		
		if($parent > 0)
		{
			$folder = new file();
			$folder->load($parent);
				
			$path = file::getFullPathTo($folder->parent);	
			$path .= '/'.$folder->name;			
		}
		
		return $path;
	}
	
	public static function cacheHeaders($lastModifiedDate, $etag="")
	{		
		// expiry time 5 minutes, then recheck (if no change, 304 Not modified will be issued)
	    header("Expires: ".gmdate("D, d M Y H:i:s", core_time() + 60*5)." GMT");
		header("Pragma: cache");
		
		if(!empty($lastModifiedDate)) 
		{ 
			header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastModifiedDate)." GMT"); 
				
			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
				strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModifiedDate) 
			{
				if (php_sapi_name()=='CGI') 
				{
					header("Status: 304 Not Modified");
					return true;					
				} 
				else 
				{
					header("HTTP/1.0 304 Not Modified");
					return true;					
				}
			} 
			else if(!empty($etag) && ($_SERVER['HTTP_IF_NONE_MATCH'] == $etag))
			{
				header("HTTP/1.0 304 Not Modified");
				return true;
			}
		}
		
		return false;
	}
	
	public static function getMime($filename)
	{
        $mime_types = array(

            'txt' => array('text/plain', 'document'),

            // images
            'png' => array('image/png', 'image'),
            'jpeg' => array('image/jpeg', 'image'),
            'jpg' => array('image/jpeg', 'image'),
            'gif' => array('image/gif', 'image'),
            'tiff' => array('image/tiff', 'image'),
            'svg' => array('image/svg+xml', 'image'),

            // archives
            'zip' => array('application/zip', 'archive'),
            'rar' => array('application/x-rar-compressed', 'archive'),
            'exe' => array('application/x-msdownload', 'file'),

            // audio/video
            'mp3' => array('audio/mpeg', 'audio'),
            'wav' => array('audio/x-wav', 'audio'),			
            'qt' => array('video/quicktime', 'video'),
            'mov' => array('video/quicktime', 'video'),
            'avi' => array('video/x-msvideo', 'video'),		
            'mp4' => array('video/mp4', 'video'),	
            'webm' => array('video/webm', 'video'),
            'wmv' => array('video/x-ms-wmv', 'video'),
            'swf' => array('application/x-shockwave-flash', 'flash'),
            'flv' => array('video/x-flv', 'video'),				

            // adobe
            'pdf' => array('application/pdf', 'document'),
            'psd' => array('image/vnd.adobe.photoshop', 'image'),

            // ms office
            'doc' => array('application/msword', 'document'),
            'rtf' => array('application/rtf', 'document'),
            'xls' => array('application/vnd.ms-excel', 'document'),
            'ppt' => array('application/vnd.ms-powerpoint', 'document')			
        );

		$ext = file::getExtension($filename);
		
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else {
            return array('application/octet-stream', 'file');
        }
	
	}
	
	public function refresh()
	{
		global $website;
		
		$dims = $this->image_dimensions(NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$this->id);
		$this->width = $dims['width'];
		$this->height = $dims['height'];
		$this->size = filesize(NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$this->id);
		
		$this->save();
		
		$thumbs = glob(NAVIGATE_PRIVATE.'/'.$website->id.'/thumbnails/*-'.$this->id);
		if(is_array($thumbs))	
		{
			foreach($thumbs as $t)	
				@unlink($t);		
		}
	}

	public function resize_uploaded_image()
	{
		global $website;

		if($this->type != 'image')
			return;

		if(file::is_animated_gif($this->absolute_path()))
			return;

		if($website->resize_uploaded_images > 0)
		{
			$this->refresh();

			if($this->width > $this->height)
			{
				// resize by width
				$thumbnail_path = file::thumbnail($this, $website->resize_uploaded_images, 0, false);
			}
			else
			{
				// resize by height
				$thumbnail_path = file::thumbnail($this, 0, $website->resize_uploaded_images, false);
			}

            $size = filesize($thumbnail_path);

			// copy created thumbnail (resized image) over the original file
			@copy($thumbnail_path, $this->absolute_path());

			// remove all previous thumbnails (including the temporary resized image)
			$this->refresh();

            $this->size = $size;
            $this->save();
		}
	}

	
	public static function image_dimensions($path)
	{
		$handle = new upload($path);
		$dimensions = array(	'width' => $handle->image_src_x,
								'height' => $handle->image_src_y);
		return $dimensions;
	}

	
	public static function is_animated_gif($path)
	{
		$handle = fopen($path, 'rb');
		$line = fread($handle, filesize($path));
		fclose($handle);
		$frames = 0;
		
		if((substr($line, 0, 6) == "GIF89a") || (substr($line, 0, 6) == "GIF87a")) 
		{
			$frames = explode('21f904', bin2hex($line));
			$frames = sizeof($frames) - 1;
		}
		
		return ($frames > 1);
	}
	
	
	public static function thumbnail($item, $width=0, $height=0, $border=true)
	{	
		$original  = $item->absolute_path();
		$thumbnail = '';

		$item_id = $item->id;
		if(!is_numeric($item_id)) 
			$item_id = md5($item->id);
			
		if($border===true || $border==='true' || $border===1)
			$border = 1;
		else 
			$border = 0;		

		// we have the thumbnail already created for this image?
		if(file_exists(NAVIGATE_PRIVATE.'/'.$item->website.'/thumbnails/'.$width.'x'.$height.'-'.$border.'-'.$item_id))
		{
			// ok, a file exists, but it's older than the image file? (original image file has changed)
			if(filemtime(NAVIGATE_PRIVATE.'/'.$item->website.'/thumbnails/'.$width.'x'.$height.'-'.$border.'-'.$item_id) > filemtime($original))
			{
				// the thumbnail already exists and is up to date	
				$thumbnail = NAVIGATE_PRIVATE.'/'.$item->website.'/thumbnails/'.$width.'x'.$height.'-'.$border.'-'.$item_id;
			}
		}

		if(empty($thumbnail))	// so we have to create a new thumbnail
		{
			$thumbnail = NAVIGATE_PRIVATE.'/'.$item->website.'/thumbnails/'.$width.'x'.$height.'-'.$border.'-'.$item_id;		

			$handle = new upload($original);
			$size = array(	'width' => $handle->image_src_x,
							'height' => $handle->image_src_y);			

			$handle->image_convert = 'png';
			
			// if needed, calculate width or height with aspect ratio
			if(empty($width))
			{
				$width = round(($height / $size['height']) * $size['width']);
				return file::thumbnail($item, $width, $height, $border);
			}
			else if(empty($height))
			{
				$height = round(($width / $size['width']) * $size['height']);
				return file::thumbnail($item, $width, $height, $border);
			}
			
			$handle->image_x = $width;
			$handle->image_y = $height;

			if($size['width'] < $width && $size['height'] < $height)
			{
				// the image size is under the requested width / height? => fill around with transparent color
				$handle->image_default_color = '#FFFFFF';
				$handle->image_resize = true;
				$handle->image_ratio_no_zoom_in = true;
				$borderP= array(	floor( ($height - $size['height']) / 2 ),
									ceil( ($width - $size['width']) / 2 ),
									ceil( ($height - $size['height']) / 2 ),
									floor( ($width - $size['width']) / 2 ));
				$handle->image_border = $borderP;
				$handle->image_border_color = '#FFFFFF';
				$handle->image_border_transparency = 127;
			}
			else
			{			
				// the image size is bigger than the requested width / height, we must resize it			
				$handle->image_default_color = '#FFFFFF';
				$handle->image_resize = true;
				$handle->image_ratio_fill = true;
			}
			
			if($border==0)
			{
				$handle->image_border = false;
				$handle->image_ratio_no_zoom_in = false;
				$handle->image_ratio_fill = true;						
				$handle->image_ratio_crop = true;	
			}
			$handle->process(dirname($thumbnail));
			
			rename($handle->file_dst_pathname, $thumbnail);
		}
		return $thumbnail;
	}
	
	public static function loadTree($id_parent=0)
	{
		global $DB;	
		global $website;
		
		$DB->query('  SELECT * FROM nv_files 
					   WHERE type = "folder"
					     AND parent = '.intval($id_parent).'
						 AND website = '.$website->id.' 
					ORDER BY parent ASC, id DESC');
		
		$result = $DB->result();
		
		return $result;
	}	
	
	public static function hierarchy($id_parent=0)
	{		
		$tree = array();
		
		if($id_parent==-1)
		{
			/*
			$tree[] = array(   'id' => '0',
							   'parent' => -1,
							   'position' => 0,
							   'permission' => 0,
							   'icon' => 0,
							   'metatags' => '',
							   'label' => $website->name,
							   'date_published' => '',
							   'date_unpublish' => '',
							   'dates' => 'x - x',
							   'children' => structure::hierarchy(0)
							);
			*/
			$obj = new structure();
			$obj->id = 0;
			$obj->label = t(18, 'Home');
			$obj->parent = -1;
			$obj->children = file::hierarchy(0);
			
			$tree[] = $obj;
			
		}
		else
		{
			$tree = file::loadTree($id_parent);
			
			for($i=0; $i < count($tree); $i++)
			{
				$children = file::hierarchy($tree[$i]->id);
				
				$tree[$i]->children = $children;
				$tree[$i]->label = $tree[$i]->name;
				if(empty($tree[$i]->label)) 
					$tree[$i]->label = '[ ? ]';
			}	
		}
		
		return $tree;
	}
	
	public static function hierarchyList($hierarchy, $selected)
	{		
		$html = array();
				
		if(!is_array($hierarchy)) $hierarchy = array();
		
		foreach($hierarchy as $node)
		{	
			$li_class = '';
			$post_html = file::hierarchyList($node->children, $selected);
			if(strpos($post_html, 'class="active"')!==false) $li_class = ' class="open" ';
					
			if(empty($html)) $html[] = '<ul>';
			if($node->id == $selected)
				$html[] = '<li '.$li_class.' value="'.$node->id.'"><span class="active">'.$node->label.'</span>';
			else
				$html[] = '<li '.$li_class.' value="'.$node->id.'"><span>'.$node->label.'</span>';

			$html[] = $post_html;
			$html[] = '</li>';
		}
		if(!empty($html)) $html[] = '</ul>';		
		
		return implode("\n", $html);
	}	
	
	public static function getExtension($filename)
	{
		$ext = explode('.',$filename);
		$ext = strtolower(array_pop($ext));
		return $ext;
	}
	
	public function absolute_path()
	{
        global $website;

		if(is_numeric($this->id))
			$path = NAVIGATE_PRIVATE.'/'.$this->website.'/files/'.$this->id;		
		else
        {
            if(file_exists(NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$this->id))
                $path = NAVIGATE_PATH.'/themes/'.$website->theme.'/'.$this->id;
            else if(file_exists(NAVIGATE_PATH.'/'.$this->id))
                $path = NAVIGATE_PATH.'/'.$this->id;
            else
                $path = $this->id;
        }

		return $path;
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_files WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }
}

?>