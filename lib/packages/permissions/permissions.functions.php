<?php
function navigate_permissions_structure_selector($ws_id, $ws_name)
{
	global $layout;

	$hierarchy = structure::hierarchy(0, $ws_id);
	$hierarchy_html = structure::hierarchyList($hierarchy, null, null, true);

	$out = '
		<div id="navigate_permissions_structure_selector_website_'.$ws_id.'" data-title="'.t(16, "Structure").'" style="display: none;">
			<div class="navigate-form-row">
				<label>'.t(16, 'Structure').'</label>
				<div class="category_tree"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$ws_name.$hierarchy_html.'</div>
				<input type="hidden"
				       data-website="'.$ws_id.'"
				       data-permission-name=""
					   id="navigate_permissions_structure_selector_website_'.$ws_id.'_categories"
					   value=""
			    />
			</div>
		</div>
	';

	$layout->add_script('
		$("#navigate_permissions_structure_selector_website_'.$ws_id.' .category_tree ul:first").kvaTree(
		{
	        imgFolder: "js/kvatree/img/",
			dragdrop: false,
			background: "#f2f5f7",
			overrideEvents: true,
			onClick: function(event, node)
			{
				if($(node).find("span:first").hasClass("active"))
					$(node).find("span:first").removeClass("active");
				else
					$(node).find("span:first").addClass("active");

				var categories = new Array();

				$("#navigate_permissions_structure_selector_website_'.$ws_id.' .category_tree span.active").parent().each(function()
				{
					categories.push($(this).attr("value"));
				});

				if(categories.length > 0)
					$("#navigate_permissions_structure_selector_website_'.$ws_id.'_categories").val(categories);
				else
					$("#navigate_permissions_structure_selector_website_'.$ws_id.'_categories").val("");

				var permission_name = $("#navigate_permissions_structure_selector_website_'.$ws_id.'_categories").data("permission-name");
				if(permission_name)
				{
					// update changes into the variable which controls the values of the modified permissions
					navigate_permissions_changes["wid'.$ws_id.'." + permission_name] = categories;
					navigate_permissions_update();

					$("#permissions_list_website_'.$ws_id.'")
						.find("button[data-permission-name=\'" + permission_name + "\']")
						.attr("title", categories.length);
				}
			}
		});

		$("#navigate_permissions_structure_selector_website_'.$ws_id.' .category_tree li").find("span:first").css("cursor", "pointer");
	');

	return $out;
}
?>