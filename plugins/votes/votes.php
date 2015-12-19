<?php
function nvweb_votes_plugin($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $template;

	$out = '';

	switch($vars['mode'])
	{
			
		default:
	}

	return $out;
}

function nvweb_votes_event($event, $html)
{
	global $webuser;
	global $website;
	global $current;
	
	$code = '';
	$js = '';

	if($event == 'before_parse')
	{	
		if($_REQUEST['plugin']=='nv_votes')
		{
			if(empty($webuser->id))
			{
				echo json_encode(array('error' => 'no_webuser'));	
			}
			else
			{
				$status = webuser_vote::update_object_votes(
					$webuser->id,
					$_POST['object'],
					$_POST['object_id'],
					$_POST['score'],
					true
				);

				if($status==='already_voted')
					echo json_encode(array('error' => 'already_voted'));
				else if($status===true)
					echo json_encode(array('ok' => 'true'));	
				else if(!$status)
					echo json_encode(array('error' => 'error'));
			}
			nvweb_clean_exit();
		}
		else
		{
			// add jquery from CDN if not already loaded
			if(strpos($html, 'jquery')===false)	
				$code = '<script language="javascript" type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>'."\n";
			
			$js = '
				function nvweb_votes_plugin_vote(value, callback)
				{
					jQuery.ajax({
						type: "POST",
						url: "'.$website->absolute_path().'/'.$current['route'].'?plugin=nv_votes",
						data: {
							score: value,
							object: "'.$current['type'].'",
							object_id: "'.$current['id'].'"
						},
						success: function(data)
						{
							if(callback)
								callback(data);
						},
						dataType: "json"
					});	
				}
			';
			
			nvweb_after_body("html", $code);
			nvweb_after_body("js", $js);				
		}
	}
	
	return $html;
}
?>