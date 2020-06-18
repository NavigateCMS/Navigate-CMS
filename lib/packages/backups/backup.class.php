<?php
require_once(NAVIGATE_PATH.'/lib/external/misc/zipfile.php');

class backup
{
	public $id;
	public $website;
	public $date_created;
	public $size;
	public $status;	// creating, correct
	public $title;
	public $notes;
	public $file;
	public $runtime; // seconds needed to create the backup
	public $version; // navigate cms version when the backup was created (ex. 1.6.6 r368)

    public function __construct()
    {
        
    }

	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_backups WHERE id = '.intval($id).' AND website = '.$website->id))
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
		$this->date_created = $main->date_created;
		$this->size 		= $main->size;
		$this->status 		= $main->status;
		$this->title 		= $main->title;
		$this->notes 		= $main->notes;
		$this->file  		= $main->file;
		$this->runtime 		= $main->runtime;
		$this->version 		= $main->version;
	}
	
	public function load_from_post()
	{
		$this->title  		= core_purify_string($_REQUEST['title']);
		$this->notes 		= core_purify_string($_REQUEST['notes']);
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
		global $website;

		// remove all old entries
		if(!empty($this->id))
		{
			$DB->execute('
				DELETE FROM nv_backups
				WHERE id = '.intval($this->id).' AND 
					  website = '.$website->id
			);
			
			// remove backup file
            @unlink(NAVIGATE_URL.'/private/'.$this->file);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
		
		$current_version = update::latest_installed();

		$ok = $DB->execute('
			INSERT INTO nv_backups
				(id, website, date_created, size, status, title, notes, file, runtime, version)
			VALUES 
				( 0, :website, :date_created, :size, :status, :title, :notes, :file, :runtime, :version)
			',
			array(
				'website' => $website->id,
				'date_created' => time(),
				'size' => value_or_default($this->size, 0),
				'status' => value_or_default($this->status, ''),
				'title' => value_or_default($this->title, ''),
				'notes' => value_or_default($this->notes, ''),
				'file'  => value_or_default($this->file, ''),
				'runtime' => value_or_default($this->runtime, 0),
				'version' => $current_version->version.' r'.$current_version->revision
			)
		);

		$this->id = $DB->get_last_id();
		
		return true;
	}
	
	public function update()
	{
		global $DB;
			
		$ok = $DB->execute('
			UPDATE nv_backups
			SET size = :size, status = :status, title = :title, notes = :notes, file = :file, runtime = :runtime
			WHERE id = :id 
			  AND website = :website',
			array(
				'id' => $this->id,
				'website' => $this->website,
				'size' => value_or_default($this->size, 0),
				'status' => value_or_default($this->status, ''),
				'title' => value_or_default($this->title, ''),
				'notes' => value_or_default($this->notes, ''),
				'file'  => value_or_default($this->file, ''),
				'runtime' => value_or_default($this->runtime, 0)
			)
		);
							  
		if(!$ok)
        {
            throw new Exception($DB->get_last_error());
        }
		
		return true;
	}		
	
	
	public function quicksearch($text)
	{
		$like = ' LIKE '.protect('%'.$text.'%');
				
		// all columns to look for	
		$cols[] = 'i.id' . $like;
		$cols[] = 'i.title' . $like;
		$cols[] = 'i.notes' . $like;
			
		$where = ' AND ( ';	
		$where.= implode( ' OR ', $cols); 
		$where .= ')';
		
		return $where;
	}	

    public static function estimated_size()
    {
        global $DB;
        global $website;

        // database
        // themes, templates and webgets
        // extensions
        // files

        // database
        $DB->query('SHOW TABLE STATUS'); // MySQL only!
        $database = array_sum($DB->result('Data_length'));

        $templates = foldersize(NAVIGATE_PRIVATE.'/'.$website->id.'/templates');
        $webgets = foldersize(NAVIGATE_PRIVATE.'/'.$website->id.'/webgets');
        $files = foldersize(NAVIGATE_PRIVATE.'/'.$website->id.'/files');

        $themes = foldersize('themes');
        $extensions = foldersize('plugins');

        return ($database + $templates + $webgets + $files + $themes + $extensions);
    }

    public static function status($code)
    {
        $status = array(
            '' => '<span class="ui-icon ui-icon-radio-on" style=" float: left; "></span> '.t(413, 'Waiting to begin'),
            'prepare' => '<span class="ui-icon ui-icon-clock" style=" float: left; "></span> '.t(424, 'Preparing process'),
            'database' => '<span class="ui-icon ui-icon-bookmark" style=" float: left; "></span> '.t(414, 'Exporting database'),
            'themes' => '<span class="ui-icon ui-icon-copy" style=" float: left; "></span> '.t(415, 'Copying themes and templates'), // & webgets!
            'extensions' => '<span class="ui-icon ui-icon-copy" style=" float: left; "></span> '.t(416, 'Copying extensions'),
            'files' => '<span class="ui-icon ui-icon-copy" style=" float: left; "></span> '.t(417, 'Copying uploads'),
            'compress' => '<span class="ui-icon ui-icon-disk" style=" float: left; "></span> '.t(418, 'Creating archive'),
            'upload' => '<span class="ui-icon ui-icon-circle-check" style=" float: left; "></span> '.t(422, 'Uploading to server'),
            'completed' => '<span class="ui-icon ui-icon-circle-check" style=" float: left; "></span> '.t(419, 'Process complete'),
            'error' => '<span class="ui-icon ui-icon-alert" style=" float: left; "></span> '.t(56, 'Unexpected error')
        );

        if(!empty($status[$code]))
        {
            $code = $status[$code];
        }

        return $code;
    }

    public function backup()
    {
        global $website;
        global $DB;

        // protection against double process call
        if(!empty($this->status))
        {
            core_terminate();
        }

        // prepare temporary folder
        if(!file_exists(NAVIGATE_PRIVATE.'/'.$website->id.'/backups'))
        {
            @mkdir(NAVIGATE_PRIVATE.'/'.$website->id.'/backups', 0744, true);
        }

        $zip = new ZipArchive();

        $backup_filename = '/'.$website->id.'/backups/backup-'.time().'.zip';

        if($zip->open(NAVIGATE_PRIVATE.$backup_filename, ZIPARCHIVE::CREATE)!==TRUE)
        {
            $this->status = 'ZipArchive error: '.NAVIGATE_PRIVATE.'/'.$website->id.'/backups/backup-'.time().'.zip';
            $this->update();
            throw new Exception('ZipArchive error: '.NAVIGATE_PRIVATE.'/'.$website->id.'/backups/backup-'.time().'.zip');
        }

        $this->status = 'database';
        $this->update();

        // database
        //--> call the exporter (backup) of each object type
        // TODO: add shopping related exporters!
        $objects = array(
            'block', // blocks
            'item',
            'comment',
            'feed',
            'file',
            'grid_notes',
            'menu',
            'path',
            'profile',
            'property', // property_items?
            'structure',
            'template',
            'user', // User
            'permission',
            'webdictionary',
            'webdictionary_history',
            'website',
            'webuser',
            'webuser_group',
            //'webuser_favorites', // ???
            'webuser_vote'
        );

        include_once(NAVIGATE_PATH.'/lib/packages/blocks/block.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/comments/comment.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/feeds/feed.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/files/file.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/grid_notes/grid_notes.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/menus/menu.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/paths/path.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/profiles/profile.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/permissions/permission.class.php');
        include_once(NAVIGATE_PATH.'/lib/core/user.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary_history.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/websites/website.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_profile.class.php');
        include_once(NAVIGATE_PATH.'/lib/packages/webuser_votes/webuser_vote.class.php');

        foreach($objects as $object)
        {
            $json = $object::backup('json');
            $zip->addFromString('database/'.$object.'.json', $json);
        }

        // themes
        $DB->reconnect();
        $this->status = 'themes';
        $this->update();
        $files = rglob("*", GLOB_MARK, NAVIGATE_PATH.'/themes');
        foreach($files as $file)
        {
            if(!file_exists($file)) continue;
            $file = substr($file, strlen(NAVIGATE_PATH.'/'));
            if(substr($file, -1, 1)=="\\" || substr($file, -1, 1)=="/") continue;
            $zip->addFile($file);
        }

        // templates
        $files = rglob("*", GLOB_MARK, NAVIGATE_PRIVATE.'/'.$website->id.'/templates');
        foreach($files as $file)
        {
            if(!file_exists($file)) continue;
            $file = substr($file, strlen(NAVIGATE_PATH.'/'));
            if(substr($file, -1, 1)=="\\" || substr($file, -1, 1)=="/") continue;
            $zip->addFile($file);
        }

        // webgets
        $files = rglob("*", GLOB_MARK, NAVIGATE_PRIVATE.'/'.$website->id.'/webgets');
        foreach($files as $file)
        {
            if(!file_exists($file)) continue;
            $file = substr($file, strlen(NAVIGATE_PATH.'/'));
            if(substr($file, -1, 1)=="\\" || substr($file, -1, 1)=="/") continue;
            $zip->addFile($file);
        }

        // extensions
        $DB->reconnect();
        $this->status = 'extensions';
        $this->update();
        $files = rglob("*", GLOB_MARK, NAVIGATE_PATH.'/plugins');
        foreach($files as $file)
        {
            if(!file_exists($file)) continue;
            $file = substr($file, strlen(NAVIGATE_PATH.'/'));
            if(substr($file, -1, 1)=="\\" || substr($file, -1, 1)=="/") continue;
            $zip->addFile($file);
        }

        // files (uploads)
        $DB->reconnect();
        $this->status = 'files';
        $this->update();

        $files = rglob("*", GLOB_MARK, NAVIGATE_PRIVATE.'/'.$website->id.'/files');
        foreach($files as $file)
        {
            if(!file_exists($file)) continue;
            $file = substr($file, strlen(NAVIGATE_PATH.'/'));
            if(substr($file, -1, 1)=="\\" || substr($file, -1, 1)=="/") continue;
            $zip->addFile($file);
        }

        $DB->reconnect();
        $this->status = 'compress';
        $this->update();

        // compress
        $zip->close();

        // to do: upload to naviwebs backup service
        /*
        if($this->upload)
        {
            $DB->reconnect();
            $this->status = 'upload';
            $this->update();
        }
        */

        $DB->reconnect();
        $this->status = 'completed';
        $this->size = filesize(NAVIGATE_PRIVATE.$backup_filename);
        $this->file = $backup_filename;
        $this->update();

        unset($zip);
    }

    public function restore()
    {
        // TODO
    }
}

?>