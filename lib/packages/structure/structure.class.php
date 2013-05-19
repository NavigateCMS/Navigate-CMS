<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webuser_votes/webuser_vote.class.php');

class structure
{
	public $id; // not used
	public $website;
	public $parent;
	public $position;
	public $access; // 0 => everyone, 1 => logged in, 2 => not logged in, 3 => selected webuser groups
    public $groups; // webuser groups
	public $permission;
	public $icon;
	public $metatags;
	public $template;
	public $date_published;
	public $date_unpublish;
	public $views;
	public $votes;
	public $score;	
	public $visible; // in menus
	
	public $dictionary;
	public $paths;
	
	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_structure WHERE id = '.intval($id).' AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function reload()
	{
		$item = new structure();
		$item->load($this->id);
		return $item;
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id			= $main->id;
		$this->website		= $main->website;
		$this->parent  		= $main->parent;
		$this->position		= $main->position;
		$this->template  	= $main->template;
		$this->access		= $main->access;			
		$this->permission  	= $main->permission;
		$this->icon			= $main->icon;
		$this->metatags  	= $main->metatags;
		$this->date_published	= (empty($main->date_published)? '' : $main->date_published);
		$this->date_unpublish	= (empty($main->date_unpublish)? '' : $main->date_unpublish);	
		
		$this->votes		= $main->votes;
		$this->score		= $main->score;
		$this->views		= $main->views;			
		
		$this->dictionary	= webdictionary::load_element_strings('structure', $this->id);
		$this->paths		= path::loadElementPaths('structure', $this->id);
		$this->visible		= $main->visible;

        // to get the array of groups first we remove the "g" character
        $groups = str_replace('g', '', $main->groups);
        $this->groups	    = explode(',', $groups);
    }
	
	public function load_from_post()
	{
		global $DB;

		if(intval($_REQUEST['parent'])!=$this->id)	// protection against selecting this same category as parent of itself
			$this->parent 		= intval($_REQUEST['parent']);
			
		$this->template 	= $_REQUEST['template'];
		$this->access		= intval($_REQUEST['access']);

        $this->groups	    = $_REQUEST['groups'];
        if($this->access < 3)
            $this->groups = array();

		$this->permission	= intval($_REQUEST['permission']);		
		$this->visible		= intval($_REQUEST['visible']);		
		
		$this->date_published	= (empty($_REQUEST['date_published'])? '' : core_date2ts($_REQUEST['date_published']));	
		$this->date_unpublish	= (empty($_REQUEST['date_unpublish'])? '' : core_date2ts($_REQUEST['date_unpublish']));	
		
		// language strings and options
		$this->dictionary = array();
		$this->paths = array();
		$fields = array('title', 'action-type', 'action-jump-item', 'action-jump-branch', 'action-new-window'); //, 'path', 'visible');
		
		foreach($_REQUEST as $key => $value)
		{
			if(empty($value)) continue;
			
			foreach($fields as $field)
			{
				if(substr($key, 0, strlen($field.'-'))==$field.'-')
					$this->dictionary[substr($key, strlen($field.'-'))][$field] = $value;
			}
		
			if(substr($key, 0, strlen('path-'))=='path-')
				$this->paths[substr($key, strlen('path-'))] = $value;
		}		
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

		if(!empty($this->id))
		{
			// remove dictionary elements
			webdictionary::save_element_strings('structure', $this->id, array());
			// remove path elements
			path::saveElementPaths('structure', $this->id, array());
			// remove all votes assigned to the element
			webuser_vote::remove_object_votes('structure', $this->id);			
			
			$DB->execute('DELETE FROM nv_structure
								WHERE id = '.intval($this->id).'
								  AND website = '.$website->id.' 
								LIMIT 1'
						);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;

        if(empty($this->position))
        {
            // find the next free position on the same parent (after all exsiting children)
            $DB->query('SELECT MAX(position) as max_position
                          FROM nv_structure
                         WHERE parent = '.protect($this->parent).'
                           AND website = '.protect($website->id)
            );

            $max = $DB->result('max_position');
            $this->position = intval($max[0]) + 1;
        }

        $groups = '';
        if(!empty($this->groups))
            $groups = 'g'.implode(',g', $this->groups);

        $ok = $DB->execute(' INSERT INTO nv_structure
								(id, website, parent, position, access, groups, permission,
								 icon, metatags, template, date_published, date_unpublish, 
								 visible, views, votes, score)
								VALUES
								( 0,
								  '.$website->id.', 
								  '.protect($this->parent).',
								  '.protect($this->position).',
								  '.protect($this->access).',
								  '.protect($groups).',
								  '.protect($this->permission).',
								  '.protect($this->icon).',
								  '.protect($this->metatags).',
								  '.protect($this->template).',
								  '.protect($this->date_published).',
								  '.protect($this->date_unpublish).',
								  '.protect($this->visible).',
								  0,
								  0,
								  0								  								  
								)');
			
		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		webdictionary::save_element_strings('structure', $this->id, $this->dictionary);
   		path::saveElementPaths('structure', $this->id, $this->paths);
		
		return true;
	}
	
	public function update()
	{
		global $DB;
		global $website;

        $groups = '';
        if(!empty($this->groups))
            $groups = 'g'.implode(',g', $this->groups);

		$ok = $DB->execute(' UPDATE nv_structure
								SET 
									parent = '.protect($this->parent).',
									position = '.protect($this->position).',
									access = '.protect($this->access).',
									groups = '.protect($groups).',
									permission = '.protect($this->permission).',
									icon = '.protect($this->icon).',
									metatags = '.protect($this->metatags).',
									date_published	=   '.protect($this->date_published).',
									date_unpublish	=   '.protect($this->date_unpublish).',										
									template = '.protect($this->template).',
									visible = '.protect($this->visible).',
									views = '.protect($this->views).',
									votes = '.protect($this->votes).',
									score = '.protect($this->score).'
							  WHERE id = '.intval($this->id).'
							    AND website = '.$website->id);
			      
		if(!$ok) throw new Exception($DB->get_last_error());
		
		webdictionary::save_element_strings('structure', $this->id, $this->dictionary);
		path::saveElementPaths('structure', $this->id, $this->paths);
		return true;
	}

    // retrieve all elements associated with this structure entry
    public function elements()
    {
        global $DB;

        $elements = array();

        if(!empty($this->id))
        {
            $DB->query('
                SELECT id
                  FROM nv_items
                 WHERE category = '.$this->id.'
              ORDER BY position ASC, id ASC');
            $ids = $DB->result('id');

            for($i=0; $i < count($ids); $i++)
            {
                $elements[$i] = new item();
                $elements[$i]->load($ids[$i]);
            }
        }

        return $elements;
    }
	
	public static function loadTree($id_parent=0)
	{
		global $DB;	
		global $website;

        // TODO: try to implement a cache to avoid extra database queries
		$DB->query('  SELECT * FROM nv_structure 
					   WHERE parent = '.intval($id_parent).'
					     AND website = '.$website->id.' 
					ORDER BY position ASC, id DESC');

		$result = $DB->result();
        $parent_of = array();
		
		for($i=0; $i < count($result); $i++)
		{
			if(empty($result[$i]->date_published)) 
				$result[$i]->date_published = '&infin;';
			else
				$result[$i]->date_published = core_ts2date($result[$i]->date_published, false);
				
			if(empty($result[$i]->date_unpublish)) 
				$result[$i]->date_unpublish = '&infin;';	
			else
				$result[$i]->date_unpublish = core_ts2date($result[$i]->date_unpublish, false);		
				
			$result[$i]->dates = $result[$i]->date_published.' - '.$result[$i]->date_unpublish;
		}
		
		return $result;
	}
	
	public static function hierarchy($id_parent=0)
	{
		global $website;

		$flang = $website->languages_list[0];
		if(empty($flang))
            return array();
		
		$tree = array();
		
		if($id_parent==-1)
		{
            // create the virtual root structure entry (the website)
			$obj = new structure();
			$obj->id = 0;
			$obj->label = $website->name;
            $obj->_multilanguage_label = $website->name;
			$obj->parent = -1;
			$obj->children = structure::hierarchy(0);

			$tree[] = $obj;
		}
		else
		{
			$tree = structure::loadTree($id_parent);

			for($i=0; $i < count($tree); $i++)
            {
				$tree[$i]->dictionary = webdictionary::load_element_strings('structure', $tree[$i]->id);
                $tree[$i]->label = $tree[$i]->dictionary[$website->languages_list[0]]['title'];

                for($wl=0; $wl < count($website->languages_list); $wl++)
                {
                    $lang = $website->languages_list[$wl];

                    if(empty($tree[$i]->dictionary[$lang]['title']))
                        $tree[$i]->dictionary[$lang]['title'] = '[ ? ]';

                    $style = '';
                    if($lang != $flang)
                        $style = 'display: none';

                    $label[] = '<span class="structure-label" lang="'.$lang.'" style="'.$style.'">'
                              .$tree[$i]->dictionary[$lang]['title']
                              .'</span>';

                    $bc[$tree[$i]->id][$lang] = $tree[$i]->dictionary[$lang]['title'];
                }

                $children = structure::hierarchy($tree[$i]->id);
                $tree[$i]->children = $children;
            }
		}
		
		return $tree;
	}
	
	public static function hierarchyList($hierarchy, $selected=0)
	{		
		$html = array();
				
		if(!is_array($hierarchy))
            $hierarchy = array();
		
		if(!is_array($selected))
			$selected = array($selected);
		
		foreach($hierarchy as $node)
		{	
			$li_class = '';
			$post_html = structure::hierarchyList($node->children, $selected);
			if(strpos($post_html, 'class="active"')!==false) $li_class = ' class="open" ';
					
			if(empty($html)) $html[] = '<ul>';
			if(in_array($node->id, $selected))
				$html[] = '<li '.$li_class.' value="'.$node->id.'"><span class="active">'.$node->label.'</span>';
			else
				$html[] = '<li '.$li_class.' value="'.$node->id.'"><span>'.$node->label.'</span>';

			$html[] = $post_html;
			$html[] = '</li>';
		}
		if(!empty($html)) $html[] = '</ul>';		
		
		return implode("\n", $html);
	}

    public static function hierarchyPath($hierarchy, $category)
    {
        if(is_array($hierarchy))
        {
            foreach($hierarchy as $node)
            {
                if(!empty($node->children))
                    $val = structure::hierarchyPath($node->children, $category);

                if($node->id == $category || (!empty($val)) )
                {
                    if(empty($val))
                        return array($node->label);

                    return array_merge(array($node->label), $val);
                }
            }
        }
        return;
    }
	
	public static function reorder($parent, $children)
	{
		global $DB;
		global $website;
		
		$children = explode("#", $children);
				
		for($i=0; $i < count($children); $i++)
		{		
			if(empty($children[$i])) continue;
			$ok =	$DB->execute('UPDATE nv_structure 
									 SET position = '.($i+1).'
								   WHERE id = '.$children[$i].' 
									 AND parent = '.intval($parent).'
									 AND website = '.$website->id);
							 
			if(!$ok) return array("error" => $DB->get_last_error()); 
		}
			
		return true;	
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_structure WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }
}

?>