<?php
class navitree
{
	public $id;
	public $url;
	public $addUrl;
	public $orderUrl;
	public $hierarchy;
	public $columns;
	public $treeColumn;
    public $showLanguages;

	public function __construct($id)	
	{
		$this->id = $id;
		$this->treeColumn = 0;
		$this->initialState = 'collapsed';
	}

	public function setState($state)
	{
		$this->initialState = $state; // collapsed | expanded
	}

	public function setURL($url)
	{
		$this->url = $url;
	}
	
	public function addURL($url)
	{
		$this->addUrl = $url;
	}	
	
	public function orderURL($url)
	{
		$this->orderUrl = $url;
	}		
	
	public function setData($hierarchy)
	{
		$this->hierarchy = $hierarchy; // see below for an example
	}
	
	public function setColumns($cols)
	{
		// array (	0=> array(	name => 'header title', property => 'node property', type => 'text', width => '50%', align => 'left', options => array() ) )
		// type: (empty) text => text, count => count array elements, boolean => image, option => 
		$this->columns = $cols;
	}

    public function setLanguages($languages_array)
    {
        $this->showLanguages = $languages_array;
    }

	public function nodeToRow($node, $parent=-1)
	{
		$html = array();
		
		if($parent > -1)
			$html[] = '<tr id="node-'.$node->id.'" class="child-of-node-'.$parent.' droppable">';
		else
		{
			$html[] = '<tr id="node-'.$node->id.'" class="droppable">';	
		}
		
		foreach($this->columns as $col)
		{		
			$treecolumn = false;
			
			if($this->columns[$this->treeColumn]==$col) 
				$treecolumn = true;
		
			if($node->{$col['property']}===NULL && strpos($col['property'], 'dictionary|')===false)
			{
				$html[] = '	<td align="'.$col['align'].'">&nbsp;</td>';	
				continue;	
			}
		
			switch($col['type'])
			{
				case 'boolean':
					if($node->{$col['property']}=='true' || $node->{$col['property']}==1)
						$html[] = '	<td align="'.$col['align'].'"><img src="img/icons/silk/accept.png" /></td>';	
					else
						$html[] = '	<td align="'.$col['align'].'"><img src="img/icons/silk/cancel.png" /></td>';						
					break;				
				
				case 'count':
					$html[] = '	<td align="'.$col['align'].'">'.count($node->{$col['property']}).'</td>';	
					break;
					
				case 'option':
					foreach($col['options'] as $key => $value)
					{
						if(	$node->$col['property'] == $key )
							$html[] = '	<td align="'.$col['align'].'">'.$value.'</td>';	
					}
					break;
									
				case 'text':
				default:
                    $value = '';
                    if(strpos($col['property'], 'dictionary|')!==false)
                    {
                        $dictionary_value_name = str_replace("dictionary|", "", $col['property']);
                        foreach($node->dictionary as $lname => $ltexts)
                        {
                            if(!in_array($lname, $this->showLanguages)) // hide this language
                                continue;
                            else if($this->showLanguages[0]==$lname)    // default language
                                $value .= '<span class="navitree-text" language="'.$lname.'">'.$ltexts[$dictionary_value_name].'</span>';
                            else
                                $value .= '<span class="navitree-text" style="display: none;" language="'.$lname.'">'.$ltexts[$dictionary_value_name].'</span>';
                        }
                    }
                    else
                        $value = $node->$col['property'];

					if($treecolumn)
					{
						if($node->parent < 0)	// website item
						{
							$html[] = '	<td align="'.$col['align'].'"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$value.'</td>';
						}
						else
						{						
							if(count($node->children) > 0)
								$html[] = '	<td align="'.$col['align'].'"><img src="img/icons/silk/folder.png" align="absmiddle" /> '.$value.'</td>';
							else
								$html[] = '	<td align="'.$col['align'].'"><img src="img/icons/silk/page_white.png" align="absmiddle" /> '.$value.'</td>';
						}
					}
					else
						$html[] = '	<td align="'.$col['align'].'">'.$value.'</td>';
					break;
			}
		}
		
		$html[] = '</tr>';
		
		for($c=0; $c < count($node->children); $c++)
		{
			$html[]	= $this->nodeToRow($node->children[$c], $node->id);
		}
		
		return implode("\n", $html);
	}
	
	public function setTreeColumn($index)
	{
		$this->treeColumn = $index;	
	}
	
	public function generate()
	{
		$html = array();
		
		$html[] = '<table id="'.$this->id.'" cellspacing="1px" class="treeTable" style="visibility: hidden;">';
		$html[] = '<thead>';
		$html[] = '<tr>';
		
		foreach($this->columns as $col)
		{
			if(!empty($col['align']))  $col['align'] = 'text-align: '.$col['align'].';';
			if(!empty($col['width']))  $col['width'] = 'width: '.$col['width'];			
			$html[] = '<th class="ui-state-default ui-th-column" style="'.$col['align'].' '.$col['width'].'">'.$col['name'].'</th>';
		}
		
		$html[] = '</tr>';
		$html[] = '</thead>';
		$html[] = '<tbody>';
				
		foreach($this->hierarchy as $node)
		{
			$html[] = $this->nodeToRow($node);
		}
				
		$html[] ='	</tbody>
					</table>
				';	
	
		$html[] = '<script language="javascript" type="text/javascript">';
		$html[] = '$(window).on("load", function() { ';

		$html[] = '	$("#'.$this->id.'").treeTable(
					{
					  initialState: "'.$this->initialState.'",
					  treeColumn: '.$this->treeColumn.'
					}).css("visibility", "visible");	
					
					$("#'.$this->id.' tr").eq(1).expand();
				  ';
		
		$html[] = '	$("table#'.$this->id.' tbody tr").bind("mouseover", function() 
					{
						if($(this).hasClass("ui-state-highlight")) return true;
						$("tr.ui-state-highlight").removeClass("ui-state-highlight"); // Deselect currently selected rows
						$(".table_adder").remove();
						$(".table_reorder").remove();					  
						$(this).addClass("ui-state-highlight");	  
						if(navitree_mode=="reorder") return true;
											
						$(this).find("td").eq(1).append("<a href=\"'.$this->addUrl.'" + $(this).find("td:first").html() + "\" class=\"table_adder\"><img src=\"img/icons/silk/add.png\" /></a>");
						
						if($(this).hasClass("parent"))
						{						
							$(this).find("td").eq(1).append("<a href=\"#\" class=\"table_reorder\" onclick=\"navitree_reorder($(this).parent());\"><img src=\"img/icons/silk/arrow_switch.png\" /></a>");
						}
					});';

		$html[] = '	$("table#'.$this->id.' tbody tr").not(":first").bind("dblclick", function() 
					{
						if(navitree_mode=="reorder") return true;
						window.location.href = "'.$this->url.'" + $(this).find("td:first").html();
					}); ';
										
					/*
		$html[] = '	$("table#'.$this->id.' tbody tr span").bind("mousedown", function() 
					{
						$($(this).parents("tr")[0]).trigger("mousedown");
					});';		*/
		
		// left arrows adjustment
		
		$html[] = ' $(".treeTable").bind("click", function() { $(this).find(".expander").not(":first").css({"margin-left": "-19px", "padding-left": "19px"}); });';			
		
		// keep open/close branch status via cookie
		$html[] = '		
					function navitree_save_branch_status()
					{
						var expanded_ids = [];
						$("#'.$this->id.'").find("tr.expanded").each(function()
						{
							expanded_ids.push($(this).attr("id"));
						});
						
						$.setCookie("navigate-tree-'.$this->id.'", expanded_ids);
					}
					
					function navitree_load_branch_status()
					{
						navitree_cookie_update = false;
						var expanded_ids = $.cookie("navigate-tree-'.$this->id.'");
						
						if(!expanded_ids)
							$(".treeTable").trigger("click");
						else
						{
							// close all
							$("#'.$this->id.'").find("tr.expanded").find(".expander").trigger("click");				
							// expand the branches previously open
							for(i in expanded_ids)
							{
								$("#"+expanded_ids[i]).find(".expander").trigger("click");
							}
						}
						navitree_cookie_update = true;
					}
					
					$(".expander").live("click", function()
					{
						if(navitree_cookie_update)
							setTimeout(navitree_save_branch_status, 200);
					});

					navitree_load_branch_status();
				';		
					
		$html[] = '});';	
						
		$html[] = ' var navitree_mode = "ready"; ';
		$html[] = ' var navitree_order_trigger = 0; ';
		$html[] = ' var navitree_order = ""; ';
		$html[] = ' var navitree_cookie_update = false; ';		
						
		$html[] = ' function navitree_reorder(el)
					{
						el = $(el).parent("tr");
						$("#'.$this->id.' tr").hide();
						$("#'.$this->id.' tr:first").show();

						$(".expander").hide();
						$("tr.ui-state-highlight").removeClass("ui-state-highlight"); // Deselect currently selected rows
						
						$("#'.$this->id.' tr.child-of-node-" + $(el).find("td:first").html()).show();
						
						navitree_mode = "reorder";
						navitree_order_trigger = $(el).find("td:first").html();
						navitree_reorder_serialize();
						
						var text = "'.t(72, 'Drag any row to assign priorities').'.<br />";
							text+= "<a href=\"#\" onclick=\"navitree_reorder_submit();\" style=\"text-decoration:none; display: block; text-align: right; \"><img height=\"16\" align=\"absbottom\" width=\"16\" src=\"img/icons/silk/accept.png\"> '.t(34, 'Save').'</a>";
						
						$.jGrowl.defaults.position = "center";
						$.jGrowl(text, {
						    sticky: true,
                            open: function()
                            {
                                setTimeout(function() { $(".jGrowl-notification").css({"background-repeat": "repeat", "width": "400px"}); }, 50);
                            },
							close: function()
							{
							    navitree_reorder_cancel();
                            }
                        });
						$("#jGrowl").css({"top": "123px"});
						
						$("#'.$this->id.'").tableDnD(
						{
							onDrop: navitree_reorder_serialize
						});															
						
					}
					
					function navitree_reorder_serialize()
					{
						navitree_order = "";
								
						$("#'.$this->id.' tr.child-of-node-" + navitree_order_trigger).each(function()
						{
							navitree_order += $(this).find("td:first").html() + "#";
						});						
					}
					
					function navitree_reorder_submit()
					{
						$.ajax(
						{
						  type: "POST",
						  url: "'.$this->orderUrl.'",
						  data: { 	parent: navitree_order_trigger, 
						  			children_order: navitree_order 
								},
						  success: function(data)
						  {
							  if(!data.error)  navitree_reorder_cancel();
							  else			   $(".message").html(data.error);

						  },
						  dataType: "json"
						});
					}
					
					function navitree_reorder_cancel()
					{
						window.location.reload();	
					}
				';		
		
		$html[] = '</script>';		
				
		return implode("\n", $html);	
	}
}
?>