<?php
class update
{
	public $id;
	public $version;
	public $revision;
	public $commit;
	public $date_updated;
	public $status;
	public $changelog;
	public static $latest_version_endpoint = 'http://update.navigatecms.com/latest';
	public static $updates_list_endpoint = 'http://update.navigatecms.com/from?revision=';

	public function __construct()
    {

    }

    public function load($id)
	{
		global $DB;

		if($DB->query('SELECT * FROM nv_updates WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data);
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id			= $main->id;
		$this->version		= $main->version;		
		$this->revision		= $main->revision;
		$this->date_updated	= $main->date_updated;
		$this->commit       = $main->commit;
		$this->status		= $main->status;
		$this->changelog	= $main->changelog;
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

		// remove all old entries
		if(!empty($this->id))
		{
			$DB->execute('DELETE FROM nv_updates WHERE id = '.intval($this->id));
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		
		$ok = $DB->execute(' 
 			INSERT INTO nv_updates
				(id, version, revision, commit, date_updated, status, changelog)
			VALUES 
				( 0, :version, :revision, :commit, :date_updated, :status, :changelog )',
			array(
				'version' => $this->version,
				'revision' => $this->revision,
				'commit' => value_or_default($this->commit, ""),
				'date_updated' => $this->date_updated,
				'status' => $this->status,
				'changelog' => $this->changelog
			)
		);

		if(!$ok)
        {
            throw new Exception($DB->get_last_error());
        }
	
		$this->id = $DB->get_last_id();
		
		return true;
	}
	
	public function update()
	{
		global $DB;
					
		if(empty($this->id))
        {
            return false;
        }
		
		$ok = $DB->execute(' 
 			UPDATE nv_updates
			   SET version = :version, revision = :revision, commit = :commit,
			       date_updated = :date_updated, status = :status, changelog = :changelog
		     WHERE id = :id',
			array(
				'id' => $this->id,
				'version' => $this->version,
				'revision' => $this->revision,
				'commit' => value_or_default($this->commit),
				'date_updated' => $this->date_updated,
				'status' => $this->status,
				'changelog' => $this->changelog
			)
		);

		if(!$ok)
        {
            throw new Exception($DB->get_last_error());
        }
		
		return true;
	}		
	
	public static function latest_available()
	{
        $latest_update = @core_http_request(update::$latest_version_endpoint);

		// if update info could not be loaded set the same version installed, to avoid looking for updates if the connection cannot be established
		if(empty($latest_update))
		{
			$latest_installed = self::latest_installed();
			$latest_update = '{"Version":"'.$latest_installed->version.'","Revision":"'.$latest_installed->revision.'"}';
		}

		$latest_update = json_decode($latest_update);
		return $latest_update;
	}
	
	public static function latest_installed()
	{
		global $DB;

		$DB->query('
			SELECT * 
			FROM nv_updates 
			WHERE status = "ok" 
			ORDER BY revision DESC 
			LIMIT 1
		');

		$installed_version = $DB->first();

		return $installed_version;
	}
	
	public static function updates_available()
	{
		$latest_installed = update::latest_installed();

        $list = core_http_request((update::$updates_list_endpoint).$latest_installed->revision);

		$list = json_decode($list, true);
		if(!$list) $list = array();
		$list = array_values($list);
		return $list;		
	}
	
	public static function install_from_navigatecms($updates=array())
	{
		global $DB;
		
		@set_time_limit(0);
        if(empty($updates))
        {
            $updates = update::updates_available();
        }
		
		if(empty($updates[0]['Revision']))
        {
            return false;
        }
		
		$ulog = NAVIGATE_PATH.'/updates/update-'.$updates[0]['Revision'].'.log.txt';
		
		file_put_contents($ulog, "UPDATE PROCESS ".$updates[0]['Revision'].' on '.time()."\n", FILE_APPEND);
		
		// download 
		$ufile = NAVIGATE_PATH.'/updates/update-'.$updates[0]['Revision'].'.zip';
		if(!file_exists($ufile) || filesize($ufile) < 1000)
		{
			file_put_contents($ulog, "downloading update...\n", FILE_APPEND);

            //$data = file_get_contents($updates[0]['Zip URL']);
			//file_put_contents($ufile, $data);

            @copy($updates[0]['Zip URL'], $ufile);
            if(!file_exists($ufile) || filesize($ufile) < 100)
            {
                // try to download update with curl
                core_file_curl($updates[0]['Zip URL'], $ufile);
            }

            file_put_contents($ulog, "update package downloaded\n", FILE_APPEND);
		}
		else
		{
			file_put_contents($ulog, "update package already downloaded\n", FILE_APPEND);
		}
		
		return update::install_from_file($updates[0]['Version'], $updates[0]['Revision'], $updates[0]['Commit'], $ufile, $ulog);
	}
	
	public static function install_from_repository($file_id)
	{
		global $website;
		
		@set_time_limit(0);
		
		$latest = update::latest_installed();
		
		$ulog = NAVIGATE_PATH.'/updates/update-'.$latest->revision.'-custom-'.time().'.log.txt';
		
		file_put_contents($ulog, "UPDATE PROCESS ".$latest->version.'c '.$latest->revision.' (CUSTOM) on '.time()."\n", FILE_APPEND);
		
		// copy file from repository to updates folder
		$ufile = NAVIGATE_PATH.'/updates/update-'.$latest->revision.'c.zip';
		copy(NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$file_id, $ufile);
		
		return update::install_from_file($latest->version.'c', $latest->revision, $latest->commit, $ufile, $ulog);
	}
		
	public static function install_from_file($version, $revision, $commit, $ufile, $ulog)
	{	
		global $DB;
				
		// remove old update files (will be there if a previous update fails)
		file_put_contents($ulog, "remove old update folder\n", FILE_APPEND);
		core_remove_folder(NAVIGATE_PATH.'/updates/update');
				
		// decompress
		file_put_contents($ulog, "create new folder\n", FILE_APPEND);		
		mkdir(NAVIGATE_PATH.'/updates/update');
		
		$zip = new ZipArchive;
		file_put_contents($ulog, "open zip file\n", FILE_APPEND);
		if($zip->open($ufile) === TRUE) 
		{
			file_put_contents($ulog, "extract zip file\n", FILE_APPEND);
			$zip->extractTo(NAVIGATE_PATH.'/updates/update');
			$zip->close();
		} 
		else // zip extraction failed
		{
			file_put_contents($ulog, "zip extraction failed\n", FILE_APPEND);
			@unlink($ufile);
			core_remove_folder(NAVIGATE_PATH.'/updates/update');
			return false;
		}

		// chmod files (may fail, but not fatal error)
		file_put_contents($ulog, "chmod update (may fail in Windows)... ", FILE_APPEND);		
		$chmod_status = core_chmodr(NAVIGATE_PATH.'/updates/update', 0744);
		file_put_contents($ulog, $chmod_status."\n", FILE_APPEND);

		// pre update checks/changes
        if(file_exists(NAVIGATE_PATH.'/updates/update/update-pre.php'))
        {
            file_put_contents($ulog, "run update-pre.php"."\n", FILE_APPEND);
            include_once(NAVIGATE_PATH.'/updates/update/update-pre.php');
            if(function_exists("navigate_cms_update_pre"))
            {
                $update_pre_check = navigate_cms_update_pre($ulog);
                if(!$update_pre_check)
                {
                    return false;
                }
            }
        }
		
		// do file changes
		file_put_contents($ulog, "parse file changes\n", FILE_APPEND);
		$changeset = file_get_contents(NAVIGATE_PATH.'/updates/update/changes.txt');
        $changeset = explode("\n", $changeset);
		
		foreach($changeset as $change)
		{
			file_put_contents($ulog, $change."\n", FILE_APPEND);								
			$change = trim($change);
			if(empty($change))
            {
                continue;
            }
		
            $change = explode("\t", $change);

            // GIT
            // Added (A),
            // Copied (C),
            // Deleted (D),
            // Modified (M),
            // Renamed (R),
            // Type changed (T) (i.e. regular file, symlink, submodule, …​),
            // Unmerged (U),
            // Unknown (X), or
            // have had their pairing Broken (B)
			
			$file = str_replace('\\', '/', $change[1]);

			//if(substr($file, 0, strlen('plugins/'))=='plugins/') continue;
			if(substr($file, 0, strlen('setup/'))=='setup/')
            {
                continue;
            }
			
            switch(substr($change[0], 0, 1))
			{
				case 'A':
					// added a new file

				case 'M':
					// modified file				
					if(!file_exists(NAVIGATE_PATH.'/updates/update/'.$file)) 
					{
						file_put_contents($ulog, "file doesn't exist!\n", FILE_APPEND);
						return false;
					}
					@mkdir(dirname(NAVIGATE_PATH.'/'.$file), 0777, true);
					if(!@copy(NAVIGATE_PATH.'/updates/update/'.$file, NAVIGATE_PATH.'/'.$file))
					{
						file_put_contents($ulog, "cannot copy file!\n", FILE_APPEND);
						return false;
					}
					break;
					
				case 'D':
					// delete file
					@unlink(NAVIGATE_PATH.'/'.$file);
					break;

                case 'R':
                    // moved (renamed) file
                    // 1) delete old file
                    @unlink(NAVIGATE_PATH.'/'.$file);
                    // 2) put new file
                    $file_new = str_replace('\\', '/', $change[2]);
                    @mkdir(dirname(NAVIGATE_PATH.'/'.$file), 0777, true);
                    if(!@copy(NAVIGATE_PATH.'/updates/update/'.$file_new, NAVIGATE_PATH.'/'.$file_new))
                    {
                        file_put_contents($ulog, "cannot copy file!\n", FILE_APPEND);
                        return false;
                    }
                    break;
                    
				default:
					// all other cases
					// IGNORE the change, as we are now only getting the modified files
			}
		}
				
		// process SQL updates
		file_put_contents($ulog, "process sql update\n", FILE_APPEND);
		
		if(file_exists(NAVIGATE_PATH.'/updates/update/update.sql'))
		{			
			$sql = file_get_contents(NAVIGATE_PATH.'/updates/update/update.sql');
			
			// execute SQL in a transaction
			// http://php.net/manual/en/pdo.transactions.php
			try 
			{  
				// can't do it in one step => SQLSTATE[HY000]: General error: 2014
				$sql = explode("\n\n", $sql);
				
				//file_put_contents($ulog, "begin transaction\n", FILE_APPEND);
				//$DB->beginTransaction();
				foreach($sql as $sqlline)
				{	
					$sqlline = trim($sqlline);			
					if(empty($sqlline))
                    {
                        continue;
                    }
					file_put_contents($ulog, "execute sql:\n".$sqlline."\n", FILE_APPEND);				
					if(!$DB->execute($sqlline))
					{
						file_put_contents($ulog, "execute failed: ".$DB->get_last_error()."\n", FILE_APPEND);
						//throw new Exception($DB->get_last_error());
					}
					
					// force commit changes (slower but safer... no --> SQLSTATE[HY000]: General error: 2014)
					$DB->disconnect();
					$DB->connect();
				}		
				//file_put_contents($ulog, "commit transaction\n", FILE_APPEND);
				//$DB->commit();	
			} 
			catch (Exception $e) 
			{
				file_put_contents($ulog, "transaction error: \n".$e->getMessage()."\n", FILE_APPEND);
				//$DB->rollBack();
				return false;
			}		
		}
		else
		{
			file_put_contents($ulog, "no SQL found\n", FILE_APPEND);
		}
		
		// add the update row to know which navigate revision is currently installed
		file_put_contents($ulog, "insert new version row on updates\n", FILE_APPEND);
		$urow = new update();
		$urow->id = 0;
		$urow->version = $version; 
		$urow->revision = $revision;
		$urow->commit = $commit;
		$urow->date_updated = time();
		$urow->status = 'ok';
		$urow->changelog = '';	

		try
		{
			$ok = $urow->insert();
		}
		catch(Exception $e)
		{
			$error = $e->getMessage();
		}
		
		if($error)
        {
            file_put_contents($ulog, "execute insert failed:\n".$DB->get_last_error()."\n", FILE_APPEND);
        }

        if(file_exists(NAVIGATE_PATH.'/updates/update/update-post.php'))
        {
            include_once(NAVIGATE_PATH.'/updates/update/update-post.php');
        }

		file_put_contents($ulog, "update finished!\n", FILE_APPEND);
		
		$urow->changelog = file_get_contents($ulog);
		$urow->save();

        @unlink($ufile);

        update::cache_clean();
		
		return true;
	}

	public static function cache_clean($website_id = "*")
    {
        // do some cleaning: css&js navigate cache, thumbnails cache, tinymce cache and pages cache
        $navigatecms_cache = glob(NAVIGATE_PATH . '/cache/*.min.*');
        for($t=0; $t < count($navigatecms_cache); $t++)
        {
            @unlink($navigatecms_cache[$t]);
        }

        $navigatecms_cache = glob(NAVIGATE_PATH . '/cache/*.js');
        for($t=0; $t < count($navigatecms_cache); $t++)
        {
            @unlink($navigatecms_cache[$t]);
        }

        $tinymce_cache = glob(NAVIGATE_PATH . '/lib/external/tinymce/*.gz');
        for($t=0; $t < count($tinymce_cache); $t++)
        {
            @unlink($tinymce_cache[$t]);
        }

        $thumbnails = glob(NAVIGATE_PRIVATE . '/'.$website_id.'/thumbnails/*x*');
        for($t=0; $t < count($thumbnails); $t++)
        {
            @unlink($thumbnails[$t]);
        }

        $pages = glob(NAVIGATE_PRIVATE.'/'.$website_id.'/cache/*.page');
        for($t=0; $t < count($pages); $t++)
        {
            @unlink($pages[$t]);
        }
    }
}
?>