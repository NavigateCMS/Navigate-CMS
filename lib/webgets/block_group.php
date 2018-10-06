<?php
require_once(NAVIGATE_PATH.'/lib/packages/blocks/block_group.class.php');

// unused nvweb, may be removed in a future version;
// please use <nvlist source="block_group">

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

    if(!empty($vars['id']) && is_numeric($vars['id']))
    {
        $bg->load($vars['id']);
    }
    else if(!empty($vars['id']) && !is_numeric($vars['id']))
    {
        $bg->load_by_code($vars['id']);
    }
    else if(!empty($vars['code']))
    {
        $bg->load_by_code($vars['code']);
    }

    if(!empty($bg->id) && !empty($bg->blocks))
    {
        // start rendering every block in the group
        foreach($bg->blocks as $bgb)
        {
            // can be a numeric ID or a string representing the block type
            // note: block group blocks are not allowed, as navigate cms does not know the code to generate them
            if(is_numeric($bgb['id']))
            {
                $out[] = nvweb_blocks(array('mode' => 'single', 'id' => $bgb['id']));
            }
            else
            {
                $out[] = nvweb_blocks(array('type' => $bgb['id']));
            }
        }
    }

	return implode("\n", $out);
}

?>