var navigate_menu_current_tab;
var navigate_lang_dictionary = Array();
var navigate_codemirror_instances = Array();
var navigate_menu_unselect_timer = null;
//var $P = new PHP_JS();

$(window).bind('load', function()
{
    $("button, input:submit, a.uibutton, div.uibutton").button();
    $(".buttonset").buttonset();
    $(".buttonset").find('label').bind('click', function()
    {
        // force buttonset to update the state on click
        // jquery doesn't count a click if the curser moves a little
        $(this).parents('.buttonset').find('input[checked]').removeAttr('checked');
        $(this).prev().attr('checked', 'checked');
        $(this).parents('.buttonset').buttonset('refresh');
    });
    jQuery.longclick.duration = 1000; // default longlick duration

    // enable select2 for non-mobile browsers
    if(!jQuery.browser.mobile)
    {
        $(".select2").each(function(i, el)
        {
            navigate_selector_upgrade(el);
        });
    }

	$('#navigate-content').bind('mouseover', function()
	{
		clearTimeout(navigate_menu_unselect_timer);
		navigate_menu_unselect_timer = setTimeout(function()
		{
			$('#navigate-menu').tabs('select', navigate_menu_current_tab);
			$('#navigate-recent-items').slideUp();
		},
		817);
	});
	
	$('#navigate-menu').bind('mouseover', function()
	{
		clearTimeout(navigate_menu_unselect_timer);
	});

	$('#navigate-menu').tabs('select', navigate_menu_current_tab);

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
	$('#navigate-recent-items-link').bind('mouseenter', function()
	{
		$('#navigate-recent-items').css({
			top: $('#navigate-recent-items-link').offset().top + 20,
			left: $('#navigate-recent-items-link').offset().left + $('#navigate-recent-items-link').width() - $('#navigate-recent-items').width() - 5
		}).show();

        $('#navigate-recent-items').addClass('navi-ui-widget-shadow');

        $('#navigate-recent-items').menu();
	});
	
	$('#navigate-recent-items').bind('mouseleave', function()
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

            var height = 0;
            $(this).next().children().each(function()
            {
                height = height + $(this).height();
            });

            $(this).next().height(height);
        }
        return false;
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

    $('#navigate-favorite-extensions').bind('mouseleave', function()
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
    $("#navigate-content-tabs").bind("tabsshow", navigate_window_resize);

    setTimeout(function() { $(window).trigger('resize'); }, 30);

	navigate_status(navigate_t(42, "Ready"), "ready");
	
	setInterval(navigate_periodic_event, 60000); // each minute

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
 * jQuery.browser.mobile (http://detectmobilebrowser.com/)
 *
 * jQuery.browser.mobile will be true if the browser is a mobile device
 *
 **/
(function(a){jQuery.browser.mobile=/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4))})(navigator.userAgent||navigator.vendor||window.opera);

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

    if($('.navibrowse').length > 0)
    {
        $('.navibrowse').height($('.navibrowse').parent().height() - 10);
    }
}

function navigate_status(text, img, status)
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

    if(status=='error')
        $('#navigate-status').addClass("ui-state-error");
    else if(status=='highlight')
        $('#navigate-status').addClass("ui-state-highlight");
    else if(status==true || status=="true" || status=='active')
		$('#navigate-status').addClass("ui-state-active");

}

function navigate_notification(text)
{
	$.jGrowl.defaults.position = "center";

	$.jGrowl(text, { life: 4000, sticky: false, 
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
                // try an alternative method (Chrome browser work around)
                if(max_width==0)
                    max_width = $($(inst.selection.getContent({format: 'raw'}))[0]).attr('width');
                var or_styles = ' style="' + $(inst.selection.getContent({format: 'raw'}))[0].style.cssText + '" ';
            }

            var image_width = $(element).attr("image-width");
            var image_height = $(element).attr("image-height");

            if(max_width==0 || max_width > image_width)
                max_width = image_width;
            var scaled_height = Math.ceil((max_width * image_height) / image_width);
			html = '<img src="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'" width="'+max_width+'" height="'+scaled_height+'" '+or_styles+' />';
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
			if(selection_content=='') selection_content = '[file]';
			html = '<a rel="file" href="'+NAVIGATE_DOWNLOAD+'?wid='+web_id+'&id='+file_id+'&disposition=inline">'+selection_content+'</a>';
	}
		
	if(selection_active)
		tinyMCE.activeEditor.selection.setContent(html);
	else
		inst.execCommand("mceInsertContent", false, html);	
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
    var code = $("#"+$(el).attr("for")).val();

    if(!code || code=="")
    {
        $(el).parents(".ui-tabs-panel").find("div[lang]").show();
    }
    else
    {
        $(el).parents(".ui-tabs-panel").find("div[lang]").hide();
        $(el).parents(".ui-tabs-panel").find("div[lang="+code+"]").show();
    }

    $(navigate_codemirror_instances).each(function() { this.refresh(); } );
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
    setTimeout(function() { $(".ui-menu").fadeOut('fast'); }, 50);
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

function navigate_query_parameter(name)
{
	var results = new RegExp('[\\?&]' + name + '=([^&#]*)').exec(window.location.href);
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
            }
        }
    );

    $(el).prev().find('.select2-choice').css('width', parseInt($(el).css('width')) - 9);
}

function navigate_file_drop(selector, parent, callbacks, show_progress_in_title)
{
	// callbacks object
	// {
	//		afterAll: function() {},
	//		afterOne: function(file_id) {},	
	//		dragOver: function() {},
	//		dragLeave: function() {}	
	// }	
	$(function()
	{	
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
                        alert(err + ': ' + file.name);
						break;
				}
			},
			maxfiles: 1000,
			maxfilesize: NAVIGATE_MAX_UPLOAD_SIZE,    // max file size in MBs
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
						url: NAVIGATE_APP + "?fid=files&act=1&op=upload&parent=" + parent,
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

				navigate_status(navigate_lang_dictionary[261] + ": " + fname + " " + progress + "%", "loader"); // uploading
                
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
	});
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