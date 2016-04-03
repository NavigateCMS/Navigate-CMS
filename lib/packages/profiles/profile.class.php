<?php

class profile
{
	public $id;
	public $name;
    public $description;

	public $menus;

	public function load($id)
	{
		global $DB;
		if($DB->query('SELECT * FROM nv_profiles WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		global $DB;
		
		$main = $rs[0];
		
		$this->id      		= $main->id;
		$this->name		    = $main->name;
		$this->description  = $main->description;
		$this->menus		= json_decode($main->menus);
		if(empty($this->menus))	$this->menus = array();
	}
	
	public function load_from_post()
	{
		$this->name			= $_REQUEST['name'];
		$this->description  = $_REQUEST['description'];

		// carregar menús associats		
		$menus = explode('#', $_REQUEST['profile-menu']);
		$this->menus = array();
		foreach($menus as $menu)
		{
			if(!empty($menu))
				$this->menus[] = $menu;
		}
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

		if(!empty($this->id))
		{
			$DB->execute('
			    DELETE FROM nv_profiles
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
		    INSERT INTO nv_profiles
				(id, name, description, menus)
			VALUES
            	( 0, :name, :description, :menus )
            ',
			array(
				'name' => value_or_default($this->name, ""),
				'description' => value_or_default($this->description, ""),
				'menus' => json_encode($this->menus)
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
 			UPDATE nv_profiles
			   SET name = :name, description = :description, menus = :menus
			 WHERE id = :id',
			array(
				'name' => value_or_default($this->name, ""),
				'description' => value_or_default($this->description, ""),
				'menus' => json_encode($this->menus),
				'id' => $this->id
			)
		);

		if(!$ok)
			throw new Exception($DB->get_last_error());

		return true;
	}
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'id' . $like;
		$cols[] = 'name' . $like;
	
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}		  
	
	
	public static function profile_names()
	{
		global $DB;
		
		$DB->query('SELECT id, name FROM nv_profiles');
		$rs = $DB->result();
		$profiles = array();
		foreach($rs as $row)
			$profiles[$row->id] = $row->name;	
			
		return $profiles;
	}

    public function backup($type='json')
    {
        global $DB;

        $out = array();

        $DB->query('SELECT * FROM nv_profiles', 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }
}

?>