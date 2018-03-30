<?php
function nvweb_forms($vars=array())
{
    global $session;

	$out = '';

	switch(@$vars['mode'])
	{	
        case 'country_field':
            $options = property::countries($session['lang']);

            $country_codes = array_keys($options);
            $country_names = array_values($options);

            // include "country not defined" item
            array_unshift($country_codes, '');
            array_unshift($country_names, '('.t(307, "Unspecified").')');

            $default = $vars['default'];
            if(isset($_REQUEST[$vars['field_name']]) && !empty($_REQUEST[$vars['field_name']]))
                $default = $_REQUEST[$vars['field_name']];

            $out[] = '<select name="'.$vars['field_name'].'" id="'.$vars['field_id'].'" class="'.$vars['class'].'">';
            for($c=0; $c < count($country_codes); $c++)
            {
                if( $country_codes[$c] == $default )
                    $out[] = '<option value="'.$country_codes[$c].'" selected>'.$country_names[$c].'</option>';
                else
                    $out[] = '<option value="'.$country_codes[$c].'">'.$country_names[$c].'</option>';
            }
            $out[] = '</select>';

            $out = implode("\n", $out);
            break;

        case 'country_region_field':
            $regions = property::countries_regions();

            $out[] = '<select name="'.$vars['field_name'].'" id="'.$vars['field_id'].'" class="'.$vars['class'].'" data-country-field="'.$vars['country_field'].'">';
            $out[] = '<option data-country="" value="">('.t(307, "Unspecified").')</option>';

            $default = $vars['default'];
            if(isset($_REQUEST[$vars['field_name']]) && !empty($_REQUEST[$vars['field_name']]))
                $default = $_REQUEST[$vars['field_name']];

            for($r = 0; $r < count($regions); $r++)
            {
                if( $regions[$r]->region_id  == $default )
                    $out[] = '<option data-country="'.$regions[$r]->country_code.'" value="'.$regions[$r]->region_id.'" selected>'.$regions[$r]->name.'</option>';
                else
                    $out[] = '<option data-country="'.$regions[$r]->country_code.'" value="'.$regions[$r]->region_id.'">'.$regions[$r]->name.'</option>';
            }
            $out[] = '</select>';

            if(!empty($vars['country_field']))
            {
                if(strpos($vars['nvweb_html'], 'jquery')===false)
                    nvweb_after_body('html', '<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>');

                nvweb_after_body('js', '
                    $("select[name='.$vars['country_field'].']").on("change", function(event, keep_selected)
                    {
                        var that = this;
                        if(!keep_selected)
                            $("select[name='.$vars['field_name'].']").find("option:selected").removeAttr("selected");
                        $("select[name='.$vars['field_name'].']").find("option").not(":first").hide();
                        if($(that).find("option:selected").val()!="")
                            $("select[name='.$vars['field_name'].']").find("option[data-country="+$(that).find("option:selected").val()+"]").show();
                    });
                    
                    $("select[name='.$vars['country_field'].']").trigger("change", [true]);
                ');
            }

            $out = implode("\n", $out);
            break;

    }

	return $out;
}

?>