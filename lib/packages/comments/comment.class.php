<?php

class comment
{
	public $id;
	public $website;
	public $item;
	public $user;
	public $name;
	public $email;
	public $ip;	
	public $date_created;
	public $date_modified;
	public $status; //  -1 => To review  0 => Published  1 => Private    2 => Hidden   3 => Spam
	public $message;

    public function load($id)
	{
		global $DB;
		if($DB->query('SELECT * FROM nv_comments WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];	
		
		$this->id      		= $main->id;		
		$this->website      = $main->website;
		$this->item			= $main->item;
		$this->user			= $main->user;
   		$this->name		    = $main->name;    
   		$this->email	    = $main->email;    
   		$this->ip		    = $main->ip;    		
		$this->date_created	= $main->date_created;
		$this->date_modified= $main->date_modified;		
		$this->status		= $main->status;
		$this->message		= $main->message;	
	}
	
	public function load_from_post()
	{		
		$this->item			= $_REQUEST['comment-item'];
		$this->user			= $_REQUEST['comment-user'];
		$this->name			= $_REQUEST['comment-name'];
		$this->email		= $_REQUEST['comment-email'];
		$this->status		= intval($_REQUEST['comment-status']);
		$this->message		= $_REQUEST['comment-message'];		
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
			$DB->execute(' DELETE FROM nv_comments
							WHERE id = '.intval($this->id).'
              				LIMIT 1 '
						);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;	
		global $website;
	
		$ok = $DB->execute(' INSERT INTO nv_comments
								(id, website, item, user, name, email, ip, date_created, date_modified, status, message)
								VALUES 
								( 0,
								  '.protect($website->id).',
								  '.protect($this->item).',
								  '.protect($this->user).',
								  '.protect($this->name).',
								  '.protect($this->email).',								  
								  '.protect(core_ip()).',								  
								  '.protect(core_time()).',								  
								  '.protect(0).',
								  '.protect($this->status).',
								  '.protect($this->message).'  
								)');		
				
		if(!$ok)
            throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		$this->ip = core_ip();
		$this->date_created = core_time();

		return true;
	}	
	
	public function update()
	{
		global $DB;
	    
		$ok = $DB->execute(' UPDATE nv_comments
								SET
								  item = '.protect($this->item).',
								  user = '.protect($this->user).',
								  name = '.protect($this->name).',								  
								  email = '.protect($this->email).',
								  date_modified = '.protect(core_time()).',
								  status = '.protect($this->status).', 
								  message = '.protect($this->message).'
                 				WHERE id = '.protect($this->id));
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		return true;
	}
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'id' . $like;
		$cols[] = 'message' . $like;
		$cols[] = 'name' . $like;
		$cols[] = 'email' . $like;

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

        $DB->query('SELECT * FROM nv_comments WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

    public static function pending_count()
    {
        global $DB;
        global $website;

        $pending_comments = $DB->query_single(
            'COUNT(*)',
            'nv_comments',
            ' website = '.protect($website->id).' AND status = -1'
        );

        return $pending_comments;
    }
}

?>