<?php

class path
{
	public $id;
	public $type;
	public $object_id;
	public $lang;
	public $path;
  
  public $cache_file;
  public $cache_expires;
  	
	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_paths WHERE id = '.intval($id).' AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id         = $main->node_type;
		$this->website	  = $main->website;
		$this->type       = $main->node_id;
		$this->object_id	= $main->subtype;
		$this->lang		    = $main->lang;
   		$this->path		    = $main->path;
    
		$this->cache_file		  = $main->cache_file;
    	$this->cache_expires  = $main->cache_expires;
	}
	
	
	public function save()
	{
		global $DB;

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
			$DB->execute(' DELETE FROM nv_paths 
							WHERE id = '.intval($this->id).'
							  AND website = '.$website->id.'
              				LIMIT 1 '
						);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
	    
		$ok = $DB->execute(' INSERT INTO nv_paths
								(id, website, type, object_id, lang, path, cache_file, cache_expires)
								VALUES 
								( 0,
								  '.$website->id.',								  
								  '.protect($this->type).',
								  '.protect($this->object_id).',
								  '.protect($this->lang).',
								  '.protect($path).',
								  '.protect($cache_file).',
				                  '.protect($cache_expires).'								  
								)');
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		return true;
	}	
	
	public function update()
	{
		global $DB;
		global $website;
	    
		$ok = $DB->execute(' UPDATE nv_paths
								SET 
								  type = '.protect($this->type).',
								  object_id = '.protect($this->object_id).',
								  lang = '.protect($this->lang).',
								  path = '.protect($this->path).',
								  cache_file = '.protect($this->cache_file).',
                 				  cache_expires'.protect($this->cache_expires).'
                 				WHERE id = '.protect($this->id).'
								  AND website = '.$website->id);
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		return true;
	}	  

	public static function loadElementPaths($type, $object_id)
	{
		global $DB;
		global $website;
		
		$ok = $DB->query('SELECT *
					        FROM nv_paths 
				           WHERE type = '.protect($type).'
			                 AND object_id = '.protect($object_id).'
							 AND website = '.$website->id);		
		
	    if(!$ok) throw new Exception($DB->get_last_error());
    
		$data = $DB->result();
		if(!is_array($data)) $data = array();
		
		$out = array();
		
		foreach($data as $item)
		{
			$out[$item->lang] = $item->path;
		}
		    		
		return $out;	
	}

	public static function saveElementPaths($type, $object_id, $paths)
	{
		global $DB;
		global $website;
		
	    if(empty($object_id)) throw new Exception('ERROR path: No ID!');

		// delete old entries
		$DB->execute('DELETE FROM nv_paths 
							WHERE type = '.protect($type).'
							  AND object_id = '.protect($object_id).'
							  AND website = '.$website->id);

        if(!is_array($paths))
            return;

		// and now insert the new values
		foreach($paths as $lang => $path)
		{
    	  	if(empty($path)) continue;
    
			$ok = $DB->execute(' INSERT INTO nv_paths
									(id, website, type, object_id, lang, path, cache_file, cache_expires)
									VALUES 
									( 0,
									  '.$website->id.',
									  '.protect($type).',
									  '.protect($object_id).',
									  '.protect($lang).',
									  '.protect($path).',
									  "",
									  ""								  
									)');
  			
  			if(!$ok) throw new Exception($DB->get_last_error());
		}
		
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_paths WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

}

?>