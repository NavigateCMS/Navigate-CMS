var navigatecms = {
    forms: {
        datepicker:		{ },
        imask: []
    },
    csrf_token: null,
    beforeunload: false,
    resize_callbacks: []
};
var navigate_menu_current_tab;
var navigate_lang_dictionary = Array();
var navigate_codemirror_instances = Array();
var navigate_form_conditional_properties = Array();
var navigate_form_properties_language_selected = null;
var navigate_menu_unselect_timer = null;

//var $P = new PHP_JS();
$(window).on('load', function()
{
    $("#navigate-menu").css('opacity', 1);
    $("button, input:submit, a.uibutton, div.uibutton").not(".mce-tinymce button").button();
    $(".buttonset").controlgroup();
    $(".buttonset").find('label').on('click', function()
    {
        // force buttonset to update the state on click
        // jquery doesn't count a click if the cursor moves a little
        if($(this).prev().attr("type")=="radio")
        {
            $(this).parents('.buttonset').find('input[checked]').removeAttr('checked');
            $(this).prev().attr('checked', 'checked');
        }
        else  // checkbox
        {
            // jquery ui buttonset already works as expected
            // no need to to anything else
        }

        $(this).parents('.buttonset').controlgroup('refresh');
    });
    jQuery.longclick.duration = 1000; // default longlick duration


    /* hack to make select2 work in jQuery UI dialogs; thanks to @UltCombo */
    if($.ui && $.ui.dialog && $.ui.dialog.prototype._allowInteraction)
    {
        var ui_dialog_interaction = $.ui.dialog.prototype._allowInteraction;
        $.ui.dialog.prototype._allowInteraction = function(e) {
            if ($(e.target).closest('.select2-dropdown').length) return true;
            return ui_dialog_interaction.apply(this, arguments);
        };
    }

    $(".select2").not(".select2-container").each(function(i, el)
    {
        navigate_selector_upgrade(el);
    });

	$('#navigate-content').on('mouseover', function()
	{
		clearTimeout(navigate_menu_unselect_timer);
		navigate_menu_unselect_timer = setTimeout(function()
		{
			$('#navigate-menu').tabs('option', 'active', navigate_menu_current_tab);
			$('#navigate-recent-items').slideUp();
		},
		817);
	});
	
	$('#navigate-menu').on('mouseover', function()
	{
		clearTimeout(navigate_menu_unselect_timer);
	});

	$('#navigate-menu').tabs('option', 'active', navigate_menu_current_tab);

	// MB EXTRUDER	
	$("#navigate-website-selector-top").find(".flapLabel").prepend($("#navigate-website-main-link"));	
	$("#navigate-website-main-link").css({'display': 'inline'});
	$("#navigate-website-selector-top").find(".flapLabel").css("padding-left", "0");
	$('.voice').css('display', 'block');
	$('.extruder.top .content').css('border-radius', 'none');


    // recent items list
	$('#navigate-recent-items-link').on('mouseenter', function()
	{
        // load the recent items list, if empty
        
        if($('#navigate-recent-items li').length == 0)
        {
            $('#navigate-recent-items-link').css('cursor', 'wait');

            $.getJSON(
                "?fid=dashboard&act=recent_items&limit=10",
                {}, 
                function(data)
                {
                    // in case of a duplicated request, simply ignore it
                    if($('#navigate-recent-items li').length > 0)
                    {
                        return;
                    }

                    $(data).each(function()
                    {
                        $('#navigate-recent-items').append('<li>' + this._link + '</li>');
                    });
                    
                    $('#navigate-recent-items-link').css('cursor', 'pointer');

                    $('#navigate-recent-items').css({
                        top: $('#navigate-recent-items-link').offset().top + 20,
                        left: $('#navigate-recent-items-link').offset().left + $('#navigate-recent-items-link').width() - $('#navigate-recent-items').width() - 5
                    }).show();
                    $('#navigate-recent-items').addClass('navi-ui-widget-shadow');
                    $('#navigate-recent-items').removeClass('hidden');
                    $('#navigate-recent-items').menu();
                }
            );
        }
        else
        {
            $('#navigate-recent-items').css({
                top: $('#navigate-recent-items-link').offset().top + 20,
                left: $('#navigate-recent-items-link').offset().left + $('#navigate-recent-items-link').width() - $('#navigate-recent-items').width() - 5
            }).show();
            $('#navigate-recent-items').removeClass('hidden');
            $('#navigate-recent-items').addClass('navi-ui-widget-shadow');
            $('#navigate-recent-items').menu();
        }
	});
	
	$('#navigate-recent-items').on('mouseleave', function()
	{
		$(this).fadeOut('fast');
	});


    // actions bar submenus
    $('#navigate-content-actions a.content-actions-submenu-trigger').on('mouseenter click', function(ev)
    {
        // hide all visible menus in action bar
        $('#navigate-content-actions a.content-actions-submenu-trigger').next().hide();

        if(!$(this).next().is(':visible'))
        {
            $(this).next().menu().show();
            $(this).next().addClass('navi-ui-widget-shadow');
        }

        return false;
    });

    $('#navigate-content-actions a.content-actions-submenu-trigger').on('dblclick', function(ev)
    {
        window.location.replace($(this).attr('href'));
    });

    // favorite extensions
    $('.navigate-favorites-link').on('click', function()
    {
        $('#navigate-favorite-extensions').css({
            top: $('.navigate-favorites-link').offset().top + 10,
            left: $('.navigate-favorites-link').offset().left + $('.navigate-favorites-link').width() - $('#navigate-favorite-extensions').width() - 12,
            opacity: 0.9,
            zIndex: 1000
        }).show();
    });

    $('#navigate-favorite-extensions').on('mouseleave', function()
    {
        $(this).slideUp();
    });

    $('.navigate-droppable').on("dblclick", 'img[data-src-original]', function()
    {
        navigate_image_preview($(this).attr("data-src-original"), $(this).attr("title"));
    });

    $(".navigate-hidemenu-link").on("click", function()
    {
        if($('.navigate-session').is(':visible'))
        {
            $('.navigate-session').hide();
            $('.navigate-logo').hide();
            $('#navigate-menu').hide();
            $('#navigate-website-selector-top').hide();
            $('.navigate-top').hide();
            $('.navigate-help').hide();
            $(".navigate-hidemenu-link").html("&#9660;");
        }
        else
        {
            $('#navigate-menu').show();
            $('.navigate-logo').show();
            $('.navigate-session').show();
            $('#navigate-website-selector-top').show();
            $('.navigate-top').show();
            $('.navigate-help').show();
            $(".navigate-hidemenu-link").html("&#9650;");
        }
        navigate_window_resize();
    });

    /* nv link (pathfield) dialog functions */

    $('.naviforms-pathfield-trigger').on("click", function()
    {
        var trigger = this;

        // always hide "replace title" option
        $('#nv_link_dialog_replace_text').parent().addClass('hidden');

        $('#nv_link_dialog').removeClass("hidden");
        $('#nv_link_dialog').dialog({
            title: $('#nv_link_dialog').attr("title"),
            modal: true,
            width: 620,
            height: 400,
            buttons: [
                {
                    text: navigate_t(190, "Ok"),
                    click: function(event, ui)
                    {
                        // check if there is any path selected
                        if(!$("#nv_link_dialog_dynamic_path").hasClass("hidden"))
                        {
                            var input_path = $(trigger).parent().find('input:first');
                            input_path.val($("#nv_link_dialog_dynamic_path").text());

                            if($("#nv_link_dialog_replace_text").is(":visible:checked"))
                            {

                                if(input_path.data("role")=="property-link")
                                {
                                    $(input_path).parent().prev().find('input[data-role="property-title"]').val(
                                        $("#nv_link_dialog_title").val()
                                    );
                                }
                                else if(input_path.parents("tr"))
                                {
                                    // replace title for the current row in links table
                                    input_path.parents("tr").find('input[data-role="title"]').val(
                                        $("#nv_link_dialog_title").val()
                                    );
                                }

                            }

                            var div_info = $(trigger).parent().find('.naviforms-pathfield-link-info');
                            $(div_info).find('span').text($("#nv_link_dialog_title").val());
                            $(div_info).find('img').addClass("hidden");

                            if($("#nv_link_dialog_source_element").is(":checked"))
                            {
                                $(div_info).find('img[data-type=element]').removeClass("hidden");
                            }
                            else if($("#nv_link_dialog_source_product").is(":checked"))
                            {
                                $(div_info).find('img[data-type=product]').removeClass("hidden");
                            }
                            else
                            {
                                $(div_info).find('img[data-type=structure]').removeClass("hidden");
                            }


                            $('#nv_link_dialog').dialog("close");
                        }
                    }
                },
                {
                    text: navigate_t(58, "Cancel"),
                    click: function(event, ui)
                    {
                        $('#nv_link_dialog').dialog("close");
                    }
                }
            ],
            close: function()
            {
                $(this).dialog('destroy');
                $('#nv_link_dialog').addClass("hidden");
            }
        });
    });

    // nv link dialog category tree exists?
    if($("#nv_link_dialog_category").length > 0)
    {
        // prepare category tree

        $("#nv_link_dialog_category .tree_ul").jstree(
        {
            plugins: ["changed", "types"],
            "types" :
            {
                "default":  {   "icon": "img/icons/silk/folder.png"         },
                "leaf":     {   "icon": "img/icons/silk/page_white.png"     }
            },
            "core" :
            {
                "multiple" : false
            }
        }).on("changed.jstree", function(e, data)
        {
            if(data.selected.length > 0)
            {
                var node = data.instance.get_node(data.selected[0]);
                var id = node.data.nodeId;
                var text = $(node.text).text();
                var path = node.data.nodePath;

                $("#nv_link_dialog_dynamic_path strong").html("nv://structure/" + id);

                $("#nv_link_dialog_real_path span").html(path);
                $("#nv_link_dialog_real_path").parent().removeClass("hidden");

                $("#nv_link_dialog_dynamic_path").parent().removeClass("hidden");
                $("#nv_link_dialog_replace_text").parent().removeClass("hidden");
                $("#nv_link_dialog_title").val(text);
            }
            else
            {
                $("#nv_link_dialog_real_path").parent().addClass("hidden");
                $("#nv_link_dialog_dynamic_path").parent().addClass("hidden");
                $("#nv_link_dialog_replace_text").parent().addClass("hidden");
                $("#nv_link_dialog_title").val("");
            }
        });


        // prepare element search

        $("#nv_link_dialog_element").select2(
        {
            placeholder: $("#nv_link_dialog_element").data("placeholder"),
            minimumInputLength: 1,
            ajax: {
                url: $("#nv_link_dialog_element").data("ajax-url"),
                dataType: "json",
                delay: 100,
                data: function(params)
                {
                    return {
                        title: params.term,
                        embedding: 0,
                        nd: new Date().getTime(),
                        page_limit: 30, // page size
                        page: params.page // page number
                    };
                },
                processResults: function (data, params)
                {
                    params.page = params.page || 1;
                    return {
                        results: data.items,
                        pagination: { more: (params.page * 30) < data.total_count }
                    };
                }
            },
            templateSelection: function(row)
            {
                if(row.id)
                {
                    $("#nv_link_dialog_real_path span").html(row.path);
                    $("#nv_link_dialog_dynamic_path strong").html("nv://element/" + row.id);

                    $("#nv_link_dialog_real_path").parent().removeClass("hidden");
                    $("#nv_link_dialog_dynamic_path").parent().removeClass("hidden");
                    $("#nv_link_dialog_replace_text").parent().removeClass("hidden");
                    $("#nv_link_dialog_title").val(row.text);

                    return row.text + " <helper style='opacity: .5;'>#" + row.id + "</helper>";
                }
                else
                {
                    $("#nv_link_dialog_real_path").parent().addClass("hidden");
                    $("#nv_link_dialog_dynamic_path").parent().addClass("hidden");
                    $("#nv_link_dialog_replace_text").parent().addClass("hidden");
                    $("#nv_link_dialog_title").val("");
                    return row.text;
                }
            },
            escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
            triggerChange: true,
            allowClear: true
        });

        // prepare product search
        $("#nv_link_dialog_product").select2(
        {
            placeholder: $("#nv_link_dialog_product").data("placeholder"),
            minimumInputLength: 1,
            ajax: {
                url: $("#nv_link_dialog_product").data("ajax-url"),
                dataType: "json",
                delay: 100,
                data: function(params)
                {
                    return {
                        title: params.term,
                        embedding: 0,
                        nd: new Date().getTime(),
                        page_limit: 30, // page size
                        page: params.page // page number
                    };
                },
                processResults: function (data, params)
                {
                    params.page = params.page || 1;
                    return {
                        results: data.items,
                        pagination: { more: (params.page * 30) < data.total_count }
                    };
                }
            },
            templateSelection: function(row)
            {
                if(row.id)
                {
                    $("#nv_link_dialog_real_path span").html(row.path);
                    $("#nv_link_dialog_dynamic_path strong").html("nv://element/" + row.id);

                    $("#nv_link_dialog_real_path").parent().removeClass("hidden");
                    $("#nv_link_dialog_dynamic_path").parent().removeClass("hidden");
                    $("#nv_link_dialog_replace_text").parent().removeClass("hidden");
                    $("#nv_link_dialog_title").val(row.text);

                    return row.text + " <helper style='opacity: .5;'>#" + row.id + "</helper>";
                }
                else
                {
                    $("#nv_link_dialog_real_path").parent().addClass("hidden");
                    $("#nv_link_dialog_dynamic_path").parent().addClass("hidden");
                    $("#nv_link_dialog_replace_text").parent().addClass("hidden");
                    $("#nv_link_dialog_title").val("");
                    return row.text;
                }
            },
            escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
            triggerChange: true,
            allowClear: true
        });
    }

    /* other functions */

    $(document.body).on('mousedown', navigate_hide_context_menus);

	$(window).on('resize focus', navigate_window_resize);
    $("#navigate-content-tabs").on("tabsactivate", navigate_window_resize);

    setTimeout(function() { $(window).trigger('resize'); }, 30);

	navigate_status(navigate_t(42, "Ready"), "ready");
	
	setInterval(navigate_periodic_event, 60000); // each 60 seconds

    navigate_window_resize();
});

$(window.body).unload(function()
{
	navigate_unselect_text();
	navigate_status(navigate_t(6, "Loading") + "...", "loader");
});

// add :focus selector
$.expr[':'].focused = function(a)
{ 
	return (a == document.activeElement); 
};

/* Backspace and cancel moving back to history */
$('html').keydown(function(e)
{
	if(e.keyCode == 8)
	{
		if( $("*:focused").get(0).tagName != 'INPUT'	&&
			$("*:focused").get(0).tagName != 'TEXTAREA'	)
		{	
			navigate_status(navigate_lang_dictionary[389], 'ready', false);	// backspace key protection
			setTimeout(function()
			{
				navigate_status(navigate_lang_dictionary[42], "ready"); // ready	
			},
			500);
			return false;
		}
	}
});


/**
 * Navigate CMS custom javascript functions
 */

function navigate_window_resize()
{
	$('#navigate-content').css({ 'height': 0 });
	$('#navigate-status').css({ 'width': 0 });		
	$("#navigate-content-safe").css({ display: 'none' });
	
	$(".ui-jqgrid").css('display', 'none');
	
	$('#navigate-content-tabs').children('div').css({ 'height': 0 });			

	$('#navigate-status').css({ 'width': $(document).width() - 18 });

    $('#navigate-menu > div').css({ 'width': $(document).width() - 40 });
	
	if($('#navigate-content').position())
	{
        var navigate_status_height = $('#navigate-status').height();
        if(!navigate_status_height) navigate_status_height = 0;

		$('#navigate-content-tabs').children('div').css({
            "height": $(document).height() - navigate_status_height - $('#navigate-content').position().top - 125
        });
		
		var padding_recalc = 16 - (parseInt($('#navigate-content').css("padding-top")) * 2);
		
		$('#navigate-content').css({
            "height": $(document).height() - navigate_status_height - $('#navigate-content').position().top - 35 + padding_recalc
        });
	
		$(".ui-jqgrid").css('display', 'block');
        $("table.ui-jqgrid-btable").each(function(i, el)
        {
            if($(el).setGridHeight)
            {
                if($(el).parents('.ui-tabs-panel').length > 0)
                {
                    // table inside a tab
                    $(el).setGridHeight($(el).parents('.ui-tabs-panel').height() - 50);
                    $(el).setGridWidth($(el).parents('.ui-tabs-panel').width());
                }
                else
                {
                    // table as main layout
                    $(el).setGridHeight($('#navigate-content').height() - 80);
                    $(el).setGridWidth($('#navigate-content').width());
                }
            }
        });

		$("#navigate-content-safe").css({
            display: 'block',
            height: $('#navigate-content').height() - 30
		});
	}

    if($('.navibrowse,.navigrid').length > 0)
    {
        $('.navibrowse,.navigrid').height($('.navibrowse,.navigrid').parent().height() - 10);

        if( $('.navibrowse-items,.navigrid-items').height() < $('.navibrowse,.navigrid').height() )
        {
            $('.navibrowse-items,.navigrid-items').height($('.navibrowse,.navigrid').height() - $('.navibrowse-path').height() - 10);
        }
    }

    for(i in navigatecms.resize_callbacks)
    {
        if(typeof(navigatecms.resize_callbacks[i])=="function")
            navigatecms.resize_callbacks[i]();
    }
}

function navigate_image_preview(src, title)
{
    $('<div style="text-align: center;"><img style="width: 100%; object-fit: scale-down; object-position: 50% 0;" src="'+src+'" /></div>').dialog({
        autoOpen: true,
        modal: true,
        width: '92%',
        height: $(window).height() * 0.9,
        title: title
    }).dialogExtend(
    {
        maximizable: true
    });
}

function navigate_status(text, img, status, percentage)
{
	var ns = $("#navigate-status-info");

	if(text=="ready")
		text = navigate_lang_dictionary[42]; // ready

	if(img=='loader')	img = 'img/loader.gif';
	if(img=='ready')	img = 'img/icons/silk/record_green.png';
    if(img=='error')	img = 'img/icons/silk/error.png';
	
	if(img!=null)
		$(ns).html('<img src="' + img + '" width="16px" height="16px" align="top" /> ' + text);
	else
		$(ns).html(text);

    $('#navigate-status').removeClass("ui-state-active ui-state-error ui-state-highlight");
    $("#navigate-status-progressbar").remove();

    if(status=='error')
        $('#navigate-status').addClass("ui-state-error");
    else if(status=='highlight')
        $('#navigate-status').addClass("ui-state-highlight");
    else if(status==true || status=="true" || status=='active')
		$('#navigate-status').addClass("ui-state-active");

    if(percentage && parseInt(percentage) > 0)
    {
        $(ns).after('<div id="navigate-status-progressbar"><div id="navigate-status-progressbar-label"></div></div>');
        $("#navigate-status-progressbar" ).progressbar({
            value: parseInt(percentage)
        });
        $('#navigate-status-progressbar-label').text(percentage + '%');
    }
}

function navigate_notification(text, sticky, css_icon)
{
	$.jGrowl.defaults.position = "center";
	$.jGrowl.defaults.closerTemplate = '<div><i class="fa fa-trash fa-lg"></i></div>';

    if(!sticky || sticky=="" || sticky==null)
        sticky = false;

    var icon = "";
    if(css_icon)
        icon = '<i class="' + css_icon + '"></i> ';

	$.jGrowl(
        icon+text,
        {
            life: 4000,
            sticky: sticky,
            open: function()
            {
                 setTimeout(function()
                 {
                     $(".jGrowl-notification").css({"background-repeat": "repeat"});
                 }, 50);
            }
	});	
	$("#jGrowl").css({"top": "116px"});		
}

function navigate_t(id, text)
{
	if(navigate_lang_dictionary && navigate_lang_dictionary[id]!=null)
    {
        return navigate_lang_dictionary[id];
    }
	else
    {
        return text;
    }
}

function navigate_tinymce_add_content_event(editor_id, file_id, media, mime, web_id, element, meta)
{
    var content_added = false;
    // sync ajax
    $.ajax(
        {
            url: "?mute&fid=extensions&act=tinymce_add_content_event",
            dataType: "json",
            async: false,
            data: {
                file_id: file_id,
                media: media,
                mime: mime
            },
            success: function(json_rs)
            {
                if(json_rs != "" && Object.keys(json_rs).length > 0)
                {
                    var buttons_html = "";
                    for(extension_name in json_rs)
                    {
                        buttons_html += '<button data-extension="'+extension_name+'" data-content="'+$.base64.encode(json_rs[extension_name]['out'])+'">' +
                                            '<img src="plugins/'+extension_name+'/thumbnail.png" /><br /><br />' +
                                            json_rs[extension_name]['title'] +
                                        '</button>';
                    }

                    buttons_html += '<button data-extension="default">' +
                                        '<i class="fa fa-fw fa-3x fa-paste"></i><br /><br />(' + navigate_t(582, 'default') + ')'
                                    '</button>';

                    var embed_dialog_html = '' +
                        '<div class="embed_dialog_extensions">' +
                            buttons_html +
                        '</div>';

                    // close any open dialog
                    $(".ui-dialog-content").dialog().dialog("close");

                    $(embed_dialog_html).dialog({
                        modal: true,
                        title: '<i class="fa fa-lg fa-document"></i> ' + navigate_t(620, "Insert")
                    });

                    $(".embed_dialog_extensions button")
                        .button()
                        .on("click", function()
                        {
                            if($(this).data('extension') == 'default')
                            {
                                navigate_tinymce_add_content(editor_id, file_id, media, mime, web_id, element, meta, true);
                            }
                            else
                            {
                                var html = $.base64.decode($(this).data('content'));
                                var editor = tinyMCE.get(editor_id);

                                var selection_active  = (editor.selection.getContent({format : 'text'})!="");
                                var selection_content = editor.selection.getContent({format: 'html'});

                                   if(selection_active)
                                   {
                                       tinyMCE.activeEditor.selection.setContent(html);
                                       tinyMCE.activeEditor.execCommand('mceCleanup', false);
                                   }
                                   else
                                   {
                                       tinyMCE.get(editor_id).execCommand('mceInsertContent', false, html);
                                       tinyMCE.get(editor_id).execCommand('mceCleanup', false);
                                   }
                            }

                            $('.embed_dialog_extensions').dialog('close');
                        });

                    content_added = true;
                }
            }
        }
    );

    return content_added;
}

function navigate_tinymce_add_content(editor_id, file_id, media, mime, web_id, element, meta, do_not_trigger_event)
{
    if(!do_not_trigger_event)
    {
        // check if there is an extension to threat this adding content event
        var content_added = navigate_tinymce_add_content_event(editor_id, file_id, media, mime, web_id, element, meta);
        if(content_added)
        {
            return;
        }
    }

    var editor = tinyMCE.get(editor_id);
	var html = '';
    var embed_dialog = false;

	var selection_active  = (editor.selection.getContent({format : 'text'})!="");
	var selection_content = editor.selection.getContent({format: 'html'});

    switch(media)
    {
        case 'image':
            var max_width = $('#' + editor_id + '_ifr').contents().find('body').width();
            var or_styles = '';

            if($($.parseHTML(editor.selection.getContent({format: 'raw'}))).is('img'))
            {
                // if tinyMCE has something selected and it is an image, read its dimensions and apply them to the new image
                var max_width = $(editor.selection.getContent({format: 'raw'})).width();
                var max_height = $(editor.selection.getContent({format: 'raw'})).height();

                // if we can have a real img object, check the dimensions applied right now
                if($(editor.selection.getContent())[0])
                {
                    max_width = $(editor.selection.getContent())[0].width;
                    max_height = $(editor.selection.getContent())[0].height;
                }

                // try an alternative method (Chrome browser work around)
                if(!max_width || max_width==0)
                {
                    max_width = $($(editor.selection.getContent({format: 'raw'}))[0]).attr('width');
                }

                if(!max_width || max_height==0)
                {
                    max_height = $($(editor.selection.getContent({format: 'raw'}))[0]).attr('height');
                }

                var or_styles = ' style="' + $(editor.selection.getContent({format: 'raw'}))[0].style.cssText + '" ';

                if($(editor.selection.getContent({format: 'raw'}))[0].hasAttributes())
                {
                    var or_attrs = $(editor.selection.getContent({format: 'raw'}))[0].attributes;
                    for(var i = or_attrs.length - 1; i >= 0; i--)
                    {
                        if(or_attrs[i].name!='style' && or_attrs[i].name!='width' && or_attrs[i].name!='height' && or_attrs[i].name!='src')
                        {
                            or_styles += ' ' + or_attrs[i].name + '="' + or_attrs[i].value + '" ';
                        }
                    }
                }

                embed_dialog = true;
            }

            var image_width;
            var image_height;
            var title;
            var alt;

            if(meta)
            {
                image_width = meta.width;
                image_height = meta.height;
                title = meta.title;
                alt = meta.alt;
            }
            else
            {
                image_width = $(element).attr("image-width");
                image_height = $(element).attr("image-height");
                title = $.base64.decode($(element).attr('image-title'));
                alt = $.base64.decode($(element).attr('image-description'));
            }

            var body_width = $(editor.contentAreaContainer).width() - 37;

            if(max_width==0 || max_width > image_width)
            {
                max_width = image_width;
            }

            if(max_height==0 || max_height > image_height)
            {
                max_height = image_height;
            }

            var scaled_height = Math.ceil((max_width * image_height) / image_width);
            var scaled_width = Math.ceil((max_height * image_width) / image_height);

            var active_editor_lang = editor_id;
            if(active_editor_lang.indexOf("-") > 0)
            {
                active_editor_lang = active_editor_lang.split("-").pop();
            }

            if(typeof(title)=='string')
            {
                title = $.parseJSON(title);
            }

            if (title && title[active_editor_lang])
            {
                title = title[active_editor_lang];
            }
            else
            {
                title = "";
            }

            if(typeof(alt)=='string')
            {
                alt = $.parseJSON(alt);
            }

            if (alt && alt[active_editor_lang])
            {
                alt = alt[active_editor_lang];
            }
            else
            {
                alt = "";
            }

            if(embed_dialog)
            {
                var embed_dialog_html = '' +
                    '<div class="embed_dialog_img">' +
                        '<button action="same_width">' +
                            '<i class="fa fa-fw fa-3x fa-arrows-h"></i><br /><br />' + navigate_t(583, "Same width") + ' (' + navigate_t(582, "default") + ')' +
                        '</button>' +
                        '<button action="same_height">' +
                            '<i class="fa fa-fw fa-3x fa-arrows-v"></i><br /><br />' + navigate_t(584, "Same height") +
                        '</button>' +
                        '<button action="same_wahs">' +
                            '<i class="fa fa-fw fa-3x fa-arrows"></i><br /><br />' + navigate_t(585, "Same width & height (scaled)") +
                        '</button>' +
                        '<button action="same_wahc">' +
                            '<i class="fa fa-fw fa-3x fa-arrows-alt"></i><br /><br />' + navigate_t(586, "Same width & height (cropped)") +
                        '</button>' +
                        '<button action="full_width">' +
                            '<i class="fa fa-fw fa-3x fa-expand"></i><br /><br />' + navigate_t(587, "Full available width") +
                        '</button>' +
                    '</div>';

                // close any open dialog
                $(".ui-dialog-content").dialog().dialog("close");

                $(embed_dialog_html).dialog({
                    modal: true,
                    title: '<i class="fa fa-lg fa-image"></i> ' + navigate_t(588, "Replace image")
                });

                $(".embed_dialog_img button")
                    .button()
                    .on("click", function()
                    {
                        switch($(this).attr("action"))
                        {
                            case "same_width":
                                html = '<img src="'+NAVIGATE_DOWNLOAD+'?wid=' + web_id + '&id=' + file_id + '" ' +
                                    (!title?            '' : ' title="' + title + '" ') +
                                    (!alt?              '' : ' alt="' + alt + '" ' ) +
                                    ' width="' + max_width + '"' +
                                    ' height="' + scaled_height + '"' +
                                    ' ' + or_styles +
                                    ' />';
                                break;

                            case "same_height":
                                html = '<img src="'+NAVIGATE_DOWNLOAD+'?wid=' + web_id + '&id=' + file_id + '" ' +
                                    (!title?            '' : ' title="' + title + '" ') +
                                    (!alt?              '' : ' alt="' + alt + '" ' ) +
                                    ' width="' + scaled_width + '"' +
                                    ' height="' + max_height + '"' +
                                    ' ' + or_styles +
                                    ' />';
                                break;

                            case "same_wahs":
                                html = '<img src="'+NAVIGATE_DOWNLOAD+'?wid=' + web_id + '&id=' + file_id + '&width='+max_width+'&height='+max_height+'&border=true" ' +
                                    (!title?            '' : ' title="' + title + '" ') +
                                    (!alt?              '' : ' alt="' + alt + '" ' ) +
                                    ' width="' + max_width + '"' +
                                    ' height="' + max_height + '"' +
                                ' ' + or_styles +
                                ' />';
                                break;

                            case "same_wahc":
                                html = '<img src="'+NAVIGATE_DOWNLOAD+'?wid=' + web_id + '&id=' + file_id + '&width='+max_width+'&height='+max_height+'&border=false" ' +
                                    (!title?            '' : ' title="' + title + '" ') +
                                    (!alt?              '' : ' alt="' + alt + '" ' ) +
                                    ' width="' + max_width + '"' +
                                    ' height="' + max_height + '"' +
                                    ' ' + or_styles +
                                    ' />';
                                break;

                            case "full_width":
                                html = '<img src="'+NAVIGATE_DOWNLOAD+'?wid=' + web_id + '&id=' + file_id + '" ' +
                                    (!title?            '' : ' title="' + title + '" ') +
                                    (!alt?              '' : ' alt="' + alt + '" ' ) +
                                    ' width="' + body_width + '"' +
                                    ' ' + or_styles +
                                    ' />';
                                break;
                        }

                        tinyMCE.activeEditor.selection.setContent(html);
                        tinyMCE.activeEditor.execCommand('mceCleanup', false);

                        $('.embed_dialog_img').dialog('close');
                    });
            }
            else
            {
                html = '<img src="'+NAVIGATE_DOWNLOAD+'?wid=' + web_id + '&id=' + file_id + '" ' +
                        (!title?            '' : ' title="' + title + '" ') +
                        (!alt?              '' : ' alt="' + alt + '" ' ) +
                        (!max_width?        '' : ' width="' + max_width + '" ') +
                        (!scaled_height?    '' : ' height="' + scaled_height + '" ') +
                        '" ' + or_styles +
                    ' />';
            }
            break;

        case 'video':
            if(selection_content=='') selection_content = '[video]';
            html = '<a rel="video" navigate="navigate" type="'+mime+'" href="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline">'+selection_content+'</a>';
            /*
            html = '<video controls="controls">';
            html+= '<source src="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline" />';
            html+= '<a rel="video" href="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=attachment">'+selection_content+'</a>';
            html+= '</video>';
            */
            break;

        case 'flash':
            html = '<object data="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline" type="application/x-shockwave-flash" width="400" height="225">';
            html+= '<param name="movie" value="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline" />';
            html+= '<param name="bgcolor" value="transparent" />';
            html+= '<param name="height" value="225" />';
            html+= '<param name="width" value="400" />';
            html+= '<param name="quality" value="high" />';
            html+= '<param name="menu" value="false" />';
            html+= '<param name="allowscriptaccess" value="samedomain" />';
            html+= '</object>';
            break;

        case 'audio':
            if(selection_content=='') selection_content = '[audio]';
            html = '<a rel="audio" navigate="navigate" type="'+mime+'" href="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline">'+selection_content+'</a>';
            /*
            html = '<audio controls="controls">';
            html+= '<source src="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline">';
            html+= '<a rel="audio" href="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline">'+selection_content+'</a>';
            html+= '</audio>';
            */
            break;

        default:
            if(selection_content=='')
                selection_content = '[' + navigate_t(82, "File") + ']';

            if($($.parseHTML(selection_content)).is("A"))
                selection_content = $(selection_content).text();

            html = '<a rel="file" href="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline"> ' + selection_content + '</a>';
    }

    if(embed_dialog)
    {
        return;
    }

    if(selection_active)
    {
        tinyMCE.activeEditor.selection.setContent(html);
        tinyMCE.activeEditor.execCommand('mceCleanup', false);
    }
    else
    {
        tinyMCE.get(editor_id).execCommand('mceInsertContent', false, html);
        tinyMCE.get(editor_id).execCommand('mceCleanup', false);
    }
}

function navigate_tinymce_move_cursor_to_end(editor_id) 
{
    var editor = tinyMCE.get(editor_id);
    editor.execCommand("selectall", false, null);
    if (tinyMCE.isIE)
	{
        rng = editor.selection.getRng();
        rng.collapse(false);
        rng.select();
    }
    else {
        sel = editor.selection();
        sel.collapse();
    }
}

// Thanks to Ben for the following tinyMCE get/set cursor functions
// http://blog.squadedit.com/tinymce-and-cursor-position/

function navigate_tinymce_get_cursor_position(editor_id)
{
    var editor = tinyMCE.get(editor_id);

    //set a bookmark so we can return to the current position after we reset the content later
    var bm = editor.selection.getBookmark(0);

    //select the bookmark element
    var selector = "[data-mce-type=bookmark]";
    var bmElements = editor.dom.select(selector);

    //put the cursor in front of that element
    editor.selection.select(bmElements[0]);
    editor.selection.collapse();

    //add in my special span to get the index...
    //we won't be able to use the bookmark element for this because each browser will put id and class attributes in different orders.
    var elementID = "######cursor######";
    var positionString = '<span id="'+elementID+'"></span>';
    editor.selection.setContent(positionString);

    //get the content with the special span but without the bookmark meta tag
    var content = editor.getContent({format: "html"});
    //find the index of the span we placed earlier
    var index = content.indexOf(positionString);

    //remove my special span from the content
    editor.dom.remove(elementID, false);

    //move back to the bookmark
    editor.selection.moveToBookmark(bm);

    return index;
}

function navigate_tinymce_set_cursor_position(editor_id, index)
{
    var editor = tinyMCE.get(editor_id);

    //get the content in the editor before we add the bookmark...
    //use the format: html to strip out any existing meta tags
    var content = editor.getContent({format: "html"});

    //split the content at the given index
    var part1 = content.substr(0, index);
    var part2 = content.substr(index);

    //create a bookmark... bookmark is an object with the id of the bookmark
    var bookmark = editor.selection.getBookmark(0);

    //this is a meta span tag that looks like the one the bookmark added... just make sure the ID is the same
    var positionString = '<span id="'+bookmark.id+'_start" data-mce-type="bookmark" data-mce-style="overflow:hidden;line-height:0px"></span>';
    //cram the position string inbetween the two parts of the content we got earlier
    var contentWithString = part1 + positionString + part2;

    //replace the content of the editor with the content with the special span
    //use format: raw so that the bookmark meta tag will remain in the content
    editor.setContent(contentWithString, ({format: "raw"}));

    //move the cursor back to the bookmark
    //this will also strip out the bookmark metatag from the html
    editor.selection.moveToBookmark(bookmark);

    //return the bookmark just because
    return bookmark;
}


function navigate_unselect_text() 
{	
	var sel;
	if(document.selection && document.selection.empty)
	{
		document.selection.empty();
	}
	else if(window.getSelection) 
	{
		sel=window.getSelection();
		if(sel && sel.removeAllRanges)
			sel.removeAllRanges();
	}
	
	$(document.body).blur().focus();
}

function navigate_beforeunload_confirmation(event)
{
    navigate_unselect_text();

    if(navigate.beforeunload)
    {
        var confirmationMessage = "\o/";
        event.returnValue = confirmationMessage;        // Gecko, Trident, Chrome 34+
        return confirmationMessage;                     // Gecko, WebKit, Chrome <34
    }
    return;
}

function navigate_beforeunload_register()
{
    if(!navigate.beforeunload)
    {
        window.addEventListener("beforeunload", navigate_beforeunload_confirmation, true);
        navigate.beforeunload = true;
    }
}

function navigate_beforeunload_unregister()
{
    navigate.beforeunload = false;
    window.removeEventListener("beforeunload", navigate_beforeunload_confirmation, true);
}

function navigate_tabform_submit(formNum)
{
    // remove beforeunload warning, if any
    navigate_beforeunload_unregister();

	var tab = parseInt($('#navigate-content-tabs').children('div:visible').attr('id').replace("navigate-content-tabs-", "")) - 1;
	if(tab < 0) tab = 0;
	var url = $('#navigate-content').find('form').eq(formNum).attr('action');	
	$('#navigate-content').find('form').eq(formNum).attr('action', $.query.load(url).set('tab', tab));
	$('#navigate-content').find('form').eq(formNum).submit();	
}

function navigate_tabform_language_selector(el)
{
    // identify language selected
    var code = $("#"+$(el).attr("for")).val();
    navigate_form_properties_language_selected = code;

    if(!code || code=="")
    {
        $(el).parents(".ui-tabs-panel").find("div[lang]").show();
    }
    else
    {
        if($(el).parents(".ui-tabs-panel").length > 0)
        {
            $(el).parents(".ui-tabs-panel").find("div[lang]").hide();
            $(el).parents(".ui-tabs-panel").find("div[lang="+code+"]").show();
        }
        else
        {
            // no tabs, try to find the nearest fields to show/hide
            $(el).parent().parent().parent().find('div.navigate-form-row[lang]').hide();
            $(el).parent().parent().parent().find('div.navigate-form-row[lang="'+code+'"]').show();
        }
    }

    $(navigate_form_conditional_properties).each(function() { this.trigger("change", code); });
    $(navigate_codemirror_instances).each(function() { this.refresh(); } );
}

function navigate_tabform_conditional_property(property_id, property_conditional, allowed_values)
{
    $(document).ready(function()
    {
        var prop_check = $("form[name=navigate-content-form]").find("[id^=property-" + property_conditional + "]").on(
            "change keyup click blur",
            function(event)
            {
                var conditional_value = $(this).val();
                var conditional_lang = $(this).parent(".navigate-form-row").attr("lang");

                if($(this).attr("type")=="checkbox")
                {
                    conditional_value = 0;
                    if($(this).is(":checked"))
                        conditional_value = 1;
                }

                // now, for each property with the assigned id (may be multilanguage)...
                $("div[nv_property=" + property_id + "]").each(function()
                {
                    var property_lang = $(this).attr("lang");

                    // if the current property is multilanguage and the language is not the same of the condition,
                    // or if the current prop is multi and the conditional is also multi
                    // do nothing, the property will be treated in another loop
                    if( (typeof(property_lang) != "undefined" && (typeof(conditional_lang) != "undefined") && conditional_lang != property_lang ) )
                    {
                        return;
                    }

                    // first we hide the property
                    $(this).hide();

                    // then we check if the property has to be shown right now
                    if( $.inArray(String(conditional_value), allowed_values) >= 0 )
                    {
                        if( typeof(property_lang) == "undefined")
                        {
                            $("div[nv_property=" + property_id + "]").show();
                        }
                        else if(
                            !navigate_form_properties_language_selected ||
                            navigate_form_properties_language_selected == property_lang
                        )
                        {
                            $("div[nv_property=" + property_id + "][lang="+property_lang+"]").show();
                        }
                    }

                });
            });

        navigate_form_conditional_properties.push(prop_check);
        prop_check.trigger("change");
    });
}

function navigate_periodic_event()
{
	if(phpjs_function_exists('navigate_periodic_event_delegate'))
	{
		navigate_periodic_event_delegate();
	}
	else
	{
		// session keep alive standard call
		$.ajax({
		  async: true,
		  url: NAVIGATE_APP + '?fid=keep_alive'
		});
	}
}

function navigate_hide_context_menus(e)
{
    // trigger click on inner li > a
    if(e && $(e.target).hasClass("ui-menu-item"))
    {
        $(e.target).find('a:first').click();
        var href = $(e.target).find('a:first').attr("href");
        if(href && href!="" && href!="#")
            window.location.replace(href);
    }

    setTimeout(function()
    {
        $(".ui-menu").each(function()
        {
            $(this).trigger('menuclose');
            $(this).fadeOut('fast');
        });

    }, 50);
}

function phpjs_function_exists(function_name)
{
    // Checks if the function exists  
    // 
    // version: 1009.2513
    // discuss at: http://phpjs.org/functions/function_exists    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Steve Clay
    // +   improved by: Legaev Andrey
    // *     example 1: function_exists('isFinite');
    // *     returns 1: true    
    if (typeof function_name == 'string')
	{
        return (typeof this.window[function_name] == 'function');
    } 
	else
	{
        return (function_name instanceof Function);
    }
}

function phpjs_implode(glue, pieces) 
{
    // Joins array elements placing glue string between items and return one string  
    // 
    // version: 1008.1718
    // discuss at: http://phpjs.org/functions/implode    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Waldo Malqui Silva
    // +   improved by: Itsacon (http://www.itsacon.net/)
    // +   bugfixed by: Brett Zamir (http://brett-zamir.me)
    // *     example 1: implode(' ', ['Kevin', 'van', 'Zonneveld']);    // *     returns 1: 'Kevin van Zonneveld'
    // *     example 2: implode(' ', {first:'Kevin', last: 'van Zonneveld'});
    // *     returns 2: 'Kevin van Zonneveld'
    var i = '', retVal='', tGlue='';
    if (arguments.length === 1) {        pieces = glue;
        glue = '';
    }
    if (typeof(pieces) === 'object') {
        if (pieces instanceof Array) {            return pieces.join(glue);
        }
        else {
            for (i in pieces) {
                retVal += tGlue + pieces[i];                tGlue = glue;
            }
            return retVal;
        }
    }    else {
        return pieces;
    }
}

function phpjs_nl2br(str, is_xhtml)
{
    // Converts newlines to HTML line breaks
    //
    // version: 1109.2015
    // discuss at: http://phpjs.org/functions/nl2br    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Philip Peterson
    // +   improved by: Onno Marsman
    // +   improved by: Atli Þór
    // +   bugfixed by: Onno Marsman    // +      input by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Brett Zamir (http://brett-zamir.me)
    // +   improved by: Maximusya
    // *     example 1: nl2br('Kevin\nvan\nZonneveld');    // *     returns 1: 'Kevin\nvan\nZonneveld'
    // *     example 2: nl2br("\nOne\nTwo\n\nThree\n", false);
    // *     returns 2: '<br>\nOne<br>\nTwo<br>\n<br>\nThree<br>\n'
    // *     example 3: nl2br("\nOne\nTwo\n\nThree\n", true);
    // *     returns 3: '\nOne\nTwo\n\nThree\n'

    var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '' : '<br>';

    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}

function phpjs_json_encode(mixed_val)
{
    // http://kevin.vanzonneveld.net
    // +      original by: Public Domain (http://www.json.org/json2.js)
    // + reimplemented by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +      improved by: Michael White
    // +      input by: felix
    // +      bugfixed by: Brett Zamir (http://brett-zamir.me)
    // *        example 1: json_encode(['e', {pluribus: 'unum'}]);
    // *        returns 1: '[\n    "e",\n    {\n    "pluribus": "unum"\n}\n]'
    /*
     http://www.JSON.org/json2.js
     2008-11-19
     Public Domain.
     NO WARRANTY EXPRESSED OR IMPLIED. USE AT YOUR OWN RISK.
     See http://www.JSON.org/js.html
     */
    var retVal, json = this.window.JSON;
    try {
        if (typeof json === 'object' && typeof json.stringify === 'function') {
            retVal = json.stringify(mixed_val); // Errors will not be caught here if our own equivalent to resource
            //  (an instance of PHPJS_Resource) is used
            if (retVal === undefined) {
                throw new SyntaxError('json_encode');
            }
            return retVal;
        }

        var value = mixed_val;

        var quote = function (string) {
            var escapable = /[\\\"\u0000-\u001f\u007f-\u009f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g;
            var meta = { // table of character substitutions
                '\b': '\\b',
                '\t': '\\t',
                '\n': '\\n',
                '\f': '\\f',
                '\r': '\\r',
                '"': '\\"',
                '\\': '\\\\'
            };

            escapable.lastIndex = 0;
            return escapable.test(string) ? '"' + string.replace(escapable, function (a) {
                var c = meta[a];
                return typeof c === 'string' ? c : '\\u' + ('0000' + a.charCodeAt(0).toString(16)).slice(-4);
            }) + '"' : '"' + string + '"';
        };

        var str = function (key, holder) {
            var gap = '';
            var indent = '    ';
            var i = 0; // The loop counter.
            var k = ''; // The member key.
            var v = ''; // The member value.
            var length = 0;
            var mind = gap;
            var partial = [];
            var value = holder[key];

            // If the value has a toJSON method, call it to obtain a replacement value.
            if (value && typeof value === 'object' && typeof value.toJSON === 'function') {
                value = value.toJSON(key);
            }

            // What happens next depends on the value's type.
            switch (typeof value) {
                case 'string':
                    return quote(value);

                case 'number':
                    // JSON numbers must be finite. Encode non-finite numbers as null.
                    return isFinite(value) ? String(value) : 'null';

                case 'boolean':
                case 'null':
                    // If the value is a boolean or null, convert it to a string. Note:
                    // typeof null does not produce 'null'. The case is included here in
                    // the remote chance that this gets fixed someday.
                    return String(value);

                case 'object':
                    // If the type is 'object', we might be dealing with an object or an array or
                    // null.
                    // Due to a specification blunder in ECMAScript, typeof null is 'object',
                    // so watch out for that case.
                    if (!value) {
                        return 'null';
                    }
                    if ((this.PHPJS_Resource && value instanceof this.PHPJS_Resource) || (window.PHPJS_Resource && value instanceof window.PHPJS_Resource)) {
                        throw new SyntaxError('json_encode');
                    }

                    // Make an array to hold the partial results of stringifying this object value.
                    gap += indent;
                    partial = [];

                    // Is the value an array?
                    if (Object.prototype.toString.apply(value) === '[object Array]') {
                        // The value is an array. Stringify every element. Use null as a placeholder
                        // for non-JSON values.
                        length = value.length;
                        for (i = 0; i < length; i += 1) {
                            partial[i] = str(i, value) || 'null';
                        }

                        // Join all of the elements together, separated with commas, and wrap them in
                        // brackets.
                        v = partial.length === 0 ? '[]' : gap ? '[\n' + gap + partial.join(',\n' + gap) + '\n' + mind + ']' : '[' + partial.join(',') + ']';
                        gap = mind;
                        return v;
                    }

                    // Iterate through all of the keys in the object.
                    for (k in value) {
                        if (Object.hasOwnProperty.call(value, k)) {
                            v = str(k, value);
                            if (v) {
                                partial.push(quote(k) + (gap ? ': ' : ':') + v);
                            }
                        }
                    }

                    // Join all of the member texts together, separated with commas,
                    // and wrap them in braces.
                    v = partial.length === 0 ? '{}' : gap ? '{\n' + gap + partial.join(',\n' + gap) + '\n' + mind + '}' : '{' + partial.join(',') + '}';
                    gap = mind;
                    return v;
                case 'undefined':
                // Fall-through
                case 'function':
                // Fall-through
                default:
                    throw new SyntaxError('json_encode');
            }
        };

        // Make a fake root object containing our value under the key of ''.
        // Return the result of stringifying the value.
        return str('', {
            '': value
        });

    } catch (err) { // Todo: ensure error handling above throws a SyntaxError in all cases where it could
        // (i.e., when the JSON global is not available and there is an error)
        if (!(err instanceof SyntaxError)) {
            throw new Error('Unexpected error type in json_encode()');
        }
        this.php_js = this.php_js || {};
        this.php_js.last_error_json = 4; // usable by json_last_error()
        return null;
    }
}

function phpjs_version_compare(v1, v2, operator)
{
    //       discuss at: http://phpjs.org/functions/version_compare/
    //      original by: Philippe Jausions (http://pear.php.net/user/jausions)
    //      original by: Aidan Lister (http://aidanlister.com/)
    // reimplemented by: Kankrelune (http://www.webfaktory.info/)
    //      improved by: Brett Zamir (http://brett-zamir.me)
    //      improved by: Scott Baker
    //      improved by: Theriault
    //        example 1: version_compare('8.2.5rc', '8.2.5a');
    //        returns 1: 1
    //        example 2: version_compare('8.2.50', '8.2.52', '<');
    //        returns 2: true
    //        example 3: version_compare('5.3.0-dev', '5.3.0');
    //        returns 3: -1
    //        example 4: version_compare('4.1.0.52','4.01.0.51');
    //        returns 4: 1

    this.php_js = this.php_js || {};
    this.php_js.ENV = this.php_js.ENV || {};
    // END REDUNDANT
    // Important: compare must be initialized at 0.
    var i = 0,
        x = 0,
        compare = 0,
    // vm maps textual PHP versions to negatives so they're less than 0.
    // PHP currently defines these as CASE-SENSITIVE. It is important to
    // leave these as negatives so that they can come before numerical versions
    // and as if no letters were there to begin with.
    // (1alpha is < 1 and < 1.1 but > 1dev1)
    // If a non-numerical value can't be mapped to this table, it receives
    // -7 as its value.
        vm = {
            'dev': -6,
            'alpha': -5,
            'a': -5,
            'beta': -4,
            'b': -4,
            'RC': -3,
            'rc': -3,
            '#': -2,
            'p': 1,
            'pl': 1
        },
    // This function will be called to prepare each version argument.
    // It replaces every _, -, and + with a dot.
    // It surrounds any nonsequence of numbers/dots with dots.
    // It replaces sequences of dots with a single dot.
    //    version_compare('4..0', '4.0') == 0
    // Important: A string of 0 length needs to be converted into a value
    // even less than an unexisting value in vm (-7), hence [-8].
    // It's also important to not strip spaces because of this.
    //   version_compare('', ' ') == 1
        prepVersion = function (v) {
            v = ('' + v)
                .replace(/[_\-+]/g, '.');
            v = v.replace(/([^.\d]+)/g, '.$1.')
                .replace(/\.{2,}/g, '.');
            return (!v.length ? [-8] : v.split('.'));
        };
    // This converts a version component to a number.
    // Empty component becomes 0.
    // Non-numerical component becomes a negative number.
    // Numerical component becomes itself as an integer.
    numVersion = function (v) {
        return !v ? 0 : (isNaN(v) ? vm[v] || -7 : parseInt(v, 10));
    };
    v1 = prepVersion(v1);
    v2 = prepVersion(v2);
    x = Math.max(v1.length, v2.length);
    for (i = 0; i < x; i++) {
        if (v1[i] == v2[i]) {
            continue;
        }
        v1[i] = numVersion(v1[i]);
        v2[i] = numVersion(v2[i]);
        if (v1[i] < v2[i]) {
            compare = -1;
            break;
        } else if (v1[i] > v2[i]) {
            compare = 1;
            break;
        }
    }
    if (!operator) {
        return compare;
    }

    // Important: operator is CASE-SENSITIVE.
    // "No operator" seems to be treated as "<."
    // Any other values seem to make the function return null.
    switch (operator) {
        case '>':
        case 'gt':
            return (compare > 0);
        case '>=':
        case 'ge':
            return (compare >= 0);
        case '<=':
        case 'le':
            return (compare <= 0);
        case '==':
        case '=':
        case 'eq':
            return (compare === 0);
        case '<>':
        case '!=':
        case 'ne':
            return (compare !== 0);
        case '':
        case '<':
        case 'lt':
            return (compare < 0);
        default:
            return null;
    }
}

function phpjs_sha1(str)
{
    //  discuss at: http://phpjs.org/functions/sha1/
    // original by: Webtoolkit.info (http://www.webtoolkit.info/)
    // improved by: Michael White (http://getsprink.com)
    // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    //    input by: Brett Zamir (http://brett-zamir.me)
    //  depends on: utf8_encode
    //   example 1: sha1('Kevin van Zonneveld');
    //   returns 1: '54916d2e62f65b3afa6e192e6a601cdbe5cb5897'

    var rotate_left = function(n, s) {
        var t4 = (n << s) | (n >>> (32 - s));
        return t4;
    };

    /*var lsb_hex = function (val) { // Not in use; needed?
     var str="";
     var i;
     var vh;
     var vl;

     for ( i=0; i<=6; i+=2 ) {
     vh = (val>>>(i*4+4))&0x0f;
     vl = (val>>>(i*4))&0x0f;
     str += vh.toString(16) + vl.toString(16);
     }
     return str;
     };*/

    var cvt_hex = function(val) {
        var str = '';
        var i;
        var v;

        for (i = 7; i >= 0; i--) {
            v = (val >>> (i * 4)) & 0x0f;
            str += v.toString(16);
        }
        return str;
    };

    var blockstart;
    var i, j;
    var W = new Array(80);
    var H0 = 0x67452301;
    var H1 = 0xEFCDAB89;
    var H2 = 0x98BADCFE;
    var H3 = 0x10325476;
    var H4 = 0xC3D2E1F0;
    var A, B, C, D, E;
    var temp;

    str = phpjs_utf8_encode(str);
    var str_len = str.length;

    var word_array = [];
    for (i = 0; i < str_len - 3; i += 4) {
        j = str.charCodeAt(i) << 24 | str.charCodeAt(i + 1) << 16 | str.charCodeAt(i + 2) << 8 | str.charCodeAt(i + 3);
        word_array.push(j);
    }

    switch (str_len % 4) {
        case 0:
            i = 0x080000000;
            break;
        case 1:
            i = str.charCodeAt(str_len - 1) << 24 | 0x0800000;
            break;
        case 2:
            i = str.charCodeAt(str_len - 2) << 24 | str.charCodeAt(str_len - 1) << 16 | 0x08000;
            break;
        case 3:
            i = str.charCodeAt(str_len - 3) << 24 | str.charCodeAt(str_len - 2) << 16 | str.charCodeAt(str_len - 1) <<
                8 | 0x80;
            break;
    }

    word_array.push(i);

    while ((word_array.length % 16) != 14) {
        word_array.push(0);
    }

    word_array.push(str_len >>> 29);
    word_array.push((str_len << 3) & 0x0ffffffff);

    for (blockstart = 0; blockstart < word_array.length; blockstart += 16) {
        for (i = 0; i < 16; i++) {
            W[i] = word_array[blockstart + i];
        }
        for (i = 16; i <= 79; i++) {
            W[i] = rotate_left(W[i - 3] ^ W[i - 8] ^ W[i - 14] ^ W[i - 16], 1);
        }

        A = H0;
        B = H1;
        C = H2;
        D = H3;
        E = H4;

        for (i = 0; i <= 19; i++) {
            temp = (rotate_left(A, 5) + ((B & C) | (~B & D)) + E + W[i] + 0x5A827999) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B, 30);
            B = A;
            A = temp;
        }

        for (i = 20; i <= 39; i++) {
            temp = (rotate_left(A, 5) + (B ^ C ^ D) + E + W[i] + 0x6ED9EBA1) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B, 30);
            B = A;
            A = temp;
        }

        for (i = 40; i <= 59; i++) {
            temp = (rotate_left(A, 5) + ((B & C) | (B & D) | (C & D)) + E + W[i] + 0x8F1BBCDC) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B, 30);
            B = A;
            A = temp;
        }

        for (i = 60; i <= 79; i++) {
            temp = (rotate_left(A, 5) + (B ^ C ^ D) + E + W[i] + 0xCA62C1D6) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B, 30);
            B = A;
            A = temp;
        }

        H0 = (H0 + A) & 0x0ffffffff;
        H1 = (H1 + B) & 0x0ffffffff;
        H2 = (H2 + C) & 0x0ffffffff;
        H3 = (H3 + D) & 0x0ffffffff;
        H4 = (H4 + E) & 0x0ffffffff;
    }

    temp = cvt_hex(H0) + cvt_hex(H1) + cvt_hex(H2) + cvt_hex(H3) + cvt_hex(H4);
    return temp.toLowerCase();
}

function phpjs_utf8_encode(argString)
{
    //  discuss at: http://phpjs.org/functions/utf8_encode/
    // original by: Webtoolkit.info (http://www.webtoolkit.info/)
    // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // improved by: sowberry
    // improved by: Jack
    // improved by: Yves Sucaet
    // improved by: kirilloid
    // bugfixed by: Onno Marsman
    // bugfixed by: Onno Marsman
    // bugfixed by: Ulrich
    // bugfixed by: Rafal Kukawski
    // bugfixed by: kirilloid
    //   example 1: utf8_encode('Kevin van Zonneveld');
    //   returns 1: 'Kevin van Zonneveld'

    if (argString === null || typeof argString === 'undefined') {
        return '';
    }

    var string = (argString + ''); // .replace(/\r\n/g, "\n").replace(/\r/g, "\n");
    var utftext = '',
        start, end, stringl = 0;

    start = end = 0;
    stringl = string.length;
    for (var n = 0; n < stringl; n++) {
        var c1 = string.charCodeAt(n);
        var enc = null;

        if (c1 < 128) {
            end++;
        } else if (c1 > 127 && c1 < 2048) {
            enc = String.fromCharCode(
                (c1 >> 6) | 192, (c1 & 63) | 128
            );
        } else if ((c1 & 0xF800) != 0xD800) {
            enc = String.fromCharCode(
                (c1 >> 12) | 224, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128
            );
        } else { // surrogate pairs
            if ((c1 & 0xFC00) != 0xD800) {
                throw new RangeError('Unmatched trail surrogate at ' + n);
            }
            var c2 = string.charCodeAt(++n);
            if ((c2 & 0xFC00) != 0xDC00) {
                throw new RangeError('Unmatched lead surrogate at ' + (n - 1));
            }
            c1 = ((c1 & 0x3FF) << 10) + (c2 & 0x3FF) + 0x10000;
            enc = String.fromCharCode(
                (c1 >> 18) | 240, ((c1 >> 12) & 63) | 128, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128
            );
        }
        if (enc !== null) {
            if (end > start) {
                utftext += string.slice(start, end);
            }
            utftext += enc;
            start = end = n + 1;
        }
    }

    if (end > start) {
        utftext += string.slice(start, stringl);
    }

    return utftext;
}

function phpjs_strip_tags(input, allowed)
{
  //  discuss at: http://phpjs.org/functions/strip_tags/
  // original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // improved by: Luke Godfrey
  // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  //    input by: Pul
  //    input by: Alex
  //    input by: Marc Palau
  //    input by: Brett Zamir (http://brett-zamir.me)
  //    input by: Bobby Drake
  //    input by: Evertjan Garretsen
  // bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // bugfixed by: Onno Marsman
  // bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // bugfixed by: Eric Nagel
  // bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // bugfixed by: Tomasz Wesolowski
  //  revised by: Rafał Kukawski (http://blog.kukawski.pl/)
  //   example 1: strip_tags('<p>Kevin</p> <br /><b>van</b> <i>Zonneveld</i>', '<i><b>');
  //   returns 1: 'Kevin <b>van</b> <i>Zonneveld</i>'
  //   example 2: strip_tags('<p>Kevin <img src="someimage.png" onmouseover="someFunction()">van <i>Zonneveld</i></p>', '<p>');
  //   returns 2: '<p>Kevin van Zonneveld</p>'
  //   example 3: strip_tags("<a href='http://kevin.vanzonneveld.net'>Kevin van Zonneveld</a>", "<a>");
  //   returns 3: "<a href='http://kevin.vanzonneveld.net'>Kevin van Zonneveld</a>"
  //   example 4: strip_tags('1 < 5 5 > 1');
  //   returns 4: '1 < 5 5 > 1'
  //   example 5: strip_tags('1 <br/> 1');
  //   returns 5: '1  1'
  //   example 6: strip_tags('1 <br/> 1', '<br>');
  //   returns 6: '1 <br/> 1'
  //   example 7: strip_tags('1 <br/> 1', '<br><br/>');
  //   returns 7: '1 <br/> 1'

  allowed = (((allowed || '') + '')
    .toLowerCase()
    .match(/<[a-z][a-z0-9]*>/g) || [])
    .join(''); // making sure the allowed arg is a string containing only tags in lowercase (<a><b><c>)
  var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi,
    commentsAndPhpTags = /<!--[\s\S]*?-->|<\?(?:php)?[\s\S]*?\?>/gi;
  return input.replace(commentsAndPhpTags, '')
    .replace(tags, function($0, $1) {
      return allowed.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : '';
    });
}




function navigate_query_parameter(name, url)
{
    if(!url)
        url = window.location.href;

	var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(url);
	if (!results) { return 0; }
	return results[1] || 0;
}

// Thanks! Taken from http://stackoverflow.com/questions/487073/jquery-check-if-element-is-visible-after-scrolling
function navigate_element_visible(elem)
{
    var docViewTop = $(window).scrollTop();
    var docViewBottom = docViewTop + $(window).height();

    var elemTop = $(elem).offset().top;
    var elemBottom = elemTop + $(elem).height();

    return ((elemBottom >= docViewTop) && (elemTop <= docViewBottom)
      && (elemBottom <= docViewBottom) &&  (elemTop >= docViewTop) );
}

function navigate_tinymce_event(event, element, ignoreScroll)
{
    if(!element)
        element = event.currentTarget;

    if(element.editorId)
        element = element.editorId;

    if(event.type=='blur')
    {
        // save scroll position
        navigate_tinymce_scroll(event, element);
    }
    else if(event.type=='focus')
    {
        // restore scroll position, unless it is zero
        var cookie = $.cookie("navigate-tinymce-scroll");

        if(cookie)
        {
            var spos = 0;

            if($('#id').length > 0 && $('#id').val() > 0)
                eval("if(cookie.i" + $('#id').val() + ") { spos = cookie.i"+ $('#id').val() + "." + element.replace(/-/g, '_') + "; };");
            else
                spos = cookie[element.replace(/-/g, '_')];

            if(spos > 0)
            {
                setTimeout(
                    function()
                    {
                        $('#' + element + '_ifr').contents().scrollTop(spos);
                    },
                    20
                );
            }
        }
    }
    else if(event.type=='scroll')
    {
        if(!ignoreScroll)
        {
            // if the user scrolls the tinyMCE iframe, we ignore the previous saved scroll position
            navigate_tinymce_scroll(event, element);
        }
    }

    return true;
}

function navigate_tinymce_scroll(event, element, reset)
{
    var sTop = $(event.currentTarget).scrollTop();
    if(reset)
        sTop = 0;
    var cookie = $.cookie("navigate-tinymce-scroll");
    if(!cookie)
        cookie = {};

    element = element.replace(/-/g, '_');

    if($('#id').length > 0 && $('#id').val() > 0)
    {
        eval("if(!cookie.i" + $('#id').val() + ") { cookie.i"+ $('#id').val() + " = {}; };");
        element = 'i' + $('#id').val() + '.' + element;
    }

    eval("cookie." + element + " = " + sTop + ";");
    $.setCookie("navigate-tinymce-scroll", cookie);
}


//plugin buttonset vertical
//https://gist.github.com/760885
$.fn.buttonsetv = function()
{
    $(':radio, :checkbox', this).wrap('<div style="margin: 1px"/>');
    $(this).controlgroup();
    $('label:first', this).removeClass('ui-corner-left').addClass('ui-corner-top');
    $('label:last', this).removeClass('ui-corner-right').addClass('ui-corner-bottom');
    mw = 0; // max witdh
    $('label', this).each(function(index){
        w = $(this).width();
        if (w > mw) mw = w;
    });
    $('label', this).each(function(index){
        $(this).width(mw);
    });
};

function navigate_selector_upgrade(el)
{
    // check if the element has already select2 applied
	if($(el).hasClass("select2-container"))
		return;

    // if width was applied, prepare Select2 component to use it instead of its default width
    var width = $(el)[0].style.width;
    var classes = $(el)[0].className;

    $(el).select2(
        {
            selectOnBlur: true,
            minimumResultsForSearch: 6
        }
    );

    // autoclose select2 after clearing
    if($(el).attr("multiple")!="multiple")
    {
        $(el).on("select2:unselecting", function (e)
        {
            $(this).select2("val", "");
            $(this).val("");
            e.preventDefault();
            $(this).trigger("change");
        });
    }

    // force defined width
    if(width)
    {
        $(el).prev().find('a:first').css('width', width);
    }

    classes = classes.replace("select2 ", "");
    $(el).next().addClass(classes);

    // add custom values, if enabled
    $(el).parent().find('a[data-action="create_custom_value"]').on("click", function()
    {
        $('<div class="navigate-form-row"><input type="text" name="create_custom_value" /></div>').dialog({
            title: navigate_t(472, "Add"),
            modal: true,
            width: 428,
            buttons: [
                {
                    text: navigate_t(190, "Ok"),
                    icon: "ui-icon-check",
                    click: function()
                    {
                        var new_value = $(this).find('input[name="create_custom_value"]').val();
                        if(new_value)
                        {
                            $(el).append($('<option>', { value: new_value, text: new_value, selected: true }));
                            if($(el).attr('multiselect'))
                                $(el).val($(el).val().concat(new_value));
                            else
                                $(el).val(new_value);
                        }
                        $( this ).dialog( "close" );
                    }
                },
                {
                    text: navigate_t(58, "Cancel"),
                    icon: "ui-icon-close",
                    click: function()
                    {
                        $( this ).dialog( "close" );
                    }
                }
            ]
        });
    });
}

function navigate_file_drop(selector, parent, callbacks, show_progress_in_title)
{
	// callbacks object
	// {
    //      uploadStarted: function(file) {},
	//		afterAll: function() {},
	//		afterOne: function(file_id) {},	
	//		dragOver: function() {},
	//		dragLeave: function() {}	
	// }	
	$(function()
	{
        if($(selector).attr("data-filedrop")!="true")
        {
            // remove previous binding, if exists (in case of trying to init the dropzone on the same object multiple times)
            // Dropzone.forElement(selector).destroy();
            $(selector).dropzone(
            {
                url: NAVIGATE_URL + "/navigate_upload.php?session_id=" + navigate["session_id"] + "&engine=dropzone",
                paramName: "upload",
                parallelUploads: 1,
                autoProcessQueue: 1,
                clickable: false,
                error: function(file, err)
                {
                    switch(err)
                    {
                        case "BrowserNotSupported":
                            alert(navigate_lang_dictionary[401]);
                            break;

                        case "TooManyFiles":
                            alert(navigate_lang_dictionary[402]);
                            break;

                        case "FileTooLarge":
                            // program encountered a file whose size is greater than maxfilesize
                            // FileTooLarge also has access to the file which was too large
                            // use file.name to reference the filename of the culprit file
                            alert(navigate_lang_dictionary[403] + ': ' + file.name);
                            break;

                        default:
                            if(file && file.name)
                            {
                                alert(err + ': ' + file.name);
                            }
                            else
                            {
                                alert(err);
                            }
                            break;
                    }
                },
                maxFiles: 1000,
                maxFilesize: NAVIGATE_MAX_UPLOAD_SIZE,    // max file size in MBs,
                document_title: "",
                dragover: function()
                {
                    // user dragging files over #dropzone
                    navigate_status(navigate_lang_dictionary[260], "img/icons/misc/dropbox.png", true); // Drag & Drop files now to upload them
                    if(callbacks.dragOver)
                    {
                        callbacks.dragOver();
                    }
                },
                dragleave: function()
                {
                    // user dragging files out of #dropzone
                    navigate_status(navigate_lang_dictionary[42], "ready"); // ready
                    if(callbacks.dragLeave)
                    {
                        callbacks.dragLeave();
                    }
                },
                sending: function(file, xhr, formData)
                {
                    // Will send the filesize along with the file as POST data.
                    formData.append("filesize", file.size);
                    formData.append("_nv_csrf_token", navigatecms.csrf_token);
                },
                processing: function(file)
                {
                    var fname = file.fileName;
                    if(!fname)
                    {
                        fname = file.name;
                    }

                    navigate_status(navigate_lang_dictionary[261] + ": " + fname, "loader"); // uploading
                    $("link[rel*=shortcut]").remove().clone().appendTo("head").attr("href", "img/loader.gif");

                    if(show_progress_in_title)
                    {
                        $(this).document_title = $(document).attr("title");
                        $(document).attr("title", navigate_lang_dictionary[261] + ": " + fname); // uploading
                    }

                    if(callbacks.uploadStarted)
                    {
                        callbacks.uploadStarted(file);
                    }
                },
                complete: function(file)
                {
                    // response is the data you got back from server in JSON format.
                    
                    if(!file || !file.xhr)
                    {
                        return false;
                    }
                    
                    var response = $.parseJSON(file.xhr.response);

                    if(show_progress_in_title)
                    {
                        $(document).attr("title", $(this).document_title);
                    }

                    var uploaded = [];

                    if(response.filename)
                    {
                        uploaded = response;
                    }
                    else if(response[0])
                    {
                        uploaded = response[0];
                    }

                    if(!uploaded || !uploaded.filename || uploaded.error)
                    {
                        navigate_notification(navigate_lang_dictionary[262] + ": " + uploaded.filename);
                    } // Error uploading file
                    else
                    {
                        $.ajax(
                        {
                            async: false,
                            url: NAVIGATE_APP + "?fid=files&act=1&op=upload&parent=" + $(selector).attr("data-filedrop-parent"),
                            success: function(data)
                            {
                                if(callbacks.afterOne)
                                {
                                    callbacks.afterOne(data);
                                }
                            },
                            type: "post",
                            dataType: "json",
                            data: ({
                                tmp_name: uploaded.temporal,
                                name: uploaded.filename,
                                size: file.size
                            })
                        });
                    }
                },
                uploadprogress: function(file, progress, bytesSent)
                {
                    // this function is used for large files and updates intermittently
                    // progress is the integer value of file being uploaded percentage to completion
                    var fname = file.fileName;
                    if(!fname)  fname = file.name;

                    progress = parseFloat(progress).toFixed(2);
                    
                    navigate_status(navigate_lang_dictionary[261] + ": " + fname, "loader", "default", progress); // uploading

                    if(show_progress_in_title)
                    {
                        $(document).attr("title", navigate_lang_dictionary[261] + ": " + fname + " " + progress + "%", "loader");
                    }
                },
                queuecomplete: function()
                {
                    // runs after all files have been uploaded or otherwise dealt with
                    $("link[rel*=shortcut]").remove().clone().appendTo("head").attr("href", "favicon.ico");
                    navigate_status(navigate_lang_dictionary[42], "ready"); // ready

                    if(callbacks.afterAll)
                    {
                        callbacks.afterAll();
                    }
                },
                previewTemplate: "", // remove default template
                addedfile: function(file) {},
                thumbnail: function(file, dataUrl) {} // ignore default template
            });

            $(selector).attr("data-filedrop", "true");
            $(selector).attr("data-filedrop-parent", parent);
        }
        else
        {
            // filedrop was already bound
            // we still allow changing the upload folder
            $(selector).attr("data-filedrop-parent", parent);
        }
    });
}

function navigate_file_video_info(provider, reference, callback)
{
    var ref = reference;

    if(provider=="parse")
    {
        provider = ref.split('#')[0];
        ref = ref.split('#')[1];

        if(!provider || provider=="")
        {
            provider = "file";
            ref = reference;
        }
    }

    $.get(
        NAVIGATE_APP + '?fid=files&act=json&op=video_info&provider=' + provider + '&reference=' + ref,
        function(data)
        {
            if(typeof(callback)=='function')
                callback($.parseJSON(data));
        }
    );
}


function navigate_dropbox_load_video(name, value)
{
    $("#" + name).val(value);
    $("#" + name).html("");
    $("#" + name).parent().find(".navigate-droppable-cancel").show();
    $("#" + name).parent().find(".navigate-droppable-create").hide();
    $("#" + name + "-droppable-info").find(".navigate-droppable-info-title").html("");
    $("#" + name + "-droppable-info").find(".navigate-droppable-info-provider").html("");
    $("#" + name + "-droppable-info").find(".navigate-droppable-info-extra").html("");

    navigate_file_video_info(
        "parse",
        value,
        function(data)
        {
            var play = '';
            if(!data.id || data.id==null)
            {
                $("#" + name + "-droppable").html("<img src=\"img/icons/misc/dropbox.png\" vspace=\"18\" />");
                $("#" + name + "-droppable-wrapper").find(".navigate-droppable-cancel").hide();
                $("#" + name + "-droppable-wrapper").find(".navigate-droppable-create").show();
                navigate_notification("Error " + value);
                return false;
            }

            $("#" + name + "-droppable").html("<img src=\""+data.extra.thumbnail_cache_absolute+"&width=80&height=60&border=false\" />");

            if(data.mime=='video/youtube' || data.mime=='video/vimeo')
            {
                play = '<br /><a href="'+data.extra.link+'" target="_blank"><i class="fa fa-2x fa-play-circle"></i></a>';
                $("#" + name + "-droppable-info").find(".navigate-droppable-info-title").html(data.name);
                $("#" + name + "-droppable-info").find(".navigate-droppable-info-provider").html(data.mime+play);
                $("#" + name + "-droppable-info").find(".navigate-droppable-info-extra").show();
                $("#" + name + "-droppable-info").find(".navigate-droppable-info-extra").html(data.uploaded_by);
                $("#" + name + "-droppable").find("img").css({
                    "width": "80px",
                    "height": "60px",
                    "margin-top": "8px"
                });
            }
            else
            {
                $("#" + name + "-droppable-info").find(".navigate-droppable-info-title").html(data.name);
                $("#" + name + "-droppable-info").find(".navigate-droppable-info-provider").html(data.mime);
                $("#" + name + "-droppable-info").find(".navigate-droppable-info-extra").hide();
                $("#" + name + "-droppable").find("img").css({
                    "width": "64px",
                    "margin-top": "6px"
                });
            }
        }
    );
}

/**
 * Get YouTube ID from various YouTube URL
 * @author: takien
 * @url: http://takien.com
 * For PHP YouTube parser, go here http://takien.com/864
 */
function navigate_youtube_reference_from_url(url)
{
    var ID = '';
    var yt_url = url.replace(/(>|<)/gi,'');
    yt_url = yt_url.split(/(vi\/|v=|\/v\/|youtu\.be\/|\/embed\/)/);

    if(yt_url[2] !== undefined)
    {
        ID = yt_url[2].split(/[^0-9a-z_\-]/i);
        ID = ID[0];
    }
    else
    {
        ID = yt_url;
    }
    return ID;
}

function navigate_vimeo_reference_from_url(url)
{
    var m = url.match(/^.+vimeo.com\/(.*\/)?([^#\?]*)/);
    return m ? m[2] || m[1] : null;
}


function navigate_dropbox_clone_value(origin_field_id, destination_field_id)
{
	var orig_dropbox = $("#" + origin_field_id + "-droppable-wrapper");
	var dest_dropbox = $("#" + destination_field_id + "-droppable-wrapper");

	$("#" + destination_field_id).val($("#" + origin_field_id).val());

	dest_dropbox.find(".navigate-droppable").html(orig_dropbox.find(".navigate-droppable").html());
	dest_dropbox.find(".navigate-droppable-cancel").html(orig_dropbox.find(".navigate-droppable-cancel").html());
	dest_dropbox.find(".navigate-droppable-info").html(orig_dropbox.find(".navigate-droppable-info").html());

	if($("#" + destination_field_id).val() != "")
	{
		dest_dropbox.find(".navigate-droppable-cancel").show();
		dest_dropbox.find(".navigate-droppable-create").hide();
	}
	else
	{
		dest_dropbox.find(".navigate-droppable-cancel").hide();
		dest_dropbox.find(".navigate-droppable-create").show();
	}
}


function navigate_string_cut(text, maxlen, morechar)
{
    if(!morechar)
        morechar = '&hellip;';

    // truncate by plain text
    text = phpjs_strip_tags(text);
    var olen = text.length;

    if(olen < maxlen)
        return text;

    var pos = (text.substr(0 , maxlen)).lastIndexOf(' ');
    text = text.substr(0 , pos);
    if(olen > maxlen)
        text = text + morechar;

	return text;
}

function nv_link_dialog_source_change()
{
    setTimeout(function()
    {
        var source = $('#nv_link_dialog_source_structure:checked,#nv_link_dialog_source_element:checked,#nv_link_dialog_source_product:checked').val();

        $("#nv_link_dialog_real_path").parent().addClass("hidden");
        $("#nv_link_dialog_dynamic_path").parent().addClass("hidden");
        $("#nv_link_dialog_replace_text").parent().addClass("hidden");

        $("#nv_link_dialog_category").parent().addClass("hidden");
        $("#nv_link_dialog_element").parent().addClass("hidden");
        $("#nv_link_dialog_product").parent().addClass("hidden");

        $("#nv_link_dialog_category .jstree").jstree("deselect_all");
        $("#nv_link_dialog_element").val(null).trigger("change");
        $("#nv_link_dialog_product").val(null).trigger("change");

        if(source == "element")
        {
            $("#nv_link_dialog_element").parent().removeClass("hidden");
        }
        else if(source == "product")
        {
            $("#nv_link_dialog_product").parent().removeClass("hidden");
        }
        else if(source == "structure")
        {
            $("#nv_link_dialog_category").parent().removeClass("hidden");
        }
    }, 100);
}

function navigate_confirmation_dialog(ok_callback, content, title, ok_button, cancel_button, cancel_callback)
{
    if(!title || title == "")
    {
        title = navigate_t(59, "Confirmation");
    }

    if(!content || content == "")
    {
        content = navigate_t(57, "Do you really want to delete the item?");
    }

    if(!ok_button || ok_button=="")
    {
        ok_button = navigate_t(190, "Ok");
    }

    if(!cancel_button || cancel_button=="")
    {
        cancel_button = navigate_t(58, "Cancel");
    }

    $('<div>' + content +'</div>').dialog(
    {
        resizable: true,
        height: 150,
        width: 300,
        modal: true,
        title: title,
        buttons:
        [
            {
                text: ok_button,
                click: function()
                {
                    if(typeof(ok_callback)=="function")
                    {
                        ok_callback();
                    }

                    $(this).dialog("close");
                }
            },
            {
                text: cancel_button,
                click: function()
                {
                    if(typeof(cancel_callback)=="function")
                    {
                        cancel_callback();
                    }
                    $(this).dialog("close");
                }
            }
        ]
    });
}

function navigate_string_to_decimal(value)
{
    // remove all characters except numbers, the negative symbol and the decimal character (defined by the current user)
    var regex = new RegExp('[^0-9\-\\' + navigate["decimal_separator"] + ']', "g");

    if(typeof(value)!="string")
        value = value.toString();
    value = value.replace(regex, "");

    // replace the user decimal character for the internal symbol: a dot .
    var regex = new RegExp('\\' + navigate["decimal_separator"], "g");
    value = value.replace(regex, ".");

    value = parseFloat(value);

    return value;
}

function navigate_decimal_to_string(value)
{
    value = parseFloat(value.toFixed(2));
    value = value + "";
    value = value.replace(/\./g, navigate["decimal_separator"]);
    return value;
}

/**
 * https://gist.github.com/1255491
 *
 * creates a split button UI component using jquery ui 1.8.x's menu and button groups. See comment about potential
 * changes in jquery ui 1.9.x.
 *
 * @see http://jqueryui.com/demos/button/splitbutton.html for a non-functional example
 * @see https://raw.github.com/jquery/jquery-ui/5f4a6009e9987842b3a970c77bed0b52f7e810e2/demos/button/splitbutton.html for the code used for this plugin.
 *
 * @param options.selected closure to execute upon menu item selection (default: execute the link href)
 * @param options.showMenu closure to show context menu (default: show the menu relative to the button)
 *
 * Thanks mcantrell!!!
 */
(function($) {
    $.fn.splitButton = function(options)
    {
        var menu = null;
        var settings =
        {
            showMenu: function()
            {
                if (menu) menu.hide();
                menu = $(this).parent().next().show().position(
                    {
                        my: "left top",
                        at: "left bottom",
                        of: $(this).parent().parent()
                    }
                );

                // use relative coordinates, to minimize scroll bug (not perfect, though)
                $(this).parent().next().css({
                    'margin-left': $(this).parent().position().left - 2, // - $(this).parents('.ui-tabs-panel').offset().left
                    'margin-top': $(this).parent().height(),
                    'left': '0',
                    'top': '',
                    'z-index': 10000
                });

				$(this).parents('.ui-tabs-panel:first').animate(
					{
						scrollTop: $(this).offset().top
					},
					200
				);

                $(document).one("click", function()
                {
                    menu.hide();
                });
                return false;
            }
        };

        return this.click(settings.showMenu);
    };
})(jQuery);