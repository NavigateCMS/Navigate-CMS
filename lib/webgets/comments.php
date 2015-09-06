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

		if(!empty($website->theme) && method_exists($theme, 't'))
		{
			foreach($webgets[$webget]['translations'] as $code => $text)
			{
				$theme_translation = $theme->t($code);

				if(!empty($theme_translation) && $theme_translation!=$code)
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

            if(isset($_GET['nv_approve_comment']))
            {
                // process 1-click comment approval
                $comment = new comment();
                $comment->load($_GET['id']);

                if(!empty($comment->id) && $comment->status == -1) // comment is still not reviewed
                {
                    $hash = $_GET['hash'];
                    if($hash == sha1($comment->id . $comment->email . APP_UNIQUE . serialize($website->contact_emails)))
                    {
                        // hash check passed
                        $comment->status = 0;
                        $comment->save();
                        nvweb_after_body("js", $vars['alert_callback'].'("'.t(555, "Item has been successfully published.").'");');
                    }
                    else
                    {
                        nvweb_after_body("js", $vars['alert_callback'].'("'.t(344, "Security error").'");');
                    }
                }
                else
                {
                    nvweb_after_body("js", $vars['alert_callback'].'("'.t(56, "Unexpected error").'");');
                }
            }

            if(isset($_GET['nv_remove_comment']))
            {
                // process 1-click comment removal
                $comment = new comment();
                $comment->load($_GET['id']);

                if(!empty($comment->id) && $comment->status == -1) // comment is still not reviewed
                {
                    $hash = $_GET['hash'];
                    if($hash == sha1($comment->id . $comment->email . APP_UNIQUE . serialize($website->contact_emails)))
                    {
                        // hash check passed
                        $comment->delete();
                        nvweb_after_body("js", $vars['alert_callback'].'("'.t(55, "Item successfully deleted").'");');
                    }
                    else
                    {
                        nvweb_after_body("js", $vars['alert_callback'].'("'.t(344, "Security error").'");');
                    }
                }
                else
                {
                    nvweb_after_body("js", $vars['alert_callback'].'("'.t(56, "Unexpected error").'");');
                }
            }

			if($_REQUEST['form-type']=='comment-reply')
			{
				// add comment	
				if(
                    ( (empty($_REQUEST['reply-name'])  ||  empty($_REQUEST['reply-email'])) && empty($webuser->id) )
                    ||
					empty($_REQUEST['reply-message'])
                )
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

                // trigger the "new_comment" event through the extensions system before inserting it!
                $extensions_messages = $events->trigger('comment', 'before_insert', array('comment' => $comment));

                foreach($extensions_messages as $ext_name => $ext_result)
                {
                    if(isset($ext_result['error']))
                    {
                        nvweb_after_body("js", $vars['alert_callback'].'("'.$ext_result['error'].'");');
                        return;
                    }
                }

                $comment->insert();

                // reload the element to retrieve the new comments
                $element = new item();
                $element->load($comment->item);

                if($current['type']=='item')
                    $current['object'] = $element;

                // trigger the "new_comment" event through the extensions system
                $events->trigger('comment', 'after_insert', array('comment' => $comment));

				if(!empty($comment->id) && $status == -1)
					nvweb_after_body("js", $vars['alert_callback'].'("'.$webgets[$webget]['translations']['your_comment_has_been_received_and_will_be_published_shortly'].'");');

                $notify_addresses = $website->contact_emails;

                if(!empty($element->comments_moderator))
                    $notify_addresses[] = user::email_of($element->comments_moderator);

                $hash = sha1($comment->id . $comment->email . APP_UNIQUE . serialize($website->contact_emails) );

                $message = navigate_compose_email(array(
                    array(
                        'title' => t(9, 'Content'),
                        'content' => $element->dictionary[$current['lang']]['title']
                    ),
                    array(
                        'title' => $webgets[$webget]['translations']['name'],
                        'content' => $_REQUEST['reply-name'].@$webuser->username
                    ),
                    array(
                        'title' => $webgets[$webget]['translations']['email'],
                        'content' => $_REQUEST['reply-email'].@$webuser->email
                    ),
                    array(
                        'title' => $webgets[$webget]['translations']['message'],
                        'content' => nl2br($_REQUEST['reply-message'])
                    ),
                    array(
                        'footer' =>
                            '<a href="'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?wid='.$website->id.'&fid=10&act=2&tab=5&id='.$element->id.'"><strong>'.$webgets[$webget]['translations']['review_comments'].'</strong></a>'.
                            '&nbsp;&nbsp;|&nbsp;&nbsp;'.
                            '<a style=" color: #008830" href="'.nvweb_self_url().'?nv_approve_comment&id='.$comment->id.'&hash='.$hash.'">'.t(258, "Publish").'</a>'.
                            '&nbsp;&nbsp;|&nbsp;&nbsp;'.
                            '<a style=" color: #FF0090" href="'.nvweb_self_url().'?nv_remove_comment&id='.$comment->id.'&hash='.$hash.'">'.t(525, "Remove comment (without confirmation)").'</a>'
                    )
                ));

                // trying to implement One-Click actions (used in Google GMail)
                // You need to be registered with Google first: https://developers.google.com/gmail/markup/registering-with-google
                $one_click_actions = '
                    <script type="application/ld+json">
                    {
                        "@context": "http://schema.org",
                        "@type": "EmailMessage",
                        "potentialAction":
                        {
                            "@type": "ViewAction",
                            "name": "'.$webgets[$webget]['translations']['review_comments'].'",
                            "url": "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?wid='.$website->id.'&fid=10&act=2&tab=5&id='.$element->id.'"
                        }
                    }
                    </script>
				';

				$message = '<html><head>'.$one_click_actions.'</head><body>'.$message.'</body></html>';

                foreach($website->contact_emails as $contact_address)
                    nvweb_send_email($website->name.' | '.$webgets[$webget]['translations']['new_comment'], $message, $contact_address);

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
							<div class="comments-reply-field"><label>'.$webgets[$webget]['translations']['name'].'</label> <input type="text" name="reply-name" value="" /></div>
							<div class="comments-reply-field"><label>'.$webgets[$webget]['translations']['email'].' *</label> <input type="text" name="reply-email" value="" /></div>
							<div class="comments-reply-field"><label>'.$webgets[$webget]['translations']['message'].'</label> <textarea name="reply-message"></textarea></div>
							<!-- {{navigate-comments-reply-extra-fields-placeholder}} -->
							<div class="comments-reply-field comments-reply-field-info-email"><label>&nbsp;</label> * '.$webgets[$webget]['translations']['email_will_not_be_published'].'</div>
							<div class="comments-reply-field comments-reply-field-submit"><input class="comments-reply-submit" type="submit" value="'.$webgets[$webget]['translations']['submit'].'" /></div>
						</form>
					</div>
				';

                $extensions_messages = $events->trigger('comment', 'reply_extra_fields', array('html' => &$out));
                // add any extra field generated
                if(!empty($extensions_messages))
                {
                    $extra_fields = array_map(
                        function($v)
                        {
                            return $v;
                        },
                        array_values($extensions_messages)
                    );

                    $out = str_replace(
                        '<!-- {{navigate-comments-reply-extra-fields-placeholder}} -->',
                        implode("\n", $extra_fields),
                        $out
                    );
                }

			}
			else if($element->comments_enabled_to > 0 && !empty($webuser->id))
			{
				// Post a comment (signed in users)
                if(empty($vars['avatar_size']))
                    $vars['avatar_size'] = 32;

                $avatar_url = NVWEB_OBJECT.'?type=blank';
                if(!empty($webuser->avatar))
                    $avatar_url = NVWEB_OBJECT.'?wid='.$website->id.'&id='.$webuser->avatar.'&amp;disposition=inline&width='.$vars['avatar_size'].'&height='.$vars['avatar_size'];

				$out = '
					<div class="comments-reply">
						<div><div class="comments-reply-info">'.$webgets[$webget]['translations']['post_a_comment'].'</div></div>
						<br />
						<form action="'.NVWEB_ABSOLUTE.'/'.$current['route'].'" method="post">
							<input type="hidden" name="form-type" value="comment-reply" />
							<div class="comments-reply-field"><label style="display: none;">&nbsp;</label> <img src="'.$avatar_url.'" width="'.$vars['avatar_size'].'" height="'.$vars['avatar_size'].'" align="absmiddle" /> <span class="comments-reply-username">'.$webuser->username.'</span><a class="comments-reply-signout" href="?webuser_signout">(x)</a></div>
							<br/>
							<div class="comments-reply-field"><label>'.$webgets[$webget]['translations']['message'].'</label> <textarea name="reply-message"></textarea></div>
							<!-- {{navigate-comments-reply-extra-fields-placeholder}} -->
							<div class="comments-reply-field-submit"><input class="comments-reply-submit" type="submit" value="'.$webgets[$webget]['translations']['submit'].'" /></div>
						</form>
					</div>
				';

                $extensions_messages = $events->trigger('comment', 'reply_extra_fields', array('html' => $out));
                // add any extra field generated
                if(!empty($extensions_messages))
                {
                    $extra_fields = array_map(
                        function($v)
                        {
                            return $v;
                        },
                        array_values($extensions_messages)
                    );

                    $out = str_replace(
                        '<!-- {{navigate-comments-reply-extra-fields-placeholder}} -->',
                        implode("\n", $extra_fields),
                        $out
                    );
                }
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
    else if($current['type']=='item')
    {
        $element = new item();
        $element->load($current['id']);
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

function nvweb_website_comments_list($offset=0, $limit=2147483647, $permission=NULL, $order='oldest')
{
    global $DB;
    global $website;
    global $current;

    if($order=='newest')
        $orderby = "nvc.date_created DESC";
    else
        $orderby = "nvc.date_created ASC";

    $DB->query('SELECT SQL_CALC_FOUND_ROWS nvc.*, nvwu.username, nvwu.avatar, nvwd.text as item_title
				  FROM nv_comments nvc
				 LEFT OUTER JOIN nv_webusers nvwu
							  ON nvwu.id = nvc.user
				 LEFT OUTER JOIN nv_webdictionary nvwd
				              ON nvwd.node_id = nvc.item AND
                                 nvwd.website = nvc.website AND
                                 nvwd.node_type = "item" AND
                                 nvwd.subtype = "title" AND
                                 nvwd.lang = '.protect($current['lang']).'
				 WHERE nvc.website = '.protect($website->id).'
				   AND status = 0
				ORDER BY '.$orderby.'
				LIMIT '.$limit.'
			   OFFSET '.$offset);

    $rs = $DB->result();
    $total = $DB->foundRows();

    return array($rs, $total);
}

?>