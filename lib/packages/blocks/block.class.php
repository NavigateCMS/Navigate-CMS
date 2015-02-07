<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');

class block
{
	public $id;
	public $type;
	public $date_published;
	public $date_unpublish;		
	public $access;
    public $groups;
	public $enabled;
	public $trigger;
	public $action;
	public $notes;
	public $dictionary;
	public $position;
    public $fixed;
    public $categories;
    public $exclusions;

    public $properties;

    public function __clone()
    {
        foreach($this as $key => $val)
        {
            if(is_object($val))
                $this->{$key} = clone $val;
            else if(is_array($val))
                $this->{$key} = mb_unserialize(serialize($val));
        }
    }

	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_blocks 
						WHERE id = '.intval($id).'
						  AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id				= $main->id;
		$this->website			= $main->website;
		$this->type  			= $main->type;
		$this->date_published	= (empty($main->date_published)? '' : $main->date_published);
		$this->date_unpublish	= (empty($main->date_unpublish)? '' : $main->date_unpublish);
		$this->access			= $main->access;
		$this->enabled			= $main->enabled;

		$this->trigger			= mb_unserialize($main->trigger);
				
		if(is_array($this->trigger['trigger-html']))
		{
			foreach($this->trigger['trigger-html'] as $language => $code)
			{
				$this->trigger['trigger-html'][$language] = htmlspecialchars_decode($code);
			}
		}
		
		if(is_array($this->trigger['trigger-content']))
		{
			foreach($this->trigger['trigger-content'] as $language => $code)
			{
				$this->trigger['trigger-content'][$language] = stripslashes($code);
			}
		}

		$this->action			= mb_unserialize($main->action);				
		$this->notes			= $main->notes;		
		$this->dictionary		= webdictionary::load_element_strings('block', $this->id);	// title

        $this->position			= $main->position;
        $this->fixed	        = $main->fixed;
        $this->categories		= array_filter(explode(',', $main->categories));
        $this->exclusions		= array_filter(explode(',', $main->exclusions));

        // to get the array of groups first we remove the "g" character
        $groups = str_replace('g', '', $main->groups);
        $this->groups = explode(',', $groups);
        if(!is_array($this->groups))  $this->groups = array($groups);
	}
	
	public function load_from_post()
	{
		global $DB;
		global $website;

		$this->type  			= $_REQUEST['type'];
		$this->date_published	= (empty($_REQUEST['date_published'])? '' : core_date2ts($_REQUEST['date_published']));	
		$this->date_unpublish	= (empty($_REQUEST['date_unpublish'])? '' : core_date2ts($_REQUEST['date_unpublish']));	
		$this->access			= intval($_REQUEST['access']);

        $this->groups	        = $_REQUEST['groups'];
        if($this->access < 3)
            $this->groups = array();

		$this->enabled			= intval($_REQUEST['enabled']);	
		$this->notes  			= pquotes($_REQUEST['notes']);

		$this->dictionary		= array();	// for titles
		$this->trigger			= array();
		$this->action			= array();
				
		$fields_title 	= array ( 'title' );
		$fields_trigger = array (
            'trigger-type',
            'trigger-title',
            'trigger-image',
            'trigger-rollover',
            'trigger-rollover-active',
            'trigger-video',
            'trigger-flash',
            'trigger-html',
            'trigger-links',
            'trigger-content'
        );
		$fields_action	= array (
            'action-type',
            'action-web',
            'action-file',
            'action-image'
        );

		foreach($_REQUEST as $key => $value)
		{
			if(empty($value)) continue;
				
			foreach($fields_title as $field)
			{
				if(substr($key, 0, strlen($field.'-'))==$field.'-')
					$this->dictionary[substr($key, strlen($field.'-'))]['title'] = $value;
			}

			foreach($fields_trigger as $field)
			{
                // f.e., Does this REQUEST field begins with "trigger-content-"?
				if(substr($key, 0, strlen($field.'-'))==$field.'-')
				{
                    switch($field)
                    {
                        case 'trigger-html':
                            $this->trigger[$field][substr($key, strlen($field.'-'))] = htmlspecialchars($value);
                            break;

                        case 'trigger-content':
                            $this->trigger[$field][substr($key, strlen($field.'-'))] = $value;
                            break;

                        case 'trigger-links':
                            $key_parts = explode("-", $key);
                            $key_lang = array_pop($key_parts);
                            $key_name = array_pop($key_parts);
                            $value = array_filter($value);

                            // ignore links without a title or without a link
                            if(empty($value) || empty($key_name))
                                continue;

                            $this->trigger[$field][$key_lang][$key_name] = $value;
                            break;

                        default:
                            $this->trigger[$field][substr($key, strlen($field.'-'))] = pquotes($value);
                            break;
                    }
				}
			}
			
			foreach($fields_action as $field)
			{
				if(substr($key, 0, strlen($field.'-'))==$field.'-')
					$this->action[$field][substr($key, strlen($field.'-'))] = $value;
			}						
		}

		$this->categories 	= '';
		if(!empty($_REQUEST['categories']))
			$this->categories	= explode(',', $_REQUEST['categories']);	
			
		$this->exclusions 	= '';
		if(!empty($_REQUEST['exclusions']))
			$this->exclusions	= explode(',', $_REQUEST['exclusions']);

		if($_REQUEST['all_categories']=='1')
        {
			$this->categories 	= array();
			$this->exclusions 	= array();
        }
	}
	
	public static function reorder($type, $order, $fixed)
	{
		global $DB;
		global $website;

		$item = explode("#", $order);
							
		for($i=0; $i < count($item); $i++)
		{		
			if(empty($item[$i])) continue;

            $block_is_fixed = ($fixed[$item[$i]]=='1'? '1' : '0');

			$ok =	$DB->execute('UPDATE nv_blocks
									 SET position = '.($i+1).',
									     fixed = '.$block_is_fixed.'
								   WHERE id = '.$item[$i].'
						 		     AND website = '.$website->id);
			
			if(!$ok) return array("error" => $DB->get_last_error()); 
		}
			
		return true;	
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
			webdictionary::save_element_strings('block', $this->id, array());
			
			$DB->execute('DELETE FROM nv_blocks
								WHERE id = '.intval($this->id).'
						  		  AND website = '.$website->id
						);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;

        if(!is_array($this->categories))
            $this->categories = array();

        if(!is_array($this->exclusions))
            $this->exclusions = array();

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

        $ok = $DB->execute(
            'INSERT INTO nv_blocks
                (id, website, type, date_published, date_unpublish,
                 position, fixed, categories, exclusions,
                 access, groups, enabled, `trigger`, action, notes)
                VALUES
                ( 0,
                  :website,
                  :type,
                  :date_published,
                  :date_unpublish,
                  :position,
                  :fixed,
                  :categories,
                  :exclusions,
                  :access,
                  :groups,
                  :enabled,
                  :trigger,
                  :action,
                  :notes
                )
            ',
            array(
                ':website'          =>  $website->id,
                ':type'             =>  $this->type,
                ':date_published'   =>  intval($this->date_published),
                ':date_unpublish'   =>  intval($this->date_unpublish),
                ':position'         =>  intval($this->position),
                ':fixed'            =>  intval($this->fixed),
                ':categories'       =>  implode(',', $this->categories),
                ':exclusions'       =>  implode(',', $this->exclusions),
                ':access'           =>  intval($this->access),
                ':groups'           =>  $groups,
                ':enabled'          =>  intval($this->enabled),
                ':trigger'          =>  serialize($this->trigger),
                ':action'           =>  serialize($this->action),
                ':notes'            =>  (is_null($this->notes)? '' : $this->notes)
            )
        );

		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		webdictionary::save_element_strings('block', $this->id, $this->dictionary);
		
		return true;
	}
	
	public function update()
	{
		global $DB;
		global $website;

        if(!is_array($this->categories))
            $this->categories = array();

        if(!is_array($this->exclusions))
            $this->exclusions = array();

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

        $ok = $DB->execute(
            'UPDATE nv_blocks
             SET
                `type`			= :type,
                date_published 	= :date_published,
                date_unpublish  = :date_unpublish,
                `position` 		= :position,
                fixed	        = :fixed,
                categories		= :categories,
                exclusions		= :exclusions,
                `trigger` 		= :trigger,
                `action` 		= :action,
                access 			= :access,
                groups          = :groups,
                enabled 		= :enabled,
                notes	 		= :notes
             WHERE id = :id
               AND website = :website
            ',
            array(
                ':id'               =>  $this->id,
                ':website'          =>  $this->website,
                ':type'             =>  $this->type,
                ':date_published'   =>  $this->date_published,
                ':date_unpublish'   =>  $this->date_unpublish,
                ':position'         =>  $this->position,
                ':fixed'            =>  $this->fixed,
                ':categories'       =>  implode(',', $this->categories),
                ':exclusions'       =>  implode(',', $this->exclusions),
                ':access'           =>  $this->access,
                ':groups'           =>  $groups,
                ':enabled'          =>  $this->enabled,
                ':trigger'          =>  serialize($this->trigger),
                ':action'           =>  serialize($this->action),
                ':notes'            =>  $this->notes
            )
        );
		
		if(!$ok) throw new Exception($DB->get_last_error());

		webdictionary::save_element_strings('block', $this->id, $this->dictionary);
		
		return true;
	}

    // TODO: in Navigate 1.9 add more block types (modes)
    public static function modes()
    {
        $modes = array(
            'block' => t(437, 'Block'),
            'theme' => t(368, 'Theme')
            /*
             * 'google_map' => 'Google Map',
             * 'bing_map' => 'Bing Map',
             * 'google_adsense' => 'Google Adsense'
             * etc.
             */
        );

        return $modes;
    }

    public static function custom_types()
    {
        global $DB;
        global $website;

        $data = $DB->query_single('block_types', 'nv_websites', ' id = '.$website->id);
        $data = mb_unserialize($data);

        return $data;
    }
	
	public static function types($orderby='id', $asc='asc')
	{
		global $theme;

        $data = block::custom_types();

        // retrieve block types from theme
        $theme_blocks = json_decode(json_encode($theme->blocks), true);

        if(!is_array($theme_blocks))
            $theme_blocks = array();
        else
        {
            // process theme translations for each block title
            for($b=0; $b < count($theme_blocks); $b++)
                $theme_blocks[$b]['title'] = $theme->t($theme_blocks[$b]['title']);
        }

        if(!is_array($data))
            $data = array();

        $data = array_merge($data, $theme_blocks);

        // Navigate 1.6.6 compatibility (before title/code separation)
        for($d=0; $d < count($data); $d++)
        {
        	if(function_exists($theme->t))
            	$data[$d]['title'] = $theme->t($data[$d]['title']);

            if(empty($data[$d]['code']))
                $data[$d]['code'] = $data[$d]['title'];

            if(empty($data[$d]['type']))
                $data[$d]['type'] = 'block';
        }

		// Obtain a list of columns
		if(!is_array($data)) $data = array();
		$order = array();
				
		foreach($data as $key => $row)
			$order[$key]  = $row[$orderby];

		// Sort the data with volume descending, edition ascending
		// $data as the last parameter, to sort by the common key
		array_multisort($order, (($asc=='asc')? SORT_ASC : SORT_DESC), $data);

/*
		$x[] = array( 'id'		=> 1,
					  'title'	=> 'test',
                      'code'    => 'codename',
					  'width'	=> 200,
					  'height'	=> 50,
					  'order'	=> 'theme',
					  'maximum' => 3
					);

		$x = serialize($x);
		var_dump($x);
*/
		return $data;
	}
	
	public static function types_update($array)
	{
		global $DB;
		global $website;

        $array = array_filter($array);
        sort($array);

		$array = serialize($array);
				
		$ok = $DB->execute('UPDATE nv_websites 
		 				 	SET block_types = '.protect($array).'
					   		WHERE id = '.$website->id);
					
		if(!$ok)
			throw new Exception($DB->last_error());
							
		return true;
	}

    public function property($property_name, $raw=false)
    {
        // load properties if not already done
        if(empty($this->properties))
            $this->properties = property::load_properties('block', $this->type, 'block', $this->id);

        for($p=0; $p < count($this->properties); $p++)
        {
            if($this->properties[$p]->name==$property_name || $this->properties[$p]->id==$property_name)
            {
                if($raw)
                    $out = $this->properties[$p]->value;
                else
                    $out = $this->properties[$p]->value;

                break;
            }
        }

        return $out;
    }
	
	public function quicksearch($text)
	{
		global $DB;
		global $website;
		
		$like = ' LIKE '.protect('%'.$text.'%');
		
		// we search for the IDs at the dictionary NOW (to avoid inefficient requests)
		
		$DB->query('SELECT DISTINCT (nvw.node_id)
					 FROM nv_webdictionary nvw
					 WHERE nvw.node_type = "block" AND
						   nvw.text '.$like.' AND
						   nvw.website = '.$website->id, 'array');
						   
		$dict_ids = $DB->result("node_id");
		
		// all columns to look for	
		$cols[] = 'b.id' . $like;
		$cols[] = 'b.type' . $like;
		$cols[] = 'b.notes' . $like;		
		
		/* INEFFICIENT WAY
		$cols[] = 'i.id IN ( SELECT nvw.node_id 
							 FROM nv_webdictionary nvw
							 WHERE nvw.node_type = "item" AND
								   nvw.text '.$like.'
							)' ;
		*/
		
		if(!empty($dict_ids))
			$cols[] = 'b.id IN ('.implode(',', $dict_ids).')';
			
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_blocks WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }
		
}

?>