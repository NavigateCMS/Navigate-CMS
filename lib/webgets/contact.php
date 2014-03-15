<?php
function nvweb_contact($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $webgets;
	global $dictionary;
	global $webuser;
	global $theme;
	
	$webget = 'contact';

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
				'name' => t(159, 'Name'),
				'email' => t(44, 'E-Mail'),
				'message' => t(380, 'Message'),
                'fields_blank' => t(444, 'You left some required fields blank.'),
                'contact_request_sent' => t(445, 'Your contact request has been sent. We will contact you shortly.'),
                'contact_request_failed' => t(446, 'We\'re sorry. Your contact request could not be sent. Please try again or find another way to contact us.')
		);

		// theme translations 
		// if the web theme has custom translations for this string subtypes, use it (for the user selected language)
		/* just add the following translations to your json theme dictionary:

			"name": "Name",
			"email": "E-Mail",
			"message": "Message",
		    "fields_blank": "You left some required fields blank.",
		    "contact_request_sent": "Your contact request has been sent. We will contact you shortly.",
		    "contact_request_failed": "We're sorry. Your contact request could not be sent. Please try again or find another way to contact us."

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

	if(empty($vars['notify']))
        $vars['notify'] = 'alert';

	$out = '';

	switch(@$vars['mode'])
	{	
		case 'send':
            if(!empty($_POST))  // form sent
            {
                // a page may have several forms, which one do we have to check?
                if(!empty($vars['form']))
                {
                    list($field_name, $field_value) = explode('=', $vars['form']);
                    if($_POST[$field_name]!=$field_value)
                        return;
                }

                // check if this send request really comes from the website and not from a spambot
                if(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $website->subdomain.'.'.$website->domain)
                    return;

                // prepare fields and labels
                $fields = explode(',', @$vars['fields']);
                $labels = explode(',', @$vars['labels']);
                if(empty($labels))
                    $labels = $fields;

                $labels = array_map(
                    function($key)
                    {
                        global $webgets;
                        global $theme;

                        $tmp = $theme->t($key);
                        if(!empty($tmp))
                            return $theme->t($key);
                        else
                            return $webgets['contact']['translations'][$key];
                    },
                    $labels
                );
                $fields = array_combine($fields, $labels);

                // $fields = array( 'field_name' => 'field_label', ... )

                // check required fields
                $errors = array();
                $required = array();

                if(!empty($vars['required']))
                    $required = explode(',', $vars['required']);

                if(!empty($required))
                {
                    foreach($required as $field)
                    {
                        $value = trim($_POST[$field]);
                        if(empty($value))
                            $errors[] = $fields[$field];
                    }

                    if(!empty($errors))
                        return nvweb_contact_notify($vars, true, $webgets[$webget]['translations']['fields_blank'].' ('.implode(", ", $errors).')');
                }

                // create e-mail message and send it
                $message = nvweb_contact_generate($fields);

                $subject = $vars['subject'];
                if(!empty($subject))
                    $subject = ' | '.$subject;
                $subject = $website->name.$subject;

                $sent = nvweb_send_email($subject, $message, $website->contact_emails);

                if($sent)
                    $out = nvweb_contact_notify($vars, false, $webgets[$webget]['translations']['contact_request_sent']);
                else
                    $out = nvweb_contact_notify($vars, true, $webgets[$webget]['translations']['contact_request_failed']);
            }

    }
	
	return $out;
}

function nvweb_contact_notify($vars, $is_error, $message)
{
    $out = '';

    switch($vars['notify'])
    {
        case 'inline':
            if($is_error)
                $out = '<div class="nvweb-contact-form-error">'.$message.'</div>';
            else
                $out = '<div class="nvweb-contact-form-success">'.$message.'</div>';
            break;

        case 'alert':
            nvweb_after_body('js', 'alert("'.$message.'");');
            break;

        default:
            // if empty, default is alert
            if(empty($vars['notify']))
            {
                nvweb_after_body('js', 'alert("'.$message.'");');
            }
            else
            {
                // if not empty, it's a javascript function call
                if($is_error && !empty($vars['error_callback']))
                    nvweb_after_body('js', $vars['error_callback'].'("'.$message.'");');
                else
                    nvweb_after_body('js', $vars['notify'].'("'.$message.'");');
            }
            break;
    }

    return $out;
}

function nvweb_contact_generate($fields)
{
    $out = array();

    $out[] = '<div style=" background: #E5F1FF; width: 600px; border-radius: 6px; margin: 10px auto; padding: 1px 20px 20px 20px;">';

    if(is_array($fields))
    {
        foreach($fields as $field => $label)
        {
           if(substr($field, -2, 2)=='[]')
               $field = substr($field, 0, -2);

            if(is_array($_REQUEST[$field]))
            {
                $value = print_r($_REQUEST[$field], true);
                $value = str_replace("Array\n", '', $value);
                $value = nl2br($value);
            }
            else
                $value = nl2br($_REQUEST[$field]);

            $out[] = '<div style="margin: 25px 0px 10px 0px;">';
            $out[] = '    <div style="color: #595959; font-size: 17px; font-weight: bold; font-family: Verdana;">'.$label.'</div>';
            $out[] = '</div>';
            $out[] = '<div style=" background: #fff; border-radius: 6px; padding: 10px; margin-top: 5px; line-height: 25px; text-align: justify; ">';
            $out[] = '    <div class="text" style="color: #595959; font-size: 16px; font-style: italic; font-family: Verdana;">'.$value.'</div>';
            $out[] = '</div>';
        }
    }
    else
        $out[] = $fields;

    $out[] = '</div>';

    return implode("\n", $out);
}
?>