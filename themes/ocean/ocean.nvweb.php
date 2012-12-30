<?php

nvweb_webget_load('blocks');
nvweb_webget_load('content');
nvweb_webget_load('properties');

function nvweb_ocean($vars=array())
{
    global $website;
    global $current;
    global $theme;

    $out = '';

    switch($vars['mode'])
    {
        case 'theme':
            $out = nvweb_ocean_settings($vars['html']);
            break;

        case 'contact-location':
            $rs = nvweb_content_items($current['object']->id);
            $properties = property::load_properties_associative($rs[0]->id, 'contact', 'item', $rs[0]->id);

            if(@!empty($properties['placemark']))
            {
                $placemark = str_replace('#', ',', $properties['placemark']);
                $out = '<h5>'.$theme->t('location').'</h5>';
                $out.= '<iframe width="300" height="300" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?f=q&amp;source=s_q&amp;geocode=&amp;q='.$placemark.'&amp;t=h&amp;ie=UTF8&amp;z=19&amp;output=embed"></iframe>';
                //$out.= '<iframe width="300" height="300" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="http://maps.google.com/maps?f=q&amp;source=s_q&amp;sll='.$placemark.'&amp;t=h&amp;ie=UTF8&amp;z=19&amp;output=embed"></iframe>';
            }
            break;
    }

    return $out;
}

function nvweb_ocean_settings($html)
{
    // apply theme settings
    global $website;
    global $theme;
    global $template;
    global $current;

    $theme_color_scheme = $website->theme_options->style;

    // theme color scheme
    if(!empty($theme_color_scheme) && $theme_color_scheme!='blue')
    {
        $html = str_replace(    '<link rel="stylesheet" href="css/blue/global.css" />',
                                '<link rel="stylesheet" href="'.$theme->styles->{$theme_color_scheme}->global.'" />',
                                $html
        );

        $html = str_replace(    '<link rel="stylesheet" href="css/blue/blue.css" />',
                                '<link rel="stylesheet" href="'.$theme->styles->{$theme_color_scheme}->color.'" />',
                                $html
        );

        $html = str_replace(    'src="img/zoom.png"',
                                'src="img/colors/'.$theme_color_scheme.'-zoom.png"',
                                $html
        );

        $html = str_replace(    'src="img/edit.png"',
                                'src="img/colors/'.$theme_color_scheme.'-edit.png"',
                                $html
        );
    }

    // header logo
    if(empty($website->theme_options->logo) || $website->theme_options->logo=='img/logo-ocean.png')
    {
        if($theme_color_scheme=='black')
            $html = str_replace('<OCEAN_LOGO />', '<img src="img/colors/black-logo-ocean.png" width="213" height="93" />', $html);
        else
            $html = str_replace('<OCEAN_LOGO />', '<img src="img/logo-ocean.png" width="213" height="93" />', $html);
    }
    else
        $html = str_replace('<OCEAN_LOGO />', '<img src="'.NVWEB_OBJECT.'?id='.$website->theme_options->logo.'" />', $html);


    // footer social links [facebook, twitter, rss]
    $social_links = array();

    if(!empty($website->theme_options->facebook_page))
        $social_links[] = '<a href="'.$website->theme_options->facebook_page.'" target="_blank"><img src="img/facebook.png" width="32" height="32" /></a>';

    if(!empty($website->theme_options->twitter_page))
        $social_links[] = '<a href="'.$website->theme_options->twitter_page.'" target="_blank"><img src="img/twitter.png" width="32" height="32" /></a>';

    if(!empty($website->theme_options->feed_url->$current['lang']))
        $social_links[] = '<a href="'.$website->theme_options->feed_url->$current['lang'].'" target="_blank"><img src="img/rss.png" width="32" height="32" /></a>';

    $html = str_replace('<OCEAN_SOCIAL_ICONS />', implode("\n", $social_links), $html);

    // footer "latest entries" category
    $html = str_replace('{{OCEAN_FOOTER_LATEST_CATEGORY}}', $website->theme_options->footer_latest_entries, $html);

    // home template options (slideshow, quote)
    if($current['template'] == 'home')
    {
        $hquote = '<div class="block-quote" style="border-top: none; padding: 0px;"><br /></div>';

        $tquote = nvweb_content(array(
            'mode' => 'section',
            'section' => 'home_quote'
        ));
        $tquote = strip_tags($tquote);

        if(!empty($tquote))
        {
            $quotes_open = "img/quotes1.png";
            $quotes_close = "img/quotes2.png";

            if($theme_color_scheme=='black')
            {
                $quotes_open = "img/colors/black-quotes1.png";
                $quotes_close = "img/colors/black-quotes2.png";
            }

            $hquote = '
                <div class="block-quote">
                    <span>
                        <img src="'.$quotes_open.'" width="51" height="37" align="bottom"/>'.nl2br($tquote).' <img src="'.$quotes_close.'" width="51" height="37" align="top"/>
                    </span>
                </div>
            ';
        }
        $html = str_replace('<OCEAN_HOME_QUOTE />', $hquote, $html);

        // home gallery
        if(strpos($html, '<OCEAN_HOME_SLIDER />') !== false)
        {
            $default = '
                <a href="http://www.naviwebs.com" target="_blank"><img src="img/slider1.png" width="960" height="330" /></a>
                <a href="http://www.navigatecms.com" target="_blank"><img src="img/slider2.png" width="960" height="330" /></a>
            ';

            if($theme_color_scheme=='black')
            {
                $default = '
                    <a href="http://www.naviwebs.com" target="_blank"><img src="img/colors/black-slider1.png" width="960" height="330" /></a>
                    <a href="http://www.navigatecms.com" target="_blank"><img src="img/colors/black-slider2.png" width="960" height="330" /></a>
                ';
            }

            $out = nvweb_blocks(array(
                'mode' => 'ordered',
                'type' => 'ocean-home-slider'
            ));

            if(empty($out))
                $out = $default;

            $html = str_replace('<OCEAN_HOME_SLIDER />', $out, $html);
        }

        $slideshow_pause = nvweb_properties(array(
            'mode' => 'item',
            'id' => $current['object']->id,
            'property' => 'slideshow_pause'
        ));

        if(empty($slideshow_pause))
            $slideshow_pause = 5;

        $header_script = 'var ocean_slideshow_pause = '.$slideshow_pause.';';

        // home samples "latest works" category
        $html = str_replace('{{OCEAN_HOME_PORTFOLIO_CATEGORY}}', $website->theme_options->home_portfolio_category, $html);
    }

    // javascript variables (must be set on header, before loading ocean.js)
    if(!empty($header_script))
    {
        $before = strpos($html, 'ocean.js');
        $before = strripos(substr($html, 0, $before), '<');

        $script = '
            <script language="javascript" type="text/javascript">
                '.$header_script.'
            </script>
        ';
        $html = substr($html, 0, $before) . $script . substr($html, $before);
    }

    return $html;
}

function nvweb_ocean_blocks_render($block, $vars)
{
    global $current;
    global $theme;
    global $website;

    $out = array();
    switch($block->type)
    {
        case 'sidebar-text-content':
            $out[] = '<h5 style="font-style: italic;">'.$block->dictionary[$current['lang']]['title'].'</h5>';
            $out[] = '<span>'.$block->trigger['trigger-content'][$current['lang']].'</span>';

            $trigger_html = '<p class="button-read" style=" margin-top: 0px; ">'.$theme->t('read_more').'</p>';
            if(!empty($block->action['action-type']))
                $out[] = nvweb_blocks_render_action($block->action, $trigger_html, $current['lang']);
            break;

        case 'sidebar-quote':
            $quotes_open = "img/quotes1.png";
            $quotes_close = "img/quotes2.png";

            if($website->theme_options->style=='black')
            {
                $quotes_open = "img/colors/black-quotes1.png";
                $quotes_close = "img/colors/black-quotes2.png";
            }

            $out[] = '<div class="block-quote-right">';
            $out[] = '<span>';
            $out[] = '<img src="'.$quotes_open.'" width="51" height="37" align="bottom"/>';
            $out[] = strip_tags($block->trigger['trigger-content'][$current['lang']]);
            $out[] = '<img src="'.$quotes_close.'" width="51" height="37" align="top"/>';
            $out[] = '</span>';
            $out[] = '</div>';
            break;
    }

    return implode("\n", $out);
}

?>