<?php
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/comments/comment.class.php');
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
	
	public $languages;
	public $styles;
	public $options;
	public $blocks;
	public $templates;
	
	public $dictionary;
	public $dictionaries;
	
	public function load($name)
	{	
		$json = @file_get_contents(NAVIGATE_PATH.'/themes/'.$name.'/'.$name.'.theme');

		if(empty($json))
			return false;
			
		$theme = json_decode($json);

        if(empty($theme))
            return false;

		//var_dump(json_last_error());
		$this->name = $name;
		$this->title = $theme->title;
		$this->version = $theme->version;
		$this->author = $theme->author;
		$this->website = $theme->website;
		
		$this->languages = $theme->languages;
		$this->styles = $theme->styles;
		$this->options = (array)$theme->options;
		$this->blocks = (array)$theme->blocks;
		$this->templates = (array)$theme->templates;
        $this->content_samples = (array)$theme->content_samples;

		return true;
	}

    public function delete()
    {
        $ok = false;
        if(file_exists(NAVIGATE_PATH.'/themes/'.$this->name))
        {
            core_remove_folder(NAVIGATE_PATH.'/themes/'.$this->name);
            $ok = !file_exists(NAVIGATE_PATH.'/themes/'.$this->name);
        }

        return $ok;
    }
	
	public function templates()
	{		
		$data = array();
		
		foreach($this->templates as $template)
		{
			$template->id = $template->type;
			$template->title = $this->template_title($template->type);
			$data[] = $template;
		}
		
		return $data;
	}
	
	public function template_title($type, $add_theme_name = true)
	{		
		$out = $this->t($type);
	
		if($out==$type)
		{
			$types = theme::types();
			$out = (empty($types[$type])? $type : $types[$type]);
		}
		
		if($add_theme_name)
			$out = $this->title . ' | ' . $out;
		
		return $out;
	}
	
	public function t($code='')
	{
        global $DB;
		global $user;
        global $website;
        global $current;

		if(empty($this->dictionary))
		{
			$theme_languages = (array)$this->languages;
            $file = '';

    		if(!is_array($theme_languages))
				$theme_languages = array();

            // if we are in Navigate CMS, user has the default language
            // if we call this function from the website, the session has the default language
            $current_language = (empty($user)? $current['lang'] : $user->language);

			foreach($theme_languages as $lcode => $lfile)
			{
				if( $lcode==$current_language || empty($file))
					$file = $lfile;	
			}

			$json = @file_get_contents(NAVIGATE_PATH.'/themes/'.$this->name.'/'.$file);
		
			if(!empty($json))		
				$this->dictionary = (array)json_decode($json);

            // maybe we have a custom translation added in navigate / webdictionary ?
            $DB->query('SELECT subtype, lang, text
                          FROM nv_webdictionary
                         WHERE website = '.$website->id.'
                           AND node_type = "theme"
                           AND lang = '.protect($user->language).'
                           AND theme = '.protect($this->name));
            $rs = $DB->result();

            for($r=0; $r < count($rs); $r++)
                $this->dictionary[$rs[$r]->subtype] = $rs[$r]->text;
		}

		$out = $code;
        if(substr($out, 0, 1)=='@')  // get translation from theme dictionary
            $out = substr($out, 1);

        if(!empty($this->dictionary[$out]))
			$out = $this->dictionary[$out];
		
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
					$jarray = (array)json_decode($json);	
				
				if(!empty($jarray))
				{
					foreach($jarray as $code => $text)
					{
						$id = count($dict) + 1;
						$id = -$id;
						$dict[] = array(
                            'id'		=>	$id, //.' | '.$this->name . ' | '.$code,
                            'theme'		=>	$this->name,
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
            'home'			=>	t(187, 'Home page'),
            'content'		=>	t(9, 'Content'),
            'gallery'		=>	t(210, 'Gallery'),
            'blog'			=>	t(375, 'Blog'),
            'blog_entry'	=>	t(376, 'Blog entry'),
            'item'			=>	t(180, 'Item'),
            'list'			=>	t(39, 'List'),
            'contact'	    =>	t(377, 'Contact'),
            'search'		=>	t(41, 'Search'),
            'newsletter'	=>	t(249, 'Newsletter'),
            'portfolio'     =>  t(447, 'Portfolio'),
            'portfolio_item'=>  t(448, 'Portfolio item')
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
                $themes[$t] = array(
                    'code'  =>  $code,
                    'title' =>  $theme_json->title
                );
		}

        $themes = array_filter($themes);
        sort($themes);

		return $themes;
	}

    public function import_sample()
    {
        global $website;
        global $DB;
        global $theme;

        if(!file_exists(NAVIGATE_PATH.'/themes/'.$this->name.'/'.$this->name.'_sample.zip'))
            throw new Exception(t(56, 'Unexpected error'));

        $ptf = NAVIGATE_PRIVATE.'/tmp/'.$this->name.'_sample';
        core_remove_folder($ptf);

        // decompress the zip file
        $zip = new ZipArchive;
        if($zip->open(NAVIGATE_PATH.'/themes/'.$this->name.'/'.$this->name.'_sample.zip') === TRUE)
        {
            @mkdir($ptf, 0777, true);
            $zip->extractTo($ptf);
            $zip->close();
        }

        // website languages (add website included languages)
        $wlangs = unserialize(file_get_contents($ptf.'/languages.serialized'));
        foreach($wlangs as $lcode => $loptions)
        {
            if(!in_array($lcode, $website->languages))
                $website->languages[$lcode] = $loptions;
        }

        // theme options
        $toptions = unserialize(file_get_contents($ptf.'/theme_options.serialized'));
        $website->theme_options = $toptions;

        $website->save();

        // files
        $files = array();
        $files_or = unserialize(file_get_contents($ptf.'/files.serialized'));

        $theme_files_parent = file::create_folder($this->name);

        foreach($files_or as $f)
        {
            // metadata
            $files[$f->id] = new file();
            $files[$f->id]->load_from_resultset(array($f));
            $files[$f->id]->id = 0;
            $files[$f->id]->website = $website->id;
            $files[$f->id]->parent = $theme_files_parent;
            $files[$f->id]->insert();

            // finally copy the sample file
            @copy($ptf.'/files/'.$f->id, NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$files[$f->id]->id);
        }

        // structure
        $structure = array();
        $structure_or = unserialize(file_get_contents($ptf.'/structure.serialized'));

        // hide existing structure entries
        $DB->execute('UPDATE nv_structure
                         SET permission = 2, visible = 0
                         WHERE website = '.$website->id
        );

        foreach($structure_or as $category)
        {
            if(empty($category))
                continue;

            $old_category_id = $category->id;
            $category->id = 0;
            $category->website = $website->id;
            // if this category has a parent != root, update the parent id with the new value given
            if($category->parent > 0)
                $category->parent = $structure[$category->parent]->id;

            $category->insert();

            $structure[$old_category_id] = $category;
        }


        // items
        $items = array();
        $items_or = unserialize(file_get_contents($ptf.'/items.serialized'));

        foreach($items_or as $item)
        {
            $old_item_id = $item->id;
            $item->id = 0;
            $item->website = $website->id;
            // if this category has a parent != root, update the parent id with the new value given
            if($item->category > 0)
                $item->category = $structure[$item->category]->id;

            $item->dictionary = theme::import_sample_parse_dictionary($item->dictionary, $files);

            $item->insert();

            $items[$old_item_id] = $item;
        }


        // blocks
        $blocks = array();
        $blocks_or = unserialize(file_get_contents($ptf.'/blocks.serialized'));

        foreach($blocks_or as $block)
        {
            $old_block_id = $block->id;
            $block->id = 0;
            $block->website = $website->id;

            // update structure entries (if used)
            if(!empty($block->categories))
            {
                for($bc=0; $bc < count($block->categories); $bc++)
                    $block->categories[$bc] = $structure[$block->categories[$bc]]->id;
            }

            // update Actions (file/image)
            if(is_array($block->action['action-file']))
                foreach($block->action['action-file'] as $lang => $file)
                    $block->action['action-file'][$lang] = $files[$file]->id;

            if(is_array($block->action['action-image']))
                foreach(@$block->action['action-image'] as $lang => $file)
                    $block->action['action-image'][$lang] = $files[$file]->id;

            // update Triggers (image/rolloverimage/flash/content/html)
            if(is_array($block->trigger['trigger-image']))
                foreach(@$block->trigger['trigger-image'] as $lang => $file)
                    $block->trigger['trigger-image'][$lang] = $files[$file]->id;

            if(is_array($block->trigger['trigger-rollover']))
                foreach(@$block->trigger['trigger-rollover'] as $lang => $file)
                    $block->trigger['trigger-rollover'][$lang] = $files[$file]->id;

            if(is_array($block->trigger['trigger-rollover-active']))
                foreach(@$block->trigger['trigger-rollover-active'] as $lang => $file)
                    $block->trigger['trigger-rollover'][$lang] = $files[$file]->id;

            if(is_array($block->trigger['trigger-flash']))
                foreach(@$block->trigger['trigger-flash'] as $lang => $file)
                    $block->trigger['trigger-flash'][$lang] = $files[$file]->id;

            $block->trigger['trigger-content'] = theme::import_sample_parse_array($block->trigger['trigger-content'], $files);
            $block->trigger['trigger-html'] = theme::import_sample_parse_array($block->trigger['trigger-html'], $files);

            $block->dictionary = theme::import_sample_parse_dictionary($block->dictionary, $files);

            $block->insert();

            $blocks[$old_block_id] = $block;
        }


        // comments
        $comments_or = unserialize(file_get_contents($ptf.'/comments.serialized'));

        foreach($comments_or as $comment)
        {
            $comment->id = 0;
            $comment->website = $website->id;
            $comment->item = $items[$comment->item]->id;
            $comment->ip = '';
            $comment->insert();
        }


        // properties
        // array ('structure' => ..., 'item' => ..., 'block' => ...)
        $properties = unserialize(file_get_contents($ptf.'/properties.serialized'));
        $elements_with_properties = array('structure', 'item', 'block');
        foreach($elements_with_properties as $el)
        {
            if($el=='structure')    $real = $structure;
            else if($el=='item')    $real = $items;
            else if($el=='block')   $real = $blocks;

            foreach($properties[$el] as $el_id => $el_properties)
            {
                if(empty($el_properties))
                    continue;

                $el_properties_associative = array();

                foreach($el_properties as $foo => $property)
                {
                    if(empty($property->value))
                        continue;

                    switch($property->type)
                    {
                        case 'file':
                        case 'image':
                            if(isset($files[$property->value]->id))
                                $property->value = $files[$property->value]->id;
                            break;

                        case 'category':
                            if(isset($structure[$property->value]->id))
                                $property->value = $structure[$property->value]->id;
                            break;

                        default:
                    }

                    $el_properties_associative[$property->id] = $property->value;
                }

                if(!empty($el_properties_associative))
                {
                    if($el=='block')
                        $template = $real[$el_id]->type;
                    else
                        $template = $real[$el_id]->template;

                    property::save_properties_from_array($el, $real[$el_id]->id, $template, $el_properties_associative);
                }
            }
        }

        core_remove_folder($ptf);
    }

    public static function export_sample($a_categories, $a_items, $a_blocks, $a_comments, $folder)
    {
        global $website;
        global $theme;

        @set_time_limit(0);

        $categories = array();
        $items = array();
        $blocks = array();
        $comments = array();
        $properties = array();
        $files = array();

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
                    if($property->type == 'image' || $property->type == 'file')
                        $files[] = $property->value;
            }
        }

        for($c=0; $c < count($a_comments); $c++)
        {
            $tmp = new structure();
            $tmp->load($a_comments[$c]);
            $comments[$tmp->id] = $tmp;
        }

        for($i=0; $i < count($a_items); $i++)
        {
            $tmp = new item();
            $tmp->load($a_items[$i]);

            //$properties['item'][$tmp->id] = property::load_properties_associative('item', $tmp->template, 'item', $tmp->id);
            $properties['item'][$tmp->id] = property::load_properties('item', $tmp->template, 'item', $tmp->id);
            list($tmp->dictionary, $files) = theme::export_sample_parse_dictionary($tmp->dictionary, $files);

            // add files referenced in properties
            if(is_array($properties['item'][$tmp->id]))
            {
                foreach($properties['item'][$tmp->id] as $property)
                    if($property->type == 'image' || $property->type == 'file')
                        $files[] = $property->value;
            }

            $items[$tmp->id] = $tmp;
        }

        for($i=0; $i < count($a_blocks); $i++)
        {
            $tmp = new block();
            $tmp->load($a_blocks[$i]);

            $tmp->trigger['trigger-content'] = theme::import_sample_parse_array($tmp->trigger['trigger-content'], $files);
            $tmp->trigger['trigger-html'] = theme::import_sample_parse_array($tmp->trigger['trigger-html'], $files);

            //$properties['block'][$tmp->id] = property::load_properties_associative('block', $tmp->type, 'block', $tmp->id);
            $properties['block'][$tmp->id] = property::load_properties('block', $tmp->type, 'block', $tmp->id);
            list($tmp->dictionary, $files) = theme::export_sample_parse_dictionary($tmp->dictionary, $files);

            // add files referenced in properties
            if(is_array($properties['block'][$tmp->id]))
            {
                foreach($properties['block'][$tmp->id] as $property)
                    if($property->type == 'image' || $property->type == 'file')
                        $files[] = $property->value;
            }

            $blocks[$tmp->id] = $tmp;
        }

        $folders = array();
        if(!empty($folder))
        {
            array_push($folders, $folder);
            while(!empty($folders))
            {
                $f = array_shift($folders);
                $f = file::filesOnPath($f);
                foreach($f as $file)
                {
                    if($file->type == 'folder')
                        array_push($folders, $file->id);
                    else
                        $files[] = $file->id;
                }
            }
        }

        $files = array_unique($files);
        for($f=0; $f < count($files); $f++)
        {
            $file = new file();
            $file->load($files[$f]);
            $files[$f] = $file;
        }

        $zip = new zipfile();
        $zip->addFile(serialize($website->languages), 'languages.serialized');
        $zip->addFile(serialize($theme->options), 'theme_options.serialized');
        $zip->addFile(serialize($categories), 'structure.serialized');
        $zip->addFile(serialize($items), 'items.serialized');
        $zip->addFile(serialize($blocks), 'blocks.serialized');
        $zip->addFile(serialize($comments), 'comments.serialized');
        $zip->addFile(serialize($files), 'files.serialized');
        $zip->addFile(serialize($properties), 'properties.serialized');

        foreach($files as $file)
            $zip->addFile(file_get_contents($file->absolute_path()), 'files/'.$file->id);

        $contents = $zip->file();

        header('Content-Disposition: attachment; filename="'.$website->theme.'_sample.zip"');
        header('Content-Length: '.strlen($contents));

        echo $contents;
    }

    public static function export_sample_parse_dictionary($dictionary, $files=array())
    {
        if(is_array($dictionary))
        {
            foreach($dictionary as $language => $dictionary_data)
                list($dictionary, $files) = theme::export_sample_parse_array($dictionary_data, $files);
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
                preg_match_all('#'.NAVIGATE_DOWNLOAD.'#', $content, $matches, PREG_OFFSET_CAPTURE);
                for($m=count($matches); $m >= 0; $m--)
                {
                    if(@empty($matches[$m][0][1])) continue;
                    $offset = $matches[$m][0][1] + strlen(NAVIGATE_DOWNLOAD);
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
                // http://192.168.1.30/navigate/navigate_download.php --> NAVIGATE_DOWNLOAD
                // http://192.168.1.30/ocean [ $website->absolute_path() ] --> WEBSITE_ABSOLUTE_PATH
                // http://192.168.1.30/navigate/themes/ocean [ NAVIGATE_PARENT.NAVIGATE_FOLDER.'/themes/'.$website->theme ] --> THEME_ABSOLUTE_PATH

                $content = str_replace(NAVIGATE_DOWNLOAD, 'url://{{NAVIGATE_DOWNLOAD}}', $content);
                $content = str_replace($website->absolute_path(), 'url://{{WEBSITE_ABSOLUTE_PATH}}', $content);
                $content = str_replace(NAVIGATE_PARENT.NAVIGATE_FOLDER.'/themes/'.$website->theme, 'url://{{THEME_ABSOLUTE_PATH}}', $content);

                $dictionary[$entry] = $content;
            }
        }

        return array($dictionary, $files);
    }

    public static function import_sample_parse_dictionary($dictionary, $files=array())
    {
        if(is_array($dictionary))
        {
            foreach($dictionary as $language => $foo)
                theme::import_sample_parse_array($dictionary[$language], $files);
        }

        return $dictionary;
    }

    public static function import_sample_parse_array($dictionary, $files=array())
    {
        global $website;

        if(!is_array($dictionary))
            return $dictionary;

        foreach($dictionary as $entry => $content)
        {
            // replace file IDs with real ones

            // example: %7B%7BNAVIGATE_FILE%7D117%7D  --> {{NAVIGATE_FILE}117}

            preg_match_all('#%7B%7BNAVIGATE_FILE%7D#', $content, $matches, PREG_OFFSET_CAPTURE);

            for($m=count($matches); $m >= 0; $m--)
            {
                if(@empty($matches[$m][0])) continue;

                $offset = $matches[$m][0][1] + strlen('%7B%7BNAVIGATE_FILE%7D#');
                $end = strpos($content, '%7D', $offset);
                $file_id = substr($content, $offset - 1, $end - $offset + 1);
                $content = substr_replace($content, $files[$file_id]->id, $matches[$m][0][1], strlen('%7B%7BNAVIGATE_FILE%7D'.$file_id.'%7D'));
            }

            $content = str_replace('url://{{NAVIGATE_DOWNLOAD}}', NAVIGATE_DOWNLOAD, $content);
            $content = str_replace('url://{{WEBSITE_ABSOLUTE_PATH}}', $website->absolute_path(), $content);
            $content = str_replace('url://{{THEME_ABSOLUTE_PATH}}', NAVIGATE_PARENT.NAVIGATE_FOLDER.'/themes/'.$website->theme, $content);

            $dictionary[$entry] = $content;
        }

        return $dictionary;
    }
}
?>