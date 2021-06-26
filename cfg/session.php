<?php
/* Navigate CMS session management */

$session_cookie_domain = $_SERVER['SERVER_NAME'];

if(!preg_match(
    "/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])".
    "(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/",
    $_SERVER['SERVER_NAME']) &&
    $_SERVER['SERVER_NAME']!='localhost'
)
{
    $session_cookie_domain = $_SERVER['SERVER_NAME'];
    if(substr_count($_SERVER['SERVER_NAME'], '.') > 1)
    {
        $session_cookie_domain = substr($_SERVER['SERVER_NAME'], strpos($_SERVER['SERVER_NAME'], "."));
    }

    $session_cookie_prefs = array(
        'lifetime' => 3600,
        'path' => '/',
        'domain' => $session_cookie_domain,
        'secure' => ($_SERVER['REQUEST_SCHEME']=='https'),
        'httponly' => true,
        'sameSite' => 'Lax'
    );

    @session_set_cookie_params($session_cookie_prefs);
}

$session_name = 'NVSID_'.substr(md5(APP_UNIQUE), 0, 8).'_'.md5($session_cookie_domain);

// retrieve session id via cookie except if a "session_id" parameter has been given when calling navigate_upload.php
if(isset($_COOKIE[$session_name]) && empty($_REQUEST['session_id']))
{
    if(session_id() != $_COOKIE[$session_name])
    {
        session_write_close();
        session_id($_COOKIE[$session_name]);
    }
}

if(!defined("NAVIGATE_SESSIONS_PATH"))
{
    // app installation before 2.9.1
    define("NAVIGATE_SESSIONS_PATH", NAVIGATE_PRIVATE.'/sessions');
}

if(!empty(NAVIGATE_SESSIONS_PATH))
{
    @session_save_path(NAVIGATE_SESSIONS_PATH);
    @ini_set('session.gc_probability', 1);
}

@session_name($session_name);
session_start();

// set/refresh session cookie
setcookie_samesite(session_name(), session_id(), time() + 3600, '/', $session_cookie_domain);

// also refresh PHPSESSID cookie, to avoid problems
setcookie_samesite("PHPSESSID", session_id(), time() + 3600, '/', $session_cookie_domain);

// set CSRF token, if not already there
if(!isset($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes( 32 ));
    $_SESSION['csrf_token_time'] = time();

    // we create a specific token for direct GET requests that make modifications
    $_SESSION['request_token'] = bin2hex(openssl_random_pseudo_bytes( 16 ));
}

header('X-Csrf-Token: '.$_SESSION['csrf_token']);

?>