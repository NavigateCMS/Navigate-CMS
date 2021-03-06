tinymce.PluginManager.add('nv_link', function(editor)
{
	var last_search;
	var ctrl_input_href;

	function createLinkList(callback) {
		return function() {
			var linkList = editor.settings.link_list;

			if (typeof linkList == "string") {
				tinymce.util.XHR.send({
					url: linkList,
					success: function(text) {
						callback(tinymce.util.JSON.parse(text));
					}
				});
			} else if (typeof linkList == "function") {
				linkList(callback);
			} else {
				callback(linkList);
			}
		};
	}

	function buildListItems(inputList, itemCallback, startItems) {
		function appendItems(values, output) {
			output = output || [];

			tinymce.each(values, function(item) {
				var menuItem = {text: item.text || item.title};

				if (item.menu) {
					menuItem.menu = appendItems(item.menu);
				} else {
					menuItem.value = item.value;

					if (itemCallback) {
						itemCallback(menuItem);
					}
				}

				output.push(menuItem);
			});

			return output;
		}

		return appendItems(inputList, startItems || []);
	}

	function showDialog(linkList) 
	{
		var data = {}, selection = editor.selection, dom = editor.dom, selectedElm, anchorElm, initialText;
		var win, onlyText, textListCtrl, linkListCtrl, relListCtrl, targetListCtrl, classListCtrl, linkTitleCtrl, value;

		function linkListChangeHandler(e) 
		{
			var textCtrl = win.find('#text');

			if (!textCtrl.value() || (e.lastControl && textCtrl.value() == e.lastControl.text()))
			{
				textCtrl.value(e.control.text());
			}

			win.find('#href').value(e.control.value());
		}

		function buildAnchorListControl(url)
		{
			var anchorList = [];

			tinymce.each(editor.dom.select('a:not([href])'), function(anchor)
			{
				var id = anchor.name || anchor.id;

				if (id) {
					anchorList.push({
						text: id,
						value: '#' + id,
						selected: url.indexOf('#' + id) != -1
					});
				}
			});

			if (anchorList.length)
			{
				anchorList.unshift({text: 'None', value: ''});
				return {
					name: 'anchor',
					type: 'listbox',
					label: 'Anchors',
					values: anchorList,
					onselect: linkListChangeHandler
				};
			}
		}

		function select_option_to_be_focused(e)
		{
			var menu = $('ul.tinymce_nv_link_ajax_selector:last');

			if(e.keyCode == 38) // up
			{
				$(document).on('focusin', tinymce_focus_hack);
				$(ctrl_input_href).find('input').blur();
				$(menu).menu( "previous" );
				$(menu).focus();
				e.stopPropagation();
				e.preventDefault();
				return false;
			}
			else if(e.keyCode == 40) // down
			{
				$(document).on('focusin', tinymce_focus_hack);
				$(ctrl_input_href).find('input').blur();
				$(menu).menu( "next" );
				$(menu).focus();
				e.stopPropagation();
				e.preventDefault();
				return false;
			}
			else if(e.keyCode == 13) // enter
			{
				$(ctrl_input_href).find('input').blur();
				$(menu).menu("select");
				$(document).off('focusin', tinymce_focus_hack);
				$(ctrl_input_href).parents('.mce-floatpanel').find('input').eq(1).focus();
				e.stopPropagation();
				e.preventDefault();
				return false;
			}
			else if(e.keyCode == 9) // tab
			{
				$(document).off('focusin', tinymce_focus_hack);
				$(ctrl_input_href).parents('.mce-floatpanel').find('input').eq(1).focus();
				$(menu).remove();
				e.stopPropagation();
				e.preventDefault();
				return false;
			}

			return true;
		}

		function tinymce_focus_hack(event)
		{
			if ($(event.target).closest(".mce-window").length)
			{
				event.stopImmediatePropagation();
			}
		}

		function updateText()
		{
			var input = this;

			if(this.value() != "" && (this.value() != last_search   || $('ul.tinymce_nv_link_ajax_selector:visible').length < 1 ))
			{
				last_search = this.value();

				$.getJSON(
					'?fid=websites&act=json&oper=search_links',
					{
						text: this.value(),
						lang: $('#' + tinyMCE.activeEditor.id).parent().attr("lang")
					},
					function(data)
					{
						$('.tinymce_nv_link_ajax_selector').remove();
						var ul = $('<ul class="tinymce_nv_link_ajax_selector"></ul>');
						$(data).each(function()
						{
							$(ul).append('<li title="' + this.path + '">' + this.text + '</li>');
						});
	
						ctrl_input_href = input.$el;

						if(!$(ctrl_input_href).offset())
							return;

						var offsetElement = $(ctrl_input_href).parents('.mce-floatpanel');
						var offsetTop = 0;
						var offsetLeft = 0;

						if(offsetElement && typeof(offsetElement.offset) == "function")
						{
							if(offsetElement.offset())
							{
								offsetTop = offsetElement.offset().top;
								offsetLeft = offsetElement.offset().left;
							}
						}

						$(ctrl_input_href).parents('.mce-floatpanel').append(ul);
						$(ctrl_input_href).parents('.mce-floatpanel').find('ul.tinymce_nv_link_ajax_selector:last')
							.css({
								top: $(ctrl_input_href).offset().top + $(ctrl_input_href).height() - offsetTop,
								left: $(ctrl_input_href).offset().left - offsetLeft,
								width: $(ctrl_input_href).width() - 3
							})
							.on("keydown", function(e) { if(e.keyCode==9) return select_option_to_be_focused(e); })
							.menu({
								select: function( event, ui )
								{
									var li = ($(ui.item.get(0)));
									var controls = $(ctrl_input_href).parents('.mce-floatpanel').find('input');

									controls.eq(0).val(li.attr("title"));

									if(controls.eq(1).val()=="" || controls.eq(1).val() == last_search)
										controls.eq(1).val(li.text());

									$(li).parent().remove();
									$(document).off('focusin', tinymce_focus_hack);
								}
							});
					}
				);
			}

			/* why duplicate text?
			if (!initialText && data.text.length === 0 && onlyText)
			{
				this.parent().parent().find('#text')[0].value(this.value());
			}
			*/
		}

		function urlChange(e)
		{
			var meta = e.meta || {};

			if (linkListCtrl) 
			{
				linkListCtrl.value(editor.convertURL(this.value(), 'href'));
			}

			tinymce.each(e.meta, function(value, key) {
				win.find('#' + key).value(value);
			});

			if (!meta.text) {
				updateText.call(this);
			}
		}

		function isOnlyTextSelected(anchorElm)
		{
			var html = selection.getContent();

			// Partial html and not a fully selected anchor element
			if (/</.test(html) && (!/^<a [^>]+>[^<]+<\/a>$/.test(html) || html.indexOf('href=') == -1)) {
				return false;
			}

			if (anchorElm) {
				var nodes = anchorElm.childNodes, i;

				if (nodes.length === 0) {
					return false;
				}

				for (i = nodes.length - 1; i >= 0; i--) {
					if (nodes[i].nodeType != 3) {
						return false;
					}
				}
			}

			return true;
		}

		selectedElm = selection.getNode();
		anchorElm = dom.getParent(selectedElm, 'a[href]');
		onlyText = isOnlyTextSelected();

		data.text = initialText = anchorElm ? (anchorElm.innerText || anchorElm.textContent) : selection.getContent({format: 'text'});
		data.href = anchorElm ? dom.getAttrib(anchorElm, 'href') : '';

		if (anchorElm)
		{
			data.target = dom.getAttrib(anchorElm, 'target');
		} else if (editor.settings.default_link_target) 
		{
			data.target = editor.settings.default_link_target;
		}

		if ((value = dom.getAttrib(anchorElm, 'rel')))
		{
			data.rel = value;
		}

		if ((value = dom.getAttrib(anchorElm, 'class')))
		{
			data['class'] = value;
		}

		if ((value = dom.getAttrib(anchorElm, 'title')))
		{
			data.title = value;
		}

		if (onlyText)
		{
			textListCtrl =	{
				name: 'text',
				type: 'textbox',
				size: 40,
				label: 'Text to display',
				onchange: function() {
					data.text = this.value();
				}
			};
		}

		if (linkList) 
		{
			linkListCtrl = {
				type: 'listbox',
				label: 'Link list',
				values: buildListItems(
					linkList,
					function(item) {
						item.value = editor.convertURL(item.value || item.url, 'href');
					},
					[{text: 'None', value: ''}]
				),
				onselect: linkListChangeHandler,
				value: editor.convertURL(data.href, 'href'),
				onPostRender: function() {
					/*eslint consistent-this:0*/
					linkListCtrl = this;
				}
			};
		}

		if (editor.settings.target_list !== false)
		{
			if (!editor.settings.target_list) {
				editor.settings.target_list = [
					{text: 'None', value: ''},
					{text: 'New window', value: '_blank'}
				];
			}

			targetListCtrl = {
				name: 'target',
				type: 'listbox',
				label: 'Target',
				values: buildListItems(editor.settings.target_list)
			};
		}

		if (editor.settings.rel_list)
		{
			relListCtrl = {
				name: 'rel',
				type: 'listbox',
				label: 'Rel',
				values: buildListItems(editor.settings.rel_list)
			};
		}

		if (editor.settings.link_class_list)
		{
			classListCtrl = {
				name: 'class',
				type: 'listbox',
				label: 'Class',
				values: buildListItems(
					editor.settings.link_class_list,
					function(item) {
						if (item.value) {
							item.textStyle = function() {
								return editor.formatter.getCssText({inline: 'a', classes: [item.value]});
							};
						}
					}
				)
			};
		}

		if (editor.settings.link_title !== false)
		{
			linkTitleCtrl = {
				name: 'title',
				type: 'textbox',
				label: 'Title',
				value: data.title
			};
		}

		// http://makina-corpus.com/blog/metier/2016/how-to-create-a-custom-dialog-in-tinymce-4
		win = editor.windowManager.open({
			title: 'Insert link',
			data: data,
			body: [
				{
					name: 'href',
					type: 'filepicker',
					filetype: 'file',
					size: 40,
					autofocus: true,
					label: 'Url',
					onchange: urlChange,
					onkeyup: updateText,
					onkeydown: select_option_to_be_focused
				},
				textListCtrl,
				linkTitleCtrl,
				buildAnchorListControl(data.href),
				linkListCtrl,
				relListCtrl,
				targetListCtrl,
				classListCtrl
			],
			onSubmit: function(e) 
			{
				/*eslint dot-notation: 0*/
				var href;

				data = tinymce.extend(data, e.data);
				href = data.href;

				// Delay confirm since onSubmit will move focus
				function delayedConfirm(message, callback) {
					var rng = editor.selection.getRng();

					tinymce.util.Delay.setEditorTimeout(editor, function() {
						editor.windowManager.confirm(message, function(state) {
							editor.selection.setRng(rng);
							callback(state);
						});
					});
				}

				function insertLink() 
				{
					var linkAttrs = {
						href: href,
						target: data.target ? data.target : null,
						rel: data.rel ? data.rel : null,
						"class": data["class"] ? data["class"] : null,
						title: data.title ? data.title : null
					};

					if (anchorElm) 
					{
						editor.focus();

						if (onlyText && data.text != initialText) 
						{
							if ("innerText" in anchorElm) {
								anchorElm.innerText = data.text;
							} else {
								anchorElm.textContent = data.text;
							}
						}

						dom.setAttribs(anchorElm, linkAttrs);

						selection.select(anchorElm);
						editor.undoManager.add();
					}
					else
					{
						if (onlyText) {
							editor.insertContent(dom.createHTML('a', linkAttrs, dom.encode(data.text)));
						} else {
							editor.execCommand('mceInsertLink', false, linkAttrs);
						}
					}
				}

				if (!href) {
					editor.execCommand('unlink');
					return;
				}

				// Is email and not //user@domain.com
				if (href.indexOf('@') > 0 && href.indexOf('//') == -1 && href.indexOf('mailto:') == -1) {
					delayedConfirm(
						'The URL you entered seems to be an email address. Do you want to add the required mailto: prefix?',
						function(state) {
							if (state) {
								href = 'mailto:' + href;
							}

							insertLink();
						}
					);

					return;
				}

				// Is not protocol prefixed
				if ((editor.settings.link_assume_external_targets && !/^\w+:/i.test(href)) ||
					(!editor.settings.link_assume_external_targets && /^\s*www[\.|\d\.]/i.test(href))) {
					delayedConfirm(
						'The URL you entered seems to be an external link. Do you want to add the required http:// prefix?',
						function(state) {
							if (state) {
								href = 'http://' + href;
							}

							insertLink();
						}
					);

					return;
				}

				insertLink();
			}
		});

		// add custom URL button
		$(win['$el']).find('input:first').width("243px");

		$(win['$el']).find('.mce-window-body:first')
			.append('<a id="nv_link_find" class="tinymce-dialog-button"></a>');

		$('#nv_link_find')
			.html('<i class="fa fa-sitemap"></i>')
			.css({
				position: "absolute",
				right: "20px",
				top: "15px"
			})
			.on("click", function()
			{
				$('#mce-modal-block').data('zindex-or', $('#mce-modal-block').css('z-index'));
				$('.mce-window').data('zindex-or', $('.mce-window').css('z-index'));

				$('#mce-modal-block').css('z-index', 101);
				$('.mce-window').css('z-index', 100);

				var restore_mce_window = function()
				{
					$("#nv_link_dialog").dialog( "destroy" );
					$('#mce-modal-block').css('z-index', $('#mce-modal-block').data('zindex-or'));
					$('.mce-window').css('z-index', $('.mce-window').data('zindex-or'));
				};

				$('#nv_link_dialog').removeClass("hidden");
				$('#nv_link_dialog').dialog({
					title: $('#nv_link_dialog').attr("title"),
					modal: true,
					width: 620,
					height: 400,
					buttons: [
						{
							text: "Ok",
							click: function(event, ui)
							{
								// check if there is any path selected
								if(!$("#nv_link_dialog_dynamic_path").hasClass("hidden"))
								{
									$('.mce-window').find('input:first').val($("#nv_link_dialog_dynamic_path").text());
									if($("#nv_link_dialog_replace_text").is(":checked"))
										$('.mce-window').find('input').eq(1).val($("#nv_link_dialog_title").val());
								}

								$('#nv_link_dialog').addClass("hidden");
								restore_mce_window();
							}
						},
						{
							text: "Cancel",
							click: function(event, ui)
							{
								$('#nv_link_dialog').addClass("hidden");
								restore_mce_window();
							}
						}
					],
					close: function(event, ui)
					{
						$('#nv_link_dialog').addClass("hidden");
						restore_mce_window();
					}
				});

				// make sure the dynamic link dialog is in front of any other one
				$('#nv_link_dialog').parent().css("z-index", $('.mce-window').data('zindex-or') + 1);
			});

	}

	editor.addButton('link', {
		icon: 'link',
		tooltip: 'Insert/edit link',
		shortcut: 'Meta+K',
		onclick: createLinkList(showDialog),
		stateSelector: 'a[href]'
	});

	editor.addButton('unlink', {
		icon: 'unlink',
		tooltip: 'Remove link',
		cmd: 'unlink',
		stateSelector: 'a[href]'
	});

	editor.addShortcut('Meta+K', '', createLinkList(showDialog));
	editor.addCommand('mceLink', createLinkList(showDialog));

	this.showDialog = showDialog;

	editor.addMenuItem('nv_link', {
		icon: 'link',
		text: 'Insert/edit link',
		shortcut: 'Meta+K',
		onclick: createLinkList(showDialog),
		stateSelector: 'a[href]',
		context: 'insert',
		prependToContext: true
	});
});