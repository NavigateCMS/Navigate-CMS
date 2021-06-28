<?php

class order
{
    public $id;
    public $website;
    public $reference;
    public $webuser;

    public $customer_data;
            // ip, guest, name, email, phone, country, region, zipcode, location, address
    public $customer_notes;

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
    public $shipping_tax_amount;
    public $shipping_invoiced;
    public $shipping_data;
            // carrier, reference, tracking_url

    public $shipping_address;
            // object: name, nin, company, address, location, zipcode, region, country, phone, email
    public $billing_address;
            // object: name, nin, company, address, location, zipcode, region, country, phone, email

    public $coupon;
    public $coupon_code;
    public $coupon_data; // backup of the original coupon definition
    public $coupon_amount;

    public $total;
    public $payment_done;
    public $payment_method;
    public $payment_data;

    public $history; // (json) array[] = array(time(), 'event_codename', object or array);

    public $lines;  // from table nv_orders_lines
            // id, website, order, customer, product, sku, name, option, quantity, currency,
            // original_price, coupon_unit, base_price, base_price_tax_amount, tax_value, coupon_amount, tax_amount, total

    public $weight;
    public $weight_unit;
    public $size_unit;

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
        $this->customer_notes       = $main->customer_notes;

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
        $this->shipping_tax_amount  = $main->shipping_tax_amount;
        $this->shipping_invoiced    = $main->shipping_invoiced;
        $this->shipping_data        = json_decode($main->shipping_data);

        $this->shipping_address     = json_decode($main->shipping_address);
        $this->billing_address      = json_decode($main->billing_address);

        $this->coupon               = $main->coupon;
        $this->coupon_code          = $main->coupon_code;
        $this->coupon_data          = $main->coupon_data;
        $this->coupon_amount        = $main->coupon_amount;

        $this->total                = $main->total;
        $this->payment_done         = $main->payment_done;
        $this->payment_method       = $main->payment_method;
        $this->payment_data         = $main->payment_data;

        $this->weight               = $main->weight;
        $this->weight_unit          = $main->weight_unit;
        $this->size_unit            = $main->size_unit;

        $this->history              = json_decode($main->history);

        $this->lines                = array();

        if(empty($this->customer_data))
        {
            $this->customer_data = json_decode("{}");
        }

        if(empty($this->shipping_data))
        {
            $this->shipping_data = json_decode("{}");
        }

        if(empty($this->shipping_address))
        {
            $this->shipping_address = json_decode("{}");
        }

        if(empty($this->billing_address))
        {
            $this->billing_address = json_decode("{}");
        }

        if(empty($this->payment_data))
        {
            $this->payment_data = json_decode("{}");
        }

        if(empty($this->history))
        {
            $this->history = json_decode("{}");
        }
    }

    public function load_lines()
    {
        global $DB;

        $DB->query('
            SELECT * 
            FROM nv_orders_lines 
            WHERE `order` = '.intval($this->id).' 
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

        $this->customer_notes = $_REQUEST['customer-notes'];

        $this->webuser = intval($_REQUEST['webuser']);

        // TODO: save new position for order lines
        // TODO: allow order lines changes (modify, add, remove...)

        $this->subtotal_amount = core_string2decimal($_REQUEST['subtotal_amount']);
        $this->subtotal_taxes_cost = core_string2decimal($_REQUEST['subtotal_taxes_cost']);
        $this->subtotal_invoiced = core_string2decimal($_REQUEST['subtotal_invoiced']);

        // not editable after creating
        // $this->weight = $_REQUEST['weight'];
        // $this->weight_unit = $_REQUEST['weight_unit'];
        // $this->size_unit = $_REQUEST['size_unit'];

        // TODO: allow modifying coupon amount

        $this->shipping_amount = core_string2decimal($_REQUEST['shipping_amount']);
        $this->shipping_tax = core_string2decimal($_REQUEST['shipping_tax']);
        $this->shipping_tax_amount = core_string2decimal($_REQUEST['shipping_tax_amount']);
        $this->shipping_invoiced = core_string2decimal($_REQUEST['shipping_invoiced']);

        $this->total = core_string2decimal($_REQUEST['total']);

        $this->shipping_data->reference = trim($_REQUEST['shipping_data-reference']);
        $this->shipping_data->tracking_url = trim($_REQUEST['shipping_data-tracking_url']);

        $this->shipping_address->name = trim($_REQUEST['shipping_address-name']);
        $this->shipping_address->nin = trim($_REQUEST['shipping_address-nin']);
        $this->shipping_address->company = trim($_REQUEST['shipping_address-company']);
        $this->shipping_address->address = trim($_REQUEST['shipping_address-address']);
        $this->shipping_address->location = trim($_REQUEST['shipping_address-location']);
        $this->shipping_address->zipcode = trim($_REQUEST['shipping_address-zipcode']);
        $this->shipping_address->country = trim($_REQUEST['shipping_address-country']);
        $this->shipping_address->region = trim($_REQUEST['shipping_address-region']);
        $this->shipping_address->phone = trim($_REQUEST['shipping_address-phone']);
        //$this->shipping_address->email = trim($_REQUEST['shipping_address-email']);

        $this->billing_address->name = trim($_REQUEST['billing_address-name']);
        $this->billing_address->nin = trim($_REQUEST['billing_address-nin']);
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
        {
            return $this->update();
        }
        else
        {
            return $this->insert();
        }
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

        $ok = $DB->execute(' 
 			INSERT INTO nv_orders
				(id, website, reference, webuser, customer_data, customer_notes, date_created, date_updated, currency,
                 subtotal_amount, subtotal_taxes_cost, subtotal_invoiced,
                 weight, weight_unit, size_unit,
                 shipping_method, shipping_amount, shipping_tax, shipping_tax_amount, shipping_invoiced, shipping_data,
                 shipping_address, billing_address,
                 coupon, coupon_code, coupon_amount, coupon_data,
                 total, payment_done, payment_method, payment_data,
                 status, history)
			VALUES 
				( 0, :website, :reference, :webuser, :customer_data, :customer_notes, :date_created, :date_updated, :currency,
                 :subtotal_amount, :subtotal_taxes_cost, :subtotal_invoiced,
                 :weight, :weight_unit, :size_unit,
                 :shipping_method, :shipping_amount, :shipping_tax, :shipping_tax_amount, :shipping_invoiced, :shipping_data,
                 :shipping_address, :billing_address,
                 :coupon, :coupon_code, :coupon_amount, :coupon_data,
                 :total, :payment_done, :payment_method, :payment_data,
                 :status, :history)
			',
            array(
                'website' => value_or_default($this->website, $website->id),
                'reference' => value_or_default($this->reference, ""),
                'webuser' => value_or_default($this->webuser, 0),
                'customer_data' => json_encode($this->customer_data),
                'customer_notes' => $this->customer_notes,
                'date_created' => core_time(),
                'date_updated' => core_time(),
                'currency' => value_or_default($this->currency, ""),
                'subtotal_amount' => value_or_default($this->subtotal_amount, 0),
                'subtotal_taxes_cost' => value_or_default($this->subtotal_taxes_cost, 0),
                'subtotal_invoiced' => value_or_default($this->subtotal_invoiced, 0),
                'weight' => value_or_default($this->weight, 0),
                'weight_unit' => value_or_default($this->weight_unit, ''),
                'size_unit' => value_or_default($this->size_unit, ''),
                'shipping_method' => value_or_default($this->shipping_method, ""),
                'shipping_amount' => value_or_default($this->shipping_amount, 0),
                'shipping_tax' => value_or_default($this->shipping_tax, 0),
                'shipping_tax_amount' => value_or_default($this->shipping_tax_amount, 0),
                'shipping_invoiced' => value_or_default($this->shipping_invoiced, 0),
                'shipping_data' => json_encode($this->shipping_data),
                'shipping_address' => json_encode($this->shipping_address),
                'billing_address' => json_encode($this->billing_address),
                'coupon' => value_or_default($this->coupon, 0),
                'coupon_code' => value_or_default($this->coupon_code, ""),
                'coupon_amount' => value_or_default($this->coupon_amount, 0),
                'coupon_data' => value_or_default($this->coupon_data, ""),
                'total' => value_or_default($this->total, 0),
                'payment_done' => value_or_default($this->payment_done, 0),
                'payment_method' => value_or_default($this->payment_method, ""),
                'payment_data' => json_encode($this->payment_data),
                'status' => value_or_default($this->status, ""),
                'history' => json_encode($this->history)
            )
        );

        $this->id = $DB->get_last_id();

        if(!$ok)
        {
            throw new Exception($DB->get_last_error());
        }

        // now we insert each order line
        for($l=0; $l < count($this->lines); $l++)
        {
            $ok = $DB->execute('
                INSERT INTO nv_orders_lines
                  (id, website, `order`, position, customer, 
                   product, sku, name, `option`, quantity, currency, 
                   original_price, coupon_unit, base_price, base_price_tax_amount, 
                   coupon_amount, tax_value, tax_amount, total)
                VALUES
                  ( 0, :website, :order, :position, :customer, 
                    :product, :sku, :name, :option, :quantity, :currency, 
                    :original_price, :coupon_unit, :base_price, :base_price_tax_amount, 
                    :coupon_amount, :tax_value, :tax_amount, :total
                )
            ', array(
                'website' => value_or_default($this->website, $website->id),
                'customer' => value_or_default($this->webuser, ""),
                'order' => value_or_default($this->id, 0),
                'position' => ($l+1),
                'product' => $this->lines[$l]['product'],
                'sku' => $this->lines[$l]['sku'],
                'name' => $this->lines[$l]['name'],
                'option' => $this->lines[$l]['option'],
                'quantity' => value_or_default($this->lines[$l]['quantity'], 0),
                'currency' => $this->lines[$l]['currency'],
                'original_price' => value_or_default($this->lines[$l]['original_price'], 0),
                'coupon_unit' => value_or_default($this->lines[$l]['coupon_unit'], 0),
                'base_price' => value_or_default($this->lines[$l]['base_price'], 0),
                'base_price_tax_amount' => value_or_default($this->lines[$l]['base_price_tax_amount'], 0),
                'coupon_amount' => value_or_default($this->lines[$l]['coupon_amount'], 0),
                'tax_value' => value_or_default($this->lines[$l]['tax_value'], 0),
                'tax_amount' => value_or_default($this->lines[$l]['tax_amount'], 0),
                'total' => value_or_default($this->lines[$l]['total'], 0)
            ));


            if(!$ok)
            {
                throw new Exception($DB->get_last_error());
            }
        }

        return true;
    }

    public function update()
    {
        global $DB;

        $ok = $DB->execute(' 
 			UPDATE nv_orders
			  SET reference = :reference, webuser = :webuser, customer_data = :customer_data, customer_notes = :customer_notes, 
			      date_updated = :date_updated, currency = :currency,
                  subtotal_amount = :subtotal_amount, subtotal_taxes_cost = :subtotal_taxes_cost, subtotal_invoiced = :subtotal_invoiced,
                  weight = :weight, weight_unit = :weight_unit, size_unit = :size_unit,
                  shipping_method = :shipping_method, shipping_amount = :shipping_amount, 
			      shipping_tax = :shipping_tax, shipping_tax_amount = :shipping_tax_amount, shipping_invoiced = :shipping_invoiced, 
                  shipping_data = :shipping_data, shipping_address = :shipping_address, billing_address = :billing_address,
                  coupon = :coupon, coupon_code = :coupon_code, coupon_amount = :coupon_amount, coupon_data = :coupon_data,
                  total = :total, payment_done = :payment_done, payment_method = :payment_method, payment_data = :payment_data,
                  status = :status, history = :history
			WHERE id = :id	AND	website = :website',
            array(
                'id' => $this->id,
                'website' => $this->website,
                'reference' => $this->reference,
                'webuser' => $this->webuser,
                'customer_data' => json_encode($this->customer_data),
                'customer_notes' => $this->customer_notes,
                'date_updated' => core_time(),
                'currency' => $this->currency,
                'subtotal_amount' => value_or_default($this->subtotal_amount, 0),
                'subtotal_taxes_cost' => value_or_default($this->subtotal_taxes_cost, 0),
                'subtotal_invoiced' => value_or_default($this->subtotal_invoiced, 0),
                'weight' => value_or_default($this->weight, 0),
                'weight_unit' => value_or_default($this->weight_unit, ''),
                'size_unit' => value_or_default($this->size_unit, ''),
                'shipping_method' => $this->shipping_method,
                'shipping_amount' => value_or_default($this->shipping_amount, 0),
                'shipping_tax' => value_or_default($this->shipping_tax, 0),
                'shipping_tax_amount' => value_or_default($this->shipping_tax_amount, 0),
                'shipping_invoiced' => value_or_default($this->shipping_invoiced, 0),
                'shipping_data' => json_encode($this->shipping_data),
                'shipping_address' => json_encode($this->shipping_address),
                'billing_address' => json_encode($this->billing_address),
                'coupon' => value_or_default($this->coupon, 0),
                'coupon_code' => value_or_default($this->coupon_code, ""),
                'coupon_amount' => value_or_default($this->coupon_amount, 0),
                'coupon_data' => value_or_default($this->coupon_data, ""),
                'total' => value_or_default($this->total, 0),
                'payment_done' => value_or_default($this->payment_done, 0),
                'payment_method' => $this->payment_method,
                'payment_data' => json_encode($this->payment_data),
                'status' => $this->status,
                'history' => json_encode($this->history)
            )
        );

        if(!$ok)
        {
            throw new Exception($DB->get_last_error());
        }

        // TODO: do we need to also update the order lines?

        if($this->notify_customer)
        {
            $private_comment_to_customer = trim($_REQUEST['notify_customer_comment']);
            $this->send_customer_notification($private_comment_to_customer);
            $this->history[] = array(
                time(),
                'notify_customer',
                array(
                    'status' => $this->status,
                    'comment' => $private_comment_to_customer
                )
            );
            $this->update_history();
        }

        return true;
    }

    public function update_history()
    {
        global $DB;

        $ok = $DB->execute(' 
 			UPDATE nv_orders
			  SET history = :history
			WHERE id = :id	AND	website = :website',
            array(
                'id' => $this->id,
                'website' => $this->website,
                'history' => json_encode($this->history)
            )
        );

        if(!$ok)
        {
            throw new Exception($DB->get_last_error());
        }

        return true;
    }

    public static function create_from_cart($cart)
    {
        global $website;
        global $webuser;

        $order = new Order();

        $order->id = 0;
        $order->website = $website->id;
        $order->webuser = $cart['customer'];

        $order->customer_data = array(
            'ip' => core_ip(),
            'guest' => ($cart['customer'] == 'guest'),
            'name' => @$webuser->fullname,
            'email' => @$webuser->email,
            'phone' => @$webuser->phone,
            'country' => @$webuser->country,
            'region' => @$webuser->region,
            'zipcode' => @$webuser->zipcode,
            'location' => @$webuser->location,
            'address' => @$webuser->address
        );

        $order->customer_notes = $cart['customer_notes'];

        $order->status = 'payment_pending'; // [payment_pending, pending, processing, sent, completed, cancelled, contact us]
        $order->date_created = time();
        $order->date_updated = time();
        $order->currency = $cart['currency'];

        $order->subtotal_amount = $cart['subtotal_without_taxes']; // after discounts, WITHOUT TAXES
        $order->subtotal_taxes_cost = $cart['subtotal_taxes_amount']; // after discounts, before shipping
        $order->subtotal_invoiced = $cart['subtotal']; // after discounts, WITH taxes, before shipping

        $order->shipping_method = $cart['shipping_method'].'/'.$cart['shipping_rate'];
        $order->shipping_amount = $cart['shipping_price_without_taxes'];
        $order->shipping_tax = $cart['shipping_tax_value'];
        $order->shipping_tax_amount = $cart['shipping_tax_amount'];
        $order->shipping_invoiced = $cart['shipping_price'];
        $order->shipping_data = array(
            'method' => $cart['shipping_method_data'], // backup of the shipping method definition
            'carrier' => $cart['shipping_carrier'],
            'reference' => '',
            'tracking_url' => ''
        );

        $order->shipping_address = (object) $cart['address_shipping'];  // (is array)
        // name, nin, company, address, location, zipcode, region, country, phone, email
        $order->billing_address = (object) $cart['address_billing'];    // (is array)
        // name, nin, company, address, location, zipcode, region, country, phone, email

        $order->coupon = $cart['coupon'];
        $order->coupon_code = $cart['coupon_code'];
        $order->coupon_data = $cart['coupon_data'];
        $order->coupon_amount = value_or_default($cart['coupon_amount'], 0);

        $order->total = $cart['total'];

        $order->payment_done = 0;
        $order->payment_method = $cart['payment_method'];
        $order->payment_data = '';

        if($order->total == 0)
        {
            $order->payment_done = 1;
            $order->payment_method = 0;
            $order->payment_data = '';
        }

        $order->history = array();
        $order->history[] = array(time(), 'order_created_from_cart', $cart);

        $order->lines = array();
        foreach($cart['lines'] as $line)
        {
            $order->lines[] = array(
                'id' => 0,
                'website' => $website->id,
                'order' => 0,
                'customer' => $cart['customer'],
                'product' => $line['id'],
                'sku' => $line['sku'],
                'name' => $line['name'],
                'option' => $line['option'],
                'quantity' => $line['quantity'],
                'currency' => $line['currency'],
                'original_price' => $line['price'],
                'coupon_unit' => $line['coupon_unit'],
                'base_price' => $line['base_price'],
                'base_price_tax_amount' => $line['base_price_tax_amount'],
                'tax_value' => $line['tax_value'],
                'coupon_amount' => $line['coupon_amount'],
                'tax_amount' => $line['subtotal_tax_amount'],
                'total' => $line['subtotal']
            );
        }

        $order->weight = $cart['weight'];
        $order->weight_unit = $cart['weight_unit'];
        $order->size_unit = $cart['size_unit'];

        $order->notify_customer = false;    // boolean, not saved in database

        $order->reference = order::create_reference($order);

        return $order;
    }

    public static function create_reference($order)
    {
        // TODO: generate a reference using rules provided

        // timestamp method
        $reference = strftime("%Y%m%d%H%M%S").$order->webuser;

        return $reference;
    }

    public static function get_addresses($webuser)
    {
        global $website;
        global $DB;

        $addresses = array();
        $hashes = array();

        $from_time = time() - (4 * 365 * 24 * 3600); // ignore addresses used 4 years ago

        $DB->query('
            SELECT shipping_address, billing_address 
              FROM nv_orders
             WHERE website = '.$website->id.' 
               AND webuser = '.intval($webuser).'
               AND date_created > '.$from_time.'
          ORDER BY id DESC
        ');

        $rs = $DB->result();

        // remove duplicated and clean empty addresses
        foreach($rs as $order)
        {
            $shipping_a = json_decode($order->shipping_address, true);
            $billing_a = json_decode($order->billing_address);

            $prehash = implode("", (array)$shipping_a);
            if(!empty($prehash))
            {
                $hash = md5($prehash);
                if(!in_array(md5($prehash), $hashes))
                {
                    $addresses[] = $shipping_a;
                    $hashes[] = $hash;
                }
            }

            $prehash = implode("", (array)$billing_a);
            if(!empty($prehash))
            {
                $hash = md5($prehash);
                if(!in_array(md5($prehash), $hashes))
                {
                    $addresses[] = $billing_a;
                    $hashes = $hash;
                }
            }
        }

        return $addresses;
    }

    public function generate_pdf($download=false)
    {
        global $website;
        global $lang;

        $out = false;
        $currencies = product::currencies();
        $states = order::status();

        $customer = new webuser();
        $customer->load($this->webuser);

        $pdf_lang = $website->languages_published[0];
        if(in_array($customer->language, $website->languages_published))
        {
            $pdf_lang = $customer->language;
        }

        $dictionary = new language();
        if($lang->code == $pdf_lang)
        {
            // already loaded!
            $dictionary = $lang;
        }
        else
        {
            $dictionary->load($pdf_lang);
        }

        $shipping_region_name = "";
        if(!empty($this->shipping_address->region))
        {
            $shipping_region_name = property::country_region_name_by_code($this->shipping_address->region);
            $shipping_region_name.= ', ';
        }
        $shipping_country_name = property::country_name_by_code($this->shipping_address->country);     

        if(@empty($this->billing_address->name))
        {
            // make a copy of the shipping_address array
            //$this->billing_address = array_merge($this->shipping_address, array());
            $this->billing_address = clone $this->shipping_address;
        }

        $billing_region_name = "";
        if(!empty($this->billing_address->region))
        {
            $billing_region_name = property::country_region_name_by_code($this->billing_address->region);
            $billing_region_name.= ', ';
        }
        $billing_country_name = property::country_name_by_code($this->billing_address->country);

        $shipping_method = new shipping_method();
        list($shipping_method_id, $shipping_method_rate) = explode("/", $this->shipping_method);
        $shipping_method->load($shipping_method_id);


        $html = '
            <style>
                td
                {
                    text-align: left;
                    vertical-align: top;
                    font-size: 11px;                    
                }
                
                th
                {
                    font-size: 11px;
                    text-align: left;
                    font-weight: bold;
                }  
                
                .logo
                {
                    max-width: 400px;
                    max-height: 100px;
                }    
                
                .text-small
                {
                    font-size: 11px;
                }
                
                h4
                {
                    font-size: 11px;
                    font-weight: bold;
                    margin-bottom: 0;
                }
                
                hr
                {
                    height: 0px;
                    margin: 8px;                            
                    padding: 0;
                    color: #cccccc; 
                }
                
                #order_lines
                {
                    width: 100%;
                }
                
                .footer
                {
                    text-align: center;
                }
            </style>
        ';

        $logo = '<h2>'.$website->name.'</h2>';
        if(!empty($website->shop_logo))
        {
            $logo = '<img class="logo" src="'.file::file_url($website->shop_logo).'" align="top" />';
        }

        $html .= '
            <table width="100%">
                <tr>
                    <td>
                        '.$logo.'
                    </td>
                    <td colspan="2" style="text-align: right;">
                        <br />
                        <p><strong>'.$dictionary->t(734, "Order").'</strong> #'.$this->reference.'</p>
                        <br />
                        <p><strong>'.$dictionary->t(86, "Date").'</strong> '.core_ts2date($this->date_created).'</p>                    
                    </td>                
                </tr>
                <tr>
                    <td colspan="3"><br /></td>
                </tr>
                <tr>
                    <td width="33%">
                        <h4>'.$dictionary->t(816, "Shop information").'</h4>
                        <hr />
                        <p class="text-small">
                            '.nl2br($website->shop_address[$pdf_lang]).'
                        </p>
                    </td>        
                    <td width="33%">
                        <h4>'.$dictionary->t(716, "Shipping address").'</h4>
                        <hr />
                        <p class="text-small">
                            '.$this->shipping_address->name.'<br />
                            '.(!empty($this->shipping_address->company)? '[ '.$this->shipping_address->company.' ]<br />' : '' ).'
                            '.$this->shipping_address->address.'<br />
                            '.$this->shipping_address->zipcode.' '.$this->shipping_address->location.'<br />
                            '.$shipping_region_name.$shipping_country_name.'<br />
                            <br />
                            '.$this->shipping_address->email.'<br />
                            '.$this->shipping_address->phone.'
                        </p>                        
                    </td>                
                    <td width="33%">
                        <h4>'.$dictionary->t(717, "Billing address").'</h4>
                        <hr />
                        <p class="text-small">
                            '.$this->billing_address->name.'<br />
                            '.(!empty($this->billing_address->company)? '[ '.$this->billing_address->company.' ]<br />' : '' ).'
                            '.$this->billing_address->address.'<br />
                            '.$this->billing_address->zipcode.' '.$this->billing_address->location.'<br />
                            '.$billing_region_name.$billing_country_name.'<br />
                            <br />
                            '.$this->billing_address->email.'<br />
                            '.$this->billing_address->phone.'
                        </p>             
                    </td>                
                </tr>
            </table>            
        ';

        $this->load_lines();
        $table_lines = array();

        foreach($this->lines as $line)
        {
            $line_option_name = "";
            if(!empty($line->option))
            {
                $line_option_name = '<br /><small><em>'.$line->option.'</em></small>';
            }

            $table_lines[] = '
                <tr>
                    <td>'.$line->sku.'</td>
                    <td>'.$line->name.$line_option_name.'</td>
                    <td style="text-align: right;">'.(($line->coupon_unit > 0)? core_decimal2string($line->coupon_unit).' '.$currencies[$line->currency] : "-").'</td>
                    <td style="text-align: right;">'.core_decimal2string($line->base_price).' '.$currencies[$line->currency].'</td>
                    <td style="text-align: right;">'.core_decimal2string($line->tax_value).' % <br/><small><em>'.core_decimal2string($line->base_price_tax_amount).' '.$currencies[$line->currency].'</em></small></td>
                    <td style="text-align: right;">'.core_decimal2string($line->quantity).'</td>
                    <td style="text-align: right;">'.core_decimal2string($line->total, 2, true).' '.$currencies[$line->currency].'</td>
                </tr>
            ';
        }

        $coupon_info = "";
        if(!empty($this->coupon_code))
        {
            $coupon_data = json_decode($this->coupon_data, true);
            $coupon_info = $dictionary->t(726, 'Coupon').': '.$this->coupon_code.' ('.$coupon_data['dictionary'][$website->languages_list[0]]['name'].')'.'<br />';
        }

        $html .= '
            <h4>'.$dictionary->t(25, "Products").' / '.$dictionary->t(178, "Services").'</h4>
            <hr />
            <table style="width: 100%;" cellpadding="3">
                <thead>
                    <tr>            
                        <th style="width: 72px;">SKU</th>
                        <th style="width: 224px;">'.$dictionary->t(159, 'Name').'</th>
                        <th style="text-align: right; width: 48px;">'.$dictionary->t(811, 'Dis.').'</th>
                        <th style="text-align: right; width: 96px;">'.$dictionary->t(676, 'Base price').'</th>
                        <th style="text-align: right; width: 48px;">'.$dictionary->t(677, 'Tax').'</th>
                        <th style="text-align: right; width: 48px;">'.$dictionary->t(810, 'Qty.').'</th>
                        <th style="text-align: right; width: 96px;">'.$dictionary->t(685, 'Subtotal').'</th>
                    </tr>
                </thead>
                <tbody>
                    '.implode("", $table_lines).'
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7"><hr /></td>                    
                    </tr>         
                    <tr>
                        <td></td>
                        <td colspan="6">'.$coupon_info.'</td>                    
                    </tr>         
                    <tr>
                        <td></td>
                        <td>
                            '.$dictionary->t(720, 'Shipping method').': '.$shipping_method->dictionary[$website->languages_list[0]]["title"].'                            
                        </td>
                        <td></td>
                        <td style="text-align: right;">'.core_decimal2string($this->shipping_amount).' '.$currencies[$this->currency].'</td>
                        <td style="text-align: right;">'.core_decimal2string($this->shipping_tax).' % <br/><small><em>'.core_decimal2string($this->shipping_tax_amount).' '.$currencies[$line->currency].'</em></small></td>
                        <td></td>
                        <td style="text-align: right;">'.core_decimal2string($this->shipping_invoiced, 2, true).' '.$currencies[$line->currency].'</td>           
                    </tr>                
                </tfoot>
            </table>            
        ';

        $html .= '
            <div style="text-align: right;">
                <strong>'.$dictionary->t(706, 'Total').'</strong>                
                <br />
                <strong style="font-size: 14px;">'.core_decimal2string($this->total, 2, true).' '.$currencies[$line->currency].'</strong>
            </div>
        ';

        $html .= '
            <br />
            <hr />
            <br />
            <div class="footer">
                <small>'.$website->shop_legal_info[$pdf_lang].'</small>
            </div>
            <br />
        ';

        $filename = $dictionary->t(734, "Order").' '.$this->reference.'.pdf';

        $mpdf = @new \Mpdf\Mpdf(
            array(
                'default_font_size' => 9,
                'default_font' => 'dejavusanscondensed'
            )
        );
        $mpdf->SetHTMLHeader("");
        $mpdf->SetHTMLFooter("");
        $mpdf->WriteHTML($html);

        if($download)
        {
            $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD); // 'INLINE' for inline download
            $out = true;
        }
        else
        {
            $out = $mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN);
        }

        return $out;
    }

    public function send_customer_notification($comment)
    {
        global $website;
        global $lang;

        $customer = new webuser();
        $customer->load($this->webuser);

        $email_lang = $website->languages_published[0];
        if(in_array($customer->language, $website->languages_published))
        {
            $email_lang = $customer->language;
        }

        $dictionary = new language();
        if($lang->code == $email_lang)
        {
            $dictionary = $lang;
        }
        else // already loaded!
        {
            $dictionary->load($email_lang);
        }

        $comment_array = array();
        if(!empty($comment))
        {
            $comment_array = array(
                'title'   => $dictionary->t(205, "Comment"),
                'content' => nl2br($comment)
            );
        }

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
                $comment_array,
                array(
                    'footer' => '<a href="' . $website->absolute_path() . $website->homepage() . '" style="text-decoration: none; color: #fff;">&#128712;</a> ' .
                        $dictionary->t(735, "For any complaint or inquiry, please contact us.")
                )
            )
        );

        $sent = navigate_send_email(
            $dictionary->t(734, "Order") . ' #' . $this->reference.' — ' . order::status($this->status, $dictionary),
            $message,
            $this->customer_data->email,
            NULL,
            false
        );

        return $sent;
    }

    public function send_customer_order_creation()
    {
        global $website;
        global $lang;

        $customer = new webuser();
        $customer->load($this->webuser);

        $email_lang = $website->languages_published[0];
        if(in_array($customer->language, $website->languages_published))
        {
            $email_lang = $customer->language;
        }

        $dictionary = new language();
        if($lang->code == $email_lang)
        {
            // already loaded!
            $dictionary = $lang;
        }
        else
        {
            $dictionary->load($email_lang);
        }

        $this->load_lines();
        $lines = array();
        $lines[] = '<tr>';
        $lines[] = '<td style="text-align: center; ">'.$dictionary->t(724, "Quantity").'</td>';
        $lines[] = '<td width="85%">'.$dictionary->t(198, "Product").'</td>';
        $lines[] = '</tr>';
        for($l=0; $l < count($this->lines); $l++)
        {
            $lines[] = '<tr><td colspan="2"><div style="height: 1px; background-color: #aaaaaa;"></div></td></tr>';
            $lines[] = '<tr>';
            $lines[] = '<td valign="top" style="text-align: center;">'.core_decimal2string($this->lines[$l]->quantity).'</td>';
            $lines[] = '<td>'.$this->lines[$l]->name.'<br /><small>'.$this->lines[$l]->option.'</small></td>';
            $lines[] = '</tr>';
        }

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
                    'title'   => $dictionary->t(25, "Products"),
                    'content' => '<table width="100%">'.implode("\n", $lines).'</table>'
                ),
                array(
                    'title'   => $dictionary->t(706, "Total"),
                    'content' => core_price2string($this->total, $this->currency)
                ),
                array(
                    'title'   => $dictionary->t(68, "Status"),
                    'content' => order::status($this->status, $dictionary) . '<br /><small>'.core_ts2date($this->date_updated).'</small>'
                ),
                array(
                    'footer' => '<a href="' . $website->absolute_path() . $website->homepage() . '" style="text-decoration: none; color: #fff;">&#128712;</a> ' .
                        $dictionary->t(735, "For any complaint or inquiry, please contact us.")
                )
            )
        );

        $pdf_data = $this->generate_pdf();

        navigate_send_email(
            $dictionary->t(792, "Order created") . ' #' . $this->reference,
            $message,
            $this->customer_data->email,
            array(
                0 => array(
                    'name' => $dictionary->t(734, "Order").' '.$this->reference.'.pdf',
                    'data' => $pdf_data
                )
            ),
            false
        );

        $this->history[] = array(
            time(),
            'notify_customer',
            array(
                'status' => $this->status,
                'order_pdf' => $dictionary->t(734, "Order").' '.$this->reference.'.pdf'
            )
        );
        $this->update_history();
    }

    public static function find_by_reference($reference, $website_id=null)
    {
        global $DB;
        global $website;

        if(empty($website_id))
        {
            $website_id = $website->id;
        }

        $order_id = $DB->query_single(
            'id',
            'nv_orders',
            'reference = :reference AND website = :wid',
            NULL,
            array(
                ':wid' => $website_id,
                ':reference' => $reference
            )
        );

        return $order_id;
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
        {
            return $status[$state];
        }
        else
        {
            return $status;
        }
    }

    public static function tax_amount_in_a_price($price, $tax_value)
    {
        $divisor = 1 + $tax_value/100;
        $original_price = round($price / $divisor, 2);
        $tax_amount = $price - $original_price;

        return $tax_amount;
    }

    public function quicksearch($text)
    {
        $parameters = array();

        $like = ' LIKE CONCAT("%", :qs_text, "%") ';
        $parameters[':qs_text'] = $text;

        $cols[] = 'reference '.$like;
        $cols[] = 'coupon_code '.$like;
        $cols[] = 'customer_data '.$like;

        $where = ' AND ( ';
        $where.= implode( ' OR ', $cols);
        $where .= ')';

        return array($where, $parameters);
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $DB->query('SELECT * FROM nv_orders WHERE website = '.intval($website->id), 'object');
        $out = $DB->result();

        if($type='json')
        {
            $out = json_encode($out);
        }

        return $out;
    }

}

?>