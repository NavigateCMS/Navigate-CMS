<?php

class comment
{
	public $id;
	public $website;
	public $item;
	public $user;
	public $name;
	public $email;
	public $url;
	public $ip;
	public $date_created;
	public $date_modified;
    public $last_modified_by;
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
   		$this->url  	    = $main->url;
   		$this->ip		    = $main->ip;
		$this->date_created	= $main->date_created;
		$this->date_modified= $main->date_modified;		
		$this->last_modified_by  = $main->last_modified_by;
		$this->status		= $main->status;
		$this->message		= html_entity_decode($main->message, ENT_COMPAT, "UTF-8");
	}
	
	public function load_from_post()
	{
		$this->item			= $_REQUEST['comment-item'];
		$this->user			= $_REQUEST['comment-user'];
		$this->name			= $_REQUEST['comment-name'];
		$this->email		= $_REQUEST['comment-email'];
		$this->url		    = $_REQUEST['comment-url'];
		$this->status		= intval($_REQUEST['comment-status']);
		$this->message		= $_REQUEST['comment-message'];
		$this->date_created	= (empty($_REQUEST['comment-date_created'])? '' : core_date2ts($_REQUEST['comment-date_created']));
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

		// remove all old entries
		if(!empty($this->id))
		{
			$DB->execute('
 				DELETE FROM nv_comments
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

		$message = htmlentities($this->message, ENT_COMPAT, 'UTF-8', true);

		$ok = $DB->execute('
 			INSERT INTO nv_comments
				(	id, website, item, user, name, email, url, ip,
					date_created, date_modified, last_modified_by,
					status, message
				)
				VALUES
				( 	0, :website, :item, :user, :name, :email, :url, :ip,
					:date_created, :date_modified, :last_modified_by,
					:status, :message)
			',
			array(
				":website" => $website->id,
				":item" => $this->item,
				":user" => $this->user,
				":name" => $this->name,
				":email" => $this->email,
				":url" => $this->url,
				":ip" => core_ip(),
				":date_created" => core_time(),
				":date_modified" => 0,
				":last_modified_by" => 0,
				":status" => $this->status,
				":message" => $message
			)
		);

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
        global $user;

		$message = htmlentities($this->message, ENT_COMPAT, 'UTF-8', true);

		$ok = $DB->execute('
 			UPDATE nv_comments
 			SET
 			  item = :item,
              user = :user,
              name = :name,
              email = :email,
              url = :url,
              date_created = :date_created,
              date_modified = :date_modified,
              last_modified_by = :last_modified_by,
              status = :status,
              message = :message
            WHERE id = :id
			',
			array(
				":item" => $this->item,
				":user" => $this->user,
				":name" => $this->name,
				":email" => $this->email,
				":url" => $this->url,
				":date_created" => $this->date_created,
				":date_modified" => core_time(),
				":last_modified_by" => $user->id,
				":status" => $this->status,
				":message" => $message,
				":id" => $this->id
			)
		);
		
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

    public static function remove_spam()
    {
        global $DB;
        global $website;

        $count = $DB->query_single(
	        'count(*) as total',
	        'nv_comments',
	        'website = '.protect($website->id).' AND status = 3'
        );

        $ok = $DB->execute('
			DELETE FROM nv_comments
             WHERE website = '.protect($website->id).'
               AND status = 3'
        );

        if($ok)
            return $count;
    }
}

?>