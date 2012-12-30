<?php	
function nvweb_votes($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $webuser;
	
	switch($vars['mode'])
	{
		case 'score':
            $out = nvweb_votes_calc($current['object'], $vars['round'], $vars['half'], $vars['min'], $vars['max']);
			break;
			
		case 'votes':
			$out = $current['object']->votes;
			break;
			
		case 'webuser':
			$score = NULL;
			if(!empty($webuser->id))	
				$score = $DB->query_single('value', 'nv_webuser_votes', ' website = '.protect($website->id).'
																	  AND webuser = '.protect($webuser->id).'
																	  AND object = '.protect($current['type']).'
																	  AND object_id = '.protect($current['id']));
			$out = (empty($score)? '0' : $score);
			break;
	}
	
	return $out;
}

function nvweb_votes_calc($object, $round="", $half=false, $min="", $max="")
{
    $score = 0;

    if($object->votes > 0)
        $score = $object->score / $object->votes;

    if($half=='true')
        $score = $score / 2;

    switch($round)
    {
        case 'floor':
            $out = floor($score);
            break;

        case 'ceil':
            $out = ceil($score);
            break;

        default:
            if(is_integer($round))
                $out = round($score, $round);
            else
                $out = $score;
    }

    if(!empty($max) && $out > $max)
        $out = $max;

    if(!empty($min) && $out < $min)
        $out = $min;

    return $out;
}

?>