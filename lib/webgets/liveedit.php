<?php
require_once(NAVIGATE_PATH.'/lib/packages/comments/comment.class.php');

function nvweb_liveedit($vars=array())
{
	global $website;
	global $current;
	global $DB;
    global $lang;
    global $theme;
    global $session;

	$out = array();
	$url = '';

	if(!empty($_SESSION['APP_USER#'.APP_UNIQUE]))
	{
		switch($current['type'])
		{			
			case 'item':
				$url = NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=10&act=2&id='.$current['object']->id.'&tab=2&tab_language='.$current['lang'].'&quickedit=true&wid='.$website->id;
				break;
				
			case 'structure':
				// load the first item
				$DB->query('	SELECT id 
								  FROM nv_items
								 WHERE category = '.protect($current['category']).'
								   AND permission < 2
								   AND website = '.$website->id.'
						   ');
				
				$rs = $DB->first();				
			
				$url = NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=10&act=2&id='.$rs->id.'&tab=2&quickedit=true&wid='.$website->id;
				break;	

			default:
					
		}

        if(empty($lang))
        {
            $lang = new language();
            $lang->load($current['lang']);
        }

        // add jQuery if has not already been loaded in the template
        $includes = array();
        if(strpos($vars['nvweb_html'], 'jquery')===false)
            $includes[] = '<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>';

        $includes[] = '<script language="javascript" type="text/javascript" src="'.NAVIGATE_URL.'/js/navigate_liveedit.js"></script>';
        $includes[] = '<link rel="stylesheet" type="text/css" href="'.NAVIGATE_URL.'/css/tools/navigate_liveedit.css" />';

        nvweb_after_body('html', implode("\n", $includes)."\n");

        $comments = comment::pending_count();

        // TODO: check user permissions before allowing "Create", "Edit" and other functions

        $out[] = '<div id="navigate_liveedit_bar" style="display: none;">';
        $out[] = '  <a href="http://www.navigatecms.com" target="_blank"><img src="'.NAVIGATE_URL.'/img/navigatecms/navigatecms_logo_52x24_white.png" width="52" height="24" /></a>';
        $out[] = '  <a href="'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=items&act=create" target="_blank"><img src="'.NAVIGATE_URL.'/img/icons/silk/page_add.png" /> '.t(38, 'Create').'</a>';
        $out[] = '  <a href="'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=comments" target="_blank"><img src="'.NAVIGATE_URL.'/img/icons/silk/comments.png" /> '.$comments.'</a>';
        //$out[] = '  <div id="navigate_liveedit_bar_liveedit_button"><img src="'.NAVIGATE_URL.'/img/icons/silk/shape_square_select.png" /> '.t(458, 'Edit in place').'</div>';

        if(!empty($url))
            $out []= '<a style="float: right;" href="'.$url.'" target="_blank">
                        <img src="'.NAVIGATE_URL.'/img/icons/silk/application_double.png" />
                        '.t(456, 'Edit in Navigate CMS').'
                      </a>';

        $out[] = '  <div id="navigate_liveedit_bar_information_button" style=" float: right; "><img src="'.NAVIGATE_URL.'/img/icons/silk/information.png" /> '.t(457, 'Information').'</div>';

        $page_type = array(
            'item' => t(180, 'Item'),
            'structure' => t(16, 'Structure')
        );
        $page_type = $page_type[$current['type']];


        $out[] = '  <div id="navigate_liveedit_bar_information">';
        $out[] = '      <span>'.t(368, 'Theme').' <strong>'.$theme->title.'</strong></span>';
        $out[] = '      <span>'.t(79, 'Template').' <strong>'.$theme->template_title($current['template'], false).'</strong></span>';
        $out[] = '      <span>'.t(160, 'Type').' <strong>'.$page_type.'</strong></span>';
        $out[] = '      <span>ID <strong>'.$current['id'].'</strong></span>';
        $out[] = '      <span>'.t(46, 'Language').' <strong>'.language::name_by_code($session['lang']).'</strong></span>';

        /* elements associated to this structure entry
        if($current['type']=='structure')
        {
            if(empty($current['structure_elements']))
                $current['structure_elements'] = $current['object']->elements();

            $se_ids = array();
            for($se=0; $se < count($current['structure_elements']); $se++)
                $se_ids[] = $current['structure_elements'][$se]->id;

            if(!empty($se_ids))
                $out[] = '      <span>'.t(22, 'Elements').' <strong>'.implode(', ', $se_ids).'</strong></span>';
        }
        */

        $out[] = '  </div>';

        $out[] = '</div>';
	}

	return implode("\n", $out);
}
?>