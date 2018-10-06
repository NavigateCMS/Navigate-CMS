<?php
function nvweb_languages($vars=array())
{
	global $website;
    global $current;

	$out = array();

    $class = value_or_default($vars['class'], "");
		
	switch($vars['mode'])
	{
		case 'code':
			foreach($website->languages_published as $lang)
			{
                if($current['lang']==$lang)
                {
                    $out[] = '<a href="?lang='.$lang.'" class="language-selected '.$class.' active">'.$lang.'</a>';
                }
                else
                {
                    $out[] = '<a href="?lang='.$lang.'" class="'.$class.'">'.$lang.'</a>';
                }
			}
            $out = implode('<span class="nv-language-separator">'.$vars['separator'].'</span>', $out);
			break;
			
		case 'name':
			foreach($website->languages_published as $lang)
			{
                $lang_name = language::name_by_code($lang);
                if($current['lang']==$lang)
                {
                    $out[] = '<a href="?lang='.$lang.'" class="language-selected '.$class.' active">'.$lang_name.'</a>';
                }
                else
                {
                    $out[] = '<a href="?lang='.$lang.'" class="'.$class.'">'.$lang_name.'</a>';
                }
			}
            $out = implode('<span class="nv-language-separator">'.$vars['separator'].'</span>', $out);
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
                {
                    $out[] = '<a href="?lang='.$lang.'" class="language-selected '.$class.' active"><img src="'.NVWEB_ABSOLUTE.'/object?type=flag&code='.$flag.'" />'.$extra.'</a>';
                }
                else
                {
                    $out[] = '<a href="?lang='.$lang.'" class="'.$class.'"><img src="'.NVWEB_ABSOLUTE.'/object?type=flag&code='.$flag.'" alt="'.$flag.'" />'.$extra.'</a>';
                }
			}
            $out = implode('<span class="nv-language-separator">'.$vars['separator'].'</span>', $out);
			break;

        case 'li':
            foreach($website->languages_published as $lang)
            {
                if(empty($lang))
                    continue;
                $lang_name = language::name_by_code($lang);
                if($current['lang']==$lang)
                {
                    $out[] = '<li><a href="?lang='.$lang.'" class="language-selected '.$class.' active">'.$lang_name.'</a></li>';
                }
                else
                {
                    $out[] = '<li><a href="?lang='.$lang.'" class="'.$class.'">'.$lang_name.'</a></li>';
                }
            }
            $out = implode('<span class="nv-language-separator">'.$vars['separator'].'</span>', $out);
            break;

        case 'select':
        default:
            $out[] = '<select class="'.$class.'" onchange="if(this.value!=\''.$current['lang'].'\') window.location.href = \'?lang=\'+this.value;">';
            foreach($website->languages_published as $lang)
            {
                if(empty($lang))
                {
                    continue;
                }
                $lang_name = language::name_by_code($lang);
                if($current['lang']==$lang)
                {
                    $out[] = '<option value="'.$lang.'" selected="selected">'.$lang_name.'</option>';
                }
                else
                {
                    $out[] = '<option value="'.$lang.'">'.$lang_name.'</option>';
                }
            }
            $out[] = '</select>';
            $out = implode("\n", $out);
            break;
    }
		
	return $out;
}
?>