<?php
require_once(NAVIGATE_PATH.'/lib/external/force-utf8/Encoding.php');

function nvweb_comments($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $webgets;
	global $dictionary;
	global $webuser;
	global $theme;
    global $events;
    global $session;
	
	$webget = 'comments';

	if(!isset($webgets[$webget]))
	{
		$webgets[$webget] = array();

		global $lang;		
		if(empty($lang))
		{		
			$lang = new language();
			$lang->load($current['lang']);
		}
		
		// default translations		
		$webgets[$webget]['translations'] = array(
				'post_a_comment' => t(379, 'Post a comment'),
				'name' => t(159, 'Name'),
				'email' => t(44, 'E-Mail'),
				'message' => t(380, 'Message'),
				'email_will_not_be_published' => t(381, 'E-Mail will not be published'),
				'submit' => t(382, 'Submit'),
				'sign_in_or_sign_up_to_post_a_comment' => t(383, 'Sign in or Sign up to post a comment'),
				'comments_on_this_entry_are_closed' => t(384, 'Comments on this entry are closed'),
				'please_dont_leave_any_field_blank' => t(385, 'Please don\'t leave any field blank'),
				'your_comment_has_been_received_and_will_be_published_shortly' => t(386, 'Your comment has been received and will be published shortly'),
				'new_comment' => t(387, 'New comment'),
				'review_comments' => t(388, 'Review comments')
		);
		
		// theme translations 
		// if the web theme has custom translations for this string subtypes, use it (for the user selected language)
		/* just add the following translations to your json theme dictionary:

			"post_a_comment": "Post a comment",
			"name": "Name",
			"email": "E-Mail",
			"message": "Message",
			"email_will_not_be_published": "E-Mail will not be published",
			"submit": "Submit",
			"sign_in_or_sign_up_to_post_a_comment": "Sign in or Sign up to post a comment",
			"comments_on_this_entry_are_closed": "Comments on this entry are closed",
			"please_dont_leave_any_field_blank": "Please don't leave any field blank",
			"your_comment_has_been_received_and_will_be_published_shortly": "Your comment has been received and will be published shortly",
			"new_comment": "New comment",
			"review_comments": "Review comments"
			
		*/
		if(!empty($website->theme) && function_exists($theme->t))
		{
			foreach($webgets[$webget]['translations'] as $code => $text)
			{
				$theme_translation = $theme->t($code);
				if(!empty($theme_translation))
					$webgets[$webget]['translations'][$code] = $theme_translation;
			}	
		}
	}

	if(empty($vars['alert_callback'])) $vars['alert_callback'] = 'alert';	

	$out = '';

    // if the current page belongs to a structure entry
    // we need to get the associated elements to retrieve and post its comments
    // (because structure entry pages can't have associated comments)
    // so, ONLY the FIRST element associated to a category can have comments in a structure entry page
    // (of course if the element has its own page, it can have its own comments)
    $element = $current['object'];
    if($current['type']=='structure')
    {
        if(empty($current['structure_elements']))
            $current['structure_elements'] = $element->elements();
        $element = $current['structure_elements'][0];
    }

	switch(@$vars['mode'])
	{	
		case 'process':
		
			if($_REQUEST['form-type']=='comment-reply')
			{
				// add comment	
				if( (	(empty($_REQUEST['reply-name']) ||	empty($_REQUEST['reply-email'])) && empty($webuser->id)) ||
					empty($_REQUEST['reply-message']))
				{
					nvweb_after_body("js", $vars['alert_callback'].'("'.$webgets[$webget]['translations']['please_dont_leave_any_field_blank'].'");');
					return;
				}				

				$status = -1; // new comment, not approved

				if(empty($element->comments_moderator))
                    $status = 0; // all comments auto-approved

                $comment = new comment();
                $comment->id = 0;
                $comment->website = $website->id;
                $comment->item = $element->id;
                $comment->user = (empty($webuser->id)? 0 : $webuser->id);
                $comment->name = $_REQUEST['reply-name'];
                $comment->email = $_REQUEST['reply-email'];
                $comment->ip = core_ip();
                $comment->date_created = core_time();
                $comment->date_modified = 0;
                $comment->status = $status;
                $comment->message = htmlentities($_REQUEST['reply-message'], ENT_COMPAT, 'UTF-8', true);
                $comment->insert();

                // reload the element to retrieve the new comments
                $element->load($element->id);

                // trigger the "new_comment" event through the plugin system
                $events->trigger('comment', 'after_insert', array('comment' => $comment));

				if($DB->get_last_id() > 0 && $status == -1)
					nvweb_after_body("js", $vars['alert_callback'].'("'.$webgets[$webget]['translations']['your_comment_has_been_received_and_will_be_published_shortly'].'");');

				$title = $DB->query_single('text', 'nv_webdictionary', ' node_type= "item" AND 
																		 node_id = '.protect($element->id).' AND
																		 subtype = "title" AND 
																		 lang = '.protect($current['lang']));					

				if(!empty($element->comments_moderator))
				{				
					$message 	= array();
					$message[]  = '<h2>'.$website->name.'</h2>';
					$message[]  = '';				
					$message[]  = $webgets[$webget]['translations']['new_comment'];
					$message[]  = '';				
					$message[]  = '<strong>'.$title.'</strong>';	
					$message[]  = $webgets[$webget]['translations']['name'].': '.$_REQUEST['reply-name'].@$webuser->username;
					$message[]  = $webgets[$webget]['translations']['email'].': '.$_REQUEST['reply-email'].@$webuser->email;
					$message[]  = $webgets[$webget]['translations']['message'].': '.$_REQUEST['reply-message'];																								
					$message[]  = '';				
					$message[]  = '';		
					$message[]  = '<a href="'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=10&act=2&tab=5&id='.$element->id.'">'.$webgets[$webget]['translations']['review_comments'].'</a>';
					$message[]  = '';																
					$message[]  = 'Navigate CMS';												
					$message 	= implode("<br />", $message);
						
					nvweb_send_email($website->name.': '.$webgets[$webget]['translations']['new_comment'], $message, user::email_of($element->comments_moderator));
				}
			}
			break;

		case 'reply':
			if($element->comments_enabled_to==2 && empty($webuser->id))
			{
				// Post a comment (unsigned users)
				$out = '
					<div class="comments-reply">
						<div><div class="comments-reply-info">'.$webgets[$webget]['translations']['post_a_comment'].'</div></div>
						<br />
						<form action="'.NVWEB_ABSOLUTE.'/'.$current['route'].'" method="post">
							<input type="hidden" name="form-type" value="comment-reply" />
							<div><label>'.$webgets[$webget]['translations']['name'].'</label> <input type="text" name="reply-name" value="" /></div>
							<div><label>'.$webgets[$webget]['translations']['email'].' *</label> <input type="text" name="reply-email" value="" /></div>
							<div><label>'.$webgets[$webget]['translations']['message'].'</label> <textarea name="reply-message"></textarea></div>
							<div><label>&nbsp;</label> * '.$webgets[$webget]['translations']['email_will_not_be_published'].'</div>
							<div><input class="comments-reply-submit" type="submit" value="'.$webgets[$webget]['translations']['submit'].'" /></div>
						</form>
					</div>
				';
			}
			else if($element->comments_enabled_to > 0	&& !empty($webuser->id))
			{
				// Post a comment (signed in users)
				$out = '
					<div class="comments-reply">
						<div><div class="comments-reply-info">'.$webgets[$webget]['translations']['post_a_comment'].'</div></div>
						<br />
						<form action="'.NVWEB_ABSOLUTE.'/'.$current['route'].'" method="post">
							<input type="hidden" name="form-type" value="comment-reply" />
							<div><label>&nbsp;</label> <img src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.$webuser->avatar.'&amp;disposition=inline" width="32" height="32" align="absmiddle" /> <span>'.$webuser->username.'</span></div>
							<br/>
							<div><label>'.$webgets[$webget]['translations']['message'].'</label> <textarea name="reply-message"></textarea></div>
							<div><input class="comments-reply-submit" type="submit" value="'.$webgets[$webget]['translations']['submit'].'" /></div>
						</form>
					</div>
				';				
			}
			else if($element->comments_enabled_to==1)
			{
				$out = '<div class="comments-reply"><div class="comments-reply-info">'.$webgets[$webget]['translations']['sign_in_or_sign_up_to_post_a_comment'].'</div></div>';
			}
			else
			{
				$out = '<div class="comments-reply"><div class="comments-reply-info">'.$webgets[$webget]['translations']['comments_on_this_entry_are_closed'].'</div></div>';
			}
			break;

		case 'comments':
            setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

			list($comments, $comments_total) = nvweb_comments_list(); // get all comments of the current entry

			if(empty($vars['avatar_size']))
				$vars['avatar_size'] = '48';
			
			if(empty($vars['date_format']))
				$vars['date_format'] = '%d %B %Y %H:%M';

			for($c=0; $c < $comments_total; $c++)
			{		
				$avatar = $comments[$c]->avatar;
				if(!empty($avatar))
					$avatar = '<img src="'.NVWEB_OBJECT.'?type=image&id='.$avatar.'" width="'.$vars['avatar_size'].'px" height="'.$vars['avatar_size'].'px"/>';
				else
					$avatar = '<img src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" width="'.$vars['avatar_size'].'px" height="'.$vars['avatar_size'].'px"/>';
								
				$out .= '
					<div class="comment">
						<div class="comment-avatar">'.$avatar.'</div>
						<div class="comment-username">'.(!empty($comments[$c]->username)? $comments[$c]->username : $comments[$c]->name).'</div>
						<div class="comment-date">'.Encoding::toUTF8(strftime($vars['date_format'], $comments[$c]->date_created)).'</div>
						<div class="comment-message">'.nl2br($comments[$c]->message).'</div>
						<div style="clear:both"></div>
					</div>
				';				
			}		
			break;	
	}
	
	return $out;
}

function nvweb_comments_list($offset=0, $limit=2147483647, $permission=NULL, $order='oldest')
{
	global $DB;
	global $website;
	global $current;

    if($order=='newest')
        $orderby = "nvc.date_created DESC";
    else
        $orderby = "nvc.date_created ASC";

    $element = $current['object'];
    if($current['type']=='structure')
    {
        if(empty($current['structure_elements']))
            $current['structure_elements'] = $element->elements();
        $element = $current['structure_elements'][0];
    }

    $DB->query('SELECT SQL_CALC_FOUND_ROWS nvc.*, nvwu.username, nvwu.avatar
				  FROM nv_comments nvc
				 LEFT OUTER JOIN nv_webusers nvwu 
							  ON nvwu.id = nvc.user
				 WHERE nvc.website = '.protect($website->id).'
				   AND nvc.item = '.protect($element->id).'
				   AND status = 0 
				ORDER BY '.$orderby.'
				LIMIT '.$limit.'
			   OFFSET '.$offset);		
	
	$rs = $DB->result();
	$total = $DB->foundRows();
		
	return array($rs, $total);
}

?>