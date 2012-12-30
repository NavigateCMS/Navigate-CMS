<?php
class permission
{
    public $id;
    public $name;
    public $scope;
    public $function;
    //public $description;
    //public $type;
    //public $dvalue;
    public $profile;
    public $user;
    public $value;

    /**
     * Load the properties of a certain permission
     *
     * @param integer $id ID of the user in the database
     */
    public function load($name, $profile_id=0, $user_id=0)
    {
        global $DB;
        global $user;

        if($DB->query('SELECT * FROM nv_permissions
                         WHERE name = '.protect($name).'
                           AND profile = '.intval($profile_id).'
                           AND user = '.intval($user_id)))
        {
            $data = $DB->result();
            $this->load_from_resultset($data[0]);
        }

        if(empty($this->name))
        {
            $definition = permission::get_definition($name);

            // NO permission set on database, preload with the definition
            $this->name     = $definition['name'];
            $this->scope    = $definition['scope'];
            $this->function = $definition['function'];
            $this->profile  = intval($profile_id);
            $this->user     = intval($user_id);
            $this->value    = $definition['dvalue'];
        }

    }

    /**
     * Load the database resultset properties to this permission instance.
     *
     * @param object $rs Resultset returned by the Database class
     */
    public function load_from_resultset($rs)
    {
        $this->id           = $rs->id;
        $this->name         = $rs->name;
        $this->scope        = $rs->scope;
        $this->function     = $rs->function;
        //$this->description  = $rs->id;
        //$this->type         = $rs->type;
        //$this->dvalue       = $rs->id;
        $this->profile      = $rs->profile;
        $this->user         = $rs->user;
        $this->value        = $rs->value;
    }

    /**
     * Insert or update a permission
     *
     * @return bool True if success, False otherwise
     */
    public function save()
    {
        global $DB;

        if(empty($this->id))
            return $this->insert();
        else
            return $this->update();
    }

    /**
     * Insert the properties of a new permission into the Database
     *
     * @return boolean True if success, Exception otherwise
     */
    public function insert()
    {
        global $DB;

        $ok = $DB->execute(
            'INSERT INTO nv_permissions
                (id, name, scope, function, profile, user, value)
                VALUES
                ( 0,
                  :name,
                  :scope,
                  :function,
                  :profile,
                  :user,
                  :value
                )',
            array(
                ':name'      =>  $this->name,
                ':scope'     =>  $this->scope,
                ':function'  =>  intval($this->function),
                ':profile'   =>  $this->profile,
                ':user'      =>  $this->user,
                ':value'     =>  (!isset($this->value)? '' : $this->value)
            )
        );

        if(!$ok)
            throw new Exception($DB->get_last_error());

        $this->id = $DB->get_last_id();

        return true;
    }

    /**
     * Update the properties of an existing permission in Database
     *
     *
     * @return boolean True if success, Exception otherwise
     */
    public function update()
    {
        global $DB;

        if(empty($this->id)) return false;

        $ok =  $DB->execute('  UPDATE nv_permissions
								  SET name       = :name,
								      scope      = :scope,
								      function   = :function,
								      profile    = :profile,
								      user       = :user,
								      value      = :value
								WHERE id = :id',
            array(
                ':id'        =>  $this->id,
                ':name'      =>  $this->name,
                ':scope'     =>  $this->scope,
                ':function'  =>  intval($this->function),
                ':profile'   =>  $this->profile,
                ':user'      =>  $this->user,
                ':value'     =>  (empty($this->value)? '' : $this->value)
            )
        );


        if(!$ok)
            throw new Exception($DB->get_last_error());

        return true;

    }


    public static function get_definitions()
    {
        $definitions = array();
        $definitions['system'] = json_decode(file_get_contents(NAVIGATE_PATH.'/lib/permissions/navigatecms.json'), true);
        $definitions['functions'] = json_decode(file_get_contents(NAVIGATE_PATH.'/lib/permissions/functions.json'), true);

        $definitions['extensions'] = array();
        $extensions = extension::list_installed();
        for($e=0; $e < count($extensions); $e++)
        {
            if(!empty($extensions[$e]['permissions']))
            {
                foreach($extensions[$e]['permissions'] as $permission)
                {
                    $definitions['extensions'][] = (array)$permission;
                }
            }
        }

        return $definitions;
    }

    public static function get_definition($name)
    {
        global $user;

        $scopes = array('system', 'functions', 'extensions');
        $definition = '';
        // force loading all permissions definitions (if not already done)
        $foo = $user->permission('');
        $definitions = $user->permissions['definitions'];

        foreach($scopes as $scope)
        {
            for($i=0; $i < count($definitions[$scope]); $i++)
            {
                $def = $definitions[$scope][$i];
                if($def['name']==$name)
                {
                    $definition = $def;
                    break;
                }
            }
        }

        return $definition;
    }

    public static function get_values($who='user', $obj, $definitions=NULL)
    {
        global $DB;

        // load all permission definitions: system, functions, extensions
        $scopes = array('system', 'functions', 'extensions');

        if(empty($definitions))
            $definitions = permission::get_definitions();

        // load permissions with values set on database
        if($who=='user')
        {
            $DB->query('SELECT * FROM nv_permissions WHERE profile = '.protect($obj->profile));
            $permissions_profile = $DB->result();

            $DB->query('SELECT * FROM nv_permissions WHERE user = '.protect($obj->id));
            $permissions_user = $DB->result();
        }
        else if($who=='profile')
        {
            $DB->query('SELECT * FROM nv_permissions WHERE profile = '.protect($obj->id));
            $permissions_profile = $DB->result();
            $permissions_user = array();
        }

        // now combine definitions with custom values
        $permissions = array();

        foreach($scopes as $scope)
        {
            for($i=0; $i < count($definitions[$scope]); $i++)
            {
                $def = $definitions[$scope][$i];
                $permissions[$def['name']] = $def['dvalue'];

                // search for a custom value on PROFILE permissions
                for($pp=0; $pp < count($permissions_profile); $pp++)
                {
                    if($permissions_profile[$pp]->name == $def['name'])
                    {
                        $permissions[$def['name']] = $permissions_profile[$pp]->value;
                        break; // no need to look further
                    }
                }

                // search for a custom value on USER permissions
                for($pu=0; $pu < count($permissions_user); $pu++)
                {
                    if($permissions_user[$pu]->name == $def['name'])
                    {
                        $permissions[$def['name']] = $permissions_user[$pu]->value;
                        break; // no need to look further
                    }
                }
            }
        }

        return $permissions;
    }

    public static function update_permissions($changes=array(), $profile_id=0, $user_id=0)
    {
        if(!is_array($changes))
            return;

        foreach($changes as $key => $value)
        {
            $permission = new permission();
            $permission->load($key, intval($profile_id), intval($user_id));
            $permission->value = $value;
            $permission->save();
        }
    }

    /**
     * Retrieve all permissions information and encode it in JSON format to do a Backup.
     *
     *
     * @param string $type Encode format for the rows, right now only "json" available
     * @return string All permissions rows of the database encoded
     */
    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_permissions', 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }
}
?>