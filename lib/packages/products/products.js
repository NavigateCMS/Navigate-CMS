/* navigate products function javascript code */

$("#product-author-text").autocomplete(
    {
        source: function(request, response)
        {
            var toFind = {
                "username": request.term,
                nd: new Date().getTime()
            };

            $.ajax(
                {
                    url: NAVIGATE_APP + "?fid=products&act=json_find_user&format=autocomplete",
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
            $("#product-author").val(ui.item.id);
        }
    }
);
			
function navigate_periodic_event_delegate()
{
	// autosave text sections (for each language)
	var data = {};
	
	for(al in active_languages)
	{					
		for(s in template_sections)
		{
			var section_id = "section-" + template_sections[s]["id"] + "-" + active_languages[al];

			if(!template_sections[s]["text"])
				template_sections[s]["text"] = [];

			if(!template_sections[s]["text"][active_languages[al]])
				template_sections[s]["text"][active_languages[al]] = "";

			switch(template_sections[s]["editor"])
			{
				case "tinymce":
					if(!tinyMCE.get(section_id) || tinyMCE.get(section_id).initialized != true) continue;
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
	   url: NAVIGATE_APP + "?fid=products&act=autosave&id=" + navigate_query_parameter("id"),
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


function navigate_products_tags_copy_from_language(from, to)
{
	$("#tags-" + to).tagit("removeAll");
	var tags = $("#tags-" + from).tagit("assignedTags");

	for(i in tags)
		$("#tags-" + to).tagit("createTag", tags[i]);
}

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


function navigate_products_path_check(el, ev)
{
    var caret_position = null;
    if($(el).is('input') && $(el).is(':focus'))
        caret_position = $(el).caret();

	if($(el).val()=="") return;
	
	if($(el).val()==last_checks[$(el).id]) return;
	
	var path = $(el).val();
	path = path.replace(/(['"“”«»?:\+\&!¿#\\\\])/g, "");
	path = path.replace(/[.\s]+/g, navigate["word_separator"]);

	$(el).val(path);
	
	last_checks[$(el).id] = path;
	
	$(el).next().html('<img src="' + NAVIGATE_URL + '/img/loader.gif" align="absmiddle" />');

	$.ajax({
	  url: NAVIGATE_APP + "?fid=products&act=path_free_check",
	  dataType: "json",
	  data: "id=" + product_id + "&path=" + $(el).val(),
	  type: "get",
	  complete: function(data, textStatus)
	  {
		  var free = true;

		  if(data && data.length==1)
		  {
			 // same element?
			 if( data[0].object_id != product_id ||
				 data[0].type != "product" )
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

function navigate_products_sku_check(el, ev)
{
    $(el).next().html("");
    if($(el).val()=="") return;

    $(el).next().html('<img src="' + NAVIGATE_URL + '/img/loader.gif" align="absmiddle" />');

    if($(el).val()==last_checks[$(el).id]) return;

    last_checks[$(el).id] = $(el).val();

    $.ajax({
        url: NAVIGATE_APP + "?fid=products&act=sku_free_check",
        dataType: "json",
        data: "id=" + product_id + "&sku=" + $(el).val(),
        type: "get",
        success: function(data, textStatus)
        {
            var free = true;

            if(data && data.length==1)
            {
                // same product?
                if(data[0].id != product_id)
                    free = false;
            }
            else if(data && data.length > 1)
            {
                free = false;
            }

            if(free)	free = "<img src=\"" + NAVIGATE_URL + "/img/icons/silk/tick.png\" align=\"absmiddle\" />";
            else		free = "<img src=\"" + NAVIGATE_URL + "/img/icons/silk/cancel.png\" align=\"absmiddle\" />";

            free += "<img class=\"erase_sku\" src=\"" + NAVIGATE_URL + "/img/icons/silk/erase.png\" align=\"absmiddle\" />";
            $(el).next().find(".erase_sku").off();
            $(el).next().html(free);
            $(el).next().find(".erase_sku").on("click", function()
            {
                $(el).focus();
                $(el).val("");
            }).css('cursor', 'pointer');
        }
    });
}

function navigate_products_path_generate(el)
{
	var language = $(el).attr("id").substr(5);
	var surl;
	if(product_category_path[language] && product_category_path[language]!="")
		surl = product_category_path[language];
	else
		surl = "/" + language;
	var title = $("#title-"+language).val();
	title = title.replace(/(['"“”«»?:\+\&!¿#\\\\])/g, "");
	title = title.replace(/[.\s]+/g, navigate["word_separator"]);
    surl += "/" + title;
	$(el).val(surl.toLowerCase());
	navigate_products_path_check(el);
}

var navigate_products_language_selected = '';
function navigate_products_select_language(el)
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
	
	navigate_products_language_selected = code;
	
    $(navigate_codemirror_instances).each(function() { this.refresh(); } );
}


function navigate_products_copy_from_change_origin(el)
{			
	$("#navigate_products_copy_from_language_selector").parent().hide();
	$("#navigate_products_copy_from_template").parent().hide();
	$("#navigate_products_copy_from_element_title").parent().hide();
	$("#navigate_products_copy_from_product_title").parent().hide();
	$("#navigate_products_copy_from_section").parent().hide();
			
	switch($("#"+$(el).attr("for")).val())
	{
		case "language":
			$("#navigate_products_copy_from_language_selector").parent().show();
			$.ajax(
				{
					url: NAVIGATE_APP + '?fid=' + navigate_query_parameter('fid') + "&act=copy_from_template_zones",
					dataType: "json",
					method: "GET",
					data: {
                        id: product_id
                    },
					success: function( data ) 
					{
						$("#navigate_products_copy_from_section").empty();
						
						$.each(data, function(row) 
						{
							$("#navigate_products_copy_from_section").append(
                                $("<option />").val(data[row].id).html(data[row].title)
                            );
						});
						
						$("#navigate_products_copy_from_section").parent().show();

                        if($("#navigate_products_copy_from_section").hasClass('select2'))
                        {
                            $("#navigate_products_copy_from_section").trigger("change");
                        }
					}
				});							
			break;
			
		case "item":
			$("#navigate_products_copy_from_language_selector").parent().show();
			$("#navigate_products_copy_from_element_title").parent().show();
			break;

		case "product":
            $("#navigate_products_copy_from_language_selector").parent().show();
            $("#navigate_products_copy_from_product_title").parent().show();
			break;
	}
}

function navigate_products_copy_from_dialog_process(dest)
{			
	if(!dest || dest=="")
		return;

	switch($("input[name=\'navigate_products_copy_from_type[]\']:checked").val())
	{
		case "language":
			var lang = $("#navigate_products_copy_from_language_selector").val();
			var copy_from_section = $("#navigate_products_copy_from_section").val();
			tinyMCE.get(dest).setContent(
				tinyMCE.get("section-" + copy_from_section + "-" + lang).getContent({ format: "raw" }),
				{ format: "raw" }
			);
			break;
			
		case "item":
			var copy_from_section = $("#navigate_products_copy_from_section").val();
			$.ajax(
			{
				url: NAVIGATE_APP + '?fid=items&act=raw_zone_content',
				dataType: "html",
				method: "GET",
				data: { 
					"lang": $("#navigate_products_copy_from_language_selector").val(),
					"node_id": $("#navigate_products_copy_from_item_id").val(),
					"zone": $("#navigate_products_copy_from_section option:selected").data("type"),
					"section": copy_from_section
				},
				success: function( data ) 
				{
					tinyMCE.get(dest).setContent(data, { format: "raw" });
				}
			});					
			
			break;

		case "product":
			var copy_from_section = $("#navigate_products_copy_from_section").val();
			$.ajax(
			{
				url: NAVIGATE_APP + '?fid=products&act=raw_zone_content',
				dataType: "html",
				method: "GET",
				data: {
					"lang": $("#navigate_products_copy_from_language_selector").val(),
					"node_id": $("#navigate_products_copy_from_product_id").val(),
					"zone": $("#navigate_products_copy_from_section option:selected").data("type"),
					"section": copy_from_section
				},
				success: function( data )
				{
					tinyMCE.get(dest).setContent(data, { format: "raw" });
				}
			});

			break;
	}	
}

function navigate_products_copy_from_history_dialog(element, section, language, type)
{
	// load content history (synchronously)
    navigate_status(navigate_t(185, "Searching elements"), "loader", true);

	$.ajax(
	{
		url: NAVIGATE_APP + '?fid=' + navigate_query_parameter('fid') + '&act=load_content_history',
		dataType: "json",
		async: false,
		method: "GET",
		data: {
			"section": section,
			"lang": language,
			"id": product_id
		},
		success: function( data )
		{
			$("#navigate_products_copy_from_history_options").html("");

            navigate_status(navigate_t(42, "Ready"), "ready");

			if(!data[0]) return false;

			for(i in data)
			{
				if(i==0)
					$("#navigate_products_copy_from_history_options").append('<option selected="selected" value="'+data[i].id+'" type="'+type+'">'+data[i].date+'</option>');
				else
					$("#navigate_products_copy_from_history_options").append('<option value="'+data[i].id+'" type="'+type+'">'+data[i].date+'</option>');
			}

            // load first history item
            navigate_products_copy_from_history_preview(data[0].id, type);

			$("#navigate_products_copy_from_history").dialog(
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
								tinyMCE.get(element).setContent(
									$("#navigate_products_copy_from_history_text_raw").html(), { format: "raw" }
								);
							}
							else // raw
							{
								$("#" + element).val(
									$("#navigate_products_copy_from_history_text_raw").html()
								);
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

function navigate_products_copy_from_history_preview(id, type)
{
    $.get(
        NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "&act=raw_zone_content&history=true&id="+id,
        function(data)
        {
            var data_view = data;
            if(type != 'tinymce')
                data_view = phpjs_nl2br(data, false);

            $("#navigate_products_copy_from_history_text").off('load').on('load', function()
            {
                $("#navigate_products_copy_from_history_text").contents().find('html').html('<head></head><body></body>');
                $("#navigate_products_copy_from_history_text").contents().find('head').html($('#navigate_products_copy_from_history_stylesheets').val());
                $("#navigate_products_copy_from_history_text").contents().find('body').html(data_view);
                $("#navigate_products_copy_from_history_text_raw").html(data);
            });
            $("#navigate_products_copy_from_history_text").attr('src', 'about:blank');
        }
    );
	//$("#navigate_products_copy_from_history_text").load(NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "&act=92&history=true&id="+id);
}

function navigate_products_copy_from_history_remove()
{
	var id = $("#navigate_products_copy_from_history_options").val();
	
	$.get(NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "'&act=delete_content_history&id="+id, function(data)
	{
		$("#navigate_products_copy_from_history_options").find("option[value="+id+"]").remove();
		$("#navigate_products_copy_from_history_options").val($("#navigate_products_copy_from_history_options").find("option:first").val());
		$("#navigate_products_copy_from_history_options").trigger("change");
	});
}

function navigate_products_copy_from_dialog(dest)
{
    $("#navigate_products_copy_from").dialog(
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
                        navigate_products_copy_from_dialog_process(dest);
                        $(this).dialog("close");
                        $(window).trigger("resize");
                    }
                }
            ],
            width: 650,
            height: 235,
            open: function()
            {
            	// ELEMENT finder
                // destroy and create the title autocomplete widget each time the dialog is shown
                if($('#navigate_products_copy_from_element_title').hasClass('.ui-autocomplete-input'))
                    $("#navigate_products_copy_from_element_title").autocomplete("destroy");

                var ace = $("#navigate_products_copy_from_element_title").autocomplete(
                    {
                        source: function(request, response)
                        {
                            $("#navigate_products_copy_from_section").parent().hide();
                            var toFind = {
                                "title": request.term,
                                "lang": $("#navigate_products_copy_from_language_selector").val(),
                                nd: new Date().getTime()
                            };

                            $.ajax(
                                {
                                    url: NAVIGATE_APP + "?fid=items&act=search_by_title",
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
                        select: function navigate_products_copy_from_title_callback(event, ui)
                        {
                            $("#navigate_products_copy_from_item_id").val(ui.item.id);
                            $.ajax({
								url: NAVIGATE_APP + "?fid=items&act=copy_from_template_zones",
								dataType: "json",
								method: "GET",
								data: {id: ui.item.id},
								success: function( data )
								{
									$("#navigate_products_copy_from_section").empty();

									$.each(data, function(row)
									{
										$("#navigate_products_copy_from_section").append(
											$("<option data-type='"+data[row].type+"'></option>").val(data[row].id).html(data[row].title)
										);
									});

									$("#navigate_products_copy_from_section").parent().show();
								}
							});
                        }
                    });

				$(ace).trigger("autocompleteselect");
				$("#navigate_products_copy_from").find(".buttonset").controlgroup('refresh');

				// PRODUCT finder
                if($('#navigate_products_copy_from_product_title').hasClass('.ui-autocomplete-input'))
				{
					$("#navigate_products_copy_from_product_title").autocomplete("destroy");
				}

                var acp = $("#navigate_products_copy_from_product_title").autocomplete(
                    {
                        source: function(request, response)
                        {
                            $("#navigate_products_copy_from_section").parent().hide();
                            var toFind = {
                                "title": request.term,
                                "lang": $("#navigate_products_copy_from_language_selector").val(),
                                nd: new Date().getTime()
                            };

                            $.ajax(
                                {
                                    url: NAVIGATE_APP + "?fid=products&act=search_by_title",
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
                        select: function navigate_products_copy_from_title_callback(event, ui)
                        {
                            $("#navigate_products_copy_from_product_id").val(ui.item.id);
                            $.ajax({
                                url: NAVIGATE_APP + "?fid=products&act=copy_from_template_zones",
                                dataType: "json",
                                method: "GET",
                                data: {id: ui.item.id},
                                success: function( data )
                                {
                                    $("#navigate_products_copy_from_section").empty();

                                    $.each(data, function(row)
                                    {
                                        $("#navigate_products_copy_from_section").append(
                                            $("<option data-type='"+data[row].type+"'></option>").val(data[row].id).html(data[row].title)
                                        );
                                    });

                                    $("#navigate_products_copy_from_section").parent().show();
                                }
                            });
                        }
                    });

                $(acp).trigger("autocompleteselect");
            }
        }
    );
}

// script#6		
// gallery

$("#products-gallery-elements").on("dblclick", ".navigate-droppable", function()
{
	var id = $(this).attr("id");
	id = id.replace("products-gallery-item-", "");
	id = id.replace("-droppable", "");

	if(!id || id=="" || id=="empty") return;

	$("#navigate_products_gallery_captions_form_image-droppable img")
		.attr("src", NAVIGATE_DOWNLOAD + "?wid=" + $(this).attr("website_id") + "&id=" + id + "&disposition=inline&width=75&height=75")
		.attr("data-src-original", NAVIGATE_DOWNLOAD + "?wid=" + $(this).attr("website_id") + "&id=" + id + "&disposition=inline")
		.attr("vspace", 0);

	for(lang in active_languages)
	{
		$("#navigate_products_gallery_captions_form_image_" + active_languages[lang])
			.val($("#products-gallery-item-" + id + "-dictionary-" + active_languages[lang]).val());
	}

	$("#navigate_products_gallery_captions_form").dialog(
	{
		title: "<img src=\"img/icons/silk/image_edit.png\" align=\"absmiddle\"> " + navigate_t(77, "Properties"),
		modal: true,
		buttons:
		[
			{
				text: navigate_t(58,'Cancel'),
				click: function()
				{
					$(this).dialog("close");
					$(window).trigger("resize");
				}
			},
			{
				text: navigate_t(190, 'Ok'),
				click: function()
				{
					for(lang in active_languages)
					{
						var image_caption_id = "products-gallery-item-" + id + "-dictionary-" + active_languages[lang];

						$("#"+image_caption_id)
							.val($("#navigate_products_gallery_captions_form_image_" + active_languages[lang]).val());
					}
					$(this).dialog("close");
					$(window).trigger("resize");
				}
			}
		],
		width: 650,
		height: 300
	});
});

$("#products-gallery-elements").on("contextmenu", "li:not(.gallery-item-empty-droppable)", function(e)
	{
		navigate_hide_context_menus();
		var trigger = $(this);

		setTimeout(function()
		{
			$('#contextmenu-gallery-items').data("file-id", $(trigger).find("div:first").data("file-id"));

			$('#contextmenu-gallery-items').menu();

			var xpos = e.clientX;
			var ypos = e.clientY;

			if(xpos + $('#contextmenu-gallery-items').width() > $(window).width())
				xpos -= $('#contextmenu-gallery-items').width();

			$('#contextmenu-gallery-items').css({
				"top": ypos,
				"left": xpos,
				"z-index": 100000,
				"position": "absolute"
			});
			$('#contextmenu-gallery-items').addClass('navi-ui-widget-shadow');

			$('#contextmenu-gallery-items').show();

			$("#contextmenu-gallery-items-properties").off("click").on("click", function()
			{
				$(trigger).find('div:first').trigger("dblclick");
			});

			$("#contextmenu-gallery-items-permissions").off("click").on("click", function()
			{
				navigate_contextmenu_permissions_dialog($('#contextmenu-gallery-items').data("file-id"), trigger);
			});

			$("#contextmenu-gallery-items-description").off("click").on("click", function()
			{
				navigate_contextmenu_description_dialog($('#contextmenu-gallery-items').data("file-id"), trigger);
			});

			$("#contextmenu-gallery-items-focalpoint").off('click').on("click", function ()
			{
				navigate_media_browser_focalpoint($('#contextmenu-gallery-items').data("file-id"));
			});

			$("#contextmenu-gallery-items-remove").off('click').on("click", function ()
			{
				$(trigger).find('.navigate-droppable-cancel').trigger("click");
			});

			$("#contextmenu-gallery-items-move-beginning").off('click').on("click", function ()
			{
				$("#products-gallery-elements").prepend(trigger);
				$("#products-gallery-elements").sortable( "refreshPositions" );
				navigate_products_gallery_parse();
			});

			$("#contextmenu-gallery-items-move-end").off('click').on("click", function ()
			{
				$(trigger).insertBefore($(trigger).parent().find("li:last"));
				$("#products-gallery-elements").sortable( "refreshPositions" );
				navigate_products_gallery_parse();
			});

		}, 250);

		return false;
	}
);

function navigate_products_gallery_change_captions(image_id, last_image_id)
{		
	if(last_image_id!=null)
	{
		for(lang in active_languages)
		{
			$("#products-gallery-item-"+last_image_id+"-dictionary-"+active_languages[lang]).remove();
		}
	}
	
	if(image_id!=null)
	{			
		for(lang in active_languages)
		{				
			$("#products-gallery-elements")
				.after('<input type="hidden" value="" ' +
						   'name="products-gallery-item-'+image_id+'-dictionary-'+active_languages[lang]+'" ' +
						   '  id="products-gallery-item-'+image_id+'-dictionary-'+active_languages[lang]+'" />');
		}
	}
}

function navigate_products_gallery_parse()
{
	$("#products-gallery-elements-order").val("");
	$("#products-gallery-elements").children().each(function()
	{
		var id = $(this).children("div:first").attr("id");
		if(id)
		{
			id = id.replace("products-gallery-item-", "");
			id = id.replace("-droppable", "");				
		
			if(parseInt(id) > 0) 
				$("#products-gallery-elements-order").val($("#products-gallery-elements-order").val() + "#" + id);
		}
	});
	$("#products-gallery-elements-order").val($("#products-gallery-elements-order").val().substr(1));
}

$("#products-gallery-elements").sortable({
	containment: "#navigate-content-tabs",
	scroll: true,
	helper: "clone",
	opacity: 0.7,
	iframeFix: true,
	items: "> li:not(.gallery-item-empty-droppable)",
	update: navigate_products_gallery_parse,
	start: function(event, ui)
	{
		$(ui.helper).find('div:first').addClass("navigate_media_browser_clone");
	}
});

$("#products-gallery-elements div.navigate-droppable").droppable(
{
	accept: ".draggable-image",
	classes: {
		"ui-droppable-hover": "navigate-droppable-hover"
	},
	drop: function(event, ui) 
	{
		navigate_products_gallery_drop(this, event, ui);
	}
});

function navigate_products_gallery_drop(el, event, ui)
{	
	var file_id = $(ui.draggable).attr("id").substring(5);
	
	if($(el).attr("id")=="products-gallery-item-empty-droppable")
	{
		// create a new node
		var new_node;
		new_node = '<li>' +
				   '<div id="products-gallery-item-' + file_id + '-droppable" website_id="' + navigate_media_browser_website + '" data-file-id="' + file_id + '" class="navigate-droppable ui-corner-all">' +
				   $(ui.draggable).html() +
				   '</div> ' +
				   '<div class="navigate-droppable-cancel"><img src="img/icons/silk/cancel.png" /></div> ' +
				   '</li>';

		$(el).parent().before($(new_node));
		$(el).parent().prev().find("div.navigate-droppable").droppable({
			accept: ".draggable-image", 
			classes: {
				"ui-droppable-hover": "navigate-droppable-hover"
			},
			drop: function(event, ui) 
			{				
				navigate_products_gallery_drop(this, event, ui);
			}
		});
        $('#products-gallery-elements').find('.navigate-droppable-cancel').show();
		navigate_products_gallery_change_captions(file_id);
	}
	else
	{
        // reorder nodes
		var last_id = $(el).attr("id");
		last_id = last_id.replace("products-gallery-item-", "");
		last_id = last_id.replace("-droppable", "");
		navigate_products_gallery_change_captions(file_id, last_id);	
					
		$(el).attr("id", "products-gallery-item-" + file_id + "-droppable");
		$(el).attr("name", "products-gallery-item-" + file_id + "-droppable");
		$(el).html($(ui.draggable).html());
	}
	navigate_products_gallery_parse();
}

$("#products-gallery-elements").on("click", ".navigate-droppable-cancel", function()
{
	var id = $(this).prev().attr("id");
	id = id.replace("products-gallery-item-", "");
	id = id.replace("-droppable", "");
	navigate_products_gallery_change_captions(null, id);
	$(this).parent().remove();
	navigate_products_gallery_parse();
});

$("#products-gallery-elements").on("mouseenter", ".navigate-droppable", function()
{
	if($(this).attr('id')!="products-gallery-item-empty-droppable")
		navigate_status(navigate_t(286, "Drag to reorder. Double click a item to set a caption."), "ready");
});

$("#products-gallery-elements").on("mouseleave", ".navigate-droppable", function()
{
	navigate_status(navigate_t(42, "Ready"), "ready");
});


// script#7
// comments moderator autocomplete
$("#item-comments_moderator").select2(
{
    ajax: {
        url: NAVIGATE_APP + "?fid=" + navigate_query_parameter('fid') + "&act=json_find_user",
        dataType: "json",
        delay: 100,
        data: function (params)
        {
            return {
                username: params.term,
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
	placeholder: $("#item-comments_moderator-helper").text(), // text when no value selected
    minimumInputLength: 1,
    templateSelection: function(row)
	{
		if(row.id)
			return row.text + " <helper style='opacity: .5;'>#" + row.id + "</helper>";
		else
			return row.text;
	},
	escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
    allowClear: true
});



// script #8
// comments moderation

$("#object-comments-toolbar-publish").on("click", function()
{
    var oca = object_comment_active;
	$.ajax(
	{
		url: NAVIGATE_APP + "?fid=products&act=change_comment_status&id=" + oca + "&opt=publish",
		dataType: "text",
		method: "GET",
		success: function( data ) 
		{
			if(data=="true")	
				$("#object-comment-" + oca).attr("class", "object-comment-message object-comment-status-public");
		}
	});					
});

$("#object-comments-toolbar-unpublish").on("click", function()
{
    var oca = object_comment_active;
	$.ajax(
	{
		url: NAVIGATE_APP + "?fid=products&act=change_comment_status&id=" + oca + "&opt=unpublish",
		dataType: "text",
		method: "GET",
		success: function( data ) 
		{
			if(data=="true")	
				$("#object-comment-" + oca).attr("class", "object-comment-message object-comment-status-private");
		}
	});					
});			

$("#object-comments-toolbar-delete").on("click", function()
{
	var oca = object_comment_active;
	navigate_confirmation_dialog(
		function()
		{
			$.ajax(
				{
					url: NAVIGATE_APP + "?fid=products&act=change_comment_status&id=" + oca + "&opt=delete",
					dataType: "text",
					method: "GET",
					success: function( data )
					{
						if(data=="true")
						{
							$("#object-comment-" + oca).parent().remove();
							$("#object-comments-toolbar").hide();
						}
					}
				}
			);
		}
	);
});	

$("#object-comments-toolbar").parent().on("scroll", function()	 { $("#object-comments-toolbar").hide(); });
$(".object-comment-message").on("mousemove", function() { navigate_products_comment_toolbar(this); });

var object_comment_active = 0;
function navigate_products_comment_toolbar(el)
{
	object_comment_active = $(el).attr("id").substr("object-comment-".length);
	if($(el).position().top > 20)
		$("#object-comments-toolbar").css($(el).offset()).show();
	else
		$("#object-comments-toolbar").hide();
		
	$("#object-comments-toolbar-publish").show();
	$("#object-comments-toolbar-unpublish").show();
	$("#object-comments-toolbar-delete").show();
	
	if($(el).hasClass("object-comment-status-private"))
		$("#object-comments-toolbar-unpublish").hide();

	if($(el).hasClass("object-comment-status-hidden"))
		$("#object-comments-toolbar-unpublish").hide();

	if($(el).hasClass("object-comment-status-public"))
		$("#object-comments-toolbar-publish").hide();
}


// script#9 (statistics)
if($("#navigate-panel-web-votes-graph").length > 0)
{
	var votes_graph_data = $.parseJSON($("#navigate-panel-web-data-score").text());
	$.plot(
		$("#navigate-panel-web-score-graph"),
		votes_graph_data,
		{
			series:
			{
				pie:
				{
					show: true,
					radius: 1,
					tilt: 0.5,
					startAngle: 3/4,
					label:
					{
						show: true,
						formatter: function(label, series)
						{
							return '<div style="font-size:12px;text-align:center;padding:2px;color:#fff;"><span style="font-size: 20px; font-weight: bold; ">'+label+'</span><br/>'+Math.round(series.percent)+'% ('+series.data[0][1]+')</div>';
						},
						background: { opacity: 0.6 }
					},
					stroke:
					{
						color: "#F2F5F7",
						width: 4
					}
				}
			},
			legend:
			{
				show: false
			}
		}
	);

	var votes_graph_data_points = $("#navigate-panel-web-data-votes_by_date").text();
	votes_graph_data_points = $.parseJSON(votes_graph_data_points);

	var plot = $.plot(
		$("#navigate-panel-web-votes-graph"),
		[votes_graph_data_points],
		{
			points:
			{
				show: true
			},
			yaxis:
			{
				tickDecimals: 0
			},
			xaxis:
			{
				mode: "time"
			},
			grid:
			{
				markings: function (axes)
				{
					// mark weekends in the grid
					var markings = [];
					var d = new Date(axes.xaxis.min);
					// go to the first Saturday
					d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 1) % 7))
					d.setUTCSeconds(0);
					d.setUTCMinutes(0);
					d.setUTCHours(0);
					var i = d.getTime();
					do {
						// when we don't set yaxis, the rectangle automatically
						// extends to infinity upwards and downwards
						markings.push({ xaxis: { from: i, to: i + 2 * 24 * 60 * 60 * 1000 } });
						i += 7 * 24 * 60 * 60 * 1000;
					} while (i < axes.xaxis.max);

					return markings;
				},
				markingsColor: "#e7f5fc"
			},
			zoom:
			{
				interactive: true
			},
			pan:
			{
				interactive: true
			}
		}
	);

	$('.navigate-panel').css({
		"visibility": "visible",
		"float": "left",
		"margin-right": "8px"
	});
}


function navigate_products_tabform_submit(formNum)
{
	// remove beforeunload warning, if any
	navigate_beforeunload_unregister();

	var tab = parseInt($('#navigate-content-tabs').children('div:visible').attr('id').replace("navigate-content-tabs-", "")) - 1;
	if(tab < 0) tab = 0;

	var url = $('#navigate-content').find('form').eq(formNum).attr('action');
	$('#navigate-content').find('form').eq(formNum).attr(
		'action',
		$.query.load(url).set('tab', tab).set('tab_language', navigate_products_language_selected).toString()
	);

	$('#navigate-content').find('form').eq(formNum).submit();
}


function navigate_products_tags_ranking(language, element)
{
	var title = $(element).html() + " (" + language + ")";
	var dialog_id = "tags-ranking-" + language;

	// if try to reopen dialog, remove the old one and create a new instance
	if($("#" + dialog_id).length > 0)
		$("#" + dialog_id).remove();

	$.getJSON(
		NAVIGATE_APP + '?fid=products&act=json_tags_ranking&lang=' + language,
		function(rs)
		{
			var dialog = $('<div id="'+dialog_id+'" class="tags-ranking-dialog" />')
				.dialog(
				{
					title: title,
					classes: { "ui-dialog": "navi-ui-widget-shadow" },
					width: 300,
					height: 220,
					open: function(event, ui)
					{
						$("#" + dialog_id).on("click", "span", function()
						{
							$("#tags-" + language).tagit("createTag", $(this).text());
							$(this).addClass('active');
						});

						$("#tags-" + language).on("change", function()
						{
							if($("#" + dialog_id).length > 0) // dialog still exists?
							{
								$("#" + dialog_id).find("span").each(function()
								{
									if($('.tagit-label:visible:contains("'+$(this).text()+'")').length > 0)
										$(this).addClass('active');
									else
										$(this).removeClass('active');
								});
							}
						});

						setTimeout(function() { $("#tags-" + language).trigger("change"); }, 300);
					},
					close: function( event, ui )
					{
						$("#" + dialog_id).off("click", "span");
						setTimeout(function() { $("#" + dialog_id).remove(); }, 300);
					}
				}
			);

			for(i in rs)
				dialog.append('<span>' + rs[i] + '</span>');
		}
	);
}

$("#div_category_order button")
	.button({icon: "ui-icon-arrowthick-2-n-s"})
	.on("click", function(e)
	{
		e.stopPropagation();
		e.preventDefault();
		navigate_status(navigate_t(6, "Loading") + "...", "loader");

		$("#products_order_window").load("?fid=products&act=products_order&category=" + $("#category").val() + "&_bogus=" + new Date().getTime(), function()
		{
			navigate_status(navigate_t(42, "Ready"), "ready");
			$("#products_order_window").dialog({
				modal: true,
				title: navigate_t(171, 'Order'),
				width: 600,
				height: 500,
				buttons:
				[
                    {
                        text: navigate_t(58, 'Cancel'),
                        click: function () {
                            $(this).dialog("destroy");
                        }
                    },
					{
						text: navigate_t(190, 'Ok'),
						click: function()
						{
							var dialog = this;
							// save
							$.post(
								"?fid=products&act=products_order&category=" + $("#category").val() + "&_bogus=" + new Date().getTime(),
								{
									"products-order": $("#products-order").val()
								},
								function(response)
								{
									if(response=="true")
									{
										$(dialog).dialog("destroy");
									}
									else
									{
										$("<div>"+response+"</div>").dialog({
											modal: true,
											title: navigate_t(56, "Unexpected error")
										});
									}
								}
							);
						}
					}
				]
			});
		});
	}
);


// help messages
$("#order_info").qtip(
{
	content:    "<div>" + $("#order_info").data("message") + "</div>",
	show:       {   event: "mouseover"  },
	hide:       {   event: "mouseout"   },
	style:      {   tip: true,  width: 300, classes: "qtip-cream"   },
	position:   {   at: "top right",    my: "bottom left"   }
});

$("#status_info").qtip(
{
	content:    "<div>" + $("#status_info").data("message") + "</div>",
	show:       {   event: "mouseover"  },
	hide:       {   event: "mouseout"   },
	style:      {   tip: true,  width: 300, classes: "qtip-cream"   },
	position:   {   at: "top right",    my: "bottom left"   }
});

$("#template_info").qtip(
{
	content:    "<div>" + $("#template_info").data("message") + "</div>",
	show:       {   event: "mouseover"  },
	hide:       {   event: "mouseout"   },
	style:      {   tip: true,  width: 300, classes: "qtip-cream"   },
	position:   {   at: "top right",    my: "bottom left"   }
});


// help message 1: suggest "Save" after changing the template
$("#template").data("original-value", $("#template").val());
$("#template").on("change blur", function()
{
	// reset icon
	var template_info_displayed = ($("#template_info").css('display')!="none");
	$("#template_info").css('display', 'none');

	// template changed since last load?
	if(	$("#template").val() != $("#template").data("original-value"))
	{
		$("#template_info").css('display', 'inline-block');
		if(!template_info_displayed)
			$("#template_info").effect("pulsate", "slow");
	}
});

// help message 2: after setting a future publishing date, alert the user if this element is "Private"
$("#date_published,#permission").on("change blur", function()
{
	// reset icon
	var status_info_displayed = ($("#status_info").css('display')!="none");
	$("#status_info").css('display', 'none');

	// date set to future?
	if(	$("#date_published").datetimepicker("getDate") &&
		$("#date_published").datetimepicker("getDate").getTime() > (new Date().getTime())
	)
	{
		// private or hidden?
		if($("#permission").val() > 0)
		{
			$("#status_info").css('display', 'inline-block');
			if(!status_info_displayed)
				$("#status_info").effect("pulsate", "slow");
		}
	}
});


for(al in active_languages)
{
    navigate_products_path_check($("#path-" + active_languages[al]));

    $("#path-" + active_languages[al]).on("focus", function()
    {
        if($(this).val() == "")
        	navigate_products_path_generate($(this));
    });
}


$("#product-track_inventory").on("click", function()
{
	if($(this).is(":checked"))
        $("#product-stock_available").parent().show();
	else
        $("#product-stock_available").parent().hide();
});


$("#product-tax_class").on("change", function()
{
	if($(this).val() == "custom")
        $("#product-tax_value").css('visibility', 'visible');
	else
        $("#product-tax_value").css('visibility', 'hidden');
});

$("#product-offer").on("change", function()
{
    $("#product-offer_price").parent().hide();
    $("#product-offer_begin_date").parent().hide();
    $("#product-offer_end_date").parent().hide();

    if($(this).is(":checked"))
    {
        $("#product-offer_price").parent().show();
        $("#product-offer_begin_date").parent().show();
        $("#product-offer_end_date").parent().show();
    }
});

$("#product-base_price,#product-base_price_currency,#product-tax_class,#product-tax_value,#product-offer_price,#product-offer").on("change keyup", function()
{
	var price = $("#product-base_price").inputmask('unmaskedvalue');

    if($("#product-offer").is(":checked"))
	{
		price = $("#product-offer_price").inputmask('unmaskedvalue');
	}

	if($("#product-tax_class").val() == "custom")
	{
		price = price + ( (price / 100) * $("#product-tax_value").inputmask('unmaskedvalue'));
	}

	$("#product-selling_price").val(navigate_decimal_to_string(price));
	$("#product-selling_price-text").html($("#product-selling_price").val());
});
