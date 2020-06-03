/* code to make third party libraries work as expected in Navigate CMS */

/* allow jQuery UI Dialog to set titles in HTML, not just text */
/* source: http://stackoverflow.com/questions/14488774/using-html-in-a-dialogs-title-in-jquery-ui-1-10 */
$.widget("ui.dialog", $.extend({}, $.ui.dialog.prototype, {
    _title: function(title) {
        if (!this.options.title ) {
            title.html("&#160;");
        } else {
            title.html(this.options.title);
        }
    }
}));

/* jQuery UI selectmenu with icons */
$.widget( "custom.iconselectmenu", $.ui.selectmenu,
{
    _renderItem: function( ul, item )
    {
        var li = $( "<li>" );
        var wrapper = $( "<div>", { text: item.label } );

        if( item.disabled )
        {
            li.addClass( "ui-state-disabled" );
        }

        $( "<span>", {
            style: item.element.attr( "data-style" ),
            "class": "ui-icon " + item.element.attr( "data-class" )
        }).appendTo( wrapper );

        return li.append(wrapper).appendTo( ul );
    }
});

/* jQuery UI selectmenu with images */
$.widget( "custom.imageselectmenu", $.ui.selectmenu,
{
    _renderItem: function( ul, item )
    {
        var li = $( "<li>" );
        var wrapper = $( "<div>", { text: item.label } );

        if( item.disabled )
        {
            li.addClass( "ui-state-disabled" );
        }

        if(!item.element.attr('data-src'))
        {
            item.element.attr('data-src', "img/transparent.gif");
        }

        $( "<img />", {
            style: item.element.attr( "data-style" ),
            width: item.element.attr( "data-width" ),
            height: item.element.attr( "data-height" ),
            src: item.element.attr( "data-src" ),
            "class": item.element.attr( "data-class" )
            })
            .css({
                "margin-right": "4px",
                "vertical-align": "-3px"
            })
            .prependTo( wrapper );

        return li.append(wrapper).appendTo( ul );
    },
    updateIcon: function()
    {
        // this = ui
        var icon = $('#' + this.ids.element).find('option[value="'+$('#'+this.ids.element).val()+'"]').attr('data-class');
        $('#'+this.ids.button+' img').remove();
        $('#'+this.ids.button)
            .find('.ui-selectmenu-text')
            .prepend('<img src="img/transparent.gif" class="'+icon+'" />');
        $('#'+this.ids.button+' img').css({
            'margin-right': '4px',
            'margin-left': '3px',
            'margin-top': '1px',
            'margin-bottom': '0px',
            'vertical-align': '-5px'
        });
    }
});