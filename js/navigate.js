var navigatecms = {};
var navigate_menu_current_tab;
var navigate_lang_dictionary = Array();
var navigate_codemirror_instances = Array();
var navigate_form_conditional_properties = Array();
var navigate_form_properties_language_selected = null;
var navigate_menu_unselect_timer = null;

//var $P = new PHP_JS();

$(window).on('load', function()
{
    $("button, input:submit, a.uibutton, div.uibutton").button();
    $(".buttonset").buttonset();
    $(".buttonset").find('label').on('click', function()
    {
        // force buttonset to update the state on click
        // jquery doesn't count a click if the cursor moves a little
        $(this).parents('.buttonset').find('input[checked]').removeAttr('checked');
        $(this).prev().attr('checked', 'checked');
        $(this).parents('.buttonset').buttonset('refresh');
    });
    jQuery.longclick.duration = 1000; // default longlick duration

    $(".select2").each(function(i, el)
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

	$('.navigate-plus-link img').hover(
		function()	{	$(this).attr('src', 'img/icons/misc/plus_black-32.png'); },
		function()	{	$(this).attr('src', 'img/icons/misc/plus_blue-32.png');  }		
	);

    $('.navigate-favorites-link img').hover(
        function()	{	$(this).attr('src', 'img/icons/misc/heart_black-32.png'); },
        function()	{	$(this).attr('src', 'img/icons/misc/heart_blue-32.png');  }
    );
	
	$('.navigate-help-link img').hover(
		function()	{	$(this).attr('src', 'img/icons/misc/help_black-32.png'); },
		function()	{	$(this).attr('src', 'img/icons/misc/help_blue-32.png');	 }		
	);

	$('.navigate-logout-link img').hover(
		function()	{	$(this).attr('src', 'img/icons/misc/power_black-32.png'); },
		function()	{	$(this).attr('src', 'img/icons/misc/power_blue-32.png');  }		
	);

    // recent items list
	$('#navigate-recent-items-link').on('mouseenter', function()
	{
		$('#navigate-recent-items').css({
			top: $('#navigate-recent-items-link').offset().top + 20,
			left: $('#navigate-recent-items-link').offset().left + $('#navigate-recent-items-link').width() - $('#navigate-recent-items').width() - 5
		}).show();

        $('#navigate-recent-items').addClass('navi-ui-widget-shadow');

        $('#navigate-recent-items').menu();
	});
	
	$('#navigate-recent-items').on('mouseleave', function()
	{
		$(this).fadeOut('fast');
	});


    // actions bar submenus
    $('#navigate-content-actions a.content-actions-submenu-trigger').on('mouseenter click', function(ev)
    {
        if($(this).next().is(':visible'))
            $(this).next().hide();
        else
        {
            $(this).next().menu().show();
            $(this).next().addClass('navi-ui-widget-shadow');

            // deprecated code
            /*
            //var height = 0;
            $(this).next().children().each(function()
            { //height = height + $(this).height();
            });
            //$(this).next().height(height);
            */
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

    $(document.body).on('mousedown', navigate_hide_context_menus);

	$(window).on('resize', navigate_window_resize);
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

		$("#navigate-content-safe").css({ display: 'block', height: $('#navigate-content').height() - 30 });		
		//$("table.ui-jqgrid-btable").setGridWidth($('#navigate-content').width());		
	}

    if($('.navibrowse,.navigrid').length > 0)
    {
        $('.navibrowse,.navigrid').height($('.navibrowse,.navigrid').parent().height() - 10);

        if( $('.navibrowse-items,.navigrid-items').height() < $('.navibrowse,.navigrid').height() )
            $('.navibrowse-items,.navigrid-items').height( $('.navibrowse,.navigrid').height() - $('.navibrowse-path').height()  - 10 );
    }
}

function navigate_status(text, img, status, percentage)
{
	var ns = $("#navigate-status-info");
	
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
            value: percentage
        });
        $('#navigate-status-progressbar-label').text(percentage + '%');
    }
}

function navigate_notification(text, sticky)
{
	$.jGrowl.defaults.position = "center";
    if(!sticky || sticky=="" || sticky==null)
        sticky = false;

	$.jGrowl(text,
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
		return navigate_lang_dictionary[id];	
	else
		return text;
}

function navigate_tinymce_add_content(editor_id, file_id, media, mime, web_id, element)
{
    var inst = tinyMCE.getInstanceById(editor_id);
	var html = '';
    var embed_dialog = false;

	var selection_active  = (inst.selection.getContent({format : 'text'})!="");
	var selection_content = inst.selection.getContent({format: 'html'});

	switch(media)
	{
		case 'image':
            var max_width = $('#' + editor_id + '_ifr').contents().find('body').width();
            var or_styles = '';

            if($(inst.selection.getContent({format: 'raw'})).is('img'))
            {
                // if tinyMCE has something selected and it is an image, read its dimensions and apply them to the new image
                var max_width = $(inst.selection.getContent({format: 'raw'})).width();
                var max_height = $(inst.selection.getContent({format: 'raw'})).height();

                // try an alternative method (Chrome browser work around)
                if(max_width==0)
                    max_width = $($(inst.selection.getContent({format: 'raw'}))[0]).attr('width');

                if(max_height==0)
                    max_height = $($(inst.selection.getContent({format: 'raw'}))[0]).attr('height');

                var or_styles = ' style="' + $(inst.selection.getContent({format: 'raw'}))[0].style.cssText + '" ';

                embed_dialog = true;
            }

            var image_width = $(element).attr("image-width");
            var image_height = $(element).attr("image-height");
            var body_width = $(inst.contentAreaContainer).width() - 37;

            if(max_width==0 || max_width > image_width)
                max_width = image_width;

            if(max_height==0 || max_height > image_height)
                max_height = image_height;

            var scaled_height = Math.ceil((max_width * image_height) / image_width);
            var scaled_width = Math.ceil((max_height * image_width) / image_height);

            var title = $.base64.decode($(element).attr('image-title'));
            var alt = $.base64.decode($(element).attr('image-description'));

            var active_editor_lang = editor_id;
            if(active_editor_lang.indexOf("-") > 0)
                active_editor_lang = active_editor_lang.split("-").pop();

            title = $.parseJSON(title);
            if(title && title[active_editor_lang])
                title = title[active_editor_lang];
            else
                title = "";

            alt = $.parseJSON(alt);
            if(alt && alt[active_editor_lang])
                alt = alt[active_editor_lang];
            else
                alt = "";

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

            if($(selection_content).is("A"))
                selection_content = $(selection_content).text();

			html = '<a rel="file" href="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline"> ' + selection_content + '</a>';
	}

    if(embed_dialog)
        return;

	if(selection_active)
    {
		tinyMCE.activeEditor.selection.setContent(html);
        tinyMCE.activeEditor.execCommand('mceCleanup', false);
    }
	else
    {
        tinyMCE.execInstanceCommand(editor_id, 'mceInsertContent', false, html);
        tinyMCE.execInstanceCommand(editor_id, 'mceCleanup', false);
    }
}

function navigate_tinymce_move_cursor_to_end(editor_id) 
{
    var inst = tinyMCE.getInstanceById(editor_id);
    tinyMCE.execInstanceCommand(editor_id,"selectall", false, null);
    if (tinyMCE.isMSIE) {
        rng = inst.getRng();
        rng.collapse(false);
        rng.select();
    }
    else {
        sel = inst.getSel();
        sel.collapseToEnd();
    }
}

// Thanks to Ben for the following tinyMCE get/set cursor functions
// http://blog.squadedit.com/tinymce-and-cursor-position/

function navigate_tinymce_get_cursor_position(editor_id)
{
    var editor = tinyMCE.getInstanceById(editor_id);

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
    var editor = tinyMCE.getInstanceById(editor_id);

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

function navigate_tabform_submit(formNum)
{
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
        var prop_check = $("#navigate-properties-form").find("[id^=property-" + property_conditional + "]").on(
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

function navigate_hide_context_menus()
{
    setTimeout(function()
    {
        //$('select.select2').select2('close'); // should not be necessary, maybe fixed in 3.4.2?
        $(".ui-menu").fadeOut('fast');
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



function navigate_query_parameter(name, url)
{
    if(!url)
        url = window.location.href;

	var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(url);
	if (!results) { return 0; }
	return results[1] || 0;
}

// Thanks! Taken from http://stackoverflow.com/questions/487073/jquery-check-if-element-is-visible-after-scroling
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
        // restore scroll position
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
                    10
                );
            }
        }
    }
    else if(event.type=='scroll')
    {
        if(!ignoreScroll)
        {
            // if the user scrolls the tinyMCE iframe, we ignore the previous saved scroll position
            navigate_tinymce_scroll(event, element, true);
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
    $(this).buttonset();
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
    // if width was applied, prepare Select2 component to use it instead of its default width
    var width = $(el)[0].style.width;

    $(el).select2(
        {
            formatNoMatches: function ()
            {
                return navigate_lang_dictionary[492]; /*"No matches found"*/
            },
            formatInputTooShort: function (input, min)
            {
                var n = min - input.length;
                var text = navigate_lang_dictionary[495]; // Please enter at least {number} characters
                text.replace(/{number}/, min);
                return text;
            },
            formatSelectionTooBig: function (limit)
            {
                var text = navigate_lang_dictionary[496]; // You can only select {number} items
                text.replace(/{number}/, limit);
                return text;
            },
            formatLoadMore: function (pageNumber)
            {
                return navigate_lang_dictionary[493]; /*"Loading more results..."*/
            },
            formatSearching: function ()
            {
                return navigate_lang_dictionary[494]; /*"Searching..."*/
            },
            selectOnBlur: true
        }
    );

    // force defined width
    if(width)
        $(el).prev().find('a:first').css('width', width);

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
                    icons: {
                        primary: "ui-icon-check"
                    },
                    click: function()
                    {
                        var new_value = $(this).find('input[name="create_custom_value"]').val();
                        if(new_value)
                        {
                            $(el).append($('<option>', { value: new_value, text: new_value, selected: true }));
                            if($(el).attr('multiselect'))
                                $(el).select2("val", $(el).select2("val").concat(new_value));
                            else
                                $(el).select2("val", new_value);
                        }
                        $( this ).dialog( "close" );
                    }
                },
                {
                    text: navigate_t(58, "Cancel"),
                    icons: {
                        primary: "ui-icon-close"
                    },
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
            // filedrop does not offer a "remove bindings" option, so only the first binding for an element is used
            $(selector).filedrop(
            {
                url: NAVIGATE_URL + "/navigate_upload.php?session_id=" + navigate["session_id"] + "&engine=filedrop",
                paramname: "upload",
                error: function(err, file)
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
                                alert(err + ': ' + file.name);
                            else
                                alert(err);
                            break;
                    }
                },
                maxfiles: 1000,
                maxfilesize: NAVIGATE_MAX_UPLOAD_SIZE,    // max file size in MBs,
                queuefiles: 1,
                document_title: "",
                dragOver: function()
                {
                    // user dragging files over #dropzone
                    navigate_status(navigate_lang_dictionary[260], "img/icons/misc/dropbox.png", true); // Drag & Drop files now to upload them
                    if(callbacks.dragOver)
                        callbacks.dragOver();
                },
                dragLeave: function()
                {
                    // user dragging files out of #dropzone
                    navigate_status(navigate_lang_dictionary[42], "ready"); // ready
                    if(callbacks.dragLeave)
                        callbacks.dragLeave();
                },
                docOver: function()
                {
                    // user dragging files anywhere inside the browser document window
                },
                docLeave: function()
                {
                    // user dragging files out of the browser document window
                },
                drop: function()
                {
                    // user drops file
                },
                uploadStarted: function(i, file, len)
                {
                    // a file began uploading
                    // i = index => 0, 1, 2, 3, 4 etc
                    // file is the actual file of the index
                    // len = total files user dropped

                    var fname = file.fileName;
                    if(!fname)  fname = file.name;

                    navigate_status(navigate_lang_dictionary[261] + ": " + fname, "loader"); // uploading
                    $("link[rel*=shortcut]").remove().clone().appendTo("head").attr("href", "img/loader.gif");

                    if(show_progress_in_title)
                    {
                        $(this).document_title = $(document).attr("title");
                        $(document).attr("title", navigate_lang_dictionary[261] + ": " + fname); // uploading
                    }

                    if(callbacks.uploadStarted)
                        callbacks.uploadStarted(file);
                },
                uploadFinished: function(i, file, response, time)
                {
                    // response is the data you got back from server in JSON format.
                    if(show_progress_in_title)
                    {
                        $(document).attr("title", $(this).document_title);
                    }

                    var uploaded = [];

                    if(response.filename)	uploaded = response;
                    else if(response[0])	uploaded = response[0];

                    if(uploaded.error)
                        navigate_notification(navigate_lang_dictionary[262] + ": " + uploaded.filename); // Error uploading file
                    else
                    {
                        $.ajax(
                        {
                            async: false,
                            url: NAVIGATE_APP + "?fid=files&act=1&op=upload&parent=" + $(selector).attr("data-filedrop-parent"),
                            success: function(data)
                            {
                                if(callbacks.afterOne)
                                    callbacks.afterOne(data);
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
                progressUpdated: function(i, file, progress)
                {
                    // this function is used for large files and updates intermittently
                    // progress is the integer value of file being uploaded percentage to completion
                    var fname = file.fileName;
                    if(!fname)  fname = file.name;

                    navigate_status(navigate_lang_dictionary[261] + ": " + fname, "loader", "default", progress); // uploading

                    if(show_progress_in_title)
                    {
                        $(document).attr("title", navigate_lang_dictionary[261] + ": " + fname + " " + progress + "%", "loader");
                    }
                },
                speedUpdated: function(i, file, speed)
                {
                    // speed in kb/s
                },
                rename: function(name)
                {
                    // name in string format
                    // must return alternate name as string
                },
                beforeEach: function(file)
                {
                    // file is a file object
                    // return false to cancel upload
                },
                afterAll: function()
                {
                    // runs after all files have been uploaded or otherwise dealt with
                    $("link[rel*=shortcut]").remove().clone().appendTo("head").attr("href", "favicon.ico");
                    navigate_status(navigate_lang_dictionary[42], "ready"); // ready

                    if(callbacks.afterAll)
                        callbacks.afterAll();
                }
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
                navigate_notification(navigate_lang_dictionary[56]); // Unexpected error
                return false;
            }

            $("#" + name + "-droppable").html("<img src=\""+data.extra.thumbnail_url+"\" />");

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
    url = url.replace(/(>|<)/gi,'').split(/(vi\/|v=|\/v\/|youtu\.be\/|\/embed\/)/);
    if(url[2] !== undefined) {
        ID = url[2].split(/[^0-9a-z_]/i);
        ID = ID[0];
    }
    else {
        ID = url;
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
            selected: function(event, ui)
            {
                document.location = ui.item.children()[0];
            },
            showMenu: function()
            {
                if (menu) menu.hide();
                menu = $(this).parent().next().show().position(
                    {
                        my: "left top",
                        at: "left bottom",
                        of: $(this).prev()
                    }
                );

                // use relative coordinates, to minimize scroll bug (not perfect, though)
                $(this).parent().next().css({
                    'margin-left': $(this).prev().position().left - 2, // - $(this).parents('.ui-tabs-panel').offset().left
                    'margin-top': $(this).prev().height(),
                    'left': '0',
                    'top': '',
                    'z-index': 1000
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
        if (options)
        {
            $.extend(settings, options);
        }
        var buttonConfig = { text: false, icons: { primary: "ui-icon-triangle-1-s" }};
        return this.button().next().button(buttonConfig).click(settings.showMenu).parent().buttonset()
            // this may change to select: in jquery ui 1.9
            .next().menu({selected: settings.selected});
    };
})(jQuery);