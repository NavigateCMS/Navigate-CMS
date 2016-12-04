tinymce.PluginManager.add('nv_rollups', function(editor, url)
{
	 editor.addMenuItem(
		 'unlink',
		 {
			 text: tinymce.util.I18n.translate("Remove link"),
             icon: 'unlink',
             cmd: 'Unlink'
		 }
	 );

	 editor.addButton(
		'nv_rollup_links',
		{
			 type   : 'SplitButton',
             icon   : 'link',
			 stateSelector: 'a',
             title  : tinymce.util.I18n.translate("Insert\/edit link"),
			 onclick: function() { editor.plugins.nv_link.showDialog(); },
             menu   : [
				 editor.menuItems['nv_link'], // use special navigate links search plugin
                 editor.menuItems['unlink'],
                 editor.menuItems['anchor']
			 ]
		}
	 );
	
	 editor.addMenuItem(
		 'nonbreaking',
		 {
			 text: tinymce.util.I18n.translate("Nonbreaking space"),
             icon: 'nonbreaking',
             cmd: 'Nonbreaking'
		 }
	 );


	editor.addMenuItem(
		 'loremipsum',
		 {
			 text: "Lorem ipsum",
             image: url + '/../loremipsum/img/loremipsum.gif',
             cmd: 'mceLoremIpsum'
		 }
	 );

     editor.addButton(
		'nv_rollup_special_char',
		{
			 type   : 'MenuButton',
             icon   : 'nonbreaking',
             title  : tinymce.util.I18n.translate("Special character"),
             menu   : [
				 editor.menuItems['subscript'],
                 editor.menuItems['superscript'],
				 editor.menuItems['fontawesome'],
                 editor.menuItems['charmap'],
                 editor.menuItems['loremipsum'],
                 editor.menuItems['nonbreaking'],
			  	 editor.menuItems['hr']
			 ]
		}
	 );

});