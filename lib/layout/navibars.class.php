<?php
class navibars
{
	public $elements;
	
	public function __construct()	
	{
		$this->elements = array();
	}
	
	function title($text)
	{
		$this->elements['title'] = '<div class="ui-corner-all" id="navigate-content-title">'.$text.'</div>';
	}

	function add_actions($links)
	{		
		if(is_array($links)) 
		{
			$search_form_pos = array_search('search_form', $links);
			
			if($search_form_pos !== false)
			{
				$links[$search_form_pos] = array();
				
				// if we are showing a list (act=0), make an ajax call
				// else redirect browser to the list and make the search after load
				
				if(empty($_REQUEST['act']))	// list!				
				{
					$links[$search_form_pos][] = '<img onclick="$(this).next().triggerHandler(\'submit\');" height="16" align="absmiddle" width="16" src="img/icons/silk/zoom.png"></a>';
					// $links[$search_form_pos][] = '<form method="GET" action="#" onsubmit=" var search = $(\'#navigate-quicksearch\').val(); $(\'#\' + $(\'.ui-jqgrid\').attr(\'id\').substr(5)).jqGrid(\'setGridParam\', { url: \'?fid='.$_REQUEST['fid'].'&act=1&_search=true&quicksearch=\' + search }).trigger(\'reloadGrid\'); return false;">';
					$links[$search_form_pos][] = '<form method="GET" action="#" onsubmit=" navitable_quicksearch($(\'#navigate-quicksearch\').val()); return false;">';
				}
				else	// other screen
				{
					$links[$search_form_pos][] = '<img onclick="$(this).next().trigger(\'submit\');" height="16" align="absmiddle" width="16" src="img/icons/silk/zoom.png"></a>';					
					$links[$search_form_pos][] = '<form method="POST" action="?fid='.$_REQUEST['fid'].'&act=0&quicksearch=true">';
				}
					
				$links[$search_form_pos][] = '	<input type="hidden" name="fid" value="'.$_REQUEST['fid'].'" />';
				$links[$search_form_pos][] = '	<input type="hidden" name="act" value="0" />';				
				$links[$search_form_pos][] = '	<input type="text" id="navigate-quicksearch" name="navigate-quicksearch" size="16" onclick="if(this.value==\''.t(41, 'Search').'...\') this.value=\'\';" value="'.t(41, 'Search').'...">';
				$links[$search_form_pos][] = '</form>';
            
				$links[$search_form_pos] = implode("\n", $links[$search_form_pos]);
			}
			$links = implode("\n", $links);
		}
		
		$this->elements['actions'][] = '<div class="ui-corner-all">'.$links.'</div>';	
	}

	function form($tag="", $action="")
	{
		if(empty($action))
			$action = $_SERVER['QUERY_STRING'];
			
		if(empty($tag))
			$tag = '<form name="navigate-content-form" action="?'.$action.'" method="post" enctype="multipart/form-data">';
		
		$this->elements['form'] = $tag;
	}	

	function add_tab($name, $href='')
	{
		$this->elements['tabs'][] = array(	'name' => $name, 
											'href' => $href);
		
		return count($this->elements['tabs']) - 1;	
	}
	
	function add_content($html)
	{
		$this->elements['html'][] = $html;	
	}
	
	function add_tab_content($content)
	{
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = $content;
	}
	
	function add_tab_content_row($content, $id="")
	{
		if(is_array($content)) $content = implode("\n", $content);
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '<div class="navigate-form-row" id="'.$id.'">';
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = $content;
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '</div>';		
	}		
	
	function add_tab_content_panel($title, $content, $id="", $width="90%", $height="200px")
	{
		if(is_array($content)) $content = implode("\n", $content);
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '<div class="ui-widget-content ui-corner-all" style=" float: left; margin-right: 10px; margin-bottom: 5px; width: '.$width.'; height: '.$height.' " id="'.$id.'">';
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '<div class="ui-state-default ui-corner-top" style=" padding: 5px; ">'.$title.'</div>';	// ui-tabs-selected ui-state-active
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '<div class="" style=" height: '.(intval($height) - 32).'px; overflow: auto; ">'.$content.'</div>';
		$this->elements['tabs_content'][(count($this->elements['tabs'])-1)][] = '</div>';				
	}
	
	
	function generate_tabs()
	{
		global $layout;
		
		$tabs = $this->elements['tabs'];
		
		$buffer[] =  '<div id="navigate-content-tabs" style=" visibility: hidden; ">';
		
			$buffer[] =  '<ul>';
			
			for($t=0; $t < count($tabs); $t++)
			{
				if(empty($tabs[$t]['href']))
					$buffer[] = '<li><a href="#navigate-content-tabs-'.($t+1).'">'.$tabs[$t]['name'].'</a></li>';
				else
					$buffer[] = '<li><a href="'.$tabs[$t]['href'].'">'.$tabs[$t]['name'].'</a></li>';
			}
			$buffer[] =  '</ul>';			
			
			for($t=0; $t < count($tabs); $t++)
			{
				if(!empty($tabs[$t]['href'])) continue;
				
				$buffer[] = '<div id="navigate-content-tabs-'.($t+1).'">';
				
				if(!empty($this->elements['tabs_content'][$t]))
					$buffer[] = implode("\n", $this->elements['tabs_content'][$t]);
					
				$buffer[] = '</div>';				
			}			
		
		$buffer[] =  '</div>';	
		
        $layout->add_script('
            $(window).on("load", function()
            {
                $("#navigate-content-tabs").tabs({
                    '.(!empty($_REQUEST['tab'])? 'selected: '.$_REQUEST['tab'].',' : '').'
                    select: function()  // DEPRECATED AND REMOVED IN jquery ui 1.10
                    {
                        $(navigate_codemirror_instances).each(function() { this.refresh(); } );
                    },
                    beforeActivate: function() // NEW WAY from JQUERY UI 1.10
                    {
                        setTimeout(function() {
                            $(navigate_codemirror_instances).each(function() { this.refresh(); } );
                        }, 200);
                    }
                });
                $("#navigate-content-tabs").css({"visibility": "visible"});
            });
        ');

		return implode("\n", $buffer);	
	}
	
	function generate()
	{
		$buffer[] = $this->elements['title'];
		
		if(!empty($this->elements['actions']))
		{
			$buffer[] = '<div id="navigate-content-actions">';
			$buffer[] = implode("\n", $this->elements['actions']);
			$buffer[] = '</div>';
		}
		
		$buffer[] = '<div id="navigate-content-top-spacer"></div>';
		
		if(!empty($this->elements['form']))
			$buffer[] = $this->elements['form'];

		if(!empty($this->elements['tabs']))
			$buffer[] = $this->generate_tabs();
			
		if(!empty($this->elements['form']))
			$buffer[] = '</form>';			
			
		if(!empty($this->elements['html']))
			$buffer[] = implode("\n", $this->elements['html']);
		
		return implode("\n", $buffer);	
	}
}
?>