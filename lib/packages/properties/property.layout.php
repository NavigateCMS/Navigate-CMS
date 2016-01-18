<?php
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');

function navigate_property_layout_form($element, $template, $item, $item_id)
{
    global $website;
    global $layout;

	$out = array();
    $property_rows = array();
	
	// load the element properties
	//$properties = property::elements($element, $template);

	// load the item property values
	$properties = property::load_properties($element, $template, $item, $item_id);

	// generate the form
	for($p = 0; $p < count($properties); $p++)
	{
		if($properties[$p]->enabled == '0') continue;
		$property_rows[] = navigate_property_layout_field($properties[$p]);
	}

    if(!empty($property_rows) && !empty($property_rows[0]))  // no properties => no form
    {
        $out[] = '<div id="navigate-properties-form">';
        $out[] = '<input type="hidden" name="property-element" value="'.$element.'" />';
        $out[] = '<input type="hidden" name="property-template" value="'.$template.'" />';

        $property_rows = implode("\n", $property_rows);

        // language selector (only if it's a multilanguage website and we have almost one multilanguage property)
        if(count($website->languages) > 1 && strpos($property_rows, 'lang="'.$website->languages_list[1].'"') !== false)
        {
            $website_languages_selector = $website->languages();
            $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

            $naviforms = new naviforms();

            $out[] = '<div class="navigate-form-row">';
            $out[] = '<label>'.t(63, 'Languages').'</label>';
            $out[] = $naviforms->buttonset('properties_language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);");
            $out[] = '</div>';
        }

        $out[] = $property_rows;

        $out[] = '</div>';

	    navigate_property_layout_scripts();
    }
	
	return implode("\n", $out);	
}

function navigate_property_layout_field($property, $object="")
{
	global $website;
	global $layout;
    global $theme;
    global $DB;

	// object used for translations (theme or extension)
	if(empty($object))
		$object = $theme;

	$naviforms = new naviforms();
	$langs = $website->languages_list;

	$field = array();

	if(!isset($property->value))
        $property->value = $property->dvalue;

    if(!isset($property->multilanguage))
        $property->multilanguage = 'false';

	$property_name = $property->name;
	if(!empty($object))
		$property_name = $object->t($property_name);

	if(in_array($property->type, array("text", "textarea", "rich_textarea", "link")) || $property->multilanguage=='true')
	{
		if(!isset($property->multilanguage) || $property->multilanguage !== false || $property->multilanguage == "false")
            $property->multilanguage = 'true';
		else
			$property->multilanguage = 'false';

        if(is_object($property->value))
            $property->value = (array)$property->value;

        if(!is_array($property->value))
            $property->value = array();

		foreach($langs as $lang)
		{
			if(!isset($property->value[$lang]) && isset($property->dvalue))
				$property->value[$lang] = $property->dvalue;
		}
	}

	// auto show/hide properties by other properties values --> "conditional": [ { "source_property_id" : [value1,"value2"] } ]
    if(!empty($property->conditional))
    {
        foreach($property->conditional as $conditional)
        {
            foreach($conditional as $conditional_property => $conditional_values)
            {
                if(!is_array($conditional_values))
                    $conditional_values = array($conditional_values);

                $conditional_values = '["'.implode('", "', $conditional_values).'"]';

                $layout->add_script('
                    navigate_tabform_conditional_property("'.$property->id.'", "'.$conditional_property.'", '.$conditional_values.');
                ');
            }
        }
    }

	
	switch($property->type)
	{
		case 'value':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->textfield("property-".$property->id, $property->value);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;

		case 'rating':
			$default = explode('#', $property->dvalue);
			$stars = $default[1];
			if(empty($stars)) $stars = 5;
			$inputs = $stars*2; // half stars ALWAYS enabled
			
			if($property->value == $property->dvalue)
                $property->value = intval($default[0]) * 2;
		
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" style=" height: 18px; ">';
			$field[] = '<label>'.$property_name.'</label>';
			for($i=1; $i <= $inputs; $i++)
			{		
				$checked = '';
				if($property->value == $i)
                    $checked = ' checked="checked" ';
				$field[] = '<input type="radio" name="property-'.$property->id.'" class="star {split:2}" value="'.$i.'" '.$checked.' />';
			}
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;			

		case 'boolean':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->checkbox("property-".$property->id, ($property->value=='1'));
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';
			break;
		
		case 'option':
            $options = $property->options;

            if(is_string($options))
                $options = mb_unserialize($options);
            else if(is_object($options))
                $options = (array)$options;

            // translate each option text
            if(!empty($object))
            {
                foreach($options as $value => $text)
                    $options[$value] = $object->t($text);
            }

			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->selectfield("property-".$property->id, array_keys($options), array_values($options), $property->value);
            if(!empty($property->helper))
            {
	            $helper_text = $property->helper;
	            if(!empty($object))
		            $helper_text = $object->t($helper_text);
                $field[] = '<div class="subcomment">'.$helper_text.'</div>';
            }
			$field[] = '</div>';			
			break;
			
		case 'moption':
            $options = $property->options;
            if(is_string($options))
                $options = mb_unserialize($options);
            else if(is_object($options))
                $options = (array)$options;

            // translate each option text
            if(!empty($object))
            {
                foreach($options as $value => $text)
                    $options[$value] = $object->t($text);
            }

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->selectfield("property-".$property->id, array_keys($options), array_values($options), explode(',', $property->value), "", true);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;			
			
		case 'country': 				
			$options = property::countries();

			$country_codes = array_keys($options);
			$country_names = array_values($options);

			// include "country not defined" item
			array_unshift($country_codes, '');
			array_unshift($country_names, '('.t(307, "Unspecified").')');

			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->selectfield("property-".$property->id, $country_codes, $country_names, strtoupper($property->value));
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;	
			
		case 'coordinates':
			$coordinates = explode('#', $property->value);
			$latitude  = @$coordinates[0];
			$longitude = @$coordinates[1];			
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->textfield("property-".$property->id.'-latitude',  $latitude, '182px');
			$field[] = $naviforms->textfield("property-".$property->id.'-longitude', $longitude, '182px');
			$field[] = '<img src="img/icons/silk/map_magnify.png" align="absmiddle" hspace="3px" id="property-'.$property->id.'-show" />';
			$field[] = '<div id="property-'.$property->id.'-map-container" style=" display: none; ">';
			$field[] = '	<div class="navigate-form-row" id="property-'.$property->id.'-search" style=" width: 320px; height: 24px; margin-top: 5px; margin-left: 85px; position: absolute; z-index: 100; opacity: 0.9; ">';
			$field[] = '		<input type="text" name="property-'.$property->id.'-search-text" style=" width: 280px; " /> ';
			$field[] = '		<img class="ui-widget ui-button ui-state-default ui-corner-all" sprite="false" style=" cursor: pointer; padding: 3px; " src="'.NAVIGATE_URL.'/img/icons/silk/zoom.png" align="right" />';			
			$field[] = '	</div>';
			$field[] = '	<div id="property-'.$property->id.'-map" style=" width: 400px; height: 200px; "></div>';
			$field[] = '</div>';
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';		
			$field[] = '<script language="javascript" type="text/javascript" src="https://maps.google.com/maps/api/js?sensor=false"></script>';

			$layout->add_script('
				var property_'.$property->id.'_gmap = null;
			    var marker = null;
							
				$("#property-'.$property->id.'-search input").on("keyup", function(e)
				{	if(e.keyCode == 13)	property'.$property->id.'search();	});
				
				$("#property-'.$property->id.'-search img").on("click", property'.$property->id.'search);
				
				$("#property-'.$property->id.'-show").on("click", function()
				{
					var myLatlng = new google.maps.LatLng(
					    $("#property-'.$property->id.'-latitude").val(),
					    $("#property-'.$property->id.'-longitude").val()
					);
					var myOptions = {
						zoom: 16,
						center: myLatlng,
						mapTypeId: google.maps.MapTypeId.ROADMAP,
						disableDoubleClickZoom: true
					};					
					property_'.$property->id.'_gmap = new google.maps.Map($("#property-'.$property->id.'-map")[0], myOptions);
					var marker = new google.maps.Marker({
						  position: myLatlng,
						  title: myLatlng.lat() + ", " + myLatlng.lng()
					});
					marker.setMap(property_'.$property->id.'_gmap);  
					google.maps.event.addListener(property_'.$property->id.'_gmap, "dblclick", function(event) 
					{
						marker.setMap(null);

						$("#property-'.$property->id.'-latitude").val(event.latLng.lat());
						$("#property-'.$property->id.'-longitude").val(event.latLng.lng())
						marker = new google.maps.Marker({
						  position: event.latLng,
						  title: event.latLng.lat() + ", " + event.latLng.lng()
						});
						marker.setMap(property_'.$property->id.'_gmap);
					});

					$("#property-'.$property->id.'-map-container").dialog({
						width: 600,
						height: 400,
						title: "'.t(300, 'Map').': '.t(301, 'Double click a place to set the coordinates').'",
						resize: property'.$property->id.'resize
					}).dialogExtend(
					{
						maximizable: true,
						"maximize" : property'.$property->id.'resize,
						"restore" : property'.$property->id.'resize
					});
					
					property'.$property->id.'resize();

				}).css("cursor", "pointer");	
				
				function property'.$property->id.'resize()
				{
					$("#property-'.$property->id.'-map").width($("#property-'.$property->id.'-map-container").width()); 
					$("#property-'.$property->id.'-map").height($("#property-'.$property->id.'-map-container").height());	
					google.maps.event.trigger(property_'.$property->id.'_gmap, "resize");
				}
				
				function property'.$property->id.'search()
				{				
					var geocoder = new google.maps.Geocoder();
					geocoder.geocode( { "address": $("#property-'.$property->id.'-search input").val()}, function(results, status) 
					{
						if (status == google.maps.GeocoderStatus.OK)
						{
							property_'.$property->id.'_gmap.setCenter(results[0].geometry.location);
							var marker = new google.maps.Marker(
							{
								map: property_'.$property->id.'_gmap, 
								position: results[0].geometry.location
							});
						} 
						else 
						{
							alert("Geocode was not successful for the following reason: " + status);
						}
					});
					
					return false;
				}				
			');
			break;		
			
		case 'text':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

				$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
				$field[] = '<label>'.$property_name.' '.$language_info.'</label>';
				$field[] = $naviforms->textfield("property-".$property->id."-".$lang, $property->value[$lang]);
				if(!empty($property->helper))
				{
					$helper_text = $property->helper;
					if(!empty($object))
						$helper_text = $object->t($helper_text);
					$field[] = '<div class="subcomment">'.$helper_text.'</div>';
				}
				$field[] = '</div>';
			}
			break;
			
		case 'textarea':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

				$style = "";
				if(!empty($property->width))
					$style = ' width: '.$property->width.'px; ';

				$language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';
				if($property->multilanguage == 'false')
					$language_info = '';

				$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
				$field[] = '<label>'.$property_name.' '.$language_info.'</label>';
				$field[] = $naviforms->textarea("property-".$property->id."-".$lang, $property->value[$lang], 4, 48, $style);
				$field[] = '<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'-'.$lang.'" data-action="copy-from" title="'.t(189, 'Copy from').'..."><img src="img/icons/silk/page_white_copy.png" align="absmiddle"></button>';
				if(!empty($property->helper))
				{
					$helper_text = $property->helper;
					if(!empty($object))
						$helper_text = $object->t($helper_text);
					$field[] = '<div class="subcomment">'.$helper_text.'</div>';
				}
				$field[] = '</div>';

				if($property->multilanguage == 'false')
					break;
			}		
			break;

        case 'rich_textarea':
            foreach($langs as $lang)
            {
                if(!is_array($property->value))
                {
                    $ovalue = $property->value;
                    $property->value = array();
                    foreach($langs as $lang_value)
                        $property->value[$lang_value] = $ovalue;
                }

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';
	            if($property->multilanguage == 'false')
		            $language_info = '';

                $width = NULL;
                if(!empty($property->width))
                    $width = $property->width.'px';

                $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
                $field[] = '<label>'.$property_name.' '.$language_info.'</label>';
                $field[] = $naviforms->editorfield("property-".$property->id."-".$lang, $property->value[$lang], $width);
	            $field[] = '&nbsp;<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'-'.$lang.'" data-action="copy-from" title="'.t(189, 'Copy from').'..."><img src="img/icons/silk/page_white_copy.png" align="absmiddle"></button>';
	            if(!empty($property->helper))
	            {
		            $helper_text = $property->helper;
		            if(!empty($object))
			            $helper_text = $object->t($helper_text);
		            $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	            }
                $field[] = '</div>';

	            if($property->multilanguage == 'false')
		            break;
            }
            break;

        case 'color':
            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
            $field[] = $naviforms->colorfield("property-".$property->id, $property->value, @$property->options);
	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';
            break;

		case 'date':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->datefield("property-".$property->id, $property->value, false);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';			
			break;
			
		case 'datetime':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->datefield("property-".$property->id, $property->value, true);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';					
			break;

        case 'source_code':
            if($property->multilanguage!='true' && $property->multilanguage!='1')
            {
                $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
                $field[] = '<label>'.$property_name.'</label>';
                $field[] = $naviforms->scriptarea("property-".$property->id, $property->value);
	            $field[] = '&nbsp;<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'-'.$lang.'" data-action="copy-from" title="'.t(189, 'Copy from').'..."><img src="img/icons/silk/page_white_copy.png" align="absmiddle"></button>';
	            if(!empty($property->helper))
	            {
		            $helper_text = $property->helper;
		            if(!empty($object))
			            $helper_text = $object->t($helper_text);
		            $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	            }
                $field[] = '</div>';
            }
            else
            {
                foreach($langs as $lang)
                {
                    if(!is_array($property->value))
                    {
                        $ovalue = $property->value;
                        $property->value = array();
                        foreach($langs as $lang_value)
                            $property->value[$lang_value] = $ovalue;
                    }

                    $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

                    $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
                    $field[] = '<label>'.$property_name.' '.$language_info.'</label>';
                    $field[] = $naviforms->scriptarea("property-".$property->id."-".$lang, $property->value[$lang]);
	                $field[] = '&nbsp;<button class="navigate-form-row-property-action" data-field="property-'.$property->id.'-'.$lang.'" data-action="copy-from" title="'.t(189, 'Copy from').'..."><img src="img/icons/silk/page_white_copy.png" align="absmiddle"></button>';
	                if(!empty($property->helper))
	                {
		                $helper_text = $property->helper;
		                if(!empty($object))
			                $helper_text = $object->t($helper_text);
		                $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	                }
                    $field[] = '</div>';
                }
            }
            break;
		
		case 'link':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

                $link = explode('##', $property->value[$lang]);
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
                    $title = $property->value[$lang];
                    $link = $property->value[$lang];
                    $target = '_self';
                }

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';
				if($property->multilanguage == 'false')
					$language_info = '';

				$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'" style="margin-bottom: 0px;">';
				$field[] = '<label>'.$property_name.' '.$language_info.'</label>';
                $field[] = $naviforms->textfield("property-".$property->id."-".$lang."-title", $title);
                $field[] = '<span class="navigate-form-row-info">'.t(67, 'Title').'</span>';
                $field[] = '</div>';
                $field[] = '<div class="navigate-form-row" lang="'.$lang.'" style="margin-bottom: 0px;">';
                $field[] = '<label>&nbsp;</label>';
                $field[] = $naviforms->textfield("property-".$property->id."-".$lang."-link", $link);
                $field[] = '<span class="navigate-form-row-info">'.t(197, 'Link').'</span>';
                $field[] = '</div>';
                $field[] = '<div class="navigate-form-row" lang="'.$lang.'">';
                $field[] = '<label>&nbsp;</label>';
                $field[] = $naviforms->selectfield(
                    "property-".$property->id."-".$lang."-target",
                    array(
                        '_self',
                        '_blank'
                    ),
                    array(
                        t(173, "Follow URL"),
                        t(174, "Open URL (new window)")
                    ),
                    $target
                );
                $field[] = '<span class="navigate-form-row-info">'.t(172, 'Action').'</span>';
				if(!empty($property->helper))
				{
					$helper_text = $property->helper;
					if(!empty($object))
						$helper_text = $object->t($helper_text);
					$field[] = '<div class="subcomment">'.$helper_text.'</div>';
				}
                $field[] = '</div>';

				if($property->multilanguage == 'false')
					break;
			}		
			break;
			
		case 'image':
            if($property->multilanguage!='true' && $property->multilanguage!='1')
            {
                $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
                $field[] = '<label>'.$property_name.'</label>';
                $field[] = $naviforms->dropbox("property-".$property->id, $property->value, "image", false, @$property->dvalue);
	            if(!empty($property->helper))
	            {
		            $helper_text = $property->helper;
		            if(!empty($object))
			            $helper_text = $object->t($helper_text);
		            $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	            }
                $field[] = '</div>';
            }
            else
            {
                foreach($langs as $lang)
                {
                    if(!is_array($property->value))
                    {
                        $ovalue = $property->value;
                        $property->value = array();
                        foreach($langs as $lang_value)
                            $property->value[$lang_value] = $ovalue;
                    }

                    $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

                    $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'" lang="'.$lang.'">';
                    $field[] = '<label>'.$property_name.' '.$language_info.'</label>';
                    $field[] = $naviforms->dropbox("property-".$property->id."-".$lang, $property->value[$lang], "image", false, @$property->dvalue);
	                if(!empty($property->helper))
	                {
		                $helper_text = $property->helper;
		                if(!empty($object))
			                $helper_text = $object->t($helper_text);
		                $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	                }
                    $field[] = '</div>';
                }
            }
			break;

        case 'video':
            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
            $field[] = $naviforms->dropbox("property-".$property->id, $property->value, "video", false, $property->dvalue);
	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';
            break;

		case 'file':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->dropbox("property-".$property->id, $property->value);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
			$field[] = '</div>';						
			break;
			
		case 'comment':
			$field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
			$field[] = '<label>'.$property_name.'</label>';
			$comment_text = $property->value;
			if(!empty($object))
				$comment_text = $object->t($property->value);
			$field[] = '<div class="subcomment" style="clear: none;">'.$comment_text.'</div>';
			$field[] = '</div>';								
			break;
			
		case 'category':
            $hierarchy = structure::hierarchy(0);
            $categories_list = structure::hierarchyList($hierarchy, $property->value);

            if(empty($categories_list))
                $categories_list = '<ul><li value="0">'.t(428, '(no category)').'</li></ul>';

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
            $field[] = $naviforms->dropdown_tree("property-".$property->id, $categories_list, $property->value);
			if(!empty($property->helper))
			{
				$helper_text = $property->helper;
				if(!empty($object))
					$helper_text = $object->t($helper_text);
				$field[] = '<div class="subcomment">'.$helper_text.'</div>';
			}
            $field[] = '</div>';
            break;

        case 'categories':
            $hierarchy = structure::hierarchy(0);
            $selected = explode(',', $property->value);
            if(!is_array($selected))
                $selected = array($property->value);
            $categories_list = structure::hierarchyList($hierarchy, $selected);

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
            $field[] = '<div class="category_tree" id="categories-tree-property-'.$property->id.'"><img src="img/icons/silk/world.png" align="absmiddle" /> '.$website->name.$categories_list.'</div>';
            $field[] = $naviforms->hidden('property-'.$property->id, $property->value);
            $field[] = '<label>&nbsp;</label>';
            $field[] = '<button id="categories_tree_select_all_categories-property-'.$property->id.'">'.t(481, 'Select all').'</button>';
	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';

            $layout->add_script('
                $("#categories-tree-property-'.$property->id.' ul:first").kvaTree({
                    imgFolder: "js/kvatree/img/",
                    dragdrop: false,
                    background: "#f2f5f7",
                    overrideEvents: true,
                    onClick: function(event, node)
                    {
                        if($(node).find("span:first").hasClass("active"))
                            $(node).find("span:first").removeClass("active");
                        else
                            $(node).find("span:first").addClass("active");

                        var categories = new Array();

                        $("#categories-tree-property-'.$property->id.' span.active").parent().each(function()
                        {
                            categories.push($(this).attr("value"));
                        });

                        if(categories.length > 0)
                            $("#property-'.$property->id.'").val(categories);
                        else
                            $("#property-'.$property->id.'").val("");
                    }
                });

                $("#categories-tree-property-'.$property->id.' li").find("span:first").css("cursor", "pointer");

                $("#categories_tree_select_all_categories-property-'.$property->id.'").on("click", function()
                {
                    var categories = new Array();

                    $("#categories-tree-property-'.$property->id.'").find("li").not(".separator").each(function(i, el)
                    {
                        $(el).find("span:first").addClass("active");
                        categories.push($(el).attr("value"));
                    });

                    $("#property-'.$property->id.'").val(categories);

                    return false
                });
            ');
            break;

        case 'item':

            $property_item_title = '';
			$property_item_id = '';

            if(!empty($property->value))
            {
                $property_item_title = $DB->query_single(
                    'text',
                    'nv_webdictionary',
                    '   node_type = "item" AND
                        website = "'.$website->id.'" AND
                        node_id = "'.$property->value.'" AND
                        subtype = "title" AND
                        lang = "'.$website->languages_published[0].'"'
                );
	            $property_item_title = array($property_item_title);
	            $property_item_id = array($property->value);
            }

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
			$field[] = $naviforms->selectfield("property-".$property->id, $property_item_id, $property_item_title, $property->value, null, false, null, null, false);
	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';

            $layout->add_script('
                $("#property-'.$property->id.'").select2(
                {
                    placeholder: "'.t(533, "Find element by title").'",
                    minimumInputLength: 1,
                    ajax: {
                        url: "'.NAVIGATE_URL.'/'.NAVIGATE_MAIN.'?fid=items&act=json_find_item",
                        dataType: "json",
                        delay: 100,

                        data: function(params)
                        {
	                        return {
				                title: params.term,
				                template: "'.$property->item_template.'",
				                nd: new Date().getTime(),
				                page_limit: 30, // page size
				                page: params.page // page number
				            };
                        },
                        processResults: function (data, params)
				        {
				            params.page = params.page || 1;
				            return {
								results: data.items,
								pagination: { more: (params.page * 30) < data.total_count }
							};
				        }
                    },
                    templateSelection: function(row)
					{
						if(row.id)
							return row.text + " <helper style=\'opacity: .5;\'>#" + row.id + "</helper>";
						else
							return row.text;
					},
					escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
                    triggerChange: true,
                    allowClear: true
                });
            ');

            break;

        case 'webuser_groups':
            $webuser_groups = webuser_group::all_in_array();

            // to get the array of groups first we remove the "g" character
            $property->value    = str_replace('g', '', $property->value);
            $property->value    = explode(',', $property->value);

            $field[] = '<div class="navigate-form-row" nv_property="'.$property->id.'">';
            $field[] = '<label>'.$property_name.'</label>';
            $field[] = $naviforms->multiselect(
                'property-'.$property->id,
                array_keys($webuser_groups),
                array_values($webuser_groups),
                $property->value
            );
	        if(!empty($property->helper))
	        {
		        $helper_text = $property->helper;
		        if(!empty($object))
			        $helper_text = $object->t($helper_text);
		        $field[] = '<div class="subcomment">'.$helper_text.'</div>';
	        }
            $field[] = '</div>';
            break;

        case 'product':
            // TO DO (when navigate has products!)
            break;
			
		default:
	}
	
	return implode("\n", $field);
}

function navigate_property_layout_scripts()
{
	global $layout;
	global $website;

	$ws_languages = $website->languages();
	$default_language = array_keys($ws_languages);
    $default_language = $default_language[0];

	$naviforms = new naviforms();

	$layout->add_content('
		<div id="navigate-properties-copy-from-dialog" style=" display: none; ">
			<div class="navigate-form-row">
				<label>'.t(191, 'Source').'</label>
				'.$naviforms->buttonset(
					'navigate_properties_copy_from_dialog_type',
					array(
						'language'   => t(46, 'Language'),
						'item'	    => t(180, 'Item'),
						'structure'	=> t(16, 'Structure')
					),
					'0',
					"navigate_properties_copy_from_change_origin(this);"
				).'
			</div>
			<div class="navigate-form-row" style=" display: none; ">
				<label>'.t(46, 'Language').'</label>
				'.$naviforms->selectfield(
					'navigate_properties_copy_from_language_selector',
					array_keys($ws_languages),
					array_values($ws_languages),
					$default_language,
					"navigate_properties_copy_from_change_language(this);"
				).'
			</div>

			<div class="navigate-form-row" style=" display: none; ">
				<label>'.t(67, 'Title').'</label>
				'.$naviforms->textfield('navigate_properties_copy_from_item_title').'
				<button id="navigate_properties_copy_from_item_reload"><i class="fa fa-repeat"></i></button>
				'.$naviforms->hidden('navigate_properties_copy_from_item_id', '').'
			</div>

			<div class="navigate-form-row" style=" display: none; ">
				<label>'.t(67, 'Title').'</label>
				'.$naviforms->textfield('navigate_properties_copy_from_structure_title').'
				<button id="navigate_properties_copy_from_structure_reload"><i class="fa fa-repeat"></i></button>
				'.$naviforms->hidden('navigate_properties_copy_from_structure_id', '').'
			</div>

			<div class="navigate-form-row" style=" display: none; ">
				<label>'.t(239, 'Section').'</label>
				'.$naviforms->select_from_object_array('navigate_properties_copy_from_section', array(), 'code', 'name', '').'
			</div>
		</div>
	');

	$layout->add_script('
		$.getScript("lib/packages/properties/properties.js", function()
		{
			$(".navigate-form-row-property-action").on("click", function(e)
			{
				e.stopPropagation();
				e.preventDefault();
				navigate_properties_copy_from_dialog(this);
			});
		});
	');
}

?>