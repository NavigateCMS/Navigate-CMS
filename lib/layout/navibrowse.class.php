<?php
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');

class navibrowse
{
	public $id;
	public $items;
	public $path;
	public $parent;
	public $previous;

	public $empty_file;
	public $empty_folder;
	public $icon_size;
	
	public $url;
	public $onDblClick;
    public $onRightClick;

	public function __construct($id)	
	{
		$this->id = $id;
		$this->empty_file = 'img/icons/ricebowl/mimetypes/mime-empty.png';
		$this->empty_folder = 'img/icons/ricebowl/filesystem/folder_grey.png';		
		$this->icon_size = '64px';
		$this->path = '/';
	}
	
	public function items($items)
	{
		$this->items = $items;	
	}
	
	public function path($path, $parent, $previous)
	{
		$this->path = $path;	
		$this->parent = $parent;
		$this->previous = $previous;
	}
	
	public function setUrl($url)
	{
		$this->url = $url;	
	}
	
	public function onDblClick($callback)
	{
		$this->onDblClick = $callback;	
	}
	
	public function onMove($callback)
	{
		$this->onMove = $callback;	
	}

    public function onRightClick($callback)
    {
        $this->onRightClick = $callback;
    }
	
	public static function mimeIcon($mime, $type='file')
	{
        $empty_file = 'img/icons/ricebowl/mimetypes/mime-empty.png';
        $empty_folder = 'img/icons/ricebowl/filesystem/folder_grey.png';

        /* nonsense in a static function
		if($this)
		{
			$empty_file = $this->empty_file;
			$empty_folder = $this->empty_folder;
		}
        */

		switch($mime)
		{
			case 'folder/back':
				$icon = 'img/icons/ricebowl/filesystem/folder_back.png';			
				break;
			
			case 'folder/images':
				$icon = 'img/icons/ricebowl/filesystem/folder_applications.png';
				break;
				
			case 'folder/audio':
				$icon = 'img/icons/ricebowl/filesystem/folder_music.png';			
				break;			
			
			case 'folder/video':
				$icon = 'img/icons/ricebowl/filesystem/folder_movies.png';			
				break;							
				
			case 'folder/documents':
				$icon = 'img/icons/ricebowl/filesystem/folder_documents.png';			
				break;					

			case 'folder/flash':
				$icon = 'img/icons/ricebowl/filesystem/folder_flash.png';			
				break;	
						
			case 'folder/blue':
				$icon = 'img/icons/ricebowl/filesystem/folder_blue.png';			
				break;			
			
			default:
				switch($type)
				{
					case 'folder':
						$icon = $empty_folder;
						break;	
						
					case 'image':
						$icon = 'img/icons/ricebowl/mimetypes/image.png';
						break;
						
					case 'video':
						$icon = 'img/icons/ricebowl/mimetypes/video.png';
						break;	
						
					case 'audio':
						$icon = 'img/icons/ricebowl/mimetypes/audio.png';
						break;	
						
					case 'document':
						$icon = 'img/icons/ricebowl/mimetypes/wordprocessing.png';					
						break;	
						
					case 'flash':
						$icon = 'img/icons/ricebowl/mimetypes/swf.png';					
						break;								
						
					case 'archive':
						$icon = 'img/icons/ricebowl/mimetypes/zip.png';					
						break;																				
						
					default:
						$icon = $empty_file;
				}
		}
		
		return $icon;
	}
	
	public function generate()
	{
		global $layout;
		global $website;

		$html = array();

        $access = array(
            0 => '', //<img src="img/icons/silk/page_white_go.png" align="absmiddle" title="'.t(254, 'Everybody').'" />',
            1 => '<img src="img/icons/silk/lock.png" align="absmiddle" title="'.t(361, 'Web users only').'" />',
            2 => '<img src="img/icons/silk/user_gray.png" align="absmiddle" title="'.t(363, 'Users who have not yet signed up or signed in').'" />',
            3 => '<img src="img/icons/silk/group_key.png" align="absmiddle" title="'.t(512, "Selected web user groups").'" />'
        );

        $permissions = array(
            0 => '', //'<img src="img/icons/silk/world.png" align="absmiddle" title="'.t(69, 'Published').'" />',
            1 => '<img src="img/icons/silk/world_dawn.png" align="absmiddle" title="'.t(70, 'Private').'" />',
            2 => '<img src="img/icons/silk/world_night.png" align="absmiddle" title="'.t(81, 'Hidden').'" />'
        );


        $html[] = '<div class="navigate-content-safe ui-corner-all" id="navigate-content-safe">';
		
		$html[] = '<div class="navibrowse" id="'.$this->id.'">';
		
		$html[] = '<div class="navibrowse-path ui-corner-all">';
		$html[] = '	<a href="?fid='.$_REQUEST['fid'].'"><img src="img/icons/silk/folder_home.png" width="16px" height="16px" align="absbottom" /> '.t(18, 'Home').'</a>';
		if($this->parent > 0)
			$html[] = '	<a href="?fid='.$_REQUEST['fid'].'&parent='.$this->previous.'"><img src="img/icons/silk/folder_up.png" width="16px" height="16px" align="absbottom" /> '.t(139, 'Back').'</a>';
		$html[] = '	<a href="#" onclick="navibrowse_folder_tree_dialog('.$this->parent.');"><img src="img/icons/silk/application_side_tree.png" width="16px" height="16px" align="absbottom" /> '.t(75, 'Path').': '.$this->path.'</a>';
		$html[] = '</div>';
		
		$html[] = '<div class="navibrowse-items">';		
		
		if($this->parent > 0) // we are on a subfolder, let's include the ".." directory
		{
			$icon = $this->mimeIcon('folder/back');
			$html[] = '<div class="navibrowse-folder navibrowse-back ui-corner-all" id="item-'.$this->previous.'">';
			$html[] = '		<img src="'.$icon.'" width="'.$this->icon_size.'" height="'.$this->icon_size.'" />';	
			$html[] = '		<div class="navibrowse-item-name">'.t(139, 'Back').'</div>';				
			$html[] = '</div>';				
		}
		
		if(empty($this->items))
            $this->items = array();

		foreach($this->items as $item)
		{
			if($item->type == 'image')
				$icon = NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=inline&width='.intval($this->icon_size).'&height='.intval($this->icon_size);
			else
				$icon = $this->mimeIcon($item->mime, $item->type);
			
			if($item->type=='folder')
			{
				$html[] = '<div class="navibrowse-folder ui-corner-all" mime="'.$item->mime.'" id="item-'.$item->id.'">';
				$html[] = '		<img src="'.$icon.'" width="'.$this->icon_size.'" height="'.$this->icon_size.'" />';	
				$html[] = '		<div class="navibrowse-item-name">'.$item->name.'</div>';				
				$html[] = '</div>';	
			}
			else
			{
				$html[] = '<div class="navibrowse-file ui-corner-all" mime="'.$item->mime.'" id="item-'.$item->id.'">';
                $html[] = '     <div class="navibrowse-file-access-icons">'.$permissions[$item->permission].$access[$item->access].'</div>';
				$html[] = '		<img src="'.$icon.'"  width="'.$this->icon_size.'" height="'.$this->icon_size.'" />';
				$html[] = '		<div class="navibrowse-item-name">'.$item->name.'</div>';
				$html[] = '</div>';					
			}
		}

		$html[] = '<div class="clearer">&nbsp;</div>';

		$html[] = '</div>';

		$html[] = '</div>';
		
		$html[] = '</div>';
		
		$hierarchy = file::hierarchy(0);
		$folders_tree = file::hierarchyList($hierarchy, $item->parent);			
		
		$html[] = '
			<div id="navibrowse-folder-tree-dialog">
				<div class="navibrowse_folder_tree" style=" width: 90%; "><img src="img/icons/silk/folder_home.png" align="absmiddle" />  '.t(18, 'Home').$folders_tree.'</div>
			</div>
		';			
		
		$html[] = '<script language="javascript" type="text/javascript">';

//		$html[] = '$(".navibrowse-file, .navibrowse-folder").bind("mouseover", function() { $(this).css("opacity", 0.7); });';
//		$html[] = '$(".navibrowse-file, .navibrowse-folder").bind("mouseout", function() { $(this).css("opacity", 1); });';		
		
		$html[] = '$(".navibrowse-path a").button();';
		
		$html[] = '$(".navibrowse-items").children().bind("click", function(e)
				   {
					   if(e.ctrlKey)
					   {
							// just add or remove the item as selected  
							if($(this).hasClass("ui-selected"))
							{
								$(this).removeClass("ui-selected ui-selectee");
								$(this).children().removeClass("ui-selected ui-selectee");
							}
							else
								$(this).addClass("ui-selected ui-selectee");

							navibrowse_selected = $("div.navibrowse-file.ui-selected, div.navibrowse-folder.ui-selected");
					   }
					   else
					   {
						   // deselect everything except the new selected item
							$(".navibrowse-items div").removeClass("ui-selectee ui-selected");
							$(".navibrowse-items img").removeClass("ui-selectee ui-selected");						
							$(this).addClass("ui-selected ui-selectee");
					   }
					   
					   e.stopPropagation();
						
				   });';
				   
		
		$html[] = '$(".navibrowse-folder").bind("dblclick", function()
				   {
						window.location.href = "'.$this->url.'" + this.id.substring(5);
				   });';

        if(!empty($this->onDblClick))
		    $html[] = '$(".navibrowse-file").bind("dblclick", function()
			    	   {
				    		'.$this->onDblClick.'(this);
				    });';

		$html[] = '$(".navibrowse-items").bind("click", function()
					{
						$(".ui-selectee, .ui-selected").removeClass("ui-selectee ui-selected");
					});';

        if(!empty($this->onRightClick))
            $html[] = '$(".navibrowse-items").children().on("contextmenu", function(e)
			    		{
			    		    if($(this).attr("id")=="item-0")
			    		        return;

                            e.preventDefault();
                            e.stopPropagation();
                            $(".ui-selectee, .ui-selected").removeClass("ui-selectee ui-selected");

			    		    var ev = e;
			    		    var trigger = this;

			    		    setTimeout(function()
			    		    {
				    	        '.$this->onRightClick.'(trigger, ev);
                            }, 150);
					    });';
				   
		$html[] = '$(".navibrowse-items").selectable(
					{
						distance: 10,
						selecting: function(event, ui)
						{
							$(".navibrowse-back").removeClass("ui-selectee ui-selected");	
						},
						selected: function(event, ui)	
						{
							$(".navibrowse-back").removeClass("ui-selectee ui-selected");
							$(".navibrowse-back").children().removeClass("ui-selectee ui-selected");							
							$(".clearer").removeClass("ui-selectee ui-selected");
							navibrowse_selected = $("div.navibrowse-file.ui-selected, div.navibrowse-folder.ui-selected");
						}
					});';							

		$html[] = '
			$(".navibrowse_folder_tree ul:first").kvaTree(
			{
				imgFolder: "js/kvatree/img/",
				autoclose: false,
				dragdrop: false,
				background: "#f2f5f7",
				onDblClick: function(event, node)
				{
					window.location.href = "?fid='.$_REQUEST['fid'].'&parent=" + $(node).attr("value");	
				}
			});		
			
			$("#navibrowse-folder-tree-dialog").hide();
		
			function navibrowse_folder_tree_dialog(current)
		    {
			  $("#navibrowse-folder-tree-dialog").dialog(
			  {
					resizable: true,
					height: 350,
					width: 500,
					modal: true,
					title: "'.t(221, 'Jump to').'...",
					buttons: 
					{
						"'.t(58, 'Cancel').'": function() 
						{
							$(this).dialog("close");
						}
					}
				});
		    }
		';
				
		// drag'n'drop support
		if(!empty($this->onMove))
		{
			$html[] = '
				var navibrowse_selected;
				$(".navibrowse-items").children().not(".navibrowse-back").draggable(
				{
					opacity: 0.8,
					zIndex: 1000,
					revert: true, //"invalid",
					start: function(event, ui)
					{
						if(navibrowse_selected && navibrowse_selected.length < 1)
						{
							$(".navibrowse-items div").removeClass("ui-selectee ui-selected");
							$(".navibrowse-items img").removeClass("ui-selectee ui-selected");							
						}
						$(this).addClass("ui-selected");
						navibrowse_selected = $("div.navibrowse-file.ui-selected, div.navibrowse-folder.ui-selected");

						if(navibrowse_selected.length > 1)
							$(ui.helper).append("<div id=\"navibrowse-item-badge\">" + navibrowse_selected.length + "</div>");
					},
					stop: function()
					{
						navibrowse_selected = $("div.navibrowse-file.ui-selected, div.navibrowse-folder.ui-selected");
						$("#navibrowse-item-badge").remove();
					}
				});
			';
			
			$html[] = '$(".navibrowse-folder").droppable(
					   {
							hoverClass: "ui-selected",
							tolerance: "pointer",
							drop: function(event, ui)
							{
								if(navibrowse_selected!=null && navibrowse_selected.length > 1)
								{									
									var folder = $(this).attr("id").substring(5);									
									var ids = [];
									
									$(navibrowse_selected).each(function()
									{
										if($(this).attr("id").substring(5) != folder)
											ids.push($(this).attr("id").substring(5));
									});
									
									navibrowse_selected = jQuery.grep(navibrowse_selected, function(value) 
									{
										return $(value).attr("id").substring(5) != folder;
									});									
									'.$this->onMove.'(ids, folder, navibrowse_selected);									
								}
								else
								{								
									var folder = $(this).attr("id").substring(5);
									var item = $(ui.draggable).attr("id").substring(5);
									'.$this->onMove.'(item, folder, ui.draggable);
								}
								
								$(".navibrowse-items div").removeClass("ui-selectee ui-selected");
								$(".navibrowse-items img").removeClass("ui-selectee ui-selected");								
							}
					   });';		
		}
					
		$html[] = '</script>';

		return implode("\n", $html);	
	}
}
?>