<?php
class payment_method
{
    public $id;
    public $website;
    public $codename;
    public $extension;
    public $image;
    public $permission;
    
    public $dictionary;

    public function load($id)
    {
        global $DB;
        global $website;

        if($DB->query('
            SELECT * FROM nv_payment_methods 
            WHERE id = '.intval($id).' AND 
                  website = '.$website->id)
        )
        {
            $data = $DB->result();
            $this->load_from_resultset($data);
        }
    }

    public function load_from_resultset($rs)
    {
        $main = $rs[0];

        $this->id			= $main->id;
        $this->website		= $main->website;
        $this->codename		= $main->codename;
        $this->extension    = $main->extension;
        $this->image		= $main->image;
        $this->permission	= $main->permission;
        $this->dictionary	= webdictionary::load_element_strings('payment_method', $this->id);
    }

    public function load_from_post()
    {
        $this->codename  	= $_REQUEST['codename'];
        $this->extension  	= $_REQUEST['extension'];
        $this->permission	= $_REQUEST['permission'];
        $this->image		= intval($_REQUEST['image']);

        $this->dictionary   = array();
        $fields = array('title', 'description');
        foreach($_REQUEST as $key => $value)
        {
            if(empty($value)) continue;

            foreach($fields as $field)
            {
                if(substr($key, 0, strlen($field.'-'))==$field.'-')
                    $this->dictionary[substr($key, strlen($field.'-'))][$field] = $value;
            }
        }
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
            // remove grid notes
            grid_notes::remove_all('payment_method', $this->id);

            // remove dictionary strings
            webdictionary::save_element_strings('payment_method', $this->id, array(), $this->website);

            $DB->execute('
				DELETE FROM nv_payment_methods
					  WHERE id = '.intval($this->id).' AND 
					        website = '.$website->id
            );
        }

        return $DB->get_affected_rows();
    }

    public function insert()
    {
        global $DB;
        global $website;

        $DB->execute(' 
 			INSERT INTO nv_payment_methods
				(id, website, codename, extension, image, permission)
			VALUES 
				( 0, :website, :codename, :extension, :image, :permission)
			',
            array(
                'website' => value_or_default($this->website, $website->id),
                'codename' => value_or_default($this->codename, ""),
                'extension' => value_or_default($this->extension, ""),
                'image' => value_or_default($this->image, 0),
                'permission' => value_or_default($this->permission, 0)
            )
        );

        $this->id = $DB->get_last_id();

        webdictionary::save_element_strings('payment_method', $this->id, $this->dictionary, $this->website);

        return true;
    }

    public function update()
    {
        global $DB;

        $ok = $DB->execute(' 
 			UPDATE nv_payment_methods
			  SET codename = :codename, extension = :extension, image = :image, permission = :permission
			WHERE id = :id	AND	website = :website',
            array(
                'id' => $this->id,
                'website' => $this->website,
                'codename' => value_or_default($this->codename, ""),
                'extension' => value_or_default($this->extension, ""),
                'image' => value_or_default($this->image, 0),
                'permission' => value_or_default($this->permission, 0)
            )
        );

        if(!$ok)
            throw new Exception($DB->get_last_error());

        webdictionary::save_element_strings('payment_method', $this->id, $this->dictionary, $this->website);

        return true;
    }

    public static function get_available($ws=NULL)
    {
        global $website;
        global $DB;

        if(empty($ws))
            $ws = $website->id;

        $DB->query('
            SELECT *
              FROM nv_payment_methods
             WHERE website = '.$ws.' 
        ');

        $rs = $DB->result();

        $payment_methods = array();
        foreach($rs as $row)
        {
            $sm = new payment_method();
            $sm->load_from_resultset(array($row));
            if(nvweb_object_enabled($sm))
            {
                $payment_methods[] = $sm;
            }
        }

        return $payment_methods;
    }


    public function quicksearch($text)
    {
        global $DB;
        global $website;

        $like = ' LIKE '.protect('%'.$text.'%');

        // we search for the IDs at the dictionary NOW (to avoid inefficient requests)

        $DB->query('
            SELECT DISTINCT (nvw.node_id)
              FROM nv_webdictionary nvw
             WHERE nvw.node_type = "payment_method" AND
                   nvw.text '.$like.' AND
                   nvw.website = '.$website->id,
            'array'
        );

        $dict_ids = $DB->result("node_id");

        // all columns to look for
        $cols[] = 'pm.id' . $like;

        if(!empty($dict_ids))
            $cols[] = 'pm.id IN ('.implode(',', $dict_ids).')';

        $where = ' AND ( ';
        $where.= implode( ' OR ', $cols);
        $where .= ')';

        return $where;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $DB->query('SELECT * FROM nv_payment_methods WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

}

?>