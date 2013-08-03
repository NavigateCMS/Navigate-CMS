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