var navigate_permissions_changes = {};

$(window).on("resize focus", function()
{
	$("#navigate-permissions-websites-tabs").height(0);
	$("#navigate-permissions-websites-tabs").height(
			$("#navigate-permissions-websites-tabs").parent().height()
	);

	$("#navigate-permissions-websites-tabs > div").height(0);
	$("#navigate-permissions-websites-tabs > div").height(
		$("#navigate-permissions-websites-tabs").height() - 46
	);

	$('[id^="permissions_list_website_"]')
	    .setGridHeight($("#navigate-permissions-websites-tabs > div:first").height(), true);

	navigate_window_resize();
});

$(window).on("load", function() { $(window).trigger("resize"); });

function navigate_permissions_list_callback(list)
{
	var ws = $(list).data("website");

	$(list).find(".buttonset").each(function(i, el)
	{
		$(el).buttonset();
		$(el).css("white-space", "normal");
		$(el).children(".ui-button").css({"float": "left", "height": "24px", "margin": "2px 0px 2px"});
	});

	$(list).find("select").each(function(i, el)
	{
		$(el).css("width", "100%");
		navigate_selector_upgrade(el);
	});

	// tooltips
	$(list).find("td[aria-describedby=" + $(list).attr("id") + "_name]").each(function()
	{
		$(this).qtip({
			content: $(this).find('div:first').data("description"),
			position:
			{
				target: "mouse",
				my: "left bottom"
			},
			show:
			{
				delay: 500
			}
		});
	});

	$(list).find("button").button();

	$(list).find("button[data-action=structure]").on("click", function(event)
	{
		event.stopPropagation();
		event.preventDefault();

		var permission_name = $(this).parent().parent().attr("id"); // tr ID

		// set the new permission name in the hidden field
		$("#navigate_permissions_structure_selector_website_" + ws)
			.find("input[type=hidden]")
			.data("permission-name", permission_name);

		// reset active categories
		$("#navigate_permissions_structure_selector_website_" + ws).find('span.active').removeClass("active");

		// set active categories...
		var categories = "";

		if(typeof(navigate_permissions_changes['wid' + ws + '.' + permission_name])=="undefined")
			categories = $(this).data("value"); // example: [1,3,5]	// ...from last save
		else
			categories = navigate_permissions_changes['wid' + ws + '.' + permission_name];	// ...from the current modified values

		if(typeof(categories) != "undefined")
		{
			if(typeof(categories) == "number")
				categories = [categories];

			for(c in categories)
			{
				if(categories[c])
					$("#navigate_permissions_structure_selector_website_" + ws)
							.find('li[value='+categories[c]+'] span.text')
							.click();
			}
		}

		$("#navigate_permissions_structure_selector_website_" + ws).dialog({
			title: $("#navigate_permissions_structure_selector_website_" + ws).data("title"),
			modal: true,
			width: 700,
			height: 500,
			buttons: [
				{
					text: navigate_t(92, "Close"),
					click: function()
					{
						$( this ).dialog( "close" );
					}
				}
			]
		});
	});

	navigate_window_resize();
}

$('[id^="permissions_list_website_"]').on("click", "td[aria-describedby=permissions_list_value]", function()
{
	if($(this).find("input[type=text]").length > 0)
	{
		if(!$(this).find("input:first").is(":focus"))
			$(this).find("input:first").focus();
	}
});


function navigate_permission_change_boolean(el)
{
	var code = $(el).attr("for");
	// code is a string like this: navigatecms.privacy_mode_true
	var value = code.substr(code.lastIndexOf("_") + 1);
	code = code.substr(0, code.lastIndexOf("_"));

	navigate_permissions_changes[code] = value;
	navigate_permissions_update();
}

function navigate_permission_change_text(el)
{
	var code = $(el).attr("name");
	var value = $(el).val();

	navigate_permissions_changes[code] = value;
	navigate_permissions_update();
}

function navigate_permission_change_option(el)
{
	var code = $(el).attr("name");
	var value = $(el).val();

	navigate_permissions_changes[code] = value;
	navigate_permissions_update();
}

function navigate_permissions_update()
{
	var changes = phpjs_json_encode(navigate_permissions_changes);
	$("#navigate_permissions_changes").val(changes);
}
