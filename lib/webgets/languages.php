<?php
function nvweb_languages($vars=array())
{
	global $website;
	global $DB;
    global $current;

	$out = array();
		
	switch($vars['mode'])
	{
		case 'code':
			foreach($website->languages_published as $lang)
			{
                if($current['lang']==$lang)
                    $out[] = '<a href="?lang='.$lang.'" class="language-selected">'.$lang.'</a>';
                else
				    $out[] = '<a href="?lang='.$lang.'">'.$lang.'</a>';
			}
            $out = implode($vars['separator'], $out);
			break;
			
		case 'name':
			foreach($website->languages_published as $lang)
			{
                $lang_name = language::name_by_code($lang);
                if($current['lang']==$lang)
                    $out[] = '<a href="?lang='.$lang.'" class="language-selected">'.$lang_name.'</a>';
                else
                    $out[] = '<a href="?lang='.$lang.'">'.$lang_name.'</a>';
			}
            $out = implode($vars['separator'], $out);
			break;

		case 'flag':
			foreach($website->languages_published as $lang)
			{
                $flag = $lang;
                if(strpos($lang, '_') > 0)
                {
                    $code = explode('_', $lang);
                    $flag = $code[0];
                    $extra = ' '.$code[1];
                }

                if($current['lang']==$lang)
                    $out[] = '<a href="?lang='.$lang.'" class="language-selected"><img src="'.NVWEB_ABSOLUTE.'/object?type=flag&code='.$flag.'" />'.$extra.'</a>';
                else
                    $out[] = '<a href="?lang='.$lang.'"><img src="'.NVWEB_ABSOLUTE.'/object?type=flag&code='.$flag.'" />'.$extra.'</a>';
			}
            $out = implode($vars['separator'], $out);
			break;

        case 'select':
        default:
            $out[] = '<select onchange="if(this.value!=\''.$current['lang'].'\') window.location.href = \'?lang=\'+this.value;">';
            foreach($website->languages_published as $lang)
            {
                if(empty($lang))
                    continue;
                $lang_name = language::name_by_code($lang);
                if($current['lang']==$lang)
                    $out[] = '<option value="'.$lang.'" selected="selected">'.$lang_name.'</option>';
                else
                    $out[] = '<option value="'.$lang.'">'.$lang_name.'</option>';
            }
            $out[] = '</select>';
            $out = implode($vars['separator'], $out);
            break;
    }
		
	return $out;
}
?>