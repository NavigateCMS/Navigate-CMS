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
				<div class="category_tree">
				    <img src="img/icons/silk/world.png" align="absmiddle" /> '.$ws_name.
                    '<div class="tree_ul">'.$hierarchy_html.'</div>'.
                '</div>
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
		$("#navigate_permissions_structure_selector_website_'.$ws_id.' .tree_ul").jstree({
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
            $("#navigate_permissions_structure_selector_website_'.$ws_id.' .tree_ul").jstree("open_node", "#" + li[0].id);
        
            var children_nodes = new Array();
            children_nodes.push(li);
            $(li).find("li").each(function() {
                children_nodes.push("#" + $(this)[0].id);
            });
        
            $("#navigate_permissions_structure_selector_website_'.$ws_id.' .tree_ul").jstree("select_node", children_nodes);
        
            return false;
        })
        .on("changed.jstree", function(e, data)
        {
            var i, j, r = [];
            var categories = new Array();
            $("#navigate_permissions_structure_selector_website_'.$ws_id.'_categories").val("");       
        
            for(i = 0, j = data.selected.length; i < j; i++)
            {
                var id = data.instance.get_node(data.selected[i]).data.nodeId;
                categories.push(id);
            }
            
            if(categories.length > 0)
                $("#navigate_permissions_structure_selector_website_'.$ws_id.'_categories").val(categories);                                

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
        });
	');

	return $out;
}
?>