<?php

class nv_function
{
	public $id;
	public $category;
	public $codename;
	public $icon;
	public $lid;
	public $enabled;
  	
	public function load($id)
	{
		global $DB;
		if($DB->query('SELECT * FROM nv_functions WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id      		= $main->id;
		$this->category     = $main->category;
		$this->codename		= $main->codename;
		$this->icon		    = $main->icon;
   		$this->lid		    = $main->lid;    
		$this->enabled		= $main->enabled;
	}
	
	public function load_from_post()
	{
		$this->category     = $_REQUEST['category'];
		$this->codename		= $_REQUEST['codename'];
		$this->icon		    = $_REQUEST['icon'];
   		$this->lid		    = $_REQUEST['lid'];    
		$this->enabled		= ($_REQUEST['enabled']=='1'? '1' : '0');
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

		// remove all old entries
		if(!empty($this->id))
		{
			$DB->execute(' 
 				DELETE FROM nv_functions
				WHERE id = '.intval($this->id).'
                LIMIT 1 '
			);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
    
		$ok = $DB->execute(' 
 			INSERT INTO nv_functions
				(id, category, codename, icon, lid, enabled)
			VALUES 
				( 0, :category, :codename, :icon, :lid, :enabled )',
			array
			(
				'category' => value_or_default($this->category, ""),
				'codename' => value_or_default($this->codename, ""),
				'icon' => value_or_default($this->icon, ""),
				'lid' => value_or_default($this->lid, 0),
				'enabled' => value_or_default($this->enabled, 0)
			)
		);
				
		if(!$ok)
			throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		return true;
	}	
	
	public function update()
	{
		global $DB;
	    
		$ok = $DB->execute(' 
 			UPDATE nv_functions
			   SET category = :category, codename = :codename, icon = :icon,
				  lid = :lid, enabled = :enabled
            WHERE id = :id',
			array(
				'id' => $this->id,
				'category' => value_or_default($this->category, ""),
				'codename' => value_or_default($this->codename, ""),
				'icon' => value_or_default($this->icon, ""),
				'lid' => value_or_default($this->lid, 0),
				'enabled' => value_or_default($this->enabled, 0)
			)
		);
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		return true;
	}
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'id' . $like;
		$cols[] = 'category' . $like;
		$cols[] = 'codename' . $like;
		$cols[] = 'icon' . $like;		
		$cols[] = 'lid' . $like;		
	
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}		  
	
	public static function load_all_functions()
	{
		global $DB;

		$DB->query('SELECT * FROM nv_functions');
		
		$data = $DB->result();
		
		if(empty($data)) $data = array();
		
		return $data;
			
	}
}

?>