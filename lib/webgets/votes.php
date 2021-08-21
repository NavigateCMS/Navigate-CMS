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

        case 'percentage':
            $out = nvweb_votes_calc($current['object'], false, false);
            // returns score from 0 to 10, with decimals
            $out = $out * 10;
            $out = round($out, 2, PHP_ROUND_HALF_UP);

            if(!empty($vars['max']) && $out > $vars['max'])
            {
                $out = $vars['max'];
            }

            if(!empty($vars['min']) && $out < $vars['min'])
            {
                $out = $vars['min'];
            }

            $out = number_format($out, 2, '.', '');
            break;
			
		case 'votes':
			$out = $current['object']->votes;
			break;
			
		case 'webuser':
			$score = NULL;
			if(!empty($webuser->id))
            {
				$score = $DB->query_single(
                    'value',
                    'nv_webuser_votes',
                    ' website = :wid
					  AND webuser = :webuser_id
					  AND object = :type
					  AND object_id = :object_id',
                    null,
                    array(
                        ':wid' => $website->id,
                        ':webuser_id' => $webuser->id,
                        ':type' => $current['type'],
                        ':object_id' => $current['id']
                    )
                );
            }
			$out = (empty($score)? '0' : $score);
			break;
	}
	
	return $out;
}

function nvweb_votes_calc($object, $round="", $half=false, $min="", $max="")
{
    $score = 0;

    if($object->votes > 0)
    {
        $score = $object->score / $object->votes;
    }

    if($half=='true')
    {
        $score = $score / 2;
    }

    switch($round)
    {
        case 'floor':
            $out = floor($score);
            break;

        case 'ceil':
            $out = ceil($score);
            break;

        case false:
            $out = $score;
            break;

        default:
            if(is_numeric($round))
            {
                $out = round($score, $round);
            }
            else
            {
                $out = $score;
            }
    }

    if(!empty($max) && $out > $max)
    {
        $out = $max;
    }

    if(!empty($min) && $out < $min)
    {
        $out = $min;
    }

    return $out;
}

?>