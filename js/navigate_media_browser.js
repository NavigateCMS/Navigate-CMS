var navigate_media_browser_limit = 50;
var navigate_media_browser_offset = 0;
var navigate_media_browser_parent = 0;
var navigate_media_browser_website = 0;
var navigate_media_browser_order = 'date_added_DESC';
var navigate_media_browser_folderpath = 0;

function navigate_media_browser() 
{
    $('select#media_browser_type')
        .iconselectmenu({
            appendTo: '#navigate_media_browser_buttons',
            select: navigate_media_browser_select_type
        })
        .iconselectmenu( "menuWidget" )
        .addClass( "ui-menu-icons" );

    $('select#media_browser_order')
        .imageselectmenu({
            appendTo: '#navigate_media_browser_buttons',
            select: navigate_media_browser_select_order
        }
    );

	$("#navigate_media_browser_buttons").find("div").eq(0).controlgroup().css("float", "left");
	$("#media_browser_search img").button().removeClass('ui-corner-all');

	navigate_media_browser_website = navigate['website_id'];
	
	$("#navigate-media-browser").dialog(
	{
		title: navigate_lang_dictionary[11], // Multimedia
		position: [13, 1],
		height: 154,
		width: $("#navigate-content").width() - 6,
        minWidth: 500,
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
                navigate_media_browser_order = pos.order;

				$("#navigate-media-browser").parent().css({top: pos.top, left: pos.left, width: pos.width, height: pos.height});
				$("#navigate-media-browser").parent().addClass("navi-ui-widget-shadow");
				$("#navigate-media-browser").css({height: pos.height - 50});
				$("#navigate_media_browser_items").css({height: pos.height - 30 - 50});

                if(pos.type)
                {
                    $('select[name="media_browser_type"]').val(pos.type);
                }

                if(pos.order)
                {
                    $('select[name="media_browser_order"]').val(pos.order);
                }

				if(pos.folder_id > 0)
				{
					navigate_media_browser_parent = pos.folder_id;
					navigate_media_browser_set_folder(pos.folder_id, pos.folder_path);
				}
			}

            $('select[name="media_browser_type"]').iconselectmenu( "refresh" );
            $('select[name="media_browser_order"]').imageselectmenu( "refresh" );
            $('select[name="media_browser_order"]').imageselectmenu( "updateIcon" );

            navigate_media_browser_reload();

            // user is allowed to upload files, otherwise the upload_button won't be inserted
            if($("#navigate_media_browser_upload_button").length > 0)
            {
                navigate_file_drop(
                    "#navigate-media-browser",
                    navigate_media_browser_parent,
                    {
                        uploadStarted: function(file)
                        {
                            /*
                            var div_id = 'upload-'+phpjs_sha1(file.name);
                            $('#navigate_media_browser_items').prepend('<div id="'+div_id+'" class="ui-corner-all" style="display: none;"></div>');
                            $('#'+div_id).append('<figure class="navigatecms_loader"></figure>');
                            $('#'+div_id).append('<span style="clear: both; display: block; height: 0px;">'+file.name+'</span>');
                            $('#navigate_media_browser_items .draggable-folder:last').after($('#'+div_id));
                            $('#'+div_id).show();
                            */
                        },
                        afterOne: function(file)
                        {
                            /*
                            var div_id = 'upload-'+phpjs_sha1(file.name);
                            $('#'+div_id).find('figure').remove();
                            */
                        },
                        afterAll: navigate_media_browser_reload
                    }
                );
            }

            $("#navigate-media-browser").on("scroll", function()
            {
                $("#file-more", "#navigate-media-browser").each(function()
                {
                    if(navigate_element_visible(this))
                    {
                        $(this).trigger("click");
                    }
                });
            });
		}
	}).dialogExtend(
	{
		maximizable: true
	});

	/* search events */
	$("#media_browser_search input").on("keydown", function()
	{
		if(arguments[0].keyCode==13)
		{
            navigate_media_browser_offset = 0;
            if($("#media_browser_search input").val().length > 0)
            {
                $("#media_browser_search img.media_browser_search_find").click();
            }
            else
            {
                $("#media_browser_search img.media_browser_search_cancel").click();
            }
			return false;	
		}
	});

	$("#media_browser_search img.media_browser_search_find").on("click", function()
	{
        $("#media_browser_search img.media_browser_search_cancel").show();
        navigate_media_browser_offset = 0;
		navigate_media_browser_reload();
	});

	$("#media_browser_search img.media_browser_search_cancel").on("click", function()
	{
        $("#media_browser_search img.media_browser_search_cancel").hide();
        $("#media_browser_search input").val("");
        navigate_media_browser_offset = 0;
		navigate_media_browser_reload();
	});

	navigate_website_selector_setup();
}

function navigate_media_browser_refresh()
{
	// drag & drop support
    var media_browser_items_draggable = $("#navigate_media_browser_items > div").not("#file-more").not("div[mimetype='folder/back']");

    $(media_browser_items_draggable).each(function()
    {
        var that = this;

        $(that).mousedown(function(event)
        {
            if(event.ctrlKey)
            {
                // use HTML5 draggable
                that.setAttribute( 'draggable', true );
                that.ondragstart = function( event )
                {
                    // create a json object with all info of the element
                    var object_info = {
                        mediatype: $(that).attr("mediatype"),
                        mimetype: $(that).attr("mimetype"),
                        image_width: $(that).attr("image-width"),
                        image_height: $(that).attr("image-height"),
                        image_title: $(that).attr("image-title"),
                        image_description: $(that).attr("image-description"),
                        download_link: $(that).attr("download-link"),
                        file_id: $(that).data("file-id"),
                        navipath: $(that).attr("navipath"),
                        website: $(that).data("website-id"),
                        id: $(that).attr("id")
                    };

                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', object_info.id);
                    event.dataTransfer.setData('nv_object_info', JSON.stringify(object_info));
                }
            }
            else
            {
                // use jQuery UI draggable, if not already active
                that.removeAttribute( 'draggable' );
                if(!$(that).data("ui-draggable"))
                {
                    $(that).draggable(
                        {
                            revert: true,
                            scroll: true,
                            containment: "document",
                            opacity: 0.7,
                            helper: "clone",
                            appendTo: "body",
                            iframeFix: true,
                            start: function (event, ui)
                            {
                                $(ui.helper).find('div.file-access-icons').hide();
                                $(ui.helper).addClass("navigate_media_browser_clone");
                            },
                            stop: function (event, ui)
                            {
                                $(ui.helper).find('div.file-access-icons').show();
                                $(that).draggable("destroy");
                                navigate_media_browser_refresh_files_used();
                            }
                        }
                    );
                    $(that).trigger(event);
                }
            }
        });
    });
/*
    media_browser_items_draggable.draggable(
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
            $(ui.helper).find('div.file-access-icons').hide();
			$(ui.helper).addClass("navigate_media_browser_clone");
		},
        stop: function(event, ui)
        {
            $(ui.helper).find('div.file-access-icons').show();
            navigate_media_browser_refresh_files_used();
        }
	});
	*/

    $("#navigate_media_browser_items").off("contextmenu");

    if($('select#media_browser_type').val()=="folder")
    {
        $("#navigate_media_browser_items").on("contextmenu", function(e)
        {
            navigate_hide_context_menus();
            var trigger = $(this);

            setTimeout(function()
            {
                $('#contextmenu-mediabrowser').data("file-type", $(trigger).data("mediatype"));
                $('#contextmenu-mediabrowser').data("file-id", $(trigger).data("file-id"));

                $('#contextmenu-mediabrowser').menu();

                var xpos = e.clientX;
                var ypos = e.clientY;

                if(xpos + $('#contextmenu-mediabrowser').width() > $(window).width())
                {
                    xpos -= $('#contextmenu-mediabrowser').width();
                }

                $('#contextmenu-mediabrowser').css({
                    "top": ypos,
                    "left": xpos,
                    "z-index": 100000,
                    "position": "absolute"
                });

                $('#contextmenu-mediabrowser').addClass('navi-ui-widget-shadow');

                $('#contextmenu-mediabrowser').show();

                $("#contextmenu-mediabrowser-create_folder").off('click').on("click", function ()
                {
                    navigate_files_edit_folder();
                });

            }, 250);

            return false;
        });

        $("#navigate_media_browser_items div.draggable-folder")
            .droppable({
                classes: {
                    "ui-droppable-hover": "ui-state-highlight"
                },
                tolerance: "pointer",
                drop: function(event, ui)
                {
                    var folder = $(this).attr("id").substring(5);
                    var item = $(ui.draggable).attr("id").substring(5);

                    $.ajax(
                    {
                        async: false,
                        type: "post",
                        data: {
                            item: item,
                            folder: folder
                        },
                        url: NAVIGATE_APP + "?fid=files&act=json&op=move",
                        success: function(data)
                        {
                            if(data=="true")
                            {
                                $(".navigate_media_browser_clone").remove();
                                $(ui.draggable).remove();
                            }
                        }
                    });
                }
            }
        );
    }

    $("#navigate_media_browser_items div")
        .not("#file-more").not(".file-image-wrapper").not(".file-icon-wrapper").not(".draggable-folder").not(".file-access-icons")
        .off("contextmenu")
        .on("contextmenu", function(e)
        {
            navigate_hide_context_menus();
            var trigger = $(this);

            setTimeout(function()
            {
                $('#contextmenu-files').data("file-type", $(trigger).data("mediatype"));
                $('#contextmenu-files').data("file-id", $(trigger).data("file-id"));

                $('#contextmenu-files').menu();

                var xpos = e.clientX;
                var ypos = e.clientY;

                if(xpos + $('#contextmenu-files').width() > $(window).width())
                    xpos -= $('#contextmenu-files').width();

                $('#contextmenu-files').css({
                    "top": ypos,
                    "left": xpos,
                    "z-index": 100000,
                    "position": "absolute"
                });

                $('#contextmenu-files').addClass('navi-ui-widget-shadow');

                $('#contextmenu-files').show();

                $("#contextmenu-files-focalpoint").hide();
                $("#contextmenu-files-description").hide();
                if($(trigger).hasClass('draggable-image'))
                {
                    $("#contextmenu-files-focalpoint").show();
                    $("#contextmenu-files-description").show();
                }

                $("#contextmenu-files-download_link").off('click').on("click", function ()
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

                $("#contextmenu-files-duplicate").off("click").on("click", function()
                {
                    var itemId = $(trigger).attr('id').substring(5);

                    $.ajax(
                        {
                            async: false,
                            url: NAVIGATE_APP + '?fid=files&act=json&op=duplicate_file&id=' + itemId,
                            success: function(data)
                            {
                                navigate_media_browser_reload();
                            }
                        }
                    );
                });

                $("#contextmenu-files-permissions").off("click").on("click", function()
                {
                    var itemId = $(trigger).attr('id').substring(5);
                    navigate_contextmenu_permissions_dialog(itemId, trigger);
                });

                $("#contextmenu-files-rename").off("click").on("click", function()
                {
                    var itemId = $(trigger).attr('id').substring(5);
                    var title = "";
                    if($(trigger).attr("mediatype")=="image")
                        title = $(trigger).find("div.file-image-wrapper img").attr("title");
                    else
                        title = $(trigger).find("div.file-icon-wrapper img").attr("title");

                    navigate_files_rename(itemId, title);
                });

                $("#contextmenu-files-description").off("click").on("click", function()
                {
                    var itemId = $(trigger).attr('id').substring(5);
                    navigate_contextmenu_description_dialog(itemId, trigger);
                });

                $("#contextmenu-files-focalpoint").off('click').on("click", function ()
                {
                    var itemId = $(trigger).attr('id').substring(5);
                    navigate_media_browser_focalpoint(itemId);
                });

                $("#contextmenu-files-delete").off("click").on("click", function()
                {
                    navigate_contextmenu_delete_dialog(navigate_media_browser_delete, trigger);
                });
            }, 250);

            return false;
        }
    );

    $("#navigate_media_browser_items div.draggable-folder")
        .not("#file-more").not(".file-image-wrapper").not(".file-icon-wrapper").not(".file-access-icons")
        .off("contextmenu")
        .on("contextmenu", function(e)
        {
            navigate_hide_context_menus();
            var trigger = $(this);

            setTimeout(function()
            {
                $('#contextmenu-mediabrowser-folders').data("file-id", $(trigger).data("file-id"));

                $('#contextmenu-mediabrowser-folders').menu();

                var xpos = e.clientX;
                var ypos = e.clientY;

                if(xpos + $('#contextmenu-mediabrowser-folders').width() > $(window).width())
                    xpos -= $('#contextmenu-mediabrowser-folders').width();

                $('#contextmenu-mediabrowser-folders').css({
                    "top": ypos,
                    "left": xpos,
                    "z-index": 100000,
                    "position": "absolute"
                });

                $('#contextmenu-mediabrowser-folders').addClass('navi-ui-widget-shadow');

                $('#contextmenu-mediabrowser-folders').show();

                $("#contextmenu-mediabrowser-folders-open").off("click").on("click", function()
                {
                    $(trigger).trigger("dblclick");
                });

                $("#contextmenu-mediabrowser-folders-rename").off("click").on("click", function()
                {
                    var itemId = $(trigger).attr('id').substring(5);
                    navigate_files_edit_folder(
                        itemId,
                        $(trigger).find("div.file-icon-wrapper img").attr("title"),
                        $(trigger).attr("mimetype")
                    );
                });

                $("#contextmenu-mediabrowser-folders-delete").off("click").on("click", function()
                {
                    navigate_contextmenu_delete_dialog(navigate_media_browser_delete, trigger);
                });
            }, 250);

            return false;
        }
    );

    navigate_media_browser_refresh_files_used();
}

function navigate_media_browser_refresh_files_used()
{
    // find images and files used in the current page and put a mark on them
    // files can be used in: properties (navigate-droppable), content, galleries

    // 1. remove all existing marks
    $("#navigate_media_browser_items").find(".file-access-icons").find(".file-used").remove();
    var files_used = [];

    // 2. find files used in properties
    $(".navigate-droppable").each(function()
    {
        files_used.push($(this).parent().find('input').val());
    });

    // 3. find files used in galleries
    var gallery_items = $(".items-gallery").parents(".ui-tabs-panel").find("input");
    if($(gallery_items))
    {
        gallery_items = $(gallery_items).val();
        if(gallery_items && gallery_items != "")
        files_used = files_used.concat(gallery_items.split("#"));
    }

    // 4. find files used in content
    $(".navigate-form-row textarea").each(function()
    {
        var text = '<div>' + $(this).val() + '</div>'; // force having html code
        // find all occurrences of "navigate_download.php" and "/object" in images and links
        $(text).find("img,a").each(function()
        {
            var link = $(this).attr("src");
            if(!link)
                link = $(this).attr("href");

            if( link.indexOf("/navigate_download.php") > -1   ||
                link.indexOf("/object") > -1 )
            {
                // find file id value
                files_used.push(navigate_query_parameter('id', link));
            }
        });
    });

    // 5. clean array
    files_used = files_used.filter(function (v, i, a) { return a.indexOf (v) == i });

    // 6. put a mark on each file used
    for(i in files_used)
    {
        if(!files_used[i] || files_used[i]=="" || files_used[i]==0)
        {
            continue;
        }

		// ignore default theme images including a slash
		if(files_used[i].indexOf('/') < 0)
		{
			$("#navigate_media_browser_items")
				.find("#file-" + files_used[i])
				.find(".file-access-icons")
				.append('<img align="absmiddle" class="file-used" title="'+navigate_t(580, "Used in this page")+'" src="img/icons/silk/tick.png">');
		}
    }
}

function navigate_contextmenu_permissions_dialog(file_id, trigger)
{
    $.ajax(
        {
            async: false,
            dataType: 'json',
            url: NAVIGATE_APP + '?fid=files&act=json&op=permissions&id=' + file_id,
            success: function(data)
            {
                $('#contextmenu-permissions-access').val(data.access).trigger('change');
                $('#contextmenu-permissions-permission').val(data.permission).trigger('change');
                if(data.enabled=='1')
                    $('#contextmenu-permissions-enabled').attr('checked', 'checked');
                else
                    $('#contextmenu-permissions-enabled').removeAttr('checked');

                $('#contextmenu-permissions-groups option').removeAttr("selected");
                $(data.groups).each(function(i, val)
                {
                    $('#contextmenu-permissions-groups option[value="'+val+'"]').attr("selected", "selected");
                });
                $('#contextmenu-permissions-groups').multiselect('refresh');

                $('#contextmenu-permissions-dialog').dialog(
                    {
                        resizable: true,
                        width: 610,
                        height: 200,
                        modal: true,
                        title: navigate_lang_dictionary[17], // Permissions
                        buttons: [
                            {
                                text: navigate_lang_dictionary[190], // Ok
                                click: function()
                                {
                                    var data = {
                                        id: file_id,
                                        access: $('#contextmenu-permissions-access').val(),
                                        permission: $('#contextmenu-permissions-permission').val(),
                                        enabled: ($('#contextmenu-permissions-enabled').is(':checked')? 1:0),
                                        groups: $('#contextmenu-permissions-groups').val()
                                    };

                                    $.ajax(
                                    {
                                        method: 'post',
                                        async: false,
                                        data: data,
                                        url: NAVIGATE_APP + '?fid=files&act=json&op=permissions&id=' + file_id,
                                        success: function(data)
                                        {
                                            if(data=='true')
                                            {
                                                navigate_media_browser_reload();
                                                $('#contextmenu-permissions-dialog').dialog("close");
                                            }
                                        }
                                    });
                                }
                            },
                            {
                                text: navigate_lang_dictionary[58], // Cancel
                                click: function()
                                {
                                    $(this).dialog("close");
                                }
                            }
                        ]
                    }
                );

                navigate_permissions_dialog_webuser_groups_visibility(data.access);
            }
        }
    );
}


function navigate_files_edit_folder(id, name, mime)
{
    $('#navigate-edit-folder').dialog({
        modal: true,
        width: 620,
        height: 200,
        title: navigate_lang_dictionary[141], // Folder
        resizable: false,
        buttons:
        [
            {
                text: navigate_lang_dictionary[58], // Cancel
                click: function()
                {
                    $("#navigate-edit-folder").dialog("close");
                }
            },
            {
                text: navigate_lang_dictionary[190], // Ok
                click: function()
                {
                    var op = "edit_folder";
                    if(!name)
                    {
                        op = "create_folder";
                    }

                    $.ajax(
                        {
                            async: false,
                            type: "post",
                            data: {
                                name: $("#folder-name").val(),
                                mime: $("#folder-mime").val(),
                                parent: navigate_media_browser_parent
                            },
                            dataType: "json",
                            url: "?fid=files&act=json&id=" + id + "&op=" + op,
                            success: function(data)
                            {
                                if(op == 'create_folder')
                                {
                                    var priority = [parseInt(data)];
                                    navigate_media_browser_reload(priority);
                                }
                                else
                                {
                                    navigate_media_browser_reload();
                                }
                                $("#navigate-edit-folder").dialog("close");
                            }
                        }
                    );
                }
            }
        ]
    });

    $("#folder-name").val(name);
    $("#folder-mime").val(mime).trigger("change");
}



function navigate_files_rename(id, name)
{
    $("#navigate-edit-file").dialog(
        {
            title: navigate_t(82, 'File') + ": " + name,
            resizable: false,
            height: 150,
            width: 625,
            modal: true,
            buttons:
            [
                {
                    text: navigate_t(190, 'Ok'),
                    click: function()
                    {
                        $.ajax(
                            {
                                async: false,
                                type: "post",
                                data: {
                                    name: $("#file-name").val(),
                                    id: id
                                },
                                url: NAVIGATE_APP + "?fid=files&act=json&op=edit_file",
                                success: function(data)
                                {
                                    navigate_media_browser_reload();
                                    $("#navigate-edit-file").dialog("close");
                                }
                            });
                    }
                },
                {
                    text: navigate_t(58, 'Cancel'),
                    click: function()
                    {
                        $("#navigate-edit-file").dialog("close");
                    }
                }
            ]
        }
    );

    $("#file-name").val(name);
}

function navigate_contextmenu_description_dialog(file_id, trigger, title, alt)
{
    if(!title)
    {
        var title = [];

        // try to retrieve the info from Media browser window
        if($('#file-' + file_id).length > 0)
        {
            title = $('#file-' + file_id).attr('image-title');
            title = Base64.decode(title);
            title = $.parseJSON(title);
        }
    }

    if(!alt)
    {
        var alt = [];
        // try to retrieve the info from Media browser window
        if($('#file-' + file_id).length > 0)
        {
            alt = $('#file-' + file_id).attr('image-description');
            alt = Base64.decode(alt)
            alt = $.parseJSON(alt);
        }
    }

    $('#contextmenu-description-dialog-id').html(file_id);

    // empty current values
    $('[id^=contextmenu-description-dialog-title-]').val('');
    $('[id^=contextmenu-description-dialog-description-]').val('');

    for(attr_lang in title)
    {
        $('#contextmenu-description-dialog-title-' + attr_lang).val(title[attr_lang]);
    }

    for(attr_lang in alt)
    {
        $('#contextmenu-description-dialog-description-' + attr_lang).val(alt[attr_lang]);
    }

    $('#contextmenu-description-dialog').dialog(
        {
            resizable: true,
            width: 620,
            height: 400,
            modal: true,
            title: navigate_lang_dictionary[334], // Description
            buttons: [
                {
                    text: navigate_lang_dictionary[190], // Ok
                    click: function()
                    {
                        var titles = {};
                        var descriptions = {};

                        $('[id^=contextmenu-description-dialog-title-]').each(function()
                        {
                            var flang = $(this).attr('id').replace('contextmenu-description-dialog-title-', '');
                            titles[flang] = $(this).val();
                        });

                        $('[id^=contextmenu-description-dialog-description-]').each(function()
                        {
                            var flang = $(this).attr('id').replace('contextmenu-description-dialog-description-', '');
                            descriptions[flang] = $(this).val();
                        });

                        $.ajax(
                    {
                                method: 'post',
                                async: false,
                                data: {
                                    id: file_id,
                                    titles: titles,
                                    descriptions: descriptions
                                },
                                url: NAVIGATE_APP + '?fid=files&act=json&op=description&id=' + file_id,
                                success: function(data)
                                {
                                    if(data=='true')
                                    {
                                        navigate_media_browser_reload();
                                        $('#contextmenu-description-dialog').dialog("close");
                                    }
                                }
                           }
                       );
                    }
                },
                {
                    text: navigate_lang_dictionary[58], // Cancel
                    click: function()
                    {
                        $(this).dialog("close");
                    }
                }
            ]
        }
    );
}

function navigate_contextmenu_delete_dialog(callback, params)
{
    // 57: Do you really want to delete this item?
    // 59: Confirmation
    // 58: Cancel
    // 35: Delete

    $('<div id="navigate-contextmenu-delete-dialog">'+navigate_lang_dictionary[57]+'</div>').dialog(
    {
        resizable: true,
        height: 150,
        width: 300,
        modal: true,
        title: navigate_lang_dictionary[59],
        buttons: [
            {
                text: navigate_lang_dictionary[35],
                click: function()
                {
                    $("#navigate-contextmenu-delete-dialog").dialog("close");
                    if(callback)
                        callback(params);
                    $("#navigate-contextmenu-delete-dialog").remove();
                }
            },
            {
                text: navigate_lang_dictionary[58],
                click: function()
                {
                    $("#navigate-contextmenu-delete-dialog").dialog("close");
                    $("#navigate-contextmenu-delete-dialog").remove();
                }
            }
        ]
    });
}

function navigate_media_browser_delete(element)
{
    var itemId = $(element).attr('id').substring(5);

    $.ajax(
        {
            async: false,
            url: NAVIGATE_APP + '?fid=files&act=json&op=delete&id=' + itemId,
            success: function(data)
            {
                if(data=='1' || data=='true')
                {
                    $(element).fadeOut();
                }
				else if(data != "" && data != '""')
                {
                    navigate_notification(data);
                }
            }
        }
    );
}

// priority: optional array of priority items
function navigate_media_browser_reload(priority)
{
	var media = $("select[name=media_browser_type]").val();
	if(!priority)
    {
        priority = [];
    }

    // update icon on media_browser_type button
    var icon = $('select#media_browser_type option[value="'+$('select#media_browser_type').val()+'"]').attr('data-class');
    $('#media_browser_type-button i').remove();
    $('#media_browser_type-button')
        .find('.ui-selectmenu-text')
        .prepend('<i class="ui-icon '+icon+'" />');
	
	var text = $("#media_browser_search input").val();

	navigate_status(navigate_lang_dictionary[185] + "...", "loader"); // Searching elements

    if(media=='folder')
    {
        $('#media_browser_type-button').find('.ui-selectmenu-text').html(
            '<i class="ui-icon ui-icon-folder-collapsed" />' +
            $("#nvmb-folder").attr("prefix") +
            "&nbsp;&nbsp;" +
            navigate_media_browser_folderpath
        );
    }

    $('#navigate_media_browser_website').button("enable");
    $('#navigate_media_browser_upload_button').button("enable");
    if(media=='youtube')
    {
        $('#navigate_media_browser_website').button("disable");
        $('#navigate_media_browser_upload_button').button("disable");
    }

    $("#navigate_media_browser_items").load(
		"?fid=files&act=media_browser",
        {
            'website': navigate_media_browser_website,
            "media": media,
            "offset": navigate_media_browser_offset,
            "limit": navigate_media_browser_limit,
            "parent": navigate_media_browser_parent,
            "order": navigate_media_browser_order,
            "priority": priority.join(","),
            "text": text
        },
		function() 
		{ 
			if(media == "folder")
			{
				var folder_id = $("#navigate_media_browser_folder_id").val();
				if(folder_id==0)
                {
                    navigate_media_browser_set_folder(folder_id, "/");
                }
			}													
			
			// drag & drop support and contextmenu!
			navigate_media_browser_refresh();
			
			$("#file-more").on("click", function()
			{
                $(this).off("click");
                $(this).html('<figure class="navigatecms_loader"></figure>');
                navigate_media_browser_offset += navigate_media_browser_limit;
				navigate_media_browser_reload();
			});
			
			$("#navigate_media_browser_items div.draggable-folder").on("dblclick", function()
			{
				$("#media_browser_search input").val("");
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
    if(path==0)
        path = "";

    $('#media_browser_type-button').find('.ui-selectmenu-text').html(
        '<i class="ui-icon ui-icon-folder-collapsed" />' + $("#nvmb-folder").attr("prefix") + "&nbsp;&nbsp;" + path
    );
	
	$("#navigate_media_browser_folder_id").val(folder_id);
    navigate_media_browser_folderpath = path;

	navigate_media_browser_save_position();

    // user is allowed to upload files, otherwise the upload_button won't be inserted
    if($("#navigate_media_browser_upload_button").length > 0)
    {
        navigate_file_drop("#navigate_media_browser_items", navigate_media_browser_parent,
        {
            afterAll: navigate_media_browser_reload
        });
    }
}

function navigate_media_browser_save_position()
{
    if(!$("#navigate-media-browser").is(':visible'))
        return;

	var pos = $("#navigate-media-browser").parent().offset();
	var width = $("#navigate-media-browser").parent().width();
	var height = $("#navigate-media-browser").parent().height();	
	var folder_id = parseInt($("#navigate_media_browser_folder_id").val());
	var folder_path = navigate_media_browser_folderpath;
	var type = $('select[name="media_browser_type"]').val();
	var order = $('select[name="media_browser_order"]').val();

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
		order: order,
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

function navigate_media_browser_focalpoint(file_id)
{
    $(".focalpoint_select").next().remove();
    $(".focalpoint_select").remove();
    $.get(
        NAVIGATE_APP + '?fid=files&act=json&op=focalpoint&id=' + file_id,
        function(focalpoint)
        {
            if(!focalpoint || focalpoint=="")
            {
                focalpoint = "50#50"; // default: image center
            }

            focalpoint = focalpoint.split('#');

            var focalpoint_top = parseFloat(focalpoint[0]);
            var focalpoint_left = parseFloat(focalpoint[1]);

            var image_url = NAVIGATE_DOWNLOAD + '?id=' + file_id + '&disposition=inline&width=700&force_scale=true';
            var html = '<div><div class="focalpoint_select"></div><img src="'+image_url+'" width="100%" /></div>';
            $(html).dialog(
                {
                    modal: true,
                    title: navigate_t(540, "Focal point"),
                    width: 700,
                    height: 500,
                    resizable: false
                }
            );
            $(".focalpoint_select").css('visibility', 'hidden');

            var img = $(".focalpoint_select").parent().find("img");

            img.on("load", function()
            {
                focalpoint_top = ((focalpoint_top/100) * $(img)[0].height) - ($(".focalpoint_select").height() / 2);
                focalpoint_left = ((focalpoint_left/100) * $(img)[0].width) - ($(".focalpoint_select").width() / 2);

                if(focalpoint_top < 0)
                {
                    focalpoint_top = ($(img).height() / 2) - ($(".focalpoint_select").height() / 2);
                }

                if(focalpoint_left < 0)
                {
                    focalpoint_left = ($(img).width() / 2) - ($(".focalpoint_select").width() / 2);
                }

                $(".focalpoint_select")
                    .css({
                        top: focalpoint_top,
                        left: focalpoint_left,
                        visibility: 'visible'
                    })
                    .draggable(
                    {
                        containment: img,
                        stop: function(event, ui)
                        {
                            var percentage_top = ((ui.position.top + ($(ui.helper).height() / 2)) / $(img).height()) * 100;
                            var percentage_left = ((ui.position.left + ($(ui.helper).width() / 2)) / $(img).width()) * 100;

                            if(Math.ceil($(ui.helper).width()/2) == ui.position.left)
                            {
                                // focal point is touching the left side
                                percentage_left = 0;
                            }

                            if(Math.ceil($(ui.helper).height()/2) == ui.position.top * 2)
                            {
                                // focal point is touching the top side
                                percentage_top = 0;
                            }

                            if(Math.ceil(ui.position.left + $(ui.helper).width()) >= $(img).width())
                            {
                                // focal point is touching the right side
                                percentage_left = 100;
                            }

                            if(Math.ceil(ui.position.top + $(ui.helper).height()) >= $(img).height())
                            {
                                // focal point is touching the bottom side
                                percentage_top = 100;
                            }

                            $.post(
                                NAVIGATE_APP + '?fid=files&act=json&op=focalpoint&id=' + file_id,
                                {
                                    top: percentage_top,
                                    left: percentage_left
                                },
                                function(result)
                                {
                                    if(result=='true')
                                    {
                                        $(ui.helper).effect("highlight", 'slow');
                                    }
                                }
                            );
                        }
                    });
            });

        }
    );
}

function navigate_media_browser_select_type( event, ui )
{
    var type = $('select#media_browser_type').val();
    var icon = $('select#media_browser_type option[value="'+type+'"]').attr('data-class');
    $('#media_browser_type-button i').remove();
    //$('#media_browser_type-button').prepend('<i class="ui-icon '+icon+'" />');
    $('#media_browser_type-button')
        .find('.ui-selectmenu-text')
        .prepend('<i class="ui-icon '+icon+'" />');

    // force items refresh
    $("#media_browser_search input").val("");
    navigate_media_browser_offset = 0;
    navigate_media_browser_reload();
    navigate_media_browser_save_position();
}

function navigate_media_browser_select_order(event, ui)
{
    navigate_media_browser_order = $('select#media_browser_order').val();
    $('select[name="media_browser_order"]').imageselectmenu( "updateIcon" );

    $("#navigate_media_browser_items").empty();
    navigate_media_browser_reload();
    navigate_media_browser_save_position();
}