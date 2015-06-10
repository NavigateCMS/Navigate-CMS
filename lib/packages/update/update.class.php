<?php
class update
{
	public $id;
	public $version;
	public $revision;
	public $date_updated;
	public $status;
	public $changelog;
	
	public function load($id)
	{
		global $DB;
		global $website;
		
		if($DB->query('SELECT * FROM nv_updates WHERE id = '.intval($id)))
		{
			$data = $DB->result();
			$this->load_from_resultset($data); // there will be as many entries as languages enabled
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];
		
		$this->id			= $main->id;
		$this->version		= $main->version;		
		$this->revision		= $main->revision;
		$this->date_updated	= $main->date_updated;
		$this->status		= $main->status;
		$this->changelog	= $main->changelog;
	}	
	
	public function save()
	{
		global $DB;

		if(!empty($this->id))
			return $this->update();
		else
			return $this->insert();			
	}
	
	public function delete()
	{
		global $DB;
		global $website;

		// remove all old entries
		if(!empty($this->id))
		{
			$DB->execute('DELETE FROM nv_updaes
								WHERE id = '.intval($this->id)
						);
		}
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
		global $website;
		
		$ok = $DB->execute(' INSERT INTO nv_updates
							(id, version, revision, date_updated, status, changelog)
							VALUES 
							( 0,
							  '.protect($this->version).',
							  '.protect($this->revision).',
							  '.protect($this->date_updated).',
							  '.protect($this->status).',
							  '.protect($this->changelog).'					  
							)');							
			
		if(!$ok)
			throw new Exception($DB->get_last_error());
	
		$this->id = $DB->get_last_id();
		
		return true;
	}
	
	public function update()
	{
		global $DB;
		global $website;
					
		if(empty($this->id)) return false;			
		
		$ok = $DB->execute(' UPDATE nv_updates
								SET 
									version		 = '.protect($this->version).',
									revision 	 =   '.protect($this->revision).',
									date_updated =   '.protect($this->date_updated).',
									status 		 =   '.protect($this->status).',
									changelog  	 =   '.protect($this->changelog).'
							WHERE id = '.$this->id);
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		return true;
	}		
	
	public static function latest_available()
	{
        $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 20
                )
            )
        );
		$latest_update = @file_get_contents(
            'http://update.navigatecms.com/latest',
            0,
            $context
        );
		if(empty($latest_update))
			return false;
		$latest_update = json_decode($latest_update);
		return $latest_update;
	}
	
	public static function latest_installed()
	{
		global $DB;
		$DB->query('SELECT * FROM nv_updates ORDER BY revision DESC LIMIT 1');
		$installed_version = $DB->first();		
		return $installed_version;
	}
	
	public static function updates_available()
	{
		$latest_installed = update::latest_installed();
        $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 20
                )
            )
        );
		$list = file_get_contents(
            'http://update.navigatecms.com/from?revision='.$latest_installed->revision,
            0,
            $context
        );
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
		    $updates = update::updates_available();
		
		if(empty($updates[0]['Revision'])) return false;
		
		$ulog = NAVIGATE_PATH.'/updates/update-'.$updates[0]['Revision'].'.log.txt';
		
		file_put_contents($ulog, "UPDATE PROCESS ".$updates[0]['Revision'].' on '.time()."\n", FILE_APPEND);
		
		// download 
		$ufile = NAVIGATE_PATH.'/updates/update-'.$updates[0]['Revision'].'.zip';
		if(!file_exists($ufile) || filesize($ufile) < 1000)
		{
			file_put_contents($ulog, "download update...\n", FILE_APPEND);

            //$data = file_get_contents($updates[0]['Zip URL']);
			//file_put_contents($ufile, $data);

            @copy($updates[0]['Zip URL'], $ufile);
            if(!file_exists($ufile) || filesize($ufile) < 100)
            {
                // try to download update with curl
                core_file_curl($updates[0]['Zip URL'], $ufile);
            }

            file_put_contents($ulog, "update downloaded\n", FILE_APPEND);
		}
		else
		{
			file_put_contents($ulog, "update already downloaded\n", FILE_APPEND);	
		}
		
		return update::install_from_file($updates[0]['Version'], $updates[0]['Revision'], $ufile, $ulog);
	}
	
	public static function install_from_repository($file_id)
	{
		global $DB;
		global $website;
		
		@set_time_limit(0);
		
		$latest = update::latest_installed();
		
		$ulog = NAVIGATE_PATH.'/updates/update-'.$latest->revision.'-custom-'.time().'.log.txt';
		
		file_put_contents($ulog, "UPDATE PROCESS ".$latest->version.'c '.$latest->revision.' (CUSTOM) on '.time()."\n", FILE_APPEND);
		
		// copy file from repository to updates folder
		$ufile = NAVIGATE_PATH.'/updates/update-'.$latest->revision.'c.zip';
		copy(NAVIGATE_PRIVATE.'/'.$website->id.'/files/'.$file_id, $ufile);
		
		return update::install_from_file($latest->version.'c', $latest->revision, $ufile, $ulog);
	}
		
	public static function install_from_file($version, $revision, $ufile, $ulog)
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
		$chmod_status = core_chmodr(NAVIGATE_PATH.'/updates/update', 0755);
		file_put_contents($ulog, $chmod_status."\n", FILE_APPEND);		
		
		// do file changes
		file_put_contents($ulog, "parse file changes\n", FILE_APPEND);
		$hgchanges = file_get_contents(NAVIGATE_PATH.'/updates/update/changes.txt');
		$hgchanges = explode("\n", $hgchanges);
		
		foreach($hgchanges as $change)
		{
			file_put_contents($ulog, $change."\n", FILE_APPEND);								
			$change = trim($change);
			if(empty($change)) continue;
		
			$change = explode(" ", $change, 2);

			// new, removed and modified files
			// M = modified
			// A = added
			// R = removed
			// C = clean
			// ! = missing (deleted by non-hg command, but still tracked)
			// ? = not tracked
			// I = ignored
			//   = origin of the previous file listed as A (added)
			
			$file = str_replace('\\', '/', $change[1]);

			//if(substr($file, 0, strlen('plugins/'))=='plugins/') continue;
			if(substr($file, 0, strlen('setup/'))=='setup/') continue;	
			
			switch($change[0])
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
					
				case 'R':
					// remove file
					@unlink(NAVIGATE_PATH.'/'.$file);
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
					if(empty($sqlline)) continue;
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
			file_put_contents($ulog, "execute insert failed:\n".$DB->get_last_error()."\n", FILE_APPEND);

        if(file_exists(NAVIGATE_PATH.'/updates/update/update-post.php'))
            include_once(NAVIGATE_PATH.'/updates/update/update-post.php');

		file_put_contents($ulog, "update finished!\n", FILE_APPEND);
		
		$urow->changelog = file_get_contents($ulog);
		$urow->save();

        @unlink($ufile);
		
		return true;
		
	}
}
?>