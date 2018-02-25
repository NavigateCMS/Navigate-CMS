<?php
require_once('../cfg/globals.php');
require_once(NAVIGATE_PATH.'/web/nvweb_common.php');

function nvweb_parse($request)
{
    debugger::timer('nvweb-page-init');

    /* global variables */
    global $DB;
    global $current;
    global $session;
    global $webuser;
    global $website;
    global $theme;
    global $template;
    global $dictionary;
    global $plugins;
    global $events;
    global $webgets;
    global $idn;
    global $html;

    $idn = new \Mso\IdnaConvert\IdnaConvert();
    $events = new events();

    // create database connection
    $DB = new database();
    if (!$DB->connect())
    {
        die(APP_NAME . ' # ERROR<br /> ' . $DB->get_last_error());
    }

    // global exception catcher
    try
    {
        // which website do we have to load?
        $url = nvweb_self_url();

        if (!empty($request['wid']))
        {
            $website = new website();
            $website->load(intval($request['wid']));
        }
        else
            $website = nvweb_load_website_by_url($url);

        if (($website->permission == 2) ||
            ($website->permission == 1 && empty($_SESSION['APP_USER#' . APP_UNIQUE]))
        )
        {
            if (!empty($website->redirect_to))
                header('location: ' . $website->redirect_to);
            nvweb_clean_exit();
        }

        // global helper variables
        $session = array();        // webuser session
        $structure = array();    // web menu structure
        $webgets = array();    // webgets static data
        $webuser = new webuser();
        $theme = new theme();
        if (!empty($website->theme))
            $theme->load($website->theme);

        $route = $request['route'];
        // remove last '/' in route if exists
        if (substr($route, -1) == '/')
            $route = substr($route, 0, -1);

        // remove the "folder" part of the route (only if this url is really under a folder)
        if (!empty($website->folder) && strpos('/' . $route, $website->folder) === 0)
            $route = substr('/' . $route, strlen($website->folder) + 1);

        $nvweb_absolute = $website->absolute_path();
        try
        {
            $nvweb_absolute = $idn->encodeUri($nvweb_absolute);
        }
        catch (\InvalidArgumentException $e)
        {
            // do nothing, the domain is already in punycode
        }

        define('NVWEB_ABSOLUTE', $nvweb_absolute);
        define('NVWEB_OBJECT', $nvweb_absolute . '/object');
        define('NVWEB_AJAX', $nvweb_absolute . '/nvajax');
        define('NVWEB_THEME', NAVIGATE_PARENT . NAVIGATE_FOLDER . '/themes/' . $theme->name);
        define('NAVIGATE_URL', NAVIGATE_PARENT . NAVIGATE_FOLDER);

        if(!isset($_SESSION['nvweb.' . $website->id]))
        {
            $_SESSION['nvweb.' . $website->id] = array();
            $session['lang'] = nvweb_country_language();
        }
        else
        {
            $session = $_SESSION['nvweb.' . $website->id];

            if (empty($session['lang']))
                $session['lang'] = nvweb_country_language();
        }

        $force_language = "";
        if(isset($request['lang']))
        {
            $force_language = $request['lang'];
        }
        else if(strpos($url, 'lang='))
        {
            $params = parse_url($url, PHP_URL_QUERY);
            parse_str($params, $params);
            if (isset($params['lang']))
                $force_language = $params['lang'];
        }

        if (!empty($force_language))
            $session['lang'] = $force_language;
        else if (isset($request['lang']))
            $session['lang'] = $request['lang'];

        // global data across webgets
        $current = array(
            'lang'               => $session['lang'],
            'route'              => $route,
            'object'             => '',
            'type'               => '',
            'template'           => '',
            'category'           => '',
            'webuser'            => '',
            'plugins'            => '',
            'plugins_called'     => '',
            'delayed_nvlists'    => array(),
            'delayed_nvsearches' => array(),
            'delayed_tags_pre'   => array(),
            'delayed_tags_code'  => array(),
            'navigate_session'   => !empty($_SESSION['APP_USER#' . APP_UNIQUE]),
            'html_after_body'    => array(),
            'js_after_body'      => array(),
            'css_after_body'     => array(),
            'pagecache_enabled'  => (
                    empty($_SESSION['APP_USER#' . APP_UNIQUE]) &&
                    empty($_POST) &&
                    (count($_GET) <= 1) &&
                    empty($webuser->id) &&
                    ($website->page_cache == "1")
            )
        );

        debugger::stop_timer('nvweb-page-init');
        debugger::timer('nvweb-website-cron');

        $website->cron();

        debugger::stop_timer('nvweb-website-cron');
        debugger::timer('nvweb-page-cache-check');

        if($current['pagecache_enabled'])
        {
            $page_cache = NAVIGATE_PRIVATE.'/'.$website->id.'/cache/'.sha1($route).'.'.$session['lang'].'.page';

            // the cache for this page has been created in the last hour?
            if(file_exists($page_cache) && filemtime($page_cache) > (core_time() - 3600))
            {
                $html = file_get_contents($page_cache);
                $_SESSION['nvweb.' . $website->id] = $session;
                session_write_close();
                $DB->disconnect();
                echo $html;
                echo '<!-- cached page time: '.(date("c", filemtime($page_cache))).' -->';
                exit;
            }
            // else, generate the page and save it into the cache, if necessary
        }

        debugger::stop_timer('nvweb-page-cache-check');
        debugger::timer('nvweb-load-dictionary');

        // load dictionary, extensions and bind events (as soon as possible)
        $dictionary = nvweb_dictionary_load();

        debugger::stop_timer('nvweb-load-dictionary');
        debugger::timer('nvweb-load-plugins');

        nvweb_plugins_load();

        $current['plugins'] = $plugins;
        $events->extension_backend_bindings(null, true);

        debugger::stop_timer('nvweb-load-plugins');
        debugger::timer('nvweb-load-webuser');

        if (!empty($session['webuser']))
            $webuser->load($session['webuser']);
        else if (!empty($_COOKIE["webuser"]))
            $webuser->load_by_hash($_COOKIE['webuser']);

        // if the webuser was removed, it doesn't exist anymore,
        //  $session/$_COOKIE may have obsolete data, force a log out
        // also check date range access
        if( (empty($webuser->id) &&
            (!empty($session['webuser']) || !empty($_COOKIE['webuser']))) || !$webuser->access_allowed()
        )
        {
            $webuser->unset_cookie();
            unset($webuser);
            $webuser = new webuser();
        }

        if (!empty($webuser->id))
        {
            $webuser->lastseen = core_time();
            $webuser->save(false); // don't trigger the webuser_modified event
        }

        // check if the webuser wants to sign out
        if (isset($request['webuser_signout']))
        {
            $webuser->unset_cookie();
            unset($webuser);
            $webuser = new webuser();
        }

        $current['webuser'] = @$session['webuser'];

        setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);
        date_default_timezone_set($webuser->timezone ? $webuser->timezone : $website->default_timezone);

        // help developers to find problems
        if ($current['navigate_session'] == 1 && APP_DEBUG)
        {
            error_reporting(E_ALL ^ E_NOTICE);
            ini_set('display_errors', true);
        }

        debugger::stop_timer('nvweb-load-webuser');
        debugger::timer('nvweb-parse-route');

        // parse route
        nvweb_route_parse($current['route']);
        $permission = nvweb_check_permission();

        // if no preview & permission not allowed
        // if preview but no navigate_session active
        if (($request['preview'] == 'true' && $current['navigate_session'] != 1) ||
            (empty($request['preview']) && !$permission)
        )
        {
            nvweb_route_parse('***nv.not_allowed***');
            nvweb_clean_exit();
        }

        $template = nvweb_template_load();
        $events->trigger('theme', 'template_load', array('template' => &$template));

        if (empty($template))
            throw new Exception('Navigate CMS: no template found!');

        debugger::stop_timer('nvweb-parse-route');
        debugger::timer('nvweb-template-special-tags');

        // parse the special tag "include"
        // also convert curly brackets tags {{nv object=""}} to <nv object="" /> version
        // we do it now because new nv tags could be added before parsing the whole html
        $html = nvweb_template_parse_special($template->file_contents);

        debugger::stop_timer('nvweb-template-special-tags');
        debugger::timer('nvweb-plugins-event-before_parse');

        $current['plugins_called'] = nvweb_plugins_called_in_template($html);
        $html = nvweb_plugins_event('before_parse', $html);

        debugger::stop_timer('nvweb-plugins-event-before_parse');
        debugger::timer('nvweb-theme-settings');

        $html = nvweb_theme_settings($html);

        debugger::stop_timer('nvweb-theme-settings');

        // debugger timers controlled inside
        $html = nvweb_template_parse_lists($html);
        $html = nvweb_template_parse($html);

        debugger::timer('nvweb-template-process-new-nv-tags-delayed-lists');

        // if the content has added any nv tag, process them
        if (strpos($html, '{{nv ') !== false || strpos($html, '<nv '))
        {
            $html = nvweb_template_parse_special($html);
            $html = nvweb_template_parse_lists($html);
            $html = nvweb_template_parse($html);
        }

        // if we have a delayed nv list we need to parse it now
        if (!empty($current['delayed_nvlists']) || !empty($current['delayed_nvsearches']))
        {
            $html = nvweb_template_parse_lists($html, true);

            if (strpos($html, '{{nv ') !== false || strpos($html, '<nv '))
            {
                $html = nvweb_template_parse_special($html);
                $html = nvweb_template_parse_lists($html);
                $html = nvweb_template_parse($html);
            }
        }

        debugger::stop_timer('nvweb-template-process-new-nv-tags-delayed-lists');
        debugger::timer('nvweb-template-after-generation-tweaks');

        $html = nvweb_template_oembed_parse($html);
        $html = nvweb_template_processes($html);

        $end = nvweb_after_body('php');
        $end .= nvweb_after_body('html');
        $end .= nvweb_after_body('js');
        $end .= nvweb_after_body('css');
        $end .= "\n\n";
        $end .= '</body>';

        $html = str_replace('</body>', $end, $html);

        $events->trigger('theme', 'before_tweaks', array('html' => &$html));

        $html = nvweb_template_tweaks($html);
        $html = nvweb_template_restore_special($html);

        debugger::stop_timer('nvweb-template-after-generation-tweaks');
        debugger::timer('nvweb-theme-after_parse-event');

        $events->trigger('theme', 'after_parse', array('html' => &$html));
        $html = nvweb_plugins_event('after_parse', $html);

        debugger::stop_timer('nvweb-theme-after_parse-event');

        $html = nvweb_template_convert_nv_paths($html);

        $_SESSION['nvweb.' . $website->id] = $session;
        session_write_close();

        if($current['navigate_session'] == true && APP_DEBUG)
        {
            echo $html;

            echo "\n\r<!--\n\r" . '$current:' . "\n\r";
            print_r($current);
            echo "\n\r!--><!--\n\r" . '$_SESSION:' . "\n\r";

            $stmp = $_SESSION;
            foreach ($stmp as $key => $val)
            {
                if (substr($key, 0, 4) == 'PMA_') // hide phpMyAdmin single signon settings!!
                    continue;

                echo '[' . $key . '] => ' . print_r($val, true) . "\n";
            }

            echo "\n\r!--><!--\n\r" . 'profiling:' . "\n\r";

            foreach (debugger::get_timers() as $key => $debugger_timer)
            {
                $key = array_keys($debugger_timer);
                $key = $key[0];

                $val = $debugger_timer[$key];
                echo '[' . $key . '] => ' . $val . " ms\n";
            }

            echo "!-->";

            if (isset($_GET['profiling']))
                debugger::bar_dump(debugger::get_timers('list'));
        }
        else
        {
            $events->trigger('nvweb', 'before_output');

            // close any previous output buffer
            // some PHP configurations ALWAYS open a buffer
            $zlib_oc_enabled = ini_get('zlib.output_compression');
            if(function_exists('ob_start') && !$zlib_oc_enabled)
            {
                if(ob_get_level() > 0)
                    while(@ob_end_flush());

                // open gzip buffer
                ob_start("ob_gzhandler");
                echo $html;
                ob_end_flush();
            }
            else
                echo $html;

            // finally, save this page request into the website pages cache (only for anonymous users)
            if($current['pagecache_enabled'])
            {
                $page_cache = NAVIGATE_PRIVATE.'/'.$website->id.'/cache/'.sha1($route).'.'.$session['lang'].'.page';
                @file_put_contents($page_cache, $html);
            }
        }
    }
    catch (Exception $e)
    {
        ?>
        <html>
        <body>
        ERROR
        <br/><br/>
        <?php
        echo $e->getMessage();
        echo '<br />';
        echo $e->getFile() . ' ' . $e->getLine();
        ?>
        </body>
        </html>
        <?php
    }

    $DB->disconnect();
}

nvweb_parse($_REQUEST);
?>