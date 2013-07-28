<?php
/* Navigate CMS session management */

$session_name = 'NVSID_'.substr(md5(NAVIGATE_PATH), 0, 8);

if(isset($_COOKIE[$session_name]))
{
    if(session_id() != $_COOKIE[$session_name])
    {
        session_write_close();
        session_id($_COOKIE[$session_name]);
    }
}

$session_cookie_domain = $_SERVER['SERVER_NAME'];

if(!preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])".
    "(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $_SERVER['SERVER_NAME'])
    && $_SERVER['SERVER_NAME']!='localhost')
{
    $session_cookie_domain = $_SERVER['SERVER_NAME'];
    if(substr_count($_SERVER['SERVER_NAME'], '.') > 1)
        $session_cookie_domain = substr($_SERVER['SERVER_NAME'], strpos($_SERVER['SERVER_NAME'], "."));

    session_set_cookie_params(3600, '/', $session_cookie_domain, false, false);
}

session_save_path(NAVIGATE_PRIVATE.'/sessions');
session_name($session_name);
session_start();

setcookie(session_name(), session_id(), time() + 3600, '/', $session_cookie_domain);

?>