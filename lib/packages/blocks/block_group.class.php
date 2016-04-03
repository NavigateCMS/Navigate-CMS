<?php
class block_group
{
	public $id;
    public $website;
	public $code;
    public $title;
	public $notes;
    public $blocks; // array

	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_block_groups
						WHERE id = '.intval($id).'
						  AND website = '.$website->id))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}

    public function load_by_code($code)
	{
		global $DB;
		global $website;

		if($DB->query('SELECT * FROM nv_block_groups
						WHERE code = '.protect($code).'
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
		$this->code  			= $main->code;
		$this->title  			= $main->title;
		$this->notes  			= $main->notes;
		$this->blocks			= mb_unserialize($main->blocks);
	}
	
	public function load_from_post()
	{
        $this->code  			= $_REQUEST['code'];
        $this->title  			= $_REQUEST['title'];
        $this->notes 			= $_REQUEST['notes'];
        $this->blocks			= explode(",", $_REQUEST['blocks_group_selection']);
        if(empty($this->blocks))
            $this->blocks = array();
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
			$DB->execute('
				DELETE FROM nv_block_groups
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

        $ok = $DB->execute(
            'INSERT INTO nv_block_groups
                (id, website, code, title, notes, blocks)
                VALUES
                ( 0, :website, :code, :title, :notes, :blocks )
            ',
            array(
                ':website'          =>  value_or_default($this->website, $website->id),
                ':code'             =>  value_or_default($this->code, ''),
                ':title'            =>  value_or_default($this->title, ''),
                ':notes'            =>  value_or_default($this->notes, ''),
                ':blocks'           =>  serialize($this->blocks)
            )
        );

		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();

		return true;
	}
	
	public function update()
	{
		global $DB;
		global $website;

        $ok = $DB->execute(
            'UPDATE nv_block_groups
             SET
                `code`			= :code,
                title           = :title,
                notes	 		= :notes,
                blocks   		= :blocks
             WHERE id = :id
               AND website = :website
            ',
            array(
                ':id'               =>  $this->id,
                ':website'          =>  $this->website,
                ':code'             =>  value_or_default($this->code, ''),
                ':title'            =>  value_or_default($this->title, ''),
                ':notes'            =>  value_or_default($this->notes, ''),
                ':blocks'           =>  serialize($this->blocks)
            )
        );
		
		if(!$ok) throw new Exception($DB->get_last_error());

		return true;
	}

    public static function paginated_list($offset, $limit, $order_by_field, $order_by_ascdesc)
    {
        global $DB;
	    global $website;

        $DB->queryLimit(
            '*',
            'nv_block_groups',
            'website = '.protect($website->id),
            $order_by_field.' '.$order_by_ascdesc,
            $offset,
            $limit
        );
        $rs = $DB->result();
        $total = $DB->foundRows();

        return array($rs, $total);
    }
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');

		// all columns to look for	
		$cols[] = 'b.id' . $like;
		$cols[] = 'b.title' . $like;
		$cols[] = 'b.notes' . $like;		

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

        $DB->query('
            SELECT *
              FROM nv_block_groups
             WHERE website = '.protect($website->id),
            'object'
        );
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

	public static function __set_state(array $obj)
	{
		$tmp = new block_group();
		foreach($obj as $key => $val)
			$tmp->$key = $val;

		return $tmp;
	}
		
}

?>