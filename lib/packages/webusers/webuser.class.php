<?php

class webuser
{
	public $id;
	public $website;
	public $username;
	public $password;
	public $email;
    public $groups;
	public $fullname;
	public $gender; // male / female / (empty)
	public $avatar;
	public $birthdate;
	public $language; // ISO 639-1 (2 chars) (en => English, es => Español)
	public $country; // ISO-3166-1993 (US => United States of America, ES => Spain)
	public $timezone; // PHP5 Timezone Code (, "Europe/Madrid")
	public $address;
	public $zipcode;
	public $location;
	public $phone;
	public $social_website;
	public $joindate;
    public $lastseen;
	public $newsletter;
    public $private_comment;
	public $activation_key;
	public $cookie_hash;
	public $blocked;
  	
	public function load($id)
	{
		global $DB;
		if($DB->query('SELECT * FROM nv_webusers WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_by_hash($hash)
	{
		global $DB;
		global $session;
        global $events;

        $ok = $DB->query('SELECT * FROM nv_webusers WHERE cookie_hash = '.protect($hash));
        if($ok)
            $data = $DB->result();

        if(!empty($data))
		{

			$this->load_from_resultset($data);
			$session['webuser'] = $this->id;

            // maybe this functions is called without initializing $events
            if(method_exists($events, 'trigger'))
            {
                $events->trigger(
                    'webuser',
                    'sign_in',
                    array(
                        'webuser' => $this
                    )
                );
            }
		}

	}

    public function load_by_profile($network, $network_user_id)
    {
        global $DB;
        global $session;

        // the profile exists (connected to a social network)?
        $swuser = $DB->query_single(
            'webuser',
            'nv_webuser_profiles',
            ' network = '.protect($network).' AND '.
            ' network_user_id = '.protect($network_user_id)
        );

        if(!empty($swuser))
            $this->load($swuser);
    }

    public function load_from_resultset($rs)
	{
		$main = $rs[0];	
		
		$this->id      		= $main->id;		
		$this->website      = $main->website;
		$this->username		= $main->username;
		$this->password		= $main->password;
   		$this->email	    = $main->email;    
		$this->fullname		= $main->fullname;
		$this->gender		= $main->gender;		
		$this->avatar		= $main->avatar;		
		$this->birthdate	= $main->birthdate;
		$this->language		= $main->language;	
		$this->country		= $main->country;
		$this->timezone		= $main->timezone;
		$this->address		= $main->address;
		$this->zipcode		= $main->zipcode;
		$this->location		= $main->location;
		$this->phone		= $main->phone;	
		$this->social_website   = $main->social_website;
        $this->joindate		= $main->joindate;
        $this->lastseen		= $main->lastseen;
		$this->newsletter	= $main->newsletter;
		$this->private_comment	= $main->private_comment;
		$this->activation_key	= $main->activation_key;
		$this->cookie_hash	= $main->cookie_hash;
		$this->blocked		= $main->blocked;

        // to get the array of groups first we remove the "g" character
        $groups = str_replace('g', '', $main->groups);
        $this->groups = explode(',', $groups);
        if(!is_array($this->groups))  $this->groups = array($groups);
	}
	
	public function load_from_post()
	{
		//$this->website      = $_REQUEST['webuser-website'];
		$this->username		= trim($_REQUEST['webuser-username']);
		if(!empty($_REQUEST['webuser-password']))
			$this->set_password($_REQUEST['webuser-password']);			
   		$this->email	    = $_REQUEST['webuser-email'];
   		$this->groups	    = $_REQUEST['webuser-groups'];
		$this->fullname		= $_REQUEST['webuser-fullname'];
		$this->gender		= $_REQUEST['webuser-gender'][0];		
		$this->avatar		= $_REQUEST['webuser-avatar'];		
		if(!empty($_REQUEST['webuser-birthdate']))
			$this->birthdate	= core_date2ts($_REQUEST['webuser-birthdate']);
		else
			$this->birthdate	= '';
		$this->language		= $_REQUEST['webuser-language'];			
		$this->newsletter	= ($_REQUEST['webuser-newsletter']=='1'? '1' : '0');
		$this->blocked		= ($_REQUEST['webuser-blocked']=='1'? '1' : '0');	
		
		$this->country		= $_REQUEST['webuser-country'];
		$this->timezone		= $_REQUEST['webuser-timezone'];
		$this->address		= $_REQUEST['webuser-address'];
		$this->zipcode		= $_REQUEST['webuser-zipcode'];
		$this->location		= $_REQUEST['webuser-location'];
		$this->phone		= $_REQUEST['webuser-phone'];			
		$this->social_website = $_REQUEST['webuser-social_website'];
		$this->private_comment = $_REQUEST['webuser-private_comment'];

        // social profiles is a navigate cms private field
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
        global $events;

		if(!empty($this->id))
		{
            // remove all social profiles
            $DB->execute(' DELETE FROM nv_webuser_profiles
							WHERE webuser = '.intval($this->id)
            );

            // finally remove webuser account
            $DB->execute(' DELETE FROM nv_webusers
							WHERE id = '.intval($this->id).'
              				LIMIT 1 '
						);

            $events->trigger(
                'webuser',
                'delete',
                array(
                    'webuser' => $this
                )
            );
		}

		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;	
		global $website;
        global $events;

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

		$ok = $DB->execute(' INSERT INTO nv_webusers
								(	id, website, username, password, email, groups, fullname, gender, avatar, birthdate,
									language, country, timezone, address, zipcode, location, phone, social_website,
									joindate, lastseen, newsletter, private_comment, activation_key, cookie_hash, blocked)
								VALUES 
								( 0,
								  '.protect($website->id).',
								  '.protect($this->username).',
								  '.protect($this->password).',
								  '.protect($this->email).',
								  '.protect($groups).',
								  '.protect($this->fullname).',
								  '.protect($this->gender).',								  
								  '.protect($this->avatar).',								  
								  '.protect($this->birthdate).',
								  '.protect($this->language).',
								  '.protect($this->country).',
								  '.protect($this->timezone).',
								  '.protect($this->address).',
								  '.protect($this->zipcode).',
								  '.protect($this->location).',
								  '.protect($this->phone).',
								  '.protect($this->social_website).',
								  '.protect(core_time()).',
								  '.protect(0).',
								  '.protect($this->newsletter).',
								  '.protect($this->private_comment).',
								  '.protect($this->activation_key).',
								  '.protect($this->cookie_hash).',
								  '.protect($this->blocked).'				  
								)');							
				
		if(!$ok) throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();

        $events->trigger(
            'webuser',
            'save',
            array(
                'webuser' => $this
            )
        );
		
		return true;
	}	
	
	public function update()
	{
		global $DB;
        global $events;

        $groups = '';
        if(is_array($this->groups))
        {
            $this->groups = array_unique($this->groups); // remove duplicates
            $this->groups = array_filter($this->groups); // remove empty
            if(!empty($this->groups))
                $groups = 'g'.implode(',g', $this->groups);
        }

        if($groups == 'g')
            $groups = '';

		$ok = $DB->execute(' UPDATE nv_webusers
								SET
								  website = '.protect($this->website).',
								  username = '.protect($this->username).',
								  password = '.protect($this->password).',
								  email = '.protect($this->email).',
								  groups = '.protect($groups).',
								  fullname = '.protect($this->fullname).',
								  gender = '.protect($this->gender).',
								  avatar = '.protect($this->avatar).',								  
								  birthdate = '.protect($this->birthdate).',
								  language = '.protect($this->language).',
								  lastseen = '.protect($this->lastseen).',
								  country = '.protect($this->country).',
								  timezone = '.protect($this->timezone).',
								  address = '.protect($this->address).',
								  zipcode = '.protect($this->zipcode).',
								  location = '.protect($this->location).',
								  phone	= '.protect($this->phone).',
								  social_website = '.protect($this->social_website).',
								  newsletter = '.protect($this->newsletter).',
								  private_comment = '.protect($this->private_comment).',
								  activation_key = '.protect($this->activation_key).',
								  cookie_hash = '.protect($this->cookie_hash).',
								  blocked = '.protect($this->blocked).'
                 				WHERE id = '.protect($this->id));
		
		if(!$ok) throw new Exception($DB->get_last_error());

        $events->trigger(
            'webuser',
            'save',
            array(
                'webuser' => $this
            )
        );

		return true;
	}
	
	public function authenticate($website, $username, $password)
	{
		global $DB;
        global $events;
		
		$username = trim($username);
		$username = mb_strtolower($username);
				
		$A1 = md5($username.':'.APP_REALM.':'.$password);

        $website_check = '';
		if($website > 0)
			$website_check = 'AND website  = '.protect($website);
				
		if($DB->query('SELECT * 
						 FROM nv_webusers 
						WHERE blocked = "0"
						  '.$website_check.'
						  AND LOWER(username) = '.protect($username)))
		{		
			$data = $DB->result();	
			
			if($data[0]->password==$A1) 
			{
				$this->load_from_resultset($data);

                $events->trigger(
                    'webuser',
                    'sign_in',
                    array(
                        'webuser' => $this
                    )
                );

				return true;
			}
		}
		
		return false;		
	}
	
	public function set_password($newpass)
	{
		$this->password = md5(mb_strtolower($this->username).':'.APP_REALM.':'.$newpass);
	}
	
	public function set_cookie()
	{
		global $session;
		
		$session['webuser'] = $this->id;
		$this->cookie_hash = sha1(rand(1, 9999999));
		$this->update();
		setcookie('webuser', $this->cookie_hash, time()+60*60*24*365, '/', substr($_SERVER['SERVER_NAME'], strpos($_SERVER['SERVER_NAME'], "."))); // 365 days		
	}
	
	public static function unset_cookie()
	{
		global $session;
		
		$session['webuser'] = '';
		setcookie('webuser', NULL, -1, '/', substr($_SERVER['SERVER_NAME'], strpos($_SERVER['SERVER_NAME'], ".")));
	}

	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'id' . $like;
		$cols[] = 'LOWER(username)' . mb_strtolower($like);
		$cols[] = 'email' . $like;
		$cols[] = 'fullname' . $like;		
	
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}	

    public static function social_network_profile_update($network, $network_user_id, $extra='', $data=array())
    {
        global $DB;
        global $webuser;
        global $website;

        if(is_array($extra))
            $extra = serialize($extra);

        // the profile exists?
        $swuser = $DB->query_single(
            'webuser',
            'nv_webuser_profiles',
            ' network = '.protect($network).' AND '.
            ' network_user_id = '.protect($network_user_id)
        );

        // the webuser already exists/is logged in?

        $wuser = new webuser();

        if(!empty($webuser->id))
        {
            // an existing webuser is already signed in, but we don't have his/her social profile
            if(empty($swuser))
            {
                $DB->execute('INSERT nv_webuser_profiles
                                   (id, network, network_user_id, webuser, extra)
                                   VALUES
                                   (    0,
                                        '.protect($network).',
                                        '.protect($network_user_id).',
                                        '.protect($webuser->id).',
                                        '.protect($extra).'
                                   )');
            }

            $wuser->load($webuser->id);
        }
        else
        {
            // there is no webuser logged in
            if(empty($swuser))
            {
                // and we don't have any social profile that matches the one used to sign in
                // Ex. Signed in with Facebook without having a previous webuser account in the current website
                $wuser->website = $website->id;
                $wuser->joindate = core_time();
                $wuser->lastseen = core_time();
                $wuser->blocked = 0;
                $wuser->insert();

                $DB->execute('INSERT nv_webuser_profiles
                                   (id, network, network_user_id, webuser, extra)
                                   VALUES
                                   (    0,
                                        '.protect($network).',
                                        '.protect($network_user_id).',
                                        '.protect($wuser->id).',
                                        '.protect($extra).'
                                   )');
            }
            else
            {
                // BUT we have a social profile matching a previous webuser in database
                // Ex. Signed in with Facebook having a webuser account previously
                $wuser->load($swuser);
            }
        }

        // either way, now we have a webuser account that we need to update
        foreach($data as $field => $value)
            $wuser->$field = $value;

        $wuser->update();

        return $wuser->id;
    }

	public static function available($username, $website_id)
	{
		global $DB;
		
		// remove spaces and make username lowercase (only to compare case insensitive)
		$username = trim($username);
		$username = mb_strtolower($username);
	
		$data = NULL;
		if($DB->query('SELECT COUNT(*) as total
					   FROM nv_webusers 
					   WHERE LOWER(username) = '.protect($username).'
					   	 AND website = '.$website_id))
		{
			$data = $DB->first();
		}
		
		return ($data->total <= 0);
	}

    public static function export($type='csv')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('
            SELECT id, website, username, email, groups, fullname, gender,
                '/*avatar,*/.'
                birthdate, language, country, timezone,
                address, zipcode, location, phone, social_website,
                joindate, lastseen, newsletter, private_comment, blocked
            FROM nv_webusers
            WHERE website = '.protect($website->id), 'array');

        $fields = array(
            "ID",
            t(177, 'Website').' [NV]',
            t(1, 'User'),
            t(44, 'E-Mail'),
            t(506, 'Groups'),
            t(159, 'Name'),
            t(304, 'Gender'),
            //(246, 'Avatar'),
            t(248, 'Birthdate'),
            t(46, 'Language'),
            t(224, 'Country'),
            t(97, 'Timezone'),
            t(233, 'Address'),
            t(318, 'Zip code'),
            t(319, 'Location'),
            t(320, 'Phone'),
            t(177, 'Website'),
            t(247, 'Date joined'),
            t(563, 'Last seen'),
            t(249, 'Newsletter'),
            t(538, 'Private comment'),
            t(47, 'Blocked')
        );

        $out = $DB->result();

        $temp_file = tempnam("", 'nv_');
        $fp = fopen($temp_file, 'w');

        fputcsv($fp, $fields);

        foreach ($out as $fields)
            fputcsv($fp, $fields);

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.basename('webusers.csv'));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($temp_file));
        ob_clean();
        flush();
        fclose($fp);
        readfile($temp_file);

        @unlink($temp_file);

        core_terminate();
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_webusers WHERE website = '.protect($website->id), 'object');

        if($type='json')
            $out['nv_webusers'] = json_encode($DB->result());

        $DB->query('SELECT nwp.* FROM nv_webuser_profiles nwp, nv_webusers nw
                    WHERE nwp.webuser = nw.id
                      AND nw.website = '.protect($website->id),
            'object');

        if($type='json')
            $out['nv_webuser_profiles'] = json_encode($DB->result());

        if($type='json')
            $out = json_encode($out);

        return $out;
    }
}

?>