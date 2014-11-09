<?php
function run()
{
	global $website;
	global $layout;
	
	switch(@$_REQUEST['act'])
	{
		default:
			$out = about_layout();
	}
	
	return $out;
}

function about_layout()
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$current_version = update::latest_installed();
	
	$navibars->title(t(215, 'About'));
	
	$navibars->form();		
	$navibars->add_tab('Navigate CMS');
	
	$navibars->add_tab_content_row(array(	'<label>'.t(216, 'Created by').'</label>',
											'<a href="http://www.naviwebs.com" target="_blank">Naviwebs</a>' ));								

	$navibars->add_tab_content_row(array(	'<label>'.t(220, 'Version').'</label>',
											'<span>'.$current_version->version.' r'.$current_version->revision.'</span>' ));

	$navibars->add_tab_content_row(array(	'<label>'.t(378, 'License').'</label>',
											'<a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPL v2</a>' ));
											
	$navibars->add_tab_content_row(array(	'<label>'.t(219, 'Copyright').'</label>',
											'<a href="http://www.naviwebs.com" target="_blank">&copy; 2010 - '.date('Y').', Naviwebs.com</a>' ));

	$navibars->add_tab(t(218, 'Third party libraries'));	
	
	$navibars->add_tab_content_row(array(	'<label>'.t(218, 'Third party libraries').'</label>',
											'<a href="http://tinymce.moxiecode.com/" target="_blank">TinyMCE WYSIWYG Javascript editor 3.5.10</a><br />'));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.cirkuit.net/projects/tinymce/cirkuitSkin/" target="_blank">TinyMCE Cirkuit skin v0.5</a><br />' ));
											
	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="https://github.com/badsyntax/tinymce-custom-inlinepopups" target="_blank">TinyMCE jQuery UI inline popups (2011/06/14)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/tinymce-plugins/codemagic" target="_blank">TinyMCE CodeMagic plugin (2013/07/20)</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://code.google.com/p/imgmap/" target="_blank">TinyMCE imgmap plugin v1.08</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
   											'<a href="http://www.assembla.com/spaces/lorem-ipsum" target="_blank">TinyMCE LoremIpsum plugin v0.12</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
   											'<a href="https://github.com/claviska/tinymce-table-dropdown" target="_blank">Table Dropdown plugin for TinyMCE (31 Jan 2012)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="http://code.google.com/p/tinymce-pre-plugin/" target="_blank">TinyMCE pre plugin r3 (17 Nov 2010)</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.jquery.com" target="_blank">jQuery v1.11.1 + jQuery Migrate v1.2.1</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://www.jqueryui.com" target="_blank">jQuery UI v1.11.2</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://fortawesome.github.io/Font-Awesome/" target="_blank">Font Awesome v4.20</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.trirand.com/blog/" target="_blank">jqGrid v4.6</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://stanlemon.net/pages/jgrowl" target="_blank">jGrowl v1.2.12</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="http://ivaynberg.github.com/select2" target="_blank">Select2 v3.4.8</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="http://www.firephp.org" target="_blank">FirePHPCore Server Library 0.3</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="http://mind2soft.com/labs/jquery/multiselect/" target="_blank">jQuery UIx Multiselect v2.0RC</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.prokvk.com/en/kvatree-jquery-plugin.html" target="_blank">kvaTree v1.0</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.plupload.com/" target="_blank">Plupload v2.0.0</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://player.bitgravity.com" target="_blank">Bitgravity free video player v6</a><br />' ));
											
	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://mediaelementjs.com/" target="_blank">MediaElement.js v2.11.2</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/pisi/Longclick" target="_blank">jQuery Long Click v0.3.2 (22-Jun-2010)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://plugins.jquery.com/project/query-object" target="_blank">jQuery.query v2.1.8 (22-Jun-2010)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://code.google.com/p/jautochecklist/" target="_blank">jAutochecklist v1.12</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://pupunzi.open-lab.com/mb-jquery-components/jquery-mb-extruder/" target="_blank">jQuery mb.extruder v2.5</a><br />' ));
											
	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.flotcharts.org" target="_blank">Flot (Attractive Javascript plotting for jQuery) v0.7</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="https://github.com/ludo/jquery-treetable" target="_blank">jQuery treeTable plugin v2.3.0</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/isocra/TableDnD" target="_blank">jQuery Table DnD plugin v0.7</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/mathiasbynens/jquery-noselect" target="_blank">jQuery noSelect plugin v51bac1d397 (2012-01-11)</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://code.google.com/p/jquery-dialogextend/" target="_blank">jQuery Dialog Extend plugin v2.0.2</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',											
											'<a href="http://codemirror.net" target="_blank">CodeMirror source code editor v4.0</a><br />'));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="https://code.google.com/a/apache-extras.org/p/phpmailer/" target="_blank">PHP Mailer v5.2.2</a><br />'));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://craigsworks.com/projects/qtip2/" target="_blank">qTip2 (14 Dec 2012)</a><br />'));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://phlymail.com/en/downloads/idna-convert.html" target="_blank">Net_IDNA v0.8.0</a><br />'));
											
	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://xoxco.com/clickable/jquery-tags-input" target="_blank">jQuery Tags Input v1.2.2</a><br />'));											

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://code.google.com/p/cssmin/" target="_blank">CssMin v3.0.1</a><br />'));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.verot.net/php_class_upload.htm" target="_blank">class.upload v0.32</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="http://www.framework2.com.ar/dzone/forceUTF8-es/" target="_blank">Encoding UTF8 Class (by Sebastián Grignoli)</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="https://github.com/weixiyen/jquery-filedrop" target="_blank">jQuery FileDrop v0.1.5 (2011/10/03)</a><br />' ));	

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://trentrichardson.com/examples/timepicker/" target="_blank">jQuery Timepicker Addon v1.4.2</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="https://github.com/tzuryby/jquery.hotkeys" target="_blank">jQuery HotKeys v2d51e3a (May 20 2012)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="https://github.com/DrPheltRight/jquery-caret" target="_blank">jQuery Caret v20803a7a16 (Sep 23 2011)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
                                            '<a href="https://github.com/tbasse/jquery-truncate" target="_blank">jQuery Truncate Text Plugin v18fdc9195c (Apr 03 2013)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://www.fyneworks.com/jquery/star-rating/" target="_blank">jQuery Star Rating Plugin v3.13</a><br />' ));									

    $navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="https://github.com/yatt/jquery.base64/" target="_blank">jQuery.base64 v2013.03.26</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://www.eyecon.ro/colorpicker/#about" target="_blank">jQuery Color Picker v23.05.2009</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://code.google.com/p/ezcookie/" target="_blank">jQuery ezCookie v0.7.01</a><br />' ));

	$navibars->add_tab(t(29, 'Images'));	
	
	$navibars->add_tab_content_row(array(	'<label>'.t(29, 'Images').'</label>',
											'<a href="http://www.famfamfam.com/lab/icons/silk/" target="_blank">famfamfam Silk Icons 1.3 (Mark James)</a><br />'));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://damieng.com/creative/icons/silk-companion-1-icons" target="_blank">Silk Companion I (Damien Guard)</a><br />' ));
	
	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.cagintranet.com/archive/download-famfamfam-silk-companion-2-icon-pack/" target="_blank">Silk Companion II (Chris Cagle)</a><br />' ));
	
	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',												
											'<a href="http://www.webdesignerdepot.com/2009/07/200-free-exclusive-vector-icons-primo/" target="_blank">Primo Icons</a><br />' ));

	$navibars->add_tab_content_row(array(	'<label>&nbsp;</label>',
											'<a href="http://fontawesome.io" target="_blank">Font Awesome by Dave Gandy - http://fontawesome.io</a><br />' ));

    $navibars->add_tab(t(526, 'Translations'));

    $navibars->add_tab_content_row(array(	'<label>English</label>',
        '<a href="http://www.navigatecms.com">Navigate CMS</a>'));

    $navibars->add_tab_content_row(array(	'<label>Español</label>',
        '<a href="mailto:info@naviwebs.com">Marc Lobato (naviwebs.com)</a><br />' ));

    $navibars->add_tab_content_row(array(	'<label>Deutsch</label>',
        '<a href="http://www.lingudora.com" target="_blank">Dominik Hlusiak (lingudora.com)</a><br />' ));

    return $navibars->generate();
}


?>