<?php
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/comments/comment.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block_group.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/files/file.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/external/misc/zipfile.php');

class theme
{
	public $name;
	public $title;
    public $version;
	public $author;
	public $website;
	public $shop;
	
	public $languages;
	public $styles;
	public $options;
    public $block_groups;
	public $blocks;
	public $templates;
    public $webusers;

    public $sections;
	public $dictionary;
	public $dictionaries;
	
	public function load($name)
	{
		$json = @file_get_contents(NAVIGATE_PATH.'/themes/'.$name.'/'.$name.'.theme');

		if(empty($json))
        {
            return false;
        }
			
		$theme = json_decode($json);

        if(empty($theme))
        {
            return false;
        }

		//var_dump(json_last_error());
		$this->name = $name;
		$this->title = $theme->title;
		$this->version = $theme->version;
		$this->author = $theme->author;
		$this->website = $theme->website;
		$this->shop = value_or_default($theme->shop, false);

        // remove "@" from styles section definition
        $this->styles = json_encode($theme->styles);
        $this->styles = str_replace("@", "", $this->styles);
        $this->styles = json_decode($this->styles);

		$this->languages = $theme->languages;
		$this->options = (array)$theme->options;
		$this->blocks = (array)$theme->blocks;
		$this->block_groups = (array)$theme->block_groups;
		$this->templates = (array)$theme->templates;
		$this->webusers = (array)$theme->webusers;
        $this->content_samples = (array)$theme->content_samples;

		$this->content_samples_parse(null);

		// in 2.0 templates->section "code" was replaced by "id"
		// added some code to keep compatibility with existing themes
		for($t=0; $t < count($this->templates); $t++)
		{
		    if(isset($this->templates[$t]->sections))
            {
                for ($s = 0; $s < count($this->templates[$t]->sections); $s++)
                {
                    if(!is_array($this->templates[$t]->sections))
                    {
                        $this->templates[$t]->sections = (array) $this->templates[$t]->sections;
                    }

                    if(!empty($this->templates[$t]->sections))
                    {
                        if (!isset($this->templates[$t]->sections[$s]->id))
                        {
                            $this->templates[$t]->sections[$s]->id = $this->templates[$t]->sections[$s]->code;
                        }
                    }
                }
            }

			// remove spaces in "uses" attribute value, if declared
            if(isset($this->templates[$t]->uses))
            {
                $this->templates[$t]->uses = str_replace(" ", "", $this->templates[$t]->uses);
            }
		}

		return true;
	}

    public function delete()
    {
        global $user;

        if($user->permission("themes.delete")=="false")
        {
            throw new Exception(t(610, "Sorry, you are not allowed to execute this function."));
        }

        $ok = false;
        if(file_exists(NAVIGATE_PATH.'/themes/'.$this->name))
        {
            core_remove_folder(NAVIGATE_PATH.'/themes/'.$this->name);
            $ok = !file_exists(NAVIGATE_PATH.'/themes/'.$this->name);
        }

        return $ok;
    }
	
	public function templates($type=NULL)
	{		
		$data = array();

        if(!is_array($this->templates))
        {
            $this->templates = array();
        }

		foreach($this->templates as $template)
		{
			$template->id = $template->type;
			$template->title = $this->template_title($template->type);
			$data[] = $template;

            if($type == $template->id)
            {
                return $template;
            }
		}
		
		return $data;
	}
	
	public function template_title($type, $add_theme_name=true)
	{
		$out = $this->t($type);

		if($out==$type)
		{
			$types = theme::types();
			$out = (empty($types[$type])? $type : $types[$type]);
		}
		
		if($add_theme_name)
        {
            $out = $this->title . ' | ' . $out;
        }
		
		return $out;
	}
	
	public function t($code='')
	{
        global $DB;
		global $user;
		global $webuser;
        global $website;
        global $session;

		$out = "";

		if(empty($this->dictionary))
		{
			$theme_languages = (array)$this->languages;
            $file = '';

    		if(!is_array($theme_languages))
				$theme_languages = array();

            // if we are in Navigate CMS, user has the default language
            // if we call this function from the website, the session has the default language
            $current_language = $session['lang'];
            if(empty($current_language) && !empty($webuser))
            {
                $current_language = $webuser->language;
            }

            if(empty($current_language) && !empty($user))
            {
                $current_language = $user->language;
            }

			foreach($theme_languages as $lcode => $lfile)
			{
				if( $lcode==$current_language || empty($file))
                {
                    $file = $lfile;
                }
			}

			$json = @file_get_contents(NAVIGATE_PATH.'/themes/'.$this->name.'/'.$file);
		
			if(!empty($json))		
            {
                $this->dictionary = (array)json_decode($json);
            }

            // maybe we have a custom translation added in navigate / webdictionary ?
            if(!empty($website->id))
            {
                $DB->query('
                  SELECT subtype, lang, text
                    FROM nv_webdictionary
                   WHERE website = :wid
                     AND node_type = "theme"
                     AND lang = :lang
                     AND theme = :theme',
                    'object',
                    array(
                        ':wid' => $website->id,
                        ':lang' => $current_language,
                        ':theme' => $this->name
                    )
                );
                $rs = $DB->result();

                for($r=0; $r < count($rs); $r++)
                {
                    $this->dictionary[$rs[$r]->subtype] = $rs[$r]->text;
                }
            }
		}

		if(is_string($code))
		{
			$out = $code;

	        if(substr($out, 0, 1)=='@')  // get translation from theme dictionary
            {
                $out = substr($out, 1);
            }

	        if(!empty($this->dictionary[$out]))
            {
                $out = $this->dictionary[$out];
            }
		}
		
		return $out;
	}
	
	public function get_translations()
	{		
		if(empty($this->dictionaries))
		{
			$dict = array();
			foreach($this->languages as $lcode => $lfile)
			{
				$jarray = NULL;
				$json = @file_get_contents(NAVIGATE_PATH.'/themes/'.$this->name.'/'.$lfile);
			
				if(!empty($json))		
                {
                    $jarray = (array)json_decode($json);
                }
				
				if(!empty($jarray))
				{
					foreach($jarray as $code => $text)
					{
						$id = count($dict) + 1;
						$id = -$id;
						$dict[] = array(
                            'id'		=>	$id, //.' | '.$this->name . ' | '.$code,
                            'theme'		=>	$this->name,
							'source'    =>  'theme.'.$this->name.'.'.$code,
                            'node_id'	=>	$code,
                            'lang'		=>	$lcode,
                            'text'		=>	$text
                        );
					}
				}		
			}
			
			$this->dictionaries = $dict;
		}
		
		return $this->dictionaries;
	}	

	public static function types()
	{		
		$template_types = array(
            'home'			    =>	t(187, 'Home page'),
            'content'		    =>	t(9, 'Content'),
            'gallery'		    =>	t(210, 'Gallery'),
            'blog'			    =>	t(375, 'Blog'),
            'blog_entry'	    =>	t(376, 'Blog entry'),
            'item'			    =>	t(630, 'Element'),
            'product'		    =>	t(198, 'Product'),
            'product_list'	    =>	t(737, 'Product list'),
            'cart'	            =>	t(736, 'Cart'),
            'checkout'          =>	t(738, 'Checkout'),
            'customer_account'  =>	t(817, 'Customer account'),
            'list'			    =>	t(39, 'List'),
            'contact'	        =>	t(377, 'Contact'),
            'search'		    =>	t(41, 'Search'),
            'newsletter'	    =>	t(249, 'Newsletter'),
            'portfolio'         =>  t(447, 'Portfolio'),
            'portfolio_item'    =>  t(448, 'Portfolio item'),
            'not_found'         =>  t(13, 'Not found')
        );

		return $template_types;
	}
	
	public static function list_available()
	{
		$themes = glob(NAVIGATE_PATH.'/themes/*/*.theme');
        
		for($t=0; $t < count($themes); $t++)
		{
            $theme_json = @json_decode(@file_get_contents($themes[$t]));

            debug_json_error($themes[$t]); // if debug is enabled, show last json error

            $code = substr($themes[$t], strrpos($themes[$t], '/')+1);
            $code = substr($code, 0, strpos($code, '.theme'));

            $themes[$t] = '';

            if(!empty($theme_json))
            {
                $themes[$t] = array(
                    'code'  =>  $code,
                    'title' =>  $theme_json->title,
                    'version' => $theme_json->version
                );
            }
		}

        $themes = array_filter($themes);
        sort($themes);

		return $themes;
	}

    public function block_group_blocks($block_group_id)
    {
        $out = array();
        foreach($this->block_groups as $bg)
        {
            if($bg->id == $block_group_id)
            {
                foreach($bg->blocks as $bgb)
                {
                    if(empty($bgb->type))
                    {
                        $bgb->type = $bgb->id;
                    }
                    $out[$bgb->id] = $bgb;
                }
            }
        }
        return $out;
    }

    // add special samples if the theme is using foundation, bootstrap...
    public function content_samples_parse($ws=null)
    {
        global $website;

	    if(empty($ws) && !empty($website))
        {
            $ws = $website;
        }
	    else
        {
            $ws = new website();
        }

        $content_samples = array();

        $grid_samples = array(
            '6,6',
            '4,4,4',
            '3,3,3,3',
            '9,3', '3,9',
            '8,4', '4,8',
            '7,5', '5,7',
            '6,3,3', '3,6,3', '3,3,6'
        );

        $text = "Vis prodesset adolescens adipiscing te, usu mazim perfecto recteque at, assum putant erroribus mea in.\n";
        $text.= "Vel facete imperdiet id, cum an libris luptatum perfecto, vel fabellas inciderint ut.";

        if(!empty($this->content_samples))
        {
            foreach($this->content_samples as $cs)
            {
                switch($cs->file)
                {
                    case 'foundation_grid':
                    case 'bootstrap_grid':
                    case 'grid':
                        $stylesheets = $ws->content_stylesheets('array', "content", false, $this);

                        $html_pre = '<html><head>';

                        if(!empty($stylesheets) && is_array($stylesheets))
                        {
                            foreach($stylesheets as $ss)
                            {
                                $html_pre.= '<link rel="stylesheet" type="text/css" href="'.$ss.'" />';
                            }
                        }

                        $html_pre.= '</head><body><div id="navigate-theme-content-sample" style=" width: 99%; ">';

                        foreach($grid_samples as $gs)
                        {
                            $cols = explode(',', $gs);

                            $name = "Grid &nbsp; [ ";

                            $html = $html_pre.'<div class="row">';
                            foreach($cols as $col)
                            {
                                $name .= $col.str_pad("", $col, "-");
                                $scol = $col * 2;
                                // set the small column to the closest step: 6 or 12
                                if($scol >= 8) $scol = 12;
                                if($scol <= 7) $scol = 6;

                                $html .= '<div class="col-md-'.$col.' medium-'.$col.' col-xs-'.$scol.' small-'.$scol.' columns">'.$text.'</div>';
                            }
                            $name .= " ]";
                            $html .= '</div>'; // close row
                            $html .= '<div><p>+</p></div>'; // add extra space under the row
                            $html .= '</div>'; // close copy enabled content
                            $html .= '</body></html>';

                            $content_samples[] = json_decode(json_encode(array('title' => $name, 'content' => $html)));
                        }
                        break;

                    case 'skeleton_grid':
                        $stylesheets = explode(",", $ws->content_stylesheets());
                        $translate = array(
                            1   => "one",
                            2   => "two",
                            3   => "three",
                            4   => "four",
                            5   => "fix",
                            6   => "six",
                            7   => "seven",
                            8   => "eight",
                            9   => "nine",
                            10   => "ten",
                            11   => "eleven",
                            12   => "twelve"
                        );

                        $html_pre = '<html><head>';
                        foreach($stylesheets as $ss)
                        {
                            $html_pre.= '<link rel="stylesheet" type="text/css" href="'.$ss.'" />';
                        }
                        $html_pre.= '</head><body><div id="navigate-theme-content-sample" style=" width: 99%; ">';

                        foreach($grid_samples as $gs)
                        {
                            $cols = explode(',', $gs);

                            $name = "Grid &nbsp; [ ";

                            $html = $html_pre.'<div class="row">';
                            foreach($cols as $col)
                            {
                                $name .= $col.str_pad("", $col, "-");
                                $scol = $col * 2;
                                // set the small column to the closest step: 6 or 12
                                if($scol >= 8) $scol = 12;
                                if($scol <= 7) $scol = 6;

                                $html .= '<div class="'.$translate[$col].' columns">'.$text.'</div>';
                            }
                            $name .= " ]";
                            $html .= '</div>'; // close row
                            $html .= '<div><p>+</p></div>'; // add extra space under the row
                            $html .= '</div>'; // close copy enabled content
                            $html .= '</body></html>';

                            $content_samples[] = json_decode(json_encode(array('title' => $name, 'content' => $html)));
                        }
                        break;

                    default:
                        $content_samples[] = $cs;
                }
            }

            $this->content_samples = $content_samples;
        }
    }

    public static function check_upload($file_upload, $theme_name)
    {
        // check mime
        if(!in_array($file_upload['type'], array('application/zip', 'application/x-zip-compressed')))
        {
            return false;
        }

        // check file extension
        if(pathinfo($file_upload['name'], PATHINFO_EXTENSION) != 'zip')
        {
            return false;
        }

        // extract the zip in a temporary folder
        $zip = new ZipArchive;
        if($zip->open($file_upload['tmp_name']) !== true)
        {
            return false;
        }

        $tempdir = NAVIGATE_PRIVATE . '/tmp/' . uniqid('theme-check-');

        mkdir($tempdir);
        $zip->extractTo($tempdir);
        $zip->close();

        // theme definition exists?
        if(!file_exists($tempdir . '/' . $theme_name . '.theme'))
        {
            core_remove_folder($tempdir);
            return false;
        }

        // it's a valid json?
        $theme_def = file_get_contents($tempdir . '/' . $theme_name . '.theme');
        $theme_def = json_decode($theme_def);

        if(json_last_error() != JSON_ERROR_NONE)
        {
            core_remove_folder($tempdir);
            return false;
        }

        // check every php file included
        $files = core_recursive_file_search($tempdir,  '/.*\/*.(php|phtml)/');

        $prohibited_functions = array(
            'eval(',
            'system(',
            'exec(',
            'shell_exec(',
            'popen(',
            'proc_open(',
            'passthru(',
            '`' // https://www.php.net/manual/en/language.operators.execution.php
        );

        foreach($files as $file)
        {
            if(!file_exists($file))
            {
                continue;
            }
            $file_content = file_get_contents($file);
            // remove all spaces
            $file_content = str_replace(array(' ', "\t", "\r", "\n"), '', $file_content);

            foreach($prohibited_functions as $pf)
            {
                if(stripos($file_content, $pf) !== false)
                {
                    core_remove_folder($tempdir);
                    return false;
                }
            }
        }

        // check for "dangerous" files
        $htaccess = core_recursive_file_search($tempdir,  '/.htaccess/');
        $phpini = core_recursive_file_search($tempdir,  '/php.ini/');
        if(!empty($htaccess) || !empty($phpini))
        {
            core_remove_folder($tempdir);
            return false;
        }

        core_remove_folder($tempdir);

        return true;
    }

    public function import_sample($ws=null)
    {
        global $DB;
        global $website;
        global $theme;
	    global $events;

        if(is_null($ws))
            $ws = $website;

        if(!file_exists(NAVIGATE_PATH.'/themes/'.$this->name.'/'.$this->name.'_sample.zip'))
        {
            throw new Exception(t(56, 'Unexpected error'));
        }

        $ptf = NAVIGATE_PRIVATE.'/tmp/'.$this->name.'_sample';
        core_remove_folder($ptf);

        // decompress the zip file
        $extracted = false;
        $zip = new ZipArchive;
        if($zip->open(NAVIGATE_PATH.'/themes/'.$this->name.'/'.$this->name.'_sample.zip') === TRUE)
        {
            @mkdir($ptf, 0777, true);
            $extracted = $zip->extractTo($ptf);
            if(!$extracted)
            {
                throw new Exception(t(56, 'Unexpected error'));
            }
            $zip->close();
        }

        // website languages (add website included languages)
        if(file_exists($ptf.'/languages.var_export'))
        {
            eval('$wlangs = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/languages.var_export')).';');
        }
        else
        {
            $wlangs = unserialize(file_get_contents($ptf.'/languages.serialized'));
        }

        if(!is_array($wlangs))  $wlangs = array();

        foreach($wlangs as $lcode => $loptions)
        {
            if(!is_array($ws->languages) || !in_array($lcode, array_keys($ws->languages)))
            {
                $ws->languages[$lcode] = $loptions;
            }
        }

        // theme options
        if(file_exists($ptf.'/theme_options.var_export'))
        {
            eval('$toptions = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/theme_options.var_export')).';');
        }
        else
        {
            $toptions = unserialize(file_get_contents($ptf.'/theme_options.serialized'));
        }

        $ws->theme_options = $toptions;

        $ws->save();


        // folders (if available)
        $theme_files_parent = file::create_folder($this->name, "folder/generic", 0, $ws->id);

        $folders = array();
        if(file_exists($ptf.'/folders.var_export')) // available since v2.1.2
        {
            eval('$folders_or = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/folders.var_export')).';');
        }

        if(!empty($folders_or))
        {
            // assume folders are defined in order (first the parents, then their children)
            foreach($folders_or as $f)
            {
                // error protection
                if(empty($f->id))
                {
                    continue;
                }

                $folders[$f->id] = new file();
                $folders[$f->id]->load_from_resultset(array($f));
                $folders[$f->id]->id = 0;
                $folders[$f->id]->website = $ws->id;

                if(isset($folders[$f->parent]))
                {
                    $folders[$f->id]->parent = $folders[$f->parent]->id;
                }
                else
                {
                    $folders[$f->id]->parent = $theme_files_parent;
                }

                $folders[$f->id]->insert();
            }
        }

        
        // files
        $files = array();
        if(file_exists($ptf.'/files.var_export'))
        {
            eval('$files_or = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/files.var_export')).';');
        }
        else
        {
            $files_or = unserialize(file_get_contents($ptf.'/files.serialized'));
        }

        foreach($files_or as $f)
        {
            // error protection
            if(empty($f->id))
            {
                continue;
            }

            $files[$f->id] = new file();
            $files[$f->id]->load_from_resultset(array($f));
            $files[$f->id]->id = 0;
            $files[$f->id]->website = $ws->id;

            if(isset($folders[$f->parent]))
            {
                $files[$f->id]->parent = $folders[$f->parent]->id;
            }
            else
            {
                $files[$f->id]->parent = $theme_files_parent;
            }

            $files[$f->id]->insert();

            // finally copy the sample file
            @copy($ptf.'/files/'.$f->id, NAVIGATE_PRIVATE.'/'.$ws->id.'/files/'.$files[$f->id]->id);
        }

        // structure
        $structure = array();
        if(file_exists($ptf.'/structure.var_export'))
        {
            eval('$structure_or = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/structure.var_export')).';');
        }
        else
        {
            $structure_or = unserialize(file_get_contents($ptf.'/structure.serialized'));
        }

        // hide existing structure entries
        $DB->execute('
            UPDATE nv_structure
               SET permission = 2, visible = 0
             WHERE website = '.$ws->id
        );

        // we need to insert the old categories in order, in other words, the parents before its children
        // so to make things easy, we loop until we have no more categories to insert
        // this could lead to an infinite loop, so we have to add a simple protection
        $structure_categories_or = $structure_or;
        $changes = true;
        while(!empty($structure_categories_or) && $changes)
        {
            $changes = false;
            foreach($structure_categories_or as $si => $category)
            {
                if(empty($category))
                {
                    continue;
                }

                $old_category_id = $category->id;
                $category->id = 0;
                $category->website = $ws->id;

                if($category->parent > 0 && !isset($structure[$category->parent]))
                {
                    // this structure entry needs a parent category that's not yet inserted
                    // ignore the current entry until the next loop
                    continue;
                }

                // if this category has a parent != root, update the parent id with the new value given
                if($category->parent > 0)
                {
                    $category->parent = $structure[$category->parent]->id;
                }

                $category->insert();
                $changes = true;

                $structure[$old_category_id] = $category;
                unset($structure_categories_or[$si]);
            }

            $structure_categories_or = array_filter($structure_categories_or);
        }


        // elements
        $items = array();
        if(file_exists($ptf.'/items.var_export'))
        {
            eval('$items_or = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/items.var_export')).';');
        }
        else
        {
            $items_or = unserialize(file_get_contents($ptf.'/items.serialized'));
        }

        foreach($items_or as $item)
        {
            // error protection
            if(empty($item->id))
            {
                continue;
            }

            $old_item_id = $item->id;
            $item->id = 0;
            $item->website = $ws->id;

            // if this category has a parent != root, update the parent id with the new value given
            if($item->category > 0)
            {
                $item->category = $structure[$item->category]->id;
            }

            $item->dictionary = theme::import_sample_parse_dictionary($item->dictionary, $files, $ws);

            // gallery images (correct FILE ids)
            if(!empty($item->galleries))
            {
                $ngallery = array();
                foreach($item->galleries as $gid => $gallery)
                {
                    foreach($gallery as $fid => $caption)
                    {
                        $ngallery[$files[$fid]->id] = $caption;
                    }

                    $item->galleries[$gid] = $ngallery;
                }
            }

            $item->insert();

            $items[$old_item_id] = $item;
        }


        // blocks
        $blocks = array();
        if(file_exists($ptf.'/blocks.var_export'))
        {
            eval('$blocks_or = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/blocks.var_export')).';');
        }
        else
        {
            $blocks_or = mb_unserialize(file_get_contents($ptf.'/blocks.serialized'));
        }

        if(!is_array($blocks_or))
        {
            $blocks_or = array();
        }

        foreach($blocks_or as $block)
        {
            // error protection
            if(empty($block->id))
            {
                continue;
            }

            $old_block_id = $block->id;
            $block->id = 0;
            $block->website = $ws->id;

            // update structure entries (if used)
            if(!empty($block->categories))
            {
                for($bc=0; $bc < count($block->categories); $bc++)
                {
                    $block->categories[$bc] = $structure[$block->categories[$bc]]->id;
                }
            }

            // update Actions (file/image)
            if(is_array($block->action['action-file']))
            {
                foreach($block->action['action-file'] as $lang => $file)
                {
                    $block->action['action-file'][$lang] = $files[$file]->id;
                }
            }

            if(is_array($block->action['action-image']))
            {
                foreach(@$block->action['action-image'] as $lang => $file)
                {
                    $block->action['action-image'][$lang] = $files[$file]->id;
                }
            }

            // update Triggers (image/rolloverimage/flash/content/html)
            if(is_array($block->trigger['trigger-image']))
            {
                foreach(@$block->trigger['trigger-image'] as $lang => $file)
                {
                    $block->trigger['trigger-image'][$lang] = $files[$file]->id;
                }
            }

            if(is_array($block->trigger['trigger-rollover']))
            {
                foreach(@$block->trigger['trigger-rollover'] as $lang => $file)
                {
                    $block->trigger['trigger-rollover'][$lang] = $files[$file]->id;
                }
            }

            if(is_array($block->trigger['trigger-rollover-active']))
            {
                foreach(@$block->trigger['trigger-rollover-active'] as $lang => $file)
                {
                    $block->trigger['trigger-rollover'][$lang] = $files[$file]->id;
                }
            }

            if(is_array($block->trigger['trigger-flash']))
            {
                foreach(@$block->trigger['trigger-flash'] as $lang => $file)
                {
                    $block->trigger['trigger-flash'][$lang] = $files[$file]->id;
                }
            }

            $block->trigger['trigger-content'] = theme::import_sample_parse_array($block->trigger['trigger-content'], $files, $ws);
            $block->trigger['trigger-html'] = theme::import_sample_parse_array($block->trigger['trigger-html'], $files, $ws);

            $block->dictionary = theme::import_sample_parse_dictionary($block->dictionary, $files, $ws);

            // translate nv:// urls, which may be in:
            //      trigger->[trigger-links][lang][link][code] => link
            //      trigger->[trigger-content][lang] (as html code)
            //      trigger->[trigger-html][lang] (as html code)
            //      action->[action-web][lang]

            if(!empty($block->trigger['trigger-links']))
            {
                foreach($block->trigger['trigger-links'] as $lang => $block_trigger_link)
                {
                    foreach($block_trigger_link['link'] as $btl_code => $btl_link)
                    {
                        $btl_link = theme::import_sample_translate_nv_urls($btl_link, $structure, $items);
                        $block->trigger['trigger-links'][$lang]['link'][$btl_code] = $btl_link;
                    }
                }
            }

            if(!empty($block->trigger['trigger-content']))
            {
                foreach($block->trigger['trigger-content'] as $lang => $block_trigger_content)
                {
                    $block_trigger_content = theme::import_sample_translate_nv_urls($block_trigger_content, $structure, $items);
                    $block->trigger['trigger-content'][$lang] = $block_trigger_content;
                }
            }

            if(!empty($block->trigger['trigger-html']))
            {
                foreach($block->trigger['trigger-html'] as $lang => $block_trigger_content)
                {
                    $block_trigger_content = theme::import_sample_translate_nv_urls($block_trigger_content, $structure, $items);
                    $block->trigger['trigger-html'][$lang] = $block_trigger_content;
                }
            }

            if(!empty($block->action['action-web']))
            {
                foreach($block->action['action-web'] as $lang => $block_action_web)
                {
                    $block_action_web = theme::import_sample_translate_nv_urls($block_action_web, $structure, $items);
                    $block->action['action-web'][$lang] = $block_action_web;
                }
            }

            $block->insert();

            $blocks[$old_block_id] = $block;
        }

        // block_groups
        $block_groups = array();
        if(file_exists($ptf.'/block_groups.var_export'))
        {
            eval('$block_groups_or = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/block_groups.var_export')).';');
        }
        else
        {
            $block_groups_or = unserialize(file_get_contents($ptf.'/block_groups.serialized'));
        }

        foreach($block_groups_or as $block_group)
        {
            // error protection
            if(empty($block_group->id))
            {
                continue;
            }

            $old_block_group_id = $block_group->id;
            $block_group->id = 0;
            $block_group->website = $ws->id;

            // fix block IDs in group
            $new_selection = array();
            for($bi=0; $bi < count($block_group->blocks); $bi++)
            {
                if($block_group->blocks[$bi]['type'] == 'block')
                {
                    $block_group->blocks[$bi]['id'] = $blocks[ $block_group->blocks[$bi]['id'] ]->id;
                }

                $new_selection[] = $block_group->blocks[$bi];
            }
            $block_group->blocks = $new_selection;

            $block_group->insert();

            $block_groups[$old_block_group_id] = $block_group;
        }


        // comments
        if(file_exists($ptf.'/comments.var_export'))
        {
            eval('$comments_or = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/comments.var_export')).';');
        }
        else
        {
            $comments_or = unserialize(file_get_contents($ptf.'/comments.serialized'));
        }

        foreach($comments_or as $comment)
        {
            if(empty($comment->object_id))
            {
                continue;
            }

            $comment->id = 0;
            $comment->website = $ws->id;
            $comment->object_id = $items[$comment->object_id]->id;
            $comment->ip = '';
            $comment->insert();
        }

        // now that categories and elements have been inserted
        // we need to fix:
        //      structure jumps: [jump-branch, jump-item] to its new ID values
        //      items' sections: embedded nv:// urls
        // note: properties will be "translated" later

        // update structure properties
        foreach($structure as $old_id => $entry)
        {
            foreach($entry->dictionary as $elang => $properties)
            {
                if(!empty($properties['action-jump-item']))
                {
                    $entry->dictionary[$elang]['action-jump-item'] = $items[$properties['action-jump-item']]->id;
                }
                else if(!empty($properties['action-jump-branch']))
                {
                    $entry->dictionary[$elang]['action-jump-branch'] = $structure[$properties['action-jump-branch']]->id;
                }

                $entry->save();
            }
        }

        // find & update items' sections nv:// urls
        foreach($items as $old => $element)
        {
            foreach($element->dictionary as $eld_lang => $eld_field)
            {
                foreach($eld_field as $eld_field_key => $eld_field_val)
                {
                    $html = theme::import_sample_translate_nv_urls($eld_field_val, $structure, $items);
                    $items[$old]->dictionary[$eld_lang][$eld_field_key] = $html;
                }
            }
            $items[$old]->save();
        }


        // translate website options; check for forced multilanguage options!
	    $theme_options = array();
        for($toi=0; $toi < count($theme->options); $toi++)
        {
            $to = $theme->options[$toi];
            $to->value = $ws->theme_options->{$to->id};

            switch($to->type)
            {
                case 'file':
                case 'image':
                    // is multi-language forced for this option?
					if(in_array($to->multilanguage, array('true', '1')))
					{
						foreach($to->value as $olang => $oval)
						{
							if(isset($files[$oval]->id))
                            {
                                $to->value[$olang] = $files[$oval]->id;
                            }
						}
					}
					else
					{
						if(isset($files[$to->value]->id))
                        {
                            $to->value = $files[$to->value]->id;
                        }
					}
                    break;

                case 'category':
                    // is multi-language forced for this option?
					if(in_array($to->multilanguage, array('true', '1')))
					{
						foreach($to->value as $olang => $oval)
						{
							if(isset($structure[$oval]->id))
                            {
                                $to->value[$olang] = $structure[$oval]->id;
                            }
						}
					}
					else
					{
						if(isset($structure[$to->value]->id))
                        {
                            $to->value = $structure[$to->value]->id;
                        }
					}
                    break;

                case 'element':
                    // is multi-language forced for this option?
					if(in_array($to->multilanguage, array('true', '1')))
					{
						foreach($to->value as $olang => $oval)
						{
							if(isset($items[$oval]->id))
                            {
                                $to->value[$olang] = $items[$oval]->id;
                            }
						}
					}
					else
					{
						if(isset($items[$to->value]->id))
                        {
                            $to->value = $items[$to->value]->id;
                        }
					}
                    break;

                case 'categories':
                    // is multi-language forced for this option?
					if(in_array($to->multilanguage, array('true', '1')))
					{
						foreach($to->value as $olang => $oval)
						{
							$property_categories_old = explode(',', $oval);
		                    $property_categories_new = array();
		                    foreach($property_categories_old as $oc)
                            {
                                $property_categories_new[] = $structure[$oc]->id;
                            }

		                    $to->value[$olang] = implode(',', $property_categories_new);
						}
					}
					else
					{
	                    $property_categories_old = explode(',', $to->value);
	                    $property_categories_new = array();
	                    foreach($property_categories_old as $oc)
                        {
                            $property_categories_new[] = $structure[$oc]->id;
                        }

						$to->value = implode(',', $property_categories_new);
					}
                    break;

                default:
                    // we don't need to change this type of value
            }

	        // convert theme option definition to website option value
            $theme_options[$to->id] = $to->value;
        }

	    $ws->theme_options = $theme_options;

        $ws->save();

        // properties
        // array ('structure' => ..., 'item' => ..., 'block' => ...)
        if(file_exists($ptf.'/properties.var_export'))
        {
            eval('$properties = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/properties.var_export')).';');
        }
        else
        {
            $properties = unserialize(file_get_contents($ptf.'/properties.serialized'));
        }

        $elements_with_properties = array('structure', 'item', 'block', 'block_group_block');

        foreach($elements_with_properties as $el)
        {
            if($el=='structure')
            {
                $real = $structure;
            }
            else if($el=='item')
            {
                $real = $items;
            }
            else if($el=='block')
            {
                $real = $blocks;
            }
            else if($el=='block_group_block')
            {
                $real = $block_groups;
            }
            else
            {
                continue;
            } // unrecognized element type, ignore

            if(!is_array($properties[$el]))
            {
                continue;
            }

            foreach($properties[$el] as $el_id => $el_properties)
            {
                if(empty($el_properties))
                {
                    continue;
                }

                $item_uid = "";
                if($el=='block_group_block')
                {
                    // find each assigned block UID reference in this block group block
                    foreach($el_properties as $item_uid => $el_properties_bg)
                    {
                        theme::import_sample_properties($ws, $el_properties_bg, $el, $files, $structure, $items, $real, $el_id, $item_uid);
                    }
                }
                else
                {
                    theme::import_sample_properties($ws, $el_properties, $el, $files, $structure, $items, $real, $el_id, $item_uid);
                }
            }
        }

	    // apply final settings from export
        if(file_exists($ptf.'/settings.var_export'))
        {
            eval('$settings_or = '.str_replace("stdClass::__set_state", "(object)", file_get_contents($ptf.'/settings.var_export')).';');
        }
        else
        {
	        // get first structure ID
	        $structure_id = array_keys($structure);
	        $structure_id = $structure_id[0];
	        $settings_or = array('homepage' => $structure_id);
        }

        if(!empty($settings_or['favicon']))
        {
            $ws->favicon = $files[$settings_or['favicon']]->id;
        }

        // what is the homepage?
	    if(is_numeric($settings_or['homepage']))
	    {
		    // homepage as a category ID
		    $ws->homepage = $structure[$settings_or['homepage']]->id;
	    }
	    else
	    {
		    // homepage as a path
		    $ws->homepage = $settings_or['homepage'];
	    }

	    $ws->save();

        core_remove_folder($ptf);
    }

    public static function export_sample($a_categories, $a_items, $a_block_groups, $a_blocks, $a_comments, $folder)
    {
        global $website;
        global $theme;
	    global $DB;

        @set_time_limit(0);

        $categories = array();
        $items = array();
        $blocks = array();
        $block_groups = array();
        $comments = array();
        $properties = array();
        $files = array();
	    $settings = array();

        // structure
        for($c=0; $c < count($a_categories); $c++)
        {
            $tmp = new structure();
            $tmp->load($a_categories[$c]);
            //$properties['structure'][$tmp->id] = property::load_properties_associative('structure', $tmp->template, 'structure', $tmp->id);
            $properties['structure'][$tmp->id] = property::load_properties('structure', $tmp->template, 'structure', $tmp->id);
            $categories[$tmp->id] = $tmp;
            // add files referenced in properties
            if(is_array($properties['structure'][$tmp->id]))
            {
                foreach($properties['structure'][$tmp->id] as $property)
                {
                    if($property->type == 'image' || $property->type == 'file')
                    {
                        $files[] = $property->value;
                    }
                }
            }
        }

        // comments
        for($c=0; $c < count($a_comments); $c++)
        {
            $tmp = new comment();
            $tmp->load($a_comments[$c]);
            $comments[$tmp->id] = $tmp;
        }

        // items
        for($i=0; $i < count($a_items); $i++)
        {
            $tmp = new item();
            $tmp->load($a_items[$i]);

	        $template_id = $tmp->template;

	        if($tmp->association != "free" && $tmp->embedding == 1)
	        {
		        // we have to get the template set in the category of the item
				$template_id = $DB->query_single(
				    'template',
                    'nv_structure',
                    ' id = :category AND website = :wid',
                    null,
                    array(
                        ':wid' => $website->id,
                        ':category' => $tmp->category
                    )
                );
	        }

	        $properties['item'][$tmp->id] = property::load_properties('item', $template_id, 'item', $tmp->id);

	        list($tmp->dictionary, $files) = theme::export_sample_parse_dictionary($tmp->dictionary, $files);

            // add files referenced in properties
            if(is_array($properties['item'][$tmp->id]))
            {
                foreach($properties['item'][$tmp->id] as $property)
                {
                    if($property->type == 'image' || $property->type == 'file')
                    {
                        $files[] = $property->value;
                    }
                }
            }

            // add files referenced in gallery
            if(is_array($tmp->galleries[0]))
            {
                $gallery_images = array_keys($tmp->galleries[0]);
                $files = array_merge($files, $gallery_images);
            }

            $items[$tmp->id] = $tmp;
        }

        // block_groups
        for($i=0; $i < count($a_block_groups); $i++)
        {
            $tmp = new block_group();
            $tmp->load($a_block_groups[$i]);
            $block_groups[$tmp->id] = $tmp;

            if(is_array($tmp->blocks))
            {
                foreach($tmp->blocks as $bgb)
                {
                    if($bgb['type'] == 'block_group_block')
                    {
                        $properties['block_group_block'][$a_block_groups[$i]][$bgb['uid']] = property::load_properties($bgb['id'], $tmp->code, 'block_group_block', $bgb['id'], $bgb['uid']);
                    }
                    else if($bgb['type'] == 'extension')
                    {
                        $properties['block_group_block'][$a_block_groups[$i]][$bgb['uid']] = property::load_properties(NULL, $bgb['id'], "extension_block", NULL, $bgb['uid']);
                    }
                }
            }

            // note: maybe not all blocks in the group have been selected in the "blocks" tab
            // here we only export the block group definition, the block group blocks properties and the extension blocks properties, not adding anything else to export
        }


        // blocks
        for($i=0; $i < count($a_blocks); $i++)
        {
            $tmp = new block();
            $tmp->load($a_blocks[$i]);

            $properties['block'][$tmp->id] = property::load_properties('block', $tmp->type, 'block', $tmp->id);
            list($tmp->dictionary, $files) = theme::export_sample_parse_dictionary($tmp->dictionary, $files);
            list($tmp->trigger['trigger-content'], $files) = theme::export_sample_parse_array($tmp->trigger['trigger-content'], $files);
            list($tmp->trigger['trigger-html'], $files) = theme::export_sample_parse_array($tmp->trigger['trigger-html'], $files);

            if(!empty($tmp->trigger['trigger-image']))
            {
                $files = array_merge($files, array_values($tmp->trigger['trigger-image']));
            }

            if(!empty($tmp->trigger['trigger-rollover']))
            {
                $files = array_merge($files, array_values($tmp->trigger['trigger-rollover']));
            }

            if(!empty($tmp->trigger['trigger-rollover-active']))
            {
                $files = array_merge($files, array_values($tmp->trigger['trigger-rollover-active']));
            }

            if(!empty($tmp->trigger['trigger-flash']))
            {
                $files = array_merge($files, array_values($tmp->trigger['trigger-flash']));
            }

            if(!empty($tmp->action['action-image']))
            {
                $files = array_merge($files, array_values($tmp->action['action-image']));
            }

            if(!empty($tmp->action['action-file']))
            {
                $files = array_merge($files, array_values($tmp->action['action-file']));
            }

            // add files referenced in properties
            if(is_array($properties['block'][$tmp->id]))
            {
                foreach($properties['block'][$tmp->id] as $property)
                {
                    if($property->type == 'image' || $property->type == 'file')
                    {
                        $files[] = $property->value;
                    }
                }
            }

            $blocks[$tmp->id] = $tmp;
        }

        // folders
        // save references and get their files list
        $folders = array();
        $folders_to_check = array();
        if(!empty($folder))
        {
            array_push($folders_to_check, $folder);
            while(!empty($folders_to_check))
            {
                $f = array_shift($folders_to_check);
                $f = file::filesOnPath($f);
                foreach($f as $file)
                {
                    if($file->type == 'folder')
                    {
                        array_push($folders_to_check, $file->id);
                        array_push($folders, $file);
                    }
                    else
                    {
                        $files[] = $file->id;
                    }
                }
            }
        }

        // add files selected as theme_options
        foreach($theme->options as $to)
        {
            if($to->type == 'image' || $to->type == 'file')
            {
                $to_value = $website->theme_options->{$to->id};
                if(is_array($to_value))
                {
                    $files = array_merge($files, $to_value);
                }
                else
                {
                    $files[] = $to_value;
                }
            }
        }

        // include favicon in file list
        if(!empty($website->favicon))
        {
            $files[] = $website->favicon;
        }

        // files
        $files = array_unique($files);
        for($f=0; $f < count($files); $f++)
        {
            $file = new file();
            $file->load($files[$f]);
            $files[$f] = $file;
        }

	    // settings
	    $settings['homepage'] = $website->homepage;
        $settings['favicon'] = $website->favicon;

        $zip = new zipfile();
        $zip->addFile(var_export($website->languages, true), 'languages.var_export');
        $zip->addFile(var_export($website->theme_options, true), 'theme_options.var_export');
        $zip->addFile(var_export($categories, true), 'structure.var_export');
        $zip->addFile(var_export($items, true), 'items.var_export');
        $zip->addFile(var_export($block_groups, true), 'block_groups.var_export');
        $zip->addFile(var_export($blocks, true), 'blocks.var_export');
        $zip->addFile(var_export($comments, true), 'comments.var_export');
        $zip->addFile(var_export($files, true), 'files.var_export');
        $zip->addFile(var_export($folders, true), 'folders.var_export');
        $zip->addFile(var_export($properties, true), 'properties.var_export');
        $zip->addFile(var_export($settings, true), 'settings.var_export');

        foreach($files as $file)
        {
            $zip->addFile(file_get_contents($file->absolute_path()), 'files/'.$file->id);
        }

        $contents = $zip->file();

        header('Content-Disposition: attachment; filename="'.$website->theme.'_sample.zip"');
        header("Content-type: application/octet-stream");
        header('Content-Length: '.strlen($contents));

        echo $contents;
    }

    public static function export_sample_parse_dictionary($dictionary, $files=array())
    {
        if(is_array($dictionary))
        {
            foreach($dictionary as $language => $dictionary_data)
            {
                list($dictionary_data, $files) = theme::export_sample_parse_array($dictionary_data, $files);
                $dictionary[$language] = $dictionary_data;
            }
        }

        return array($dictionary, $files);
    }

    public static function export_sample_parse_array($dictionary, $files=array())
    {
        global $website;

        if(is_array($dictionary))
        {
            foreach($dictionary as $entry => $content)
            {
                // identify all files used
                preg_match_all('!'.NAVIGATE_DOWNLOAD.'!', $content, $matches_nd, PREG_OFFSET_CAPTURE);

                $matches_nd = $matches_nd[0];

                for($m=count($matches_nd); $m >= 0; $m--)
                {
                    if(@empty($matches_nd[$m][1]))
                    {
                        continue;
                    }
                    $offset = $matches_nd[$m][1] + strlen(NAVIGATE_DOWNLOAD);
                    $end = strpos($content, '"', $offset);
                    $file_query = substr($content, $offset + 1, $end - $offset - 1);

                    $file_query = str_replace('&amp;', '&', $file_query);
                    parse_str($file_query, $file_query);
                    $file_id = intval($file_query['id']);
                    $files[] = $file_id;

                    $file_query['id'] = '{{NAVIGATE_FILE}'.$file_id.'}';
                    if(!empty($file_query['wid']))
                    {
                        $file_query['wid'] = '{{NVWEB_WID}}';
                    }

                    $file_query = http_build_query($file_query);

                    $content = substr_replace($content, $file_query, $offset + 1, $end - $offset - 1);
                }

                preg_match_all('!'.NVWEB_OBJECT.'!', $content, $matches_no, PREG_OFFSET_CAPTURE);
                $matches_no = $matches_no[0];

                for($m=count($matches_no); $m >= 0; $m--)
                {
                    if(@empty($matches_no[$m][1])) continue;
                    $offset = $matches_no[$m][1] + strlen(NVWEB_OBJECT);
                    $end = strpos($content, '"', $offset);
                    $file_query = substr($content, $offset + 1, $end - $offset - 1);

                    $file_query = str_replace('&amp;', '&', $file_query);

                    parse_str($file_query, $file_query);
                    $file_id = intval($file_query['id']);
                    $files[] = $file_id;

                    $file_query['id'] = '{{NAVIGATE_FILE}'.$file_id.'}';
                    $file_query = http_build_query($file_query);

                    $content = substr_replace($content, $file_query, $offset + 1, $end - $offset - 1);
                }

                // example route substitutions
                // http://192.168.x.x/navigate/navigate_download.php --> NAVIGATE_DOWNLOAD
                // http://192.168.x.x/ocean [ $website->absolute_path() ] --> WEBSITE_ABSOLUTE_PATH
                // http://192.168.x.x/navigate/themes/ocean [ NAVIGATE_PARENT.NAVIGATE_FOLDER.'/themes/'.$website->theme ] --> THEME_ABSOLUTE_PATH

                $content = str_replace(NAVIGATE_DOWNLOAD, 'url://{{NAVIGATE_DOWNLOAD}}', $content);
                $content = str_replace($website->absolute_path(), 'url://{{WEBSITE_ABSOLUTE_PATH}}', $content);
                $content = str_replace(NAVIGATE_PARENT.NAVIGATE_FOLDER.'/themes/'.$website->theme, 'url://{{THEME_ABSOLUTE_PATH}}', $content);

                $dictionary[$entry] = $content;
            }
        }

        return array($dictionary, $files);
    }

    public static function import_sample_parse_dictionary($dictionary, $files=array(), $ws=null)
    {
        if(is_array($dictionary))
        {
            foreach($dictionary as $language => $foo)
            {
                $dictionary[$language] = theme::import_sample_parse_array($dictionary[$language], $files, $ws);
            }
        }

        return $dictionary;
    }

    public static function import_sample_parse_array($dictionary, $files=array(), $ws=null)
    {
        global $website;

	    if(empty($ws))
        {
            $ws = $website;
        }

        if(!is_array($dictionary))
        {
            return $dictionary;
        }

        foreach($dictionary as $entry => $content)
        {
            // replace file IDs with real ones

            // example: %7B%7BNAVIGATE_FILE%7D117%7D  --> {{NAVIGATE_FILE}117}

            preg_match_all('!%7B%7BNAVIGATE_FILE%7D!', $content, $matches, PREG_OFFSET_CAPTURE);

            $matches = $matches[0];

            for($m=count($matches); $m >= 0; $m--)
            {
                if(@empty($matches[$m])) continue;

                $offset = $matches[$m][1] + strlen('%7B%7BNAVIGATE_FILE%7D#');
                $end = strpos($content, '%7D', $offset);
                $file_id = substr($content, $offset - 1, $end - $offset + 1);
                $content = substr_replace($content, $files[$file_id]->id, $matches[$m][1], strlen('%7B%7BNAVIGATE_FILE%7D'.$file_id.'%7D'));
            }

            $content = str_replace('%7B%7BNVWEB_WID%7D%7D', $ws->id, $content);
            $content = str_replace('url://{{NAVIGATE_DOWNLOAD}}', NAVIGATE_DOWNLOAD, $content);
            $content = str_replace('url://{{WEBSITE_ABSOLUTE_PATH}}', $ws->absolute_path(), $content);
            $content = str_replace('url://{{THEME_ABSOLUTE_PATH}}', NAVIGATE_PARENT.NAVIGATE_FOLDER.'/themes/'.$ws->theme, $content);

            $dictionary[$entry] = $content;
        }

        return $dictionary;
    }

    public static function import_sample_properties($ws, $el_properties, $el, $files, $structure, $items, $real, $el_id, $item_uid)
    {
        global $DB;

        $el_properties_associative = array();

        foreach($el_properties as $foo => $property)
        {
            if(!empty($property) && is_array($property))
            {
                $property = $property[0];
            }

            if(empty($property->value))
            {
                continue;
            }

            // get the original ID for files and categories
            // "translate" those IDs for the ones assigned on the new website
            // for example:
            // (old website) file id: 35    =>  (new website) file id: 3

            // ...and also translate nv:// urls in (text) properties values
            switch($property->type)
            {
                case 'file':
                case 'image':
                    if(in_array($property->multilanguage, array('true', '1')))
                    {
                        foreach($property->value as $plang => $pval)
                        {
                            if(isset($files[$pval]->id))
                            {
                                $property->value[$plang] = $files[$pval]->id;
                            }
                        }
                    }
                    else
                    {
                        if(isset($files[$property->value]->id))
                        {
                            $property->value = $files[$property->value]->id;
                        }
                    }
                    break;

                case 'category':
                    if(in_array($property->multilanguage, array('true', '1')))
                    {
                        foreach($property->value as $plang => $pval)
                        {
                            if(isset($structure[$pval]->id))
                            {
                                $property->value[$plang] = $structure[$pval]->id;
                            }
                        }
                    }
                    else
                    {
                        if(isset($structure[$property->value]->id))
                        {
                            $property->value = $structure[$property->value]->id;
                        }
                    }
                    break;

                case 'categories':
                    if(in_array($property->multilanguage, array('true', '1')))
                    {
                        foreach($property->value as $plang => $pval)
                        {
                            $property_categories_old = explode(',', $pval);
                            $property_categories_new = array();
                            foreach($property_categories_old as $oc)
                            {
                                $property_categories_new[] = $structure[$oc]->id;
                            }
                            $property->value[$plang] = implode(',', $property_categories_new);
                        }
                    }
                    else
                    {
                        $property_categories_old = explode(',', $property->value);
                        $property_categories_new = array();

                        foreach($property_categories_old as $oc)
                        {
                            $property_categories_new[] = $structure[$oc]->id;
                        }
                        $property->value = implode(',', $property_categories_new);
                    }
                    break;

                case 'element':
                    // is multi-language forced for this property?
                    if(in_array($property->multilanguage, array('true', '1')))
                    {
                        foreach($property->value as $olang => $oval)
                        {
                            if(isset($items[$oval]->id))
                            {
                                $property->value[$olang] = $items[$oval]->id;
                            }
                        }
                    }
                    else
                    {
                        if(isset($items[$property->value]->id))
                        {
                            $property->value = $items[$property->value]->id;
                        }
                    }
                    break;

                default:
                    if(is_array($property->value))
                    {
                        // multilanguage property
                        foreach($property->value as $lang => $pvalue)
                        {
                            $property->value[$lang] = theme::import_sample_translate_nv_urls($pvalue, $structure, $items);
                        }
                    }
                    else if(!is_string($property->value))  // ignore numeric values
                    {
                        $property->value = theme::import_sample_translate_nv_urls($property->value, $structure, $items);
                    }
            }

            $el_properties_associative[$property->id] = $property->value;
        }

        if(!empty($el_properties_associative))
        {
            if($el=='block_group_block')
            {
                $template = $real[$el_id]->code;
            }
            else if($el=='block')
            {
                $template = $real[$el_id]->type;
            }
            else
            {
                $template = $real[$el_id]->template;

                if(empty($template) && $el == 'item' && $real[$el_id]->embedding == 1)
                {
                    // we have to get the template set in the category of the item
                    $template = $DB->query_single(
                        'template',
                        'nv_structure',
                        ' id = '.intval($real[$el_id]->category).' AND 
								  website = '.intval($ws->id)
                    );
                }
            }

            property::save_properties_from_array($el, $real[$el_id]->id, $template, $el_properties_associative, $ws, $item_uid);
        }
    }

    public static function import_sample_translate_nv_urls($html, $structure, $items)
    {
        preg_match_all("/nv:\/\/(element|elements|structure|category)\/([0-9])+/", $html, $matches);

        if(!empty($matches) && !empty($matches[0]))
        {
            $matches = $matches[0];
            foreach($matches as $match)
            {
                $parts = explode('/', $match);
                $url = "";
                switch($parts[2])
                {
                    case 'element':
                    case 'item':
                    case 'elements':
                        $url = 'nv://element/' . $items[$parts[3]]->id;
                        break;

                    case 'structure':
                    case 'category':
                        $url = 'nv://structure/' . $structure[$parts[3]]->id;
                        break;

                    default:
                        // ignore this url, leave as is
                }

                if(!empty($url))
                {
                    $html = str_replace($match, $url, $html);
                }
            }
        }

        return $html;
    }

    public static function latest_available()
    {
        $list = theme::list_available();
        $post = array();

        if(!is_array($list))
        {
            return false;
        }

        foreach($list as $theme)
        {
            $post[$theme['code']] = $theme['version'];
        }

        $latest_update = core_curl_post(
            'http://update.navigatecms.com/themes',
            array(
                'themes' => json_encode($post)
            )
        );

        if(empty($latest_update))
        {
            return false;
        }

        $latest_update = json_decode($latest_update, true);

        return $latest_update;
    }
}

?>