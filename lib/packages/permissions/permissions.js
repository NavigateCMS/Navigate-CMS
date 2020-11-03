var navigate_permissions_changes = {};

$(window).on("resize focus", function()
{
	$("#navigate-permissions-websites-tabs").height(0);
	$("#navigate-permissions-websites-tabs").height(
		$("#navigate-permissions-websites-tabs").parent().height()
	);

	$("#navigate-permissions-websites-tabs > div").height(0);
	$("#navigate-permissions-websites-tabs > div").height(
		$("#navigate-permissions-websites-tabs").height() - 56
	);

	$('[id^="permissions_list_website_"]')
	    .setGridHeight($("#navigate-permissions-websites-tabs > div:first").height(), true);

	navigate_window_resize();
});

// dirty, but the only way we found it working :(
$("li[role=tab]").on("click", function() { $(window).trigger("resize");  });
$(window).load(function()
{
	setTimeout(function() { $(window).trigger("resize");  }, 300);
});


function navigate_permissions_list_callback(list)
{
	var ws = $(list).data("website");

	$(list).find(".buttonset").each(function(i, el)
	{
		$(el).controlgroup();
		$(el).css("white-space", "normal");
		$(el).children(".ui-button").css({"float": "left", "height": "24px", "margin": "2px 0px 2px"});
	});

	$(list).find("select").each(function(i, el)
	{
		$(el).css("width", "100%");
		if(!$(el).hasClass("select2"))
		{
			navigate_selector_upgrade(el);
		}
	});

	// tooltips
	$(list).find("td > div[data-description]").each(function()
	{
		$(this).parent().qtip({
			content: $(this).data("description"),
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
		$("#navigate_permissions_structure_selector_website_" + ws + " .tree_ul")
			.jstree("deselect_all", true); // deselect but don't trigger the "changed" event

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
				{
					var li = $("#navigate_permissions_structure_selector_website_" + ws + " .tree_ul").find("li[data-node-id="+categories[c]+"]");
					if(li && li[0])
						$("#navigate_permissions_structure_selector_website_" + ws + " .tree_ul").jstree("select_node", "#" + li[0].id);
				}
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

	// table search
	/*
	if($("#jqgh_permissions_list_website_" + ws + "_name button").length < 1)
	{
		$("#jqgh_permissions_list_website_" + ws + "_name").prepend("<button><input type='text' value='' style='display: none;' /><i class=\"fa fa-search\"></i></button>");
		$("#jqgh_permissions_list_website_" + ws + "_name button")
			.button()
			.css(
			{
				"float": "right",
				"margin-top": "0px",
				"padding": "0px"
			})
			.on("click", function(e)
			{
				e.preventDefault();
				e.stopPropagation();
				$(this).find("input").show().focus();
				$(this).find("i").hide();
			});

			$("#jqgh_permissions_list_website_" + ws + "_name input")
				.css({
					background: "transparent",
					border: "none"
				})
				.on("keyup", function()
				{
					var text = $(this).val();
					$("#permissions_list_website_" + ws).find("tr").not(":first").each(function()
					{
						if($(this).find("span:contains(" + text + ")").length > 0)
							$(this).show();
						else
							$(this).hide();
					});
				})
				.on("blur", function()
				{
					if($(this).val()=="")
					{
						$(this).hide();
						$(this).next().show();
					}
				});

		$("#jqgh_permissions_list_website_" + ws + "_name span.ui-button-text").css({"padding-top": "0", "padding-bottom": "0"});
	}
	*/
}

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
