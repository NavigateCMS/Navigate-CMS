<?php

class navitable
{
	public $cols;
	public $id;
	public $url;
	public $sortname;
	public $sortorder;
	public $edit_index;
	public $edit_url;
    public $delete_url;
    public $quicksearch_url;
	public $initial_url;
	public $data_index;
	public $click_action;
    public $add_action;
	public $delete_action;
	public $search_action;
    public $load_callback;
    public $disable_select;
    public $after_select_callback;
    public $after_right_click_col;
    public $default_fid;

	public function __construct($id="")	
	{
		if(empty($id)) $id = 'navitable-'.time();
		$this->cols = array();
		$this->id = $id;
		$this->data_index = 'id';
		$this->click_action = 'redirect';
		$this->add_action = false;
		$this->delete_action = false;
		$this->search_action = false;
        $this->disable_select = false;
        $this->load_callback = '';
        $this->after_select_callback = '';
        $this->after_right_click_col = '';
        $this->default_fid = $_REQUEST['fid'];
		$this->quicksearch_url = '';
	}
	
	public function setURL($url)
	{
		$this->url = $url;	
	}

	public function setQuickSearchURL($url)
	{
		$this->quicksearch_url = $url;
	}
	
	public function setTitle($title)
	{
		$this->title = $title;	
	}

    public function setLoadCallback($script='')
    {
        $this->load_callback = $script;
    }
	
	public function enableDelete($delete_url = NULL)
	{
		$this->delete_action = true;
        if(!empty($delete_url))
            $this->delete_url = $delete_url;
        else
            $this->delete_url = '?fid='.$this->default_fid.'&act=1&oper=del';
    }
	
	public function enableSearch()
	{
		$this->search_action = true;	
	}

    public function enableAdd()
    {
        $this->add_action = true;
    }

    public function disableSelect()
    {
        $this->disable_select = true;
    }

    public function setDataIndex($name)
	{
        $this->data_index = $name;
    }

    public function setDefaultFID($fid)
    {
        $this->default_fid = $fid;
    }

	public function sortBy($name, $order = 'asc')
	{
		$this->sortname	 = $name;
		$this->sortorder = $order;		
	}
	
	public function addCol($label, $index, $width="auto", $sortable="false", $align="left", $edit=array(), $hidden="false")
	{
		/*
		http://www.trirand.com/jqgridwiki/doku.php?id=wiki:common_rules
		$edit = array(
			'type' => 'text', 'textarea', 'select', 'checkbox', 'password', 'button', 'image', 'file', 'custom'
			'options' => depends on type,
			'rules' =>  not implemented
			'form' => not implemented
		)
		*/
        $sortby = '';
				
		if(!empty($edit))
		{
			if(empty($edit['options'])) $edit['options'] = '{}';						
			if(empty($edit['rules'])) $edit['rules'] = '{}';
			if(empty($edit['form'])) $edit['form'] = '{}';			
			
			$newcol		 = '{	label: "'.$label.'",
								name: "'.$index.'", 
								index : "'.$sortby.'", 
								width : "'.$width.'", 
								sortable : '.$sortable.', 
								align: "'.$align.'", 
								hidden: '.$hidden.',
							  	editable: true, 
								edittype: "'.$edit['type'].'", 
								editoptions: '.$edit['options'].',
								editrules: '.$edit['rules'].', 
								formoptions: '.$edit['form'].'
							 }';
							  			
			$this->click_action = 'jqform';
		}
		else
		{
			$newcol		 = '{	label: "'.$label.'",
								name: "'.$index.'", 
								index : "'.$sortby.'", 
								width : "'.$width.'", 
								sortable : '.$sortable.', 
								align: "'.$align.'",
								hidden: '.$hidden.'
							  }';
		}
		
		$newcol = preg_replace("/\n|\r|\t/", " ", $newcol);
		$this->cols[] = $newcol;
	}
	
	public static function jqgridJson($dataset, $page, $offset, $max, $total, $indexID=0)
	{		
		/* from jQGrid docs 
			rows	the root tag for the grid
			page	the number of the requested page
			total	the total pages of the query
			records	the total records from the query
			row		a particular row in the grid
			cell	the actual data. Note that CDATA can be used. This way we can add images, links and check boxes.		
		*/
	
		$obj = new stdClass();
		
		$obj->rows = array();
		
		for($i=0; $i < count($dataset); $i++)
		{		
			$data = $dataset[$i];
			
			if(empty($data)) continue;
			
			unset($row);
			$row = array();
			
			$obj->rows[$i]['id'] = $data[$indexID]; // FIRST COLUMN MUST BE THE ID
			$obj->rows[$i]['cell'] = array(); 
			
			if(empty($data)) $data = array();
			foreach($data as $key => $value)
			{
				$row[] = $value;
			}
			$obj->rows[$i]['cell'] = $row;
		}

		if(empty($total)) $total = count($obj->rows);
		$obj->records = $total;
				
		$obj->total = ceil($obj->records / $max);	// pages
		$obj->page = $page;	// requested page			

		header('Content-Type: text/json');
		echo json_encode($obj);
		
		return true;
	}

    // compareField is the column name when writing a SQL comparison
    //              is the column value when doing a PHP comparison
	public static function jqgridcompare($compareField, $compareType, $compareValue, $returnResult=false)
	{
		global $DB;
		$compare = '';
        $result = false;
		
		switch($compareType)
		{
			case 'eq':	// equal
				$compare = $compareField.' = '.protect($compareValue);
                if($returnResult)
                    $result = (strcasecmp($compareField, $compareValue)==0);
				break;
			
			case 'ne': // not equal
				$compare = $compareField.' != '.protect($compareValue);
                if($returnResult)
                    $result = (strcasecmp($compareField, $compareValue)!=0);
				break;
				
			case 'lt': // less
				$compare = $compareField.' < '.protect($compareValue);
                if($returnResult)
                    $result = ($compareField < $compareValue);
				break;
							
			case 'le': // less or equal
				$compare = $compareField.' <= '.protect($compareValue);
                if($returnResult)
                    $result = ($compareField <= $compareValue);
				break;
							
			case 'gt': // greater
				$compare = $compareField.' > '.protect($compareValue);
                if($returnResult)
                    $result = ($compareField > $compareValue);
				break;
							
			case 'ge': // greater or equal
				$compare = $compareField.' >= '.protect($compareValue);
                if($returnResult)
                    $result = ($compareField >= $compareValue);
				break;

            case 'nu': // is null
                $compare = $compareField.' IS NULL ';
                if($returnResult)
                    $result = empty($compareField); // or is_null()?
                break;

            case 'nn': // is not null
                $compare = $compareField.' IS NOT NULL ';
                if($returnResult)
                    $result = !empty($compareField);
                break;

            case 'in': // is in
                if(!empty($compareValue))
                {
                    $compareValue = explode(",", $compareValue);
                    $compareValue = array_filter($compareValue);
                    $compareValue = array_map(
                        function($v)
                        {
                            if(is_integer($v))
                                return $v;
                            else
                                return '"'.$v.'"';
                        },
                        $compareValue
                    );

                    $compare = $compareField.' IN ('.implode(",", $compareValue).') ';

                    if($returnResult)
                        $result = in_array($compareField, $compareValue);
                }
                else
                    $compare = ' 1=1 ';
                break;

            case 'ni': // is not in
                $compare = $compareField.' NOT IN ('.$compareField.') ';
                if($returnResult)
                    $result = !in_array($compareField, explode(',', $compareField));
                break;
							
			case 'bw': // begins with
				$compare = $compareField.' LIKE '.protect($compareValue.'%');
                if($returnResult)
                    $result = (substr_compare($compareField, $compareValue, 0, strlen($compareValue), true)==0);
				break;
				
			case 'bn': // not begins with
				$compare = $compareField.' NOT LIKE '.protect($compareValue.'%');
                if($returnResult)
                    $result = (substr_compare($compareField, $compareValue, 0, strlen($compareValue), true)!=0);
				break;
							
			case 'ew': // ends with			
				$compare = $compareField.' LIKE '.protect('%'.$compareValue);
                if($returnResult)
                    $result = (substr_compare($compareField, $compareValue, -strlen($compareValue), strlen($compareValue), true)==0);
				break;

			case 'en': // NOT ends with			
				$compare = $compareField.' NOT LIKE '.protect('%'.$compareValue);
                if($returnResult)
                    $result = (substr_compare($compareField, $compareValue, -strlen($compareValue), strlen($compareValue), true)!=0);
				break;

			case 'nc': // NOT contains
				$compare = $compareField.' NOT LIKE '.protect('%'.$compareValue.'%');
                if($returnResult)
                    $result = (strpos($compareField, $compareValue)===false);
                break;
							
			default:
			case 'cn': // contains
				if(!empty($compareValue) && !$returnResult)
				{
					$compare = $compareField.' LIKE '.protect('%'.$compareValue.'%');
	                if($returnResult)
	                    $result = (strpos($compareField, $compareValue)!==false);
				}
				else // nothing to search, ignore this filter!
				{
					$compare = '1 = 1';
				}
				break;
		}

        if($returnResult)
            return $result;

		return $compare;
	}

    // convert jqgrid search filters to SQL conditions
	public static function jqgridsearch($filters)
	{
		$filters = json_decode($filters);

		$groupOp = ' AND ';
		if($filters->groupOp=='OR') $groupOp = ' OR ';
		
		$where = '';

		foreach($filters->rules as $rule)
		{
			if(empty($where)) $where =  ' AND ( ';
			else			  $where .= $groupOp;
						
			$where.= navitable::jqgridcompare($rule->field, $rule->op, $rule->data);
		}
		
		$where .= ') ';

		return $where;

	}

    public static function jqgridCheck($row, $filters)
    {
        $filters = json_decode($filters);

        if($filters->groupOp=='OR')
            $result = false;
        else
            $result = true;

        foreach($filters->rules as $rule)
        {
            if($filters->groupOp=='OR')
                $result = $result || navitable::jqgridcompare($row[$rule->field], $rule->op, $rule->data, true);
            else
                $result = $result && navitable::jqgridcompare($row[$rule->field], $rule->op, $rule->data, true);
        }

        return $result;
    }
	
	public function setEditUrl($index = 'id', $url)
	{
		$this->edit_index = $index;
		$this->edit_url = $url;			
	}
	
	public function setInitialURL($url)
	{
		$this->initial_url = $url;	
	}
	
	public function generate()
	{
		global $layout;
		global $user;

        $grid_notes_control = '?fid='.$this->default_fid.'&act=';
        if(strpos($this->default_fid, 'ext_')===0)
            $grid_notes_control = '?fid='.$this->default_fid.'&mode=';

		$html = array();

		$html[] = '<table id="'.$this->id.'"></table>';
		$html[] = '<div id="'.$this->id.'-pager"></div>';		
			
		$html[] = '<script language="javascript" type="text/javascript">';

        $html[] = 'var navitable_'.$this->id.'_selected_rows = []; ';
		
		$html[] = '$(window).on("load", function() { ';
		
		$html[] = '$("#'.$this->id.'").jqGrid({';
		
		if(!empty($this->initial_url))		
			$html[] = 'url: "'.$this->initial_url.'",';
		else
			$html[] = 'url: "'.$this->url.'",';
		
		$html[] = 'editurl: "'.$this->url.'",';		
		$html[] = 'datatype: "json",';	
		$html[] = 'mtype: "GET",';			
		$html[] = 'pager: "#'.$this->id.'-pager",';	
		
		$html[] = 'viewrecords: true,';		// display the number of total records in the pager bar
		$html[] = 'rowNum: "30",';			
		$html[] = 'rowList: [10,15,20,30,50,100],';
		$html[] = 'scroll: 1,';
		$html[] = 'iconSet: "fontAwesome",';

		$html[] = 'loadonce: false,';

        if($this->click_action == 'redirect')
			$html[] = 'multiselect: true,';			
		//$html[] = 'multikey: "ctrlKey",';
				
		$html[] = 'autowidth: true,';
		$html[] = 'shrinkToFit: true,';
		$html[] = 'forceFit: true,';

        $html[] = 'onSelectRow: function(rowid, status, e) {
                navitable_'.$this->id.'_selected_rows = $("#'.$this->id.'").jqGrid("getGridParam", "selarrrow");
                '.$this->after_select_callback.'
        },';

        $html[] = 'onSelectAll: function(aRowids, status) {
            if(status)
                navitable_'.$this->id.'_selected_rows = aRowids;
            else
                navitable_'.$this->id.'_selected_rows = [];

            // restore cells background color
            $("#'.$this->id.'").find("tr").trigger("mouseenter").trigger("mouseleave");

            '.$this->after_select_callback.'
        },';

        $html[] = 'onRightClickRow: function(rowid, iRow, iCol, e) {
            '.$this->after_right_click_col.'
        },';

        $html[] = 'gridComplete: function() {
			$("td").noSelect(); // requires jQuery no-select plugin
            $("#'.$this->id.'").find("tr").not(":first").off().longclick(function()
            {
                '.$this->id.'_dclick($(this).attr("id"), null, null, null);
            });
			navigate_grid_bind_color_swatch_notes();
			$(navitable_'.$this->id.'_selected_rows).each(function(i, el)
			{
			    //$("#'.$this->id.'").setSelection(el, true);
			    // update row background
			    $("#" + el).trigger("mouseenter").trigger("mouseleave");
            });
            $("#'.$this->id.'").jqGrid("setGridWidth", $("#navigate-content").width());
			'.$this->load_callback.'
		},';

		if(!empty($this->sortname))
		{
			$html[] = 'sortname: "'.$this->sortname.'",';
			$html[] = 'sortorder: "'.$this->sortorder.'",';		
		}
		
		if(!empty($this->click_action))
			$html[] = ' ondblClickRow: '.$this->id.'_dclick, ';
		
		if(!empty($this->title))
			$html[] = 'caption: "'.$this->title.'",';

		$html[] = 'colModel: ['.implode(",\n", $this->cols).']';	
				
		$html[] = '});';

		$html[] = '$("#'.$this->id.'").jqGrid(
		    "navGrid",
            "#'.$this->id.'-pager",
            {
                add: '.($this->add_action && !empty($this->edit_url)? 'true' : 'false').',
                edit: '.(($this->click_action=='jqform')? 'true' : 'false').',
                del: '.($this->delete_action? 'true' : 'false').',
                search:'.(!empty($this->search_action)? 'true' : 'false').',
                searchtext:"'.t(41, 'Search').' ",
                addfunc: function()
                {
                    window.location.href="?fid='.$this->default_fid.'&act=2";
                },
                delfunc: function(rownums)
                {
                    // ask confirmation
                    $(\'<div id="navigate-delete-dialog" class="hidden">'.t(60, 'Do you really want to delete the selected items?').'</div>\')
                        .dialog(
                        {
                            resizable: true,
                            height: 150,
                            width: 300,
                            modal: true,
                            title: "'.t(59, 'Confirmation').'",
                            buttons:
                            {
                                "'.t(58, 'Cancel').'": function()
                                {
                                    $(this).dialog("close");
                                },
                                "'.t(35, 'Delete').'": function()
                                {
                                    $(this).dialog("close");
                                    // get row main id
                                    var rowids = [];
                                    for(var rownum in rownums)
                                    {
                                        rowids.push($("#'.$this->id.'").getRowData(rownums[rownum]).'.$this->data_index.');
                                    }
                                    // ajax call
                                    $.ajax(
                                    {
                                        async: false,
                                        data: {ids: rowids},
                                        dataType: "json",
                                        complete: function()
                                        {
                                            // reload table (with or without success)
                                            $("#'.$this->id.'").trigger("reloadGrid");
                                        },
                                        type: "post",
                                        url: "'.$this->delete_url.'"
                                    });
                                }
                            }
                        });
                }
            },
            {},
            {},
            {},
            { multipleSearch: true }
        );';

		$html[] = '$("#'.$this->id.'").jqGrid("setGridParam", 
		{ 
			url: "'.$this->url.'"
		});';


        // enable multiple row selection with shift key
        // the following code is adapted from:
        // http://stackoverflow.com/questions/11174499/shift-click-jqgrid-multiselect-missing-last-row
        $html[] = '$("#'.$this->id.'").jqGrid("setGridParam",
		{
			beforeSelectRow: function(rowid, e) {
			    if('.($this->disable_select? 'true' : 'false').')
			        return false;

                var $this = $(this), rows = this.rows,
                // get id of the previous selected row
                startId = $this.jqGrid("getGridParam", "selrow"),
                startRow, endRow, iStart, iEnd, i, rowidIndex;

                if (!e.ctrlKey && !e.shiftKey)
                {
                    //  intentionally left here to show differences with
                    //  Oleg\'s solution. Just have normal behavior instead.
                    //  $this.jqGrid("resetSelection");
                }
                else if (startId && e.shiftKey)
                {
                    // Do not clear existing selections
                    // get DOM elements of the previous selected and
                    // the currect selected rows
                    startRow = rows.namedItem(startId);
                    endRow = rows.namedItem(rowid);

                    if (startRow && endRow)
                    {
                        // get min and max from the indexes of the previous selected
                        // and the currect selected rows
                        iStart = Math.min(startRow.rowIndex, endRow.rowIndex);
                        rowidIndex = endRow.rowIndex;
                        iEnd = Math.max(startRow.rowIndex, rowidIndex);

                        // get the rowids of selected rows
                        var selected = $this.jqGrid("getGridParam","selarrrow");

                        for (i = iStart; i <= iEnd; i++)
                        {
                            // if this row isn\'t selected, then toggle it.
                            // jqgrid will select the clicked on row, so just ignore it.
                            // note that we still go <= iEnd because we don\'t know which is start or end.
                            if(selected.indexOf(rows[i].id) < 0 && i != rowidIndex)
                            {
                                // true is to trigger onSelectRow event, which you may not need
                                $this.jqGrid("setSelection", rows[i].id, true);
                            }
                        }
                    }

                    // clear text selection (needed in IE)
                    if(document.selection && document.selection.empty)
                    {
                        document.selection.empty();
                    }
                    else if(window.getSelection)
                    {
                        window.getSelection().removeAllRanges();
                    }
                }
                return true;
            },
            beforeRequest: function()
            {
                var filters = $("#'.$this->id.'").jqGrid("getGridParam", "postData").filters;
                if(filters)
                {
                    // remove quicksearch text field and restore grid search url
                    $("#navigate-quicksearch").val("");
                    $("#'.$this->id.'").jqGrid("setGridParam", { url: "'.$this->url.'" });
                }
            }
		});';

		// enable keyboard navigation
		$html[] = '$("#'.$this->id.'").jqGrid("bindKeys", 
		{ 
			onEnter: function(rowid) { '.$this->id.'_dclick(rowid, null, null, null); }
		});';
						
		$html[] = '});';		
		
		if(!empty($this->edit_url) && ($this->click_action=="redirect"))
		{
			$html[] = 'function '.$this->id.'_dclick(rowid, iRow, iCol, e)';
			$html[] = '{';
			$html[] = '		navigate_unselect_text();';
			$html[] = ' 	var data = $("#'.$this->id.'").getRowData(rowid); ';
            //$html[] = '	console.log(rowid); ';
            //$html[] = '	console.log(data);';
            $html[] = '     if(e && e.ctrlKey) ';
            $html[] = ' 	{   window.open("'.$this->edit_url.'" + data.'.$this->edit_index.');  }';
            $html[] = ' 	else';
			$html[] = ' 	{   window.location.href = "'.$this->edit_url.'" + data.'.$this->edit_index.';  }';
			//$html[] = ' 	window.location.href = "'.$this->edit_url.'" + rowid;';
			//$html[] = ' 	window.location.href = "'.$this->edit_url.'" + $(this).getCol(1)[iRow];';	// we catch the ID from the first column
			//$html[] = ' 	window.location.href = "'.$this->edit_url.'" + data.id;';	// we catch the ID from the first column
			$html[] = '}';				
		}
		else if(!empty($this->edit_url) && ($this->click_action="jqform"))
		{
			$html[] = 'function '.$this->id.'_dclick(rowid, iRow, iCol, e)';
			$html[] = '{';
			$html[] = '		navigate_unselect_text();	';			
			$html[] = '		$("'.$this->id.'").jqGrid("editGridRow", rowid, {height:280,reloadAfterSubmit:false}); ';
			$html[] = '}';			
		}
        else if(!empty($this->click_action))
		{
			$html[] = 'function '.$this->id.'_dclick(rowid, iRow, iCol, e)';
			$html[] = '{';
			$html[] = '		navigate_unselect_text();	';
			$html[] = '		if('.$this->click_action.') '.$this->click_action.'(rowid); ';
			$html[] = '}';
		}

		if(empty($this->quicksearch_url))
		{
	        $this->quicksearch_url = '?fid='.$this->default_fid.'&act=1&_search=true&quicksearch=';
	        if(strpos($this->default_fid, 'ext_')===0)
	            $this->quicksearch_url = '?fid='.$this->default_fid.'&mode=json&_search=true&quicksearch=';
		}

		$html[] = 'function navitable_quicksearch(text)';
		$html[] = '{';
		$html[] = '     $("#'.$this->id.'").jqGrid("setGridParam", { postData: { filters: null} });'; // remove current search filters
		$html[] = '		$("#'.$this->id.'").jqGrid("setGridParam", { url: "'.$this->quicksearch_url.'" + text });';
		$html[] = '		$("#'.$this->id.'").trigger("reloadGrid");';
        // disable original search URL to allow table sorting based on quicksearch
		//$html[] = '		$("#'.$this->id.'").jqGrid("setGridParam", { url: "'.$this->url.'" });';
		$html[] = '}';

		$html[] = "
            var navigate_grid_default_row_background = '#F9FAFB';

			function navigate_grid_bind_color_swatch_notes()
			{
				// update row background color
			    $('.grid_color_swatch').each(function()
			    {
			        if($(this).attr('ng-background') != '')
			        {
			            var tr = $(this).parent().parent();
                        var ngbk = $(this).attr('ng-background');
			            $(tr).find('td').each(function(i, el)
			            {
    		                $(el).css('background', ngbk);
			            });
			        }
			        else
			        {
			          $(tr).find('td').each(function(i, el)
			          {
			            $(el).css('background', navigate_grid_default_row_background);
                      });
			        }
			    });

			    $('.navigate_grid_notes_span').on('click', function()
			    {
			        $(this).next().trigger('click');
			    });

			    // prepare grid notes button
                $('.grid_note_edit').each(function()
			    {
			        if($(this).attr('ng-notes')==0)
			        {
			          $(this).css('opacity', 0.5);
			        
			          $(this).off('mouseenter').off('mouseleave').hover(
			              function()
                          { $(this).css('opacity', 1); },
                          function()
                          { $(this).css('opacity', 0.5); }
                      );
                    }
                    else
                      $(this).css('opacity', 1);

                    $(this).off('click').on('click', function()
                    {
                        var row_id = $(this).parent().parent().attr('id');
                        // open item notes dialog
                        $('<div><img src=\"".NAVIGATE_URL."/img/loader.gif\" style=\" top: 162px; left: 292px; position: absolute; \" /></div>').dialog({
                            modal: true,
                            width: 600,
                            height: 400,
                            title: '".t(168, "Notes")."',
                            open: function(event, ui)
                            {
                                var container = this;
                                $.getJSON('".$grid_notes_control."grid_notes_comments&id=' + row_id, function(data)
                                {
                                    $(container).html('".
                                        '<div><form action="#" onsubmit="return false;" method="post"><span class=\"grid_note_username\">'.$user->username.'</span><button class="grid_note_save">'.t(34, 'Save').'</button><br /><textarea id="grid_note_comment" class="grid_note_comment"></textarea></form></div>'
                                    ."');

                                    for(d in data)
                                    {
                                    
                                        var note = '<div class=\"grid_note ui-corner-all\" grid-note-id=\"'+data[d].id+'\" style=\" background: '+data[d].background+'; \">';
                                        note += '<span class=\"grid_note_username\">'+data[d].username+'</span>';
                                        note += '<span class=\"grid_note_remove\"><img src=\"".NAVIGATE_URL."/img/icons/silk/decline.png\" /></span>';
                                        note += '<span class=\"grid_note_date\">'+data[d].date+'</span>';
                                        note += '<span class=\"grid_note_text\">'+data[d].note+'</span>';
                                        note += '</div>';

                                        $(container).append(note);
                                    }

                                    // TO DO: add color picker selector when adding a comment
                                    // $('.grid_note_save').after('<div style=\"float: right;\"></div>');

                                    $(container).find('.grid_note_remove').on('click', function()
                                    {
                                        var grid_note = $(this).parent();

                                        $.get(
                                            '".$grid_notes_control."grid_note_remove&id=' + $(this).parent().attr('grid-note-id'),
                                            function(result)
                                            {
                                                if(result=='true')
                                                {
                                                    $(grid_note).fadeOut();
                                                    $('#".$this->id."').trigger('reloadGrid');
                                                }
                                            }
                                        );
                                    });

                                    $(container).find('.grid_note_save').button({
                                        icons: { primary: 'ui-icon-disk' }
                                    }).on('click', function()
                                    {
                                        $.post('".$grid_notes_control."grid_notes_add_comment',
                                        {
                                            comment: $(container).find('.grid_note_comment').val(),
                                            id: row_id,
                                            background: $('#' + row_id).find('.grid_color_swatch').attr('ng-background')
                                        },
                                        function(result)
                                        {
                                            if(result=='true') // reload dialog and table
                                            {
                                                $(container).parent().remove();
                                                $('#' + row_id).find('.grid_note_edit').trigger('click');
                                                $('#".$this->id."').trigger('reloadGrid');
                                            }
                                        });
                                    });
                                });
                            }
                        });
                    });

			    });


			    // click event on color swatch icon
			    $('.grid_color_swatch').off('click').on('click', function(e)
			    {
			        e.stopPropagation();

			        var tr = $(this).parent().parent();
			        var original_background = $(tr).find('td:first').css('background-color');

					// assign the item id of the row to the floating div
					$('#navigate_grid_color_picker span').attr('data-item-id', $(tr).attr('id'));

			        $('#navigate_grid_color_picker').css({
			            left: $(this).offset().left - $('#navigate_grid_color_picker').width() + 2,
			            top: $(this).offset().top - $('#navigate_grid_color_picker').height() + 3
			        }).show();

			        $('#navigate_grid_color_picker span').off('mouseenter').off('mouseleave').hover(
                        function()
                        {
                            var new_background = $(this).css('background-color');

                            if(new_background=='rgb(255, 255, 255)')
                                new_background = '';

                            $(tr).find('td').css('background', new_background);
                        },
                        function()
                        {
                            $(tr).find('td').css('background', original_background);
                        }
                    );

			        $('#navigate_grid_color_picker span').off('click').on('click', function(e)
			        {
			            $('#navigate_grid_color_picker').hide();

						// retrieve the grid row by the item id previously saved
			            var tr = $('tr[id=\"'+$(this).attr('data-item-id')+'\"]');

			            var new_background = $(this).css('background-color');

			            if(new_background=='rgb(255, 255, 255)')
			                new_background = navigate_grid_default_row_background;

			            // now save this preference
			            $.ajax({
			               type: 'POST',
			               url: '".$grid_notes_control."grid_note_background',
			               data: {
			                   id: $(this).attr('data-item-id'),
			                   background: new_background
			               },
			               success: function(msg)
			               {
			                  $(tr).find('img.grid_color_swatch').attr('ng-background', new_background);
			                  $(tr).find('td').animate(
			                    {'background-color': new_background},
			                    500,
			                    'swing',
			                    function()
			                    {
			                        $(this).trigger('mouseleave');
			                    }
                              );
			               }
			             });
			        });

                    var remove_grid_color_picker = function(e)
                    {
                          if($(e).target != $('#navigate_grid_color_picker span'))
                          {
                            $('#navigate_grid_color_picker').hide();
                            $(window).off('click', remove_grid_color_picker);
                          }
                    };

			        $(window).off('click', remove_grid_color_picker).on('click', remove_grid_color_picker);

			    }).css('cursor', 'pointer');

			    // protect row selected color
			    $('.grid_color_swatch').parent().parent().each(function(i, row)
			    {
                      // for each row, clear the custom background color on mouse enter
                      $(row).off('mouseenter').on('mouseenter', function()
                      {
                          $(this).find('td').css('background', 'transparent');
                      });

                      // for each row, restore the custom background color unless row is selected
                      $(row).off('mouseleave').on('mouseleave', function()
                      {
                          if(!$(row).hasClass('ui-state-highlight'))
                          {
                              var color_swatch_background = $(this).find('.grid_color_swatch').attr('ng-background');
                              if(color_swatch_background != '')
                              {
                                  $(row).find('td').css('background', color_swatch_background);
                              }
                              else
                              {
                                  $(row).find('td').css('background', 'transparent'); //navigate_grid_default_row_background);
                              }
                          }
                      });
			    });
			};
		";
		
		$html[] = '</script>';

        $html[] = '
            <div id="navigate_grid_color_picker" class="ui-corner-all">
                <span style="background-color: #e6ecff;"></span>
                <span style="background-color: #dff2f2;"></span>
                <span style="background-color: #dff2df;"></span>
                <span style="background-color: #ffffdf;"></span>
                <span style="background-color: #ffecdf;"></span>
                <span style="background-color: #f2dfec;"></span>
                <span style="background-color: #ece6f2;"></span>
                <span style="background-color: #e6ece6;"></span>
                <span style="background-color: #f2f2df;"></span>
                <span style="background-color: #fff0df;"></span>
                <span style="background-color: #fceddf;"></span>
                <span style="background-color: #ffffff;"></span>
            </div>
        ';
		
		
		return implode("\n", $html);	
	}
}

?>