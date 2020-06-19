<?php
require_once(NAVIGATE_PATH.'/lib/packages/webdictionary/webdictionary.class.php');

function run()
{
	global $layout;
	global $website;
	
	$out = '';
	$wtext = new webdictionary();
			
	switch($_REQUEST['act'])
	{
		case 'json': // json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
                    if(naviforms::check_csrf_token('header'))
                    {
                        $ids = $_REQUEST['ids'];
                        foreach($ids as $id)
                        {
                            $wtext->load($id);
                            $wtext->delete();
                        }
                        echo json_encode(true);
                    }
                    else
                    {
                        echo json_encode(false);
                    }
					break;
					
				default: // list or search
                    $out = array();
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$where = ' website = '.$website->id;
															
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
                        {
                            $where .= $wtext->quicksearch($_REQUEST['quicksearch']);
                        }
						else if(isset($_REQUEST['filters']))
                        {
                            $where .= navitable::jqgridsearch($_REQUEST['filters']);
                        }
						else	// single search
                        {
                            $where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
                        }
					}

                    if( !in_array($_REQUEST['sord'], array('', 'desc', 'DESC', 'asc', 'ASC')) ||
                        !in_array($_REQUEST['sidx'], array('id', 'node_id', 'source', 'lang', 'text') )
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];

					list($dataset, $total) = webdictionary_search($where, $orderby, $offset, $max);

					for($i=0; $i < count($dataset); $i++)
					{
						$origin = "";
						if(!empty($dataset[$i]['theme']))
                        {
                            $origin = '<i class="fa fa-fw fa-paint-brush ui-text-light" title="'.t(368, "Theme").'"></i> '.$dataset[$i]['theme'];
                        }
						else if(!empty($dataset[$i]['extension']))
                        {
                            $origin = '<i class="fa fa-fw fa-puzzle-piece ui-text-light" title="'.t(617, "Extension").'"></i> '.$dataset[$i]['extension'];
                        }

						if(empty($dataset[$i]))
                        {
                            continue;
                        }

						$string_id = $dataset[$i]['id'];
						if(!empty($dataset[$i]['theme']))
                        {
                            $string_id = $dataset[$i]['theme'].'.'.$string_id;
                        }

						if(!empty($dataset[$i]['extension']))
                        {
                            $string_id = $dataset[$i]['extension'].'.'.$string_id;
                        }

						$out[$i] = array(
							0	=> $string_id,	// this 4th column won't appear, it works as ghost column for setting a unique ID to the row
							1	=> $dataset[$i]['node_id'], // id of the word (Ex. word "3" in English -> test, word "3" in Spanish -> prueba)
							2	=> $origin,
							3 	=> language::name_by_code($dataset[$i]['lang']),
							4	=> core_special_chars($dataset[$i]['text']),
							5   => $dataset[$i]['source']
						);
					}

					navitable::jqgridJson($out, $page, $offset, $max, $total, 0); // 0 is the index of the ghost ID column
					break;
			}
			
			session_write_close();
			exit;
			break;
		
		case 'edit': // edit/new form
			if(!empty($_REQUEST['path']) && !is_numeric($_REQUEST['id']))
            {
                $wtext->load($_REQUEST['path']);
            }
			else if(!empty($_REQUEST['id']))
            {
                $wtext->load(intval($_REQUEST['id']));
            }

			if(isset($_REQUEST['form-sent']))
			{
				$wtext->load_from_post();

				try
				{
                    naviforms::check_csrf_token();
					$wtext->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = webdictionary_form($wtext);
			break;	
			
		case 'remove':
            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
                break;
            }
			else if(!empty($_REQUEST['id']))
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

		case 'edit_language':
			if($_REQUEST['form-sent']=='true')
			{
                naviforms::check_csrf_token();
				$status = webdictionary::save_translations_post($_REQUEST['code']);
				if($status=='true')
                {
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
                }
				else
                {
                    $layout->navigate_notification(implode('<br />', $status), true, true);
                }
			}

			$out = webdictionary_edit_language_form($_REQUEST['code']);
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
	global $events;
	global $website;

	$navibars = new navibars();
	$navitable = new navitable("webdictionary_list");
	
	$navibars->title(t(21, 'Dictionary'));

	if(count($website->languages) > 0)
	{
		foreach($website->languages as $wslg_code => $wslg)
        {
            $wslg_links[] = '<a href="?fid='.$_REQUEST['fid'].'&act=edit_language&code='.$wslg_code.'"><i class="fa fa-fw fa-caret-right"></i> '.language::name_by_code($wslg_code).'</a>';
        }

		$events->add_actions(
			'blocks',
			array(
				'item' => null,
				'navibars' => &$navibars
			),
			$wslg_links,
			'<a class="content-actions-submenu-trigger" href="#"><img height="16" align="absmiddle" width="16" src="img/icons/silk/comment_edit.png"> '.t(602, 'Edit language').' &#9662;</a>'
		);
	}

	$navibars->add_actions(
		array(
			'<a href="?fid='.$_REQUEST['fid'].'&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
		)
	);

	$navitable->setQuickSearchURL('?fid='.$_REQUEST['fid'].'&act=json&_search=true&quicksearch=');
	if($_REQUEST['quicksearch']=='true')
    {
        $navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=json&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
    }
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=json');
	$navitable->sortBy('node_id');
	$navitable->setDataIndex('node_id');
	$navitable->setEditUrl('node_id', '?fid='.$_REQUEST['fid'].'&act=edit&id=', 'path');
	$navitable->max_rows = 500;

	$navitable->addCol("#id#", 'id', "40", "true", "left", NULL, "true");   // ghost (unique) ID
	
	$navitable->addCol("ID", 'node_id', "90", "true", "left");	// textual ID
	$navitable->addCol(t(191, 'Source'), 'source', "60", "true", "left");
	$navitable->addCol(t(46, 'Language'), 'lang', "50", "true", "left");	
	$navitable->addCol(t(54, 'Text'), 'text', "400", "true", "left");	
	$navitable->addCol("Path", "path", 0, "false", "left", NULL, "true");

	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function webdictionary_form($item)
{
	global $layout;
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
		$navibars->add_actions(
			array(
				'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'
			)
		);
	}
	else
	{
		$navibars->add_actions(
			array(
				'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
				'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
			)
		);

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid='.$_REQUEST['fid'].'&act=remove&id='.$item->node_id.'&path='.$_REQUEST['path'].'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}
	
	$navibars->add_actions(
		array(
			(!empty($item->id)? '<a href="?fid=webdictionary&act=edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=webdictionary&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
		)
	);

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->csrf_token());
    $navibars->add_tab_content($naviforms->hidden('theme', $item->theme));

	$node_id_text = (!empty($item->node_id)? $item->node_id : t(52, '(new)'));
	if(!empty($item->node_id) && !is_numeric($item->node_id))
	{
		if($item->node_type == 'extension')
        {
            $node_id_text .= ' | '.$item->extension_name;
        }
		else
        {
            $node_id_text .= ' | '.$theme->title;
        }
	}
	
	$navibars->add_tab_content_row(
		array(
			'<label>ID</label>',
			'<span>'.$node_id_text.'</span>'
		)
	);
											
	$navibars->add_tab_content($naviforms->hidden('node_type', (empty($item->node_type)? 'global' : $item->node_type)));
	$navibars->add_tab_content($naviforms->hidden('subtype', (empty($item->subtype)? 'text' : $item->subtype)));	
	$navibars->add_tab_content($naviforms->hidden('node_id', $item->node_id));		
											

    $data = array();
    foreach($website->languages_list as $l)
    {
        $data[$l] = language::name_by_code($l);
    }

	// load installed translation services
    $translate_extensions = extension::list_installed('translate', false);

    foreach($website->languages_list as $lang)
	{
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
			$navibars->add_tab_content_row(
				array(
					'<label>'.language::name_by_code($lang).'</label>',
					$naviforms->textarea('webdictionary-text-'.$lang, $item->text[$lang])
				)
			);
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

		// remove theme strings that do not match the search query
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

	$extensions_translations = array();

	$extensions = extension::list_installed();
	if(!is_array($extensions))
    {
        $extensions = array();
    }

	foreach($extensions as $extension)
	{
		$ext = new extension();
		$ext->load($extension['code']);
		$extension_translations = $ext->get_translations(); // load all translations of the extension

		// remove extension strings that do not match the search query
		if(!empty($qsearch))
		{
			for($trs=0; $trs < count($extension_translations); $trs++)
			{
				$tt_text = mb_strtolower($extension_translations[$trs]['text']);
				$tt_extension = mb_strtolower($extension_translations[$trs]['extension']);
				$tt_id = mb_strtolower($extension_translations[$trs]['node_id']);
				$tt_lang = $extension_translations[$trs]['lang'];

				if(	strpos($tt_text, $qsearch) === false	&&
					strpos($tt_extension, $qsearch) === false	&&
					strpos($tt_id, $qsearch) === false	&&
					strpos($tt_lang, $qsearch) === false	)
				{
					$extension_translations[$trs] = NULL;
				}
			}

			$extension_translations = array_filter($extension_translations);
		}

		if(!empty($extension_translations))
		{
			$extensions_translations = array_merge(
				$extensions_translations,
				$extension_translations
			);
		}
	}


	$DB->query('
		SELECT id, theme, node_id, node_type, lang, `text`, CONCAT_WS(".", node_type, "" , subtype) AS source
		  FROM nv_webdictionary
		 WHERE '.$where.'
		   AND node_type = "global"
		
		UNION 
		
		SELECT id, theme, subtype AS node_id, node_type, lang, `text`, CONCAT_WS(".", node_type, theme, subtype) AS source
		  FROM nv_webdictionary
		 WHERE '.$where.'
		   AND node_type = "theme"',
		 'array'
	);
	$resultset = $DB->result();

	// remove from theme_translations the strings already present (customized) in database
	for($dbrs=0; $dbrs < count($resultset); $dbrs++)
	{
		for($trs=0; $trs < count($theme_translations); $trs++)
		{				
			if(	$resultset[$dbrs]['node_type'] == "theme"	&&
				$resultset[$dbrs]['node_id'] == $theme_translations[$trs]['node_id']	&&
				$resultset[$dbrs]['lang'] == $theme_translations[$trs]['lang']
			)
			{
				unset($theme_translations[$trs]);
				break;
			}
		}
	}

	$dataset = array_merge($resultset, $theme_translations);

	$DB->query('
		SELECT id, extension, node_type, subtype AS node_id, lang, `text`, CONCAT_WS(".", node_type, extension, subtype) AS source
		  FROM nv_webdictionary
		 WHERE '.$where.'
		   AND node_type = "extension"',
		 'array'
	);
	$resultset = $DB->result();

	// remove from extension translations the strings already present (customized) in database
	for($dbrs=0; $dbrs < count($resultset); $dbrs++)
	{
		for($trs=0; $trs < count($extensions_translations); $trs++)
		{
			if(	$resultset[$dbrs]['node_type'] == "extension"	&&
				$resultset[$dbrs]['extension'] == $extensions_translations[$trs]['extension']	&&
				$resultset[$dbrs]['node_id'] == $extensions_translations[$trs]['node_id']	&&
				$resultset[$dbrs]['lang'] == $extensions_translations[$trs]['lang']
			)
			{
				unset($extensions_translations[$trs]);
				break;
			}
		}
	}

	$dataset = array_merge($dataset, $resultset, $extensions_translations);
	$total = count($dataset);

	// reorder dataset
	$orderby = explode(' ', $orderby);
	// [0] -> column, [1] -> asc | desc

	$dataset = array_orderby($dataset, $orderby[0], ($orderby[1]=='desc'? SORT_DESC : SORT_ASC));

	$dataset = array_slice($dataset, $offset, $max);

	return array($dataset, $total);
}

function webdictionary_edit_language_form($code)
{
	global $DB;
	global $website;
	global $theme;
	global $events;

	$navibars = new navibars();
	$naviforms = new naviforms();

	$navibars->title(t(21, 'Dictionary').' / '.t(602, 'Edit language'));

	$navibars->add_actions(
		array(
			'<a href="#" onclick="navigate_tabform_submit(0);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'
		)
	);

	$navibars->add_actions(
		array(
			'<a href="?fid=webdictionary&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
		)
	);

	$navibars->form();

	$navibars->add_tab(t(188, "Translate"));

	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->csrf_token());

	$origin = "";
	foreach($website->languages_list as $l)
	{
		if($l==$code)
        {
            continue;
        }
		else
		{
			$origin = $l;
			break;
		}
	}

	// retrieve original theme translations, if any
	$theme->get_translations();
	$dict_dest = array();
	foreach($theme->dictionaries as $otext)
	{
		if($otext['lang'] == $code)
		{
			$dict_dest[$otext['node_id']] = $otext['text'];
		}
	}

	// retrieve existing database dictionary translations
	$DB->query('
		SELECT *
		  FROM nv_webdictionary
		 WHERE (	(node_type = "global")
		    		OR (node_type = "theme" AND theme= "'.$theme->name.'")
		       ) AND
		       website = '.$website->id.'
    ');

	$db_trans = $DB->result();

	foreach($db_trans as $otext)
	{
		$text_id = $otext->node_id;
		if($otext->node_type == "theme")
			$text_id = $otext->subtype;

		if($otext->lang == $code)
		{
			$dict_dest[$text_id] = $otext->text;
		}
		else if($otext->lang == $origin && $otext->node_type == "global")
		{
			array_push(
				$theme->dictionaries,
				array(
					"source" => $text_id,
					"node_id" => $text_id,
					"text" => $otext->text,
					"lang" => $otext->lang
				)
			);
		}
	}


	$extensions_translations = array();

	$extensions = extension::list_installed();
	if(!is_array($extensions))
		$extensions = array();

	foreach($extensions as $extension)
	{
		$ext = new extension();
		$ext->load($extension['code']);
		$extension_translations = $ext->get_translations(); // load all translations of the extension

		$extensions_translations = array_merge(
			$extensions_translations,
			$extension_translations
		);
	}

	$DB->query('
		SELECT *
		  FROM nv_webdictionary
		 WHERE node_type = "extension" AND
		       website = '.$website->id,
		'array'
	);
	$resultset = $DB->result();

	for($dbrs=0; $dbrs < count($resultset); $dbrs++)
	{
		$found = false;
		for($trs=0; $trs < count($extensions_translations); $trs++)
		{
			if(	$resultset[$dbrs]['node_type'] == "extension"	&&
				$resultset[$dbrs]['extension'] == $extensions_translations[$trs]['extension']	&&
				$resultset[$dbrs]['subtype'] == $extensions_translations[$trs]['node_id']	&&
				$resultset[$dbrs]['lang'] == $extensions_translations[$trs]['lang']
			)
			{
				$found = true;
				$extensions_translations[$trs]['text'] = $resultset[$dbrs]['text'];
			}
		}

		// translation was not included in the extension languages, so we need to add it to our array
		if(!$found)
		{
			$extensions_translations[] = array(
				'extension' => $resultset[$dbrs]['extension'],
				'source' => 'extension.'.$resultset[$dbrs]['extension'].'.'.$resultset[$dbrs]['subtype'],
				'node_id' => $resultset[$dbrs]['subtype'],
				'lang' => $resultset[$dbrs]['lang'],
				'text' => $resultset[$dbrs]['text']
			);
		}
	}

	// generate table

	$table = '<table class="box-table">';
	$table.= '<tr><th>'.t(237, "Code").'</th><th>'.language::name_by_code($origin).'</th><th>'.language::name_by_code($code).'</th></tr>';

	foreach($theme->dictionaries as $otext)
	{
		if($otext['lang'] == $origin)
		{
			$translation = $dict_dest[$otext['node_id']];
			if(is_numeric($otext['source']))
            {
                $otext['source'] = 'global.'.$otext['source'];
            }

			// note: PHP does not allow using dots in $_POST variable names, unless they are used in an array
			$table.= '
				<tr>
					<td>'.$otext['node_id'].'</textarea></td>
					<td><textarea rows="2" cols="60" disabled="disabled">'.core_special_chars($otext['text']).'</textarea></td>
					<td><textarea name="data['.$code.'.'.$otext['source'].']" rows="2" cols="60">'.core_special_chars($translation).'</textarea></td>
				</tr>
			';
		}
	}

	foreach($extensions_translations as $otext)
	{
		if($otext['lang'] == $origin)
		{
			$translation = "";
			foreach($extensions_translations as $dtext)
			{
				if($otext['source'] == $dtext['source'] &&  $dtext['lang']==$code)
                {
                    $translation = $dtext['text'];
                }
			}

			$table.= '
				<tr>
					<td>'.$otext['source'].'</textarea></td>
					<td><textarea rows="2" cols="60" disabled="disabled">'.core_special_chars($otext['text']).'</textarea></td>
					<td><textarea name="data['.$code.'.'.$otext['source'].']" rows="2" cols="60">'.core_special_chars($translation).'</textarea></td>
				</tr>
			';
		}
	}

	$table.= '</table>';

	$navibars->add_tab_content($table);

	return $navibars->generate();
}

?>