<?php
class order
{
    public $id;
    public $website;
    public $reference;
    public $webuser;

    public $customer_data;
            // ip, guest, name, email, phone, country, region, zipcode, location, address

    public $status; // payment_pending, pending, processing, sent, completed, cancelled, contact us
    public $date_created;
    public $date_updated;
    public $currency;

    public $subtotal_amount;
    public $subtotal_taxes_cost;
    public $subtotal_invoiced;

    public $shipping_method;
    public $shipping_amount;
    public $shipping_tax;
    public $shipping_invoiced;
    public $shipping_data;
            // carrier, reference, tracking_url

    public $shipping_address;
            // name, company, address, location, zipcode, region, country, phone
    public $billing_address;
            // name, company, address, location, zipcode, region, country, phone, email

    public $coupon;
    public $coupon_code;
    public $discount_amount;
    public $discount_percentage;
    public $discount_invoiced;

    public $total;
    public $payment_done;
    public $payment_method;
    public $payment_data;

    public $history;

    public $lines;  // from table nv_orders_lines
            // id, website, order, customer, product, sku, name, option, quantity, currency, original_price, base_price, tax_value, tax_amount, total

    public $notify_customer;    // boolean, not saved in database

    public function load($id)
    {
        global $DB;
        global $website;

        if($DB->query('
            SELECT * FROM nv_orders 
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

        $this->id			        = $main->id;
        $this->website		        = $main->website;
        $this->reference	        = $main->reference;

        $this->webuser	            = $main->webuser;

        $this->customer_data	    = json_decode($main->customer_data);

        $this->status	            = $main->status;
        $this->date_created	        = $main->date_created;
        $this->date_updated	        = $main->date_updated;
        $this->currency	            = $main->currency;

        $this->subtotal_amount	    = $main->subtotal_amount;
        $this->subtotal_taxes_cost	= $main->subtotal_taxes_cost;
        $this->subtotal_invoiced    = $main->subtotal_invoiced;

        $this->shipping_method      = $main->shipping_method;
        $this->shipping_amount      = $main->shipping_amount;
        $this->shipping_tax         = $main->shipping_tax;
        $this->shipping_invoiced    = $main->shipping_invoiced;
        $this->shipping_data        = json_decode($main->shipping_data);

        $this->shipping_address     = json_decode($main->shipping_address);
        $this->billing_address      = json_decode($main->billing_address);

        $this->coupon               = $main->coupon;
        $this->coupon_code          = $main->coupon_code;
        $this->discount_amount      = $main->discount_amount;
        $this->discount_percentage  = $main->discount_percentage;
        $this->discount_invoiced    = $main->discount_invoiced;

        $this->total                = $main->total;
        $this->payment_done         = $main->payment_done;
        $this->payment_method       = $main->payment_method;
        $this->payment_data         = $main->payment_data;

        $this->history              = json_decode($main->history);

        $this->lines                = array();

        if(empty($this->customer_data))
            $this->customer_data = json_decode("{}");

        if(empty($this->shipping_data))
            $this->shipping_data = json_decode("{}");

        if(empty($this->shipping_address))
            $this->shipping_address = json_decode("{}");

        if(empty($this->billing_address))
            $this->billing_address = json_decode("{}");

        if(empty($this->payment_data))
            $this->payment_data = json_decode("{}");

        if(empty($this->history))
            $this->history = json_decode("{}");
    }

    public function load_lines()
    {
        global $DB;

        $DB->query('
            SELECT * 
            FROM nv_orders_lines 
            WHERE `order` = '.protect($this->id).' 
            ORDER BY position ASC'
        );
        $this->lines = $DB->result();
    }

    public function load_from_post()
    {
        // Orders v1, minor changes only

        $this->reference	= trim($_REQUEST['reference']);
        $this->payment_done	= $_REQUEST['payment_done']=='1'? 1 : 0;
        $this->status		= $_REQUEST['status'];
        $this->notify_customer	= $_REQUEST['notify_customer']=='1'? 1 : 0;

        $this->customer_data->name = $_REQUEST['customer-name'];
        $this->customer_data->email = $_REQUEST['customer-email'];
        $this->customer_data->phone = $_REQUEST['customer-phone'];
        $this->customer_data->guest = $_REQUEST['customer-guest']=='1'? 1 : 0;

        $this->webuser = intval($_REQUEST['webuser']);

        // TODO: save new position for order lines
        // TODO: allow order lines changes (modify, add, remove...)

        $this->subtotal_amount = core_string2decimal($_REQUEST['subtotal_amount']);
        $this->subtotal_taxes_cost = core_string2decimal($_REQUEST['subtotal_taxes_cost']);
        $this->subtotal_invoiced = core_string2decimal($_REQUEST['subtotal_invoiced']);

        // TODO: allow modifying coupon discount amount

        $this->shipping_amount = core_string2decimal($_REQUEST['shipping_amount']);
        $this->shipping_tax = core_string2decimal($_REQUEST['shipping_tax']);
        $this->shipping_invoiced = core_string2decimal($_REQUEST['shipping_invoiced']);

        $this->total = core_string2decimal($_REQUEST['total']);

        $this->shipping_data->reference = trim($_REQUEST['shipping_data-reference']);
        $this->shipping_data->tracking_url = trim($_REQUEST['shipping_data-tracking_url']);

        $this->shipping_address->name = trim($_REQUEST['shipping_address-name']);
        $this->shipping_address->company = trim($_REQUEST['shipping_address-company']);
        $this->shipping_address->address = trim($_REQUEST['shipping_address-address']);
        $this->shipping_address->location = trim($_REQUEST['shipping_address-location']);
        $this->shipping_address->zipcode = trim($_REQUEST['shipping_address-zipcode']);
        $this->shipping_address->country = trim($_REQUEST['shipping_address-country']);
        $this->shipping_address->region = trim($_REQUEST['shipping_address-region']);
        $this->shipping_address->phone = trim($_REQUEST['shipping_address-phone']);

        $this->billing_address->name = trim($_REQUEST['billing_address-name']);
        $this->billing_address->company = trim($_REQUEST['billing_address-company']);
        $this->billing_address->address = trim($_REQUEST['billing_address-address']);
        $this->billing_address->location = trim($_REQUEST['billing_address-location']);
        $this->billing_address->zipcode = trim($_REQUEST['billing_address-zipcode']);
        $this->billing_address->country = trim($_REQUEST['billing_address-country']);
        $this->billing_address->region = trim($_REQUEST['billing_address-region']);
        $this->billing_address->phone = trim($_REQUEST['billing_address-phone']);
        $this->billing_address->email = trim($_REQUEST['billing_address-email']);
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

        $affected_rows = 0;

        if(!empty($this->id))
        {
            // remove grid notes
            grid_notes::remove_all('order', $this->id);

            $DB->execute('
				DELETE FROM nv_orders
					  WHERE id = '.intval($this->id).' AND 
					        website = '.$this->website
            );

            $affected_rows = $DB->get_affected_rows();

            if($affected_rows == 1)
            {
                // also remove order lines
                $DB->execute('
				  DELETE FROM nv_orders_lines
				  	    WHERE `order` = '.intval($this->id).' AND 
					          website = '.$this->website
                );
            }
        }

        return $affected_rows;
    }

    public function insert()
    {
        global $DB;
        global $website;

        $DB->execute(' 
 			INSERT INTO nv_orders
				(id, website, reference, webuser, customer_data, date_created, date_updated, currency,
                 subtotal_amount, subtotal_taxes_cost, subtotal_invoiced,
                 shipping_method, shipping_amount, shipping_tax, shipping_invoiced, shipping_data,
                 shipping_address, billing_address,
                 coupon, coupon_code, discount_amount, discount_percentage, discount_invoiced,
                 total, payment_done, payment_method, payment_data,
                 status, history)
			VALUES 
				( 0, :website, :reference, :webuser, :customer_data, :date_created, :date_updated, :currency,
                 :subtotal_amount, :subtotal_taxes_cost, :subtotal_invoiced,
                 :shipping_method, :shipping_amount, :shipping_tax, :shipping_invoiced, :shipping_data,
                 :shipping_address, :billing_address,
                 :coupon, :coupon_code, :discount_amount, :discount_percentage, :discount_invoiced,
                 :total, :payment_done, :payment_method, :payment_data,
                 :status, :history)
			',
            array(
                'website' => value_or_default($this->website, $website->id),
                'reference' => value_or_default($this->reference, ""),
                'webuser' => value_or_default($this->webuser, 0),
                'customer_data' => json_encode($this->customer_data),
                'date_created' => core_time(),
                'date_updated' => 0,
                'currency' => value_or_default($this->currency, ""),
                'subtotal_amount' => value_or_default($this->subtotal_amount, 0),
                'subtotal_taxes_cost' => value_or_default($this->subtotal_taxes_cost, 0),
                'subtotal_invoiced' => value_or_default($this->subtotal_invoiced, 0),
                'shipping_method' => value_or_default($this->shipping_method, ""),
                'shipping_amount' => value_or_default($this->shipping_amount, 0),
                'shipping_tax' => value_or_default($this->shipping_tax, 0),
                'shipping_invoiced' => value_or_default($this->shipping_invoiced, 0),
                'shipping_data' => json_encode($this->shipping_data),
                'shipping_address' => json_encode($this->shipping_address),
                'billing_address' => json_encode($this->billing_address),
                'coupon' => value_or_default($this->coupon, ""),
                'coupon_code' => value_or_default($this->coupon_code, ""),
                'discount_amount' => value_or_default($this->discount_amount, 0),
                'discount_percentage' => value_or_default($this->discount_percentage, 0),
                'discount_invoiced' => value_or_default($this->discount_invoiced, 0),
                'total' => value_or_default($this->total, 0),
                'payment_done' => value_or_default($this->payment_done, 0),
                'payment_method' => value_or_default($this->payment_method, ""),
                'payment_data' => json_encode($this->payment_data),
                'status' => value_or_default($this->status, ""),
                'history' => json_encode($this->history)
            )
        );

        $this->id = $DB->get_last_id();

        return true;
    }

    public function update()
    {
        global $DB;

        $ok = $DB->execute(' 
 			UPDATE nv_orders
			  SET reference = :reference, webuser = :webuser, customer_data = :customer_data, date_updated = :date_updated, currency = :currency,
                  subtotal_amount = :subtotal_amount, subtotal_taxes_cost = :subtotal_taxes_cost, subtotal_invoiced = :subtotal_invoiced,
                  shipping_method = :shipping_method, shipping_amount = :shipping_amount, shipping_tax = :shipping_tax, shipping_invoiced = :shipping_invoiced, 
                  shipping_data = :shipping_data, shipping_address = :shipping_address, billing_address = :billing_address,
                  coupon = :coupon, coupon_code = :coupon_code, discount_amount = :discount_amount, discount_percentage = :discount_percentage, discount_invoiced = :discount_invoiced,
                  total = :total, payment_done = :payment_done, payment_method = :payment_method, payment_data = :payment_data,
                  status = :status, history = :history
			WHERE id = :id	AND	website = :website',
            array(
                'id' => $this->id,
                'website' => $this->website,
                'reference' => $this->reference,
                'webuser' => $this->webuser,
                'customer_data' => json_encode($this->customer_data),
                'date_updated' => core_time(),
                'currency' => $this->currency,
                'subtotal_amount' => value_or_default($this->subtotal_amount, 0),
                'subtotal_taxes_cost' => value_or_default($this->subtotal_taxes_cost, 0),
                'subtotal_invoiced' => value_or_default($this->subtotal_invoiced, 0),
                'shipping_method' => $this->shipping_method,
                'shipping_amount' => value_or_default($this->shipping_amount, 0),
                'shipping_tax' => value_or_default($this->shipping_tax, 0),
                'shipping_invoiced' => value_or_default($this->shipping_invoiced, 0),
                'shipping_data' => json_encode($this->shipping_data),
                'shipping_address' => json_encode($this->shipping_address),
                'billing_address' => json_encode($this->billing_address),
                'coupon' => $this->coupon,
                'coupon_code' => $this->coupon_code,
                'discount_amount' => value_or_default($this->discount_amount, 0),
                'discount_percentage' => value_or_default($this->discount_percentage, 0),
                'discount_invoiced' => value_or_default($this->discount_invoiced, 0),
                'total' => value_or_default($this->total, 0),
                'payment_done' => value_or_default($this->payment_done, 0),
                'payment_method' => $this->payment_method,
                'payment_data' => json_encode($this->payment_data),
                'status' => $this->status,
                'history' => json_encode($this->history)
            )
        );

        if(!$ok)
            throw new Exception($DB->get_last_error());

        if($this->notify_customer)
            $this->send_customer_notification();

        return true;
    }

    public function send_customer_notification()
    {
        global $website;
        global $lang;

        $customer = new webuser();
        $customer->load($this->webuser);

        $email_lang = $website->languages_published[0];
        if(in_array($customer->language, $website->languages_published))
            $email_lang = $customer->language;

        $email_lang = 'es';

        $dictionary = new language();
        if($lang->code == $email_lang)
            $dictionary = $lang; // already loaded!
        else
            $dictionary->load($email_lang);

        $message = navigate_compose_email(
            array(
                array(
                    'title'   => $dictionary->t(177,"Website"),
                    'content' => '<a href="' . $website->absolute_path() . $website->homepage() . '">' . $website->name . '</a>'
                ),
                array(
                    'title'   => $dictionary->t(734, "Order"),
                    'content' => $this->reference . '<br /><small>'.core_ts2date($this->date_created, true).'</small>'
                ),
                array(
                    'title'   => $dictionary->t(68, "Status"),
                    'content' => order::status($this->status, $dictionary) . '<br /><small>'.core_ts2date($this->date_updated).'</small>'
                ),
                array(
                    'footer' => '<a href="' . $website->absolute_path() . $website->homepage() . '" style="text-decoration: none;">&#128712;</a> ' .
                        $dictionary->t(735, "For any complaint or inquiry, please contact us.")
                )
            )
        );

        navigate_send_email(
            $dictionary->t(734, "Order") . ' #' . $this->reference.' — ' . order::status($this->status, $dictionary),
            $message,
            $customer->email,
            NULL,
            false
        );

    }

    public static function status($state=NULL, $dictionary=NULL)
    {
        global $lang;
        if(empty($dictionary))
            $dictionary = $lang;

        $status = array(
            "payment_pending" => $dictionary->t(709, "Payment pending"),
            "pending" => $dictionary->t(710, "Pending"),
            "processing" => $dictionary->t(711, "Processing"),
            "sent" => $dictionary->t(712, "Sent"),
            "completed" => $dictionary->t(713, "Completed"),
            "cancelled" => $dictionary->t(714, "Cancelled"),
            "refunded" => $dictionary->t(723, "Refunded"),
            "contact_us" => $dictionary->t(715, "Contact us")
        );

        if(!empty($state))
            return $status[$state];
        else
            return $status;
    }

    public function quicksearch($text)
    {
        $like = ' LIKE '.protect('%'.$text.'%');

        $cols[] = 'reference '.$like;

        $where = ' AND ( ';
        $where.= implode( ' OR ', $cols);
        $where .= ')';

        return $where;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $DB->query('SELECT * FROM nv_orders WHERE website = '.protect($website->id), 'object');
        $out = $DB->result();

        if($type='json')
            $out = json_encode($out);

        return $out;
    }

}

?>