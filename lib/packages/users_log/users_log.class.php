<?php
class users_log
{
	public $id;
	public $date;
	public $user;
	public $website;
	public $function;
	public $item;
	public $action;
	public $item_title;
	public $data; // binary blob

	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_users_log
						WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id				= $main->id;
		$this->date				= $main->date;
		$this->user				= $main->user;
		$this->website			= $main->website;
		$this->function			= $main->function;
		$this->item				= $main->item;
		$this->action			= $main->action;
		$this->item_title		= $main->item_title;
		$this->data				= $main->data;	

		// uncompress with gzdecode (function on PHP SVN, yet to be released)
		if(!empty($this->data))
			$this->data = gzdecode($this->data);
		
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

		if(!empty($this->id))
		{			
			$DB->execute('DELETE FROM nv_users_log
								WHERE id = '.intval($this->id));
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
		
		$this->date = core_time();	

		$encoded_data = '';	
		if($action=='save')
			$encoded_data = gzencode($data);    
   		
		// prepared statement
		$ok = $DB->execute(' INSERT INTO nv_users_log
								(id, `date`, user, website, `function`, item, action, item_title, data)
								VALUES 
								( ?, ?, ?, ?, ?, ?, ?, ?, ?	)',
							array(
								0,
								core_time(),
								$user->id,
								$website->id,
								$function,
								$item,
								$action,
								$item_title,
								$encoded_data
							));							
			
		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
				
		return true;
	}
		
	public static function action($function, $item='', $action='', $item_title='', $data='')
	{
		global $DB;
		global $website;
		global $user;
		
		$encoded_data = '';
		if($action=='save')
			$encoded_data = gzencode($data); 
			
		// a blank (new) form requested
		if($action=='load' && empty($item))
			return true;

        $wid = $website->id;
        if(empty($wid))
            $wid = 0;

        $uid = $user->id;
        if(empty($uid))
            $uid = 0;

        if(!is_numeric($function))
        {
            $func = core_load_function($function);
            $function = $func->id;
        }
		
		// prepared statement			
		$ok = $DB->execute(' INSERT INTO nv_users_log
								(id, `date`, user, website, `function`, item, action, item_title, data)
								VALUES 
								( ?, ?, ?, ?, ?, ?, ?, ?, ?	)',
							array(
								0,
								core_time(),
								$uid,
								$wid,
								$function,
								$item,
								$action,
								(string)$item_title,
								$encoded_data
							));		
			
		if(!$ok) throw new Exception($DB->get_last_error());
				
		return true;
	}
	
	public static function recent_items($limit=5)
	{
		global $DB;
		global $user;
		global $website;
		
		$DB->query('SELECT DISTINCT nvul.website, nvul.function, nvul.item, nvul.item_title,
									nvf.lid as function_title, nvf.icon as function_icon							
					FROM nv_users_log nvul, 
						 nv_functions nvf
					WHERE user = '.protect($user->id).'
					  AND nvul.function = nvf.id
					  AND nvul.item > 0
					  AND nvul.action = "load"
					  AND nvul.website = '.protect($website->id).'
					  AND nvul.item_title <> ""
					ORDER BY `date` DESC
					LIMIT '.$limit);

		$rows = $DB->result();
		
		return $rows;		
	}
}

?>