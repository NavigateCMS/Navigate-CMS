<?php
class webuser_group
{
    public $id;
    public $website;
    public $name;
    public $description;

    public function load($id)
    {
        global $DB;
        if($DB->query('SELECT * FROM nv_webuser_groups WHERE id = '.intval($id)))
        {
            $data = $DB->result();
            $this->load_from_resultset($data);
        }
    }

    public function load_from_resultset($rs)
    {
        $main = $rs[0];

        $this->id      		= $main->id;
        $this->website      = $main->website;
        $this->name         = $main->name;
        $this->code         = $main->code;
        $this->description  = $main->description;
    }

    public function load_from_post()
    {
        $this->name         = $_REQUEST['name'];
        $this->code         = $_REQUEST['code'];
        $this->description  = $_REQUEST['description'];
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

        if(!empty($this->id))
        {
            $DB->execute(' DELETE FROM nv_webuser_groups
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

        $ok = $DB->execute(' INSERT INTO nv_webuser_groups
								( id, website, name, code, description )
								VALUES
								( 0,
								  '.protect($website->id).',
								  '.protect($this->name).',
								  '.protect($this->code).',
								  '.protect($this->description).'
								)');

        if(!$ok) throw new Exception($DB->get_last_error());

        $this->id = $DB->get_last_id();

        return true;
    }

    public function update()
    {
        global $DB;

        $ok = $DB->execute(' UPDATE nv_webuser_groups
								SET
								  website = '.protect($this->website).',
								  name = '.protect($this->name).',
								  code = '.protect($this->code).',
								  description = '.protect($this->description).'
                 				WHERE id = '.protect($this->id));

        if(!$ok) throw new Exception($DB->get_last_error());

        return true;
    }

    public static function all()
    {
        global $DB;
        global $website;

        $DB->query('SELECT *
                    FROM nv_webuser_groups
                    WHERE website = '.protect($website->id));

        return $DB->result();
    }

    public static function all_in_array()
    {
        global $DB;
        global $website;
        $out = array();

        $DB->query('SELECT *
                    FROM nv_webuser_groups
                    WHERE website = '.protect($website->id));

        $rs = $DB->result();

        foreach($rs as $row)
            $out[$row->id] = $row->name;

        return $out;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT *
                    FROM nv_webuser_groups
                    WHERE website = '.protect($website->id),
                    'object');

        $out = $DB->result();

        return $out;
    }
}
?>