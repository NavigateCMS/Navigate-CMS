function navigate_files_onload()
{
    $(document).on('keydown.del', function (evt)    { navigate_files_remove(); return false; } );
    $(document).on('keydown.home', function (evt)   { window.location.href = '?fid=files&act=0'; return false; } );
    $(document).on('keydown.Ctrl_j', function (evt) { navibrowse_folder_tree_dialog(0); return false; } );
}

function navigate_files_contextmenu(el, ev)
{
    var html = '<ul id="navigate-files-contextmenu">'+
                '<li action="open"><a href="#"><span class="ui-icon ui-icon-arrowreturnthick-1-e"></span>'+navigate_t(499, "Open")+'</a></li>'+
                '<li action="rename"><a href="#"><span class="ui-icon ui-icon-pencil"></span>'+navigate_t(500, "Rename")+'</a></li>'+
                '<li action="duplicate"><a href="#"><span class="ui-icon ui-icon-copy"></span>'+navigate_t(477, "Duplicate")+'</a></li>'+
                '<li action="delete"><a href="#"><span class="ui-icon ui-icon-trash"></span>'+navigate_t(35, 'Delete')+'</a></li>'+
                '</ul>';

    $("#navigate-files-contextmenu").remove();
    $(html).appendTo($("body"));

    $("#navigate-files-contextmenu").menu();

    $("#navigate-files-contextmenu").css(
        {
            "top": ev.clientY,
            "left": ev.clientX,
            "z-index": 100000,
            "position": "absolute"
        }
    ).addClass("navi-ui-widget-shadow").show();

    var type = "file";
    if($(el).hasClass("navibrowse-folder"))
        type = "folder";

    var id = $(el).attr("id").replace(/item-/, "");

    var selected_items = $("div.navibrowse-file.ui-selected,div.navibrowse-folder.ui-selected");

    // attach events to type & id

    if(selected_items.length == 1 || selected_items.length == 0)
    {
        $("#navigate-files-contextmenu").find("li[action=\"open\"]").on("click", function()
        {
            $(el).trigger("dblclick");
        }).show();

        $("#navigate-files-contextmenu").find("li[action=\"rename\"]").on("click", function()
        {
            if(type=="folder")
            {
                navigate_files_edit_folder(id, $(el).find(".navibrowse-item-name").text(), $(el).attr("mime"));
            }
            else
            {
                navigate_files_rename(id, $(el).find(".navibrowse-item-name").text());
            }
        }).show();

        if(type=="file")
        {
            $("#navigate-files-contextmenu").find("li[action=\"duplicate\"]").on("click", function()
            {
                $.ajax(
                    {
                        async: false,
                        type: "post",
                        data: {
                            id: id
                        },
                        url: NAVIGATE_APP + "?fid=" + navigate_query_parameter("fid") + "&act=json&op=duplicate_file",
                        success: function(data)
                        {
                            if(data=="true")
                                window.location.reload();
                            else
                                navigate_notification(data, true, true);
                        }
                    });
            }).show();
        }

        $("#navigate-files-contextmenu").find("li[action=\"delete\"]").on("click", function()
        {
            var elements = $(".ui-selected img").parent();
            $(el).trigger("click");
            navigate_files_remove(elements);
        });
    }
    else
    {
        $("#navigate-files-contextmenu").find("li[action=\"open\"]").hide();
        $("#navigate-files-contextmenu").find("li[action=\"rename\"]").hide();
        $("#navigate-files-contextmenu").find("li[action=\"duplicate\"]").hide();
        $("#navigate-files-contextmenu").find("li[action=\"delete\"]").on("click", function()
        {
            var elements = $(".ui-selected img").parent();
            $(el).trigger("click");
            navigate_files_remove(elements);
        });
    }
}

function navigate_files_rename(id, name)
{
    $("#navigate-edit-file").dialog(
        {
            title: navigate_t(82, 'File') + ": " + name,
            resizable: false,
            height: 200,
            width: 625,
            modal: true,
            buttons:
            [
                {
                    text: navigate_t(58, 'Cancel'),
                    click: function()
                    {
                        $("#navigate-edit-file").dialog("close");
                    }
                },
                {
                    text: navigate_t(152, 'Continue'),
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
                                url: NAVIGATE_APP + "?fid=" + navigate_query_parameter("fid") + "&act=json&op=edit_file",
                                success: function(data)
                                {
                                    $("#navigate-edit-file").dialog("close");
                                    window.location.reload();
                                }
                            });
                    }
                }
            ]
        });

    $("#file-name").val(name);
}

function navigate_files_dblclick(el)
{
    var itemId = el.id.substring(5);
    window.location.href = "?fid=files&act=2&id=" + itemId;
}

function navigate_files_move(item_id, folder_id, element)
{
    $.ajax(
        {
            async: false,
            type: "post",
            data: {
                item: item_id,
                folder: folder_id
            },
            url: NAVIGATE_APP + "?fid=" + navigate_query_parameter("fid") + "&act=json&op=move",
            success: function(data)
            {
                if(data=="true")
                {
                    if(typeof(element)=="array")
                    {
                        for(el in element)
                        {
                            if($(el).attr("id").substring(5) != folder_id)
                                $(el).remove();
                        }
                    }
                    else
                    {
                        $(element).remove();
                    }
                }
            }
        }
    );
}