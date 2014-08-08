<?php
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block_group.class.php');

function nvweb_block_group($vars=array())
{
	global $website;
	global $DB;
	global $current;
    global $webgets;
    global $webuser;

    $webget = 'block_group';

	$out = array();

    $bg = new block_group();

    if(!empty($vars['id']))
        $bg->load($vars['id']);
    else if(!empty($vars['code']))
        $bg->load_by_code($vars['code']);

    if(!empty($bg->id) && !empty($bg->blocks))
    {
        // start rendering every block in the group
        foreach($bg->blocks as $bgb)
        {
            // can be a numeric ID or a string representing the block type
            if(is_numeric($bgb))
                $out[] = nvweb_blocks(array('mode' => 'single', 'id' => $bgb));
            else
                $out[] = nvweb_blocks(array('type' => $bgb));
        }
    }

	return implode("\n", $out);
}

?>