<?php
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
            'forgot_password_success' => t(648, "An e-mail with a temporary password has been sent to your e-mail account."),
            'forgot_password_error' => t(446, "We're sorry. Your contact request could not be sent. Please try again or find another way to contact us.")
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
            "forgot_password_success": "An e-mail with a temporary password has been sent to your e-mail account.",
            "forgot_password_error": "We're sorry. Your contact request could not be sent. Please try again or find another way to contact us."
        */
        if(!empty($website->theme) && method_exists($theme, 't'))
        {
            foreach($webgets[$webget]['translations'] as $code => $text)
            {
                $theme_translation = $theme->t($code);
                if(!empty($theme_translation) && $code!=$theme_translation)
                    $webgets[$webget]['translations'][$code] = $theme_translation;
            }
        }
    }


	$out = '';

    switch($vars['mode'])
    {
        case 'id':
            if(!empty($webuser->id))
                $out = $webuser->id;
            break;

        case 'username':
            if(!empty($webuser->username))
                $out = $webuser->username;
            break;

        case 'fullname':
            if(!empty($webuser->fullname))
                $out = $webuser->fullname;
            break;

        case 'gender':
            if(!empty($webuser->gender))
                $out = $webuser->gender;
            break;

        case 'newsletter':
            $out = $webuser->newsletter;
            break;

        case 'email':
            if(!empty($webuser->email))
                $out = $webuser->email;
            break;

        case 'authenticate':
            $webuser_website = $vars['website'];
            if(empty($webuser_website))
                $webuser_website = $website->id;

            $signin_username = $_REQUEST[(empty($vars['username_field'])? 'signin_username' : $vars['username_field'])];
            $signin_password = $_REQUEST[(empty($vars['password_field'])? 'signin_password' : $vars['password_field'])];

            // a page may have several forms, which one do we have to check?
            if(!empty($vars['form']))
            {
                list($field_name, $field_value) = explode('=', $vars['form']);
                if($_POST[$field_name]!=$field_value)
                    return;
            }

            // ignore empty (or partial empty) forms
            if(!empty($signin_username) && !empty($signin_password))
            {
                $signed_in = $webuser->authenticate($webuser_website, $signin_username, $signin_password);

                if(!$signed_in)
                {
                    $message = $webgets[$webget]['translations']['login_incorrect'];

                    if(empty($vars['notify']))
                        $vars['notify'] = 'inline';

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
                            nvweb_after_body('js', $vars['callback'].'(true);');
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
                    return;
            }

            // check if this send request really comes from the website and not from a spambot
            if( parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->subdomain.'.'.$website->domain &&
                parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->domain )
                return;

            if(empty($vars['email_field']))
                $vars['email_field'] = 'newsletter_email';

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
                        ' email = '.protect($email).'
                          AND website = '.$website->id
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
                    $message = $webgets[$webget]['translations']['forgot_password_success'];
                else
                    $message = $webgets[$webget]['translations']['forgot_password_error'];

                if(empty($vars['notify']))
                    $vars['notify'] = 'inline';

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

        case 'signup':
            // TODO
            // pre checks: correct form, not spambot, email not empty and valid
            // get the profile data from the form
            // more checks: password strenght & confirmation, etc.
            // save the new webuser account
            // prepare account confirmation (unless not required by webget attributes)
            //      leave the account blocked
            //      generate an activation key
            //      send confirmation email
            // if no account confirmation is required, auto login

            break;

        case 'avatar':
            // TODO return webuser's avatar image
            break;

        case 'newsletter_subscribe':
            // a page may have several forms, which one do we have to check?
            if(!empty($vars['form']))
            {
                list($field_name, $field_value) = explode('=', $vars['form']);
                if($_POST[$field_name]!=$field_value)
                    return;
            }

            // check if this send request really comes from the website and not from a spambot
            if( parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->subdomain.'.'.$website->domain &&
                parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->domain )
                return;


            if(empty($vars['email_field']))
                $vars['email_field'] = 'newsletter_email';

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
                        ' email = '.protect($email).'
                          AND website = '.$website->id
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
                            $wu->newsletter = 1;
                            $ok = $wu->save();
                        }
                    }

                    if(empty($wu_id) || ($wu->access==1 && !empty($wu->activation_key)))
                    {
                        // create a new webuser account with that email
                        $username = strtolower(substr($email, 0, strpos($email, '@'))); // left part of the email

                        if(!empty($username) && !in_array($username, array('info', 'admin', 'contact', 'demo', 'test')))
                        {
                            // check if the proposed username already exists,
                            // in that case use the full email as username
                            // ** if the email already exists, the subscribe process only updates the newsletter setting!
                            $wu_id = $DB->query_single(
                                'id',
                                'nv_webusers',
                                ' username = '.protect($username).'
                              AND website = '.$website->id
                            );
                        }

                        if(!empty($wu_id))
                        {
                            // oops, user already exists... try another username -- the full email address
                            $wu_id = $DB->query_single(
                                'id',
                                'nv_webusers',
                                ' username = '.protect($email).'
                                    AND website = '.$website->id
                            );

                            if(empty($wu_id))
                            {
                                // ok, email is a new username
                                $username = $email;
                            }
                            else
                            {
                                // nope, email is already used (this code should never execute **)
                                $username = uniqid($username.'-');
                            }
                        }
                        else
                        {
                            // new sign up
                            $wu->id = 0;
                            $wu->website = $website->id;
                            $wu->email = $email;
                            $wu->newsletter = 1;
                            $wu->language = $current['lang']; // infer the webuser language by the active website language
                            $wu->username = $username;
                            $wu->access = 1;    // user is blocked until the server recieves an email confirmation
                        }

                        $wu->activation_key = md5($wu->email . rand(1, 9999999));
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

                        @nvweb_send_email($website->name, $message, $wu->email);
                        $pending_confirmation = true;
                    }
                }

                $message = $webgets[$webget]['translations']['subscribe_error'];
                if($pending_confirmation)
                    $message = $webgets[$webget]['translations']['email_confirmation'];
                else if($ok)
                    $message = $webgets[$webget]['translations']['subscribed_ok'];

                if(empty($vars['notify']))
                    $vars['notify'] = 'inline';

                switch($vars['notify'])
                {
                    case 'alert':
                        nvweb_after_body('js', 'alert("'.$message.'");');
                        break;

                    case 'inline':
                        if($ok)
                            $out = '<div class="nvweb-newsletter-form-success">'.$message.'</div>';
                        else
                            $out = '<div class="nvweb-newsletter-form-error">'.$message.'</div>';
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
    }

    return $out;
}

?>