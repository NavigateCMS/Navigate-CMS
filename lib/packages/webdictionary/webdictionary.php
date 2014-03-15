<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$wtext = new webdictionary();
			
	switch($_REQUEST['act'])
	{
		case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
					foreach($ids as $id)
					{
						$wtext->load($id);
						$wtext->delete();
					}
					echo json_encode(true);
					break;
					
				default: // list or search	
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby= $_REQUEST['sidx'].' '.$_REQUEST['sord'];

					$where = ' website = '.$website->id;				
															
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $wtext->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}
				
					list($dataset, $total) = webdictionary_search($where, $orderby, $offset, $max);

					for($i=0; $i < count($dataset); $i++)
					{
						if(empty($dataset[$i])) continue;
						$out[$i] = array(
							0	=> $dataset[$i]['id'],	// this 4th column won't appear, it works as ghost column for setting a unique ID to the row
							1	=> $dataset[$i]['node_id'], // id of the word (Ex. word "3" in English -> test, word "3" in Spanish -> prueba)
							2	=> $dataset[$i]['theme'],
							3 	=> language::name_by_code($dataset[$i]['lang']),
							4	=> $dataset[$i]['text']
						);
					}					
					
					navitable::jqgridJson($out, $page, $offset, $max, $total, 0); // 0 is the index of the ghost ID column
					break;
			}
			
			session_write_close();
			exit;
			break;
		
		case 'edit': // edit/new form		
			if(!empty($_REQUEST['id']))
				$wtext->load($_REQUEST['id']);

			if(isset($_REQUEST['form-sent']))
			{
				$wtext->load_from_post();
				try
				{
					$wtext->save();
					$layout->navigate_notification(t(53, "Data saved successfully."), false);	
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = webdictionary_form($wtext);
			break;	
			
		case 'remove': // remove
			if(!empty($_REQUEST['id']))
			{
				$wtext->load($_REQUEST['id']);
				if($wtext->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = webdictionary_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = webdictionary_form($wtext);
				}
			}
			break;
		
		case 0: // list / search result
		default:			
			$out = webdictionary_list();
			break;
	}
	
	return $out;
}

function webdictionary_list()
{
	$navibars = new navibars();
	$navitable = new navitable("webdictionary_list");
	
	$navibars->title(t(21, 'Dictionary'));

	$navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
									'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));
		
	//$navibars->add_tab(t(51, "List"));
	
	//$navitable->setTitle('Diccionari');	
	//$navitable->setURL(NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid='.$_REQUEST['fid'].'&act=1');
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('node_id');
	$navitable->setDataIndex('node_id');
	$navitable->setEditUrl('node_id', '?fid='.$_REQUEST['fid'].'&act=edit&id=');

	$navitable->addCol("#id#", 'id', "40", "true", "left", NULL, "true");	
	
	$navitable->addCol("ID", 'node_id', "90", "true", "left");	
	$navitable->addCol(t(368, 'Theme'), 'theme', "60", "true", "left");	
	$navitable->addCol(t(46, 'Language'), 'lang', "50", "true", "left");	
	$navitable->addCol(t(54, 'Text'), 'text', "400", "true", "left");	
	
	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function webdictionary_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $theme;
    global $events;
	
	$navibars = new navibars();
	$naviforms = new naviforms();

    $events->trigger(
        'webdictionary',
        'edit',
        array()
    );

	$navibars->title(t(21, 'Dictionary'));

	if(empty($item->node_id))
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
									);
	}
	else
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
											'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
										)
									);		
								

		
		$delete_html = array();
		$delete_html[] = '<div id="navigate-delete-dialog" class="hidden">'.t(57, 'Do you really want to delete this item?').'</div>';
		$delete_html[] = '<script language="javascript" type="text/javascript">';
		$delete_html[] = 'function navigate_delete_dialog()';		
		$delete_html[] = '{';				
		$delete_html[] = '$("#navigate-delete-dialog").dialog({
							resizable: true,
							height: 150,
							width: 300,
							modal: true,
							title: "'.t(59, 'Confirmation').'",
							buttons: {
								"'.t(58, 'Cancel').'": function() {
									$(this).dialog("close");
								},
								"'.t(35, 'Delete').'": function() {
									$(this).dialog("close");
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=remove&id='.$item->node_id.'";
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=webdictionary&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=webdictionary&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->hidden('theme', $item->theme));
	
	$node_id_text = (!empty($item->node_id)? $item->node_id : t(52, '(new)'));
	if(!empty($item->node_id) && !is_numeric($item->node_id))
		$node_id_text .= ' | '.$theme->title;
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.$node_id_text.'</span>' ));
											
	$navibars->add_tab_content($naviforms->hidden('node_type', (empty($item->node_type)? 'global' : $item->node_type)));
	$navibars->add_tab_content($naviforms->hidden('subtype', (empty($item->subtype)? 'text' : $item->subtype)));	
	$navibars->add_tab_content($naviforms->hidden('node_id', $item->node_id));		
											

    $data = array();
    foreach($website->languages_list as $l)
        $data[$l] = language::name_by_code($l);

    $translate_extensions = extension::list_installed('translate');

    foreach($website->languages_list as $lang)
	{
		$select = $naviforms->selectfield('', array_keys($data), array_values($data), $website->languages_list[0], '', false, false, ' width: auto; ');

        $translate_menu = '';
        if(!empty($translate_extensions))
        {
            $translate_extensions_titles = array();
            $translate_extensions_actions = array();

            foreach($translate_extensions as $te)
            {
                if($te['enabled']=='0') continue;
                $translate_extensions_titles[] = $te['title'];
                $translate_extensions_actions[] = 'javascript: navigate_translate_'.$te['code'].'(\'#webdictionary-text-'.$lang.'\', \''.$lang.'\');';
            }

            if(!empty($translate_extensions_actions))
            {
                $translate_menu = $naviforms->splitbutton(
                    'translate_'.$lang,
                    '<img src="img/icons/silk/comment.png" align="absmiddle"> '.t(188, 'Translate'),
                    $translate_extensions_actions,
                    $translate_extensions_titles
                );
            }
        }

		if(count($website->languages_list) > 1)
		{
			$navibars->add_tab_content_row(array(
                '<label>'.language::name_by_code($lang).'</label>',
                '<div style="float: left; ">',
                $naviforms->textarea('webdictionary-text-'.$lang, $item->text[$lang]),
                '</div>',
                '<div style="float: left; margin-left: 10px; ">',
                $translate_menu,
                '</div>'
            ));
		}
		else
		{
			$navibars->add_tab_content_row(array(	'<label>'.language::name_by_code($lang).'</label>',
													$naviforms->textarea('webdictionary-text-'.$lang, $item->text[$lang])
												));
		}
		
	}
	
	return $navibars->generate();
}

function webdictionary_search($where, $orderby, $offset, $max)
{
	global $DB;
	global $website;
	global $theme;
	
	$theme_translations = array();
	
	if(!empty($theme))
	{
		$theme_translations = $theme->get_translations(); // force load theme dictionary in all languages available
		
		// search filters for theme strings (ONLY ENABLED FOR QUICKSEARCH)
		// $where example = 'website = 1 AND ( node_id LIKE "%design%" OR lang LIKE "%design%" OR text LIKE "%design%")'
		
		// extract the pattern		
		$qsearch = substr($where, strpos($where, ' LIKE "%') + 8); 
		$qsearch = mb_strtolower(substr($qsearch, 0, strpos($qsearch, '%" OR')));
		$qsearch = trim($qsearch);
		
		if(!empty($qsearch))
		{			
			for($trs=0; $trs < count($theme_translations); $trs++)
			{
				$tt_text = mb_strtolower($theme_translations[$trs]['text']);
				$tt_theme = mb_strtolower($theme_translations[$trs]['theme']);
				$tt_id = mb_strtolower($theme_translations[$trs]['node_id']);
				$tt_lang = $theme_translations[$trs]['lang'];
				
				if(	strpos($tt_text, $qsearch) === false	&&
					strpos($tt_theme, $qsearch) === false	&&
					strpos($tt_id, $qsearch) === false	&&
					strpos($tt_lang, $qsearch) === false	)
				{
					$theme_translations[$trs] = NULL;
				}
			}		
		}
		
		$theme_translations = array_filter($theme_translations);
		sort($theme_translations);
	}
	
	$DB->query('SELECT id, theme, node_id, lang, `text`
				  FROM nv_webdictionary
				 WHERE '.$where.'
				   AND node_type = "global"
				UNION 
				SELECT id, theme, subtype AS node_id, lang, `text`
				  FROM nv_webdictionary
				 WHERE '.$where.'
				   AND node_type = "theme"',
				 'array'
			  );
			  	
	$resultset = $DB->result();

	// remove from theme_translations the strings 
	// customized in Navigate database
	for($dbrs=0; $dbrs < count($resultset); $dbrs++)
	{
		for($trs=0; $trs < count($theme_translations); $trs++)
		{				
			if(	$resultset[$dbrs]['node_id']==$theme_translations[$trs]['node_id']	&&
				$resultset[$dbrs]['lang']==$theme_translations[$trs]['lang'])
			{
				unset($theme_translations[$trs]);
				break;
			}
		}
	}
	
	$dataset = array_merge($resultset, $theme_translations);
	$total = count($dataset);
	
	// reorder dataset
	$orderby = explode(' ', $orderby);
	// [0] -> column, [1] -> asc | desc

	$dataset = array_orderby($dataset, $orderby[0], ($orderby[1]=='desc'? SORT_DESC : SORT_ASC));
	
	$dataset = array_slice($dataset, $offset, $max);
	
	return array($dataset, $total);
}

?>