<?php
require_once(NAVIGATE_PATH.'/lib/webgets/content.php');
require_once(NAVIGATE_PATH.'/lib/external/force-utf8/Encoding.php');

function nvweb_properties($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $cache;
	global $properties;

	$out = '';

	switch(@$vars['mode'])
	{
        case 'website':
            $wproperty = new property();
            $wproperty->load_from_theme($vars['property']);
            if(!empty($wproperty))
                $out = nvweb_properties_render($wproperty, $vars);
            break;

		case 'item':
            if(!isset($properties['item-'.$vars['id']]) && !empty($vars['id']))
			{
				// load item template
				if(empty($vars['template']))
					$vars['template'] = $DB->query_single('template', 'nv_items', ' id = '.protect($vars['id']));

                // if template is not defined (embedded element), take its category template
                if(empty($vars['template']))
                    $vars['template'] = $DB->query_single(
                        'template',
                        'nv_structure',
                        ' id = (
                            SELECT category
                            FROM nv_items
                            WHERE id = '.intval($vars['id']).'
                        )'
                    );

				$properties['item-'.$vars['id']] = property::load_properties("item", $vars['template'], 'item', $vars['id']);
			}
            else if(empty($vars['id']))
            {
                $vars['id'] = $current['object']->id;
                $vars['type'] = $current['object']->template;
                if(!isset($properties['item-'.$vars['id']]))
                    $properties['item-'.$current['object']->id] = property::load_properties("item", $vars['type'], 'item', $vars['id']);
            }

			$current_properties	= $properties['item-'.$vars['id']];

			// now we find the property requested
			if(!is_array($current_properties)) $current_properties = array();
			foreach($current_properties as $property)
			{
				if($property->id == $vars['property'] || $property->name == $vars['property'])
				{
					$out = nvweb_properties_render($property, $vars);
					break;	
				}
			}				
			break;

        case 'block':
			if(!isset($properties['block-'.$vars['id']]))
			{
				// load item type
				if(empty($vars['type']))
                {
                    $vars['type'] = $DB->query_single('type', 'nv_blocks', ' id = '.protect($vars['id']));

                    if(empty($cache['block_types']))
                        $cache['block_types'] = block::types();

                    // we need to know if the block is defined in the active theme or in the database (numeric ID)
                    foreach($cache['block_types'] as $bt)
                    {
                        if($bt['code']==$vars['type'])
                        {
                            $vars['type'] = $bt['id'];
                            break;
                        }
                    }
                }

				$properties['block-'.$vars['id']] = property::load_properties("block", $vars['type'], 'block', $vars['id']);
			}

			$current_properties	= $properties['block-'.$vars['id']];

			// now we find the property requested
			if(!is_array($current_properties)) $current_properties = array();
			foreach($current_properties as $property)
			{
				if($property->id == $vars['property'] || $property->name == $vars['property'])
				{
					$out = nvweb_properties_render($property, $vars);
					break;
				}
			}
			break;

        case 'block_group_block':
            // find block_group block definition
            $block_group = null; // unknown
            $block_code = $vars['id'];

            if(empty($block_code))  // find the block group block which has the requested property (property name must be unique!)
            {
                $block = block::block_group_block_by_property($vars['property']);
                $block_code = $block->type;
            }
            else                    // find the block group block by its type
                $block = block::block_group_block($block_group, $block_code);

            $properties = $block->properties;

            $current_properties = property::load_properties($block_code, $block->_block_group_id, 'block_group_block', $block_code);

			// now we find the property requested
			if(!is_array($current_properties))
                $current_properties = array();
			foreach($current_properties as $property)
			{
				if($property->id == $vars['property'] || $property->name == $vars['property'])
				{
					$out = nvweb_properties_render($property, $vars);
					break;
				}
			}
			break;
		
		case 'structure':
			if(empty($vars['id']))
			{
				if($current['type']=='structure')
					$vars['id'] = $current['id'];
				else
					$vars['id'] = $current['object']->category;
			}
						
			if(!isset($properties['structure-'.$vars['id']]))	
			{
				// load category template
				$category_template = $DB->query_single('template', 'nv_structure', ' id = '.protect($vars['id']));
				if(!empty($category_template))
				{
					$properties['structure-'.$vars['id']] = property::load_properties("structure", $category_template, 'structure', $vars['id']);
				}
			}

			$current_properties	= $properties['structure-'.$vars['id']];
			
			// now we find the property requested
			if(!is_array($current_properties)) $current_properties = array();
			foreach($current_properties as $property)
			{
				if($property->id == $vars['property'] || $property->name == $vars['property'])
				{
                    if($vars['return']=='object')
                        $out = $property;
                    else
					    $out = nvweb_properties_render($property, $vars);
					break;	
				}
			}			
			break;
		
		default:
            // find the property source by its name
            $current_properties = array();

            // get website theme property
            $current_properties[] = new property();
            $current_properties[0]->load_from_theme($vars['property']);

			if($current['type']=='item')
			{
				if(!isset($properties['item-'.$current['object']->id]))
					$properties['item-'.$current['object']->id] = property::load_properties("item", $current['object']->template, 'item', $current['object']->id);

                $current_properties = array_merge($current_properties, $properties['item-'.$current['object']->id]);
			}
			else if($current['type']=='structure')
			{
				if(!isset($properties['structure-'.$current['object']->id]))
					$properties['structure-'.$current['object']->id] = property::load_properties("structure", $current['object']->template, 'structure', $current['object']->id);

                $current_properties = array_merge($current_properties, $properties['structure-'.$current['object']->id]);

                // the property could also be in the first item associated to this structure element
                $structure_items = nvweb_content_items($current['object']->id, true, 1);

                if(!empty($structure_items))
                {
                    if(empty($structure_items[0]->template))
                        $structure_items[0]->template = $current['template'];
                    $properties['item-'.$structure_items[0]->id] = property::load_properties("item", $structure_items[0]->template, 'item', $structure_items[0]->id);
                }

                if(!empty($properties['item-'.$structure_items[0]->id]))
                    $current_properties = array_merge($current_properties, $properties['item-'.$structure_items[0]->id]);
			}
			else if($current['type']=='article')
			{
				// TO DO
			}
            else
            {
                // unknown object type, maybe is an object managed by an extension?
                if(!isset($properties[$current['type'].'-'.$current['object']->id]))
					$properties[$current['type'].'-'.$current['object']->id] = property::load_properties($current['type'], $current['object']->template, $current['type'], $current['object']->id);

                $current_properties = array_merge($current_properties, $properties[$current['type'].'-'.$current['object']->id]);
            }

			// now we find the property requested
			if(!is_array($current_properties)) $current_properties = array();
			foreach($current_properties as $property)
            {
                if($property->id == $vars['property'] || $property->name == $vars['property'])
                {
                    $out = nvweb_properties_render($property, $vars);
                    break;
                }
            }

			break;			
	}
		
	return $out;
}

function nvweb_properties_render($property, $vars)
{
	global $website;
	global $current;
	global $DB;
    global $session;
    global $theme;
    global $structure;
	
	$out = '';

    setlocale(LC_ALL, $website->languages[$session['lang']]['system_locale']);

    // if this property is null (no value assigned (null), (empty) is a value!)
    // get the default value
	if(!isset($property->value))
        $property->value = $property->dvalue;

    // check multilanguage properties, where the value can be saved in a language but may be (null) in another language
	if(in_array($property->type, array("text", "textarea", "rich_textarea", "link")) || $property->multilanguage == 'true')
	{
        // cast variable as array
        if(is_object($property->value))
            $property->value = (array)$property->value;

		if(!isset($property->value) || !isset($property->value[$current['lang']]))
			$property->value[$current['lang']] = $property->dvalue->{$current['lang']};
	}

	switch($property->type)
	{
		case 'value':
			$out = $property->value;
			break;
			
		case 'boolean':
			$out = $property->value;
			break;
		
		case 'option': 				
			$options = mb_unserialize($property->options);
            $options = (array)$options;

            switch(@$vars['return'])
            {
                case 'value':
                    $out = $property->value;
                    break;

                default:
                    $out = $theme->t($options[$property->value]);
            }
			break;
			
		case 'moption': 				
			$options = mb_unserialize($property->options);
			$selected = explode(",", $property->value);

            switch(@$vars['return'])
            {
                case 'values':
                    $out = $property->value;
                    break;

                default:
                    $buffer = array();
                    foreach($selected as $seloption)
                    {
                        $buffer[] = '<span>'.$theme->t($options[$seloption]).'</span>';
                    }
                    $out .= implode(', ', $buffer);
            }
			break;
			
		case 'text':
			$out = htmlspecialchars($property->value[$current['lang']]);
			break;
			
		case 'textarea':
			$out = nl2br(htmlspecialchars($property->value[$current['lang']]));
			break;

        case 'rich_textarea':
            $out = $property->value[$current['lang']];
            break;

        case 'source_code':
            if(@$property->multilanguage=='true' || $property->multilanguage=='1')
                $out = $property->value[$current['lang']];
            else
                $out = $property->value;
            break;

		case 'date':
            if(!empty($vars['format']))
			    $out = Encoding::toUTF8(strftime($vars['format'], $property->value));
            else
                $out = date($website->date_format, $property->value);
			break;
			
		case 'datetime':
            if(!empty($vars['format']))
                $out = Encoding::toUTF8(strftime($vars['format'], $property->value));
            else
                $out = date($website->date_format.' H:i', $property->value);
			break;

		case 'link':
            // split title and link
            $link = explode('##', $property->value[$current['lang']]);
            if(is_array($link))
            {
                $target = @$link[2];
                $title = @$link[1];
                $link = $link[0];
                if(empty($title))
                    $title = $link;
            }
            else
            {
                $title = $property->value[$current['lang']];
                $link = $property->value[$current['lang']];
                $target = '_self';
            }

            if(strpos($link, '://')===false)
                $link = $website->absolute_path() . $link;

            if($vars['link']==='false')
            {
				$out = $link;
            }
			else if(isset($vars['return']))
            {
                if($vars['return']=='title')
                    $out = $title;
                else if($vars['return']=='link' || $vars['return']=='url')
                    $out = $link;
                else if($vars['return']=='target')
                    $out = $target;
            }
            else
            {
				$out = '<a href="'.$link.'" target="'.$target.'">'.$title.'</a>';
            }
			break;
			
		case 'image':
			$add = '';
			$extra = '';

            if(@$property->multilanguage=='true'  || $property->multilanguage=='1')
                $image_id = $property->value[$session['lang']];
            else
                $image_id = $property->value;
			
			if(isset($vars['width']))
            {
				$add .= ' width="'.$vars['width'].'" ';
                $extra .= '&width='.$vars['width'];
            }
			if(isset($vars['height']))
            {
				$add .= ' height="'.$vars['height'].'" ';
                $extra .= '&height='.$vars['height'];
            }
			if(isset($vars['border']))
				$extra .= '&border='.$vars['border'];

            if(isset($vars['quality']))
                $extra .= '&quality='.$vars['quality'];

			$img_url = NVWEB_OBJECT.'?type=image&id='.$image_id.$extra;

            if(empty($image_id))
            {
                $out = '';
            }
            else
            {
                if($vars['return']=='url')
                    $out = $img_url;
                else
                {
                    // retrieve additional info (title/alt), if available
                    if(is_numeric($image_id))
                    {
                        $f = new file();
                        $f->load($image_id);

                        $ftitle = $f->title[$current['lang']];
                        $falt = $f->description[$current['lang']];

                        if(!empty($ftitle))
                            $add .= ' title="'.$ftitle.'" ';

                        if(!empty($falt))
                            $add .= ' alt="'.$falt.'" ';
                    }

                    $out = '<img class="'.$vars['class'].'" src="'.$img_url.'" '.$add.' />';
                }
            }
			break;
			
		case 'file':
            if(!empty($property->value))
            {
			    $file = $DB->query_single('name', 'nv_files', ' id = '.protect($property->value).' AND website = '.$website->id);
                if($vars['return']=='url')
                    $out = NVWEB_OBJECT.'?type=file&id='.$property->value.'&disposition=attachment';
                else
			        $out = '<a href="'.NVWEB_OBJECT.'?type=file&id='.$property->value.'&disposition=attachment">'.$file.'</a>';
            }
			break;
			
		case 'comment':
			$out = $property->value;		
			break;
			
		case 'coordinates':
			$coordinates = explode('#', $property->value);
			$out = implode(',', $coordinates);
			break;
			
		case 'rating':
			// half stars always enabled
			$out = $property->value;
			// we want nearest integer down
			if($vars['option']=='floor')
				$out = floor($out/2); 
			break;

        case 'color':
            $out = $property->value;
            break;

        case 'video':
            // value may be a numeric file ID or a provider#id structure, f.e. youtube#3MteSlpxCpo
            // compatible providers: file,youtube,vimeo
            $video_id = $property->value;
            $provider = '';
            $reference = '';

            $add = '';
            if(isset($vars['width']))
                $add .= ' width="'.$vars['width'].'" ';
            if(isset($vars['height']))
                $add .= ' height="'.$vars['height'].'" ';

            if(strpos($video_id, '#')!==false)
                list($provider, $reference) = explode("#", $video_id);

            if($provider=='file')
                $video_id = $reference;

            $file = new file();
            if(is_numeric($video_id))
            {
                $file->load($video_id);
                $embed = file::embed('file', $file, $add);
            }
            else if($provider == 'youtube')
            {
                $embed = file::embed('youtube', $reference, $add);
                if(!empty($vars['part']) || $vars['part']!='embed')
                    $file->load_from_youtube($reference);
            }
            else if($provider == 'vimeo')
            {
                $embed = file::embed('vimeo', $reference, $add);
                if(!empty($vars['part']) || $vars['part']!='embed')
                    $file->load_from_vimeo($reference);
            }

            switch(@$vars['return'])
            {
                case 'title':
                    $out = $file->title;
                    break;

                case 'mime':
                    $out = $file->mime;
                    break;

                case 'author':
                    if(is_numeric($file->uploaded_by))
                        $out = $website->name;
                    else
                        $out = $file->uploaded_by;
                    break;

                case 'path':
                case 'url':
                    $out = $file->extra['link'];
                    break;

                case 'thumbnail_url':
                    $out = $file->extra['thumbnail_url'];
                    break;

                case 'thumbnail':
                    $out = '<img src="'.$file->extra['thumbnail_url'].'" class="'.$vars['class'].'" '.$add.' />';
                    break;

                case 'embed':
                default:
                    $out = $embed;
            }
            break;
			
		case 'article':
			// TO DO
			break;
			
		case 'category':
            $return = @$vars['return'];

            switch($return)
            {
                case 'name':
                    $out = $structure['dictionary'][$property->value];
                    break;

                case 'url':
                case 'link':
                    $out = nvweb_source_url('structure', $property->value);
                    break;

                default:
                    $out = $property->value;
            }
            break;

        case 'categories':
            $out = $property->value;
            break;

        case 'country':
	        $return = @$vars['return'];
	        switch($return)
	        {
		        case 'name':
			        $countries = property::countries();
					$out = $countries[$property->value];
			        break;

		        case 'id':
	            case 'code':
		        default:
			        $out = $property->value;
			        break;
	        }
            break;

        case 'item':
            $return = @$vars['return'];

            switch($return)
            {
                case 'title':
                    $item = new item();
                    $item->load($property->value);
                    $out = $item->dictionary[$current['lang']]['title'];
                    break;

                case 'url':
                case 'path':
                    $out = nvweb_source_url('item', $property->value, $current['lang']);
                    break;

                case 'id':
                default:
                    $out = $property->value;
                    break;
            }
            break;
			
		default:	
	}
	
	return $out;	
}
?>