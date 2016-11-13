var links_table_row_models = [];
var poll_answers_table_row_models = [];

/* blocks scripts */
function navigate_blocks_action_change(lang, el)
{
    $("#action-web-"+lang).parents('.navigate-form-row').hide();
    $("#action-javascript-"+lang).parents('.navigate-form-row').hide();
    $("#action-file-"+lang).parents('.navigate-form-row').hide();
    $("#action-image-"+lang).parents('.navigate-form-row').hide();

    var action_type = $(el).val();

    //if(!$(el).is(':visible')) return;

    if(action_type == "web" || action_type == "web-n")
    {
        $("#action-web-" + lang).parents('.navigate-form-row').show();
    }
    else if(action_type == "javascript")
    {
        $("#action-javascript-" + lang).parents('.navigate-form-row').show();
    }
    else
        $("#action-" + action_type + "-" + lang).parents('.navigate-form-row').show();
}

function navigate_blocks_trigger_change(lang, el)
{
    var trigger_type = $(el).val();

    $("#trigger-image-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-video-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-rollover-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-flash-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-html-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-links-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-content-"+lang).parents('.navigate-form-row').hide();

    $("#action-type-" + lang).parent().hide();

    if($.inArray(trigger_type, ["", "title", "image", "rollover", "content"]) > -1)
        $("#action-type-" + lang).parent().show();
    else
        $("#action-type-" + lang).val("");

    if($("#trigger-" + trigger_type + "-" + lang).length > 0)
        $("#trigger-" + trigger_type + "-" + lang).parents('.navigate-form-row').show();

    $(navigate_codemirror_instances).each(function() { this.refresh(); } );

    navigate_blocks_action_change(lang, $("#action-type-" + lang));
}

$("button[id^=\"trigger-links-table-add-\"]").on("click", function(e)
{
    e.preventDefault();
    e.stopPropagation();
    var lang = $(this).attr("data-lang");
    var tsid = new Date().getTime();
    $(this).parent().find("table").append('<tr></tr>').css('cursor', 'move');
    $(this).parent().find("tr:last").append(links_table_row_models[lang]);
    $(this).parent().find("tr:last").find('input,label,select').each(function()
    {
        if($(this).attr('name'))
        {
            var new_name = ($(this).attr('name').split('['))[0];
            $(this).attr('name', new_name + "[" + tsid + "]");
        }

        if($(this).attr('id'))
        {
            var new_name = ($(this).attr('id').split('['))[0];
            $(this).attr('id', new_name + "[" + tsid + "]");
        }

        if($(this).attr('for'))
        {
            var new_name = ($(this).attr('for').split('['))[0];
            $(this).attr('for', new_name + "[" + tsid + "]");
        }
    });
    $(this).parent().find("table").tableDnDUpdate();

    // enable icon selector?
    if($(this).parent().find("select[name^=trigger-links-table-icon]"))
    {
        navigate_blocks_trigger_links_table_icon_selector($(this).parent().find("tr:last"));
    }

    return false;
});

function navigate_blocks_trigger_links_table_icon_selector(tr)
{
    var field = $(tr).find("td:first").find("select");

    $(navigate_fontawesome_classes).each(function(i, row)
    {
        $(field).append('<option value="'+row.id+'">' + row.text + '</option>');
    });

    $(field).select2({
        placeholder: "",
        allowClear: true,
        escapeMarkup: function (markup)
        {
            return markup; // let our custom formatter work
        },
        templateSelection: function(row)
        {
            if(row.id)
                return "<i class=\"fa fa-fw fa-2x "+row.id+"\" style=\"vertical-align: top;\"></i> " + row.text;
            else
                return "("  + navigate_t(581, "None") + ")";
        },
        templateResult: function(data)
        {
            if(data.text)
                return "<i class=\"fa fa-fw fa-2x "+data.id+"\" style=\"vertical-align: middle;\"></i> " + data.text;
            else
                return "("  + navigate_t(581, "None") + ")";
        }
    });

    // select default value
    $(field).val($(field).data("select2-value"));
    $(field).trigger("change");
    if($(field).data("select2-value")=="")
    {
        $(field).next().find(".select2-selection__clear").trigger("mousedown");
        $(field).select2("close");
    }
}

function navigate_blocks_trigger_links_table_row_remove(el)
{
    $(el).parents('tr').remove();
}

$("button[id^=\"poll-answers-table-add-\"]").on("click", function(e)
{
    e.preventDefault();
    e.stopPropagation();
    var lang = $(this).attr("data-lang");
    var uid = new Date().getTime();
    $(this).parent().find("table").append('<tr></tr>').css('cursor', 'move');
    $(this).parent().find("tr:last").append(poll_answers_table_row_models[lang]);
    $(this).parent().find("tr:last").attr("id", "poll-answers-table-row-" + uid);
    $(this).parent().find("tr:last").find('input').each(function(i)
    {
        var new_name = ($(this).attr('name').split('['))[0];
        $(this).attr('name', new_name + "[" + uid + "]");
        //if(i==1) $(this).val(uid);
    });

    $(this).parent().find("table").tableDnDUpdate();
    return false;
});

function navigate_blocks_poll_answers_table_row_remove(el)
{
    $(el).parents('tr').remove();
}

function navigate_items_select_language(el)
{
    var code;
    if(typeof(el)=="string")
        code = el;
    else
        code = $("#"+$(el).attr("for")).val();

    $(".language_fields").css("display", "none");
    $("#language_fields_" + code).css("display", "block");

    $("#language_selector_" + code).attr("checked", "checked");

    $(navigate_codemirror_instances).each(function() { this.refresh(); } );
}

function navigate_blocks_group_block_settings(el)
{
    var code = $(el).attr("data-block-group-block");
    var block_group = $(el).attr("data-block-group");
    var uid = $(el).parent().parent().data("block-uid");

    $("<div>")
        .html('<iframe width="100%" height="100%" frameborder="0" src="?fid=blocks&act=block_group_block_options&block_group=' + block_group + '&code=' + code + '&block_uid=' + uid  + '"></iframe>')
        .dialog(
        {
            width: $(window).width() * 0.95,
            height: $(window).height() * 0.95,
            modal: true,
            title: "<img src=\"img/icons/silk/cog.png\" align=\"absmiddle\"> " + $(this).parent().prev().prev().text()
        }).dialogExtend(
        {
            maximizable: true
        });
}

function navigate_block_group_extension_block_settings(el)
{
    var id = $(el).data("block-id");
    var uid = $(el).parent().parent().data("block-uid");
    var extension = $(el).data("block-extension");
    var block_group = $(el).data("block-group");

    $("<div>")
        .html('<iframe width="100%" height="100%" frameborder="0" src="?fid=blocks&act=block_group_extension_block_options&block_group=' + block_group + '&block_id=' + id + '&block_uid=' + uid + '&block_extension=' + extension + '"></iframe>')
        .dialog(
        {
            width: $(window).width() * 0.95,
            height: $(window).height() * 0.95,
            modal: true,
            title: "<img src=\"img/icons/silk/cog.png\" align=\"absmiddle\"> " + $(this).parent().prev().prev().text()
        }).dialogExtend(
        {
            maximizable: true
        });
}

$('#navigate-content-tabs .box-table').on("keydown", 'input[data-role="link"]', function()
{
    $(this).autocomplete(
    {
        source: "?fid=blocks&act=path",
        minLength: 1
    });
});

$('#navigate-content-tabs').on("click", '.nv_block_nv_link_trigger', function()
{
    var trigger = this;

    // hide "replace title" when calling the dialog from the block action
    // leave it enabled when calling the dialog from the Links table
    if($(this).parents('table.box-table').length == 0)
        $('#nv_link_dialog_replace_text').parent().css('visibility', 'hidden');

    $('#nv_link_dialog').removeClass("hidden");
    $('#nv_link_dialog').dialog({
        title: $('#nv_link_dialog').attr("title"),
        modal: true,
        width: 620,
        height: 400,
        buttons: [
            {
                text: "Ok",
                click: function(event, ui)
                {
                    // check if there is any path selected
                    if(!$("#nv_link_dialog_dynamic_path").hasClass("hidden"))
                    {
                        var input_path = $(trigger).parent().find('input:first');
                        input_path.val($("#nv_link_dialog_dynamic_path").text());
                        if($("#nv_link_dialog_replace_text").is(":visible:checked"))
                        {
                            // replace title for the current row in links table
                            input_path.parents("tr").find('input[data-role="title"]').val(
                                $("#nv_link_dialog_title").val()
                            );
                        }

                        var div_info = $(trigger).parent().find('.nv_block_nv_link_info');
                        $(div_info).find('span').text($("#nv_link_dialog_title").val());
                        $(div_info).find('img').addClass("hidden");
                        
                        if($("#nv_link_dialog_source_element").is(":checked"))
                            $(div_info).find('img[data-type=element]').removeClass("hidden");
                        else
                            $(div_info).find('img[data-type=structure]').removeClass("hidden");

                        $('#nv_link_dialog').dialog("close");
                    }
                }
            },
            {
                text: "Cancel",
                click: function(event, ui)
                {
                    $('#nv_link_dialog').dialog("close");
                }
            }
        ],
        close: function()
        {
            $('#nv_link_dialog_replace_text').parent().css('visibility', 'visible');
        },
        open: function()
        {
            /* display the current selected option in dialog; unused
            var input = $(trigger).prev();
            var path = $(input).val().split('/');
            if(path && path.length > 0 && path[0]=='nv:')
            {
                if(path[2]=='structure')
                {
                    $('#nv_link_dialog label[for=nv_link_dialog_source_structure]').click();
                    setTimeout(function()
                    {
                        $('#nv_link_dialog #nv_link_dialog_category .tree_ul').find("li[data-node-id="+path[3]+"] a:first").trigger('click');
                    }, 100);
                }
                else if(path[2]=='element')
                {
                    $('#nv_link_dialog label[for=nv_link_dialog_source_element]').click();
                    var $option = $("<option selected></option>")
                        .val(path[3])
                        .text($(trigger).parent().find('.nv_block_nv_link_info').find('span').text());
                    $("#nv_link_dialog_element").append($option).trigger('change');
                }
            }
            */
        }
    });
});

$('.editor_selector').on('click', 'i', function()
{
    var that = this;

    switch($(this).data("action"))
    {
        case 'html':
            var container = tinyMCE.get($(this).parent().attr("for")).getContainer();
            $(container).find('i.mce-i-code').parent().click();
            break;

        case 'clear':
            navigate_confirmation_dialog(
                function()
                {
                    var tinymce_editor = tinyMCE.get($(that).parent().attr("for"));
                    tinymce_editor.setContent('');
                },
                navigate_t(497, "Do you really want to erase this data?"),
                null,
                navigate_t(627, 'Remove')
            );
            break;
    }
});

/* categories selection & exclusion */
function navigate_blocks_all_categories_switch()
{
    $("#category-tree-parent").parent().hide();
    $("#exclusions-tree-parent").parent().hide();

    if($("#all_categories_2").is(":checked"))
        $("#exclusions-tree-parent").parent().show();
    else if($("#all_categories_0").is(":checked"))
        $("#category-tree-parent").parent().show();
}

$("#all_categories_0,#all_categories_1,#all_categories_2").on("click", navigate_blocks_all_categories_switch);

$("#category-tree-parent .tree_ul").jstree({
    plugins: ["changed", "types", "checkbox"],
    "types" :
    {
        "default":  {   "icon": "img/icons/silk/folder.png"    },
        "leaf":     {   "icon": "img/icons/silk/page_white.png"      }
    },
    "checkbox":
    {
        three_state: false,
        cascade: "undetermined"
    },
    "core":
    {
        dblclick_toggle: false
    }
})
.on("dblclick.jstree", function(e)
{
    e.preventDefault();
    e.stopPropagation();

    var li = $(e.target).closest("li");
    $("#category-tree-parent .tree_ul").jstree("open_node", "#" + li[0].id);

    var children_nodes = new Array();
    children_nodes.push(li);
    $(li).find("li").each(function() {
        children_nodes.push("#" + $(this)[0].id);
    });

    $("#category-tree-parent .tree_ul").jstree("select_node", children_nodes);

    return false;
})
.on("changed.jstree", function(e, data)
{
    var i, j, r = [];
    var categories = new Array();
    $("#categories").val("");

    for(i = 0, j = data.selected.length; i < j; i++)
    {
        var id = data.instance.get_node(data.selected[i]).data.nodeId;
        categories.push(id);
    }
    if(categories.length > 0)
        $("#categories").val(categories);
});

$("#exclusions-tree-parent .tree_ul").jstree({
    plugins: ["changed", "types", "checkbox"],
    "types" :
    {
        "default":  {   "icon": "img/icons/silk/folder.png"    },
        "leaf":     {   "icon": "img/icons/silk/page_white.png"      }
    },
    "checkbox":
    {
        three_state: false,
        cascade: "undetermined"
    }
})
.on("dblclick.jstree", function(e)
{
    e.preventDefault();
    e.stopPropagation();

    var li = $(e.target).closest("li");
    $("#category-tree-parent .tree_ul").jstree("open_node", "#" + li[0].id);

    var children_nodes = new Array();
    children_nodes.push(li);
    $(li).find("li").each(function() {
        children_nodes.push("#" + $(this)[0].id);
    });

    $("#category-tree-parent .tree_ul").jstree("select_node", children_nodes);

    return false;
})
.on("changed.jstree", function(e, data)
{
    var i, j, r = [];
    var categories = new Array();
    $("#exclusions").val("");

    for(i = 0, j = data.selected.length; i < j; i++)
    {
        var id = data.instance.get_node(data.selected[i]).data.nodeId;
        categories.push(id);
    }
    if(categories.length > 0)
        $("#exclusions").val(categories);
});

navigate_blocks_all_categories_switch();


/* block groups scripts */
function block_groups_onload()
{
    $("#blocks_available_accordion").accordion({
        collapsible: true,
        heightStyle: "content"
    });

    if($("#blocks_available_accordion").find("div[data-block-type=block].hidden").length > 4)
    {
        $(".navigate-block_group-accordion-info-link").removeClass("hidden");
        $(".navigate-block_group-accordion-info-link").attr("title", $("#blocks_available_accordion").find("div[data-block-type=block].hidden").length);
        $(".navigate-block_group-accordion-info-link").on("click", function()
        {
            $(this).parent().find(".hidden").removeClass("hidden");
            $(this).remove();
        });
    }
    else
    {
        $("#blocks_available_accordion").find("div[data-block-type=block].hidden").removeClass("hidden");
    }

    $( "#block_group_selected_blocks .ui-accordion-content" ).sortable({
        scroll: true,
        helper: "clone",
        placeholder: "block_group_block ui-state-highlight placeholder",

        stop: blocks_selection_update,
        receive: function(event, ui)
        {
            $(ui.helper).removeClass("ui-state-active");
            $(ui.helper).removeAttr("style");
        }
    });

    $( "#block_group_selected_blocks .ui-accordion-content" ).on("mouseenter", ".block_group_block", function()
    {
        $(this).addClass("ui-state-active");
    });

    $( "#block_group_selected_blocks .ui-accordion-content" ).on("mouseleave", ".block_group_block", function()
    {
        $(this).removeClass("ui-state-active");
    });


    $("#blocks_available_accordion .block_group_block").not(".ui-state-disabled").draggable(
        {
            appendTo: "#navigate-content-tabs",
            connectToSortable: "#block_group_selected_blocks .ui-accordion-content",
            revert: true,
            helper: "clone",
            cursor: "move",
            opacity: 0.95,
            start: function(event, ui)
            {
                $(ui.helper).addClass("ui-state-active");
            },
            stop: function(event, ui)
            {
                $( "#block_group_selected_blocks .ui-accordion-content" ).sortable("refreshPositions");
                $( "#block_group_selected_blocks .ui-accordion-content" ).sortable("refresh");
            }
        });

    $( "#block_group_selected_blocks, #blocks_available_accordion" ).disableSelection();

    // update table result onload
    blocks_selection_update();
}

function blocks_selection_update()
{
    var blocks = [];

    $("#block_group_selected_blocks .ui-accordion-content").sortable("refresh");

    $("#block_group_selected_blocks .block_group_block").each(function()
    {
        if(!$(this).data("block-uid"))
            $(this).data("block-uid", uuid.v1());

        blocks.push({
            id: $(this).data("block-id"),
            uid: $(this).data("block-uid"),
            type: $(this).data("block-type"),
            title: $(this).find("a[data-block-type-title]").find("span").text(),
            extension: $(this).data("block-extension")
        });
    });

    $("#blocks_group_selection").val(JSON.stringify(blocks));
}

function navigate_blocks_selection_remove(el)
{
    $(el).closest(".block_group_block").remove();
    blocks_selection_update();
}

function navigate_blocks_copy_from_theme_samples(element, section, language, type)
{
    var data = theme_content_samples;

    $("#navigate_blocks_copy_from_theme_samples_options").html("");

    if(!data[0]) return false;

    for(i in data)
    {
        if(data[i].file)
        {
            $("#navigate_blocks_copy_from_theme_samples_options").append(
                '<option value="'+data[i].file+'" type="'+type+'" source="file">'+data[i].title+'</option>'
            );
        }
        else if(data[i].content)
        {
            $("#navigate_blocks_copy_from_theme_samples_options").append(
                '<option value="'+$.base64.encode(data[i].content)+'" type="'+type+'" source="content">'+data[i].title+'</option>'
            );
        }
    }

    $("#navigate_blocks_copy_from_theme_samples").dialog(
        {
            title: "<img src=\"img/icons/silk/rainbow.png\" align=\"absmiddle\"> " + navigate_lang_dictionary[368], // theme
            modal: true,
            buttons:
                [
                    {
                        text: navigate_lang_dictionary[620], // Insert (at the caret/selection position)
                        icons: {
                            primary: "ui-icon-carat-1-n"
                        },
                        click: function()
                        {
                            if(type=="tinymce")
                            {
                                tinyMCE.get(element).execCommand('mceInsertContent', false, $("#navigate_blocks_copy_from_theme_samples_text_raw").html());
                            }
                            else // raw
                            {
                                $("#" + element).val($("#navigate_blocks_copy_from_theme_samples_text_raw").html() + " " + $("#" + element).html());
                            }

                            $(this).dialog("close");
                            $(window).trigger("resize");
                        }
                    },
                    {
                        text: navigate_lang_dictionary[621], // Append (at the end)
                        icons: {
                            primary: "ui-icon-arrowthickstop-1-e"
                        },
                        click: function()
                        {
                            if(type=="tinymce")
                            {
                                tinyMCE.get(element).dom.add(tinyMCE.get(element).getBody(), 'p', {class: "navigate-tinymce-temporary-placeholder"}, '--temporary placeholder--'); // attributes, content

                                tinyMCE.get(element).selection.select(tinyMCE.get(element).getBody(), true);
                                tinyMCE.get(element).selection.collapse(false);
                                tinyMCE.get(element).execCommand('mceInsertContent', false, $("#navigate_blocks_copy_from_theme_samples_text_raw").html());

                                tinyMCE.get(element).dom.remove(
                                    tinyMCE.activeEditor.dom.select('p.navigate-tinymce-temporary-placeholder')
                                );
                            }
                            else // raw
                            {
                                $("#" + element).val($("#navigate_blocks_copy_from_theme_samples_text_raw").html() + " " + $("#" + element).html());
                            }

                            $(this).dialog("close");
                            $(window).trigger("resize");
                        }
                    },
                    {
                        text: navigate_lang_dictionary[58], // cancel
                        click: function()
                        {
                            $(this).dialog("close");
                            $(window).trigger("resize");
                        }
                    }
                ],
            width: 1080,
            height: 500,
            open: function()
            {
                // load first theme sample content
                navigate_blocks_copy_from_theme_samples_preview(
                    $("#navigate_blocks_copy_from_theme_samples_options option:first").attr("value"),
                    $("#navigate_blocks_copy_from_theme_samples_options option:first").attr("type"),
                    $("#navigate_blocks_copy_from_theme_samples_options option:first").attr("source")
                );
            }
        }
    ).dialogExtend(
        {
            maximizable: true
        }
    );

    return false;
}

function navigate_blocks_copy_from_theme_samples_preview(value, type, source)
{
    if(source == "content")
    {
        $('#navigate_blocks_copy_from_theme_samples_text > *').remove();

        $('#navigate_blocks_copy_from_theme_samples_text').html(
            '<iframe width="100%" height="100" frameborder="0" src="about:blank"></iframe>'
        );

        $('#navigate_blocks_copy_from_theme_samples_text iframe').attr(
            'height',
            $('#navigate_blocks_copy_from_theme_samples_text').parent().parent().height() - 50
        );

        $("#navigate_blocks_copy_from_theme_samples_text iframe").on("load", function()
        {
            $(this).contents().find("body").html($.base64.decode(value));
        });

        // prepare content if the user wants to include it into the current editor
        $("#navigate_blocks_copy_from_theme_samples_text_raw").html(
            $($.base64.decode(value)).filter('#navigate-theme-content-sample').html()
        );
    }
    else if(source == "file")
    {
        var file = value + '?random=' + new Date().getTime();

        $('#navigate_blocks_copy_from_theme_samples_text').html(
            '<iframe width="100%" height="100" frameborder="0" src="' + NAVIGATE_URL + "/themes/" + website_theme + "/" + file + '"></iframe>'
        );

        $('#navigate_blocks_copy_from_theme_samples_text iframe').attr(
            'height',
            $('#navigate_blocks_copy_from_theme_samples_text').parent().parent().height() - 50
        );

        $("#navigate_blocks_copy_from_theme_samples_text iframe").on("load", function()
        {
            $("#navigate_blocks_copy_from_theme_samples_text iframe")
                .contents()
                .find('img')
                .each(function()
                {
                    // repair every image file source
                    if( $(this).attr("src").substring(0, 7)!='http://'  &&
                        $(this).attr("src").substring(0, 7)!='https://'
                    )
                    {
                        var newsrc = NAVIGATE_URL + "/themes/" + website_theme + "/" + $(this).attr("src");
                        $(this).attr("src", newsrc);
                    }
                });
        });

        $.get(
            NAVIGATE_URL + "/themes/" + website_theme + "/" + file,
            function(data)
            {
                var fragment_html;

                data = $.parseHTML(data);
                $(data).each(function()
                {
                    if(!fragment_html || fragment_html == "")
                    {
                        if($(this).attr("id") == "navigate-theme-content-sample")
                            fragment_html = $(this).html();
                        else
                            fragment_html = $(this).find('#navigate-theme-content-sample').html();
                    }
                });

                $("#navigate_blocks_copy_from_theme_samples_text_raw").html( fragment_html ) ;

                $("#navigate_blocks_copy_from_theme_samples_text_raw img").each(function()
                {
                    if( $(this).attr("src").substring(0, 7)!='http://'  &&
                        $(this).attr("src").substring(0, 7)!='https://'
                    )
                        $(this).attr("src", NAVIGATE_URL + "/themes/" + website_theme + "/" + $(this).attr("src"));
                });
            }
        );
    }
}