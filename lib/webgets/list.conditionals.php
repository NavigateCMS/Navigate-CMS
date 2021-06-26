<?php

function nvweb_list_parse_conditional($tag, $item, $item_html, $position, $total)
{
    global $current;

    $out = '';

    switch($tag['attributes']['by'])
    {
        case 'property':
            if(empty($item))
            {
                // can't parse values of empty objects
                return '';
            }

            $property_name = $tag['attributes']['property_id'];
            if(empty($property_name))
            {
                $property_name = $tag['attributes']['property_name'];
            }

            if(!method_exists($item, 'property'))
            {
                return "";
            }

            $property_value = $item->property($property_name);
            $property_definition = $item->property_definition($property_name);

            $condition_value = $tag['attributes']['property_value'];

            if(in_array($property_definition->type, array('image', "file")))
            {
                if($property_value == '0')
                {
                    $property_value = "";
                }
            }

            // process special comparing values
            switch($property_definition->type)
            {
                case 'date':
                    if($condition_value == 'today')
                    {
                        $now = getdate(core_time());
                        $condition_value = mktime(0, 0, 0, $now['mon'], $now['mday'], $now['year']);
                    }
                    else if($condition_value == 'now')
                    {
                        $condition_value = core_time();
                    }
                    break;

                case 'boolean':
                    if($property_value=="" && isset($property_definition->dvalue))
                    {
                        $property_value = $property_definition->dvalue;
                    }
                    break;
            }

            $condition = false;
            if(isset($tag['attributes']['property_empty']))
            {
                // special case: for multilanguage properties check the active language
                if($property_definition->type == 'text' && is_array($property_value))
                {
                    $property_value = $property_value[$current['lang']];
                }

                if( $tag['attributes']['property_empty']=='true' && empty($property_value) ||
                    $tag['attributes']['property_empty']=='false' && !empty($property_value)
                )
                {
                    $condition = true;
                }
            }
            else
            {
                switch($tag['attributes']['property_compare'])
                {
                    case '>':
                    case 'gt':
                        $condition = ($property_value > $condition_value);
                        break;

                    case '<':
                    case 'lt':
                        $condition = ($property_value < $condition_value);
                        break;

                    case '>=':
                    case '=>':
                    case 'gte':
                        $condition = ($property_value >= $condition_value);
                        break;

                    case '<=':
                    case '=<':
                    case 'lte':
                        $condition = ($property_value <= $condition_value);
                        break;

                    case 'in':
                        $condition_values = explode(",", $condition_value);
                        $condition = in_array($property_value, $condition_values);
                        break;

                    case 'nin':
                        $condition_values = explode(",", $condition_value);
                        $condition = !in_array($property_value, $condition_values);
                        break;

                    case '!=':
                    case 'neq':
                        if(is_numeric($property_value))
                        {
                            if($condition_value == 'true' || $condition_value===true)
                            {
                                $condition_value = '1';
                            }
                            else if($condition_value == 'false' || $condition_value===false)
                            {
                                $condition_value = '0';
                            }
                        }

                        $condition = ($property_value != $condition_value);
                        break;

                    case '=':
                    case '==':
                    case 'eq':
                    default:
                        if(is_numeric($property_value))
                        {
                            if($condition_value == 'true' || $condition_value===true)
                            {
                                $condition_value = '1';
                            }
                            else if($condition_value == 'false' || $condition_value===false)
                            {
                                $condition_value = '0';
                            }
                        }

                        $condition = ($property_value == $condition_value);
                        break;
                }
            }

            if($condition)
            {
                // parse the contents of this condition on this round
                $out = $item_html;
            }
            else
            {
                // remove this conditional html code on this round
                $out = '';
            }

            break;

        case 'product':
            if(isset($tag['attributes']['offer']))
            {
                $on_offer = $item->on_offer();
                if(($tag['attributes']['offer']=='true' || $tag['attributes']['offer']=='1') && $on_offer)
                {
                    $out = $item_html;
                }
                else if(($tag['attributes']['offer']=='false' || $tag['attributes']['offer']=='0') && !$on_offer)
                {
                    $out = $item_html;
                }
                else
                {
                    $out = '';
                }
            }

            if(isset($tag['attributes']['top']))
            {
                $is_top = $item->is_top(@$tag['attributes']['top_limit']);
                if(($tag['attributes']['top']=='true' || $tag['attributes']['top']=='1') && $is_top)
                {
                    $out = $item_html;
                }
                else if(($tag['attributes']['top']=='false' || $tag['attributes']['top']=='0') && !$is_top)
                {
                    $out = $item_html;
                }
                else
                {
                    $out = '';
                }
            }

            if(isset($tag['attributes']['new']))
            {
                $is_new = $item->is_new(@$tag['attributes']['since']); // since: days
                if(($tag['attributes']['new']=='true' || $tag['attributes']['new']=='1') && $is_new)
                {
                    $out = $item_html;
                }
                else if(($tag['attributes']['new']=='false' || $tag['attributes']['new']=='0') && !$is_new)
                {
                    $out = $item_html;
                }
                else
                {
                    $out = '';
                }
            }

            if(isset($tag['attributes']['stock']))
            {
                switch($tag['attributes']['stock'])
                {
                    case 'true':
                    case 'yes':
                        if($item->stock_available > 0)
                        {
                            $out = $item_html;
                        }
                        else
                        {
                            $out = "";
                        }
                        break;

                    case 'false':
                    case 'no':
                        if($item->stock_available == 0)
                        {
                            $out = $item_html;
                        }
                        else
                        {
                            $out = "";
                        }
                        break;

                    default:
                        $value = $tag['attributes']['stock'];
                        if(is_numeric($value))
                        {
                            // exact value
                            if($item->stock_available == $value)
                            {
                                $out = $item_html;
                            }
                            else
                            {
                                $out = "";
                            }
                        }
                        else if(strpos($value, "-")!==false)
                        {
                            // range min-max
                            // Examples: 1-5
                            list($value_min, $value_max) = explode("-", $value);
                            $value_min = trim($value_min);
                            $value_max = trim($value_max);

                            if( $item->stock_available >= $value_min    &&
                                $item->stock_available <= $value_max
                            )
                            {
                                $out = $item_html;
                            }
                            else
                            {
                                $out = "";
                            }
                        }
                        else
                        {
                            // undefined condition
                            $out = "";
                        }
                        break;
                }
            }
            break;

        case 'template':
        case 'templates':
            if(empty($item))
            {
                // can't parse values of empty objects
                return '';
            }

            $templates = array();
            if(isset($tag['attributes']['templates']))
            {
                $templates = explode(",", $tag['attributes']['templates']);
            }
            else if(isset($tag['attributes']['template']))
            {
                $templates = array($tag['attributes']['template']);
            }

            if(empty($item->template))
            {
                // check if the item is embedded in a category, so we have to get the template from the category, not the item
                if(get_class($item) == 'item' && $item->association == 'category' && $item->embedding == 1)
                {
                    // assign template from its category
                    $item_category = new structure();
                    $item_category->load($item->category);
                    $item->template = $item_category->template;
                }
            }

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

        case 'position':
            if(empty($item))
            {
                // can't parse values of empty objects
                return '';
            }

            if(isset($tag['attributes']['each']))
            {
                if($position % $tag['attributes']['each'] == 0) // condition applies
                {
                    $out = $item_html;
                }
                else // remove the full nvlist_conditional tag, doesn't apply here
                {
                    $out = '';
                }
            }
            else if(isset($tag['attributes']['range']))
            {
                list($pos_min, $pos_max) = explode('-', $tag['attributes']['range']);

                if(($position+1) >= $pos_min && ($position+1) <= $pos_max)
                {
                    $out = $item_html;
                }
                else
                {
                    $out = '';
                }
            }
            else if(isset($tag['attributes']['position']))
            {
                switch($tag['attributes']['position'])
                {
                    case 'first':
                        if($position == 0)
                        {
                            $out = $item_html;
                        }
                        else
                        {
                            $out = '';
                        }
                        break;

                    case 'not_first':
                        if($position > 0)
                        {
                            $out = $item_html;
                        }
                        else
                        {
                            $out = '';
                        }
                        break;

                    case 'last':
                        if($position == ($total-1))
                        {
                            $out = $item_html;
                        }
                        else
                        {
                            $out = '';
                        }
                        break;

                    case 'not_last':
                        if($position != ($total-1))
                        {
                            $out = $item_html;
                        }
                        else
                        {
                            $out = '';
                        }
                        break;

                    default:
                        // position "x"?
                        if($tag['attributes']['position']==='0')
                        {
                            $tag['attributes']['position'] = 1;
                        }
                        if(($position+1) == $tag['attributes']['position'])
                        {
                            $out = $item_html;
                        }
                        else
                        {
                            $out = '';
                        }
                        break;
                }
            }
            break;

        case 'block':
            if(empty($item))
            {
                // can't parse values of empty objects
                return '';
            }

            // $item may be a block object or a block group block type
            $output_condition = true;
            if(isset($tag['attributes']['type']))
            {
                if( !(  $tag['attributes']['type'] == $item->type ||
                        $tag['attributes']['type'] == $item->id
                    )
                )
                {
                    $output_condition = false;
                }
            }

            // conditional by block trigger type
            if(isset($tag['attributes']['trigger']))
            {
                // allow using "hidden" for internally set "(empty)" types
                if( $tag['attributes']['trigger'] == 'hidden' )
                {
                    $tag['attributes']['trigger'] = "";
                }

                if( $item->trigger['trigger-type'][$current['lang']] != $tag['attributes']['trigger'] )
                {
                    $output_condition = false;
                }
            }

            if($output_condition)
            {
                $out = $item_html;
            }
            else
            {
                $out = "";
            }

            // does the block have a link defined?
            if(isset($tag['attributes']['linked']))
            {
                $block_has_link = in_array(
                    $item->action['action-type'][$current['lang']],
                    array("web", "web-n", "file", "image", "javascript")
                );

                if( $tag['attributes']['linked'] == "true" && $block_has_link)
                {
                    $out = $item_html;
                }
                else if( $tag['attributes']['linked'] == "false" && !$block_has_link)
                {
                    $out = $item_html;
                }
                else
                {
                    // no match, discard this conditional
                    $out = '';
                }
            }
            break;

        case 'block_type':
            if(empty($item)) return ''; // can't parse values of empty objects

            // $item is a block type defined in a block group (to add a title before listing blocks of that kind)
            if(isset($tag['attributes']['type']) && $item->_object_type == "block_group_block_type")
            {
                if( $tag['attributes']['type'] == $item->type || $tag['attributes']['type'] == $item->id )
                {
                    $out = $item_html;
                }
                else
                {
                    // no match, discard this conditional
                    $out = '';
                }
            }
            else
            {
                $out = '';
            }
            break;

        case 'access':
            if(empty($item))
            {
                // can't parse values of empty objects
                return '';
            }

            $access = 0;
            switch($tag['attributes']['access'])
            {
                case 'navigate_user':
                    if(!empty($_SESSION['APP_USER#'.APP_UNIQUE]))
                    {
                        $access = 0; // everybody
                    }
                    else
                    {
                        $access = -1; // nobody!
                    }
                    break;

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
            {
                $out = $item_html;
            }
            else
            {
                $out = '';
            }
            break;

        case 'gallery':
            if(empty($item))
            {
                return ''; // can't parse values of empty objects
            }

            if($tag['attributes']['empty']=='true')
            {
                if(empty($item->galleries[0]))
                {
                    $out = $item_html;
                }
            }
            else if($tag['attributes']['empty']=='false')
            {
                if(!empty($item->galleries[0]))
                {
                    $out = $item_html;
                }
            }
            break;

        case 'tags':
            if(empty($item))
            {
                // can't parse values of empty objects
                return '';
            }

            if($tag['attributes']['empty']=='true')
            {
                if(empty($item->dictionary[$current['lang']]['tags']))
                {
                    $out = $item_html;
                }
            }
            else if($tag['attributes']['empty']=='false')
            {
                if(!empty($item->dictionary[$current['lang']]['tags']))
                {
                    $out = $item_html;
                }
            }
            break;

        case 'structure':
            if(empty($item)) return ''; // can't parse values of empty objects

            if( isset($tag['attributes']['show_in_menus']) && isset($item->visible) )
            {
                if($item->visible == 1 && in_array($tag['attributes']['show_in_menus'], array(1, true, "true")))
                {
                    $out = $item_html;
                }
                else if($item->visible != 1 && !in_array($tag['attributes']['show_in_menus'], array(1, true, "true")))
                {
                    $out = $item_html;
                }
                else
                {
                    $out = "";
                }
            }
            else
            {
                // no match, discard this conditional
                $out = '';
            }
            break;

        case 'count':
            // check the number of results found
            // note: this is called also WHEN resultset is empty
            if( $tag['attributes']['value'] == $total    ||
                ($tag['attributes']['value'] == "empty" && $total == 0)
            )
            {
                $out = $item_html;
            }
            else
            {
                $out = '';
            }
            break;

        case 'comment':
            switch($tag['attributes']['check'])
            {
                case 'website':
                    $has_website = false;

                    if(!empty($item->url))
                    {
                        $has_website = true;
                    }
                    else if(!empty($item->user))
                    {
                        $wu = new webuser();
                        $wu->load($item->user);
                        if(!empty($wu->social_website))
                        {
                            $has_website = true;
                        }
                    }

                    if($has_website && (!isset($tag['attributes']['empty']) || $tag['attributes']['empty'] == 'false'))
                    {
                        $out = $item_html;
                    }
                    else if(!$has_website && @$tag['attributes']['empty'] == 'true')
                    {
                        $out = $item_html;
                    }
                    else
                    {
                        $out = "";
                    }
                    break;

                default:
                    $out = "";
            }
            break;

        case 'query':
            switch($tag['attributes']['check'])
            {
                case 'field':
                    if( isset($item->_query->{$tag['attributes']['field']}) &&
                        !empty($item->_query->{$tag['attributes']['field']})
                    )
                    {
                        $out = $item_html;
                    }
                    break;

                case 'field_range':
                    if( isset($item->_query->{$tag['attributes']['field']}))
                    {
                        $min = value_or_default($tag['attributes']['min'], -PHP_INT_MAX);
                        $max = value_or_default($tag['attributes']['max'], PHP_INT_MAX);

                        if( ($min && $item->_query->{$tag['attributes']['field']} >= $min)  &&
                            ($max && $item->_query->{$tag['attributes']['field']} <= $max)
                        )
                        {
                            $out = $item_html;
                        }
                    }
                    break;

                default:
                    $out = '';
            }
            break;

        default:
            // unknown nvlist_conditional, discard
            $out = '';
    }

    return $out;
}

function nvweb_list_parse_filters($raw, $object='item')
{
    global $website;
    global $DB;
    global $current;

    $alias = 'i';
    if($object=='product')
    {
        $alias = 'p';
    }

    if($object=='brand')
    {
        $alias = 'b';
    }

    $filters = array();

    if(!is_array($raw))
    {
        $raw = str_replace("'", '"', $raw);
        $aFilters = json_decode($raw, true);
    }
    else
    {
        $aFilters = $raw;
    }

    if(APP_DEBUG && json_last_error() > 0)
    {
        debugger::console($raw, json_last_error_msg());
    }

    $comparators = array(
        'eq' => '=',
        'neq' => '!=',
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<='
    );

    for($f=0; $f < count($aFilters); $f++)
    {
        $filter = $aFilters[$f];

        $key = array_keys($filter);
        $key = $key[0];
        $value = $filter[$key];

        if(substr($key, 0, 9) == 'property.')
        {
            // object property value
            // TODO: filters for values in DICTIONARY
            $key = substr($key, 9);

            if(!is_array($value))
            {
                if(substr($value, 0, 1)=='$')
                {
                    if(!isset($_REQUEST[substr($value, 1)]))
                    {
                        continue;
                    }   // ignore this filter

                    $value = $_REQUEST[substr($value, 1)];
                    if(empty($value)) // ignore empty values
                    {
                        continue;
                    }
                }
                else if(strpos($value, 'property.') === 0)
                {
                    // retrieve the property value
                    $value = nvweb_properties(
                        array(
                            'property' => str_replace("property.", "", $value)
                        )
                    );
                }

                $filters[] = ' AND '.$alias.'.id IN ( 
                                 SELECT node_id 
                                   FROM nv_properties_items
                                  WHERE website = '.$website->id.' AND
                                        property_id = '.protect($key).' AND
                                        element = "'.$object.'" AND
                                        value = '.protect($value).'
                               )';
            }
            else
            {
                foreach($value as $comp_type => $comp_value)
                {
                    if(!is_array($comp_value) && substr($comp_value, 0, 1)=='$')
                    {
                        if(!isset($_REQUEST[substr($comp_value, 1)]))
                        {
                            continue;
                        }   // ignore this filter

                        $comp_value = $_REQUEST[substr($comp_value, 1)];
                        if(empty($comp_value)) // ignore empty values
                        {
                            continue;
                        }
                    }
                    else if(!is_array($comp_value) && strpos($comp_value, 'property.') === 0)
                    {
                        // retrieve the property value
                        $comp_value = nvweb_properties(
                            array(
                                'property' => str_replace("property.", "", $comp_value)
                            )
                        );
                    }

                    if(isset($comparators[$comp_type]))
                    {
                        $filters[] = ' 
                            AND '.$alias.'.id IN ( 
                                 SELECT node_id 
                                   FROM nv_properties_items
                                  WHERE website = '.$website->id.' AND
                                        property_id = '.protect($key).' AND
                                        element = "'.$object.'" AND
                                        value '.$comparators[$comp_type].' '.protect($comp_value, null, true).'
                            )';
                    }
                    else if($comp_type == 'like' || $comp_type == 'not_like')
                    {
                        if(is_array($comp_value))
                        {
                            // multivalue, query with REGEXP: http://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_regexp
                            $filters[] = ' 
                                AND '.$alias.'.id IN ( 
                                     SELECT node_id 
                                       FROM nv_properties_items
                                      WHERE website = '.$website->id.' AND
                                            property_id = '.protect($key).' AND
                                            element = "'.$object.'" AND
                                            value '.($comp_type=='like'? 'REGEXP' : 'NOT REGEXP').' "'.implode('|', $comp_value).'"
                                )';
                        }
                        else
                        {
                            // single value, standard LIKE
                            $filters[] = ' 
                                AND '.$alias.'.id IN ( 
                                     SELECT node_id 
                                       FROM nv_properties_items
                                      WHERE website = '.$website->id.' AND
                                            property_id = '.protect($key).' AND
                                            element = "'.$object.'" AND
                                            value '.($comp_type=='like'? 'LIKE' : 'NOT LIKE').' '.protect('%'.$comp_value.'%', null, true).'
                                )';
                        }
                    }
                    else if($comp_type == 'in' || $comp_type == 'nin')
                    {
                        if($comp_type == 'nin')
                        {
                            $comp_type = 'NOT IN';
                        }
                        else
                        {
                            $comp_type = 'IN';
                        }

                        if(!is_array($comp_value))
                        {
                            $comp_value = explode(",", $comp_value);
                        }

                        if(empty($comp_value))
                        {
                            $comp_value = array(0);
                        } // avoid SQL query exception

                        $filters[] = ' 
                            AND '.$alias.'.id IN ( 
                                SELECT node_id 
                                  FROM nv_properties_items
                                 WHERE website = '.$website->id.' AND
                                        property_id = '.protect($key).' AND
                                        element = "'.$object.'" AND
                                        value '.$comp_type.'('.
                                            implode(
                                                ",",
                                                array_map(
                                                    function($v)
                                                    {
                                                        return protect($v);
                                                    },
                                                    array_values($comp_value)
                                                )
                                            ).')
                            )';
                    }
                    else if($comp_type == 'has' || $comp_type == 'hasnot')
                    {
                        if($comp_type == 'hasnot')
                        {
                            $comp_type = 'NOT FIND_IN_SET';
                        }
                        else
                        {
                            $comp_type = 'FIND_IN_SET';
                        }

                        if(!is_array($comp_value))
                        {
                            $comp_value = explode(",", $comp_value);
                        }

                        if(empty($comp_value))
                        {
                            $comp_value = array(0);
                        } // avoid SQL query exception

                        foreach($comp_value as $comp_value_part)
                        {
                            $filters[] = ' 
                                AND '.$alias.'.id IN ( 
                                    SELECT node_id 
                                      FROM nv_properties_items
                                     WHERE website = ' . $website->id . ' AND
                                            property_id = ' . protect($key) . ' AND
                                            element = "'.$object.'" AND
                                            ' . $comp_type . '(' . protect($comp_value_part) .', value)                               
                                )';
                        }
                    }
                }
            }
        }
        else
        {
            $direct_filter = false;

            // object value
            switch($key)
            {
                // item & product common values
                case 'id':
                    $field = $alias.'.id';
                    $direct_filter = true;
                    break;

                case 'author':
                    $field = $alias.'.author';
                    $direct_filter = true;
                    break;

                case 'date_to_display':
                    $field = $alias.'.date_to_display';
                    $direct_filter = true;
                    break;

                case 'score':
                    $field = $alias.'.score';
                    $direct_filter = true;
                    break;

                case 'votes':
                    $field = $alias.'.votes';
                    $direct_filter = true;
                    break;

                // product specific values or filters
                case 'brand':
                    if(substr($value, 0, 1)=='$')
                    {
                        if(!isset($_REQUEST[substr($value, 1)]))
                        {
                            // ignore this filter
                            continue 2;
                        }

                        $value = $_REQUEST[substr($value, 1)];
                        if(empty($value)) // ignore empty values
                        {
                            continue 2;
                        }
                    }

                    $brand_id = $DB->query_single(
                        'id',
                        'nv_brands',
                        ' name = :name AND 
                                website = '.$website->id,
                        '',
                        array(':name' => $value)
                    );

                    if(empty($brand_id))
                    {
                        // ignore this filter
                        continue 2;
                    }

                    $filters[] = ' AND ( p.brand = '.$brand_id.' ) ';
                    $direct_filter = false;
                    break;

                case 'offer':
                    if($value == 'true' || $value===true)
                    {
                        $filters[] = ' AND (
                            ' . $alias . '.offer_price > 0 
                            AND ( ' . $alias . '.offer_begin_date = 0 OR '.core_time().' >= '.$alias.'.offer_begin_date)
                            AND ( ' . $alias . '.offer_end_date = 0 OR '.core_time().' <= '.$alias.'.offer_end_date)
                        )';
                    }
                    else
                    {
                        $filters[] = ' AND (
                            ' . $alias . '.offer_price = 0 
                            OR ( ' . $alias . '.offer_begin_date > 0 AND '.core_time().' < '.$alias.'.offer_begin_date)
                            OR ( ' . $alias . '.offer_end_date > 0 AND '.core_time().' > '.$alias.'.offer_end_date)
                        )';
                    }
                    $direct_filter = false;
                    break;

                // brand object filters
                case 'image':
                    $field = $alias.'.image';
                    $direct_filter = true;
                    break;

                default:
                    continue 2;
                    break;
            }

            if($direct_filter)
            {
                if(!is_array($value))
                {
                    if(substr($value, 0, 1)=='$')
                    {
                        if(!isset($_REQUEST[substr($value, 1)]))
                        {
                            // ignore this filter
                            continue;
                        }

                        $value = $_REQUEST[substr($value, 1)];
                        if(empty($value)) // ignore empty values
                        {
                            continue;
                        }
                    }
                    else if(strpos($value, 'property.') === 0)
                    {
                        // retrieve the property value
                        $value = nvweb_properties(
                            array(
                                'property' => str_replace("property.", "", $value)
                            )
                        );
                    }

                    $filters[] = ' AND '.$field.' = '.protect($value);
                }
                else
                {
                    foreach($value as $comp_type => $comp_value)
                    {
                        if(!is_array($comp_value) && substr($comp_value, 0, 1)=='$')
                        {
                            if(!isset($_REQUEST[substr($comp_value, 1)]))
                            {
                                // ignore this filter
                                continue;
                            }

                            $comp_value = $_REQUEST[substr($comp_value, 1)];
                            if(empty($comp_value)) // ignore empty values
                            {
                                continue;
                            }
                        }
                        else if(!is_array($comp_value) && strpos($comp_value, 'property.') === 0)
                        {
                            // retrieve the property value
                            $comp_value = nvweb_properties(
                                array(
                                    'property' => str_replace("property.", "", $comp_value)
                                )
                            );
                        }

                        if(isset($comparators[$comp_type]))
                        {
                            $filters[] = ' AND ' . $field . ' ' . $comparators[$comp_type] . ' ' . protect($comp_value, null, true);
                        }
                        else if($comp_type == 'like' || $comp_type == 'not_like')
                        {
                            if(is_array($comp_value))
                            {
                                // multivalue, query with REGEXP: http://dev.mysql.com/doc/refman/5.7/en/string-functions.html#function_regexp
                                $filters[] = ' AND ' . $field . ' ' . ($comp_type == 'like' ? 'REGEXP' : 'NOT REGEXP') . ' "' . implode('|' . $comp_value) . '"';
                            }
                            else
                            {
                                // single value, standard LIKE
                                $filters[] = ' AND ' . $field . ' ' . ($comp_type == 'like' ? 'LIKE' : 'NOT LIKE') . ' ' . protect('%' . $comp_value . '%', null, true);
                            }
                        }
                        else if($comp_type == 'in' || $comp_type == 'nin')
                        {
                            if($comp_type == 'nin')
                            {
                                $comp_type = 'NOT IN';
                            }
                            else
                            {
                                $comp_type = 'IN';
                            }

                            if(is_array($comp_value))
                            {
                                $comp_value = implode(
                                    ",",
                                    array_map(
                                        function ($v)
                                        {
                                            return protect($v);
                                        },
                                        array_values($comp_value)
                                    )
                                );
                            }
                            else if(empty($comp_value))
                            {
                                $comp_value = 0; // avoid SQL query exception
                            }

                            $filters[] = ' AND '.$field.' '.$comp_type.'('.$comp_value.')';
                        }
                        else if($comp_type == 'has' || $comp_type == 'hasnot')
                        {
                            if($comp_type == 'hasnot')
                            {
                                $comp_type = 'NOT FIND_IN_SET';
                            }
                            else
                            {
                                $comp_type = 'FIND_IN_SET';
                            }

                            if(!is_array($comp_value))
                            {
                                $comp_value = explode(",", $comp_value);
                            }

                            if(empty($comp_value))
                            {
                                $comp_value = array();
                            } // avoid SQL query exception

                            foreach($comp_value as $comp_value_part)
                            {
                                $filters[] = ' 
                                    AND '.$alias.'.id IN ( 
                                        SELECT node_id 
                                          FROM nv_properties_items
                                         WHERE website = ' . $website->id . ' AND
                                                property_id = ' . protect($key) . ' AND
                                                element = "'.$object.'" AND
                                                ' . $comp_type . '(' . protect($comp_value_part) .', '.$field.')                               
                                    )';
                            }
                        }
                    }
                }
            }
        }
    }

    $filters = implode("\n", $filters);

    return $filters;
}

?>