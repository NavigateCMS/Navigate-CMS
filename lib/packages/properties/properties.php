<?php
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/templates/template.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.layout.php');
require_once(NAVIGATE_PATH.'/lib/packages/items/item.class.php');

function run()
{
	global $layout;
	global $DB;
	global $website;
	global $theme;

	$out = '';

	switch($_REQUEST['act'])
	{
		case "copy_from_template_zones":
			// return template sections and (textarea) properties for a content id
			$template = new template();
			$template->load($_REQUEST['template']);

			$zones = array();
            for($ts=0; $ts < count($template->sections); $ts++)
            {
	            $title = $theme->t($template->sections[$ts]['name']);
	            if($title == '#main#')
		            $title = t(238, 'Main content');
	            $zones[] = array(
		            'type' => 'section',
		            'code' => $template->sections[$ts]['code'],
		            'title' => $title
	            );
            }

			for($ps=0; $ps < count($template->properties); $ps++)
			{
				// ignore non-textual properties
				if(!in_array($template->properties[$ps]->type, array("text", "textarea", "rich_textarea")))
					continue;

				$zones[] = array(
		            'type' => 'property',
		            'code' => $template->properties[$ps]->id,
		            'title' => $theme->t($template->properties[$ps]->name)
	            );
			}

			echo json_encode($zones);

			core_terminate();
			break;
	}
}

?>