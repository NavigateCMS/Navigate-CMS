<?php
class user
{
	public $id;
	public $username;
	public $password;
	public $email;
	public $profile;
	public $language;
	public $timezone;
	public $date_format;
	public $decimal_separator;
	public $blocked;
	public $attempts;
    public $cookie_hash;
    public $activation_key;

    public $permissions;
	
	/**
	 * Load the properties of a certain user
	 *
	 * @param integer $id ID of the user in the database
	 */	
	public function load($id)
	{
		global $DB;
		if($DB->query('SELECT * FROM nv_users WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data[0]);
		}
	}
	
	/**
	 * Checks the user and password provided and states if they are linked to an application user.
	 * Nota: usernames in Navigate CMS are always case insensitive (although they are saved as given).
	 *
	 * @param string $user Username
	 * @param string $password Password in clear text
	 * @return boolean True if the username and password match, False otherwise
	 */	
	public function authenticate($user, $pass)
	{
		global $DB;	

		$user = trim($user);
		$user = mb_strtolower($user);
		
		$A1 = md5($user.':'.APP_REALM.':'.$pass);	
		
		if($DB->query('SELECT * 
						 FROM nv_users 
						WHERE LOWER(username) = '.protect($user)))
		{		
			$data = $DB->result();	
							
			if($data[0]->password==$A1) 
			{
				$this->load_from_resultset($data[0]);
				$this->attempts = 0;								
				$this->update();			
				return true;
			}
			else if(!empty($data[0]->id))
			{
				$this->load_from_resultset($data[0]);
				$this->attempts++;
				
				if($this->attempts > 9)
					$this->blocked = 1;

				$this->update();
				return false;
			}
		}
		
		return false;		
	}
	
	/**
	 * Load the database resultset properties to this user instance.
	 *
	 * @param object $rs Resultset returned by the Database class
	 */	
	public function load_from_resultset($rs)
	{
		$this->id 		  		 = $rs->id;
		$this->username 		 = $rs->username;
		$this->password 		 = $rs->password;
		$this->email			 = $rs->email;
		$this->profile			 = $rs->profile;
		$this->language			 = $rs->language;	
		$this->timezone			 = $rs->timezone;				
		$this->decimal_separator = $rs->decimal_separator;
		$this->date_format		 = $rs->date_format;
		$this->blocked			 = $rs->blocked;
		$this->attempts			 = $rs->attempts;
        $this->cookie_hash		 = $rs->cookie_hash;
        $this->activation_key	 = $rs->activation_key;
	}

	/**
	 * Encode the password using the Digest A1 format md5(username:realm:password)
	 * Note: this function only sets the password for the current instance, it doesn't modify anything in the Database.
	 *
	 * @param string $password Password in clear text
	 */	
	public function set_password($newpass)
	{
		$this->password = md5(mb_strtolower($this->username).':'.APP_REALM.':'.$newpass);	
	}
	
	/**
	 * Update the user properties with the values sent by a form.
	 * It also updates the password only if a new one is sent.
	 *
	 */	
	public function load_from_post()
	{		
		$this->username 		 = $_REQUEST['user-username'];
		$this->email			 = $_REQUEST['user-email'];
		$this->profile			 = intval($_REQUEST['user-profile']);
		$this->language			 = $_REQUEST['user-language'];	
		$this->timezone			 = $_REQUEST['user-timezone'];			
		$this->decimal_separator = $_REQUEST['user-decimal_separator'];
		$this->date_format		 = $_REQUEST['user-date_format'];
		$this->blocked			 = ($_REQUEST['user-blocked']=='1'? '1' : '0');
		
		if(!empty($_REQUEST['user-password']))
			$this->set_password($_REQUEST['user-password']);
	}

	/**
	 * Set or update a 7-day cookie to let the user enter Navigate CMS without presenting the sign in form. 
	 */
    public function set_cookie()
	{
		$this->cookie_hash = sha1(rand(1, 9999999));
		$this->update();

        setcookie('navigate-user', $this->cookie_hash, time()+60*60*24*7, '/'); // 7 days
	}

	/**
	 * Remove the cookie for a Navigate CMS user.
	 */    
    public function remove_cookie()
    {
        $this->cookie_hash = '';
		$this->update();
		setcookie('navigate-user', NULL);
    }

	/**
	 * Insert or update a user
	 *
	 * @return bool True if success, False otherwise
	 */
	public function save()
	{
		global $DB;

		if(!empty($this->id))
		  return $this->update();
		else
		  return $this->insert();
	}	

	/**
	 * Remove a user from the database.
	 * All items and objects he/she created will remain.
	 *
	 * @return integer 1 if success, 0 otherwise
	 */	
	public function delete()
	{
		global $DB;

		if(!empty($this->id))
		{
			$DB->execute(
                'DELETE FROM nv_users WHERE id = ? LIMIT 1',
                array(intval($this->id))
            );
		}
		
		return $DB->get_affected_rows();		
	}

	/**
	 * Insert the properties of a new user into the Database
	 *
	 * @return boolean True if success, Exception otherwise
	 */	
	public function insert()
	{
		global $DB;

        // auto fill required fields with bogus text
        if( empty($this->username) )
            $this->username = 'user_'.substr(md5(time()), rand(0,10), 8);

        if( empty($this->password) )
            $this->password = md5(time()/2 * rand(1,5));

		$ok = $DB->execute(
            'INSERT INTO nv_users
                (id, username, password, email, language, timezone,
                profile, date_format, decimal_separator, blocked,
                attempts, cookie_hash, activation_key)
                VALUES
                ( 0,
                  :username,
                  :password,
                  :email,
                  :language,
                  :timezone,
                  :profile,
                  :date_format,
                  :decimal_separator,
                  :blocked,
                0,
                "",
                ""
            )',
            array(
                ':username'          =>  $this->username,
                ':password'          =>  $this->password,
                ':email'             =>  $this->email,
                ':language'          =>  $this->language,
                ':timezone'          =>  $this->timezone,
                ':profile'           =>  $this->profile,
                ':date_format'       =>  $this->date_format,
                ':decimal_separator' =>  $this->decimal_separator,
                ':blocked'           =>  $this->blocked
            )
        );

		if(!$ok)
            throw new Exception($DB->get_last_error());
		
		$this->id = $DB->get_last_id();
		
		return true;
	}		

	/**
	 * Update the properties of an existing user in Database
	 *
	 *
	 * @return boolean True if success, Exception otherwise
	 */	
	public function update()
	{
		global $DB;
		
		if(empty($this->id)) return false;

        $ok =  $DB->execute('  UPDATE nv_users
								  SET email	   = :email,
									  username = :username,
									  password = :password,
									  language = :language,
									  timezone = :timezone,
									  profile  = :profile,
									  date_format  = :date_format,
									  decimal_separator = :decimal_separator,
									  blocked  = :blocked,
									  attempts = :attempts,
									  cookie_hash = :cookie_hash,
									  activation_key = :activation_key
								WHERE id = :id',
                            array(':language' => $this->language,
                                  ':id' => $this->id,
                                  ':email' => $this->email,
                                  ':username' => $this->username,
                                  ':password' => $this->password,
                                  ':timezone' => $this->timezone,
                                  ':profile' => $this->profile,
                                  ':date_format' => $this->date_format,
                                  ':decimal_separator' => $this->decimal_separator,
                                  ':blocked' => $this->blocked,
                                  ':attempts' => $this->attempts,
                                  ':cookie_hash' => $this->cookie_hash,
                                  ':activation_key' => $this->activation_key
                            )
        );


        if(!$ok)
			throw new Exception($DB->get_last_error());
			
		return true;
								
	}

    /**
     * Return a permission value applied to this user (or profile)
     *
     * @param string $name Code of the permission
     * @return string Value of the permission
     */
    public function permission($name)
    {
        // first call, we need to load the current user permissions
        if(empty($this->permissions))
        {
            $this->permissions = array();
            $this->permissions['definitions'] = permission::get_definitions();
            $this->permissions['values'] = permission::get_values('user', $this, $this->permissions['definitions']);
        }

        return $this->permissions['values'][$name];
    }

	/**
	 * Provide a SQL clause to search an user using a keyword
	 *
	 *
	 * @param string $text Keyword used to find a user
	 * @return string SQL WHERE clause
	 */
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
		
		$cols[] = 'id' . $like;
		$cols[] = 'username' . $like;
		$cols[] = 'email' . $like;
	
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}	

	/**
	 * Retrieve the e-mail of a Navigate CMS user by his/her ID.
	 *
	 * @param integer $user_id ID of the user
	 * @return string E-Mail address of the user
	 */	
	public static function email_of($user_id)
	{
		global $DB;
		$email = $DB->query_single('email', 'nv_users', ' id = '.intval($user_id));
		
		return $email;
	}

	/**
	 * Sent an e-mail to the user as a password request process
	 *
	 * @return boolean True if e-mail could be sent, False otherwise
	 */
    public function forgot_password()
    {
        global $website;

        $this->activation_key = md5($this->id . $this->password . $this->username . time());
        $this->save();

        $subject = 'Navigate CMS | '.t(407, 'Forgot password?');

        $url = NAVIGATE_PARENT.NAVIGATE_FOLDER.'/login.php?action=password-reset&value='.$this->activation_key;

        $out = array();

        $out[] = '<div style=" background: #E5F1FF; width: 600px; border-radius: 6px; margin: 10px auto; padding: 1px 20px 20px 20px;">';

        $out[] = '<div style="margin: 25px 0px 10px 0px;">';
        $out[] = '    <div style="color: #595959; font-size: 17px; font-weight: bold; font-family: Verdana;">'.$this->username.'</div>';
        $out[] = '</div>';
        $out[] = '<div style="margin: 25px 0px 10px 0px;">';
        $out[] = '    <div style="color: #595959; font-size: 17px; font-weight: bold; font-family: Verdana;">'.t(450, 'Click on the link below to change your password').'</div>';
        $out[] = '</div>';
        $out[] = '<div style=" background: #fff; border-radius: 6px; padding: 10px; margin-top: 5px; line-height: 25px; text-align: justify; ">';
        $out[] = '    <div class="text" style="color: #595959; font-size: 16px; font-style: italic; font-family: Verdana;"><a href="'.$url.'">'.$url.'</a></div>';
        $out[] = '</div>';

        $out[] = '<br /><br />';
        $out[] = '<div style="font-style:italic;">'.t(451, "This is an automated e-mail sent as a result of a password request process. If you received this e-mail by error just ignore it.").'</div>';
        $out[] = '<br />';
        $out[] = '<a href="'.NAVIGATE_PARENT.NAVIGATE_FOLDER.'"><img alt="Navigate CMS" title="Navigate CMS" src="'.NAVIGATE_PARENT.NAVIGATE_FOLDER.'/img/navigate-logo-150x70.png" width="150" height="70" /></a>';

        $out[] = '</div>';

        $message = implode("\n", $out);

        // try to send the e-mail via the first website available
        $website->load();
        $sent = navigate_send_email($subject, $message, $this->email);

        // if not sent, try to send the message through PHP mail function
        if(!$sent)
        {
            $headers  = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
            $sent = mail($this->email, $subject, $message, $headers);
        }

        return $sent;
    }


	/**
	 * Retrieve all users information and encode it in JSON format to do a Backup.
	 *
	 *
	 * @param string $type Encode format for the rows, right now only "json" available
	 * @return string All user rows of the database encoded
	 */
    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_users', 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }

}

?>