<?php
function run()
{
	global $website;
	global $layout;
	
	switch(@$_REQUEST['act'])
	{
		default:
			$out = dashboard_create();
	}
	
	return $out;
}

function dashboard_create()
{
	global $user;
	global $DB;
	global $website;
	global $layout;
		
	$navibars = new navibars();
	$naviforms = new naviforms();
	
	$navibars->title(t(18, 'Home'));
	
	// TODO: check user permissions

	if($user->profile==1) // Administrator
	{
		$installed_version = update::latest_installed();		
		$latest_update = $_SESSION['latest_update'];
		
		if(!empty($latest_update->Revision) && $latest_update->Revision > $installed_version->revision)
		{
			// current web settings
			$navibars->add_actions(	 array(	'<a href="?fid=update&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/asterisk_orange.png"> '.t(351, 'New update available!').'</a>') );
		}
	}
	
	// current web settings
	$navibars->add_actions(	 array(	'<a href="?fid=websites&act=2&id='.$website->id.'"><img height="16" align="absmiddle" width="16" src="img/icons/silk/world_edit.png"> '.t(177, 'Website').'</a>') );
	
	// user settings
	$navibars->add_actions(	 array(	'<a href="?fid=settings"><img height="16" align="absmiddle" width="16" src="img/icons/silk/user_edit.png"> '.t(14, 'Settings').'</a>') );
	
	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$stats = array();
	
//	$stats['pages_available'] = $DB->query_single('COUNT(DISTINCT object_id)', 'nv_paths', 'website = '.protect($website->id).' GROUP BY object_id');

    // count number of paths, ignoring extra languages (so if the item has 3 languages and 3 different paths, only one is counted)
	$DB->query('
	    SELECT COUNT(c.object_id) as total
	      FROM
	      (
                SELECT DISTINCT p.object_id
                  FROM nv_paths p
                 WHERE p.website = '.protect($website->id).'
              GROUP BY p.object_id
          ) c
    ');
	$count = $DB->first();
	$stats['pages_available'] = $count->total;

    // we need to include elements without paths assigned but accessible through /node/xx
    $DB->query('
        SELECT COUNT(i.id) as total
          FROM nv_items i
         WHERE i.website = '.protect($website->id).'
           AND i.embedding = 0
           AND (
                SELECT count(p.id)
                 FROM nv_paths p
                WHERE p.object_id = i.id
           ) < 1
    ');
    $count = $DB->first();
    $stats['pages_available'] += $count->total;
	
//	$stats['pages_viewed'] = $DB->query_single('SUM(i.views)', 'nv_items i', 'website = '.protect($website->id));
	$stats['comments_count'] = $DB->query_single('COUNT(*)', 'nv_comments', 'website = '.protect($website->id));
	$stats['comments_torevise'] = $DB->query_single('COUNT(*)', 'nv_comments', 'website = '.protect($website->id).' AND status = -1');
	
	$DB->query('
		SELECT SUM(x.page_views) as pages_viewed FROM
		(	
			SELECT i.views as page_views, i.id as id_item 
			  FROM nv_items i
			 WHERE i.website = '.protect($website->id).'
			   AND i.template > 0
			   AND i.embedding = 0

			UNION ALL

			SELECT s.views as page_views, s.id as id_category
			  FROM nv_structure s
			 WHERE s.website = '.protect($website->id).'
		) x
	');

    // i.embedding = 0  : all free items and category items not shown in the first page of a category (p.e. news item)
    // union all main category pages
	
	$stats['pages_viewed'] = $DB->first();
	$stats['pages_viewed'] = intval($stats['pages_viewed']->pages_viewed);

	$navibars->add_tab_content_panel('
	     <img src="img/icons/silk/chart_line.png" align="absmiddle" /> '.t(278, 'Web summary'),
         array(	'<div class="navigate-panels-summary ui-corner-all"><h2>'.$stats['pages_available'].'</h2><br />'.t(279, 'pages available').'</div>',
                '<div class="navigate-panels-summary ui-corner-all"><h2>'.$stats['pages_viewed'].'</h2><br />'.t(280, 'pages viewed').'</div>',
                '<div class="navigate-panels-summary ui-corner-all"><h2>'.$stats['comments_count'].'</h2><br />'.t(250, 'Comments').'</div>',
                '<div class="navigate-panels-summary ui-corner-all"><h2>'.$stats['comments_torevise'].'</h2><br />'.t(281, 'comments to revise').'</div>'
         ),
         'navigate-panel-web-summary',
        '385px',
        '314px'
    );

    $layout->add_script('
        $(".navigate-panels-summary").each(function()
        {
            if($(this).height() > 78)
                $(this).find("br").remove();
        });
    ');
	
	/* TOP PAGES */
	$sql = '
	    SELECT i.views as page_views, i.id as id_item, i.category as id_category, p.views as path_views, p.path as path
          FROM nv_items i, nv_paths p
         WHERE i.website = '.protect($website->id).'
           AND i.template > 0
           AND i.embedding = 0
           AND p.website = '.protect($website->id).'
           AND p.type = "item"
           AND p.object_id = i.id

        UNION ALL

        SELECT s.views as page_views, NULL as id_item, s.id as id_category, p.views as path_views, p.path as path
          FROM nv_structure s, nv_paths p
         WHERE s.website = '.protect($website->id).'
           AND p.website = '.protect($website->id).'
           AND p.type = "structure"
           AND p.object_id = s.id

        ORDER BY path_views DESC
        LIMIT 10
    ';

	$DB->query($sql, 'array');
	$pages = $DB->result();

	$pages_html = '';
	
	$url = $website->protocol;

	if(!empty($website->subdomain))
		$url .= $website->subdomain.'.';		
	$url .= $website->domain;
	$url .= $website->folder;
	
	for($e = 0; $e < 10; $e++)
	{		
		if(!$pages[$e]) break;
		
		$pages_html .= '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
							'<a href="'.$url.$pages[$e]['path'].'" target="_blank">'.
								'<strong>'.$pages[$e]['path_views'].'</strong> <img align="absmiddle" src="img/icons/silk/bullet_star.png" align="absmiddle"> '.$pages[$e]['path'].
							'</a>'.
						  '</div>';
	}

	$navibars->add_tab_content_panel(
        '<img src="img/icons/silk/award_star_gold_3.png" align="absmiddle" /> '.t(296, 'Top pages'),
        $pages_html,
        'navigate-panel-top-pages',
        '385px',
        '314px'
    );
	
	
	/* RECENT COMMENTS */
	$comments_limit = max(5, $stats['comments_torevise']);
	
	$DB->query('SELECT nvc.*, nvwu.username, nvwu.avatar
				  FROM nv_comments nvc
				  LEFT OUTER JOIN nv_webusers nvwu 
							  ON nvwu.id = nvc.user
				  WHERE nvc.website = '.$website->id.'
				 ORDER BY nvc.date_created DESC LIMIT '.$comments_limit);
    // removed
    /*
        .. AND nvwu.website = nvc.website
        .. WHERE nvc.website = '.protect($website->id).'

        to allow cross-website members
    */

	$comments = $DB->result();

	if(!empty($comments[0]))
	{	
		$comments_html = '<div style=" height: 280px; overflow: auto; ">';
		for($c=0; $c < $comments_limit; $c++)
		{				
			if(empty($comments[$c])) break;
			
			if($comments[$c]->status==2)		$comment_status = 'hidden';
			else if($comments[$c]->status==1)	$comment_status = 'private';
			else if($comments[$c]->status==-1)	$comment_status = 'new';		
			else								$comment_status = 'public';		
		
			$tmp = array(
				'<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-'.$comment_status.'">'.
                    '<a href="#" action-href="?fid=comments&act=1&oper=del&ids[]='.$comments[$c]->id.'" style="float: right;"
                        title="'.t(525, "Remove comment (without confirmation)").'" class="navigate-panel-recent-comments-remove">
                        <span class="ui-icon ui-icon-circle-close"></span>
                    </a>'.
					'<a href="?fid=comments&act=2&id='.$comments[$c]->id.'">'.
						core_ts2date($comments[$c]->date_created, true).' '.
						'<strong>'.(empty($comments[$c]->username)? $comments[$c]->name : $comments[$c]->username).'</strong>'.
					'</a>'.
				'</div>',
				'<div id="items-comment-'.$comments[$c]->id.'" class="navigate-panel-recent-comments-element">'.$comments[$c]->message.'</div>');
			
			$comments_html .= implode("\n", $tmp);
		}	
		$comments_html .= '</div>';

        $layout->add_script('
            $(".navigate-panel-recent-comments-username").hover(function()
            {
                $(this).addClass("ui-state-highlight");
            },
            function()
            {
                $(this).removeClass("ui-state-highlight");
            });

            $(".navigate-panel-recent-comments-remove").hover(function()
            {
                $(this).parent().addClass("ui-state-error");
            },
            function()
            {
                $(this).parent().removeClass("ui-state-error");
            });

            $(".navigate-panel-recent-comments-remove").on("click", function()
            {
                var el_comment = $(this).parent();

                $.getJSON(
                    $(this).attr("action-href"),
                    function(result)
                    {
                        if(result==true)
                        {
                            $(el_comment).fadeOut();
                            $(el_comment).next().fadeOut();
                        }
                    }
                );
            });
        ');
		
		$navibars->add_tab_content_panel(
            '<img src="img/icons/silk/comment.png" align="absmiddle" /> '.t(276, 'Recent comments'),
            $comments_html,
            'navigate-panel-recent-comments',
            '385px',
            '314px'
        );
	}


    /* NV USER LOG */
    $DB->query('
        SELECT u.username, ul.action, f.lid as function_lid, f.icon as function_icon, f.id as function_id, ul.item_title as title, ul.date, ul.item as item_id
          FROM nv_users_log ul, nv_users u, nv_functions f
         WHERE u.id = ul.user
           AND ul.action IN ("save", "remove")
           AND website = '.$website->id.'
           AND f.id = ul.function
      GROUP BY u.username, ul.function, ul.item
      ORDER BY date DESC
      LIMIT 10
    ', 'array');
    $users_log = $DB->result();

    if(!empty($users_log))
    {
        $users_log_html = '';
        for($e = 0; $e < 10; $e++)
        {
            if(!@$users_log[$e]) break;
            if(empty($users_log[$e]['title']))
            {
                $users_log[$e]['title'] = '('.t(282, 'Untitled').')';
                if($users_log[$e]['function_id'] == 10) // function: Elements
                {
                    // try to retrieve the title, as it may be assigned later
                    $title = $DB->query_single('text', 'nv_webdictionary', 'website = '.$website->id.' AND node_type = "item" AND node_id = '.$users_log[$e]['item_id'].' AND subtype = "title"', 'id ASC');
                    if(!empty($title))
                        $users_log[$e]['title'] = $title;
                }
            }

            if($users_log[$e]['action']=='save')
            {
                $users_log_html .= '
                    <div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
                        '<a href="?fid='.$users_log[$e]['function_id'].'&act=2&id='.$users_log[$e]['item_id'].'" title="'.core_ts2date($users_log[$e]['date'], true).' - '.t($users_log[$e]['function_lid']).'">'.
                            '<span>'.core_ts2elapsed_time($users_log[$e]['date']).'</span><img align="absmiddle" src="img/icons/silk/bullet_green.png" align="absmiddle">'.$users_log[$e]['username'] . ' <img align="absmiddle" src="'.$users_log[$e]['function_icon'].'" align="absmiddle"> ' . $users_log[$e]['title'].
                        '</a>'.
                    '</div>';
            }
            else if($users_log[$e]['action']=='remove')
            {
                $users_log_html .= '
                    <div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
                    '<a href="?fid='.$users_log[$e]['function_id'].'" title="'.core_ts2date($users_log[$e]['date'], true).' - '.t($users_log[$e]['function_lid']).'">'.
                    '<span>'.core_ts2elapsed_time($users_log[$e]['date']).'</span><img align="absmiddle" src="img/icons/silk/bullet_red.png" align="absmiddle">'.$users_log[$e]['username'] . ' <img align="absmiddle" src="'.$users_log[$e]['function_icon'].'" align="absmiddle"> ' . $users_log[$e]['title'].
                    '</a>'.
                    '</div>';
            }
        }

        $navibars->add_tab_content_panel(
            '<img src="img/icons/silk/page_edit.png" align="absmiddle" /> '.t(577, 'Latest modifications'),
            $users_log_html,
            'navigate-panel-top-elements',
            '385px',
            '314px'
        );
    }

	
	
	/* TOP ITEMS */
	// free items + category items + category templates (without items) -> ORDERED
	$sql = ' SELECT i.id, i.date_modified, i.views, d.text as title, d.lang as language,
	                u.username as author_username
			   FROM nv_items i
		  LEFT JOIN nv_webdictionary d
					 ON i.id = d.node_id
					AND d.node_type = "item"
					AND d.subtype = "title"
					AND d.lang = "'.$website->languages_list[0].'"
					AND d.website = '.$website->id.'
		  LEFT JOIN nv_users u
					 ON u.id = i.author
			  WHERE i.website = '.$website->id.'
				AND i.embedding = 0
				
		   UNION ALL
		   
			SELECT i.id, i.date_modified, s.views, d.text as title, d.lang as language,
			       u.username as author_username
			   FROM nv_items i
		  LEFT JOIN nv_webdictionary d
					 ON i.id = d.node_id
					AND d.node_type = "item"
					AND d.subtype = "title"
					AND d.lang = "'.$website->languages_list[0].'"
					AND d.website = '.$website->id.'
		  LEFT JOIN nv_users u
					 ON u.id = i.author
		  LEFT JOIN nv_structure s
		  			 ON s.id = i.category
			  WHERE i.website = '.$website->id.'
				AND i.embedding = 1
				
		   ORDER BY views DESC
			  LIMIT 4';	    
				
	$DB->query($sql, 'array');
	$elements = $DB->result();
	
	$elements_html = '';
	for($e = 0; $e < 4; $e++)
	{		
		if(!@$elements[$e]) break;
		if(empty($elements[$e]['title'])) $elements[$e]['title'] = '('.t(282, 'Untitled').')';
		
		$elements_html .= '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
							'<a href="?fid=items&act=2&id='.$elements[$e]['id'].'" title="'.core_ts2date($elements[$e]['date_modified'], true).' | '.$elements[$e]['author_username'].'">'.
								'<strong>'.$elements[$e]['views'].'</strong> <img align="absmiddle" src="img/icons/silk/bullet_star.png" align="absmiddle"> '.$elements[$e]['title'].
							'</a>'.
						  '</div>';
	}

	$navibars->add_tab_content_panel(
        '<img src="img/icons/silk/award_star_silver_3.png" align="absmiddle" /> '.t(277, 'Top elements'),
        $elements_html,
        'navigate-panel-top-elements',
        '385px',
        '145px'
    );
			
	
	/* LAST MODIFIED ITEMS */
	$sql = ' SELECT i.*, d.text as title, d.lang as language, u.username as author_username
			   FROM nv_items i
		  LEFT JOIN nv_webdictionary d
					 ON i.id = d.node_id
					AND d.node_type = "item"
					AND d.subtype = "title"
					AND d.lang = "'.$website->languages_list[0].'"
					AND d.website = '.$website->id.'
		  LEFT JOIN nv_users u
					 ON u.id = i.author
			  WHERE i.website = '.$website->id.'
		   ORDER BY date_modified DESC
			  LIMIT 5';	
				
	$DB->query($sql, 'array');
	$elements = $DB->result();

	$elements_html = '';
	for($e = 0; $e < 5; $e++)
	{
		if(!@$elements[$e]) break;
		if(empty($elements[$e]['title'])) $elements[$e]['title'] = '('.t(282, 'Untitled').')';
		$elements_html .= '<div class="navigate-panel-recent-comments-username ui-corner-all items-comment-status-public">'.
							'<a href="?fid=items&act=2&id='.$elements[$e]['id'].'" title="'.core_ts2date($elements[$e]['date_modified'], true).' | '.$elements[$e]['author_username'].'">'.$elements[$e]['title'].'</a>'.
						  '</div>';
	}

	$navibars->add_tab_content_panel(
        '<img src="img/icons/silk/pencil.png" align="absmiddle" /> '.t(275, 'Recent elements'),
        $elements_html,
        'navigate-panel-recent-elements',
        '385px',
        '162px'
    );

	
	//$navibars->add_tab(t(62, "Statistics"));
	
	return $navibars->generate();
}

?>