/* navigate elements javascript functions */

// script#1

$("#item-author-text").autocomplete(
    {
        source: function(request, response)
        {
            var toFind = {
                "username": request.term,
                nd: new Date().getTime()
            };

            $.ajax(
                {
                    url: NAVIGATE_APP + "?fid=" + navigate_query_parameter("fid") + "&act=97",
                    dataType: "json",
                    method: "GET",
                    data: toFind,
                    success: function( data )
                    {
                        response( data );
                    }
                });
        },
        minLength: 1,
        select: function(event, ui)
        {
            $("#item-author").val(ui.item.id);
        }
    }
);
			

// script#2


		
// script#3
function navigate_change_association(el)
{
	var code = "";
	
	if(!el)
	{
		if($("#association_category:checked").val())
			code = "category";
		else
			code = "free";				
	}
	else if(typeof(el)=="string") 
		code = el;
	else 
		code = $("#"+$(el).attr("for")).val();	
				
	if(code=="category")
	{
		$("#div_category_tree").show();
		$("#div_category_embedded").show();
        $("#div_category_order").show();
		setTimeout(navigate_items_update_embedding, 100);
	}
	else
	{
		$("#div_category_tree").hide();			
		$("#div_category_embedded").hide();
        $("#div_category_order").hide();
		$("#div_template_select").show();
		
		for(i in active_languages)
			$("#div_path_" + active_languages[i]).show();
	}
}

function navigate_items_update_embedding()
{
	if($("#embedding_0:checked").length == 1)
	{
		$("#div_template_select").show();
		for(i in active_languages)
			$("#div_path_" + active_languages[i]).show();
			
	}
	else
	{
		$("#div_template_select").hide();
		for(i in active_languages)
			$("#div_path_" + active_languages[i]).hide();				
	}
}

// script#4
function navigate_periodic_event_delegate()
{
	// autosave text sections (for each language)
	var data = {};
	
	for(al in active_languages)
	{					
		for(s in template_sections)
		{
			var section_id = "section-" + template_sections[s]["code"] + "-" + active_languages[al];
			
			if(!template_sections[s]["text"]) template_sections[s]["text"] = [];
			if(!template_sections[s]["text"][active_languages[al]]) template_sections[s]["text"][active_languages[al]] = "";

			switch(template_sections[s]["editor"])
			{
				case "tinymce":
					if(tinyMCE.get(section_id).initialized == 1) continue;
					template_sections[s]["text"][active_languages[al]] = tinyMCE.get(section_id).getContent({ format: "raw" });
					break;
					
				case "html": // (Codemirror) => a plain textarea
					template_sections[s]["text"][active_languages[al]] = $("#"+section_id).val();
					break;
					
				default: // plain textarea
					template_sections[s]["text"][active_languages[al]] = $("#"+section_id).val();
					break;	
			}
			
			data[section_id] = template_sections[s]["text"][active_languages[al]];
		}
	}

	navigate_status(navigate_lang_dictionary[270] + "...", "loader", false);	// autosave in progress
	
	$.ajax({
	   type: "POST",
	   url: NAVIGATE_APP + "?fid=" + navigate_query_parameter("fid") + "&act=autosave&id=" + navigate_query_parameter("id"),
	   data: data,
	   success: function(msg) 
	   {
           if(msg=='changes_saved')
           {
                var currentTime = new Date();
                var hours = currentTime.getHours();
                var minutes = currentTime.getMinutes();
                if (hours < 10) hours = "0" + hours;
                if (minutes < 10) minutes = "0" + minutes;

                navigate_status(navigate_lang_dictionary[271] + " " + hours + ":" + minutes, "ready", 'active'); // autosave completed
                setTimeout(function() { navigate_status(navigate_lang_dictionary[42], "ready"); }, 15000); // ready
           }
           else if(msg=='no_changes')
           {
               navigate_status(navigate_lang_dictionary[42], "ready");
           }
           else if(msg=='false')
           {
               // incorrect parameters, never should happen
           }
           else
           {
               // unexpected response, probably the content was not saved! warn the user
               navigate_status(navigate_lang_dictionary[440], "error", 'error'); // autosave completed
           }

	   }
	 });					
}

function navigate_items_disable_spellcheck()
{
	for(al in active_languages)
	{					
		for(s in template_sections)
		{
			var section_id = "section-" + template_sections[s]["code"] + "-" + active_languages[al];
			
			if(!template_sections[s]["text"]) template_sections[s]["text"] = [];
			if(!template_sections[s]["text"][active_languages[al]]) template_sections[s]["text"][active_languages[al]] = "";
			
			if(template_sections[s]["editor"]=="tinymce")
			{
				if(tinyMCE.get(section_id).plugins.spellchecker.active == 1)
				{
					// force disable tinymce spellchecker plugin by getting the raw contents...
					var foo = tinyMCE.get(section_id).getContent({ format: "raw" });
				}
			}
		}
	}
}

// script#5
function navigate_items_path_check(el, ev)
{
    var caret_position = null;
    if($(el).is('input') && $(el).is(':focus'))
        caret_position = $(el).caret();

	if($(el).val()=="") return;
	
	if($(el).val()==last_check[$(el).id]) return;
	
	var path = $(el).val();
    path = path.replace(/([\'"“”«»?:\+\&!¿#\\\\])/g, "");
    path = path.replace(/[.\s]+/g, "_");
    $(el).val(path);
	
	last_check[$(el).id] = path;
	
	$(el).next().html('<img src="' + NAVIGATE_URL + '/img/loader.gif" align="absmiddle" />');
	
	$.ajax({
	  url: NAVIGATE_APP + "?fid=" + navigate_query_parameter("fid") + "&act=95",
	  dataType: "json",
	  data: "id=" + item_id + "&path=" + $(el).val(),
	  type: "get",
	  success: function(data, textStatus)
	  {
		  var free = true;

		  if(data && data.length==1)
		  {
			 // same element?
			 if( data[0].object_id != item_id ||
				 data[0].type != "item" )
			 {
				free = false; 
			 }
		  }
		  else if(data && data.length > 1)
		  {
			  free = false;
		  }
		  
		  if(free)	free = "<img src=\"" + NAVIGATE_URL + "/img/icons/silk/tick.png\" align=\"absmiddle\" />";
		  else		free = "<img src=\"" + NAVIGATE_URL + "/img/icons/silk/cancel.png\" align=\"absmiddle\" />";

          free += "<img class=\"erase_path\" src=\"" + NAVIGATE_URL + "/img/icons/silk/erase.png\" align=\"absmiddle\" />";
          $(el).next().find(".erase_path").off();
		  $(el).next().html(free);
          $(el).next().find(".erase_path").on("click", function()
          {
              $(el).focus();
              $(el).val("");
          }).css('cursor', 'pointer');
	  }
	});

    if($(el).is('input') && $(el).is(':focus'))
        $(el).caret(caret_position);
}

//$(window).bind("load", function()
//{
	for(al in active_languages)
	{					
		navigate_items_path_check($("#path-" + active_languages[al]));

		$("#path-" + active_languages[al]).bind("focus", function()
		{
			if($(this).val() == "")
				navigate_items_path_generate($(this));
		});
	}				
//});

function navigate_items_path_generate(el)
{
	var language = $(el).attr("id").substr(5);
	var surl;
	if(item_category_path[language] && item_category_path[language]!="")
		surl = item_category_path[language];
	else
		surl = "/" + language;
	var title = $("#title-"+language).val();
	title = title.replace(/(['"?:\+\&¿!#\\])/g, '');
    title = title.replace(/[.\s]+/g, "_");
    surl += "/" + title;
	$(el).val(surl.toLowerCase());
	navigate_items_path_check(el);
}

var navigate_items_language_selected = '';
function navigate_items_select_language(el)
{
	var code;
	if(typeof(el)=="string") 
	{
		code = el;
        $('input[name="language_selector[]"]').parent().find('label').removeClass('ui-state-active');
		$('label[for="language_selector_' + code + '"]').addClass('ui-state-active');
	}
	else 
		code = $("#"+$(el).attr("for")).val();	
		
	$(".language_fields").css("display", "none");
	$("#language_fields_" + code).css("display", "block");
	
	$("#language_selector_" + code).attr("checked", "checked");
	
	navigate_items_language_selected = code;
	
    $(navigate_codemirror_instances).each(function() { this.refresh(); } );
}


function navigate_items_copy_from_change_origin(el)
{			
	$("#navigate_items_copy_from_language_selector").parent().hide();
	$("#navigate_items_copy_from_template").parent().hide();
	$("#navigate_items_copy_from_title").parent().hide();
	$("#navigate_items_copy_from_section").parent().hide();
			
	switch($("#"+$(el).attr("for")).val())
	{
		case "language":
			$("#navigate_items_copy_from_language_selector").parent().show();
			$.ajax(
				{
					url: NAVIGATE_APP + '?fid=' + navigate_query_parameter('fid') + "&act=template_sections",
					dataType: "json",
					method: "GET",
					data: {
                        id: item_id
                    },
					success: function( data ) 
					{
						$("#navigate_items_copy_from_section").empty();
						
						$.each(data, function(row) 
						{
							$("#navigate_items_copy_from_section").append(
                                $("<option></option>").val(data[row].code).html(data[row].name)
                            );
						});
						
						$("#navigate_items_copy_from_section").parent().show();

                        if($("#navigate_items_copy_from_section").prev().hasClass('select2'))
                        {
                            $("#navigate_items_copy_from_section").select2(
                                'val',
                                $("#navigate_items_copy_from_section").select2('val')
                            );
                        }
					}
				});							
			break;
			
		case "template":
			$("#navigate_items_copy_from_template").parent().show();
			break;
			
		case "item":
			$("#navigate_items_copy_from_language_selector").parent().show();
			$("#navigate_items_copy_from_title").parent().show();	
			break;	
	}
}

function navigate_items_copy_from_dialog_process(dest)
{			
	switch($("input[name=\'navigate_items_copy_from_type[]\']:checked").val())
	{
		case "language":
			var lang = $("#navigate_items_copy_from_language_selector").val();
			tinyMCE.get(dest).setContent(
				tinyMCE.get("section-" + $("#navigate_items_copy_from_section").val() + "-" + lang).getContent({ format: "raw" }),
				{ format: "raw" }
			);
			break;
			
		case "template":
			$.ajax(
			{
				url: NAVIGATE_APP + '?fid=' + navigate_query_parameter('fid') + '&act=93',
				dataType: "html",
				method: "GET",
				data: { 
					"id": $("#navigate_items_copy_from_template").val()
				},
				success: function( data ) 
				{
					tinyMCE.get(dest).setContent(data, { format: "raw" });
				}
			});	
			break;
			
		case "item":		
			$.ajax(
			{
				url: NAVIGATE_APP + '?fid=' + navigate_query_parameter('fid') + '&act=92',
				dataType: "html",
				method: "GET",
				data: { 
					"lang": $("#navigate_items_copy_from_language_selector").val(),
					"node_id": $("#navigate_items_copy_from_item_id").val(),
					"section": $("#navigate_items_copy_from_section").val()
				},
				success: function( data ) 
				{
					tinyMCE.get(dest).setContent(data, { format: "raw" });
				}
			});					
			
			break;	
	}	
}

function navigate_items_copy_from_history_dialog(element, section, language, type)
{
	// load history items (synchronously)
    navigate_status(navigate_t(185, "Searching elements"), "loader", true);

	$.ajax(
	{
		url: NAVIGATE_APP + '?fid=' + navigate_query_parameter('fid') + '&act=90',
		dataType: "json",
		async: false,
		method: "GET",
		data: {
			"section": section,
			"lang": language,
			"id": item_id
		},
		success: function( data )
		{
			$("#navigate_items_copy_from_history_options").html("");

            navigate_status(navigate_t(42, "Ready"), "ready");

			if(!data[0]) return false;

			for(i in data)
			{
				if(i==0)
					$("#navigate_items_copy_from_history_options").append('<option selected="selected" value="'+data[i].id+'" type="'+type+'">'+data[i].date+'</option>');
				else
					$("#navigate_items_copy_from_history_options").append('<option value="'+data[i].id+'" type="'+type+'">'+data[i].date+'</option>');
			}

            // load first history item
            navigate_items_copy_from_history_preview(data[0].id, type);

			$("#navigate_items_copy_from_history").dialog(
			{
				title: "<img src=\"img/icons/silk/time_green.png\" align=\"absmiddle\"> " + navigate_lang_dictionary[40], // history
				modal: true,
				buttons:
				[
					{
						text: navigate_lang_dictionary[58], // cancel
						click: function()
						{
							$(this).dialog("close");
							$(window).trigger("resize");
						}
					},
					{
						text: navigate_lang_dictionary[190], // ok (copy)
						click: function()
						{
							if(type=="tinymce")
							{
								tinyMCE.get(element).setContent($("#navigate_items_copy_from_history_text_raw").html(), { format: "raw" });
							}
							else // raw
							{
								$("#" + element).val($("#navigate_items_copy_from_history_text_raw").html());
							}

							$(this).dialog("close");
							$(window).trigger("resize");
						}
					}
				],
				width: 750,
				height: 450
			});
		}
	});

	return false;
}

function navigate_items_copy_from_history_preview(id, type)
{
    $.get(
        NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "&act=92&history=true&id="+id,
        function(data)
        {
            var data_view = data;
            if(type != 'tinymce')
                data_view = phpjs_nl2br(data, false);

            $("#navigate_items_copy_from_history_text").off('load').on('load', function()
            {
                $("#navigate_items_copy_from_history_text").contents().find('html').html('<head></head><body></body>');
                $("#navigate_items_copy_from_history_text").contents().find('head').html($('#navigate_items_copy_from_history_stylesheets').val());
                $("#navigate_items_copy_from_history_text").contents().find('body').html(data_view);
                $("#navigate_items_copy_from_history_text_raw").html(data);
            });
            $("#navigate_items_copy_from_history_text").attr('src', 'about:blank');
        }
    );
	//$("#navigate_items_copy_from_history_text").load(NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "&act=92&history=true&id="+id);
}

function navigate_items_copy_from_history_remove()
{
	var id = $("#navigate_items_copy_from_history_options").val();
	
	$.get(NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "'&act=89&id="+id, function(data)
	{
		$("#navigate_items_copy_from_history_options").find("option[value="+id+"]").remove();
		$("#navigate_items_copy_from_history_options").val($("#navigate_items_copy_from_history_options").find("option:first").val());
		$("#navigate_items_copy_from_history_options").trigger("change");
	});
}


function navigate_items_copy_from_theme_samples(element, section, language, type)
{
    var data = theme_content_samples;

    $("#navigate_items_copy_from_theme_samples_options").html("");

    if(!data[0]) return false;

    for(i in data)
    {
        $("#navigate_items_copy_from_theme_samples_options").append(
            '<option value="'+data[i].file+'" type="'+type+'">'+data[i].title+'</option>'
        );
    }

    $("#navigate_items_copy_from_theme_samples").dialog(
        {
            title: "<img src=\"img/icons/silk/rainbow.png\" align=\"absmiddle\"> " + navigate_lang_dictionary[368], // theme
            modal: true,
            buttons:
                [
                    {
                        text: navigate_lang_dictionary[58], // cancel
                        click: function()
                        {
                            $(this).dialog("close");
                            $(window).trigger("resize");
                        }
                    },
                    {
                        text: navigate_lang_dictionary[190], // ok (copy)
                        click: function()
                        {
                            if(type=="tinymce")
                            {
                                tinyMCE.get(element).execCommand('mceInsertContent', false, $("#navigate_items_copy_from_theme_samples_text_raw").html());
                            }
                            else // raw
                            {
                                $("#" + element).val($("#navigate_items_copy_from_theme_samples_text_raw").html() + " " + $("#" + element).html());
                            }

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
                navigate_items_copy_from_theme_samples_preview(data[0].file, type);
            }
        }
    ).dialogExtend(
        {
            maximizable: true
        }
    );

    return false;
}

function navigate_items_copy_from_theme_samples_preview(file, type)
{
    file = file + '?random=' + new Date().getTime();

    $('#navigate_items_copy_from_theme_samples_text').html(
        '<iframe width="100%" height="100" frameborder="0" src="' + NAVIGATE_URL + "/themes/" + website_theme + "/" + file + '"></iframe>'
    );

    $('#navigate_items_copy_from_theme_samples_text iframe').attr(
        'height',
        $('#navigate_items_copy_from_theme_samples_text').parent().parent().height() - 50
    );

    $("#navigate_items_copy_from_theme_samples_text iframe").on("load", function()
    {
        $("#navigate_items_copy_from_theme_samples_text iframe")
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
            $("#navigate_items_copy_from_theme_samples_text_raw").html(
                $(data).filter('#navigate-theme-content-sample').html()
            );

            $("#navigate_items_copy_from_theme_samples_text_raw img").each(function()
            {
                if( $(this).attr("src").substring(0, 7)!='http://'  &&
                    $(this).attr("src").substring(0, 7)!='https://'
                  )
                    $(this).attr("src", NAVIGATE_URL + "/themes/" + website_theme + "/" + $(this).attr("src"));
            });
        }
    );
}


function navigate_items_copy_from_dialog(dest)
{
    $("#navigate_items_copy_from").dialog(
        {
            title: "<img src=\"img/icons/silk/page_white_copy.png\" align=\"absmiddle\"> " + navigate_lang_dictionary[189] + "...",
            modal: true,
            buttons:
            [
                {
                    text: navigate_lang_dictionary[58], // cancel
                    click: function()
                    {
                        $(this).dialog("close");
                        $(window).trigger("resize");
                    }
                },
                {
                    text: navigate_lang_dictionary[190], // ok
                    click: function()
                    {
                        navigate_items_copy_from_dialog_process(dest);
                        $(this).dialog("close");
                        $(window).trigger("resize");
                    }
                }
            ],
            width: 650,
            height: 235,
            open: function()
            {
                // destroy and create the title autocomplete widget each time the dialog is shown
                if($('#navigate_items_copy_from_title').hasClass('.ui-autocomplete-input'))
                    $("#navigate_items_copy_from_title").autocomplete("destroy");

                $("#navigate_items_copy_from_title").autocomplete(
                    {
                        source: function(request, response)
                        {
                            $("#navigate_items_copy_from_section").parent().hide();
                            var toFind = {
                                "title": request.term,
                                "lang": $("#navigate_items_copy_from_language_selector").val(),
                                nd: new Date().getTime()
                            };

                            $.ajax(
                                {
                                    url: NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "&act=91",
                                    dataType: "json",
                                    method: "GET",
                                    data: toFind,
                                    success: function( data )
                                    {
                                        response( data );
                                    }
                                });
                        },
                        minLength: 1,
                        select: function navigate_items_copy_from_title_callback(event, ui)
                        {
                            $("#navigate_items_copy_from_item_id").val(ui.item.id);
                            $.ajax(
                                {
                                    url: NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "&act=template_sections",
                                    dataType: "json",
                                    method: "GET",
                                    data: {id: ui.item.id},
                                    success: function( data )
                                    {
                                        $("#navigate_items_copy_from_section").empty();

                                        $.each(data, function(row)
                                        {
                                            $("#navigate_items_copy_from_section").append(
                                                $("<option></option>").val(data[row].code).html(data[row].name)
                                            );
                                        });

                                        $("#navigate_items_copy_from_section").parent().show();
                                    }
                                });
                        }
                    });
            }
        }
    );
}

// script#6		
// gallery
function navigate_items_gallery_change_captions(image_id, last_image_id)
{		
	if(last_image_id!=null)
	{
		for(lang in active_languages)
		{
			$("#items-gallery-item-"+last_image_id+"-dictionary-"+active_languages[lang]).remove();
		}
	}
	
	if(image_id!=null)
	{			
		for(lang in active_languages)
		{				
			$("#items-gallery-elements")
				.after('<input type="hidden" value="" ' +
						   'name="items-gallery-item-'+image_id+'-dictionary-'+active_languages[lang]+'" ' +
						   '  id="items-gallery-item-'+image_id+'-dictionary-'+active_languages[lang]+'" />');	
		}
	}
		
}

function navigate_items_gallery_parse()
{
	$("#items-gallery-elements-order").val("");
	$("#items-gallery-elements").children().each(function()
	{
		var id = $(this).children("div:first").attr("id");
		if(id)
		{
			id = id.replace("items-gallery-item-", "");
			id = id.replace("-droppable", "");				
		
			if(parseInt(id) > 0) 
				$("#items-gallery-elements-order").val($("#items-gallery-elements-order").val() + "#" + id);
		}
	});
	$("#items-gallery-elements-order").val($("#items-gallery-elements-order").val().substr(1));
}

$("#items-gallery-elements").sortable({
	containment: "#navigate-content-tabs",
	scroll: true,
	update: navigate_items_gallery_parse
});

$("#items-gallery-elements div.navigate-droppable").droppable(
{
	accept: ".draggable-image", 
	hoverClass: "navigate-droppable-hover",
	drop: function(event, ui) 
	{
		navigate_items_gallery_drop(this, event, ui);
	}
});

function navigate_items_gallery_drop(el, event, ui)
{	
	var file_id = $(ui.draggable).attr("id").substring(5);
	
	if($(el).attr("id")=="items-gallery-item-empty-droppable")
	{
		// create a new node
		var new_node;
		new_node = '<li>' +
				   '<div id="items-gallery-item-' + file_id + '-droppable" website_id="' + navigate_media_browser_website + '" class="navigate-droppable ui-corner-all">' +
				   $(ui.draggable).html() +
				   '</div> ' +
				   '<div class="navigate-droppable-cancel"><img src="img/icons/silk/cancel.png" /></div> ' +
				   '</li>';

		$(el).parent().before($(new_node));
		$(el).parent().prev().find("div.navigate-droppable").droppable({
			accept: ".draggable-image", 
			hoverClass: "navigate-droppable-hover",
			drop: function(event, ui) 
			{				
				navigate_items_gallery_drop(this, event, ui);
			}
		});
        $('#items-gallery-elements').find('.navigate-droppable-cancel').show();
		navigate_items_gallery_change_captions(file_id);
	}
	else
	{
        // reorder nodes
		var last_id = $(el).attr("id");
		last_id = last_id.replace("items-gallery-item-", "");
		last_id = last_id.replace("-droppable", "");
		navigate_items_gallery_change_captions(file_id, last_id);	
					
		$(el).attr("id", "items-gallery-item-" + file_id + "-droppable");
		$(el).attr("name", "items-gallery-item-" + file_id + "-droppable");				
		$(el).html($(ui.draggable).html());
	}
	navigate_items_gallery_parse();
}

$("#items-gallery-elements .navigate-droppable-cancel").live("click", function()
{
	var id = $(this).prev().attr("id");
	id = id.replace("items-gallery-item-", "");
	id = id.replace("-droppable", "");
	navigate_items_gallery_change_captions(null, id);
	$(this).parent().remove();
	navigate_items_gallery_parse();
});

$("#items-gallery-elements .navigate-droppable").live("mouseenter", function()
{
	if($(this).attr('id')!="items-gallery-item-empty-droppable")
		navigate_status(navigate_t(286, "Drag to reorder. Double click a item to set a caption."), "ready");
});

$("#items-gallery-elements .navigate-droppable").live("mouseleave", function()
{
	navigate_status(navigate_t(42, "Ready"), "ready");
});


// script#7
// comments moderator autocomplete
$("#item-comments_moderator-text").select2(
{
    placeholder: $("#item-comments_moderator-helper").text(),
    minimumInputLength: 1,
    ajax: {
        url: NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "&act=json_find_user",
        dataType: "json",
        quietMillis: 100,
        data: function (term, page)
        {   // page is the one-based page number tracked by Select2
            return {
                username: term,
                nd: new Date().getTime(),
                page_limit: 30, // page size
                page: page // page number
            };
        },
        results: function (data, page)
        {
            var more = (page * 5) < data.total; // whether or not there are more results available
            // notice we return the value of more so Select2 knows if more results can be loaded
            return {results: data.rows, more: more};
        }
    },
    formatResult: function(row) { return row.username; },
    formatSelection: function(row) { return row.username + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>"; },
    triggerChange: true,
    allowClear: true,
    initSelection : function (element, callback)
    {
        var data = {
            id: $("#item-comments_moderator").val(),
            username: element.val()
        };

        callback(data);
    }
});

$("#item-comments_moderator-text").on("change", function(e)
{
    $("#item-comments_moderator").val(e.val);
});



// script #8
// comments moderation

$("#items-comments-toolbar-publish").on("click", function()
{
	var ica = item_comment_active;
	$.ajax(
	{
		url: NAVIGATE_APP + "?fid=items&act=98&id=" + ica + "&opt=publish",
		dataType: "text",
		method: "GET",
		success: function( data ) 
		{
			if(data=="true")	
				$("#items-comment-" + ica).attr("class", "items-comment-message items-comment-status-public");
		}
	});					
});

$("#items-comments-toolbar-unpublish").on("click", function()
{
	var ica = item_comment_active;
	$.ajax(
	{
		url: NAVIGATE_APP + "?fid=items&act=98&id=" + ica + "&opt=unpublish",
		dataType: "text",
		method: "GET",
		success: function( data ) 
		{
			if(data=="true")	
				$("#items-comment-" + ica).attr("class", "items-comment-message items-comment-status-private");
		}
	});					
});			

$("#items-comments-toolbar-delete").on("click", function()
{
	var ica = item_comment_active;
	$.ajax(
	{
		url: NAVIGATE_APP + "?fid=items&act=98&id=" + ica + "&opt=delete",
		dataType: "text",
		method: "GET",
		success: function( data ) 
		{
			if(data=="true")	
			{
				$("#items-comment-" + ica).parent().remove();
				$("#items-comments-toolbar").hide();
			}
		}
	});					
});	

$("#items-comments-toolbar").parent().on("scroll", function()	 { $("#items-comments-toolbar").hide(); });
$(".items-comment-message").on("mousemove", function() { navigate_items_comment_toolbar(this); });

var item_comment_active = 0;
function navigate_items_comment_toolbar(el)
{
	item_comment_active = $(el).attr("id").substr(14);
	if($(el).position().top > 20)
		$("#items-comments-toolbar").css($(el).offset()).show();
	else
		$("#items-comments-toolbar").hide();
		
	$("#items-comments-toolbar-publish").show();
	$("#items-comments-toolbar-unpublish").show();
	$("#items-comments-toolbar-delete").show();	
	
	if($(el).hasClass("items-comment-status-private"))
		$("#items-comments-toolbar-unpublish").hide();	

	if($(el).hasClass("items-comment-status-hidden"))
		$("#items-comments-toolbar-unpublish").hide();	

	if($(el).hasClass("items-comment-status-public"))
		$("#items-comments-toolbar-publish").hide();	
}

function navigate_items_tabform_submit(formNum)
{
	var tab = parseInt($('#navigate-content-tabs').children('div:visible').attr('id').replace("navigate-content-tabs-", "")) - 1;
	if(tab < 0) tab = 0;
	var url = $('#navigate-content').find('form').eq(formNum).attr('action');	
	$('#navigate-content').find('form').eq(formNum).attr('action', $.query.load(url).set('tab', tab).set('tab_language', navigate_items_language_selected).toString());
	$('#navigate-content').find('form').eq(formNum).submit();	
}
