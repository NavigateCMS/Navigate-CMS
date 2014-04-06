<?php
function nvweb_gallery($vars=array())
{
	global $website;
	global $DB;
	global $current;
    global $webgets;

	$out = '';
    $webget = 'gallery';
	
	// the request can come from a free item or from a category, so we have to load the first element available
	$item = NULL;

    $border = '';
    if(!empty($vars['border']))
        $border = '&border='.$vars['border'];
	
	if(!empty($vars['item']))
	{
		$item = new item();
		$item->load($vars['item']);	
	}
	else if($current['type']=='item')
	{
		// check publishing is enabled
		$enabled = nvweb_object_enabled($current['object']);				
		if($enabled)
			$item = $current['object'];
	}
	else if($current['type']=='structure')
	{
		$DB->query('	SELECT id, permission, date_published, date_unpublish
						  FROM nv_items
						 WHERE category = '.protect($current['object']->id).'
						   AND website = '.$website->id.'
				   ');
		$rs = $DB->first();
		$enabled = nvweb_object_enabled($rs);								
		if($enabled)
		{	
			$item = new item();
			$item->load($rs->id);
		}
	}
		
	if($item==NULL) return '';
	
	if(empty($vars['width']) && empty($vars['height']))
	{
		$vars['width'] = 120;
		$vars['height'] = 90;
	}
	else if(empty($vars['height']))
		$vars['height'] = '';	
	else if(empty($vars['width']))
		$vars['width'] = '';			
	
	// which gallery model?
	$out = array();

	switch(@$vars['mode'])
	{
        case 'image':
            // TO DO: add alt and title to the image
            if(is_array($item->galleries))
                $gallery = $item->galleries[0];

            if(is_string($item->galleries))
            {
                $gallery = mb_unserialize($item->galleries);
                $gallery = $gallery[0];
            }

            // no images in the gallery?
            if(!is_array($gallery))
                return '';

            $image_ids = array_keys($gallery);
            $position = intval($vars['position']);
            $image_selected = $image_ids[$position];

            // no image found at the requested position
            if(empty($image_selected))
                return '';

            $out[] = '<div class="nv_gallery_item">
                        <a class="nv_gallery_a" href="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image_selected.'&amp;disposition=inline" rel="gallery[item-'.$item->id.']">
                            <img class="nv_gallery_image" src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image_selected.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border.'"
                                 alt="" title="" />
                        </a>
                    </div>';
            break;

		case 'greybox':
			/*
			var image_set = [{'caption': 'Flower', 'url': 'http://static.flickr.com/119/294309231_a3d2a339b9.jpg'},
				{'caption': 'Nice waterfall', 'url': 'http://www.widerange.org/images/large/plitvicka.jpg'}];
			*/			
			$out[] = '<div class="nv_gallery">';		
		
			$gallery = mb_unserialize($item->galleries);
			
			$gallery = $gallery[0];
			if(!is_array($gallery)) $gallery = array();
			$first = true;
			
			$jsout = "var image_set_".$item->id." = [";
			$preload = array();			
			
			foreach($gallery as $image => $dictionary)
			{			
				if($first)
				{
					$out[] = '<a href="#" onclick="return GB_showImageSet(image_set_'.$item->id.', 1);">
											<img class="nv_gallery_image" 
												 src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border.'"
												 alt="'.$dictionary[$current['lang']].'" title="'.$dictionary[$current['lang']].'" />
									 </a>';
				}
						
				if(!$first) $jsout .= ','."\n";
				
				$jsout .= '{"caption": "'.$dictionary[$current['lang']].'", "url": "'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline"}';
				$preload[] = "'".NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline';
				$first = false;
			}
			
			$jsout .= "];";
			nvweb_after_body('js', $jsout);
			nvweb_after_body('js', 'AJS.preloadImages('.implode(',', $preload).')');	
			
			$out[] = '<div style=" clear: both; "></div>';
			$out[] = '</div>';						
			break;

        case 'piecemaker':
            $gallery = mb_unserialize($item->galleries);
            $gallery = $gallery[0];
            if(!is_array($gallery)) $gallery = array();

            foreach($gallery as $image => $dictionary)
                $out[] = '<Image Source="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border.'" Title="'.$dictionary[$current['lang']].'"></Image>';
            break;

        case 'images':
            // plain IMG without links or divs
            // TO DO: add alt and title to the image
            if(is_array($item->galleries))
                $gallery = $item->galleries[0];

            if(is_string($item->galleries))
            {
                $gallery = mb_unserialize($item->galleries);
                $gallery = $gallery[0];
            }

            $images = array_keys($gallery);

            if(empty($images))
                return '';

            foreach($images as $img)
            {
                $out[] = '<img class="nv_gallery_image" src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$img.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border.'" alt="" title="" />';
            }
            break;
			
		case 'prettyphoto':
		case 'prettyPhoto':
		default:
			$out[] = '<div class="nv_gallery">';		
		
			if(is_array($item->galleries))
				$gallery = $item->galleries[0];

			if(is_string($item->galleries))		
			{
				$gallery = mb_unserialize($item->galleries);
				$gallery = $gallery[0];
			}
						
			if(!is_array($gallery)) 
				$gallery = array();

			$first = true;
			
			foreach($gallery as $image => $dictionary)
			{			
				if($vars['only_first']=='true')
				{
					$style = ' style="display: none;" ';
					if($first)
						$style = ' style="display: block;" ';
					$first = false;
				}
				
				$out[] = '<div class="nv_gallery_item" '.$style.'>
							<a class="nv_gallery_a" href="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline" rel="gallery[item-'.$item->id.']">
								<img class="nv_gallery_image" src="'.NVWEB_OBJECT.'?wid='.$website->id.'&id='.$image.'&amp;disposition=inline&amp;width='.$vars['width'].'&amp;height='.$vars['height'].$border.'"
									 alt="'.$dictionary[$current['lang']].'" title="'.$dictionary[$current['lang']].'" />
							</a>
						</div>';
			}	
			
			$out[] = '<div style=" clear: both; "></div>';
			$out[] = '</div>';			
			break;

	}
	
	$out = implode("\n", $out);		
	
	return $out;
}

?>