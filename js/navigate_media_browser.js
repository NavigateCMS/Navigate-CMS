var navigate_media_browser_limit = 50;
var navigate_media_browser_offset = 0;
var navigate_media_browser_parent = 0;
var navigate_media_browser_website = 0;

function navigate_media_browser() 
{
	$("#navigate_media_browser_items").find("div").live("mouseenter", function() { $(this).css('opacity', 0.7); });
	$("#navigate_media_browser_items").find("div").live("mouseleave", function() { $(this).css('opacity', 1); });	
	
	$("#navigate_media_browser_buttons").find("div").eq(0).buttonset().css("float", "left");
	$("#media_browser_search img").button().removeClass('ui-corner-all');

	navigate_media_browser_website = navigate['website_id'];
	
	$("#navigate-media-browser").dialog(
	{
		title: navigate_lang_dictionary[11], // Multimedia
		position: [13, 1],
		height: 154,
		width: $("#navigate-content").width() - 6,
        minWidth: 580,
		resize: function()
		{
			$("#navigate_media_browser_items").height($(this).height() - 30);
		},
		dragStop: navigate_media_browser_save_position,
		resizeStop: navigate_media_browser_save_position,
		open: function()
		{
			var pos = $.cookie("navigate-mediabrowser"); 	
			if(pos)
			{
				$("#navigate-media-browser").parent().css({top: pos.top, left: pos.left, width: pos.width, height: pos.height});
				$("#navigate-media-browser").parent().addClass("navi-ui-widget-shadow");
				$("#navigate-media-browser").css({height: pos.height - 50});
				$("#navigate_media_browser_items").css({height: pos.height - 30 - 50});
				
				$('input[name="media_browser_type"][value="'+pos.type+'"]').trigger('click');
				
				if(pos.folder_id > 0) 
				{
					navigate_media_browser_parent = pos.folder_id;
					navigate_media_browser_set_folder(pos.folder_id, pos.folder_path);
					navigate_media_browser_reload();
				}
			}
			
			navigate_file_drop("#navigate_media_browser_items", navigate_media_browser_parent, 
							  { 
									afterAll: navigate_media_browser_reload							
							  });
		}
	}).dialogExtend(
	{
		maximizable: true
	});

	/* search events */
	$("#media_browser_search input").bind("keydown", function()
	{
		if(arguments[0].keyCode==13)
		{
            navigate_media_browser_offset = 0;
			navigate_media_browser_reload(this);	
			return false;	
		}
	});
	
	$("#media_browser_search input").bind("focus", function()
	{
		if($("#media_browser_search input").val() == (navigate_lang_dictionary[41] + "...")) // Search
			$("#media_browser_search input").val("");
	});
	
	$("#media_browser_search input").bind("blur", function()
	{
		if($("#media_browser_search input").val()=="")
			$("#media_browser_search input").val(navigate_lang_dictionary[41] + "..."); // Search
	});

	$("#media_browser_search img").bind("click", function() 
	{
        navigate_media_browser_offset = 0;
		navigate_media_browser_reload(); 
	});
		
	// element type buttonset
	
	$("input[name=media_browser_type]").bind("click", function()
	{
		$("#media_browser_search input").val(navigate_lang_dictionary[41] + "..."); // Search
        navigate_media_browser_offset = 0;
		navigate_media_browser_reload();
		navigate_media_browser_save_position();
	});

	navigate_website_selector_setup();
	
	// get the initial listing
	navigate_media_browser_reload();
}

function navigate_media_browser_refresh()
{
	// drag & drop support
	$("#navigate_media_browser_items div").not("#file-more").not("#file-0").draggable(
	{ 
		revert: true,  
		scroll: true, 
		containment: "document", 
		opacity: 0.7,
		helper: "clone",
		appendTo: "body",
		iframeFix: true,
		start: function(event, ui)
		{
			$(ui.helper).addClass("navigate_media_browser_clone");
		}
	});

    // images only: .find("div[mediatype='image']") [not needed right now]

    $("#navigate_media_browser_items div").not("#file-more").not(".draggable-folder").off("contextmenu").on("contextmenu", function(e)
    {
        navigate_hide_context_menus();
        var trigger = $(this);

        setTimeout(function()
        {
            $('#contextmenu-images').menu();

            var xpos = e.clientX;
            var ypos = e.clientY;

            if(xpos + $('#contextmenu-images').width() > $(window).width())
                xpos -= $('#contextmenu-images').width();

            $('#contextmenu-images').css({
                "top": ypos,
                "left": xpos,
                "z-index": 100000,
                "position": "absolute"
            });

            $('#contextmenu-images').addClass('navi-ui-widget-shadow');

            $('#contextmenu-images').show();

            $("#contextmenu-images-download_link").off('all').on("click", function ()
            {
                var itemId = $(trigger).attr('id').substring(5);
                var download_link = $(trigger).attr('download-link');
                //var download_link = NAVIGATE_DOWNLOAD + '?wid=' + navigate_media_browser_website + '&id=' + itemId + '&disposition=attachment';

                $('<div><form action="#"><textarea class="navigate-copy-link-textarea" style=" width: 550px; height: 100px; ">'+download_link+'</textarea></form></div>').dialog({
                    modal: true,
                    title: navigate_lang_dictionary[476] + ": Ctrl+C / Cmd+C, Escape",
                    width: 580,
                    height: 150,
                    open: function(event, ui)
                    {
                        setTimeout(function()
                        {
                            $('textarea.navigate-copy-link-textarea:visible').off('focus').on('focus', function(){
                                $(this).select();
                            });
                            $('textarea.navigate-copy-link-textarea:visible').focus();
                        }, 100);
                    },
                    close: function(event, ui)
                    {
                        $('textarea.navigate-copy-link-textarea:visible').parent().parent().parent().remove();
                    }
                });
            });

            $("#contextmenu-images-delete").off('all').on("click", function ()
            {
                navigate_contextmenu_delete_dialog(navigate_media_browser_delete, trigger);
            });
        }, 250);

        return false;
    });
}

function navigate_media_browser_delete(element)
{
    var itemId = $(element).attr('id').substring(5);

    $.ajax(
        {
            async: false,
            url: NAVIGATE_APP + '?fid=files&act=1&op=delete&id=' + itemId,
            success: function(data)
            {
                if(data=='1')
                    $(element).fadeOut();
            }
        }
    );
}

function navigate_media_browser_reload()
{
	var media = $("input[name=media_browser_type]:checked").val();
	
	var text = $("#media_browser_search input").val();
	if(!text || text==(navigate_lang_dictionary[41] + "...")) text = ""; // Search
				
	navigate_status(navigate_lang_dictionary[185] + "...", "loader"); // Searching elements
	
	$("#navigate_media_browser_items").load(
		"?fid=files&act=media_browser&website=" +
            navigate_media_browser_website +
            "&media=" + media +
            "&offset=" + navigate_media_browser_offset +
            "&limit=" + navigate_media_browser_limit +
            "&parent=" + navigate_media_browser_parent +
            "&text=" + text,
		function() 
		{ 
			if(media == "folder")
			{
				var folder_id = $("#navigate_media_browser_folder_id").val();
				if(folder_id==0) navigate_media_browser_set_folder(folder_id, "/"); 
			}													
			
			// drag & drop support and contextmenu!
			navigate_media_browser_refresh();
			
			$("#file-more").on("click", function()
			{
                $(this).html('<figure class="navigatecms_loader"></figure>');
                navigate_media_browser_offset += navigate_media_browser_limit;
				navigate_media_browser_reload();
			});
			
			$("#navigate_media_browser_items div.draggable-folder").on("dblclick", function()
			{
				$("#media_browser_search input").val(navigate_lang_dictionary[41] + "..."); // search
				navigate_media_browser_parent = $(this).attr("id").substr(5);
				navigate_media_browser_set_folder(navigate_media_browser_parent, $(this).attr("navipath"));
				navigate_media_browser_reload();
			});
			
			navigate_status(navigate_lang_dictionary[42], "ready"); // Ready
		}
	);
}

function navigate_media_browser_set_folder(folder_id, path)
{
	$("#navigate_media_browser_folder_path").val(path);
	$("#navigate_media_browser_folder_path").attr('title', path);

    if(!path || path=="")
    {
	    $("#navigate_media_browser_folder_path").parent().parent().css({"width": "20px"});
        $("#navigate_media_browser_folder_path").hide();
    }
    else
    {
        $("#navigate_media_browser_folder_path").parent().parent().css({"width": "155px"});
        $("#navigate_media_browser_folder_path").show();
    }
	
	// IE9 problems...
	$('#navigate_media_browser_buttons label[for="nvmb-folder"]').hide().show();
	
	$("#navigate_media_browser_folder_id").val(folder_id);	
	
	navigate_media_browser_save_position();
	
	navigate_file_drop("#navigate_media_browser_items", navigate_media_browser_parent, 
	{ 
		afterAll: navigate_media_browser_reload							
	});	
}

function navigate_media_browser_save_position()
{
	var pos = $("#navigate-media-browser").parent().offset();
	var width = $("#navigate-media-browser").parent().width();
	var height = $("#navigate-media-browser").parent().height();	
	var folder_id = parseInt($("#navigate_media_browser_folder_id").val());
	var folder_path = $("#navigate_media_browser_folder_path").val();
	var type = $('input[name="media_browser_type"]:checked').val();
	
	if(type!='folder')
	{
		folder_id = 0;
		folder_path = '/';
	}
	
	$.setCookie("navigate-mediabrowser", 
	{
		top: pos.top, 
		left: pos.left, 
		width: width, 
		height: height,
		type: type,
		folder_id: folder_id,
		folder_path: folder_path
	}); 				
}

function navigate_website_selector_setup()
{
    $("#navigate_media_browser_website").on('click',
	function() 
	{
		if(navigate_media_browser_website == 0)
			navigate_media_browser_website = navigate["website_id"];
			
         $('#navigate_media_browser_website_list').toggle();
		 	 
		 if($('#navigate_media_browser_website_list').is(':visible'))
		 {	
		 	$("#navigate_media_browser_website").addClass('ui-state-active');
		 
			 $('#navigate_media_browser_website_list div').removeClass('ui-state-highlight');
			 $('#navigate_media_browser_website_list').find('div[website_id='+navigate_media_browser_website+']').addClass('ui-state-highlight');

			 var viewer_height = $('#navigate_media_browser_website_list').height();
			 var real_height = $('#navigate_media_browser_website_list_wrapper').height(); // protect last element
			 
			 if(real_height <= viewer_height)
			 	$('#navigate_media_browser_website_list').css('height', real_height + 5);   
			 
			 real_height = real_height - viewer_height + 40;
			 					 
			  $('#navigate_media_browser_website_list').off('mousemove');
			  $('#navigate_media_browser_website_list').on('mousemove', function(e)
			  {
				  $("#navigate_media_browser_website").addClass('ui-state-active');
				  if(real_height > viewer_height)
				  {	
					var relativeY = e.pageY - $(this).offset().top;
					var percent_viewer = relativeY / viewer_height;
				  
					// protect first element
					$('#navigate_media_browser_website_list_wrapper').css('margin-top', -((real_height*1.10) * percent_viewer) + 20);	 // + 20 to protect first element
				  }
			  });
			 
			 $('#navigate_media_browser_website_list_wrapper div').off('click');
			 $('#navigate_media_browser_website_list_wrapper div').on('click', function()
			 {
				 navigate_media_browser_website = $(this).attr('website_id');
				 $('#navigate_media_browser_website_list').hide();
				 $("#navigate_media_browser_website").removeClass('ui-state-active');
				 navigate_media_browser_reload();			 
			 });			 
		 }
		 else
		 {
			$("#navigate_media_browser_website").removeClass('ui-state-active');			 
		 }
 	});
}