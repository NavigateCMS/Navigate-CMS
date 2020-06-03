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

		// recent folders

		$function_id = $_REQUEST['fid'];
		if(!is_numeric($function_id))
		{
			$f = core_load_function($_REQUEST['fid']);
			$function_id = $f->id;
		}
		$actions = users_log::recent_actions($function_id, 'list', 8);

		if(!empty($actions))
		{
			$html[] = '	<a href="#" id="navigate-navibrowse-recent-items-link"><img src="img/icons/silk/folder_bell.png" width="16px" height="16px" align="absbottom" /> '.t(275, 'Recent elements').' <span style=" float: right; " class="ui-icon ui-icon-triangle-1-s"></span></i></a>';
			$layout->add_content(
				'<ul id="navigate-navibrowse-recent-items" class="hidden">'.
				implode(
					"\n",
					array_map(
						function($action)
						{
							return 	'<li>'.
										'<a href="?fid='.$_REQUEST['fid'].'&parent='.$action->item.'">'.
											'<img src="img/icons/silk/folder.png" style="vertical-align: text-bottom;" /> '.
											file::getFullPathTo($action->item).
										'</a>'.
									'</li>';
						},
						$actions
					)
				).
				'</ul>'
			);

			$layout->add_script('
				$("#navigate-navibrowse-recent-items-link").on("click", function(e)
				{
					e.preventDefault();
					e.stopPropagation();
					
					$("#navigate-navibrowse-recent-items").css({
						position: "absolute",
						top: $("#navigate-navibrowse-recent-items-link").offset().top + 26,
						left: $("#navigate-navibrowse-recent-items-link").offset().left,
						zIndex: 10,
						"min-width": $("#navigate-navibrowse-recent-items-link").width() + "px"
					});
					$("#navigate-navibrowse-recent-items").addClass("navi-ui-widget-shadow");
					$("#navigate-navibrowse-recent-items").removeClass("hidden");
					$("#navigate-navibrowse-recent-items").menu().show();
					
					return false;
				});
			');
		}

		// folders hierarchy
		$html[] = '	<a href="#" onclick="navibrowse_folder_tree_dialog('.$this->parent.');"><img src="img/icons/silk/application_side_tree.png" width="16px" height="16px" align="absbottom" /> '.t(75, 'Path').': '.$this->path.'</a>';

		$html[] = '
			<div style="float: right;">
				<i class="fa fa-filter"></i>
	            <select id="navibrowse-filter-type" name="navibrowse-filter-type">
					<option value="" selected="selected">('.t(443, "All").')</option>
					<option value="folder">'.t(141, "Folder").'</option>
					<option value="image">'.t(157, 'Image').'</option>
					<option value="audio">'.t(31, 'Audio').'</option>
					<option value="document">'.t(539, 'Document').'</option>
					<option value="video">'.t(30, 'Video').'</option>
				</select>
			</div>
		';

		$layout->add_script('
			$("#navibrowse-filter-type")
				.select2(
				{
				    placeholder: "'.t(160, "Type").'",
				    allowClear: true,
			        minimumResultsForSearch: Infinity,
			        width: 150,
			        templateResult: navibrowse_filter_type_render,
			        templateSelection: navibrowse_filter_type_render
			    })
		        .on("select2:unselecting", function(e)
			    {
			        $(this).select2("val", "");
			        $(this).val("");
			        e.preventDefault();
			        $(this).trigger("change");
			    });

		    function navibrowse_filter_type_render(opt)
	        {
				if(!opt.id) { return opt.text; }

				var icon = "fa-file";
				switch(opt.element.value)
				{
					case "folder":      icon = "fa-folder-o";       break;
					case "image":       icon = "fa-image";          break;
					case "audio":       icon = "fa-music";          break;
					case "document":    icon = "fa-file-text-o";    break;
					case "video":       icon = "fa-video-camera";   break;
				}

				var html = $(\'<span><i class="fa fa-fw \'+icon+\'"></i> \' + opt.text + \'</span>\');
				return html;
	        }

		    $("#navibrowse-filter-type").on("change", function () {
				$(".navibrowse-items > div").show();
				if($("#navibrowse-filter-type").val() != "")
				{
					$(".navibrowse-items > div").each(function()
					{
						if($(this).data("file-type") == $("#navibrowse-filter-type").val())
							$(this).show();
						else
							$(this).hide();
					});
				}
		    });
		');

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
			$icon = $this->mimeIcon($item->mime, $item->type);

			$thumbnail = '';

			if($item->type == 'image')
				$thumbnail = NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=inline&width='.intval($this->icon_size).'&height='.intval($this->icon_size);

            if($item->mime == 'application/pdf' && extension_loaded('imagick'))
                $thumbnail = NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$item->id.'&disposition=inline&type=thumbnail&width='.intval($this->icon_size).'&height='.intval($this->icon_size);

			if($item->type=='folder')
			{
				$html[] = '<div class="navibrowse-folder ui-corner-all" mime="'.$item->mime.'" id="item-'.$item->id.'" data-file-type="'.$item->type.'" data-file-id="'.$item->id.'">';
				$html[] = '		<img src="'.$icon.'" width="'.$this->icon_size.'" height="'.$this->icon_size.'" />';
				$html[] = '		<div class="navibrowse-item-name">'.$item->name.'</div>';
				$html[] = '</div>';
			}
			else
			{
				$html[] = '<div class="navibrowse-file ui-corner-all" mime="'.$item->mime.'" id="item-'.$item->id.'" data-file-type="'.$item->type.'" data-file-id="'.$item->id.'">';
                $html[] = '     <div class="navibrowse-file-access-icons">'.$permissions[$item->permission].$access[$item->access].'</div>';
				$html[] = '		<img src="'.$icon.'" data-src="'.$thumbnail.'"  width="'.$this->icon_size.'" height="'.$this->icon_size.'" />';
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
			<div id="navibrowse-folder-tree-dialog" class="hidden">			
				<div class="navibrowse_folder_tree" style=" width: 90%; ">
				    <img src="img/icons/silk/folder_home.png" align="absmiddle" />  '.t(18, 'Home').
                    '<div class="tree_ul">'.$folders_tree.'</div>
                </div>
			</div>
		';

		$html[] = '<script language="javascript" type="text/javascript">';

		// replace placeholder images by real thumbnails after document is ready
		$html[] = '
			$(window).on("load", function()
			{
				new LazyLoad({
				    threshold: 200,
				    container: document.getElementById("navigate-content-safe"),
				    elements_selector: ".navibrowse-file img",
				    throttle: 40,
				    data_src: "src",
				    show_while_loading: true
				});
			});
		';

//		$html[] = '$(".navibrowse-file, .navibrowse-folder").on("mouseover", function() { $(this).css("opacity", 0.7); });';
//		$html[] = '$(".navibrowse-file, .navibrowse-folder").on("mouseout", function() { $(this).css("opacity", 1); });';
		
		$html[] = '$(".navibrowse-path a").button();';
		
		$html[] = '$(".navibrowse-items").children().on("click", function(e)
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
				   
		
		$html[] = '$(".navibrowse-folder").on("dblclick", function()
				   {
						window.location.href = "'.$this->url.'" + this.id.substring(5);
				   });';

        if(!empty($this->onDblClick))
		    $html[] = '$(".navibrowse-file").on("dblclick", function()
			    	   {
				    		'.$this->onDblClick.'(this);
				    });';

		$html[] = '$(".navibrowse-items").on("click", function()
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
                            //$(".ui-selectee, .ui-selected").removeClass("ui-selectee ui-selected");

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
            $(".navibrowse_folder_tree .tree_ul").jstree({
                plugins: ["changed", "types"],
                "types" : 
                {
                    "default":  {   "icon": "img/icons/silk/folder.png"    },
                    "leaf":     {   "icon": "img/icons/silk/page_white.png"      }
                },
                "core" : 
                {
                    "multiple" : false
                }
            }).on("dblclick.jstree", function(e, data)
            {
                var node = $(e.target).closest("li");
                window.location.href = "?fid='.$_REQUEST['fid'].'&parent=" + $(node).attr("value");
            });
				
			
			$("#navibrowse-folder-tree-dialog").hide();
		
			function navibrowse_folder_tree_dialog(current)
		    {
		        $("#navibrowse-folder-tree-dialog").removeClass("hidden");
                $("#navibrowse-folder-tree-dialog").dialog(
                {
                    resizable: true,
                    height: 350,
                    width: 500,
                    modal: true,
                    title: "'.t(221, 'Jump to').'…",
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
						{
						    $(ui.helper).append("<div id=\"navibrowse-item-badge\">" + navibrowse_selected.length + "</div>");
                        }
					},
					stop: function()
					{
						navibrowse_selected = $("div.navibrowse-file.ui-selected, div.navibrowse-folder.ui-selected");
						$("#navibrowse-item-badge").remove();
					}
				});
			';
			
			$html[] = '
			    $(".navibrowse-folder").droppable(
                {
                    classes: {
                        "ui-droppable-hover": "ui-selected"
                    },
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
                });
            ';
		}
					
		$html[] = '</script>';

		return implode("\n", $html);	
	}
}

?>