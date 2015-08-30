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
            'subscribe_error' => t(542, 'There was a problem subscribing your email to the newsletter.')
        );

        // theme translations
        // if the web theme has custom translations for this string subtypes, use it (for the user selected language)
        /* just add the following translations to your json theme dictionary:
            "login_incorrect": "Login incorrect."
            "subscribed_ok": "Your email has been successfully subscribed to the newsletter."
            "subscribe_error": "There was a problem subscribing your email to the newsletter."
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
            break;

        case 'signout_link':
            $out = NVWEB_ABSOLUTE.$website->homepage().'?webuser_signout';
            break;

        case 'newsletter_subscribe':
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

                    if(!empty($wu_id))
                    {
                        $wu = new webuser();
                        $wu->load($wu_id);
                        $wu->newsletter = 1;
                        $ok = $wu->save();
                    }
                    else
                    {
                        // create a new webuser account with that email
                        $username = substr($email, 0, strpos($email, '@')); // left part of the email
                        // if proposed username already exists,
                        // use the email as username
                        // ** if the email already exists, the subscribe process only updates the newsletter setting!
                        $wu_id = $DB->query_single(
                            'id',
                            'nv_webusers',
                            ' username = '.protect($username).'
                          AND website = '.$website->id
                        );

                        if(!empty($wu_id))
                        {
                            // oops, user already exists... try another username -- the email
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
                                // nope, email is already used (this code should never execute**)
                                $username = uniqid($username.'-');
                            }
                        }

                        $wu = new webuser();
                        $wu->id = 0;
                        $wu->website = $website->id;
                        $wu->email = $email;
                        $wu->newsletter = 1;
                        $wu->language = $current['lang']; // infer the webuser language by the active website language
                        $wu->username = $username;
                        $ok = $wu->save();
                    }
                }

                $message = $webgets[$webget]['translations']['subscribe_error'];
                if($ok)
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