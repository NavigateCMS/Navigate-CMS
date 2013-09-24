/* jQuery plugin : jAutochecklist
 @Version: 1.12
 @Desctrition: Create a list of checkbox with autocomplete
 @Website: https://code.google.com/p/jautochecklist/
 @Licence: MIT
 
 Copyright (c) 2007 John Resig, http://jquery.com/
 
 Copyright (C) 2013 Thanh Trung NGUYEN
 Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
(function($, document, window, undefined){
    'use strict';

    var pluginName = 'jAutochecklist';
    //detect dragging
    var dragging = false;
    var drag_memory;
    var dragging_state;
    /*
     //detect mobile. http://detectmobilebrowsers.com/
     var isMobile = (function(a){
     return (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(a) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0, 4)))
     })(navigator.userAgent || navigator.vendor || window.opera);
     */

    //if format isn't implemented
    if (!String.prototype.format){
        String.prototype.format = function(){
            var args = arguments;
            return this.replace(/{(\d+)}/g, function(match, number){
                return args[number] !== undefined ? args[number] : match;
            });
        };
    }

    //drag handling
    $(window).on('mouseup.' + pluginName, function(){
        dragging = false;
    });

    var fn = {
        init: function(options){
            //default setting
            var config = $.extend(true, {
                width: 200, //width of the wrapper
                listWidth: null, //width of the list
                listMaxHeight: null, //max height of the list
                checkbox: false, //show or hide checkbox
                multiple: true, //checkbox or radio
                absolutePosition: false, //use absolute position instead of inline
                dynamicPosition: false, //support dynamic position for list at the bottom of page
                fallback: false, //fallback support for values
                firstItemSelectAll: false, //enable checkall on first item
                showValue: false, //show value instead of text
                allowDeselectSingleList: true, //allow deselection on a single list
                popup: true, //show or hide popup
                popupLogoAsValue: false, //show logo instead of item value/text
                popupMaxItem: 10, //maximum number of item on popup
                popupSizeDelta: 100, //this will add to the popup width
                textAllSelected: 'All', //text when all selected
                textEmpty: 'Please select...', //the default text
                textMoreItem: 'and {0} more...', //text on popup when there are more items, {0} will be replaced with a number
                textNoResult: 'No result found...', //text no result
                textSearch: 'Type something...', //the default text
                selectorGroup: 'group', //the class selector of a group
                selectorChild: 'child', //the class selector of a child
                groupType: 0, //global setting of the type of a group
                //0 => default. The parent item will be selected if all of the children items are selected
                //1 => The parent item will be selected if at least one child item is selected
                //2 => The parent item acts independantly from the children items (exclusive)
                onClose: null, //func(values)  //values=list of selected values
                onOpen: null, //func(values) //values=list of selected values
                onItemClick: null, //func(value, li, valBefore, valAfter)
                //value		= the selected value
                //li 		= the selected list item object
                //valBefore = list of values before selection
                //valAfter 	= list of values after selection
                remote: {//fetch data from remote source
                    cache: true, //whether to cache found data (should be disable when loadMoreOnScroll is enable)
                    delay: 0, //the delay (in ms) before setting query to the remote source to reduce charge
                    loadMoreOnScroll: false, //when scroll down, next data will be loaded (will conflict with cache)
                    minLength: 0, //the minimum length of the text
                    source: null, //source of data to fetch if fnQuery isn't defined
                    fnPredict: null, //func(text, input)         custom function that handle suggestion
                    //text  = the typing search text
                    //input = the underlying input object
                    fnQuery: null      //func(obj, text, offset, callback)   custom function that handle query
                    //obj       = the current jQuery list object
                    //text      = the typing search text
                    //offset    = the offset position of the result
                    //callback  = callback function used to build the list
                }
            },
            options);

            //if is not multiple, disable checkall
            if (!config.multiple){
                config.popup = false;
                config.firstItemSelectAll = false;
            }

            return this.each(function(){
                var $this = $(this);
                var data = $this.data(pluginName);
                var id = this.id;
                var className = this.className;

                //if isn't a list or a select
                var isSelect = $this.is('select');
                if (!$this.is('ul') && !isSelect)
                    return;

                //make sure the element is not initialized twice
                if (data)
                    return;

                //clone the config setting
                var settings = $.extend(true, {
                }, config);

                if (isSelect){
                    settings.multiple = this.multiple;
                    if (!settings.multiple){
                        settings.popup = false;
                        settings.firstItemSelectAll = false;
                    }
                }

                //data passed by attribute json will override the settings
                var json = $this.data('json');
                if (json){
                    //json = $.parseJSON(json); //it's already an object
                    settings = $.extend(true, settings, json);
                }

                //create a div wrapper
                var wrapper = $('<div>').attr({
                    'class': pluginName + '_wrapper',
                    'tabindex': 0
                }).width(settings.width).append(
                ('<div class="{0}_popup"></div>'
                + '<div class="{0}_dropdown_wrapper">'
                + '<div class="{0}_arrow"><div></div></div>'
                + '<div class="{0}_dropdown"><div class="{0}_result"></div><input class="{0}_prediction" /><input class="{0}_input" placeholder="{1}" /></div></div>'
                + '<ul class="{0}_list"></ul>').format(pluginName, settings.textSearch)
                );

                if (className)
                    wrapper.addClass(className);
                if (id)
                    wrapper.attr('id', pluginName + '_wrapper_' + id);

                //add a signature of this plugin
                if (!$this.hasClass(pluginName))
                    $this.addClass(pluginName);

                //the popup should have 100px more than the wrapper width by default
                var popup = wrapper.find('div.' + pluginName + '_popup').width(settings.width + settings.popupSizeDelta - 12).css({
                    marginLeft: -settings.popupSizeDelta / 2
                });
                var dropdown = wrapper.find('div.' + pluginName + '_dropdown');
                var result = wrapper.find('div.' + pluginName + '_result');
                var input = wrapper.find('input.' + pluginName + '_input');
                var prediction = wrapper.find('input.' + pluginName + '_prediction');
                var arrow = wrapper.find('div.' + pluginName + '_arrow');
                var ul = wrapper.find('ul.' + pluginName + '_list');

                //manual size of the list
                if (settings.listWidth)
                    ul.width(settings.listWidth * 1 - 2);   //minus 2px border
                if (settings.listMaxHeight)
                    ul.css({
                        maxHeight: settings.listMaxHeight + 'px'
                    });
                if (!settings.popup){
                    popup.remove();
                    popup = null;
                }

                //list item
                var name;
                if ($this.is('ul')){
                    json = fn._buildFromUl($this, settings);
                    name = $this.data('name');
                }
                else {
                    json = fn._buildFromSelect($this, settings);
                    name = this.name;
                }

                var li = fn._buildItemFromJSON(json, settings, name);
                var tmp = fn._insertList(ul, li, settings, false, false);

                //register elements
                var elements = {
                    popup: popup,
                    wrapper: wrapper,
                    dropdown: dropdown,
                    result: result,
                    input: input,
                    prediction: prediction,
                    arrow: arrow,
                    list: ul,
                    listItem: {
                        li: tmp.li,
                        checkbox: tmp.checkbox
                    },
                    selectAll: tmp.selectAll
                };

                data = {
                    elements: elements,
                    settings: settings
                };

                $this.data(pluginName, data);

                //insert the checklist into the DOM, right after the main list
                $this.after(wrapper);
                //hide the original element
                $this.hide();

                fn._registerEvent($this);
                //update selected element
                fn._update($this);
                fn._postProcessing($this);
            });
        },
        destroy: function(){
            return this.each(function(){
                var $this = $(this);
                var data = $this.data(pluginName);
                if (!data)
                    return;

                $(document).add(window).off('.' + pluginName);
                data.elements.wrapper.remove();

                var original;
                if ($this.is('ul')){
                    //get the list of original item
                    original = $this.children('li');
                    //update original
                    data.elements.listItem.li.each(function(k, v){
                        var li = $(this);
                        original.eq(k).data('selected', li.hasClass('selected'));
                    });
                }
                else {
                    original = $this.find('option,optgroup');
                    data.elements.listItem.li.each(function(k, v){
                        var li = $(this);
                        //we use .attr to force add the attribute to the DOM
                        original.eq(k).attr('selected', li.hasClass('selected'));
                    });
                }

                $this.removeData(pluginName).show();
            });
        },
        selectAll: function(){
            return this.each(function(){
                fn._selectAll($(this), true);
            });
        },
        deselectAll: function(){
            return this.each(function(){
                fn._selectAll($(this), false);
            });
        },
        //open the list, can only open one a time
        open: function(){
            return this.each(function(){
                fn._open($(this));
            });
        },
        //close the list
        close: function(){
            return this.each(function(){
                fn._close($(this));
            });
        },
        //update the result box basing on the selected element
        update: function(){
            return this.each(function(){
                fn._update($(this));
            });
        },
        //count selected item, can only count one instance
        count: function(){
            return fn._count($(this));
        },
        //get the values, can only get value of one instance
        get: function(){
            return fn._get($(this));
        },
        //get all values, including non selected values
        getAll: function(){
            return fn._getAll($(this));
        },
        //set the values
        set: function(vals, clearAll){
            if (clearAll === undefined)
                clearAll = false;

            //convert to string
            for (var i = 0; i < vals.length; i++)
                vals[i] = vals[i].toString();

            return this.each(function(){
                fn._set($(this), vals, clearAll);
            });
        },
        unset: function(vals){
            //convert to string
            for (var i = 0; i < vals.length; i++)
                vals[i] = vals[i].toString();

            return this.each(function(){
                fn._unset($(this), vals);
            });
        },
        //disable
        disable: function(){
            return this.each(function(){
                var data = $(this).data(pluginName);
                if (!data)
                    return;
                data.elements.wrapper.addClass(pluginName + '_disabled');
            });
        },
        //enable
        enable: function(){
            return this.each(function(){
                var data = $(this).data(pluginName);
                if (!data)
                    return;
                data.elements.wrapper.removeClass(pluginName + '_disabled');
            });
        },
        //change the settings
        settings: function(json){
            return this.each(function(){
                var $this = $(this);
                var data = $this.data(pluginName);
                if (!data)
                    return;
                data.settings = $.extend(true, data.settings, json);
                $this.data(pluginName, data);
            });
        },
        //refresh the list memory
        refresh: function(){
            return this.each(function(){
                var $this = $(this);
                var data = $this.data(pluginName);
                if (!data)
                    return;

                var selectAll;
                if (settings.firstItemSelectAll)
                    selectAll = ul.children(':first').addClass(pluginName + '_checkall');

                var li = ul.children();
                data.elements.listItem = {
                    li: li,
                    checkbox: li.children('input.' + pluginName + '_listItem_input')
                };
                data.elements.selectAll = selectAll;

                $this.data(pluginName, data);
            });
        },
        /**
         * rebuild the list from JSON
         * @param ARRAY json an array of JSON
         * @param BOOLEAN showNoResult whether to show text "No result found" if nothing found
         * @param BOOLEAN isAdd if true, data will be add to the end of the list instead of replacing the current list
         * @returns OBJECT 
         */
        buildFromJSON: function(json, showNoResult, isAdd){
            return this.each(function(){
                fn._buildFromJSON($(this), json, showNoResult, isAdd);
            });
        },
        /**
         * Intentionally release the drag status to prevent some bugs
         */
        releaseDrag: function(){
            dragging = false;
        },
        /*
         *  PRIVATE
         */
        _buildFromJSON: function(obj, json, showNoResult, isAdd){
            var data = obj.data(pluginName);
            if (!data)
                return;
            var elements = data.elements;
            var settings = data.settings;
            var li = fn._buildItemFromJSON(json || [], settings, obj.data('name'));
            var tmp = fn._insertList(elements.list, li, settings, showNoResult, isAdd, elements.mobile_popup);

            data.elements.listItem = {
                li: tmp.li,
                checkbox: tmp.checkbox
            };

            data.elements.selectAll = tmp.selectAll;
            obj.data(pluginName, data);
            fn._update(obj);
            fn._postProcessing(obj);
        },
        _registerEvent: function(self){
            var data = self.data(pluginName);
            var settings = data.settings;
            var elements = data.elements;
            var wrapper = elements.wrapper;
            var dropdown = elements.dropdown;
            var input = elements.input;
            var prediction = elements.prediction;
            var result = elements.result;
            var ul = elements.list;
            var li = elements.listItem.li;
            var checkbox = elements.listItem.checkbox;
            var selectAll = elements.selectAll;
            var popup = elements.popup;
            var shift_on = false;
            var timer;

            //searching
            input.on('keydown', function(e){
                var v = prediction.val();
                //if TAB and underlying input is different
                if (e.keyCode === 9 && v && this.value !== v){
                    this.value = v;
                    return false;
                }
                //if escape
                if (e.keyCode === 27){
                    //if use absolute position simulate escape key on dummy element
                    if (settings.absolutePosition){
                        var e = $.Event("keydown");
                        e.keyCode = 27;
                        $('.' + pluginName + '_dummy').trigger(e);
                    }
                    fn._close(self);
                }
            })
            .on('keyup.' + pluginName, function(e){
                var $this = $(this);
                var val = this.value;
                var noResult = true;
                var remote = settings.remote;

                ul.children('li.' + pluginName + '_noresult').remove();
                prediction.val(val);


                //if remote, replace the current list with new data
                if (remote.source || remote.fnQuery){
                    var cache = $this.data('remote');

                    //if cache not exist, fetch from remote source
                    if (!cache || cache[val] === undefined){
                        //clear the previous timer
                        clearTimeout(timer);
                        //set a timer to reduce server charge
                        timer = setTimeout(function(){
                            //predict the next word
                            if (remote.fnPredict)
                                remote.fnPredict(val, prediction, fn._predict);
                            //user defined func
                            fn._fetchData(self, val, 0);
                        }, remote.delay);
                        return; //break the code here
                    }
                    else {  //load from cache
                        var json = cache[val];
                        if (json && json.length){
                            fn._buildFromJSON(self, json, true, false);
                            noResult = false;
                        }
                    }
                }
                else {  //using local source

                    if (val === ''){
                        li.show();
                        noResult = false;
                    }
                    else {
                        if (selectAll)
                            selectAll.hide();

                        var regex = new RegExp(val, 'i');
                        li.each(function(){
                            var $this = $(this);
                            var text = $this.text();
                            if (regex.test(text)){
                                $this.show();
                                noResult = false;
                            }
                            else
                                $this.hide();
                        });
                    }

                    //predictive search if has at least some result
                    if (val === '')
                        prediction.val(null);
                    else if (!noResult){
                        var text = [];
                        //we already know that each li contain our value, search for the next word after the value
                        li.filter(':visible').each(function(){
                            text.push($(this).text());
                        });

                        fn._predict(val, prediction, text);
                    }
                }

                //if no result
                if (noResult){
                    prediction.val(null);
                    ul.append('<li class="{0}_noresult">{1}</li>'.format(pluginName, settings.textNoResult));
                }

            })
            //stop propagoation to the wrapper
            .on('focusin.' + pluginName, function(e){
                e.stopPropagation();
            });

            //show popup
            if (popup){
                dropdown.on('mouseover.' + pluginName, function(){
                    clearTimeout(popup.data('timeout'));
                    //if have at least one element
                    if (fn._count(self) && !wrapper.hasClass(pluginName + '_disabled'))
                        popup.fadeIn();
                });

                popup.on('mouseover.' + pluginName, function(){
                    clearTimeout(popup.data('timeout'));
                });

                //if list is not opened, hide popup if mouse leave
                dropdown.add(popup).on('mouseout.' + pluginName, function(){
                    if (ul.is(':hidden')){
                        var timeout = setTimeout(function(){
                            popup.hide();
                        }, 200);
                        popup.data('timeout', timeout);
                    }
                });

                //on popup item click, remove the checkbox
                popup.on('click.' + pluginName, 'div', function(){
                    var id = this.className.replace(pluginName + '_popup_item_', '');
                    ul.find('input.' + pluginName + '_input' + id).parent('li').trigger('mousedown').trigger('mouseup');
                    fn._update(self);
                });
            }

            //on checkbox click prevent default behaviour
            ul.on('click.' + pluginName, 'input.' + pluginName + '_listItem_input', function(e){
                e.preventDefault();
            })
            //on item mouse down
            .on('mousedown.' + pluginName, 'li.' + pluginName + '_listItem', function(e){
                var $this = $(this);

                //if locked
                if ($this.hasClass('locked'))
                    return false;

                //on select text, disable click
                var text;
                if (window.getSelection)
                    text = window.getSelection().toString();
                else if (document.getSelection)
                    text = document.getSelection();
                else if (document.selection)
                    text = document.selection.createRange().text;

                if (text)
                    return false;

                //disable propagation for live event
                e.stopPropagation();

                var checked = $this.hasClass('selected');

                //if select list and prevent deselect
                if (checked && !settings.multiple && !settings.allowDeselectSingleList){
                    //delay hack, make sure it's executed after onfocus
                    setTimeout(function(){
                        fn._close(self);
                    });
                    return false;
                }

                //reset the drag memory
                if (!dragging)
                    drag_memory = [];

                //add to the drag memory to notify that this element has been processed
                drag_memory.push($this);

                //if is dragging and the checkbox has same state, exit
                if (dragging && dragging_state === checked)
                    return false;

                var input = $this.children('input.' + pluginName + '_listItem_input');
                var currentSelected = checkbox.filter(':checked');
                var valBefore = fn._getValFromCheckboxes(currentSelected);

                //reverse the checkbox status if the event is not from the checkbox
                checked = !checked;

                //checkall
                if ($this.hasClass(pluginName + '_checkall')){
                    //call user defined function click
                    if (settings.onItemClick){
                        //if return false, prevent the selection
                        if (settings.onItemClick(null, $this, valBefore, fn._getAll(self), checked) === false){
                            dragging = false;
                            return false;
                        }
                    }

                    //get the initial
                    fn._selectAll(self, checked);
                }
                else {  //simple checkbox
                    //if is label do nothing if type radio
                    if (!settings.multiple && $this.hasClass(settings.selectorGroup))
                        return false;
                    //if a group is checked and is not exclusive, get the list of children
                    var checkboxes = [];

                    if ($this.hasClass(settings.selectorGroup) && !$this.hasClass('groupType2')){
                        var level = fn._getLevel($this);
                        var children = fn._getChildren($this, level, settings.selectorChild);
                        for (var i = 0; i < children.length; i++)
                            checkboxes.push(children[i].children('input.' + pluginName + '_listItem_input'));
                    }

                    checkboxes.push(input);
                    for (var i = 0; i < checkboxes.length; i++) {
                        //if is already checked, remove this item from the list
                        if (checkboxes[i].prop('checked') === checked)
                            checkboxes[i] = null;
                        else
                            checkboxes[i].prop('checked', checked);
                    }

                    //call user defined function click
                    if (settings.onItemClick){
                        //if return false, revert to previous selection
                        if (settings.onItemClick(input.prop('value'), $this, valBefore, fn._get(self), checked) === false){
                            for (var i = 0; i < checkboxes.length; i++) {
                                if (checkboxes[i])
                                    checkboxes[i].prop('checked', !checked);
                            }

                            dragging = false;
                            return false;
                        }
                    }

                    fn._update(self);
                }

                //if is radio, close the list on click
                if (!settings.multiple){
                    //delay hack, make sure it's executed after onfocus
                    setTimeout(function(){
                        fn._close(self);
                    });
                }

                //start dragging handling, only the first clicked li can reach here
                if (!dragging){
                    dragging = true;
                    //the state of checkbox at the moment of dragging (the first checked)
                    dragging_state = checked;
                }
            })
            .on('mouseenter.' + pluginName, 'li.' + pluginName + '_listItem', function(){
                if (!dragging)
                    return;
                var $this = $(this);
                var i;
                var found = false;

                //do not click the item twice
                for (var i = 0; i < drag_memory.length; i++) {
                    if ($this.is(drag_memory[i])){
                        found = true;
                        break;
                    }
                }

                if (!found)
                    $(this).trigger('mousedown');
            })
            .on('click.' + pluginName, 'a', function(e){
                e.stopPropagation();
            })
            .on('mousedown.' + pluginName, 'a', function(e){
                e.stopPropagation();
            })
            .on('scroll.' + pluginName, function(){
                var remote = settings.remote;
                if (!remote.loadMoreOnScroll)
                    return;

                //load more only if the list reach its bottom
                var $this = $(this);
                if ($this.height() + 5 < ($this.get(0).scrollHeight - $this.scrollTop()))
                    return;

                var val = input.val();
                var offset = $this.children('li').length;
                fn._fetchData(self, val, offset);
            });

            //result click
            result.on('mousedown.' + pluginName, function(){
                if (ul.is(':hidden')){
                    fn._open(self);
                    //FF hack, need to add a delay
                    setTimeout(function(){
                        input.trigger('focus');
                    });
                }
                else
                    fn._close(self);
            });

            wrapper.on('focusin.' + pluginName, function(){
                if (ul.is(':hidden'))
                    fn._open(self);

                //as long as the wrapper has focus, focus on the input
                //IE hack
                setTimeout(function(){
                    input.trigger('focus');
                });
                li.filter('.over').removeClass('over');
            })
            //blur not triggered in FF
            .on('focusout.' + pluginName, function(){
                //need to add delay for activeElement to be set
                setTimeout(function(){
                    //close list if the active element isn't any child of the wrapper
                    if (!$(document.activeElement).closest(wrapper).length)
                        fn._close(self);
                }, 10);
            })
            .on('keydown.' + pluginName, function(e){
                var key = e.keyCode;
                var current = li.filter('.over');
                var next;

                //up/down
                if (key === 40 || key === 38){
                    //down
                    if (key === 40) //find the next over item
                        next = current.length ? current.last().next() : li.first();
                    else //up
                        next = current.length ? current.first().prev() : null;

                    //if has the next element
                    if (next && next.length){
                        //if shift is on, do not remove the current item
                        if (!shift_on){
                            current.removeClass('over');
                        }
                        next.addClass('over');

                        //scroll handling
                        if (key === 40 && next.position().top + next.height() > ul.height())
                            ul.scrollTop(ul.scrollTop() + 50);
                        else if (key === 38 && next.position().top < 0)
                            ul.scrollTop(ul.scrollTop() - 50);
                    }
                }
                //enter: do not submit form and select item
                else if (key === 13){
                    //if no selection, (de)select the fist visible item
                    if (!current.length){
                        li.filter(':visible').first().trigger('mousedown').trigger('mouseup');
                    }
                    else {
                        current.trigger('mousedown').trigger('mouseup');
                    }
                    //clear the input
                    input.val(null);
                    return false;
                }
                //shift
                else if (key === 16){
                    shift_on = true;
                }
            })
            .on('keyup.' + pluginName, function(e){
                var key = e.keyCode;
                if (key === 16)
                    shift_on = false;
            })
            .on('mouseup.' + pluginName, function(e){
                dragging = false;
                e.stopPropagation();
            })
            .on('mousedown.' + pluginName, function(e){
                e.stopPropagation();
            });
        },
        _fetchData: function(obj, val, offset){
            var data = obj.data(pluginName);
            var settings = data.settings;
            var remote = settings.remote;
            var input = data.elements.input;

            //if text length < minLength do nothing
            if (val.length < remote.minLength)
                return;

            if (remote.fnQuery)
                remote.fnQuery(obj, val, offset, fn._buildFromJSON);
            else {
                $.getJSON(remote.source, {
                    text: val,
                    offset: offset
                },
                function(json){
                    //convert to array if empty
                    if (!json)
                        json = [];
                    //build the list from a VALID json
                    fn._buildFromJSON(obj, json, true, offset > 0);

                    //cache only when offset=0
                    if (remote.cache && !offset){
                        var cache = input.data('remote') || [];
                        cache[val] = json;
                        input.data('remote', cache);
                    }
                });
            }
        },
        _postProcessing: function(obj){
            var data = obj.data(pluginName);
            var elements = data.elements;
            var wrapper = elements.wrapper;
            var ul = elements.list;

            //prevent tab stop
            ul.find('a,button,:input,object').attr('tabIndex', -1);

            //dynamic positioning
            var pos = wrapper.offset();
            var wrapperH = wrapper.height();
            var x = pos.left - $(window).scrollLeft();
            var y = pos.top - $(window).scrollTop() + wrapperH;
            var w = ul.width() + 2;   //with border
            var h = ul.height() + 2;   //with border
            var wW = $(window).width();
            var wH = $(window).height();
            //dynamic-x
            if (x + w > wW)
                ul.css({
                    left: -(wW - x)
                });
            //dynamic-y only when option is activated
            if (data.settings.dynamicPosition && y + h > wH)
                ul.css({
                    top: -h - wrapperH - 1
                });
        },
        _selectAll: function(obj, state){
            var data = obj.data(pluginName);
            if (!data)
                return;
            data.elements.listItem.checkbox.prop('checked', state);
            this._update(obj);
        },
        _update: function(obj){
            var data = obj.data(pluginName);
            if (!data)
                return;
            var settings = data.settings;
            var li = data.elements.listItem.li;
            var val = [];
            var html = '';
            var more = 0;
            var count = 0;
            var selectAll = true;

            //list the selected values
            li.each(function(){
                var $this = $(this);
                if ($this.hasClass(pluginName + '_checkall'))
                    return;

                //if is a group
                if ($this.hasClass(settings.selectorGroup))
                    fn._updateParent($this, settings);

                var input = $this.children('input.' + pluginName + '_listItem_input');
                if (!input.length)
                    return;
                var checked = input.prop('checked');
                var v = input.val();
                var text = settings.showValue && v !== '' ? v : $this.text();

                //prepare the data
                if (checked){
                    if (settings.popup){
                        if (count >= settings.popupMaxItem)
                            more++;
                        else if (!more){
                            //get the id of the input
                            var id = input.attr('class').match(/input(\d+)/);
                            var txt = text;
                            if (settings.popupLogoAsValue){
                                txt = '<img class="logo" src="{0}" />'.format($this.find('img.logo').attr('src'));
                            }
                            var className = pluginName + '_popup_item_' + id[1];
                            if ($this.hasClass('locked'))
                                className += ' locked';
                            html += '<div class="{0}">{1}</div>'.format(className, txt);
                        }

                    }

                    $this.addClass('selected');
                    val.push(text);
                    count++;
                }
                else    //not check
                {
                    $this.removeClass('selected');
                    selectAll = false;
                }
            });

            //update selected status of checkall
            if (data.elements.selectAll){
                if (selectAll)
                    data.elements.selectAll.addClass('selected');
                else {
                    data.elements.selectAll.removeClass('selected');
                }
            }

            //update popup if enable
            if (settings.popup){
                if (more)
                    html += '<div class="{0}_more">'.format(pluginName) + settings.textMoreItem.format(more) + '</div>';
                data.elements.popup.html(html);
            }

            //update result
            var text;
            if (val.length)
                text = settings.textAllSelected && selectAll ? settings.textAllSelected : val.join(', ');
            else
                text = '<span class="{0}_placeholder">{1}</span>'.format(pluginName, data.settings.textEmpty);

            data.elements.result.html(text);
        },
        //search if an event exist
        _eventExist: function(obj, evt_type){
            var evt = obj.data('events');
            //find if event exist first
            if (evt && evt[evt_type] !== undefined){
                for (var i = 0; i < evt[evt_type].length; i++) {
                    if (evt[evt_type][i].namespace === pluginName)
                        return true;
                }
            }

            return false;
        },
        _open: function(obj){
            var data = obj.data(pluginName);
            if (!data)
                return;
            var elements = data.elements;
            var wrapper = elements.wrapper;
            if (wrapper.hasClass(pluginName + '_disabled'))
                return;

            var list = elements.list;
            //ignore if is already opened
            if (!elements.list.is(':hidden'))
                return;

            if (data.settings.onOpen){
                //if return false, do not open the list
                if (data.settings.onOpen(fn._get(obj)) === false)
                    return;
            }

            elements.result.hide();
            elements.input.show().trigger('keyup'); //trigger keyup
            elements.prediction.show();
            wrapper.addClass(pluginName + '_active');

            //before showing the list, convert to absolute position if enable
            if (data.settings.absolutePosition){
                var offset = wrapper.offset();
                var dummy = $('<div></div>').attr('class', pluginName + '_dummy ' + pluginName + '_wrapper').width(wrapper.width()).height(wrapper.height());
                obj.after(dummy);
                //move the list so the absolute position can become effective
                wrapper.addClass(pluginName + '_absolute').appendTo('body').css({
                    top: offset.top + 3,
                    left: offset.left
                });

                //bind an event to the document to detect lost of focus
                var doc = $(document);
                if (!fn._eventExist(doc, 'mousedown')){
                    doc.on('mousedown.' + pluginName, function(){
                        $('ul.' + pluginName + ', select.' + pluginName).jAutochecklist('close');
                    });
                }

                //bind to window to handle resize
                var win = $(window);
                if (!fn._eventExist(win, 'resize')){
                    win.on('resize.' + pluginName, function(){
                        //find the current position of the dummy
                        var next = obj.next();
                        if (next.hasClass(pluginName + '_dummy')){
                            offset = next.offset();
                            wrapper.css({
                                top: offset.top + 3,
                                left: offset.left
                            });
                        }
                    });
                }
            }

            list.fadeIn();

            if (elements.popup)
                elements.popup.fadeIn();

            //close all other checklist
            $('ul.' + pluginName + ', select.' + pluginName).not(obj).jAutochecklist('close');
        },
        _close: function(obj){
            var data = obj.data(pluginName);
            if (!data)
                return;
            var elements = data.elements;
            var wrapper = elements.wrapper;
            //the object is destroyed or is not our plugin
            if (elements.list.is(':hidden'))
                return;

            if (data.settings.onClose){
                //if return false, do not close the list
                if (data.settings.onClose(fn._get(obj)) === false)
                    return;
            }

            if (elements.popup)
                elements.popup.hide();
            elements.input.hide().val(null);
            elements.prediction.hide().val(null);
            elements.list.hide().children('li.' + pluginName + '_noresult').remove();
            elements.result.show();
            elements.listItem.li.show();
            wrapper.removeClass(pluginName + '_active');

            //convert back absolute position to inline
            if (data.settings.absolutePosition){
                wrapper.css({
                    top: 0,
                    left: 0
                }).removeClass(pluginName + '_absolute');
                var next = obj.next();
                if (next.hasClass(pluginName + '_dummy'))
                    next.replaceWith(wrapper);
            }
        },
        _count: function(obj){
            var data = obj.data(pluginName);
            if (!data)
                return 0;

            return data.elements.listItem.checkbox.filter(':checked').length;
        },
        _get: function(obj){
            var data = obj.data(pluginName);
            if (!data)
                return [];
            var val = [];

            data.elements.listItem.checkbox.filter(':checked').each(function(){
                val.push(this.value);
            });

            return val;
        },
        _getAll: function(obj){
            var data = obj.data(pluginName);
            if (!data)
                return [];
            var val = [];

            data.elements.listItem.checkbox.each(function(){
                val.push(this.value);
            });

            return val;
        },
        _set: function(obj, vals, clearAll){
            var data = obj.data(pluginName);
            if (!data)
                return;
            if (clearAll)
                fn._selectAll(obj, false);

            data.elements.listItem.checkbox.each(function(){
                //value found
                if (vals.indexOf(this.value) !== -1)
                    this.checked = true;
            });

            fn._update(obj);
        },
        _unset: function(obj, vals){
            var data = obj.data(pluginName);
            if (!data)
                return;

            data.elements.listItem.checkbox.each(function(){
                //value found
                if (vals.indexOf(this.value) !== -1)
                    this.checked = false;
            });

            fn._update(obj);
        },
        _getLevel: function(li){
            var match = li.attr('class').match(/level(\d+)/);
            return match ? match[1] : null;
        },
        _getChildren: function(li, level, selectorChild){
            var next = li.next();
            if (!next.length)
                return [];
            var next_level = fn._getLevel(next);

            if (next_level < level || (next_level === level && (!next.hasClass(selectorChild))))
                next = null;

            return next ? [next].concat(fn._getChildren(next, level, selectorChild)) : [];
        },
        /*_getParent: function(li, settings){
         //if this is not any child
         if (!li.hasClass(settings.selectorChild)) return null;
         var prev = li.prev();
         if (!prev.length) return null;
         return prev.hasClass(settings.selectorGroup) ? prev : fn._getParent(prev);
         },*/

        _updateParent: function(li, settings){
            var groupType = settings.groupType;
            //detect the type of the group if overriden
            if (li.hasClass('groupType2'))  //if all
                groupType = 2;
            else if (li.hasClass('groupType1')) //at least one
                groupType = 1;
            else if (li.hasClass('groupType0'))
                groupType = 0;

            //exclusive, don't handle this
            if (groupType === 2)
                return;

            var level = fn._getLevel(li);
            var children = fn._getChildren(li, level, settings.selectorChild);
            var select;
            var checkbox = li.children('input.' + pluginName + '_listItem_input');

            if (groupType === 0){
                //by default selected, find at least one item not selected
                select = true;
                for (var i = 0; i < children.length; i++) {
                    if (children[i].children('input.' + pluginName + '_listItem_input').prop('checked') === false){
                        select = false;
                        break;
                    }
                }
            }
            else {
                //by default not selected, find at least one selected
                select = false;
                for (var i = 0; i < children.length; i++) {
                    if (children[i].children('input.' + pluginName + '_listItem_input').prop('checked') === true){
                        select = true;
                        break;
                    }
                }
            }

            if (select)
                li.addClass('selected');
            else
                li.removeClass('selected');
            checkbox.prop('checked', select);
        },
        _getValFromCheckboxes: function(checkboxes){
            var val = [];
            checkboxes.each(function(){
                val.push(this.value);
            });

            return val;
        },
        _buildFromUl: function(obj, settings){
            var json = [];
            obj.children().each(function(){
                var t = $(this);
                var className = this.className || '';
                var level;
                if (className)
                    level = fn._getLevel(t);

                json.push({
                    className: className,
                    groupType: t.data('grouptype'),
                    html: t.html(),
                    isChild: (t.hasClass(settings.selectorChild)),
                    isGroup: t.hasClass(settings.selectorGroup),
                    level: level,
                    locked: t.data('locked'),
                    parentBound: t.data('parentbound'),
                    selected: t.data('selected'),
                    val: t.data('value') || ''
                });
            });

            return json;
        },
        _buildFromSelect: function(obj, settings){
            var json = [];

            obj.children().each(function(){
                var t = $(this);

                //if is a group
                if (t.is('optgroup')){
                    //create a li from optgroup
                    json.push({
                        className: (this.className || '') + ' ' + settings.selectorGroup,
                        groupType: t.data('grouptype'),
                        html: this.label,
                        isChild: false,
                        isGroup: true,
                        level: null,
                        locked: false,
                        selected: false,
                        val: null
                    });

                    //foreach option in group
                    t.children().each(function(){
                        json.push({
                            className: (this.className || '') + ' ' + settings.selectorChild,
                            groupType: 0,
                            html: this.innerHTML,
                            isChild: true,
                            isGroup: false,
                            level: null,
                            locked: t.data('locked'),
                            selected: this.selected && this.hasAttribute('selected'),
                            val: this.hasAttribute('value') ? this.value : ''
                        });
                    });
                }
                else {
                    json.push({
                        className: this.className || '',
                        groupType: 0,
                        html: this.innerHTML,
                        isChild: false,
                        isGroup: false,
                        level: null,
                        locked: t.data('locked'),
                        selected: this.selected && this.hasAttribute('selected'),
                        val: this.hasAttribute('value') ? this.value : ''
                    });
                }

            });

            return json;
        },
        _buildItemFromJSON: function(json, settings, name){
            if (!name)
                name = pluginName;
            var li = '';
            var i, e, val, n;
            var type = settings.multiple ? 'checkbox' : 'radio';
            var count = 0;

            for (var i = 0; i < json.length; i++) {
                e = json[i];
                val = (e.val === '' || e.val === undefined || e.val === null) ? '' : e.val;
                var className = (e.className ? e.className + ' ' : '') + pluginName + '_listItem';
                var isGroup = e.isGroup || false;

                if (e.groupType !== undefined)
                    className += ' groupType' + e.groupType;
                if (e.locked)
                    className += ' locked';

                //add some padding
                var px = 5;
                //check the item level
                if (e.level && e.level > 1){
                    px += (e.level - 1) * 20;
                    className += ' level' + e.level;
                }
                else
                    className += ' level1';

                //if is a group
                var style = [];
                if (isGroup){   //group
                    if (val === ''){
                        className += ' ' + pluginName + '_listItem_group_empty ';
                        if (settings.checkbox)
                            px += 15;
                    }
                }
                else if (e.isChild){   //child
                    className += ' ' + pluginName + '_listItem_child';
                    px += 20;
                    if (settings.checkbox)
                        px += 15;
                }

                if (px > 5)
                    style.push('padding-left:' + px + 'px');
                
                style = style.length ? 'style="' + style.join(';') + '"' : '';

                li += '<li class="{0}" {1}>'.format(className, style);

                //if case single select and is first item, must add a fallback
                if (!settings.multiple && !count)
                    li += '<input type="hidden" name="{0}" value="" />'.format(name);

                //if is not a group, or empty label or select all
                if ((!isGroup || val !== '') && (!settings.firstItemSelectAll || i > 0)){
                    //multiple, add []
                    n = name;
                    if (settings.multiple){
                        //fallback, only apply to multiple element
                        if (settings.fallback){
                            n += '[' + count + ']';
                            li += '<input type="hidden" name="{0}" value="" />'.format(n);
                        }
                        else
                            n += '[]';
                    }

                    li += '<input type="{0}" name="{1}" value="{2}" class="{3}_listItem_input {3}_input{5}" {4} />'.format(type, n, val, pluginName, e.selected ? 'checked' : '', count++);
                }

                li += e.html + '</li>';
            }

            return li;
        },
        _insertList: function(ul, li, settings, showNoResult, isAdd, mobile_popup){
            //empty object
            var selectAll, checkbox;

            if (showNoResult && !li){
                ul.html('<li class="{0}_noresult">{1}</li>'.format(pluginName, settings.textNoResult));
                li = $();
            }
            else {
                if (isAdd)
                    ul.append(li);
                else
                    ul.html(li);
                li = ul.children();

                //if checkall enable
                if (settings.firstItemSelectAll)
                    selectAll = ul.children(':first').addClass(pluginName + '_checkall');

                checkbox = li.children('input.' + pluginName + '_listItem_input');

                //show or hide checkbox
                if (settings.checkbox)
                    checkbox.show();

            }

            return {
                li: li,
                checkbox: checkbox,
                selectAll: selectAll
            };

        },
        _predict: function(val, input, suggest){
            var result;
            var val_lower = val.toLowerCase();

            for (var i = 0; i < suggest.length; i++) {
                var text = suggest[i].toLowerCase();
                var index = text.indexOf(val_lower);
                index += val_lower.length;
                //find the index of the following space character
                var sp_index = text.indexOf(' ', index);
                //if space is the next character, find the next next space
                if (index === sp_index)
                    sp_index = text.indexOf(' ', index + 1);
                //if reaching the end without space, get all text from starting index
                result = val + (sp_index === -1 ? text.substr(index) : text.substring(index, sp_index));
                //as we found the first matched element, stop the search
                if (result !== val){
                    input.val(result);
                    return false;
                }
            }
        }

    };

    $.fn.jAutochecklist = function(method){
        //main
        if (fn[method]){
            if (method.substr(0, 1) === '_')
                $.error('Method ' + method + ' does not exist on jQuery.' + pluginName);
            return fn[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method){
            return fn.init.apply(this, arguments);
        } else {
            $.error('Method ' + method + ' does not exist on jQuery.' + pluginName);
        }
    };

})(jQuery, document, window);