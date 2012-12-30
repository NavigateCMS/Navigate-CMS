$(function()
{
    /* animate links in footer */
    $('.footer-block li').find('a').hover(
        function()
        {
            $(this).animate({ paddingLeft: '5px' }, 200);
        },
        function()
        {
            $(this).animate({ paddingLeft: '0px' }, 200);
        });

    /* submenu */
    $('#menu > ul > li').hover(
        function()
        {
            $(this).find('ul:first').fadeIn('fast');
        },
        function()
        {
            $(this).find('ul:first').fadeOut('fast');
        }
    );

    /* home slider */
    if($('#slider').children().length > 1)
    {
        $('#slider').children().not(':first').hide();
        $('#slider').children().css('position', 'absolute');
        setInterval(function()
        {
            var active = $('#slider').children(':visible');
            var next = $(active).next();
            if($(next).length < 1)
                next = $('#slider').children(':first');

            $(active).fadeOut();
            $(next).fadeIn();
        }, ocean_slideshow_pause*1000);
    }

    /* home latest works, remove if empty */
    if($('.home-samples').children().length <= 2)
        $('.home-samples').remove();

    /* footer-block clean empty links */
    $('.footer-block').find('li').each(function(index, element)
    {
        if($(element).text()=="")
            $(element).remove();
    });
});
