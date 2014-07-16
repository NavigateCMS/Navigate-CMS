<?php

class website
{
	public $id; 
	public $name;
	public $protocol; // default http://
	public $subdomain; // usually "www"
	public $domain; // naviwebs.net
	public $folder; // usually empty, used when the website is IN a folder, ex. http://www.naviwebs.com/demo/homepage
    public $redirect_to; // if the website is private or closed, redirect anonymous visitors to a real path
    public $wrong_path_action;
	public $languages; // array('en' => array( 'language' => 'en', 'variant' => 'US', 'code' => 'en_US' => 'system_locale' => 'ENU_USA'), 'es_ES' => array(...), ...)
	public $languages_published; // array ('en', 'es_ES')
	public $date_format;
	public $resize_uploaded_images;	// what to do with the uploaded images, 0 => keep original files, "yy" px => autoresize to yy pixels
	public $tinymce_css;
	public $statistics_script;
	public $permission;	//  0 => public | 1 => private | 2 => only navigate users
	public $block_types;
	public $homepage;
	public $default_timezone;
    public $aliases;
	//public $server_time_offset;
	public $metatag_description;  // multilanguage
	public $metatag_keywords;  // multilanguage
    public $metatags; // multilanguage
	public $mail_server;
	public $mail_port;
	public $mail_user;
	public $mail_address;
	public $mail_password;
    public $mail_security;
	public $contact_emails;
	public $favicon;
	
	public $theme;
	public $theme_options;
	
	public $languages_list;
	
	public function load($id="")
	{
		global $DB;
        global $user;
		
		if(empty($id)) // we suppose we only have one website or we just want the first
		{
			if($DB->query('SELECT * FROM nv_websites LIMIT 1'))
			{
				$data = $DB->result();
				$this->load_from_resultset($data); // we'll catch the first website there
			}			
		}
		else
		{
			if($DB->query('SELECT * FROM nv_websites WHERE id = '.intval($id)))
			{
				$data = $DB->result();
				$this->load_from_resultset($data);
			}
		}
	}
	
	public function load_from_resultset($rs)
	{
		$main = $rs[0];

		$this->id				= $main->id; 
		$this->name				= $main->name;

		// the fields below give an absoulte path to the website: protocol://subdomain.domain/folder
		$this->protocol			= $main->protocol;
		$this->subdomain		= $main->subdomain;
		$this->domain			= $main->domain;				
		$this->folder			= $main->folder;		

		$this->redirect_to		= $main->redirect_to;
        $this->wrong_path_action= $main->wrong_path_action;

		$this->languages		    = mb_unserialize($main->languages);
		$this->languages_published  = mb_unserialize($main->languages_published);
		$this->date_format		= $main->date_format;
		$this->tinymce_css		= $main->tinymce_css;
		$this->resize_uploaded_images = $main->resize_uploaded_images;

		$this->statistics_script= $main->statistics_script;
		$this->permission		= $main->permission;
		$this->block_types		= mb_unserialize($main->block_types);
		$this->homepage			= $main->homepage;	
		$this->default_timezone = $main->default_timezone;	
		$this->metatag_description	= json_decode($main->metatag_description, true);
		$this->metatag_keywords 	= json_decode($main->metatag_keywords, true);
		$this->metatags			    = json_decode($main->metatags, true);
		$this->favicon			= $main->favicon;
			
		$this->mail_server		= $main->mail_server;
		$this->mail_port		= $main->mail_port;
        $this->mail_security	= $main->mail_security;
		$this->mail_user		= $main->mail_user;
		$this->mail_address		= $main->mail_address;
		$this->mail_password	= $main->mail_password;	
		
		$this->theme			= $main->theme;
		$this->theme_options	= json_decode($main->theme_options);

        $this->aliases          = json_decode($main->aliases, true);
		
		$this->contact_emails	= mb_unserialize($main->contact_emails);
		if(!is_array($this->contact_emails)) $this->contact_emails = array();
				
		if(!is_array($this->languages)) $this->languages = array();
		$this->languages_list	= array_keys($this->languages);
		
		date_default_timezone_set($this->default_timezone);
	}
	
	public function load_from_post()
	{
		global $DB;
        global $theme;
					
		$this->name				= $_REQUEST['title'];
		
		$this->protocol			= $_REQUEST['protocol'];
		$this->subdomain		= $_REQUEST['subdomain'];
		$this->domain			= $_REQUEST['domain'];				
		$this->folder			= $_REQUEST['folder'];				

        $this->redirect_to		= $_REQUEST['redirect_to'];
        $this->wrong_path_action= $_REQUEST['wrong_path_action'];

        $this->date_format		= $_REQUEST['date_format'];
		$this->tinymce_css		= $_REQUEST['tinymce_css'];
		$this->resize_uploaded_images = intval($_REQUEST['resize_uploaded_images']);

		$this->statistics_script= $_REQUEST['statistics_script'];
		$this->homepage			= $_REQUEST['homepage'];		
		$this->permission		= intval($_REQUEST['permission']);
		$this->default_timezone	= $_REQUEST['default_timezone'];
		
		$this->mail_server		= $_REQUEST['mail_server'];
		$this->mail_port		= intval($_REQUEST['mail_port']);
        $this->mail_security	= intval($_REQUEST['mail_security']);
		$this->mail_user		= $_REQUEST['mail_user'];
		$this->mail_address		= $_REQUEST['mail_address'];

		if(!empty($_REQUEST['mail_password']))
			$this->mail_password	= $_REQUEST['mail_password'];			

		$ce	= explode("\n", $_REQUEST['contact_emails']);
			
		$this->contact_emails = array();
		foreach($ce as $cemail)
		{
			$cemail = trim($cemail);
			if(empty($cemail)) continue;
			$this->contact_emails[] = $cemail;
		}
		
		$this->favicon	= intval($_REQUEST['website-favicon']);

        // languages and locales
        $this->languages = array();
        $this->languages_published = array();
        for($li=0; $li < count($_REQUEST['language-id']); $li++)
        {
            $variant = trim($_REQUEST['language-variant-code'][$li]);
            $code = $_REQUEST['language-id'][$li];
            if(!empty($variant))
                $code .= '_'.$variant;

            $this->languages[$code] = array(
                'language' => $_REQUEST['language-id'][$li],
                'variant' => $variant,
                'code' => $code,
                'system_locale' => $_REQUEST['language-locale'][$li]
            );

            $this->languages_published[] = ($_REQUEST['language-published'][$li]=='1'? $code : '');
        }

        // Website metatags
        $this->metatag_description = array();
        $this->metatag_keywords = array();
        $this->metatags = array();

        foreach($this->languages as $language)
        {
            $lcode = $language['code'];
            $this->metatag_description[$lcode]	= $_REQUEST['metatag_description-'.$lcode];
            $this->metatag_keywords[$lcode]	= $_REQUEST['metatag_keywords-'.$lcode];
            $this->metatags[$lcode]	= $_REQUEST['metatags-'.$lcode];
        }

        $this->languages		    = serialize($this->languages); // already sorted
        $this->languages_published  = serialize($this->languages_published);

        // Website aliases
        $this->aliases = array();
        if(empty($_POST['website-aliases-alias']))
            $_POST['website-aliases-alias'] = array();
        foreach($_POST['website-aliases-alias'] as $key => $value)
        {
            $value = trim($value);
            if(!empty($value))
            {
                $value_real = trim($_POST['website-aliases-real'][$key]);
                if(!empty($value_real))
                    $this->aliases[$value] = $value_real;
            }
        }

        $this->theme_options = array();

        // Theme style (common property)
        $this->theme_options['style'] = $_REQUEST['property-style'];

        if(!empty($theme->options))
        {
            foreach($theme->options as $theme_option)
            {
                // get property info
                $property = new property();
                $property->load_from_theme($theme_option);

                $value = '';

                switch($property->type)
                {
                    case 'text':
                    case 'textarea':
                    case 'rich_textarea':
                        // multilang
                        $value = array();
                        if(!is_array($this->languages_list))
                            $this->languages_list = array();
                        foreach($this->languages_list as $lang)
                            $value[$lang] = $_REQUEST['property-'.$theme_option->id.'-'.$lang];
                        break;

                    case 'link':
                        // multilang and title+link
                        $value = array();
                        foreach($this->languages_list as $lang)
                            $value[$lang] = $_REQUEST['property-'.$theme_option->id.'-'.$lang.'-link'].'##'.$_REQUEST['property-'.$theme_option->id.'-'.$lang.'-title'];
                        break;

                    case 'date':
                    case 'datetime':
                        $value = core_date2ts($_REQUEST['property-'.$theme_option->id]);
                        break;

                    case 'moption':
                        $value = implode(',', $_REQUEST['property-'.$theme_option->id]);
                        break;

                    case 'coordinates':
                        $value = $_REQUEST['property-'.$theme_option->id.'-latitude'].'#'.$_REQUEST['property-'.$theme_option->id.'-longitude'];
                        break;

                    default:
                        // direct value
                       $value = $_REQUEST['property-'.$theme_option->id];
                }

                $this->theme_options[$theme_option->id] = $value;
            }
        }
	}
		
	public function save()
	{
		global $DB;

		if(!empty($this->id))
			return $this->update();
		else
			return $this->insert();			
	}
	
	public function delete($delete_related_content=true)
	{
		global $DB;

        set_time_limit(300);

		if(!empty($this->id))
		{
            if($delete_related_content)
            {
                // delete all content related to the website
                // EXCEPTION: webusers, as they may be shared between websites
                $tables = array(
                    'nv_comments',              // comments
                    'nv_extensions',            // extensions settings
                    'nv_notes',                 // notes
                    'nv_paths',                 // paths
                    'nv_properties',            // custom properties definitions
                    'nv_properties_items',      // custom properties values
                    'nv_templates',             //  templates
                    'nv_webuser_votes',         //  webusers votes
                    'nv_webuser_favorites',     //  webusers favorites
                    'nv_blocks',                //  blocks
                    'nv_items',                 //  elements
                    'nv_feeds',                 //  feeds
                    'nv_structure',             //  structure
                    'nv_webdictionary',         //  web dictionary
                    'nv_webdictionary_history', // web dictionary history
                    'nv_backups',               // backups
                    'nv_files',                 // files & folders
                );

                foreach($tables as $table)
                {
                    $DB->execute('
                        DELETE FROM '.$table.'
                         WHERE website = '.intval($this->id).'
                    ');
                }

                // finally, remove ALL files associated to the website
                // including: images, documents, thumbnails, backups, cache, custom templates...
                core_remove_folder(NAVIGATE_PRIVATE.'/'.$this->id);
            }

            // finally delete the website entry
			$DB->execute('
			    DELETE FROM nv_websites
				 WHERE id = '.intval($this->id).'
				 LIMIT 1
            ');
		}
		
		// send statistics to navigatecms.com
		if(NAVIGATECMS_STATS)
		{
			global $user;
			@core_curl_post('http://statistics.navigatecms.com/website/remove',
							array(
								'name' => $this->name,
								'ip' => $_SERVER['SERVER_ADDR'],
								'website_id' => $this->id,
								'url' => $this->absolute_path(),
								'folder' => $this->folder,
								'homepage' => $this->homepage,
								'theme' => $this->theme,								
								'emails' => serialize($this->contact_emails),
								'languages' => $this->languages_published,
								'permission' => $this->permission,
								'author_name' => $user->username,
								'author_email' => $user->email,
								'author_language' => $user->language
							));
		}		
		
		return $DB->get_affected_rows();		
	}
	
	public function insert()
	{
		global $DB;
			
		$ok = $DB->execute(' INSERT INTO nv_websites
								(	id, name, protocol, subdomain, domain, folder, redirect_to, wrong_path_action,
									languages, languages_published,
									aliases, date_format, tinymce_css, resize_uploaded_images,
									statistics_script, permission,
									mail_server, mail_port, mail_security, mail_user, mail_address, mail_password, contact_emails,
									homepage, default_timezone, metatag_description, metatag_keywords, metatags,
									favicon, theme, theme_options
								)
								VALUES 
								( 0,
								  '.protect($this->name).',
								  '.protect($this->protocol).',								  
								  '.protect($this->subdomain).',
								  '.protect($this->domain).',
								  '.protect($this->folder).',								  
								  '.protect($this->redirect_to).',
								  '.protect($this->wrong_path_action).',
								  '.protect($this->languages).',
								  '.protect($this->languages_published).',
								  '.protect(json_encode($this->aliases)).',
								  '.protect($this->date_format).',
								  '.protect($this->tinymce_css).',
								  '.protect($this->resize_uploaded_images).',
								  '.protect($this->statistics_script).',
								  '.protect($this->permission).',
								  '.protect($this->mail_server).',
								  '.protect($this->mail_port).',
								  '.protect($this->mail_security).',
								  '.protect($this->mail_user).',			
								  '.protect($this->mail_address).',			
								  '.protect($this->mail_password).',				
								  '.protect(serialize($this->contact_emails)).',
								  '.protect($this->homepage).',			
								  '.protect($this->default_timezone).',	
								  '.protect(json_encode($this->metatag_description)).',
								  '.protect(json_encode($this->metatag_keywords)).',
								  '.protect(json_encode($this->metatags)).',
								  '.protect($this->favicon).',
								  '.protect($this->theme).',
								  '.protect(json_encode($this->theme_options)).'				  
								)');
		
		if(!$ok) throw new Exception($DB->get_last_error());
		
		// finally we create the private folder
		$this->id = $DB->get_last_id();
		
		@mkdir(NAVIGATE_PRIVATE.'/'.$this->id, 0755, true);
		@mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/files', 0755, true);
		@mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/templates', 0755, true);
		@mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/thumbnails', 0755, true);
		@mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/webgets', 0755, true);
        @mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/backups', 0755, true);
        @mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/cache', 0755, true);

		// send statistics to navigatecms.com
		if(NAVIGATECMS_STATS)
		{
			global $user;
			@core_curl_post('http://statistics.navigatecms.com/website/new',
							array(
								'name' => $this->name,
								'ip' => $_SERVER['SERVER_ADDR'],
								'website_id' => $this->id,
								'url' => $this->absolute_path(),
								'folder' => $this->folder,
								'homepage' => $this->homepage,
								'theme' => $this->theme,
								'emails' => serialize($this->contact_emails),
								'languages' => $this->languages_published,
								'permission' => $this->permission,
								'author_name' => $user->username,
								'author_email' => $user->email,
								'author_language' => $user->language
							));
		}
		
		return true;
	}	
	
	public function update()
	{
		global $DB;

        if(is_array($this->languages))
            $this->languages = serialize($this->languages);

        if(is_array($this->languages_published))
            $this->languages_published = serialize($this->languages_published);

        $ok = $DB->execute('
            UPDATE nv_websites
                SET
                    name	=   ?,
                    protocol =  ?,
                    subdomain = ?,
                    domain	=   ?,
                    folder	=   ?,
                    redirect_to = ?,
                    wrong_path_action = ?,
                    languages = ?,
                    languages_published = ?,
                    aliases = ?,
                    date_format = ?,
                    tinymce_css = ?,
                    resize_uploaded_images = ?,
                    statistics_script = ?,
                    permission = ?,
                    mail_server = ?,
                    mail_port = ?,
                    mail_security = ?,
                    mail_user = ?,
                    mail_address = ?,
                    mail_password = ?,
                    contact_emails = ?,
                    homepage = ?,
                    default_timezone = ?,
                    metatag_description = ?,
                    metatag_keywords = ?,
                    metatags = ?,
                    favicon = ?,
                    theme = ?,
                    theme_options = ?
            WHERE id = '.$this->id,
            array(
                $this->name,
                $this->protocol,
                $this->subdomain,
                $this->domain,
                $this->folder,
                $this->redirect_to,
                $this->wrong_path_action,
                $this->languages,
                $this->languages_published,
                json_encode($this->aliases),
                $this->date_format,
                $this->tinymce_css,
                $this->resize_uploaded_images,
                $this->statistics_script,
                $this->permission,
                $this->mail_server,
                $this->mail_port,
                $this->mail_security,
                $this->mail_user,
                $this->mail_address,
                $this->mail_password,
                serialize($this->contact_emails),
                $this->homepage,
                $this->default_timezone,
                json_encode($this->metatag_description),
                json_encode($this->metatag_keywords),
                json_encode($this->metatags),
                $this->favicon,
                $this->theme,
                json_encode($this->theme_options)
            )
        );

		if(!$ok) throw new Exception($DB->get_last_error());
		
		if(!file_exists(NAVIGATE_PRIVATE.'/'.$this->id))
		{
			@mkdir(NAVIGATE_PRIVATE.'/'.$this->id, 0755, true);
			@mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/files', 0755, true);
			@mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/templates', 0755, true);
			@mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/thumbnails', 0755, true);
			@mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/webgets', 0755, true);
            @mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/backups', 0755, true);
            @mkdir(NAVIGATE_PRIVATE.'/'.$this->id.'/cache', 0755, true);
		}
		
		// send statistics to navigatecms.com
		if(NAVIGATECMS_STATS)
		{
			global $user;
			@core_curl_post('http://statistics.navigatecms.com/website/update',
							array(
								'ip' => $_SERVER['SERVER_ADDR'],
								'website_id' => $this->id,
								'name' => $this->name,
								'url' => $this->absolute_path(),
								'folder' => $this->folder,
								'homepage' => $this->homepage,
								'theme' => $this->theme,
								'emails' => serialize($this->contact_emails),
								'languages' => $this->languages_published,
								'permission' => $this->permission,
								'author_name' => $user->username,
								'author_email' => $user->email,
								'author_language' => $user->language
							));
		}		
		
		return true;
	}

    function create_default()
    {
        global $DB;
        global $user;

        // check if there are really NO websites
        $test = $DB->query_single('id', 'nv_websites');
        if(!empty($test))
        {
            //header('location: '.NAVIGATE_MAIN.'?logout');
            //core_terminate();
            $this->load();
            return $this;
        }

        $url = NAVIGATE_URL;
        $url = parse_url($url);

        // do we have a subdomain in the url?
        if(preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $url['host']))
        {
            $domain = $url['host'];
            $subdomain = '';
        }
        else
        {
            $host = explode('.', $url['host']);

            $domain = array_pop($host);
            if(!empty($host))
                $domain = array_pop($host) . '.' . $domain;

            $subdomain = implode('.', $host);
        }

        // navigate url --> default folder
        // http://localhost/navigate            -->     (empty)
        // http://www.domain.com/test/navigate  -->     /test
        // http://192.168.0.1/foo/foo2/navigate -->     /foo/foo2

        $folder = dirname($url['path']);
        if($folder=='/' || $folder=='\\')
            $folder = '';

        $this->name				= APP_OWNER;

        $this->protocol			= "http://";
        $this->subdomain		= $subdomain;
        $this->domain			= $domain;
        $this->folder			= $folder;
        $this->redirect_to      = '';
        $this->date_format		= 'Y/m/d';
        $this->homepage			= '/en/home';
        $this->permission		= 0;
        $this->default_timezone	= 'UTC';
        $this->contact_emails   = $user->email;

        // languages and locales
        $this->languages = array();
        $this->languages_published = array();

        $this->languages['en'] = array(
            'language' => 'en',
            'variant' => '',
            'code' => 'en',
            'system_locale' => ''
        );

        $this->languages_published[] = 'en';

        $this->languages		    = serialize($this->languages);
        $this->languages_published  = serialize($this->languages_published);

        $this->aliases = array();
        $this->theme_options = array();

        $this->insert();

        return true;
    }

	function language_compare($a, $b)
	{
		if(array_search($a->code, $this->languages_list) < array_search($b->code, $this->languages_list))
			return -1;
		else
			return 1;
	}
	
	function absolute_path($folder=true)
	{	
		$nvweb_absolute = (empty($this->protocol)? 'http://' : $this->protocol);
		if(!empty($this->subdomain))
			$nvweb_absolute .= $this->subdomain.'.';
		$nvweb_absolute .= $this->domain;
		if(!empty($folder))
			$nvweb_absolute .= $this->folder;

		return $nvweb_absolute;
	}
	
	function current_time()
	{
		$utc = core_time();
		
		$utc_timezone = new DateTimeZone('UTC');
		$utc_time = new DateTime('now', $utc_timezone);
		$website_timezone = new DateTimeZone($this->default_timezone);
		$website_time = new DateTime('now', $website_timezone);

	    $offset = $website_timezone->getOffset($utc_time);
   		$ts = $utc + $offset;

		return $ts;
	}

    public function languages()
    {
        $options = array();
        foreach($this->languages_list as $active_language_code)
            $options[$active_language_code] = language::name_by_code($active_language_code);

        return $options;
    }

    public function content_stylesheets($format='tinymce')
    {
        global $theme;

        // determine stylesheets for content (website > theme + default navigate cms)
        $content_css = array();

        $content_css[] = NAVIGATE_URL.'/css/tools/tinymce.defaults.css';

        if(!empty($this->tinymce_css))
            $content_css[] = $this->tinymce_css.'?bogus='.time();

        if(!empty($this->theme) && !empty($theme))
        {
            $style = $this->theme_options->style;
            if(!empty($style) && !empty($theme->styles->$style->content))
            {
                $style_content_css = explode(',', $theme->styles->$style->content);
                foreach($style_content_css as $scc)
                {
                    if(strpos($scc, 'http')===false)
                        $content_css[] = NAVIGATE_URL.'/themes/'.$this->theme.'/'.$scc.'?bogus='.time();
                    else
                        $content_css[] = $scc;
                }
            }
        }

        if($format=='link_tag')
        {
            $content_html = '';
            foreach($content_css as $csa)
            {
                if(!empty($csa))
                    $content_html .= '<link rel="stylesheet" type="text/css" href="'.trim($csa).'" />'."\n";
            }

            $content_css = $content_html;
        }
        else
            $content_css = implode(',', $content_css);

        return $content_css;
    }

    public function backup($type='json')
    {
        global $DB;
        global $website;

        $out = array();

        $DB->query('SELECT * FROM nv_websites WHERE id = '.protect($website->id), 'object');

        if($type='json')
            $out = json_encode($DB->result());

        return $out;
    }

    public function windows_locales()
    {
        global $session;

        // http://www.microsoft.com/resources/msdn/goglobal/default.mspx?submitted=3409&OS=Windows+Vista
        $win_loc = '{   "af":{"id":"0x0036","lang_name":"Afrikaans","country":"ZAF","locale":"AFK_ZAF"},
                        "af-ZA":{"id":"0x0436","lang_name":"Afrikaans","country":"ZAF","locale":"AFK_ZAF"},
                        "sq":{"id":"0x001C","lang_name":"Albanian","country":"ALB","locale":"SQI_ALB"},
                        "sq-AL":{"id":"0x041C","lang_name":"Albanian","country":"ALB","locale":"SQI_ALB"},
                        "gsw":{"id":"0x0084","lang_name":"Alsatian","country":"FRA","locale":"GSW_FRA"},
                        "gsw-FR":{"id":"0x0484","lang_name":"Alsatian","country":"FRA","locale":"GSW_FRA"},
                        "am":{"id":"0x005E","lang_name":"Amharic","country":"ETH","locale":"AMH_ETH"},
                        "am-ET":{"id":"0x045E","lang_name":"Amharic","country":"ETH","locale":"AMH_ETH"},
                        "ar":{"id":"0x0001","lang_name":"Arabic","country":"SAU","locale":"ARA_SAU"},
                        "ar-DZ":{"id":"0x1401","lang_name":"Arabic","country":"DZA","locale":"ARG_DZA"},
                        "ar-BH":{"id":"0x3C01","lang_name":"Arabic","country":"BHR","locale":"ARH_BHR"},
                        "ar-EG":{"id":"0x0C01","lang_name":"Arabic","country":"EGY","locale":"ARE_EGY"},
                        "ar-IQ":{"id":"0x0801","lang_name":"Arabic","country":"IRQ","locale":"ARI_IRQ"},
                        "ar-JO":{"id":"0x2C01","lang_name":"Arabic","country":"JOR","locale":"ARJ_JOR"},
                        "ar-KW":{"id":"0x3401","lang_name":"Arabic","country":"KWT","locale":"ARK_KWT"},
                        "ar-LB":{"id":"0x3001","lang_name":"Arabic","country":"LBN","locale":"ARB_LBN"},
                        "ar-LY":{"id":"0x1001","lang_name":"Arabic","country":"LBY","locale":"ARL_LBY"},
                        "ar-MA":{"id":"0x1801","lang_name":"Arabic","country":"MAR","locale":"ARM_MAR"},
                        "ar-OM":{"id":"0x2001","lang_name":"Arabic","country":"OMN","locale":"ARO_OMN"},
                        "ar-QA":{"id":"0x4001","lang_name":"Arabic","country":"QAT","locale":"ARQ_QAT"},
                        "ar-SA":{"id":"0x0401","lang_name":"Arabic","country":"SAU","locale":"ARA_SAU"},
                        "ar-SY":{"id":"0x2801","lang_name":"Arabic","country":"SYR","locale":"ARS_SYR"},
                        "ar-TN":{"id":"0x1C01","lang_name":"Arabic","country":"TUN","locale":"ART_TUN"},
                        "ar-AE":{"id":"0x3801","lang_name":"Arabic","country":"ARE","locale":"ARU_ARE"},
                        "ar-YE":{"id":"0x2401","lang_name":"Arabic","country":"YEM","locale":"ARY_YEM"},
                        "hy":{"id":"0x002B","lang_name":"Armenian","country":"ARM","locale":"HYE_ARM"},
                        "hy-AM":{"id":"0x042B","lang_name":"Armenian","country":"ARM","locale":"HYE_ARM"},
                        "as":{"id":"0x004D","lang_name":"Assamese","country":"IND","locale":"ASM_IND"},
                        "as-IN":{"id":"0x044D","lang_name":"Assamese","country":"IND","locale":"ASM_IND"},
                        "az":{"id":"0x002C","lang_name":"Azeri (Latin)","country":"AZE","locale":"AZE_AZE"},
                        "az-Cyrl":{"id":"0x742C","lang_name":"Azeri (Cyrillic)","country":"AZE","locale":"AZC_AZE"},
                        "az-Cyrl-AZ":{"id":"0x082C","lang_name":"Azeri (Cyrillic)","country":"AZE","locale":"AZC_AZE"},
                        "az-Latn":{"id":"0x782C","lang_name":"Azeri (Latin)","country":"AZE","locale":"AZE_AZE"},
                        "az-Latn-AZ":{"id":"0x042C","lang_name":"Azeri (Latin)","country":"AZE","locale":"AZE_AZE"},
                        "ba":{"id":"0x006D","lang_name":"Bashkir","country":"RUS","locale":"BAS_RUS"},
                        "ba-RU":{"id":"0x046D","lang_name":"Bashkir","country":"RUS","locale":"BAS_RUS"},
                        "eu":{"id":"0x002D","lang_name":"Basque","country":"ESP","locale":"EUQ_ESP"},
                        "eu-ES":{"id":"0x042D","lang_name":"Basque","country":"ESP","locale":"EUQ_ESP"},
                        "be":{"id":"0x0023","lang_name":"Belarusian","country":"BLR","locale":"BEL_BLR"},
                        "be-BY":{"id":"0x0423","lang_name":"Belarusian","country":"BLR","locale":"BEL_BLR"},
                        "bn":{"id":"0x0045","lang_name":"Bengali","country":"IND","locale":"BNG_IND"},
                        "bn-BD":{"id":"0x0845","lang_name":"Bengali","country":"BGD","locale":"BNB_BGD"},
                        "bn-IN":{"id":"0x0445","lang_name":"Bengali","country":"IND","locale":"BNG_IND"},
                        "bs":{"id":"0x781A","lang_name":"Bosnian (Latin)","country":"BIH","locale":"BSB_BIH"},
                        "bs-Cyrl":{"id":"0x641A","lang_name":"Bosnian (Cyrillic)","country":"BIH","locale":"BSC_BIH"},
                        "bs-Cyrl-BA":{"id":"0x201A","lang_name":"Bosnian (Cyrillic)","country":"BIH","locale":"BSC_BIH"},
                        "bs-Latn":{"id":"0x681A","lang_name":"Bosnian (Latin)","country":"BIH","locale":"BSB_BIH"},
                        "bs-Latn-BA":{"id":"0x141A","lang_name":"Bosnian (Latin)","country":"BIH","locale":"BSB_BIH"},
                        "br":{"id":"0x007E","lang_name":"Breton","country":"FRA","locale":"BRE_FRA"},
                        "br-FR":{"id":"0x047E","lang_name":"Breton","country":"FRA","locale":"BRE_FRA"},
                        "bg":{"id":"0x0002","lang_name":"Bulgarian","country":"BGR","locale":"BGR_BGR"},
                        "bg-BG":{"id":"0x0402","lang_name":"Bulgarian","country":"BGR","locale":"BGR_BGR"},
                        "ca":{"id":"0x0003","lang_name":"Catalan","country":"ESP","locale":"CAT_ESP"},
                        "ca-ES":{"id":"0x0403","lang_name":"Catalan","country":"ESP","locale":"CAT_ESP"},
                        "zh":{"id":"0x7804","lang_name":"Chinese (Simplified)","country":"CHN","locale":"CHS_CHN"},
                        "zh-Hans":{"id":"0x0004","lang_name":"Chinese (Simplified)","country":"CHN","locale":"CHS_CHN"},
                        "zh-CN":{"id":"0x0804","lang_name":"Chinese (Simplified)","country":"CHN","locale":"CHS_CHN"},
                        "zh-SG":{"id":"0x1004","lang_name":"Chinese (Simplified)","country":"SGP","locale":"ZHI_SGP"},
                        "zh-Hant":{"id":"0x7C04","lang_name":"Chinese (Traditional)","country":"HKG","locale":"ZHH_HKG"},
                        "zh-HK":{"id":"0x0C04","lang_name":"Chinese (Traditional)","country":"HKG","locale":"ZHH_HKG"},
                        "zh-MO":{"id":"0x1404","lang_name":"Chinese (Traditional)","country":"MCO","locale":"ZHM_MCO"},
                        "zh-TW":{"id":"0x0404","lang_name":"Chinese (Traditional)","country":"TWN","locale":"CHT_TWN"},
                        "co":{"id":"0x0083","lang_name":"Corsican","country":"FRA","locale":"COS_FRA"},
                        "co-FR":{"id":"0x0483","lang_name":"Corsican","country":"FRA","locale":"COS_FRA"},
                        "hr":{"id":"0x001A","lang_name":"Croatian","country":"HRV","locale":"HRV_HRV"},
                        "hr-HR":{"id":"0x041A","lang_name":"Croatian","country":"HRV","locale":"HRV_HRV"},
                        "hr-BA":{"id":"0x101A","lang_name":"Croatian (Latin)","country":"BIH","locale":"HRB_BIH"},
                        "cs":{"id":"0x0005","lang_name":"Czech","country":"CZE","locale":"CSY_CZE"},
                        "cs-CZ":{"id":"0x0405","lang_name":"Czech","country":"CZE","locale":"CSY_CZE"},
                        "da":{"id":"0x0006","lang_name":"Danish","country":"DNK","locale":"DAN_DNK"},
                        "da-DK":{"id":"0x0406","lang_name":"Danish","country":"DNK","locale":"DAN_DNK"},
                        "prs":{"id":"0x008C","lang_name":"Dari","country":"AFG","locale":"PRS_AFG"},
                        "prs-AF":{"id":"0x048C","lang_name":"Dari","country":"AFG","locale":"PRS_AFG"},
                        "dv":{"id":"0x0065","lang_name":"Divehi","country":"MDV","locale":"DIV_MDV"},
                        "dv-MV":{"id":"0x0465","lang_name":"Divehi","country":"MDV","locale":"DIV_MDV"},
                        "nl":{"id":"0x0013","lang_name":"Dutch","country":"NLD","locale":"NLD_NLD"},
                        "nl-BE":{"id":"0x0813","lang_name":"Dutch","country":"BEL","locale":"NLB_BEL"},
                        "nl-NL":{"id":"0x0413","lang_name":"Dutch","country":"NLD","locale":"NLD_NLD"},
                        "en":{"id":"0x0009","lang_name":"English","country":"USA","locale":"ENU_USA"},
                        "en-AU":{"id":"0x0C09","lang_name":"English","country":"AUS","locale":"ENA_AUS"},
                        "en-BZ":{"id":"0x2809","lang_name":"English","country":"BLZ","locale":"ENL_BLZ"},
                        "en-CA":{"id":"0x1009","lang_name":"English","country":"CAN","locale":"ENC_CAN"},
                        "en-029":{"id":"0x2409","lang_name":"English","country":"CAR","locale":"ENB_CAR"},
                        "en-IN":{"id":"0x4009","lang_name":"English","country":"IND","locale":"ENN_IND"},
                        "en-IE":{"id":"0x1809","lang_name":"English","country":"IRL","locale":"ENI_IRL"},
                        "en-JM":{"id":"0x2009","lang_name":"English","country":"JAM","locale":"ENJ_JAM"},
                        "en-MY":{"id":"0x4409","lang_name":"English","country":"MYS","locale":"ENM_MYS"},
                        "en-NZ":{"id":"0x1409","lang_name":"English","country":"NZL","locale":"ENZ_NZL"},
                        "en-PH":{"id":"0x3409","lang_name":"English","country":"PHL","locale":"ENP_PHL"},
                        "en-SG":{"id":"0x4809","lang_name":"English","country":"SGP","locale":"ENE_SGP"},
                        "en-ZA":{"id":"0x1C09","lang_name":"English","country":"ZAF","locale":"ENS_ZAF"},
                        "en-TT":{"id":"0x2C09","lang_name":"English","country":"TTO","locale":"ENT_TTO"},
                        "en-GB":{"id":"0x0809","lang_name":"English","country":"GBR","locale":"ENG_GBR"},
                        "en-US":{"id":"0x0409","lang_name":"English","country":"USA","locale":"ENU_USA"},
                        "en-ZW":{"id":"0x3009","lang_name":"English","country":"ZWE","locale":"ENW_ZWE"},
                        "et":{"id":"0x0025","lang_name":"Estonian","country":"EST","locale":"ETI_EST"},
                        "et-EE":{"id":"0x0425","lang_name":"Estonian","country":"EST","locale":"ETI_EST"},
                        "fo":{"id":"0x0038","lang_name":"Faroese","country":"FRO","locale":"FOS_FRO"},
                        "fo-FO":{"id":"0x0438","lang_name":"Faroese","country":"FRO","locale":"FOS_FRO"},
                        "fil":{"id":"0x0064","lang_name":"Filipino","country":"PHL","locale":"FPO_PHL"},
                        "fil-PH":{"id":"0x0464","lang_name":"Filipino","country":"PHL","locale":"FPO_PHL"},
                        "fi":{"id":"0x000B","lang_name":"Finnish","country":"FIN","locale":"FIN_FIN"},
                        "fi-FI":{"id":"0x040B","lang_name":"Finnish","country":"FIN","locale":"FIN_FIN"},
                        "fr":{"id":"0x000C","lang_name":"French","country":"FRA","locale":"FRA_FRA"},
                        "fr-BE":{"id":"0x080C","lang_name":"French","country":"BEL","locale":"FRB_BEL"},
                        "fr-CA":{"id":"0x0C0C","lang_name":"French","country":"CAN","locale":"FRC_CAN"},
                        "fr-FR":{"id":"0x040C","lang_name":"French","country":"FRA","locale":"FRA_FRA"},
                        "fr-LU":{"id":"0x140C","lang_name":"French","country":"LUX","locale":"FRL_LUX"},
                        "fr-MC":{"id":"0x180C","lang_name":"French","country":"MCO","locale":"FRM_MCO"},
                        "fr-CH":{"id":"0x100C","lang_name":"French","country":"CHE","locale":"FRS_CHE"},
                        "fy":{"id":"0x0062","lang_name":"Frisian","country":"NLD","locale":"FYN_NLD"},
                        "fy-NL":{"id":"0x0462","lang_name":"Frisian","country":"NLD","locale":"FYN_NLD"},
                        "gl":{"id":"0x0056","lang_name":"Galician","country":"ESP","locale":"GLC_ESP"},
                        "gl-ES":{"id":"0x0456","lang_name":"Galician","country":"ESP","locale":"GLC_ESP"},
                        "ka":{"id":"0x0037","lang_name":"Georgian","country":"GEO","locale":"KAT_GEO"},
                        "ka-GE":{"id":"0x0437","lang_name":"Georgian","country":"GEO","locale":"KAT_GEO"},
                        "de":{"id":"0x0007","lang_name":"German","country":"DEU","locale":"DEU_DEU"},
                        "de-AT":{"id":"0x0C07","lang_name":"German","country":"AUT","locale":"DEA_AUT"},
                        "de-DE":{"id":"0x0407","lang_name":"German","country":"DEU","locale":"DEU_DEU"},
                        "de-LI":{"id":"0x1407","lang_name":"German","country":"LIE","locale":"DEC_LIE"},
                        "de-LU":{"id":"0x1007","lang_name":"German","country":"LUX","locale":"DEL_LUX"},
                        "de-CH":{"id":"0x0807","lang_name":"German","country":"CHE","locale":"DES_CHE"},
                        "el":{"id":"0x0008","lang_name":"Greek","country":"GRC","locale":"ELL_GRC"},
                        "el-GR":{"id":"0x0408","lang_name":"Greek","country":"GRC","locale":"ELL_GRC"},
                        "kl":{"id":"0x006F","lang_name":"Greenlandic","country":"GRL","locale":"KAL_GRL"},
                        "kl-GL":{"id":"0x046F","lang_name":"Greenlandic","country":"GRL","locale":"KAL_GRL"},
                        "gu":{"id":"0x0047","lang_name":"Gujarati","country":"IND","locale":"GUJ_IND"},
                        "gu-IN":{"id":"0x0447","lang_name":"Gujarati","country":"IND","locale":"GUJ_IND"},
                        "ha":{"id":"0x0068","lang_name":"Hausa (Latin)","country":"NGA","locale":"HAU_NGA"},
                        "ha-Latn":{"id":"0x7C68","lang_name":"Hausa (Latin)","country":"NGA","locale":"HAU_NGA"},
                        "ha-Latn-NG":{"id":"0x0468","lang_name":"Hausa (Latin)","country":"NGA","locale":"HAU_NGA"},
                        "he":{"id":"0x000D","lang_name":"Hebrew","country":"ISR","locale":"HEB_ISR"},
                        "he-IL":{"id":"0x040D","lang_name":"Hebrew","country":"ISR","locale":"HEB_ISR"},
                        "hi":{"id":"0x0039","lang_name":"Hindi","country":"IND","locale":"HIN_IND"},
                        "hi-IN":{"id":"0x0439","lang_name":"Hindi","country":"IND","locale":"HIN_IND"},
                        "hu":{"id":"0x000E","lang_name":"Hungarian","country":"HUN","locale":"HUN_HUN"},
                        "hu-HU":{"id":"0x040E","lang_name":"Hungarian","country":"HUN","locale":"HUN_HUN"},
                        "is":{"id":"0x000F","lang_name":"Icelandic","country":"ISL","locale":"ISL_ISL"},
                        "is-IS":{"id":"0x040F","lang_name":"Icelandic","country":"ISL","locale":"ISL_ISL"},
                        "ig":{"id":"0x0070","lang_name":"Igbo","country":"NGA","locale":"IBO_NGA"},
                        "ig-NG":{"id":"0x0470","lang_name":"Igbo","country":"NGA","locale":"IBO_NGA"},
                        "id":{"id":"0x0021","lang_name":"Indonesian","country":"IDN","locale":"IND_IDN"},
                        "id-ID":{"id":"0x0421","lang_name":"Indonesian","country":"IDN","locale":"IND_IDN"},
                        "iu":{"id":"0x005D","lang_name":"Inuktitut (Latin)","country":"CAN","locale":"IUK_CAN"},
                        "iu-Latn":{"id":"0x7C5D","lang_name":"Inuktitut (Latin)","country":"CAN","locale":"IUK_CAN"},
                        "iu-Latn-CA":{"id":"0x085D","lang_name":"Inuktitut (Latin)","country":"CAN","locale":"IUK_CAN"},
                        "iu-Cans":{"id":"0x785D","lang_name":"Inuktitut (Syllabics)","country":"CAN","locale":"IUS_CAN"},
                        "iu-Cans-CA":{"id":"0x045D","lang_name":"Inuktitut (Syllabics)","country":"CAN","locale":"IUS_CAN"},
                        "ga":{"id":"0x003C","lang_name":"Irish","country":"IRL","locale":"IRE_IRL"},
                        "ga-IE":{"id":"0x083C","lang_name":"Irish","country":"IRL","locale":"IRE_IRL"},
                        "xh":{"id":"0x0034","lang_name":"isiXhosa","country":"ZAF","locale":"XHO_ZAF"},
                        "xh-ZA":{"id":"0x0434","lang_name":"isiXhosa","country":"ZAF","locale":"XHO_ZAF"},
                        "zu":{"id":"0x0035","lang_name":"isiZulu","country":"ZAF","locale":"ZUL_ZAF"},
                        "zu-ZA":{"id":"0x0435","lang_name":"isiZulu","country":"ZAF","locale":"ZUL_ZAF"},
                        "it":{"id":"0x0010","lang_name":"Italian","country":"ITA","locale":"ITA_ITA"},
                        "it-IT":{"id":"0x0410","lang_name":"Italian","country":"ITA","locale":"ITA_ITA"},
                        "it-CH":{"id":"0x0810","lang_name":"Italian","country":"CHE","locale":"ITS_CHE"},
                        "ja":{"id":"0x0011","lang_name":"Japanese","country":"JPN","locale":"JPN_JPN"},
                        "ja-JP":{"id":"0x0411","lang_name":"Japanese","country":"JPN","locale":"JPN_JPN"},
                        "kn":{"id":"0x004B","lang_name":"Kannada","country":"IND","locale":"KDI_IND"},
                        "kn-IN":{"id":"0x044B","lang_name":"Kannada","country":"IND","locale":"KDI_IND"},
                        "kk":{"id":"0x003F","lang_name":"Kazakh","country":"KAZ","locale":"KKZ_KAZ"},
                        "kk-KZ":{"id":"0x043F","lang_name":"Kazakh","country":"KAZ","locale":"KKZ_KAZ"},
                        "km":{"id":"0x0053","lang_name":"Khmer","country":"KHM","locale":"KHM_KHM"},
                        "km-KH":{"id":"0x0453","lang_name":"Khmer","country":"KHM","locale":"KHM_KHM"},
                        "qut":{"id":"0x0086","lang_name":"K\'iche","country":"GTM","locale":"QUT_GTM"},
                        "qut-GT":{"id":"0x0486","lang_name":"K\'iche","country":"GTM","locale":"QUT_GTM"},
                        "rw":{"id":"0x0087","lang_name":"Kinyarwanda","country":"RWA","locale":"KIN_RWA"},
                        "rw-RW":{"id":"0x0487","lang_name":"Kinyarwanda","country":"RWA","locale":"KIN_RWA"},
                        "sw":{"id":"0x0041","lang_name":"Kiswahili","country":"KEN","locale":"SWK_KEN"},
                        "sw-KE":{"id":"0x0441","lang_name":"Kiswahili","country":"KEN","locale":"SWK_KEN"},
                        "kok":{"id":"0x0057","lang_name":"Konkani","country":"IND","locale":"KNK_IND"},
                        "kok-IN":{"id":"0x0457","lang_name":"Konkani","country":"IND","locale":"KNK_IND"},
                        "ko":{"id":"0x0012","lang_name":"Korean","country":"KOR","locale":"KOR_KOR"},
                        "ko-KR":{"id":"0x0412","lang_name":"Korean","country":"KOR","locale":"KOR_KOR"},
                        "ky":{"id":"0x0040","lang_name":"Kyrgyz","country":"KGZ","locale":"KYR_KGZ"},
                        "ky-KG":{"id":"0x0440","lang_name":"Kyrgyz","country":"KGZ","locale":"KYR_KGZ"},
                        "lo":{"id":"0x0054","lang_name":"Lao","country":"LAO","locale":"LAO_LAO"},
                        "lo-LA":{"id":"0x0454","lang_name":"Lao","country":"LAO","locale":"LAO_LAO"},
                        "lv":{"id":"0x0026","lang_name":"Latvian","country":"LVA","locale":"LVI_LVA"},
                        "lv-LV":{"id":"0x0426","lang_name":"Latvian","country":"LVA","locale":"LVI_LVA"},
                        "lt":{"id":"0x0027","lang_name":"Lithuanian","country":"LTU","locale":"LTH_LTU"},
                        "lt-LT":{"id":"0x0427","lang_name":"Lithuanian","country":"LTU","locale":"LTH_LTU"},
                        "dsb":{"id":"0x7C2E","lang_name":"Lower Sorbian","country":"GER","locale":"DSB_GER"},
                        "dsb-DE":{"id":"0x082E","lang_name":"Lower Sorbian","country":"GER","locale":"DSB_GER"},
                        "lb":{"id":"0x006E","lang_name":"Luxembourgish","country":"LUX","locale":"LBX_LUX"},
                        "lb-LU":{"id":"0x046E","lang_name":"Luxembourgish","country":"LUX","locale":"LBX_LUX"},
                        "mk-MK":{"id":"0x042F","lang_name":"Macedonian (FYROM)","country":"MKD","locale":"MKI_MKD"},
                        "mk":{"id":"0x002F","lang_name":"Macedonian (FYROM)","country":"MKD","locale":"MKI_MKD"},
                        "ms":{"id":"0x003E","lang_name":"Malay","country":"MYS","locale":"MSL_MYS"},
                        "ms-BN":{"id":"0x083E","lang_name":"Malay","country":"BRN","locale":"MSB_BRN"},
                        "ms-MY":{"id":"0x043E","lang_name":"Malay","country":"MYS","locale":"MSL_MYS"},
                        "ml":{"id":"0x004C","lang_name":"Malayalam","country":"IND","locale":"MYM_IND"},
                        "ml-IN":{"id":"0x044C","lang_name":"Malayalam","country":"IND","locale":"MYM_IND"},
                        "mt":{"id":"0x003A","lang_name":"Maltese","country":"MLT","locale":"MLT_MLT"},
                        "mt-MT":{"id":"0x043A","lang_name":"Maltese","country":"MLT","locale":"MLT_MLT"},
                        "mi":{"id":"0x0081","lang_name":"Maori","country":"NZL","locale":"MRI_NZL"},
                        "mi-NZ":{"id":"0x0481","lang_name":"Maori","country":"NZL","locale":"MRI_NZL"},
                        "arn":{"id":"0x007A","lang_name":"Mapudungun","country":"CHL","locale":"MPD_CHL"},
                        "arn-CL":{"id":"0x047A","lang_name":"Mapudungun","country":"CHL","locale":"MPD_CHL"},
                        "mr":{"id":"0x004E","lang_name":"Marathi","country":"IND","locale":"MAR_IND"},
                        "mr-IN":{"id":"0x044E","lang_name":"Marathi","country":"IND","locale":"MAR_IND"},
                        "moh":{"id":"0x007C","lang_name":"Mohawk","country":"CAN","locale":"MWK_CAN"},
                        "moh-CA":{"id":"0x047C","lang_name":"Mohawk","country":"CAN","locale":"MWK_CAN"},
                        "mn":{"id":"0x0050","lang_name":"Mongolian (Cyrillic)","country":"MNG","locale":"MNN_MNG"},
                        "mn-Cyrl":{"id":"0x7850","lang_name":"Mongolian (Cyrillic)","country":"MNG","locale":"MNN_MNG"},
                        "mn-MN":{"id":"0x0450","lang_name":"Mongolian (Cyrillic)","country":"MNG","locale":"MNN_MNG"},
                        "mn-Mong":{"id":"0x7C50","lang_name":"Mongolian (Traditional Mongolian)","country":"CHN","locale":"MNG_CHN"},
                        "mn-Mong-CN":{"id":"0x0850","lang_name":"Mongolian (Traditional Mongolian)","country":"CHN","locale":"MNG_CHN"},
                        "ne":{"id":"0x0061","lang_name":"Nepali","country":"NEP","locale":"NEP_NEP"},
                        "ne-NP":{"id":"0x0461","lang_name":"Nepali","country":"NEP","locale":"NEP_NEP"},
                        "no":{"id":"0x0014","lang_name":"Norwegian (Bokm\u00d5l)","country":"NOR","locale":"NOR_NOR"},
                        "nb":{"id":"0x7C14","lang_name":"Norwegian (Bokm\u00d5l)","country":"NOR","locale":"NOR_NOR"},
                        "nn":{"id":"0x7814","lang_name":"Norwegian (Nynorsk)","country":"NOR","locale":"NON_NOR"},
                        "nb-NO":{"id":"0x0414","lang_name":"Norwegian (Bokm\u00d5l)","country":"NOR","locale":"NOR_NOR"},
                        "nn-NO":{"id":"0x0814","lang_name":"Norwegian (Nynorsk)","country":"NOR","locale":"NON_NOR"},
                        "oc":{"id":"0x0082","lang_name":"Occitan","country":"FRA","locale":"OCI_FRA"},
                        "oc-FR":{"id":"0x0482","lang_name":"Occitan","country":"FRA","locale":"OCI_FRA"},
                        "or":{"id":"0x0048","lang_name":"Oriya","country":"IND","locale":"ORI_IND"},
                        "or-IN":{"id":"0x0448","lang_name":"Oriya","country":"IND","locale":"ORI_IND"},
                        "ps":{"id":"0x0063","lang_name":"Pashto","country":"AFG","locale":"PAS_AFG"},
                        "ps-AF":{"id":"0x0463","lang_name":"Pashto","country":"AFG","locale":"PAS_AFG"},
                        "fa":{"id":"0x0029","lang_name":"Persian","country":"IRN","locale":"FAR_IRN"},
                        "fa-IR":{"id":"0x0429","lang_name":"Persian","country":"IRN","locale":"FAR_IRN"},
                        "pl":{"id":"0x0015","lang_name":"Polish","country":"POL","locale":"PLK_POL"},
                        "pl-PL":{"id":"0x0415","lang_name":"Polish","country":"POL","locale":"PLK_POL"},
                        "pt":{"id":"0x0016","lang_name":"Portuguese","country":"BRA","locale":"PTB_BRA"},
                        "pt-BR":{"id":"0x0416","lang_name":"Portuguese","country":"BRA","locale":"PTB_BRA"},
                        "pt-PT":{"id":"0x0816","lang_name":"Portuguese","country":"PRT","locale":"PTG_PRT"},
                        "pa":{"id":"0x0046","lang_name":"Punjabi","country":"IND","locale":"PAN_IND"},
                        "pa-IN":{"id":"0x0446","lang_name":"Punjabi","country":"IND","locale":"PAN_IND"},
                        "quz":{"id":"0x006B","lang_name":"Quechua","country":"BOL","locale":"QUB_BOL"},
                        "quz-BO":{"id":"0x046B","lang_name":"Quechua","country":"BOL","locale":"QUB_BOL"},
                        "quz-EC":{"id":"0x086B","lang_name":"Quechua","country":"ECU","locale":"QUE_ECU"},
                        "quz-PE":{"id":"0x0C6B","lang_name":"Quechua","country":"PER","locale":"QUP_PER"},
                        "ro":{"id":"0x0018","lang_name":"Romanian","country":"ROM","locale":"ROM_ROM"},
                        "ro-RO":{"id":"0x0418","lang_name":"Romanian","country":"ROM","locale":"ROM_ROM"},
                        "rm":{"id":"0x0017","lang_name":"Romansh","country":"CHE","locale":"RMC_CHE"},
                        "rm-CH":{"id":"0x0417","lang_name":"Romansh","country":"CHE","locale":"RMC_CHE"},
                        "ru":{"id":"0x0019","lang_name":"Russian","country":"RUS","locale":"RUS_RUS"},
                        "ru-RU":{"id":"0x0419","lang_name":"Russian","country":"RUS","locale":"RUS_RUS"},
                        "smn":{"id":"0x703B","lang_name":"Sami (Inari)","country":"FIN","locale":"SMN_FIN"},
                        "smj":{"id":"0x7C3B","lang_name":"Sami (Lule)","country":"SWE","locale":"SMK_SWE"},
                        "se":{"id":"0x003B","lang_name":"Sami (Northern)","country":"NOR","locale":"SME_NOR"},
                        "sms":{"id":"0x743B","lang_name":"Sami (Skolt)","country":"FIN","locale":"SMS_FIN"},
                        "sma":{"id":"0x783B","lang_name":"Sami (Southern)","country":"SWE","locale":"SMB_SWE"},
                        "smn-FI":{"id":"0x243B","lang_name":"Sami (Inari)","country":"FIN","locale":"SMN_FIN"},
                        "smj-NO":{"id":"0x103B","lang_name":"Sami (Lule)","country":"NOR","locale":"SMJ_NOR"},
                        "smj-SE":{"id":"0x143B","lang_name":"Sami (Lule)","country":"SWE","locale":"SMK_SWE"},
                        "se-FI":{"id":"0x0C3B","lang_name":"Sami (Northern)","country":"FIN","locale":"SMG_FIN"},
                        "se-NO":{"id":"0x043B","lang_name":"Sami (Northern)","country":"NOR","locale":"SME_NOR"},
                        "se-SE":{"id":"0x083B","lang_name":"Sami (Northern)","country":"SWE","locale":"SMF_SWE"},
                        "sms-FI":{"id":"0x203B","lang_name":"Sami (Skolt)","country":"FIN","locale":"SMS_FIN"},
                        "sma-NO":{"id":"0x183B","lang_name":"Sami (Southern)","country":"NOR","locale":"SMA_NOR"},
                        "sma-SE":{"id":"0x1C3B","lang_name":"Sami (Southern)","country":"SWE","locale":"SMB_SWE"},
                        "sa":{"id":"0x004F","lang_name":"Sanskrit","country":"IND","locale":"SAN_IND"},
                        "sa-IN":{"id":"0x044F","lang_name":"Sanskrit","country":"IND","locale":"SAN_IND"},
                        "gd":{"id":"0x0091","lang_name":"Scottish Gaelic","country":"GBR","locale":"GLA_GBR"},
                        "gd-GB":{"id":"0x0491","lang_name":"Scottish Gaelic","country":"GBR","locale":"GLA_GBR"},
                        "sr":{"id":"0x7C1A","lang_name":"Serbian (Latin)","country":"SRB","locale":"SRM_SRB"},
                        "sr-Cyrl":{"id":"0x6C1A","lang_name":"Serbian (Cyrillic)","country":"SRB","locale":"SRO_SRB"},
                        "sr-Cyrl-BA":{"id":"0x1C1A","lang_name":"Serbian (Cyrillic)","country":"BIH","locale":"SRN_BIH"},
                        "sr-Cyrl-ME":{"id":"0x301A","lang_name":"Serbian (Cyrillic)","country":"MNE","locale":"SRQ_MNE"},
                        "sr-Cyrl-CS":{"id":"0x0C1A","lang_name":"Serbian (Cyrillic)","country":"SCG","locale":"SRB_SCG"},
                        "sr-Cyrl-RS":{"id":"0x281A","lang_name":"Serbian (Cyrillic)","country":"SRB","locale":"SRO_SRB"},
                        "sr-Latn":{"id":"0x701A","lang_name":"Serbian (Latin)","country":"SRB","locale":"SRM_SRB"},
                        "sr-Latn-BA":{"id":"0x181A","lang_name":"Serbian (Latin)","country":"BIH","locale":"SRS_BIH"},
                        "sr-Latn-ME":{"id":"0x2C1A","lang_name":"Serbian (Latin)","country":"MNE","locale":"SRP_MNE"},
                        "sr-Latn-CS":{"id":"0x081A","lang_name":"Serbian (Latin)","country":"SCG","locale":"SRL_SCG"},
                        "sr-Latn-RS":{"id":"0x241A","lang_name":"Serbian (Latin)","country":"SRB","locale":"SRM_SRB"},
                        "nso":{"id":"0x006C","lang_name":"Sesotho sa Leboa","country":"ZAF","locale":"NSO_ZAF"},
                        "nso-ZA":{"id":"0x046C","lang_name":"Sesotho sa Leboa","country":"ZAF","locale":"NSO_ZAF"},
                        "tn":{"id":"0x0032","lang_name":"Setswana","country":"ZAF","locale":"TSN_ZAF"},
                        "tn-ZA":{"id":"0x0432","lang_name":"Setswana","country":"ZAF","locale":"TSN_ZAF"},
                        "si":{"id":"0x005B","lang_name":"Sinhala","country":"LKA","locale":"SIN_LKA"},
                        "si-LK":{"id":"0x045B","lang_name":"Sinhala","country":"LKA","locale":"SIN_LKA"},
                        "sk":{"id":"0x001B","lang_name":"Slovak","country":"SVK","locale":"SKY_SVK"},
                        "sk-SK":{"id":"0x041B","lang_name":"Slovak","country":"SVK","locale":"SKY_SVK"},
                        "sl":{"id":"0x0024","lang_name":"Slovenian","country":"SVN","locale":"SLV_SVN"},
                        "sl-SI":{"id":"0x0424","lang_name":"Slovenian","country":"SVN","locale":"SLV_SVN"},
                        "es":{"id":"0x000A","lang_name":"Spanish","country":"ESP","locale":"ESN_ESP"},
                        "es-AR":{"id":"0x2C0A","lang_name":"Spanish","country":"ARG","locale":"ESS_ARG"},
                        "es-BO":{"id":"0x400A","lang_name":"Spanish","country":"BOL","locale":"ESB_BOL"},
                        "es-CL":{"id":"0x340A","lang_name":"Spanish","country":"CHL","locale":"ESL_CHL"},
                        "es-CO":{"id":"0x240A","lang_name":"Spanish","country":"COL","locale":"ESO_COL"},
                        "es-CR":{"id":"0x140A","lang_name":"Spanish","country":"CRI","locale":"ESC_CRI"},
                        "es-DO":{"id":"0x1C0A","lang_name":"Spanish","country":"DOM","locale":"ESD_DOM"},
                        "es-EC":{"id":"0x300A","lang_name":"Spanish","country":"ECU","locale":"ESF_ECU"},
                        "es-SV":{"id":"0x440A","lang_name":"Spanish","country":"SLV","locale":"ESE_SLV"},
                        "es-GT":{"id":"0x100A","lang_name":"Spanish","country":"GTM","locale":"ESG_GTM"},
                        "es-HN":{"id":"0x480A","lang_name":"Spanish","country":"HND","locale":"ESH_HND"},
                        "es-MX":{"id":"0x080A","lang_name":"Spanish","country":"MEX","locale":"ESM_MEX"},
                        "es-NI":{"id":"0x4C0A","lang_name":"Spanish","country":"NIC","locale":"ESI_NIC"},
                        "es-PA":{"id":"0x180A","lang_name":"Spanish","country":"PAN","locale":"ESA_PAN"},
                        "es-PY":{"id":"0x3C0A","lang_name":"Spanish","country":"PRY","locale":"ESZ_PRY"},
                        "es-PE":{"id":"0x280A","lang_name":"Spanish","country":"PER","locale":"ESR_PER"},
                        "es-PR":{"id":"0x500A","lang_name":"Spanish","country":"PRI","locale":"ESU_PRI"},
                        "es-ES":{"id":"0x0C0A","lang_name":"Spanish","country":"ESP","locale":"ESN_ESP"},
                        "es-US":{"id":"0x540A","lang_name":"Spanish","country":"USA","locale":"EST_USA"},
                        "es-UY":{"id":"0x380A","lang_name":"Spanish","country":"URY","locale":"ESY_URY"},
                        "es-VE":{"id":"0x200A","lang_name":"Spanish","country":"VEN","locale":"ESV_VEN"},
                        "sv":{"id":"0x001D","lang_name":"Swedish","country":"SWE","locale":"SVE_SWE"},
                        "sv-FI":{"id":"0x081D","lang_name":"Swedish","country":"FIN","locale":"SVF_FIN"},
                        "sv-SE":{"id":"0x041D","lang_name":"Swedish","country":"SWE","locale":"SVE_SWE"},
                        "syr":{"id":"0x005A","lang_name":"Syriac","country":"SYR","locale":"SYR_SYR"},
                        "syr-SY":{"id":"0x045A","lang_name":"Syriac","country":"SYR","locale":"SYR_SYR"},
                        "tg":{"id":"0x0028","lang_name":"Tajik (Cyrillic)","country":"TAJ","locale":"TAJ_TAJ"},
                        "tg-Cyrl":{"id":"0x7C28","lang_name":"Tajik (Cyrillic)","country":"TAJ","locale":"TAJ_TAJ"},
                        "tg-Cyrl-TJ":{"id":"0x0428","lang_name":"Tajik (Cyrillic)","country":"TAJ","locale":"TAJ_TAJ"},
                        "tzm":{"id":"0x005F","lang_name":"Tamazight (Latin)","country":"DZA","locale":"TZM_DZA"},
                        "tzm-Latn":{"id":"0x7C5F","lang_name":"Tamazight (Latin)","country":"DZA","locale":"TZM_DZA"},
                        "tzm-Latn-DZ":{"id":"0x085F","lang_name":"Tamazight (Latin)","country":"DZA","locale":"TZM_DZA"},
                        "ta":{"id":"0x0049","lang_name":"Tamil","country":"IND","locale":"TAM_IND"},
                        "ta-IN":{"id":"0x0449","lang_name":"Tamil","country":"IND","locale":"TAM_IND"},
                        "tt":{"id":"0x0044","lang_name":"Tatar","country":"RUS","locale":"TTT_RUS"},
                        "tt-RU":{"id":"0x0444","lang_name":"Tatar","country":"RUS","locale":"TTT_RUS"},
                        "te":{"id":"0x004A","lang_name":"Telugu","country":"IND","locale":"TEL_IND"},
                        "te-IN":{"id":"0x044A","lang_name":"Telugu","country":"IND","locale":"TEL_IND"},
                        "th":{"id":"0x001E","lang_name":"Thai","country":"THA","locale":"THA_THA"},
                        "th-TH":{"id":"0x041E","lang_name":"Thai","country":"THA","locale":"THA_THA"},
                        "bo":{"id":"0x0051","lang_name":"Tibetan","country":"CHN","locale":"BOB_CHN"},
                        "bo-CN":{"id":"0x0451","lang_name":"Tibetan","country":"CHN","locale":"BOB_CHN"},
                        "tr":{"id":"0x001F","lang_name":"Turkish","country":"TUR","locale":"TRK_TUR"},
                        "tr-TR":{"id":"0x041F","lang_name":"Turkish","country":"TUR","locale":"TRK_TUR"},
                        "tk":{"id":"0x0042","lang_name":"Turkmen","country":"TKM","locale":"TUK_TKM"},
                        "tk-TM":{"id":"0x0442","lang_name":"Turkmen","country":"TKM","locale":"TUK_TKM"},
                        "uk":{"id":"0x0022","lang_name":"Ukrainian","country":"UKR","locale":"UKR_UKR"},
                        "uk-UA":{"id":"0x0422","lang_name":"Ukrainian","country":"UKR","locale":"UKR_UKR"},
                        "hsb":{"id":"0x002E","lang_name":"Upper Sorbian","country":"GER","locale":"HSB_GER"},
                        "hsb-DE":{"id":"0x042E","lang_name":"Upper Sorbian","country":"GER","locale":"HSB_GER"},
                        "ur":{"id":"0x0020","lang_name":"Urdu","country":"PAK","locale":"URD_PAK"},
                        "ur-PK":{"id":"0x0420","lang_name":"Urdu","country":"PAK","locale":"URD_PAK"},
                        "ug":{"id":"0x0080","lang_name":"Uyghur","country":"CHN","locale":"UIG_CHN"},
                        "ug-CN":{"id":"0x0480","lang_name":"Uyghur","country":"CHN","locale":"UIG_CHN"},
                        "uz-Cyrl":{"id":"0x7843","lang_name":"Uzbek (Cyrillic)","country":"UZB","locale":"UZB_UZB"},
                        "uz-Cyrl-UZ":{"id":"0x0843","lang_name":"Uzbek (Cyrillic)","country":"UZB","locale":"UZB_UZB"},
                        "uz":{"id":"0x0043","lang_name":"Uzbek (Latin)","country":"UZB","locale":"UZB_UZB"},
                        "uz-Latn":{"id":"0x7C43","lang_name":"Uzbek (Latin)","country":"UZB","locale":"UZB_UZB"},
                        "uz-Latn-UZ":{"id":"0x0443","lang_name":"Uzbek (Latin)","country":"UZB","locale":"UZB_UZB"},
                        "vi":{"id":"0x002A","lang_name":"Vietcountryse","country":"VNM","locale":"VIT_VNM"},
                        "vi-VN":{"id":"0x042A","lang_name":"Vietcountryse","country":"VNM","locale":"VIT_VNM"},
                        "cy":{"id":"0x0052","lang_name":"Welsh","country":"GBR","locale":"CYM_GBR"},
                        "cy-GB":{"id":"0x0452","lang_name":"Welsh","country":"GBR","locale":"CYM_GBR"},
                        "wo":{"id":"0x0088","lang_name":"Wolof","country":"SEN","locale":"WOL_SEN"},
                        "wo-SN":{"id":"0x0488","lang_name":"Wolof","country":"SEN","locale":"WOL_SEN"},
                        "sah":{"id":"0x0085","lang_name":"Yakut","country":"RUS","locale":"SAH_RUS"},
                        "sah-RU":{"id":"0x0485","lang_name":"Yakut","country":"RUS","locale":"SAH_RUS"},
                        "ii":{"id":"0x0078","lang_name":"Yi","country":"CHN","locale":"III_CHN"},
                        "ii-CN":{"id":"0x0478","lang_name":"Yi","country":"CHN","locale":"III_CHN"},
                        "yo":{"id":"0x006A","lang_name":"Yoruba","country":"NGA","locale":"YOR_NGA"},
                        "yo-NG":{"id":"0x046A","lang_name":"Yoruba","country":"NGA","locale":"YOR_NGA"}
                    }';

        $win_loc = json_decode($win_loc, true);

        $countries = property::countries($session['lang'], true);

        $locales = array();
        foreach($win_loc as $short => $locale)
            $locales[$locale['locale']] = $locale['lang_name'].' ('.$countries[$locale['country']].') ['.$short.']';

        return $locales;
    }

    public function unix_locales()
    {
        global $session;

        $locales = shell_exec('locale -a');
        $tmp = explode("\n", $locales);
        $locales = array();
        $languages = language::language_names(false);
        $countries = property::countries($session['lang'], true);
        $tmp = array_filter($tmp);
        foreach($tmp as $loc)
        {
            if(in_array($loc, array('C', 'POSIX')))
                continue;

            if(strpos($loc, '.')===false)
            {
                if(in_array($loc.'.utf8', $tmp))
                    continue;
            }
            else
            {
                // there is a dot in the locale name
                $check = substr($loc, 0, strpos($loc, '.')).'.utf8';
                if(in_array($check, $tmp) && $check!=$loc)
                    continue;
            }

            $language = @$languages[substr($loc, 0, 2)];
            if(empty($language))
                $language = '?';
            $country = @$countries[substr($loc, 3,2)];
            if(!empty($country))
                $country = ' ('.$country.')';

            $locales[$loc] = $language.$country.' ['.$loc.']';
        }

        return $locales;
    }

    public function quicksearch($text)
    {
        $like = ' LIKE '.protect('%'.$text.'%');

        $cols[] = 'id' . $like;
        $cols[] = 'name' . $like;
        $cols[] = 'domain' . $like;
        $cols[] = 'subdomain' . $like;

        $where = ' AND ( ';
        $where.= implode( ' OR ', $cols);
        $where .= ')';

        return $where;
    }
}
?>