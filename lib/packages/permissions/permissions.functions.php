<?php

function nvweb_permissions_rows($website_id, $object_type, $object_id)
{
    global $DB;

    $naviforms = new naviforms();

    $object = new stdClass();
    if($object_type == 'user')
    {
        $object = new user();
        $object->load($object_id);
    }
    else if($object_type == 'profile')
    {
        $object = new profile();
        $object->load($object_id);
    }

    $permissions_definitions = permission::get_definitions();

    $permissions_values = permission::get_values($object_type, $object, $permissions_definitions, $website_id);

    $permissions_definitions = array_merge(
        $permissions_definitions['system'],
        $permissions_definitions['functions'],
        $permissions_definitions['settings'],
        $permissions_definitions['extensions']
    );

    $out = array();

    $iRow = 0;

    for($i=0; $i < count($permissions_definitions); $i++)
    {
        // Extract and validate permission name to prevent empty key access
        $permission_name = value_or_default(array($permissions_definitions[$i], 'name'), '');
        if(empty($permission_name)) {
            continue; // Skip invalid permission definitions without names
        }

        $control = '';
        $type = '';
        $scope = t(470, 'System');

        $field_name = "wid".$website_id.".".$permission_name;
        
        // Get permission value safely using validated key
        $permission_value = isset($permissions_values[$permission_name]) ? $permissions_values[$permission_name] : '';

        if(value_or_default(array($permissions_definitions[$i], 'scope'), '')=='functions')
        {
            $scope = t(240, 'Functions');
        }
        else if(value_or_default(array($permissions_definitions[$i], 'scope'), '')=='settings')
        {
            $scope = t(459, 'Settings');
        }
        else if(value_or_default(array($permissions_definitions[$i], 'scope'), '')=='extensions')
        {
            $scope = t(327, 'Extensions');
        }

        switch(value_or_default(array($permissions_definitions[$i], 'type'), ''))
        {
            case 'boolean':
                $type = t(206, 'Boolean');
                $control = $naviforms->buttonset(
                    $field_name,
                    array(
                        'true' => '<span class="ui-icon ui-icon-circle-check"></span>',
                        'false' => '<span class="ui-icon ui-icon-circle-close"></span>'
                    ),
                    $permission_value,
                    "navigate_permission_change_boolean(this);"
                );
                break;

            case 'integer':
                $type = t(468, 'Integer');
                $control = $naviforms->textfield(
                    $field_name,
                    $permission_value,
                    '99%',
                    'navigate_permission_change_text(this);'
                );
                break;

            case 'option':
            case 'moption':

                $options = value_or_default(array($permissions_definitions[$i], 'options'), array());

                switch($options)
                {
                    case "websites":
                        $options = array();
                        $DB->query("SELECT id, name FROM nv_websites");
                        $websites = $DB->result();
                        foreach($websites as $ws)
                        {
                            $options[$ws->id] = $ws->name;
                        }
                        break;

                    case "extensions":
                        $options = array();
                        $extensions = extension::list_installed(null, true);
                        foreach($extensions as $ext)
                        {
                            $options[$ext['code']] = $ext['title'];
                        }
                        break;

                    case "structure":
                        $options = array();
                        $categories = $permission_value;
                        if(!is_array($categories))
                        {
                            $categories = array();
                        }
                        $categories = array_filter($categories);
                        $control = '<button data-permission-name="'.$permission_name.'" 
                                                    data-action="structure" data-value="'.json_encode($categories).'" 
                                                    title="'.count($categories).'"><i class="fa fa-sitemap fa-fw"></i> '.
                            t(611, "Choose").
                            '</button>';
                        break;

                    default:
                }

                $type = t(200, 'Options');
                if(empty($control))
                {
                    $control = $naviforms->selectfield(
                        $field_name,
                        array_keys($options),
                        array_values($options),
                        $permission_value,
                        'navigate_permission_change_option(this);',
                        (value_or_default(array($permissions_definitions[$i], 'type'), '')=='moption'), // multiple?
                        NULL,
                        NULL,
                        false,
                        false,
                        "",
                        ""
                    );
                }
                break;

            case 'color':
                $type = t(441, 'Color');
                $control = $naviforms->colorfield(
                    $field_name,
                    $permission_value,
                    array(),
                    'navigate_permission_change_text'
                );
                break;

            case 'string':
            default:
                $type = t(469, 'String');
                $control = $naviforms->textfield(
                    $field_name,
                    $permission_value,
                    '99%',
                    'navigate_permission_change_text(this);'
                );
                break;
        }

        // search filters
        if(!empty($_REQUEST['filters']))
        {
            $include = navitable::jqgridCheck(
                array(
                    'name' => $permission_name,
                    'scope' => $scope,
                    'type' => $type,
                    'value' => $permission_value
                ),
                $_REQUEST['filters']
            );

            if(!$include)
            {
                continue;
            }
        }

        $out[$iRow] = array(
            0	=> $permission_name,
            1	=> '<div data-description="'.value_or_default(array($permissions_definitions[$i], 'description'), '').'">'.
                '<span class="ui-icon ui-icon-float ui-icon-info"></span>&nbsp;'.
                '<span>'.$permission_name.'</span></div>',
            2	=> $scope,
            3   => $type,
            4   => $control
        );

        $iRow++;
    }

    return $out;
}

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