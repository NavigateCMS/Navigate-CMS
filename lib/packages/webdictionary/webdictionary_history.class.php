<?php

class webdictionary_history
{
	public static function save_element_strings($node_type, $node_id, $dictionary, $autosave=false)
	{
		global $DB;
		global $website;

        $changed = false;

	    if(empty($node_id)) throw new Exception('ERROR webdictionary: No ID!');
							  
		foreach($dictionary as $lang => $item)
		{
			foreach($item as $subtype => $litem)
			{	
				if(strpos($subtype, 'section-')===0)
				{			
					if($litem=='<p><br _mce_bogus="1"></p>') continue;	// tinymce empty contents, no need to save it
				
					// has text been changed since last save?
					
					$last_litem = $DB->query_single('`text`', 'nv_webdictionary_history', ' node_id = '.protect($node_id).' AND
																							website = '.protect($website->id).' AND
																							lang = '.protect($lang).' AND
																							subtype = '.protect($subtype).' AND
																							node_type = '.protect($node_type).' AND
																							autosave = '.protect(($autosave)? '1' : '0').'
																							ORDER BY date_created DESC ');
																							
					if($last_litem != $litem)
					{
                        $changed = true;

						$time = core_time();
						
						// autocleaning
						if($autosave) 
						{
							// remove previous autosaved elements
							$DB->execute('DELETE FROM nv_webdictionary_history 
												WHERE node_id = '.protect($node_id).' AND
													  website = '.protect($website->id).' AND
														 lang = '.protect($lang).' AND
													  subtype = '.protect($subtype).' AND
													node_type = '.protect($node_type).' AND
													 autosave = 1 AND
												 date_created < '.(core_time() - 86400 * 7));
						}
					
						$DB->execute('INSERT INTO nv_webdictionary_history
										(	id,	website, node_type, node_id, subtype, lang, `text`, date_created, autosave)
										VALUES
										(	0,
											'.$website->id.',
											'.protect($node_type).',
											'.protect($node_id).',
											'.protect($subtype).',
											'.protect($lang).',
											'.protect($litem).',
											'.protect(core_time()).',
											'.protect(($autosave)? '1' : '0').'
										)');
					}
				}
			}
		}

        return $changed;
	}

	public static function load_element_strings($node_type, $node_id, $savedOn="latest")
	{
		global $DB;
		
		// load all webdictionary history elements and keep only the latest
				
		$DB->query('SELECT subtype, lang, text
					  FROM nv_webdictionary_history
					 WHERE node_type = '.protect($node_type).'
					   AND node_id = '.protect($node_id).'
					 ORDER BY date_created ASC');							 
				
		$data = $DB->result();
		
		if(!is_array($data)) $data = array();
		$dictionary = array();
		
		foreach($data as $item)
		{
			$dictionary[$item->lang][$item->subtype] = $item->text;
		}
		
		return $dictionary;			
	}

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_webdictionary_history WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }
}

?>