<?php
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
function run()
{
	global $user;	
	global $layout;
	$out = '';
		
	switch($_REQUEST['act'])
	{
		case 2: // form
		case 0:	// default
		
			if(isset($_REQUEST['form-sent']))
			{
				// update user	
				$user->language = $_REQUEST['user-language'];
				$user->email = $_REQUEST['user-email'];
				$user->decimal_separator = $_REQUEST['user-decimal_separator'];
				$user->timezone = $_REQUEST['user-timezone'];
				$user->date_format = $_REQUEST['user-date_format'];
				if(!empty($_REQUEST['user-password']))
					$user->set_password($_REQUEST['user-password']);
				$user->update();
				$layout->navigate_notification(t(53, "Data saved successfully."), false);	
			}
		
			$out = settings_form();
			break;	
	}
	
	return $out;
}

function settings_form()
{
	global $user;
	global $DB;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	$navibars->title(t(14, 'Settings'));
	
	$navibars->add_actions('<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>');
	
	$navibars->form();
	
	$navibars->add_tab(t(43, "General"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.$user->id.'</span>' ));
											
	$navibars->add_tab_content_row(array(	'<label>'.t(1, 'User').'</label>',
											'<span>'.$user->username.'</span>' ));
											
	$navibars->add_tab_content_row(array(	'<label>'.t(2, 'Password').'</label>',
											'<input type="password" name="user-password" value="" size="32" />',
											'<span class="navigate-form-row-info">'.t(48, "Leave blank to keep the current value").'</span>' ));
											
	$navibars->add_tab_content_row(array(	'<label>'.t(44, 'E-Mail').'</label>',
                                            $naviforms->textfield('user-email', $user->email)));


	if($user->profile == 1 && false) // Administrator (shown as example, never enabled here) 
	{											
		// Profile selector
		$DB->query('SELECT id, name FROM nv_profiles');		
		$data = $DB->result();	
		$select = $naviforms->select_from_object_array('user-profile', $data, 'id', 'name', $user->profile);
		$navibars->add_tab_content_row(array(	'<label>'.t(45, 'Profile').'</label>',
												$select ));
	}
	else
	{
		$user_profile_name = $DB->query_single('name', 'nv_profiles', ' id = '.intval($user->profile));
		$navibars->add_tab_content_row(array(	'<label>'.t(45, 'Profile').'</label>',
												'<span>'.$user_profile_name.'</span>' ));		
	}

	// Language selector
	$DB->query('SELECT code, name FROM nv_languages WHERE nv_dictionary != ""');		
	$data = $DB->result();	
	$select = $naviforms->select_from_object_array('user-language', $data, 'code', 'name', $user->language);
	$navibars->add_tab_content_row(array(	'<label>'.t(46, 'Language').'</label>',
											$select ));

	$timezones = property::timezones();
	
	if(empty($user->timezone))
		$user->timezone = date_default_timezone_get();

	$navibars->add_tab_content_row(array(	'<label>'.t(97, 'Timezone').'</label>',
											$naviforms->selectfield("user-timezone", array_keys($timezones), array_values($timezones), $user->timezone)
										));
											
	// Decimal separator		
	$data = array(	0	=> json_decode('{"code": ",", "name": ", ---> 1234,25"}'),
					1	=> json_decode('{"code": ".", "name": ". ---> 1234.25"}'),
					2	=> json_decode('{"code": "\'", "name": "\' ---> 1234\'25"}'),
				);
				
	$select = $naviforms->select_from_object_array('user-decimal_separator', $data, 'code', 'name', $user->decimal_separator);
	$navibars->add_tab_content_row(array(	'<label>'.t(49, 'Decimal separator').'</label>',
											$select ));
											
	// Date format
	$data = array(	0	=> json_decode('{"code": "Y-m-d H:i", "name": "'.date(Y).'-12-31 23:59"}'),
					1	=> json_decode('{"code": "d-m-Y H:i", "name": "31-12-'.date(Y).' 23:59"}'),
					2	=> json_decode('{"code": "m-d-Y H:i", "name": "12-31-'.date(Y).' 23:59"}'),
					3	=> json_decode('{"code": "Y/m/d H:i", "name": "'.date(Y).'/12/31 23:59"}'),
					4	=> json_decode('{"code": "d/m/Y H:i", "name": "31/12/'.date(Y).' 23:59"}'),
					5	=> json_decode('{"code": "m/d/Y H:i", "name": "12/31/'.date(Y).' 23:59"}')
				);	

	$select = $naviforms->select_from_object_array('user-date_format', $data, 'code', 'name', $user->date_format);
	$navibars->add_tab_content_row(array(	'<label>'.t(50, 'Date format').'</label>',
											$select ));																						
	
	
	return $navibars->generate();
}
?>