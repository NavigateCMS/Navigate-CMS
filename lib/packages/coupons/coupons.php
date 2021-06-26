<?php
require_once(NAVIGATE_PATH.'/lib/packages/coupons/coupon.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/products/product.class.php');

function run()
{
    global $DB;
    global $website;
    global $layout;

	$out = '';
	$object = new coupon();
			
	switch($_REQUEST['act'])
	{
        case 'json':
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
                    if(naviforms::check_csrf_token('header'))
                    {
                        $ids = $_REQUEST['ids'];
                        foreach($ids as $id)
                        {
                            $object->load($id);
                            $object->delete();
                        }
                        echo json_encode(true);
                    }
                    else
                    {
                        echo json_encode(false);
                    }
					break;
					
				default: // list or search	
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$where = " c.website = ".intval($website->id)." ";
					$parameters = array();
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
                        {
                            list($qs_where, $qs_params) = $object->quicksearch($_REQUEST['quicksearch']);
                            $where .= $qs_where;
                            $parameters = array_merge($parameters, $qs_params);
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

                    // filter orderby vars
                    if( !in_array($_REQUEST['sord'], array('', 'desc', 'DESC', 'asc', 'ASC')) ||
                        !in_array($_REQUEST['sidx'], array('id', 'name'))
                    )
                    {
                        return false;
                    }
                    $orderby = $_REQUEST['sidx'].' '.$_REQUEST['sord'];
				
                    $sql = ' SELECT SQL_CALC_FOUND_ROWS
					                c.id, c.code, c.date_begin, c.date_end, c.type, d.text as name, d.lang as language                                    
							   FROM nv_coupons c
						  LEFT JOIN nv_webdictionary d
						  		 	 ON c.id = d.node_id
								 	AND d.node_type = "coupon"
									AND d.subtype = "name"
									AND d.lang = "'.$website->languages_list[0].'"
									AND d.website = '.$website->id.'
							  WHERE '.$where.'	
						   GROUP BY c.id, c.code, c.date_begin, c.date_end, c.type, d.text, d.lang						   
						   ORDER BY '.$orderby.' 
							  LIMIT '.$max.'
							 OFFSET '.$offset;

                    if(!$DB->query($sql, 'array', $parameters))
                    {
                        throw new Exception($DB->get_last_error());
                    }

                    $dataset = $DB->result();
                    $total = $DB->foundRows();

                    $dataset = grid_notes::summary($dataset, 'coupon', 'id');

                    $types = array(
                        'discount_amount' => t(697, "Discount amount"),
                        'discount_percentage'   =>  t(698, "Discount percentage"),
                        'free_shipping' => t(699, "Free shipping")
                    );

					$out = array();
											
					for($i=0; $i < count($dataset); $i++)
					{
                        $date_begin = core_ts2date($dataset[$i]['date_begin'], false, true);
                        $date_end = core_ts2date($dataset[$i]['date_end'], false, true);

						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> core_special_chars($dataset[$i]['code']),
							2	=> core_special_chars($dataset[$i]['name']),
							3	=> $types[$dataset[$i]['type']],
							4	=> $date_begin .' - '.$date_end,
                            5 	=> $dataset[$i]['_grid_notes_html']
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;

        case 'create':
		case 'edit':
			if(!empty($_REQUEST['id']))
				$object->load(intval($_REQUEST['id']));

			if(isset($_REQUEST['form-sent']))
			{
				$object->load_from_post();
				try
				{
                    naviforms::check_csrf_token();
					$object->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false, false, 'fa fa-check');
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = coupons_form($object);
			break;
					
		case 'delete':
            if($_REQUEST['rtk'] != $_SESSION['request_token'])
            {
                $layout->navigate_notification(t(344, 'Security error'), true, true);
                break;
            }
            else if(!empty($_REQUEST['id']))
			{
				$object->load(intval($_REQUEST['id']));	
				if($object->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = coupons_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = coupons_form($object);
				}
			}
			break;
					
		case 'list':
		default:			
			$out = coupons_list();
			break;
	}
	
	return $out;
}

function coupons_list()
{
	$navibars = new navibars();
	$navitable = new navitable("coupons_list");
	
	$navibars->title(t(682, 'Coupons'));

	$navibars->add_actions(
	    array(
	        '<a href="?fid=coupons&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
			'<a href="?fid=coupons&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
    {
        $navitable->setInitialURL("?fid=coupons&act=json&_search=true&quicksearch=".$_REQUEST['navigate-quicksearch']);
    }
	
	$navitable->setURL('?fid=coupons&act=json');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid=coupons&act=edit&id=');
    $navitable->setGridNotesObjectName("coupon");

    $navitable->addCol("ID", 'id', "40", "true", "left");
    $navitable->addCol(t(237, 'Code'), 'code', "64", "false", "center");
    $navitable->addCol(t(159, 'Name'), 'name', "300", "true", "left");
    $navitable->addCol(t(160, 'Type'), 'type', "60", "false", "center");
    $navitable->addCol(t(622, 'Date range'), 'date_begin', "80", "false", "center");
    $navitable->addCol(t(168, 'Notes'), 'note', "50", "false", "center");

	$navibars->add_content($navitable->generate());

	return $navibars->generate();
}

function coupons_form($object)
{
	global $layout;
	global $website;
	global $events;
	global $user;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
    $currencies = product::currencies();
	
	if(empty($object->id))
    {
        $navibars->title(t(682, 'Coupons').' / '.t(38, 'Create'));
    }
	else
    {
        $navibars->title(t(682, 'Coupons').' / '.t(170, 'Edit').' ['.$object->id.']');
    }

    if(empty($object->id))
    {
        $navibars->add_actions(
            array(
                ($user->permission('coupons.create')=='true'?
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : "")
            )
        );
    }
    else
    {
        $navibars->add_actions(
            array(
                ($user->permission('coupons.edit')=='true'?
                    '<a href="#" onclick="navigate_tabform_submit(1);" title="Ctrl+S" data-action="save">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'
				</a>' : ""),
                ($user->permission("coupons.delete") == 'true'?
                    '<a href="#" onclick="navigate_delete_dialog();">
					<img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'
				</a>' : "")
            )
        );

        $layout->add_script('
            function navigate_delete_dialog()
            {
                navigate_confirmation_dialog(
                    function() { window.location.href = "?fid=coupons&act=delete&id='.$object->id.'&rtk='.$_SESSION['request_token'].'"; }, 
                    null, null, "'.t(35, 'Delete').'"
                );
            }
        ');
	}

    if(!empty($object->id))
    {
        $notes = grid_notes::comments('coupon', $object->id);
        $navibars->add_actions(
            array(
                '<a href="#" onclick="javascript: navigate_display_notes_dialog();">
					<span class="navigate_grid_notes_span" style=" width: 20px; line-height: 16px; ">'.count($notes).'</span>
					<img src="img/skins/badge.png" width="20px" height="18px" style="margin-top: -2px;" class="grid_note_edit" align="absmiddle" /> '.t(168, 'Notes').'
				</a>'
            )
        );
    }


	$extra_actions = array();
    if(!empty($object->id))
    {
        // we attach an event which will be fired by navibars to put an extra button
        $events->add_actions(
            'coupon',
            array(
                'item' => &$object,
                'navibars' => &$navibars
            ),
            $extra_actions
        );
    }

    if(!empty($object->id))
    {
        $layout->navigate_notes_dialog('coupon', $object->id);
    }
	
	$navibars->add_actions(
	    array(
	        (!empty($object->id)? '<a href="?fid=coupons&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
			'<a href="?fid=coupons&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
			'search_form'
        )
    );

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $object->id));
    $navibars->add_tab_content($naviforms->csrf_token());
	
	$navibars->add_tab_content_row(
	    array(
	        '<label>ID</label>',
			'<span>'.(!empty($object->id)? $object->id : t(52, '(new)')).'</span>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(237, 'Code').'</label>',
            $naviforms->textfield('code', $object->code, NULL, NULL, 'maxlength="16"'),
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(63, 'Languages').'</label>',
            $naviforms->buttonset('language_selector', $website->languages(), $website->languages_list[0], "navigate_coupons_select_language(this);")
        )
    );

    foreach($website->languages_list as $lang)
    {
        $navibars->add_tab_content('<div class="language_fields" id="language_fields_' . $lang . '" style=" display: none; ">');

        $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

        $navibars->add_tab_content_row(
            array(
                '<label>' . t(159, 'Name') . ' ' . $language_info . '</label>',
                $naviforms->textfield('name-' . $lang, @$object->dictionary[$lang]['name'])
            )
        );

        $navibars->add_tab_content('</div>');
    }

    $layout->add_script('
        function navigate_coupons_select_language(el)
        {
            var code;
            if(typeof(el)=="string") 
            {
                code = el;
                $(\'input[name="language_selector[]"]\').parent().find(\'label\').removeClass(\'ui-state-active\');
                $(\'label[for="language_selector_\' + code + \'"]\').addClass(\'ui-state-active\');
            }
            else 
                code = $("#"+$(el).attr("for")).val();	
                
            $(".language_fields").css("display", "none");
            $("#language_fields_" + code).css("display", "block");
                        
            $("#language_selector_" + code).attr("checked", "checked");
        }
        
        navigate_coupons_select_language("'.$website->languages_list[0].'");    
    ');

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(690, 'Currency').'</label>',
            $naviforms->selectfield('currency', array_keys(product::currencies()), array_values(product::currencies()), (!empty($object->currency)? $object->currency : $website->currency), "navigate_coupons_currency_change();", false, array(), "width: 80px;", true)
        )
    );

    $layout->add_script('
        function navigate_coupons_currency_change()
        {
            var currencies = '.json_encode($currencies).';
            $(".current-currency").html(currencies[$("#currency").val()]);
        }
    ');

    $navibars->add_tab( t(700, "Conditions") );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(702, 'Date begin').'</label>',
            $naviforms->datefield('date_begin', $object->date_begin, true),
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(703, 'Date end').'</label>',
            $naviforms->datefield('date_end', $object->date_end, true),
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(696, 'Minimum spend').'</label>',
            $naviforms->decimalfield('minimum_spend', $object->minimum_spend, 2, NULL, NULL, NULL, NULL, '100px'),
            '<span class="current-currency">'.$currencies[value_or_default($object->currency, $website->currency)].'</span>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(695, 'Times allowed globally').'</label>',
            $naviforms->decimalfield('times_allowed_globally', $object->times_allowed_globally, 0, NULL, NULL, NULL, NULL, '100px'),
            '<span class="navigate-form-row-info">0 => &infin;</span>'
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(694, 'Times allowed per customer').'</label>',
            $naviforms->decimalfield('times_allowed_customer', $object->times_allowed_customer, 0, NULL, NULL, NULL, NULL, '100px'),
            '<span class="navigate-form-row-info">0 => &infin;</span>'
        )
    );

    $navibars->add_tab( t(701, "Discount") );

    $types = array(
        'discount_amount' => t(697, "Discount amount"),
        'discount_percentage'   =>  t(698, "Discount percentage"),
        'free_shipping' => t(699, "Free shipping")
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(160, 'Type').'</label>',
            $naviforms->selectfield('type', array_keys($types), array_values($types), $object->type, "navigate_coupon_type_changed();")
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(697, 'Discount amount').'</label>',
            $naviforms->decimalfield('discount_amount', $object->discount_value, 2, NULL, NULL, NULL, NULL, '80px'),
            '<span class="current-currency">'.$currencies[value_or_default($object->currency, $website->currency)].'</span>'
        ),
        "coupons_discount_amount_wrapper",
        ' style="display: none;" '
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(698, 'Discount percentage').'</label>',
            $naviforms->decimalfield('discount_percentage', $object->discount_value, 2, NULL, NULL, NULL, '%', '80px')
        ),
        "coupons_discount_percentage_wrapper",
        ' style="display: none;" '
    );

    $layout->add_script('
        function navigate_coupon_type_changed()
        {
            $("#coupons_discount_amount_wrapper").hide();
            $("#coupons_discount_percentage_wrapper").hide();
            
            switch($("#type").val())
            {                    
                case "discount_amount":
                    $("#coupons_discount_amount_wrapper").show();
                    break;
                    
                case "discount_percentage":
                    $("#coupons_discount_percentage_wrapper").show();
                    break;
                
                case "free_shipping":
                    break;
                                        
                default:
                    break;
            }                        
        }
        
        navigate_coupon_type_changed();
    ');

    $layout->add_script("
        $(document).on('keydown.ctrl_s', function (evt) { navigate_tabform_submit(1); return false; } );
        $(document).on('keydown.ctrl_m', function (evt) { navigate_media_browser(); return false; } );
    ");

	return $navibars->generate();
}
?>