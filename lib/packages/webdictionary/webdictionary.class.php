<?php

class webdictionary
{
	public $id; // unused but needed
	public $node_type;
	public $theme;
	public $node_id;
	public $subtype;
	public $lang;
	public $text;
	
	// load a certain word from the dictionary with its translations
	public function load($id)
	{
		global $DB;
		global $website;
		global $theme;

		if(is_numeric($id))
		{
			if($DB->query('SELECT * FROM nv_webdictionary
							WHERE node_id = '.intval($id).'
							  AND node_type = '.protect('global').'
							  AND website = '.$website->id))
			{
				$data = $DB->result();
				$this->load_from_resultset($data); // there will be as many entries as languages enabled
			}
		}
		else
		{
			// $id is a theme string that may be in the database or/and the theme json dictionary
			$theme_dictionary = $theme->get_translations();

			$this->node_type= 'theme';
			$this->node_id  = $id;
			$this->theme	= $theme->name;
			$this->subtype	= $id;
					
			$this->text = array();
			foreach($theme_dictionary as $word)
			{
				if($word['node_id']==$id)
					$this->text[$word['lang']] = $word['text'];
			}
			
			// we need to load the database versions of the theme strings
			// node_id is not used in database with theme strings
			$DB->query('SELECT lang, text
						  FROM nv_webdictionary 
						 WHERE node_type = "theme"
						   AND theme = '.protect($theme->name).'
						   AND subtype = '.protect($this->subtype).'
						   AND website = '.$website->id);		
								
			$data = $DB->result();
			
			if(!is_array($data)) $data = array();			
			foreach($data as $item)
				$this->text[$item->lang] = $item->text;
		}
	}
		
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->node_type= $main->node_type;
		$this->node_id  = $main->node_id;
		$this->theme	= $main->theme;
		$this->subtype	= $main->subtype;
		$this->text		= array();
		
		for($r=0; $r < count($rs); $r++)
		{
			$this->text[$rs[$r]->lang] = $rs[$r]->text;
		}
	}
	
	public function load_from_post()
	{
		global $DB;
		global $theme;

		// if node_id is empty, then is an insert
		$this->node_type 	= $_REQUEST['node_type'];
		$this->subtype 		= $_REQUEST['subtype'];
		$this->theme 		= $_REQUEST['theme']; //(is_numeric($this->node_id)? '' : $theme->name);
		$this->node_id		= $_REQUEST['node_id'];
		
		$this->text = array();
		foreach($_REQUEST as $key => $value)
		{
			if(substr($key, 0, strlen("webdictionary-text-"))=="webdictionary-text-")
				$this->text[substr($key, strlen("webdictionary-text-"))] = $value;
		}
	}
	
	
	public function save()
	{
		global $DB;
		global $website;
		
		// remove all old entries
		if(!empty($this->node_id))
		{
			if(is_numeric($this->node_id))
				$node_id_filter = ' AND node_id = '.intval($this->node_id);
			else
				$node_id_filter = '';

			$DB->execute('DELETE FROM nv_webdictionary 
								WHERE website = '.$website->id.' 
								  AND subtype = '.protect($this->subtype).'
								  AND theme = '.protect($this->theme).' 
								  AND node_type = '.protect($this->node_type).
								  $node_id_filter
						);
		}
		
		// insert the new ones
		return $this->insert();			
	}
	
	public function delete()
	{
		global $DB;
		global $website;

		// remove all old entries
		if(!empty($this->node_id))
		{
			if(is_numeric($this->node_id))
				$node_id_filter = ' AND node_id = '.intval($this->node_id);
			else
				$node_id_filter = '';			
			
			$DB->execute('
 				DELETE FROM nv_webdictionary
				WHERE subtype = '.protect($this->subtype).'
				  AND node_type = '.protect($this->node_type).'
				  AND theme = '.protect($this->theme).'
				  AND website = '.$website->id.
				  $node_id_filter
			);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
		
		if(empty($this->node_id)) 
		{
			// we need to find what is the next node_id available for this subtype
			$tmp = $DB->query_single(
				'MAX(node_id)',
				'nv_webdictionary',
				' subtype = '.protect($this->subtype).'
				       AND node_type = '.protect($this->node_type).'
					   AND website = '.$website->id
			);

			$this->node_id = intval($tmp) + 1;
		}
		
		// one entry per language
		foreach($this->text as $lang => $text)
		{
			if(empty($text)) continue;
			
			if(!empty($this->theme))
				$node_id = 0;
			else
				$node_id = $this->node_id;
						
			$ok = $DB->execute(' INSERT INTO nv_webdictionary
									(id, website, node_type, node_id, theme, subtype, lang, `text`)
									VALUES 
									( 0,
									  '.protect($website->id).',
									  '.protect($this->node_type).',
									  '.protect($node_id).',
									  '.protect($this->theme).',
									  '.protect($this->subtype).',
									  '.protect($lang).',
									  '.protect($text).'								  
									)');
			
			if(!$ok) throw new Exception($DB->get_last_error());
		}
		
		return true;
	}	
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'node_id' . $like;
		$cols[] = 'lang' . $like;
		$cols[] = 'subtype' . $like;
		$cols[] = 'text' . $like;
	
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}
	
	// only for strings NOT from theme dictionary 
	public static function load_element_strings($node_type, $node_id)
	{
		global $DB;
		
		$DB->query('SELECT subtype, lang, text
					  FROM nv_webdictionary 
					 WHERE node_type = '.protect($node_type).'
					   AND node_id = '.protect($node_id));				
				
		$data = $DB->result();
		
		if(!is_array($data)) $data = array();
		$dictionary = array();
		
		foreach($data as $item)
		{
			$dictionary[$item->lang][$item->subtype] = $item->text;
		}
		
		return $dictionary;	
	}

	public static function save_element_strings($node_type, $node_id, $dictionary)
	{
		global $DB;
		global $website;
		
	    if(empty($node_id)) throw new Exception('ERROR webdictionary: No ID!');

		// delete old entries
		$DB->execute('
		    DELETE FROM nv_webdictionary
             WHERE node_type = '.protect($node_type).'
               AND node_id = '.protect($node_id).'
               AND website = '.$website->id
        );
							  
		// and now insert the new values
        if(!is_array($dictionary))
            $dictionary = array();
		foreach($dictionary as $lang => $item)
		{
			foreach($item as $subtype => $litem)
			{	
				// NO error checking
				$DB->execute('
				    INSERT INTO nv_webdictionary
                    (	id,	website, node_type, theme, node_id, subtype, lang, `text`)
                    VALUES
                    (	0,
                        '.protect($website->id).',
                        '.protect($node_type).',
                        "",
                        '.protect($node_id).',
                        '.protect($subtype).',
                        '.protect($lang).',
                        '.protect($litem).'
                    )'
                );
			}
		}
	}

	public static function save_translations_post($language)
	{
		global $DB;
		global $website;
		global $theme;

		$errors = array();

		foreach($_POST as $key => $text)
		{
			if(substr($key, 0, strlen($language.'-'))==($language.'-'))
			{
				$key = substr($key, strlen($language.'-'));

				if(is_numeric($key))
				{
					// remove old entry, if exists
					$DB->execute('
		                DELETE FROM nv_webdictionary
						WHERE node_id = '.protect($key).'
						  AND node_type = '.protect('global').'
						  AND lang = '.protect($language).'
						  AND website = '.$website->id.'
						LIMIT 1
					');
				}
				else
				{
					// remove old entry, if exists
					$DB->execute('
		                DELETE FROM nv_webdictionary
						WHERE subtype = '.protect($key).'
						  AND node_type = '.protect("theme").'
						  AND theme = '.protect($theme->name).'
						  AND lang = '.protect($language).'
						  AND website = '.$website->id.'
						LIMIT 1
					');
				}

				// insert new value
				$ok = $DB->execute('
				    INSERT INTO nv_webdictionary
                    (	id,	website, node_type, theme, node_id, subtype, lang, `text`)
                    VALUES
                    (	0, :website, :node_type, :theme, :node_id, :subtype, :lang, :text )',
					array(
						':website' => $website->id,
						':node_type' => (is_numeric($key)? 'global' : 'theme'),
						':theme' => (is_numeric($key)? '' : $theme->name),
						':node_id' => (is_numeric($key)? $key : 0),
						':subtype' => (is_numeric($key)? '' : $key),
						':lang' => $language,
						':text' => $text
					)
				);

				if(!$ok)
					$errors[] = $DB->get_last_error();
			}
		}

		return (empty($errors)? true : $errors);
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_webdictionary WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }

}

?>