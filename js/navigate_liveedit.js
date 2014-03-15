$(function(){navigate_liveedit_init();});
//$(window).bind('resize', navigate_liveedit_init);

function navigate_liveedit_init()
{
    $('body').append('<div id="navigate_liveedit_bar_spacer">&nbsp;</div>');

    $('#navigate_liveedit_bar_information_button').on('click', function()
    {
        if($('#navigate_liveedit_bar').height()==28)
        {
            $('#navigate_liveedit_bar_information').fadeIn();
            $('#navigate_liveedit_bar').animate({'height': '56'}, 500);
        }
        else
        {
            $('#navigate_liveedit_bar').animate({'height': '28'}, 500);
            $('#navigate_liveedit_bar_information').fadeOut();
        }
    });

    $('#navigate_liveedit_bar_liveedit_button').on('click', function()
    {
        if($(this).hasClass('active'))
        {
            // remove all handlers
            $(this).removeClass('active');
        }
        else
        {
            $(this).addClass('active');
            // bind all handlers
            $('div[ng-block-id]').hover(
                function()
                {
                    $(this).css('background', 'yellow');
                },
                function()
                {
                    $(this).css('background', 'none');
                }
            )
        }
    });

    $('#navigate_liveedit_bar').show();
}

