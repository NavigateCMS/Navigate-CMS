<?php
function run()
{
	$out = '';
	
	switch(@$_REQUEST['act'])
	{
		case 'grid_note_background':
            grid_notes::background($_REQUEST['object'], $_REQUEST['id'], $_REQUEST['background']);
            core_terminate();
            break;

        case 'grid_notes_comments':
            $comments = grid_notes::comments($_REQUEST['object'], $_REQUEST['id'], false);
            echo json_encode($comments);
            core_terminate();
            break;

        case 'grid_notes_add_comment':
            echo grid_notes::add_comment($_REQUEST['object'], $_REQUEST['id'], $_REQUEST['comment'], $_REQUEST['background']);
            core_terminate();
            break;

        case 'grid_note_remove':
            echo grid_notes::remove($_REQUEST['id']);
            core_terminate();
            break;

		default:
	}
	
	return $out;
}

?>