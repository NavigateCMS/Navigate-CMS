<?php
class coupon
{
    public $id;
    public $website;
    public $code;
    public $date_begin;
    public $date_end;
    public $times_allowed_customer;
    public $times_allowed_globally;
    public $minimum_spend;
    public $type;   //  discount_percentage, discount_amount, free_shipping
    public $discount_value;
    public $currency;

    public $dictionary;

    public function load($id)
    {
        global $DB;
        global $website;

        if($DB->query('
            SELECT * FROM nv_coupons
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

        $this->id			            = $main->id;
        $this->website		            = $main->website;
        $this->code 		            = $main->code;
        $this->date_begin	            = $main->date_begin;
        $this->date_end 	            = $main->date_end;
        $this->times_allowed_customer	= $main->times_allowed_customer;
        $this->times_allowed_globally	= $main->times_allowed_globally;
        $this->minimum_spend            = $main->minimum_spend;
        $this->type      	            = $main->type;
        $this->discount_value 	        = $main->discount_value;
        $this->currency                 = $main->currency;

        $this->dictionary               = webdictionary::load_element_strings("coupon", $this->id);
    }

    public function load_from_post()
    {
        $this->code  		                = trim($_REQUEST['code']);
        $this->type  		                = trim($_REQUEST['type']);
        $this->date_begin	                = (empty($_REQUEST['date_begin'])? '' : core_date2ts($_REQUEST['date_begin']));
        $this->date_end		                = (empty($_REQUEST['date_end'])? '' : core_date2ts($_REQUEST['date_end']));
        $this->times_allowed_customer		= intval($_REQUEST['times_allowed_customer']);
        $this->times_allowed_globally		= intval($_REQUEST['times_allowed_globally']);
        $this->minimum_spend       		    = core_string2decimal($_REQUEST['minimum_spend']);
        $this->currency                     = $_REQUEST['currency'];

        switch($this->type)
        {
            case 'free_shipping':
                $this->discount_value = 0;
                break;

            case 'discount_amount':
                $this->discount_value       		= core_string2decimal($_REQUEST['discount_amount']);
                break;

            case 'discount_percentage':
                $this->discount_value       		= core_string2decimal($_REQUEST['discount_percentage']);
                break;
        }

        // language strings
        $this->dictionary = array();

        $fields = array('name');
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
            grid_notes::remove_all('coupon', $this->id);

            $DB->execute('
				DELETE FROM nv_coupons
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

        $ok = $DB->execute(' 
 			INSERT INTO nv_coupons
				(id, website, code, date_begin, date_end, 
				 times_allowed_customer, times_allowed_globally, minimum_spend,
				 type, discount_value, currency)
			VALUES 
				( 0, :website, :code, :date_begin, :date_end, 
				  :times_allowed_customer, :times_allowed_globally, :minimum_spend,
				  :type, :discount_value, :currency)
			',
            array(
                'website' => value_or_default($this->website, $website->id),
                'code' => $this->code,
                'date_begin' => value_or_default($this->date_begin, 0),
                'date_end' => value_or_default($this->date_end, 0),
                'times_allowed_customer' => value_or_default($this->times_allowed_customer, 0),
                'times_allowed_globally' => value_or_default($this->times_allowed_globally, 0),
                'minimum_spend' => value_or_default($this->minimum_spend, 0),
                'type' => value_or_default($this->type, ""),
                'discount_value' => value_or_default($this->discount_value, 0),
                'currency' => value_or_default($this->currency, "")
            )
        );

        if(!$ok)
            throw new Exception($DB->get_last_error());

        $this->id = $DB->get_last_id();

        webdictionary::save_element_strings('coupon', $this->id, $this->dictionary, $this->website);

        return true;
    }

    public function update()
    {
        global $DB;

        $ok = $DB->execute(' 
 			UPDATE nv_coupons
			  SET code = :code, date_begin = :date_begin, date_end = :date_end,
			      times_allowed_customer = :times_allowed_customer, times_allowed_globally = :times_allowed_globally,
			      minimum_spend = :minimum_spend, type = :type,
			      discount_value = :discount_value, currency = :currency
			WHERE id = :id	AND	website = :website',
            array(
                'id' => $this->id,
                'website' => $this->website,
                'code' => $this->code,
                'date_begin' => value_or_default($this->date_begin, 0),
                'date_end' => value_or_default($this->date_end, 0),
                'times_allowed_customer' => value_or_default($this->times_allowed_customer, 0),
                'times_allowed_globally' => value_or_default($this->times_allowed_globally, 0),
                'minimum_spend' => value_or_default($this->minimum_spend, 0),
                'type' => value_or_default($this->type, ""),
                'discount_value' => value_or_default($this->discount_value, 0),
                'currency' => value_or_default($this->currency, "")
            )
        );

        if(!$ok)
            throw new Exception($DB->get_last_error());

        webdictionary::save_element_strings('coupon', $this->id, $this->dictionary, $this->website);

        return true;
    }

    public function quicksearch($text)
    {
        $like = ' LIKE '.protect('%'.$text.'%');

        $cols[] = 'code '.$like;

        $where = ' AND ( ';
        $where.= implode( ' OR ', $cols);
        $where .= ')';

        return $where;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $DB->query('SELECT * FROM nv_coupons WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

}

?>