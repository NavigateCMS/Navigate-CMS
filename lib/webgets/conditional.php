<?php
require_once(NAVIGATE_PATH.'/lib/webgets/menu.php');
require_once(NAVIGATE_PATH.'/lib/webgets/properties.php');
require_once(NAVIGATE_PATH.'/lib/webgets/content.php');
require_once(NAVIGATE_PATH.'/lib/webgets/gallery.php');
require_once(NAVIGATE_PATH.'/lib/webgets/votes.php');
require_once(NAVIGATE_PATH.'/lib/webgets/list.php');
require_once(NAVIGATE_PATH.'/lib/packages/structure/structure.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/feeds/feed_parser.class.php');

function nvweb_conditional($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $cache;
	global $structure;
	global $webgets;
    global $webuser;

	$out = array();

	$webget = 'conditional';

    $categories = array();

    $item = new item();

    if($current['type']=='item')
    {
        $item->load($current['object']->id);
    }
    else
    {
        $categories = array($current['object']->id);

        if(isset($vars['categories']))
        {
            $categories = explode(',', $vars['categories']);
            $categories = array_filter($categories); // remove empty elements
        }

        $permission = (!empty($_SESSION['APP_USER#'.APP_UNIQUE])? 1 : 0);

        // public access / webuser based / webuser groups based
        $access = 2;
        $access_extra = '';
        if(!empty($current['webuser']))
        {
            $access = 1;
            if(!empty($webuser->groups))
            {
                $access_groups = array();
                foreach($webuser->groups as $wg)
                {
                    if(empty($wg))
                        continue;
                    $access_groups[] = 's.groups LIKE "%g'.$wg.'%"';
                }
                if(!empty($access_groups))
                    $access_extra = ' OR (s.access = 3 AND ('.implode(' OR ', $access_groups).'))';
            }
        }

        // get order type: PARAMETER > NV TAG PROPERTY > DEFAULT (priority given in CMS)
        $order      = @$_REQUEST['order'];
        if(empty($order))
            $order  = @$vars['order'];
        if(empty($order))   // default order: latest
            $order = 'latest';

        $orderby = nvweb_list_get_orderby($order);

        $rs = NULL;

        $access_extra_items = str_replace('s.', 'i.', $access_extra);

        // default source for retrieving items
        $DB->query('
            SELECT SQL_CALC_FOUND_ROWS i.id, i.permission, i.date_published, i.date_unpublish,
                   i.date_to_display, COALESCE(NULLIF(i.date_to_display, 0), i.date_created) as pdate,
                   d.text as title, i.position as position
              FROM nv_items i, nv_structure s, nv_webdictionary d
             WHERE i.category IN('.implode(",", $categories).')
               AND i.website = '.$website->id.'
               AND i.permission <= '.$permission.'
               AND (i.date_published = 0 OR i.date_published < '.core_time().')
               AND (i.date_unpublish = 0 OR i.date_unpublish > '.core_time().')
               AND s.id = i.category
               AND (s.date_published = 0 OR s.date_published < '.core_time().')
               AND (s.date_unpublish = 0 OR s.date_unpublish > '.core_time().')
               AND s.permission <= '.$permission.'
               AND (s.access = 0 OR s.access = '.$access.$access_extra.')
               AND (i.access = 0 OR i.access = '.$access.$access_extra_items.')
               AND d.website = i.website
               AND d.node_type = "item"
               AND d.subtype = "title"
               AND d.node_id = i.id
               AND d.lang = '.protect($current['lang']).'
             '.$orderby.'
             LIMIT 1
             OFFSET 0'
        );

        $rs = $DB->result();

        // now we have the element against which the condition will be checked
        $i = 0;

        $item->load($rs[$i]->id);
    }

    // get the template
    $item_html = $vars['template'];

    // now, parse the conditional tags (with html source code inside)
    switch($vars['by'])
    {
        case 'property':
            $property_value = NULL;
            $property_name = $vars['property_name'];
            if(empty($vars['property_name']))
                $property_name = $vars['property_id'];

            if($vars['property_scope'] == "element")
            {
                $property_value = $item->property($property_name);
            }
            else if($vars['property_scope'] == "structure")
            {
                $property = nvweb_properties(array('mode' => 'structure', 'property' => $property_name, 'return' => 'object'));
                if(!empty($property))
                    $property_value = $property->value;
            }
            else if($vars['property_scope'] == "website")
            {
                $property_value = $website->theme_options->{$property_name};
            }
            else
            {
                // no scope defined, so we have to check ELEMENT > STRUCTURE > WEBSITE (the first with a property with the given name)
                // element
                $property_value = $item->property($property_name);

                if(!$item->property_exists($property_name))
                {
                    // structure
                    $property = nvweb_properties(array('mode' => 'structure', 'property' => $property_name, 'return' => 'object'));
                    if(!empty($property))
                        $property_value = $property->value;
                    else
                    {
                        // website
                        if(isset($website->theme_options->{$property_name}))
                            $property_value = $website->theme_options->{$property_name};
                        else
                            $property_value = '';
                    }
                }
            }

            // if the property is multilanguage, get the value for the current language
            if(is_array($property_value))
                $property_value = $property_value[$current['lang']];

            // check the given condition
            if(isset($vars['property_value']))
            {
                if( $property_value == $vars['property_value']  ||
                    ($property_value=='1' && $vars['property_value']=='true') ||
                    ($property_value=='0' && $vars['property_value']=='false')
                )
                {
                    // parse the contents of this condition on this round
                    $out = $item_html;
                }
                else
                {
                    // remove this conditional html code on this round
                    $out = '';
                }
            }
            else if(isset($vars['property_empty']))
            {
                if($vars['property_empty']=='true')
                {
                    if(empty($property_value))
                    {
                        // parse the contents of this condition on this round
                        $out = $item_html;
                    }
                    else
                    {
                        // remove this conditional html code on this round
                        $out = '';
                    }
                }
                else if($vars['property_empty']=='false')
                {
                    if(!empty($property_value))
                    {
                        // parse the contents of this condition on this round
                        $out = $item_html;
                    }
                    else
                    {
                        // remove this conditional html code on this round
                        $out = '';
                    }
                }
            }
            break;

        case 'template':
        case 'templates':
            $templates = array();
            if(isset($vars['templates']))
                $templates = explode(",", $vars['templates']);
            else if(isset($vars['template']))
                $templates = array($vars['template']);

            if(in_array($item->template, $templates))
            {
                // the template matches the condition, apply
                $out = $item_html;
            }
            else
            {
                // remove this conditional html code on this round
                $out = '';
            }
            break;

        case 'access':
            $access = 0;
            switch($vars['access'])
            {
                case 3:
                case 'webuser_groups':
                    $access = 3;
                    break;

                case 2:
                case 'not_signed_in':
                    $access = 2;
                    break;

                case 1:
                case 'signed_in':
                    $access = 1;
                    break;

                case 0:
                case 'everyone':
                default:
                    $access = 0;
                    break;
            }

            if($item->access == $access)
                $out = $item_html;
            else
                $out = '';

            break;

        case 'webuser':
            if($vars['signed_in']=='true' && !empty($webuser->id))
                $out = $item_html;
            else if($vars['signed_in']=='false' && empty($webuser->id))
                $out = $item_html;
            else
                $out = '';

            break;

        case 'languages':
            if(count($website->languages_published) >= $vars['min'])
            {
                $out = $item_html;
            }
            else if(count($website->languages_published) <= $vars['max'])
            {
                $out = $item_html;
            }
            break;

        case 'language':
            if($current['lang'] == $vars['lang'])
            {
                $out = $item_html;
            }
            break;

        case 'comments':
            $DB->query('
                SELECT COUNT(*) as total
				  FROM nv_comments
				 WHERE website = '.protect($website->id).'
				   AND item = '.protect($item->id).'
				   AND status = 0
            ');
            $rs = $DB->result();
            $comments_count = $rs[0]->total + 0;

            if(!isset($vars['allowed']))
            {
                if($vars['allowed']=='true' || $vars['allowed']=='1' || empty($vars['allowed']))
                {
                    // comments allowed to everybody (2) or registered users only (1)
                    if( $item->comments_enabled_to == 2 ||
                        ( $item->comments_enabled_to == 1 && !empty($webuser->id)))
                        $out = $item_html;
                }
            }
            else if(($comments_count >= intval($vars['min'])) && isset($vars['min']))
            {
                $out = $item_html;
            }
            else if(($comments_count <= intval($vars['max'])) && isset($vars['max']))
            {
                $out = $item_html;
            }
            break;

        default:
            // unknown nvlist_conditional, discard
            $out = '';
    }

    // return the new html code after applying the condition
	return $out;
}

?>