<?php

class naviorderedtable
{
	public $id;
	public $width;
	public $input_id;
	public $headerColumns;
	public $rows;
	public $dblclick_callback;
    public $reorder_callback;
	
	public function __construct($id=null)
	{
        if(empty($id))
            $id = uniqid('naviorderedtable_');

		$this->id = $id;
		$this->rows = array();
	}
	
	public function setWidth($width)
	{
		$this->width = $width;	
	}
	
	public function setHiddenInput($input_id)
	{
		$this->input_id = $input_id;
	}
	
	public function setDblclickCallback($cback)
	{
		$this->dblclick_callback = $cback;
	}

    public function setReorderCallback($cback)
	{
		$this->reorder_callback = $cback;
	}
	
	public function addHeaderColumn($text, $width, $searchable=false)
	{
		if($searchable)
			$this->headerColumns[] ='<th width="'.$width.'"><span><img src="img/icons/silk/zoom.png" align="right" class="naviorderedtable-search" />'.$text.'</span></th>';
		else
			$this->headerColumns[] ='<th width="'.$width.'">'.$text.'</th>';
	}
	
	public function addRow($id, $columns)
	{
		$this->rows[] = '<tr id="'.$id.'">';
		
		foreach($columns as $col)
		{
		    if(!isset($col['style']))
                $col['style'] = "";

			@$this->rows[] = '<td align="'.$col['align'].'" style="'.$col['style'].'">'.$col['content'].'</td>';
		}
		
		$this->rows[] = '</tr>';				
	}
	
	public function generate()
	{
		global $layout;	
		
		$table = array();
		$table[] = '<table id="'.$this->id.'" class="box-table" width="'.$this->width.'">';
        $table[] = '<thead>';
        $table[] = '<tr class="nodrop nodrag">';
		$table[] = implode("\n", $this->headerColumns);
		$table[] = '</tr>';
        $table[] = '</thead>';
		
		$table[] = '<tbody>';		
		
		$table[] = implode("\n", $this->rows);
		
		$table[] = '</tbody>';		
	
		$table[] = '</table>';
		
		$table = implode("\n", $table);	
		
		$layout->add_script('
			$("#'.$this->id.'").tableDnD(
			{
				onDrop: function(table, row)
				{
					navigate_naviorderedtable_'.$this->id.'_reorder();
				}
			});

            $("#'.$this->id.'").on("mouseup", function()
				{
					navigate_naviorderedtable_'.$this->id.'_reorder();
				}
			);
			
			$("#'.$this->id.' img.silk-zoom").css({cursor: "pointer"}).on("click", function()
			{
                var column = $(this).parent().parent().prevAll().length;
				$(this).parent().hide();
				$(this).parent().parent().append(\'<input type="text" name="naviorderedtable-filter" value="" />\');
				$(this).parent().parent().find("input").css({width: $(this).parent().parent().attr("width") - 50 }).bind("keyup", function()
				{
					navigate_naviorderedtable_'.$this->id.'_search($(this), column);
				});
				$(this).parent().parent().find("input").focus();
			});
			
			function navigate_naviorderedtable_'.$this->id.'_reorder()
			{
				var trs = $("#'.$this->id.'").find("tr");
				var ids = [];
				for(i in trs)
				{
					if(!trs[i] || !trs[i].id || trs[i].id=="") continue;
					ids.push(trs[i].id);	
				}

				$("#'.$this->input_id.'").val(phpjs_implode("#", ids));
				'.(empty($this->reorder_callback)? '' : $this->reorder_callback.'(ids);').'
			}
			
			
			function navigate_naviorderedtable_'.$this->id.'_search(element, column)
			{
				var text = $(element).val().toLowerCase();

				// hide or show rows				
				$("#'.$this->id.'").find("tr").not(":first").each(function()
				{
					var tr_text = $(this).find("td").eq(column).text().toLowerCase();
					if(text=="" || tr_text.indexOf(text) >= 0)
						$(this).show();
					else
						$(this).hide();
				});
			}
		');

		if(!empty($this->dblclick_callback))
		{
			$layout->add_script('$("#'.$this->id.'").find("tr").bind("dblclick", function()
			{
				'.$this->dblclick_callback.'(this);
			});');		
		}
		
		return $table;			
	}
}

?>