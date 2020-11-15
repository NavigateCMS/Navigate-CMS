<?php
require_once(NAVIGATE_PATH.'/lib/packages/shipping_methods/shipping_method.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/payment_methods/payment_method.class.php');

function nvweb_webuser($vars=array())
{
	global $website;
	global $theme;
	global $current;
	global $webgets;
    global $webuser;
    global $DB;
	
	$webget = "webuser";

    if(!isset($webgets[$webget]))
    {
        $webgets[$webget] = array();

        global $lang;
        if(empty($lang))
        {
            $lang = new language();
            $lang->load($current['lang']);
        }

        // default translations
        $webgets[$webget]['translations'] = array(
            'login_incorrect' => t(4, 'Login incorrect.'),
            'subscribed_ok' => t(541, 'Your email has been successfully subscribed to the newsletter.'),
            'subscribe_error' => t(542, 'There was a problem subscribing your email to the newsletter.'),
            'email_confirmation' => t(454, "An e-mail with a confirmation link has been sent to your e-mail account."),
            'click_to_confirm_account' => t(607, "Click on the link below to confirm your account"),
            'email_confirmation_notice' => t(608, "This is an automated e-mail sent as a result of a newsletter subscription request. If you received this e-mail by error just ignore it."),
            'confirm_your_account' => t(774, "Confirm your account"),
            'account_confirmation_notice' => t(775, "This is an automated e-mail sent as a result of an account creation request. If you received this e-mail by error just ignore it."),
            'forgot_password_success' => t(648, "An e-mail with a temporary password has been sent to your e-mail account."),
            'forgot_password_error' => t(446, "We're sorry. Your password reset request could not be sent. Please try again or contact us.")
        );

        // theme translations
        // if the web theme has custom translations for this string subtypes, use it (for the user selected language)
        /* just add the following translations to your json theme dictionary:
            "login_incorrect": "Login incorrect.",
            "subscribed_ok": "Your email has been successfully subscribed to the newsletter.",
            "subscribe_error": "There was a problem subscribing your email to the newsletter.",
            "email_confirmation": "An e-mail with a confirmation link has been sent to your e-mail account.",
            "click_to_confirm_account": "Click on the link below to confirm your account",
            "email_confirmation_notice": "This is an automated e-mail sent as a result of a newsletter subscription request. If you received this e-mail by error just ignore it."
            "confirm_your_account": "Confirm your account",
            "account_confirmation_notice": "This is an automated e-mail sent as a result of an account creation request. If you received this e-mail by error just ignore it."
            "forgot_password_success": "An e-mail with a temporary password has been sent to your e-mail account.",
            "forgot_password_error": "We're sorry. Your password reset request could not be sent. Please try again or contact us."
        */
        if(!empty($website->theme) && method_exists($theme, 't'))
        {
            foreach($webgets[$webget]['translations'] as $code => $text)
            {
                $theme_translation = $theme->t($code);
                if(!empty($theme_translation) && $code!=$theme_translation)
                {
                    $webgets[$webget]['translations'][$code] = $theme_translation;
                }
            }
        }
    }


	$out = '';

    switch($vars['mode'])
    {
        case 'id':
            if(!empty($webuser->id))
            {
                $out = $webuser->id;
            }
            break;

        case 'username':
            if(!empty($webuser->username))
            {
                $out = core_special_chars($webuser->username);
            }
            break;

        case 'fullname':
            if(!empty($webuser->fullname))
            {
                $out = core_special_chars($webuser->fullname);
            }
            break;

        case 'phone':
            if(!empty($webuser->phone))
            {
                $out = $webuser->phone;
            }
            break;

        case 'gender':
            if(!empty($webuser->gender))
            {
                $out = $webuser->gender;
            }
            break;

        case 'newsletter':
            $out = $webuser->newsletter;
            break;

        case 'email':
            if(!empty($webuser->email))
            {
                $out = $webuser->email;
            }
            break;

        case 'authenticate':
            $webuser_website = $vars['website'];
            if(empty($webuser_website))
            {
                $webuser_website = $website->id;
            }

            $signin_username = $_REQUEST[(empty($vars['username_field'])? 'signin_username' : $vars['username_field'])];
            $signin_password = $_REQUEST[(empty($vars['password_field'])? 'signin_password' : $vars['password_field'])];

            // a page may have several forms, which one do we have to check?
            if(!empty($vars['form']))
            {
                list($field_name, $field_value) = explode('=', $vars['form']);
                if($_POST[$field_name]!=$field_value)
                {
                    return;
                }
            }

            // ignore empty (or partial empty) forms
            if(!empty($signin_username) && !empty($signin_password))
            {
                $signed_in = $webuser->authenticate($webuser_website, $signin_username, $signin_password);

                if(!$signed_in)
                {
                    $message = $webgets[$webget]['translations']['login_incorrect'];

                    if(empty($vars['notify']))
                    {
                        $vars['notify'] = 'inline';
                    }

                    switch($vars['notify'])
                    {
                        case 'alert':
                            nvweb_after_body('js', 'alert("'.$message.'");');
                            break;

                        case 'inline':
                            $out = '<div class="nvweb-signin-form-error">'.$message.'</div>';
                            break;

                        // javascript callback
                        default:
                            nvweb_after_body('js', $vars['error_callback'].'("'.$message.'");');
                            break;
                    }
                }
                else
                {
                    $webuser->set_cookie();
                    if(!empty($vars['notify']))
                    {
                        if($vars['notify']=='callback')
                        {
                            nvweb_after_body('js', $vars['callback'].'(true);');
                        }
                    }
                }
            }
            break;

        case 'signout_link':
            $out = NVWEB_ABSOLUTE.$website->homepage().'?webuser_signout';
            break;

        case 'forgot_password':
            // pre checks: correct form, not spambot, email not empty and valid
            // load the associated user account
            // create temporary password and send email

            // TODO: don't change the password, just generate a link and let the user enter their preferred new password

            // a page may have several forms, which one do we have to check?
            if(!empty($vars['form']))
            {
                list($field_name, $field_value) = explode('=', $vars['form']);
                if($_POST[$field_name]!=$field_value)
                {
                    return;
                }
            }

            // check if this send request really comes from the website and not from a spambot
            if( parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->subdomain.'.'.$website->domain &&
                parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->domain )
            {
                return;
            }

            if(empty($vars['email_field']))
            {
                $vars['email_field'] = 'newsletter_email';
            }

            $email = $_REQUEST[$vars['email_field']];
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);

            if(!empty($vars['email_field']) && !empty($email))
            {
                $ok = false;

                if(filter_var($email, FILTER_VALIDATE_EMAIL)!==FALSE)
                {
                    $wu_id = $DB->query_single(
                        'id',
                        'nv_webusers',
                        ' email = :email
                          AND website = :wid',
                        null,
                        array(
                            ':wid' => $website->id,
                            ':email' => $email
                        )
                    );

                    $wu = new webuser();

                    if(!empty($wu_id))
                    {
                        $wu->load($wu_id);
                        if( $wu->access == 0 ||
                            ( $wu->access == 2 &&
                                ($wu->access_begin==0 || time() > $wu->access_begin) &&
                                ($wu->access_end==0 || time() < $wu->access_end)
                            )
                        )
                        {
                            // generate new password
                            $password = generate_password(8, false, 'luds');
                            $wu->set_password($password);
                            $ok = $wu->save();

                            // send a message to communicate the new webuser's email
                            $message = navigate_compose_email(array(
                                array(
                                    'title' => $website->name,
                                    'content' => t(451, "This is an automated e-mail sent as a result of a password request process. If you received this e-mail by error just ignore it.")
                                ),
                                array(
                                    'title' => t(1, "User"),
                                    'content' => $wu->username
                                ),
                                array(
                                    'title' => t(2, "Password"),
                                    'content' => $password
                                ),
                                array(
                                    'footer' => '<a href="'.$website->absolute_path().$website->homepage().'">'.$website->name.'</a>'
                                )
                            ));

                            @nvweb_send_email($website->name, $message, $wu->email);
                        }
                    }
                }

                if($ok)
                {
                    $message = $webgets[$webget]['translations']['forgot_password_success'];
                }
                else
                {
                    $message = $webgets[$webget]['translations']['forgot_password_error'];
                }

                if(empty($vars['notify']))
                {
                    $vars['notify'] = 'inline';
                }

                switch($vars['notify'])
                {
                    case 'alert':
                        nvweb_after_body('js', 'alert("'.$message.'");');
                        break;

                    case 'inline':
                        if($ok)
                            $out = '<div class="nvweb-forgot-password-form-success">'.$message.'</div>';
                        else
                            $out = '<div class="nvweb-forgot-password-form-error">'.$message.'</div>';
                        break;

                    case 'boolean':
                        $out = $ok;
                        break;

                    case 'false':
                        break;

                    // javascript callback
                    case 'callback':
                    default:
                        if($ok)
                            nvweb_after_body('js', $vars['callback'].'("'.$message.'");');
                        else
                        {
                            if(!empty($vars['error_callback']))
                                nvweb_after_body('js', $vars['error_callback'].'("'.$message.'");');
                            else
                                nvweb_after_body('js', $vars['callback'].'("'.$message.'");');
                        }
                        break;
                }
            }
            break;

        case 'sign_up':
            // pre checks: correct form, not spambot, email not empty and valid
            // get the profile data from the form
            // more checks: password strength & confirmation, legal conditions, captcha, etc.
            // save the new webuser account
            // prepare account confirmation (unless not required by webget attributes)
            //      leave the account blocked
            //      generate an activation key
            //      send confirmation email
            // if no account confirmation is required, auto login

            // required fields
            $email = strtolower(trim($_POST[$vars['email_field']]));
            $password = trim($_POST[$vars['password_field']]);

            // required fields, only if the form has them
            $conditions = $_POST[$vars['conditions_field']];

            // optional fields
            $username = trim($_POST[$vars['username_field']]);
            if(empty($username))
            {
                $username = nvweb_webuser_generate_username($email);
            }

            $error = "";
            if(!filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $error = t(768, "Invalid e-mail address");
            }
            else if(nvweb_webuser_password_strength($password, $username, $email) < 50)
            {
                $error = t(769, "Password too weak");
            }
            else if(!empty($vars['conditions_field']) && empty($conditions))
            {
                $error = t(770, "You must accept the terms");
            }
            else if(!empty($username) && strlen($username) < 4)
            {
                $error = t(772, "Invalid username");
            }
            else
            {
                // find existing webuser accounts using the same username or email address
                $DB->query('
                  SELECT COUNT(*) AS total 
                  FROM nv_webusers WHERE
                  website = :wid
                    AND (
                        email = :email OR
                        username = :username
                    )',
                    'object',
                    array(
                        ':wid' => $website->id,
                        ':email' => $email,
                        ':username' => mb_strtolower($username)
                    )
                );
                $rs = $DB->result('total');
                if($rs[0] > 0)
                    $error = t(771, "The specified username (or email address) is already being used on our website");
            }

            if(empty($error))
            {
                // everything ok, proceed to the registration
                $wu = new webuser();
                $wu->website = $website->id;
                $wu->username = $username;
                $wu->email = $email;
                $wu->set_password($password);
                $wu->language = $current['lang'];
                $wu->access = 1; // account locked until activation
                // activation key adds the timeout of 24 hours to activate the account before it is auto-removed
                $wu->activation_key = md5($wu->email . uniqid() . rand(1, 9999999)).'-'.(time() + 24*60*60);

                $ok = $wu->save();

                if($ok)
                {
                    $ok = nvweb_webuser_sign_up_send_verification($wu, @$vars['callback_url']);
                }

                if(!$ok)
                {
                    $error = t(56, "Unexpected error");
                }
            }

            if(!empty($error))
            {
                $out = $error;
            }

            break;

        case 'comments':
            // number of comments posted (and published) by the current logged in webuser
            if(!empty($webuser->id))
            {
                $out = comment::webuser_comments_count($webuser->id);
            }
            else
            {
                $out = 0;
            }
            break;

        case 'avatar':
            $size = '48';
            $extra = '';
            if(!empty($vars['size']))
            {
                $size = intval($vars['size']);
            }

            if(!empty($vars['border']))
            {
                $extra .= '&border='.$vars['border'];
            }

            if(!empty($webuser->avatar))
            {
                $out = '<img class="'.$vars['class'].'" src="'.NVWEB_OBJECT.'?type=image'.$extra.'&id='.$webuser->avatar.'" width="'.$size.'px" height="'.$size.'px"/>';
            }
            else if(!empty($vars['default']))
            {
                // the comment creator has not an avatar, but the template wants to show a default one
                // 3 cases:
                //  numerical   ->  ID of the avatar image file in Navigate CMS
                //  absolute path (http://www...)
                //  relative path (/img/avatar.png) -> path to the avatar file included in the THEME used
                if(is_numeric($vars['default']))
                {
                    $out = '<img class="'.$vars['class'].'" src="'.NVWEB_OBJECT.'?type=image'.$extra.'&id='.$vars['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                }
                else if(strpos($vars['default'], 'http://')===0)
                {
                    $out = '<img class="'.$vars['class'].'" src="'.$vars['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                }
                else if($vars['default']=='none')
                {
                    // no image
                    $out = '';
                }
                else
                {
                    $out = '<img class="'.$vars['class'].'"src="'.NAVIGATE_URL.'/themes/'.$website->theme.'/'.$vars['default'].'" width="'.$size.'px" height="'.$size.'px"/>';
                }
            }
            else // empty avatar, try to get a libravatar/gravatar or show a blank avatar
            {
                $gravatar_hash = "";
                $gravatar_default = 'blank';
                if(!empty($vars['gravatar_default']))
                {
                    $gravatar_default = $vars['gravatar_default'];
                }

                if(!empty($webuser->email))
                {
                    $gravatar_hash = md5( strtolower( trim( $webuser->email ) ) );
                }

                if(!empty($gravatar_hash) && $gravatar_default != 'none')
                {
                    // gravatar real url: https://www.gravatar.com/avatar/
                    // we use libravatar to get more userbase
                    $gravatar_url = 'https://seccdn.libravatar.org/avatar/' . $gravatar_hash . '?s='.$size.'&d='.$gravatar_default;
                    $out = '<img class="'.$vars['class'].'" src="'.$gravatar_url.'" width="'.$size.'px" height="'.$size.'px"/>';
                }
                else
                {
                    $out = '<img class="'.$vars['class'].'" src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" width="'.$size.'px" height="'.$size.'px"/>';
                }
            }
            break;

        case 'newsletter_subscribe':
            // a page may have several forms, which one do we have to check?
            if(!empty($vars['form']))
            {
                list($field_name, $field_value) = explode('=', $vars['form']);
                if($_POST[$field_name]!=$field_value)
                {
                    return;
                }
            }

            // check if this send request really comes from the website and not from a spambot
            if( parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->subdomain.'.'.$website->domain &&
                parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->domain )
            {
                return;
            }

            if(empty($vars['email_field']))
            {
                $vars['email_field'] = 'newsletter_email';
            }

            $email = $_REQUEST[$vars['email_field']];
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);

            if(!empty($vars['email_field']) && !empty($email))
            {
                $ok = false;

                if(filter_var($email, FILTER_VALIDATE_EMAIL)!==FALSE)
                {
                    $wu_id = $DB->query_single(
                        'id',
                        'nv_webusers',
                        ' email = :email
                          AND website = :wid',
                        null,
                        array(
                            ':wid' => $website->id,
                            ':email' => $email
                        )
                    );

                    $wu = new webuser();

                    if(!empty($wu_id))
                    {
                        // webuser is already signed up!
                        $wu->load($wu_id);

                        // check if the webuser is not currently blocked
                        // and is not an expired account
                        if( $wu->access == 0 ||
                            ( $wu->access == 2 &&
                                ($wu->access_begin==0 || time() > $wu->access_begin) &&
                                ($wu->access_end==0 || time() < $wu->access_end)
                            )
                        )
                        {
                            // all fine, resubscribe
                            $wu->newsletter = 1;
                            $ok = $wu->save();
                        }

                        // a webuser exists, but the account is awating to be validated
                        if($wu->access==1 && !empty($wu->activation_key))
                        {
                            // do nothing
                        }
                    }

                    // no webuser found using this email address
                    if(empty($wu_id))
                    {
                        // create a new webuser account with that email
                        $username = nvweb_webuser_generate_username($email);

                        // finally create a new webuser account
                        $wu->id = 0;
                        $wu->website = $website->id;
                        $wu->email = $email;
                        $wu->newsletter = 1;
                        $wu->language = $current['lang']; // infer the webuser language by the active website language
                        $wu->username = $username;
                        $wu->access = 1;    // user is blocked until the server recieves an email confirmation
                        $wu->activation_key = bin2hex(openssl_random_pseudo_bytes( 32 ));
                        $ok = $wu->save();

                        // send a message to verify the new user's email
                        $email_confirmation_link = $website->absolute_path().'/nv.webuser/verify?email='.$wu->email.'&hash='.$wu->activation_key;
                        $message = navigate_compose_email(array(
                            array(
                                'title' => $website->name,
                                'content' => $webgets[$webget]['translations']['click_to_confirm_account'].
                                            '<br />'.
                                            '<a href="'.$email_confirmation_link.'">'.$email_confirmation_link.'</a>'
                            ),
                            array(
                                'footer' =>
                                    $webgets[$webget]['translations']['email_confirmation_notice'].
                                    '<br />'.
                                    '<a href="'.$website->absolute_path().$website->homepage().'">'.$website->name.'</a>'
                            )
                        ));

                        nvweb_send_email($website->name, $message, $wu->email);
                        $pending_confirmation = true;
                    }
                }

                $message = $webgets[$webget]['translations']['subscribe_error'];
                if($pending_confirmation)
                {
                    $message = $webgets[$webget]['translations']['email_confirmation'];
                }
                else if($ok)
                {
                    $message = $webgets[$webget]['translations']['subscribed_ok'];
                }

                if(empty($vars['notify']))
                {
                    $vars['notify'] = 'inline';
                }

                switch($vars['notify'])
                {
                    case 'alert':
                        nvweb_after_body('js', 'alert("'.$message.'");');
                        break;

                    case 'inline':
                        if($ok)
                        {
                            $out = '<div class="nvweb-newsletter-form-success">'.$message.'</div>';
                        }
                        else
                        {
                            $out = '<div class="nvweb-newsletter-form-error">'.$message.'</div>';
                        }
                        break;

                    case 'boolean':
                        $out = $ok;
                        break;

                    case 'false':
                        break;

                    // javascript callback
                    case 'callback':
                    default:
                        if($ok)
                            nvweb_after_body('js', $vars['callback'].'("'.$message.'");');
                        else
                        {
                            if(!empty($vars['error_callback']))
                            {
                                nvweb_after_body('js', $vars['error_callback'].'("'.$message.'");');
                            }
                            else
                            {
                                nvweb_after_body('js', $vars['callback'].'("'.$message.'");');
                            }
                        }
                        break;
                }

            }

            break;

        case 'customer_account':
            $out = nvweb_webuser_customer_account();
            break;
    }

    return $out;
}

function nvweb_webuser_password_strength($password,  $username="", $email="")
{
    $weak_passwords = array(
        '12345678', '11111111', '99999999', '00000000',
        'aaaaaaaa', 'abcdefgh', 'zzzzzzzz',
        'password', 'testtest'
    );

    $score = 100;

    if(strlen($password) < 8)
    {
        $score = 0;
    }

    if($password==$username || $password == $email)
    {
        $score = 0;
    }

    if(in_array($password, $weak_passwords))
    {
        $score = 0;
    }

    return $score;
}

function nvweb_webuser_sign_up_send_verification($wu, $callback="")
{
    global $website;
    global $webgets;

    $webget = 'webuser';

    // send a message to verify the new user's email
    if(empty($callback))
    {
        $callback = $website->homepage();
    }

    $email_confirmation_link = $website->absolute_path().'/nv.webuser/confirm?email='.$wu->email.'&hash='.$wu->activation_key.'&callback='.base64_encode($callback);

    $subject = $website->name . ' | ' . $webgets[$webget]['translations']['confirm_your_account'];

    $body  = navigate_compose_email(
        array(
            array(
                'title' => $website->name,
                'content' => $webgets[$webget]['translations']['click_to_confirm_account'].
                    '<br />'.
                    '<a href="'.$email_confirmation_link.'">'.$email_confirmation_link.'</a>'
            ),
            array(
                'footer' =>
                    $webgets[$webget]['translations']['account_confirmation_notice'].
                    '<br />'.
                    '<a href="'.$website->absolute_path().$website->homepage().'">'.$website->name.'</a>'
            )
        )
    );

    return navigate_send_email($subject, $body, $wu->email);
}

function nvweb_webuser_generate_username($email)
{
    global $DB;
    global $website;

    // generate a valid username
    // try to get the left part of the email address, except if it is a common account name
    $username = strtolower(substr($email, 0, strpos($email, '@'))); // left part of the email

    if(!empty($username) && !in_array($username, array('info', 'admin', 'contact', 'demo', 'test')))
    {
        // check if the proposed username already exists,
        // in that case use the full email as username
        // ** if the email already exists, the subscribe process only needs to update the newsletter subscription!
        $wu_id = $DB->query_single(
            'id',
            'nv_webusers',
            ' LOWER(username) = :username AND website = :wid',
            null,
            array(
                ':wid' => $website->id,
                ':username' => $username
            )
        );
    }

    if(empty($wu_id))
    {
        // proposed username is valid,
        // continue with the registration
    }
    else if(!empty($wu_id) || empty($username))
    {
        // a webuser with the proposed name already exists... or is empty
        // try using another username -- maybe the full email address?

        $username = $email;

        $wu_id = $DB->query_single(
            'id',
            'nv_webusers',
            ' LOWER(username) = :email AND website = :wid',
            null,
            array(
                ':wid' => $website->id,
                ':email' => $email
            )
        );

        if(empty($wu_id))
        {
            // proposed username is valid,
            // continue with the registration
        }
        else
        {
            // oops, email is already used for another webuser account
            // let's create a unique username and go on
            $username = uniqid($username . '-');
        }
    }

    return $username;
}

function nvweb_webuser_customer_account()
{
    global $website;
    global $session;
    global $current;
    global $webuser;
    global $html;

    $current['pagecache_enabled'] = false;

    $sign_in_info = "";
    $sign_in_error = "";

    // add jQuery if has not already been loaded in the template
    if(strpos($html, 'jquery')===false)
    {
        $out[] = '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>';
    }

    // nv_webuser resources
    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_webuser.css" />'.
        '<script src="'.NAVIGATE_URL.'/js/tools/nv_webuser.js"></script>'
    );

    $fontawesome_available = (
        strpos($html,'font-awesome.') ||
        strpos($html,'<i class="fa ')
    );

    $out = array();

    if(empty($webuser->id))
    {
        // TODO: allow website management to redirect to a custom login page
        // process form, if sent
        if(!empty($_POST))
        {
            switch($_POST["nv_wu_submit"])
            {
                case 'sign_in':
                    $emailuser_field = $_POST['nv_wu_sign_in_emailusername'];
                    $password_field = $_POST['nv_wu_sign_in_password'];

                    $ok = $webuser->authenticate($website->id, $emailuser_field, $password_field);
                    if(!$ok)
                    {
                        // try authenticating with the email field
                        $ok = $webuser->authenticate_by_email($website->id, $emailuser_field, $password_field);
                    }

                    if($ok)
                    {
                        // success, reload the current page (user profile)
                        $webuser->set_cookie();
                        nvweb_clean_exit(nvweb_self_url());
                    }

                    $sign_in_error = t(765, "There was an error with your Login/Password combination. Please try again.");
                    break;

                case 'forgot_password':
                    $ok = nvweb_webuser(array(
                        "mode" => "forgot_password",
                        "email_field" => "nv_wu_sign_in_emailusername",
                        "notify" => "boolean"
                    ));

                    if($ok)
                    {
                        $sign_in_info = t(767, "An e-mail with a confirmation has been sent to your address.");
                    }
                    else
                    {
                        $sign_in_error = t(56, "Unexpected error");
                    }
                    break;

                default:
                    break;
            }
        }

        $sign_in_symbol = '';
        $sign_up_symbol = '';
        $pwa_symbol = '';
        $purchase_conditions_link = nvweb_prepare_link($website->shop_purchase_conditions_path);

        if($fontawesome_available)
        {
            $sign_in_symbol = '<i class="fa fa-user"></i> ';
            $sign_up_symbol = '<i class="fa fa-user-plus"></i> ';
            $pwa_symbol = '<i class="fa fa-angle-double-right "></i> ';
        }

        $out = array();
        $out[] = '<div class="nv_wu_identification_form">';
        $out[] = '    <form action="?mode=identification" id="nv_wu_identification_form" method="post">';
        $out[] = '        <div class="nv_cart-flex-sb">
                              <div>
                                  <h3>'.$sign_in_symbol.t(758, "Sign in").'</h3>
                                  <input type="hidden" name="nv_wu_submit" value="" />
                                  <div>
                                      <label>'.t(760, "E-mail or username").'</label>
                                      <input type="text" name="nv_wu_sign_in_emailusername" value="" />
                                  </div>
                                  <div>
                                      <label>'.t(2, "Password").'</label>                                  
                                      <input type="password" name="nv_wu_sign_in_password" value="" />
                                      <small style="float: right;">
                                            <a href="#" data-action="forgot_password">'.t(407, "Forgot password?").'</a>
                                      </small>
                                  </div>
                                  <div class="nv_wu-sign_in_info_message">                                   
                                       <p class="custom_message">'.$sign_in_info.'</p>
                                  </div>
                                  <div class="nv_wu-sign_in_error_message">
                                       <p class="forgot_password_missing_username">'.t(766, "Please enter your e-mail address in the username field and try again.").'</p>
                                       <p class="custom_message">'.$sign_in_error.'</p>
                                  </div>
                                  <div>
                                      <button class="nv_wu_submit_btn" data-action="sign_in">'.t(3, "Enter" ).'</button>
                                  </div>
                              </div>                              
                          </div>                          
                      </form>
                  </div>
        ';

        nvweb_after_body('js', 'nv_cart_identification_init()');
    }
    else
    {
        $customer_account_menu = array(
            array(
                'name' => 'overview',
                'title' => t(818, "Overview"),
                'icon' => ($fontawesome_available? '<i class="fa fa-fw fa-dashboard"></i>' : ''),
                'enabled' => false,
                'selected' => false,
                'position' => 1
            ),
            array(
                'name' => 'orders',
                'title' => t(26, "Orders"),
                'icon' => ($fontawesome_available? '<i class="fa fa-fw fa-shopping-basket"></i>' : ''),
                'enabled' => true,
                'selected' => true,
                'position' => 2
            ),
            array(
                'name' => 'downloads',
                'title' => t(820, "Downloads"),
                'icon' => ($fontawesome_available? '<i class="fa fa-fw fa-download"></i>' : ''),
                'enabled' => false,
                'selected' => false,
                'position' => 3
            ),
            array(
                'name' => 'addresses',
                'title' => t(819, "Addresses"),
                'icon' => ($fontawesome_available? '<i class="fa fa-fw fa-address-book"></i>' : ''),
                'enabled' => true,
                'selected' => false,
                'position' => 4
            ),
            array(
                'name' => 'payment_methods',
                'title' => t(783, "Payment methods"),
                'icon' => ($fontawesome_available? '<i class="fa fa-fw fa-money"></i>' : ''),
                'enabled' => false,
                'selected' => false,
                'position' => 5
            ),
            array(
                'name' => 'settings',
                'title' => t(459, "Settings"),
                'icon' => ($fontawesome_available? '<i class="fa fa-fw fa-cog"></i>' : ''),
                'enabled' => true,
                'selected' => false,
                'position' => 6
            ),
            array(
                'name' => 'sign_out',
                'title' => t(822, "Sign out"),
                'icon' => ($fontawesome_available? '<i class="fa fa-fw fa-sign-out"></i>' : ''),
                'url' => '?webuser_signout',
                'enabled' => true,
                'selected' => false,
                'position' => 7
            )
        );

        // TODO: create an event to allow modifying this menu details

        $render_section = value_or_default($_REQUEST['s'], '');

        $customer_account_menu_html = array();
        $customer_account_menu_html[] = '<ul class="nv_wu-customer_account_menu">';
        foreach($customer_account_menu as $option)
        {
            if(isset($option['url']))
            {
                $url = $option['url'];
            }
            else
            {
                $url = '?s='.$option['name'];
            }

            // do we have to set this option as selected?
            if(!empty($render_section))
            {
                $option['selected'] = ($render_section == $option['name']);
            }
            else if($option['selected'])
            {
                $render_section = $option['name'];
            }

            if($option['enabled'])
            {
                $customer_account_menu_html[] = '<li class="'.($option['selected']? 'selected' : '').'"><a href="'.$url.'">'.$option['icon'].$option['title'].'</a></li>';
            }
        }
        $customer_account_menu_html[] = '</ul>';

        if($vars['display_title'] != 'false')
        {
            $out[] = '<h3>'.t(821, "Your account").'</h3>';
        }
        $out[] = '<div class="nv_wu-customer_account_wrapper">';
        $out[] = '    <div class="nv_wu-customer_account_menu_wrapper">'.implode("\n", $customer_account_menu_html).'</div>';
        $out[] = '    <div class="nv_wu-customer_account_content">';

        $out[] = nvweb_webuser_customer_account_render($render_section);

        $out[] = '    </div>';
        $out[] = '</div>';
    }

    $out = implode("\n", $out);

    return $out;
}

function nvweb_webuser_customer_account_render($section)
{
    $out = '';

    switch($section)
    {
        case 'overview':
            // TODO: Customer account section "Overview" not implemented
            break;

        case 'orders':
            if(isset($_GET['oid']))
            {
                $out = nvweb_webuser_customer_account_render_order($_GET['oid']);
            }
            else
            {
                $out = nvweb_webuser_customer_account_render_orders_list();
            }
            break;

        case 'downloads':
            // TODO: Customer account section "Downloads" not implemented
            break;

        case 'addresses':
            $out = nvweb_webuser_customer_account_addresses();
            break;

        case 'settings':
            $out = nvweb_webuser_customer_account_settings();
            break;

        default:
            // maybe a plugin generated section?
            // TODO: try to call event trigger
            break;
    }

    return $out;
}

function nvweb_webuser_customer_account_render_orders_list()
{
    global $DB;
    global $webuser;
    global $website;

    $out = array();

    $DB->query('
      SELECT o.id, o.reference, o.date_created, o.total, o.currency, o.status, o.payment_done, SUM(ol.quantity) as products 
      FROM nv_orders o 
      LEFT JOIN nv_orders_lines ol 
      ON ol.order = o.id
      WHERE o.webuser = '.$webuser->id.' 
        AND o.website = '.$website->id.'
      GROUP BY o.id, ol.order, o.date_created
      ORDER BY o.date_created DESC
      LIMIT 100'
    );
    $orders = $DB->result();

    if(empty($orders))
    {
        $out[] = '<blockquote>'.t(823, "No order has been made yet.").'</blockquote>';
    }
    else
    {
        $out[] = '<table class="nv_wu-customer_account-orders">';

        $out[] = '<tr>';
        $out[] = '    <th data-content="order_reference">'.t(707, "Reference").'</th>';
        $out[] = '    <th data-content="order_date">'.t(86, "Date").'</th>';
        $out[] = '    <th data-content="order_products">'.t(25, "Products").'</th>';
        $out[] = '    <th data-content="order_total">'.t(706, "Total").'</th>';
        //$out[] = '    <th>'.t(708, "Paid").'</th>';
        $out[] = '    <th data-content="order_status">'.t(68, "Status").'</th>';
        $out[] = '    <th data-content="order_actions">'.t(546, "Actions").'</th>';
        $out[] = '</tr>';

        foreach($orders as $order)
        {
            $out[] = '<tr>';
            $out[] = '    <td data-content="order_reference">'.(!empty($order->reference)? $order->reference : '#'.$order->id).'</td>';
            $out[] = '    <td data-content="order_date" title="'.core_ts2date($order->date_created, true).'">'.core_ts2date($order->date_created).'</td>';
            $out[] = '    <td data-content="order_products">'.core_decimal2string($order->products).'</td>';
            $out[] = '    <td data-content="order_total">'.core_price2string($order->total, $order->currency).'</td>';
            //$out[] = '    <td>'.$order->payment_done.'</td>';
            $out[] = '    <td data-content="order_status">'.order::status($order->status).'</td>';
            $out[] = '    <td data-content="order_actions"><a href="?s=orders&oid='.$order->id.'" class="button tiny">'.t(824, "View").'</a></td>';
            $out[] = '</tr>';
        }

        $out[] = '</table>';
    }

    $out = implode("\n", $out);
    return $out;
}

function nvweb_webuser_customer_account_render_order($order_id)
{
    global $website;
    global $webuser;
    global $current;
    global $html;

    $out = array();

    $order = new order();
    $order->load($order_id);

    $fontawesome_available = (
        strpos($html,'font-awesome.') ||
        strpos($html,'<i class="fa ')
    );

    $icons = array();
    if($fontawesome_available)
    {
        $icons = array(
            'track_shipping' => '<i class="fa fa-lg fa-truck fa-fw"></i> ',
            'payment_method' => '<i class="fa fa-lg fa-fax fa-fw"></i> ',
            'addresses' => '<i class="fa fa-lg fa-address-book fa-fw"></i> ',
            'notes' => '<i class="fa fa-lg fa-sticky-note fa-fw"></i> '
        );
    }

    if( $order->website != $website->id ||
        $order->webuser != $webuser->id
    )
    {
        $out[] = '<blockquote>'.t(825, "The requested order does not exist or cannot be displayed.").'</blockquote>';
    }
    else
    {
        $out[] = '<div class="nv_wu-customer_account-order-wrapper">';
        $out[] = '    <div class="nv_cart-flex-sb">';
            $out[] = '    <div><strong>Pedido</strong><br />'.$order->reference.'</div><br />';
            $out[] = '    <div><strong>Fecha de compra</strong><br />'.core_ts2date($order->date_created, true).'</div>';
        $out[] = '    </div>';
        $out[] = '    <br />';
        $out[] = '    <div class="nv_cart-flex-sb">';
        $out[] = '        <div><strong>Estado</strong><br />'.order::status($order->status).'<br /></div>';
        $out[] = '    </div>';
        $out[] = '</div>';

        $out[] = '<br />';

        $out[] = '<div class="nv_cart nv_cart_view_summary">';
        $out[] = '        <table width="100%">';
        $out[] = '            <thead>';
        $out[] = '                <tr>';
        $out[] = '                    <th class="nv_cart_header_product">'.t(198, "Product").'</th>';
        $out[] = '                    <th width="12%" class="nv_cart_header_quantity">'.t(724, 'Quantity').'</th>';
        $out[] = '                    <th width="10%" class="nv_cart_header_subtotal">'.t(685, 'Subtotal').'</th>';
        $out[] = '                </tr>';
        $out[] = '            </thead>';
        $out[] = '            <tbody>';

        $out[] = '          <nv object="list" source="order" id="'.$order_id.'">';
        $out[] = '            <tr class="nv_cart_line">';
        $out[] = '                <td valign="top" class="nv_cart_line_title">
                                   <div>{{nvlist source="order" value="title"}}</div>
                                 </td>';
        $out[] = '                <td valign="top" class="nv_cart_line_quantity">';
        $out[] = '                      <span class="nv_cart_line_quantity_label">'.t(724, 'Quantity').'</span>';
        $out[] = '                      <span class="nv_cart_line_quantity_value">{{nvlist source="order" value="quantity"}}</span>';
        $out[] = '                </td>';
        $out[] = '                <td valign="top" class="nv_cart_line_price">{{nvlist source="order" value="subtotal_with_taxes_without_coupon"}}</td>';
        $out[] = '            </tr>';
        $out[] = '          </nv>';

        $out[] = '            </tbody>';

        $out[] = '            <tfoot>';

        if(!empty($order->coupon))
        {
           $coupon_info = '<span class="nv_cart_coupon_info">' . $order->coupon_code . '</span>';
           $coupon_amount = core_price2string($order->coupon_amount, $order->currency);

           $out[] = '            <tr class="nv_cart_coupon">';
           $out[] = '                <td colspan="2" style="text-align: right;" class="nv_cart_coupon_information">'.t(788, "Discounts applied").$coupon_info.'</td>';
           $out[] = '                <td colspan="1" style="text-align: right;" class="nv_cart_coupon_amount"><span>'.$coupon_amount.'</span></td>';
           $out[] = '            </tr>';
        }

        $shipping_method_data = json_decode($order->shipping_data->method, true);
        $shipping_method_title = $shipping_method_data['dictionary'][$current['lang']]['title'];

        $out[] = '            <tr class="nv_cart_shipping_method">';
        $out[] = '                <td colspan="2" style="text-align: right;" class="nv_cart_shipping_method_information">'.t(720, "Shipping method").' <strong>'.$shipping_method_title.'</strong></td>';
        $out[] = '                <td colspan="1" style="text-align: right;" class="nv_cart_shipping_method_price"><span>'.core_price2string($order->shipping_invoiced, $order->currency).'</span></td>';
        $out[] = '            </tr>';


        $out[] = '                <tr class="nv_cart_subtotal">';
        $out[] = '                    <td colspan="2" style="text-align: right;">'.t(706, "Total").'</td>';
        $out[] = '                    <td colspan="1" style="text-align: right;" class="nv_cart_subtotal_amount">
                                       <big>'.core_price2string($order->total, $order->currency).'</big>                              
                                     </td>';
        $out[] = '                </tr>';
        $out[] = '            </tfoot>';
        $out[] = '        </table>';

        $out[] = '</div>';

        if(!empty($order->shipping_data->tracking_url))
        {
            $out[] = '<br /><div><h6>'.@$icons['track_shipping'].t(826, "Track delivery").'</h6></div>';
            $out[] = '<div class="nv_wu-customer_account-order-wrapper">';
            $out[] = '    <div><strong>'.$order->shipping_data->carrier.'</strong> '.$order->shipping_data->reference.'</div>';
            $out[] = '    <div><a href="'.$order->shipping_data->tracking_url.'" target="_blank">'.$order->shipping_data->tracking_url.'</a></div>';
            $out[] = '</div>';
        }

        $payment_method = new payment_method();
        $payment_method->load($order->payment_method);

        $out[] = '<br /><div><h6>'.@$icons['payment_method'].t(727, "Payment method").'</h6></div>';
        $out[] = '<div class="nv_wu-customer_account-order-wrapper">';
        $out[] = '    <div>'.$payment_method->dictionary[$current['lang']]['title'].'</div>';
        if($order->payment_done != 1)
        {
            $out[] = '    <div>'.t(68, "Status").': <strong>'.t(710, "Pending").'</strong></div>';
        }
        else
        {
            $out[] = '    <div>'.t(68, "Status").': <strong>'.t(713, "Completed").'</strong></div>';
        }
        $out[] = '</div>';

        $out[] = '<br /><div><h6>'.@$icons['addresses'].t(819, "Addresses").'</h6></div>';
        $out[] = '<div class="nv_wu-customer_account-order-wrapper">';
        $country_name = property::country_name_by_code($order->shipping_address->country);
        $out[] = '  <div class="nv_cart-flex-sb">';
        $out[] = '      <div>';
        $out[] = '         <h5>'.t(716, "Shipping address").'</h5>';
        $out[] = '         <div class="nv_cart_shipping_address_information"><p>';
        $out[] = '          '.$order->shipping_address->name.'<br />';
        if(!empty($order->shipping_address->company))
        {
            $out[] = '[ '.$order->shipping_address->company.' ]<br />';
        }
        $out[] = '          '.$order->shipping_address->address.'<br />';
        $out[] = '          '.$order->shipping_address->zipcode.' '.$order->shipping_address->location.'<br />';
        if(!empty($order->shipping_address->region))
        {
            $region_name = property::country_region_name_by_code($order->shipping_address->region);
            $out[] = '      ' . $region_name . ', ';
        }
        $out[] = $country_name;
        $out[] = '        </div>';
        $out[] = '      </div>';

        $out[] = '      <div>';
        $out[] = '        <h5>'.t(717, "Billing address").'</h5>';

        if(empty($cart['address_billing']))
        {
            $out[] = '<small>('.t(751, "Same as shipping address").')</small>';
        }
        else
        {
            $out[] = '          '.$order->shipping_address->name.'<br />';
            if(!empty($order->shipping_address->company))
            {
                $out[] = '[ '.$order->shipping_address->company.' ]<br />';
            }
            $out[] = '          '.$order->shipping_address->address.'<br />';
            $out[] = '          '.$order->shipping_address->zipcode.' '.$order->shipping_address->location.'<br />';
            if(!empty($order->shipping_address->region))
            {
                $region_name = property::country_region_name_by_code($order->shipping_address->region);
                $out[] = '      ' . $region_name . ', ';
            }
            $out[] = $country_name;
        }
        $out[] = '        <br /><br />';
        $out[] = '      </div>';

        $out[] = '  </div>';
        $out[] = '</div>';

        if(!empty($order->customer_notes))
        {
            $out[] = '<br /><div><h6>'.@$icons['notes'].t(168, "Notes").'</h6></div>';
            $out[] = '<div class="nv_wu-customer_account-order-wrapper">';
            $out[] = '    <div>'.nl2br($order->customer_notes).'</div>';
            $out[] = '</div>';
        }

        $out[] = '<br /><br />';
    }

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'
    );

    $out = implode("\n", $out);

    return $out;
}

function nvweb_webuser_customer_account_addresses()
{
    global $webuser;
    global $html;

    $out = array();

    $fontawesome_available = (
            strpos($html,'font-awesome.') ||
            strpos($html,'<i class="fa ')
        );

    $icons = array();
    if($fontawesome_available)
    {
        $icons = array(
            'success' => '<i class="fa fa-check fa-fw"></i> ',
            'shipping' => '<i class="fa fa-truck"></i> ',
            'billing' => '<i class="fa fa-address-card"></i> '
        );
    }

    //$billing_same_as_shipping = true;

    // process form, if sent
    if(!empty($_POST))
    {
        $address_shipping = array(
            'name'     => trim($_POST['wu_cusacc_shipping_name']),
            'nin'      => trim($_POST['wu_cusacc_shipping_nin']), // National identification number
            'company'  => trim($_POST['wu_cusacc_shipping_company']),
            'address'  => trim($_POST['wu_cusacc_shipping_address']),
            'location' => trim($_POST['wu_cusacc_shipping_location']),
            'zipcode'  => trim($_POST['wu_cusacc_shipping_zipcode']),
            'country'  => trim($_POST['wu_cusacc_shipping_country']),
            'region'   => trim($_POST['wu_cusacc_shipping_region']),
            'phone'    => trim($_POST['wu_cusacc_shipping_phone'])
        );
/*
        if(isset($_POST['wu_cusacc_billing_same_as_shipping']) && $_POST['wu_cusacc_billing_same_as_shipping']=='1')
        {
            $address_billing = $address_shipping;
        }
        else
        {
            $billing_same_as_shipping = false;
            $address_billing = array(
                'name'     => trim($_POST['wu_cusacc_billing_name']),
                'nin'      => trim($_POST['wu_cusacc_billing_nin']),
                'company'  => trim($_POST['wu_cusacc_billing_company']),
                'address'  => trim($_POST['wu_cusacc_billing_address']),
                'location' => trim($_POST['wu_cusacc_billing_location']),
                'zipcode'  => trim($_POST['wu_cusacc_billing_zipcode']),
                'country'  => trim($_POST['wu_cusacc_billing_country']),
                'region'   => trim($_POST['wu_cusacc_billing_region']),
                'email'    => trim($_POST['wu_cusacc_billing_email']),
                'phone'    => trim($_POST['wu_cusacc_billing_phone'])
            );
        }

        // verify fields, then save them and continue or show an error message
        // required fields: name, address, location, zipcode, country, email, phone
        $errors = array();
        if( empty($address_shipping['name']) ||
            empty($address_shipping['address']) ||
            empty($address_shipping['location']) ||
            empty($address_shipping['zipcode']) ||
            empty($address_shipping['country']) ||
            empty($address_shipping['email']) ||
            empty($address_shipping['phone'])
            )
        {
            $errors[] = '['.t(716, "Shipping address").'] '.t(444, "You left some required fields blank.");
        }

        // validate email address
        if( !filter_var($address_shipping['email'], FILTER_VALIDATE_EMAIL) )
        {
            $errors[] = '['.t(716, "Shipping address").'] '.t(768, "Invalid e-mail address").': '.$address_shipping['email'];
        }
/*
        if( !$billing_same_as_shipping )
        {
            if( empty($address_billing['name']) ||
                empty($address_billing['address']) ||
                empty($address_billing['location']) ||
                empty($address_billing['zipcode']) ||
                empty($address_billing['country']) ||
                empty($address_billing['email']) ||
                empty($address_billing['phone'])
            )
            {
                $errors[] = '['.t(717, "Billing address").'] '.t(444, "You left some required fields blank.");
            }

            if( !filter_var($address_shipping['email'], FILTER_VALIDATE_EMAIL) )
            {
                $errors[] = '['.t(717, "Billing address").'] '.t(768, "Invalid e-mail address").': '.$address_billing['email'];
            }
        }
*/

        if(empty($errors))
        {
            // everything ok, save and display confirmation

            $webuser->name = $address_shipping['name'];
            $webuser->nin = $address_shipping['nin'];
            $webuser->company = $address_shipping['company'];
            $webuser->address = $address_shipping['address'];
            $webuser->location = $address_shipping['location'];
            $webuser->zipcode = $address_shipping['zipcode'];
            $webuser->country = $address_shipping['country'];
            $webuser->region = $address_shipping['region'];
            $webuser->phone = $address_shipping['phone'];
            $webuser->save();

            $out[] = '<div class="nv_webuser_customer_account-info_message" style="display: block;">                                   
                          <p>'.@$icons['success'].t(53, "Data successfully saved").'</p>
                      </div>';
        }
    }
    else
    {
        // get default address values

        // get the last order data made by this customer
        $addresses = order::get_addresses($webuser->id);

        // TODO: allow selecting a different address from the list of previously used
        // right now, we default using the most recently used, if any
        $address_shipping = array(
            'name' => $webuser->fullname,
            'nin' => $webuser->nin,
            'company' => $webuser->company,
            'address' => $webuser->address,
            'location' => $webuser->location,
            'zipcode' => $webuser->zipcode,
            'country' => $webuser->country,
            'region' => $webuser->region,
            'phone' => $webuser->phone
        );

        if(!empty($addresses))
        {
            $address_shipping = $addresses[0];
        }

        $billing_same_as_shipping = true;
    }

    if(!empty($errors))
    {
        $errors = array_map(function($v) { return '<span>'.$v.'</span><br />'; }, $errors);
        array_unshift($errors, '<div class="nv_cart_errors_title">'.t(740, "Error").'</div>');
        $errors = '<div class="nv_cart_errors">'.implode("\n", $errors).'</div>';
    }
    else
    {
        $errors = "";
    }

    $out[] = '<div class="nv_cart_address_form">';
    $out[] = $errors;
    $out[] = '    <form action="?s=addresses" method="post">';
    $out[] = '      <h4>'.@$icons['shipping'].t(716, "Shipping address").'</h4>                
                    <div>
                        <div>
                            <label>'.t(752, "Full name").'</label>
                            <input type="text" name="wu_cusacc_shipping_name" required value="'.core_special_chars($address_shipping['name']).'" />
                        </div>                        
                    </div>
                    <div class="nv_cart-flex-sb">
                        <div>
                            <label>'.t(778, "National identification number").'</label>
                            <input type="text" name="wu_cusacc_shipping_nin" value="'.core_special_chars($address_shipping['nin']).'" />
                        </div>
                        <div>
                            <label>'.t(592, "Company").'</label>
                            <input type="text" name="wu_cusacc_shipping_company" value="'.core_special_chars($address_shipping['company']).'" />
                        </div>
                    </div>
                    <div>
                        <label>'.t(233, "Address").'</label>
                        <input type="text" name="wu_cusacc_shipping_address" required value="'.core_special_chars($address_shipping['address']).'" />
                    </div>
                    <div class="nv_cart-flex-sb">
                        <div>
                            <label>'.t(319, "Location").'</label>
                            <input type="text" name="wu_cusacc_shipping_location" required value="'.core_special_chars($address_shipping['location']).'" />
                        </div>
                        <div>
                            <label>'.t(318, "Zip code").'</label>
                            <input type="text" name="wu_cusacc_shipping_zipcode" value="'.core_special_chars($address_shipping['zipcode']).'" />
                        </div>
                    </div>
                    <div class="nv_cart-flex-sb">
                        <div>
                            <label>'.t(224, "Country").'</label>
                            <nv object="nvweb" name="forms" mode="country_field" required field_name="wu_cusacc_shipping_country" default="'.core_special_chars($address_shipping['country']).'" />
                        </div>
                        <div>
                            <label>'.t(473, "Region").'</label>
                            <nv object="nvweb" name="forms" mode="country_region_field" field_name="wu_cusacc_shipping_region" country_field="wu_cusacc_shipping_country" default="'.core_special_chars($address_shipping['region']).'" />
                        </div>
                    </div>
                    <div class="nv_cart-flex-sb">
                        <div>
                            <label>'.t(320, "Phone").'</label>
                            <input type="text" name="wu_cusacc_shipping_phone" required value="'.core_special_chars($address_shipping['phone']).'" />
                        </div>
                    </div>
                '.
                 /* '
                    <h4>'.@$icons['billing'].t(717, "Billing address").'</h4>
                    
                    <input type="checkbox" name="wu_cusacc_billing_same_as_shipping" 
                           id="wu_cusacc_billing_same_as_shipping" value="1" 
                           '.($billing_same_as_shipping? 'checked="checked"' : '').'/>
                    <label for="wu_cusacc_billing_same_as_shipping">'.t(751, "Same as shipping address").'</label>
                    
                    <div class="wu_cusacc_billing_address_wrapper">
                    
                        <div>
                            <div>
                                <label>'.t(752, "Full name").'</label>
                                <input type="text" name="wu_cusacc_billing_name" value="'.$address_billing['name'].'" />
                            </div>                            
                        </div>
                        <div class="nv_cart-flex-sb">
                            <div>
                                <label>'.t(778, "National identification number").'</label>
                                <input type="text" name="wu_cusacc_billing_nin" value="'.$address_billing['nin'].'" />
                            </div>
                            <div>
                                <label>'.t(592, "Company").'</label>
                                <input type="text" name="wu_cusacc_billing_company" value="'.$address_billing['company'].'" />
                            </div>
                        </div>
                        <div>
                            <label>'.t(233, "Address").'</label>
                            <input type="text" name="wu_cusacc_billing_address" value="'.$address_billing['address'].'" />
                        </div>
                        <div class="nv_cart-flex-sb">
                            <div>
                                <label>'.t(319, "Location").'</label>
                                <input type="text" name="wu_cusacc_billing_location" value="'.$address_billing['location'].'" />
                            </div>
                            <div>
                                <label>'.t(318, "Zip code").'</label>
                                <input type="text" name="wu_cusacc_billing_zipcode" value="'.$address_billing['zipcode'].'" />
                            </div>
                        </div>
                        <div class="nv_cart-flex-sb">
                            <div>
                                <label>'.t(224, "Country").'</label>
                                <nv object="nvweb" name="forms" mode="country_field" field_name="wu_cusacc_billing_country" default="'.$address_billing['country'].'" />
                            </div>
                            <div>
                                <label>'.t(473, "Region").'</label>
                                <nv object="nvweb" name="forms" mode="country_region_field" field_name="wu_cusacc_billing_region" country_field="wu_cusacc_billing_country"  default="'.$address_billing['region'].'" />
                            </div>
                        </div>
                        <div class="nv_cart-flex-sb">
                            <div>
                                <label>'.t(44, "E-Mail").'</label>
                                <input type="text" name="wu_cusacc_billing_email" value="'.$address_billing['email'].'" />
                            </div>
                            <div>
                                <label>'.t(320, "Phone").'</label>
                                <input type="text" name="wu_cusacc_billing_phone" value="'.$address_billing['phone'].'" />
                            </div>
                        </div>                        
                    </div>
                    <br />
                 '.*/
                    '<br />
                    <div>
                        <input type="submit" class="button nv_cart_button_continue" value="'.t(34, "Save").'" />
                    </div>';

    $out[] = '    </form>';
    $out[] = '</div>';

    $out = implode("\n", $out);
/*
    nvweb_after_body('js', '
        $("input[name=wu_cusacc_billing_same_as_shipping]").on("click change", function()
        {        
            if($(this).is(":checked"))
            {
                $(".wu_cusacc_billing_address_wrapper").slideUp();
            }
            else
            {
                $(".wu_cusacc_billing_address_wrapper").slideDown();
            }                
        });
        
        $("input[name=wu_cusacc_billing_same_as_shipping]").trigger("change"); 
    ');
*/
    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'
    );

    return $out;
}

function nvweb_webuser_customer_account_settings()
{
    global $webuser;
    global $website;
    global $html;
    global $webgets;

    $out = array();

    $fontawesome_available = (
            strpos($html,'font-awesome.') ||
            strpos($html,'<i class="fa ')
        );

    $icons = array();
    if($fontawesome_available)
    {
        $icons = array(
            'success' => '<i class="fa fa-check fa-fw"></i> ',
            'settings' => '<i class="fa fa-cog fa-fw"></i> ',
            'password' => '<i class="fa fa-asterisk fa-fw"></i> '
        );
    }

    $errors = array();
    $blank_fields = array();
    $messages = array();

    // process form, if sent
    if(!empty($_POST))
    {
        // password verify
        $password_current = $_POST['wu_cusacc_settings_password_current'];
        if(!$webuser->check_password($password_current))
        {
            $errors[] = t(833, "Incorrect password");
        }

        $fullname = core_purify_string(filter_input(INPUT_POST, 'wu_cusacc_settings_fullname', FILTER_SANITIZE_STRING));
        if(empty($fullname))
        {
            $blank_fields = t(752, "Full name");
        }
        else
        {
            $webuser->fullname = $fullname;
        }

        $username = core_purify_string(filter_input(INPUT_POST, 'wu_cusacc_settings_username', FILTER_SANITIZE_STRING));
        if(empty($username))
        {
            $blank_fields = t(1, "User");
        }
        else
        {
            if($webuser->username != $username)
            {
                // validate if requested username is not in use
                $available = webuser::available($username, $website->id);
                if(!$available)
                {
                    $errors[] = t(771, "The specified username (or email address) is already being used on our website");
                }
                else
                {
                    $webuser->username = $username;
                    $webuser->set_password($password_current);
                }
            }
        }

        $email = core_purify_string($_POST['wu_cusacc_settings_email']);
        if(empty($email))
        {
            $blank_fields = t(44, "E-Mail");
        }
        else
        {
            // validate correctness of email address
            if( !filter_var($email, FILTER_VALIDATE_EMAIL) )
            {
                $errors[] = t(768, "Invalid e-mail address").': '.$email;
            }
            else if($webuser->email != $email)
            {
                // check if email address is not already in use by another account
                if(!$webuser::email_available($email, $website->id))
                {
                    $errors[] = t(771, "The specified username (or email address) is already being used on our website");
                }
                else
                {
                    // validate email by sending a confirmation to the new address
                    $webuser->email = $email;
                    $webuser->email_verification_date = 0;
                    $webuser->activation_key = bin2hex(openssl_random_pseudo_bytes( 32 ));

                    // try saving changes before sending email
                    $webuser->save();

                    // send a message to verify the new user's email
                    $email_confirmation_link = $website->absolute_path().'/nv.webuser/confirm?email='.$webuser->email.'&hash='.$webuser->activation_key;
                    $message = navigate_compose_email(array(
                        array(
                            'title' => $website->name,
                            'content' => $webgets['webuser']['translations']['click_to_confirm_account'].
                                        '<br />'.
                                        '<a href="'.$email_confirmation_link.'">'.$email_confirmation_link.'</a>'
                        ),
                        array(
                            'footer' =>
                                $webgets['webuser']['translations']['account_confirmation_notice'].
                                '<br />'.
                                '<a href="'.$website->absolute_path().$website->homepage().'">'.$website->name.'</a>'
                        )
                    ));

                    nvweb_send_email($website->name, $message, $webuser->email);

                    $messages[] = $webgets['webuser']['translations']['email_confirmation'];
                }
            }
        }

        $password_new = trim($_POST['wu_cusacc_settings_password_new']);
        $password_confirmation = trim($_POST['wu_cusacc_settings_password_confirmation']);
        if(!empty($password_new))
        {
            if(nvweb_webuser_password_strength($password_new, $webuser->username, $webuser->email) < 50)
            {
                $errors[] = t(769, "Password too weak");
            }
            else if($password_new != $password_confirmation)
            {
                $errors[] = t(834, "The password confirmation does not match");
            }
            else
            {
                $webuser->set_password($password_new);
            }
        }


        if(!empty($blank_fields))
        {
            $errors[] = t(444, "You left some required fields blank.").' ['.implode(",", $blank_fields).']';
        }

        if(empty($errors))
        {
            // everything ok, save and display confirmation
            $webuser->save();

            $messages = array_map(function($v) { return '<p>'.$v.'</p>'; }, $messages);
            array_unshift($messages, @$icons['success'].t(53, "Data successfully saved"));
            $messages = implode("\n", $messages);

            $out[] = '<div class="nv_webuser_customer_account-info_message" style="display: block;">                                   
                          '.nl2br($messages).'
                      </div>';
        }
    }

    if(!empty($errors))
    {
        $errors = array_map(function($v) { return '<span>'.$v.'</span><br />'; }, $errors);
        array_unshift($errors, '<div class="nv_cart_errors_title">'.t(740, "Error").'</div>');
        $errors = '<div class="nv_cart_errors">'.implode("\n", $errors).'</div>';
    }
    else
    {
        $errors = "";
    }

    $out[] = '<div class="nv_cart_address_form">';
    $out[] = $errors;
    $out[] = '    <form action="?s=settings" method="post">';
    $out[] = '      <h4>'.@$icons['settings'].t(828, "Account").'</h4>
                    <div class="nv_cart-flex-sb">                
                        <div>
                            <div>
                                <label>'.t(1, "User").'</label>
                                <input type="text" name="wu_cusacc_settings_username" required value="'.core_special_chars($webuser->username).'" />
                            </div>                        
                        </div>                        
                        <div>
                            <div>
                                <label>'.t(44, "E-Mail").'</label>
                                <input type="text" name="wu_cusacc_settings_email" required value="'.$webuser->email.'" />
                            </div>                        
                        </div>
                    </div>
                    <div>
                        <div>
                            <label>'.t(752, "Full name").'</label>
                            <input type="text" name="wu_cusacc_settings_fullname" required value="'.core_special_chars($webuser->fullname).'" />
                        </div>                        
                    </div>
                    <div>
                        <label>'.t(830, "Current password").'</label>
                        <input type="password" autocomplete="new-password" name="wu_cusacc_settings_password_current" value="" />
                    </div>
                    
                    <br />
                    <h4>'.@$icons['password'].t(829, "Password change").'</h4>
                    <div>
                        <div>
                            <label>'.t(831, "New password").'</label>
                            <input type="password" autocomplete="off" name="wu_cusacc_settings_password_new" value="" />
                            <div class="nv_webuser_customer_account-form_row_helper">'.t(48, "Leave empty to keep current value").'</div>
                        </div>
                        <div>
                            <label>'.t(832, "Confirm new password").'</label>
                            <input type="password" autocomplete="off" name="wu_cusacc_settings_password_confirmation" value="" />
                        </div>
                    </div>
                              
                    <br />
                    <div>
                        <input type="submit" class="button nv_cart_button_continue" value="'.t(34, "Save").'" />
                    </div>';

    $out[] = '    </form>';
    $out[] = '</div>';

    $out = implode("\n", $out);

    nvweb_after_body('js', '

    ');

    nvweb_after_body(
        'html',
        '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/nv_cart.css" />'
    );

    return $out;
}

?>