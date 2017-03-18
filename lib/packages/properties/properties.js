var navigate_properties_copy_from_dialog_caller = null;

function navigate_properties_copy_from_dialog(trigger)
{
	navigate_properties_copy_from_dialog_caller = $(trigger).parent().find('textarea:first');

	$("#navigate-properties-copy-from-dialog").dialog(
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
						navigate_properties_copy_from_dialog_process();
						$(this).dialog("close");
						$(window).trigger("resize");
					}
				}
			],
			width: 660,
			height: 240,
			open: function()
			{
				// destroy and create the autocomplete widgets each time the dialog is shown
				if($("navigate_properties_copy_from_item_title").hasClass(".ui-autocomplete-input"))
					$("#navigate_properties_copy_from_item_title").autocomplete("destroy");

				if($("navigate_properties_copy_from_structure_title").hasClass(".ui-autocomplete-input"))
					$("#navigate_properties_copy_from_structure_title").autocomplete("destroy");

				var ac = $("#navigate_properties_copy_from_item_title").autocomplete(
					{
						source: function(request, response)
						{
							$("#navigate_properties_copy_from_section").parent().hide();
							var toFind = {
								"title": request.term,
								"lang": $("#navigate_properties_copy_from_language_selector").val(),
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
								}
							);
						},
						minLength: 1,
						select: navigate_items_copy_from_item_title_callback
					});

				function navigate_items_copy_from_item_title_callback(event, ui)
				{
					$("#navigate_properties_copy_from_item_id").val(ui.item.id);
					$.ajax({
						url: NAVIGATE_APP + "?fid=items&act=copy_from_template_zones",
						dataType: "json",
						method: "GET",
						data: {id: ui.item.id},
						success: function( data )
						{
							$("#navigate_properties_copy_from_section").empty();
							$("#navigate_properties_copy_from_section").select2();

							$.each(data, function(row)
							{
								$("#navigate_properties_copy_from_section").append(
									$("<option data-type='"+data[row].type+"' title='"+data[row].title+"'></option>")
											.val(data[row].code)
											.html(data[row].title)
								);
							});

							// force refresh
							$("#navigate_properties_copy_from_section").select2();
							$("#navigate_properties_copy_from_section").val(data[0].code);

							$("#navigate_properties_copy_from_section").parent().show();
						}
					});
				}

				$(ac).trigger("autocompleteselect");

				$('#navigate_properties_copy_from_item_reload').on("click", function(e)
				{
					e.stopPropagation();
					e.preventDefault();
					navigate_items_copy_from_item_title_callback(
						null,
						{
							item: {id: $("#navigate_properties_copy_from_item_id").val()
							}
						}
					);
				});


				// structure property search

				var acs = $("#navigate_properties_copy_from_structure_title").autocomplete({
					source: function(request, response)
					{
						$("#navigate_properties_copy_from_section").parent().hide();
						var toFind = {
							"title": request.term,
							"lang": $("#navigate_properties_copy_from_language_selector").val(),
							nd: new Date().getTime()
						};

						$.ajax(
							{
								url: NAVIGATE_APP + "?fid=structure&act=search_by_title",
								dataType: "json",
								method: "GET",
								data: toFind,
								success: function( data )
								{
									response( data );
								}
							}
						);
					},
					minLength: 1,
					select: navigate_items_copy_from_structure_title_callback
				});

				function navigate_items_copy_from_structure_title_callback(event, ui)
				{
					$("#navigate_properties_copy_from_structure_id").val(ui.item.id);
					$.ajax({
						url: NAVIGATE_APP + "?fid=structure&act=copy_from_template_zones",
						dataType: "json",
						method: "GET",
						data: {id: ui.item.id},
						success: function( data )
						{

							$("#navigate_properties_copy_from_section").empty();
							$("#navigate_properties_copy_from_section").select2();

							$.each(data, function(row)
							{
								$("#navigate_properties_copy_from_section").append(
									$("<option data-type='"+data[row].type+"' title='"+data[row].title+"'></option>")
											.val(data[row].code)
											.html(data[row].title)
								);
							});

							// force refresh
							$("#navigate_properties_copy_from_section").select2();
							$("#navigate_properties_copy_from_section").val(data[0].code);

							$("#navigate_properties_copy_from_section").parent().show();
						}
					});
				}

				
				$('#navigate_properties_copy_from_structure_reload').on("click", function(e)
				{
					e.stopPropagation();
					e.preventDefault();
					navigate_items_copy_from_structure_title_callback(
						null,
						{
							item: {id: $("#navigate_properties_copy_from_structure_id").val()}
						}
					);
				});
			}
		}
	);

	$("#navigate-properties-copy-from-dialog").parent().css('overflow', 'visible');
}

function navigate_properties_copy_from_change_origin(el)
{
	$("#navigate_properties_copy_from_language_selector").parent().hide();
	$("#navigate_properties_copy_from_structure_title").parent().hide();
	$("#navigate_properties_copy_from_item_title").parent().hide();
	$("#navigate_properties_copy_from_section").parent().hide();

	switch($("#"+$(el).attr("for")).val())
	{
		case "language":
			$("#navigate_properties_copy_from_language_selector").parent().show();
			$("#navigate_properties_copy_from_structure_title").parent().hide();
			$("#navigate_properties_copy_from_item_title").parent().hide();

			// force empty & refresh
			$("#navigate_properties_copy_from_section").empty();
			$("#navigate_properties_copy_from_section").select2();

			// find form elements
			var data = $('textarea[id]')
				.not($(navigate_properties_copy_from_dialog_caller))
				.not("#navigate_items_copy_from_history_stylesheets");

			$.each(data, function(row)
			{
				if(	!$(data[row]).parent().attr("lang") ||
					$(data[row]).parent().attr("lang") == $("#navigate_properties_copy_from_language_selector").val()
				)
				{
					var title = $(data[row]).parent().find("label:first")
						.contents().not($(data[row]).parent()
						.find("label:first").children()).text();

					$("#navigate_properties_copy_from_section").append(
						$("<option></option>").val($(data[row]).attr("id")).html(title)
					);
				}
			});

			$("#navigate_properties_copy_from_section").parent().show();

			// force refresh
			$("#navigate_properties_copy_from_section").select2();
			break;

		case "structure":
			$("#navigate_properties_copy_from_language_selector").parent().show();
			$("#navigate_properties_copy_from_structure_title").parent().show();
			break;

		case "item":
			$("#navigate_properties_copy_from_language_selector").parent().show();
			$("#navigate_properties_copy_from_item_title").parent().show();
			break;
	}
}

function navigate_properties_copy_from_change_language(el)
{
	if($("label[for=navigate_properties_copy_from_dialog_type_language]").hasClass("ui-state-active"))
	{
		// update "copy from language" tab
		navigate_properties_copy_from_change_origin($("label[for=navigate_properties_copy_from_dialog_type_language]"));
	}
	else
	{
		// do nothing
	}
}

function navigate_properties_copy_from_dialog_process()
{
	switch($("input[name=\'navigate_properties_copy_from_dialog_type[]\']:checked").val())
	{
		case "language":
			var lang = $("#navigate_properties_copy_from_language_selector").val();
			var section = $("#navigate_properties_copy_from_section").val();

			// refresh codemirror instances to update their hidden textarea
			$('.CodeMirror').each(function(i, el) { el.CodeMirror.save(); });

			navigate_properties_copy_from_set_content($("#" + section).val());
			break;

		case "structure":
			$.ajax(
			{
				url: NAVIGATE_APP + '?fid=structure&act=raw_zone_content',
				dataType: "html",
				method: "GET",
				data: {
					"lang": $("#navigate_properties_copy_from_language_selector").val(),
					"node_id": $("#navigate_properties_copy_from_structure_id").val(),
					"zone": $("#navigate_properties_copy_from_section option:selected").data("type"),
					"section": $("#navigate_properties_copy_from_section").val()
				},
				success: function( data )
				{
					navigate_properties_copy_from_set_content(data);
				}
			});
			break;

		case "item":
			$.ajax(
			{
				url: NAVIGATE_APP + '?fid=items&act=raw_zone_content',
				dataType: "html",
				method: "GET",
				data: {
					"lang": $("#navigate_properties_copy_from_language_selector").val(),
					"node_id": $("#navigate_properties_copy_from_item_id").val(),
					"zone": $("#navigate_properties_copy_from_section option:selected").data("type"),
					"section": $("#navigate_properties_copy_from_section").val()
				},
				success: function( data )
				{
					navigate_properties_copy_from_set_content(data);
				}
			});

			break;
	}
}

function navigate_properties_copy_from_set_content(data)
{
	// identify caller type: textarea, tinymce, source code...
	var id = $(navigate_properties_copy_from_dialog_caller).attr("id");
	var type = "textarea";

	// is a tinyMCE textarea?
	if(typeof(tinyMCE)!= "undefined")
	{
		$(tinyMCE.editors).each(function(t)
		{
			if(t.id == id)
				type = "tinymce";
		});
	}

	// is a code mirror textarea?
	if($(navigate_properties_copy_from_dialog_caller).parent().find(".CodeMirror").length > 0)
		type = "codemirror";

	if(type=='tinymce')
	{
		tinyMCE.get(id).setContent(data, { format: "raw" });
	}
	else if(type=='codemirror')
	{
		for(nci in navigate_codemirror_instances)
		{
			if($(navigate_codemirror_instances[nci].getTextArea()).attr("id") == id)
			{
				navigate_codemirror_instances[nci].setValue(data);
			}
		}
	}
	else
	{
		$(navigate_properties_copy_from_dialog_caller).val(data);
	}

}

function navigate_property_rating_field_round(field, score, keep)
{
	if($(field).val()=="")
		$(field).val(0);

	var score_decimal = parseFloat($(field).val());
	var integer_part = Math.floor($(field).val());

	if (integer_part == 0 && (score_decimal - integer_part) == 0)
	{
		$(field).val("");
	}
	else if (score_decimal - integer_part < 0.25)
	{
		$(field).val(integer_part);
	}
	else if (score_decimal - integer_part > 0.75)
	{
		$(field).val(integer_part + 1);
	}
	else
	{
		if($(field).next().data('half-stars'))
			$(field).val(integer_part + 0.5);
		else
			$(field).val(integer_part);
	}

	if(keep)
	{
		$(field).next().raty(
			'set',
			{
				score: $(field).val()
			}
		);
	}
}

$(".nv_property_rating_control").each(function()
{
	var field = $('#' + $(this).data("property"));
    var raty_control = this;

	/* TODO: display a floating qTip hint with the new value on mouse over; doesn't work as expected :(
	$(raty_control).qtip({
		content: $(field).val(),
		position: {
			target: 'mouse', // Track the mouse as the positioning target
			my: 'bottom left',
			at: 'top right',
			adjust: { x: 1, y: -8 } // Offset it slightly from under the mouse
		}
	});
	*/

	$(this).raty({
		score: $(field).val(),
		cancel: true,
		half: $(this).data('half-stars'),
		halfShow: $(this).data('half-stars'),
		cancelHint: null,
		number: $(this).data('stars'),
		space: false,
		hints: [ "", "", "", "", "", "", "", "", "", "" ],
		size: 16,
		precision: $(this).data('half-stars'),
		starHalf    : 'fa fa-star-half-o half',                            	// The name of the half star image.
		starOff     : 'fa fa-star-o off',                                 	// Name of the star image off.
		starOn      : 'fa fa-star',                                   		// Name of the star image on.
		mouseover: function(score, evt)
		{
			// round value to .0 or .5
			/*
			var score_rounded = (Math.round(score*2)/2).toFixed(1);

			if(score_rounded == 0 || !score)
			{
				$(raty_control).qtip('option', 'content.text', "");
				$(raty_control).qtip('hide');
			}
			else
			{
				$(raty_control).qtip('option', 'content.text', score_rounded);
				$(raty_control).qtip('show');
			}
			*/
		},
		mouseout: function(score, evt)
		{
			//$(raty_control).qtip('hide');
		},
		click: function(score, evt)
		{
			$(field).val(score);
			$(field).trigger("change");
			/*
			$(raty_control).qtip('option', 'content.text', "");
			$(raty_control).qtip('hide');
			*/
		}
	});


	$(field).on("change", function()
	{
		navigate_property_rating_field_round(this, $(this).val(), true);
	});

	$(field).trigger("change");
});
