<?php	
function nvweb_tags($vars=array())
{
	global $website;
	global $DB;
	global $current;

    $out = '';

	switch($vars['mode'])
	{
		case 'top':
            $tags = nvweb_tags_retrieve($vars['items']);
            $out = array();

            $extra = '';
            if(!empty($vars['class']))
                $extra = ' class="'.$vars['class'].'" ';

            foreach($tags as $tag => $times)
            {
                if($vars['tag']=='li')
                    $out[] = '<li><a href="'.NVWEB_ABSOLUTE.'/nvtags?q='.$tag.'" count="'.$times.'" '.$extra.'>'.$tag.'</a></li>';
                else if($vars['tag']=='span')
                    $out[] = '<span count="'.$times.'" '.$extra.'>'.$tag.'</span>'.$vars['separator'];
                else
                    $out[] = '<a href="'.NVWEB_ABSOLUTE.'/nvtags?q='.$tag.'" count="'.$times.'" '.$extra.'>'.$tag.'</a>'.$vars['separator'];
            }
            $out = implode("\n", $out);
			break;
	}
	
	return $out;
}

function nvweb_tags_retrieve($maxtags="")
{
    // TODO: implement a tags cache system to improve website render time

    global $website;
    global $DB;
    global $current;

    $tags = array();

    $DB->query(
        'SELECT text FROM nv_webdictionary
          WHERE website = '.$website->id.'
            AND subtype = "tags"
            AND lang = "'.$current['lang'].'"
        ',
        'array'
    );

    $rows = $DB->result();

    if(!empty($rows))
    {
        foreach($rows as $row)
        {
            $row_tags = explode(',', $row['text']);
            foreach($row_tags as $row_tag)
            {
                if(isset($tags[$row_tag]))
                    $tags[$row_tag]++;
                else
                    $tags[$row_tag] = 1;
            }
        }
    }

    arsort($tags);

    if(!empty($maxtags))
        $tags = array_slice($tags, 0, $maxtags, true);

    return $tags;
}

?>