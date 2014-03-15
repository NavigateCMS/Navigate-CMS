<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/themes/theme.class.php');

class template
{
	public $id;
	public $title;
	public $file;
	public $sections;
	public $gallery;
	public $comments;
	public $tags;
	public $statistics;
	public $permission; // 0 => public, 1 => private (only navigate cms users), 2 => hidden
	public $enabled;
	
	public function load($id)
	{
		global $DB;
		global $website;
		global $theme;
		
		if(is_numeric($id))
		{		
			if($DB->query('SELECT * FROM nv_templates WHERE id = '.intval($id).' AND website = '.$website->id))
			{
				$data = $DB->result();
				$this->load_from_resultset($data); // there will be as many entries as languages enabled
			}
		}
		else
		{
			if(empty($theme))
			{
				$theme = new theme();
				$theme->load($website->theme);
			}

			$this->load_from_theme($id);
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id			= $main->id;
		$this->website		= $main->website;		
		$this->title  		= $main->title;
		$this->file			= $main->file;
		$this->sections		= mb_unserialize($main->sections);
		$this->gallery		= $main->gallery;
		$this->comments		= $main->comments;		
		$this->tags			= $main->tags;		
		$this->statistics	= $main->statistics;						
		$this->permission	= $main->permission;		
		$this->enabled		= $main->enabled;						
	}
	
	public function load_from_theme($id)
	{
		global $theme;
		global $website;

		$template = NULL;
		for($t=0; $t < count($theme->templates); $t++)
		{
			if($theme->templates[$t]->type == $id)
				$template = $theme->templates[$t];	
		}

		if(!$template) return;

        $defaults = array(
            'sections' => array(
                0 => array(
                    'code' => 'main',
                    'name' => '#main#',
                    'editor' => 'tinymce',
                    'width' => '960')
                ),
            'gallery'  => 0,
            'comments' => 0,
            'tags' => 0,
            'statistics' => 1,
            'permission' => 0,
            'enabled' => 1,
            'properties' => array(
            )
        );

		$this->id			= $template->type;
		$this->website		= $website->id;		
		$this->title  		= $theme->template_title($template->type);
		$this->file			= NAVIGATE_PATH.'/themes/'.$theme->name.'/'.$template->file;
		$this->sections		= (isset($template->sections)? json_decode(json_encode($template->sections), true) : $defaults['sections']);
		$this->gallery		= (isset($template->gallery)? $template->gallery : $defaults['gallery']);
		$this->comments		= (isset($template->comments)? $template->comments : $defaults['comments']);
		$this->tags			= (isset($template->tags)? $template->tags : $defaults['tags']);
		$this->statistics	= (isset($template->statistics)? $template->statistics : $defaults['statistics']);
		$this->permission	= (isset($template->permission)? $template->permission : $defaults['permission']);
		$this->enabled		= (isset($template->enabled)? $template->enabled : $defaults['enabled']);
        $this->properties   = (isset($template->properties)? (array)$template->properties : $defaults['properties']);

        // process properties (translate titles, etc.)
        for($p=0; $p < count($this->properties); $p++)
        {
            if($this->properties[$p]->type == 'option')
            {
                $poptions = array();
                foreach($this->properties[$p]->options as $key => $value)
                    $poptions[$key] = $theme->t($value);

                $this->properties[$p]->options = $poptions;
            }
        }
	}
	
	public function load_from_post()
	{
		global $DB;
		
		$this->title  		= $_REQUEST['title'];
		$this->file			= $_REQUEST['file'];
		$this->permission	= intval($_REQUEST['permission']);
		$this->enabled		= intval($_REQUEST['enabled']);	
		
		// sections
		$this->sections		= array();
		for($s = 0; $s < count($_REQUEST['template-sections-code']); $s++)
		{
			if(empty($_REQUEST['template-sections-code'][$s])) continue;
			$this->sections[] = array( 'code' => $_REQUEST['template-sections-code'][$s], 
									   'name' => $_REQUEST['template-sections-name'][$s],
									   'editor' => $_REQUEST['template-sections-editor'][$s],
									   'width' => $_REQUEST['template-sections-width'][$s] );
		}
		if(empty($this->sections))
		{
			$this->sections = array( 0 => array( 'code' => 'main', 
												 'name' => '#main#', 
												 'editor' => 'tinymce', 
												 'width' => '960') );	
		}
		
		$this->gallery		= intval($_REQUEST['gallery']);	
		$this->comments		= intval($_REQUEST['comments']);	
		$this->tags			= intval($_REQUEST['tags']);	
		$this->statistics	= intval($_REQUEST['statistics']);								
	}
	
	
	public function save()
	{
		if(!empty($this->id))
			return $this->update();
		else
			return $this->insert();			
	}
	
	public function delete()
	{
		global $DB;
		global $website;

		// remove all old entries
		if(!empty($this->id))
		{
            // remove associated properties values and definitions
            $DB->execute("
                DELETE FROM nv_properties_items
                 WHERE property_id IN (
                            SELECT id FROM nv_properties
                            WHERE template = ".intval($this->id)."
                            AND website = ".$website->id."
                       )
                   AND website = ".$website->id
            );

            $DB->execute("
                DELETE FROM nv_properties
                 WHERE template = ".intval($this->id)."
                   AND website = ".$website->id
            );

			$DB->execute("
			    DELETE FROM nv_templates
                 WHERE id = ".intval($this->id)."
                   AND website = ".$website->id
            );
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
		
		$ok = $DB->execute(' INSERT INTO nv_templates
								(id, website, title, file, sections, gallery, comments, tags, statistics, permission, enabled)
								VALUES 
								( 0,
								  '.$website->id.',
								  '.protect($this->title).',
								  '.protect($this->file).',
								  '.protect(serialize($this->sections)).',  
								  '.protect($this->gallery).',
								  '.protect($this->comments).',
								  '.protect($this->tags).',								  								  
								  '.protect($this->statistics).',
								  '.protect($this->permission).',
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
			
		$ok = $DB->execute(' UPDATE nv_templates
								SET 
									title	= '.protect($this->title).',
									file =   '.protect($this->file).',
									sections = '.protect(serialize($this->sections)).', 
									gallery =   '.protect($this->gallery).',
									comments =   '.protect($this->comments).',
									tags =   '.protect($this->tags).',				
								 	statistics = '.protect($this->statistics).',														
									permission =   '.protect($this->permission).',
									enabled =  '.protect($this->enabled).'
							WHERE id = '.$this->id.'
							  AND website = '.$website->id);
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		return true;
	}		
	
	public static function elements()
	{
		global $DB;
		global $website;
		
		$data = array();
		
		// get available theme templates
		if(!empty($website->theme) && empty($theme))
		{		
			$theme = new theme();
			$theme->load($website->theme);
			$out = $theme->templates();
		}
				
		// merge theme templates with custom templates
		if(is_array($out))
			$data = array_merge($data, $out);
		
		if($DB->query('SELECT id, title 
						 FROM nv_templates 
						 WHERE website = '.$website->id.' 
						 ORDER BY title'))
		{
			$out = $DB->result();
		}

		if(is_array($out))
			$data = array_merge($data, $out);

		return $data;
	}

	public static function section_name($default)
	{
		global $website;
		global $theme;
		
		$out = $default;
			
		if(!empty($website->theme))
		{
			if(empty($theme))
			{
				$theme = new theme();
				$theme->load($website->theme);			
			}
				
			$out = $theme->template_title($default, false);
		}
			
		switch($default)
		{
			case '#main#':
				$out = t(238, 'Main content');
				break;
				
			default:	
		}
		
		return $out;
	}

    public static function search($orderby="", $filters=false)
    {
        global $DB;
        global $website;
        global $theme;

        // retrieve custom templates
        $DB->queryLimit('id,title,NULL as theme,permission,enabled',
                        'nv_templates',
                        'website = '.$website->id,
                        $orderby,
                        0,
                        PHP_INT_MAX);

        $dataset = $DB->result();

        // retrieve theme templates
        for($t=0; $t < count($theme->templates); $t++)
        {
            $template = new template();
            $template->load_from_theme($theme->templates[$t]->type);

            $dataset[] = array(
                'id'            =>  $template->id,
                'title'         =>  $template->title,
                'theme'         =>  $theme->title,
                'permission'    =>  $template->permission,
                'enabled'       =>  $template->enabled
            );

            unset($template);
        }

        // filter results
        if(!empty($filters))
        {
            if(!empty($filters['quicksearch']))
            {
                $dataset = array_filter_quicksearch($dataset, $filters['quicksearch']);
            }
        }

        // reorder results
        // SQL format: column direction (only ONE column allowed)
        // example: id desc
        $column = array();
        list($order_column, $order_direction) = explode(' ', $orderby);
        for($d=0; $d < count($dataset); $d++)
            $column[] = $dataset[$d][$order_column];

        array_multisort($column, ($order_direction=='desc'? SORT_DESC : SORT_ASC), $dataset);

        return $dataset;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_templates WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }


}

?>