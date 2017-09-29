<?php

class navigrid
{	
	public $id;
	public $items;
	public $thumbnail_width;
	public $thumbnail_height;	
	public $item_width;
	public $item_height;		
	public $navigrid_header;

    public $highlight_on_click;
    public $dblclick_callback;
	
	public function __construct($id)	
	{
		$this->id = $id;	
		$this->thumbnail_width = 64;
		$this->thumbnail_height = 64;	
		$this->item_width = 96;
		$this->item_height = 96;
        $this->highlight_on_click = true;
        $this->dblclick_callback = '';
	}
	
	public function items($items)
	{
		$this->items = $items;	
	}
	
	public function thumbnail_size($width=64, $height=64)
	{
		$this->thumbnail_width = $width;
		$this->thumbnail_height = $height;
	}
	
	public function item_size($width=96, $height=96)
	{
		$this->item_width = $width;
		$this->item_height = $height;
	}
	
	public function set_header($html)
	{
		$this->navigrid_header = $html;	
	}

	public function generate()
	{
		global $layout;
		global $website;

		$html = array();
		
		$html[] = '<div class="navigate-content-safe ui-corner-all" id="navigate-content-safe">';
		
		$html[] = '<div class="navigrid" id="'.$this->id.'">';

		if(!empty($this->navigrid_header))
			$html[] = $this->navigrid_header;
	
		$html[] = '<div class="navigrid-items">';		
		
		if(empty($this->items)) $this->items = array();
				
		foreach($this->items as $item)
		{
			if(empty($item['thumbnail']))
				$item['thumbnail'] = NAVIGATE_URL.'/img/transparent.gif';
			
			$html[] = '<div class="navigrid-item ui-corner-all" id="item-'.$item['id'].'" style=" width: '.$this->item_width.'px; height: '.$this->item_height.'px; ">';		
			$html[] = $item['header'];
			$html[] = '		<img src="'.$item['thumbnail'].'"  width="'.$this->thumbnail_width.'" height="'.$this->thumbnail_height.'" title="'.$item['description'].'"  />';
			$html[] = '		<div class="navigrid-item-name">'.$item['name'].'</div>';
			$html[] = $item['footer'];
			$html[] = '</div>';
		}

		$html[] = '<div class="clearer">&nbsp;</div>';

		$html[] = '</div>';

		$html[] = '</div>';
		
		$html[] = '</div>';
		
		
		$html[] = '<script language="javascript" type="text/javascript">';	

        if($this->highlight_on_click)
        {
            $html[] = '$(".navigrid-items").children().on("click", function()
                       {
                            $(".navigrid-items div").removeClass("ui-selectee ui-selected");
                            $(".navigrid-items img").removeClass("ui-selectee ui-selected");
                            $(this).addClass("ui-selected");
                       });';
        }

        if(!empty($this->dblclick_callback))
        {
            $html[] = '$(".navigrid-item").on("dblclick", function()
                       {
                            '.$this->dblclick_callback.'(this);
                       });';
        }
/*
		$html[] = '$(".navigrid-items").selectable(
					{
						distance: 10,
						selecting: function(event, ui)
						{
							$(".navigrid-back").removeClass("ui-selectee ui-selected");	
							$(this).selectable("refresh");
						},
						selected: function(event, ui)	
						{
							$(".navigrid-back").removeClass("ui-selectee ui-selected");
							$(".navigrid-back").children().removeClass("ui-selectee ui-selected");							
							$(".clearer").removeClass("ui-selectee ui-selected");
							navigrid_selected = $(".ui-selected").parent().not("div.navibrowse-items");
						}
					});';
*/
		$html[] = '</script>';

		return implode("\n", $html);	
	}
}

?>