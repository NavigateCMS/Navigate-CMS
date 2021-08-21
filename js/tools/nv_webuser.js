function nv_wu_identification_init()
{
    var jqi = jQuery('#nv_wu_identification_form');

    jqi.find("button.nv_wu_submit_btn").on("click", function(e)
    {
        e.preventDefault();
        e.stopPropagation();
        jqi.find("input[name=nv_wu_submit]").val(jQuery(this).data("action"));
        jqi.submit();
        return false;
    });

    jqi.find('div.nv_wu-sign_in_info_message p.custom_message').not(":empty").each(function()
    {
        jQuery(this).css('display', 'block');
        jQuery(this).parent().css('display', 'block');
    });

    jqi.find('div.nv_wu-sign_in_error_message p.custom_message').not(":empty").each(function()
    {
        jQuery(this).css('display', 'block');
        jQuery(this).parent().css('display', 'block');
    });

    jqi.find('div.nv_wu-sign_up_info_message p.custom_message').not(":empty").each(function()
    {
        jQuery(this).css('display', 'block');
        jQuery(this).parent().css('display', 'block');
    });

    jqi.find('div.nv_wu-sign_up_error_message p.custom_message').not(":empty").each(function()
    {
        jQuery(this).css('display', 'block');
        jQuery(this).parent().css('display', 'block');
    });

    jqi.find('a[data-action=forgot_password]').on("click", function(e)
    {
        e.preventDefault();
        e.stopPropagation();

        var username = jqi.find('input[name=nv_wu_sign_in_emailusername]').val();

        jqi.find('.nv_wu-sign_in_error_message').css('display', 'none');
        jqi.find('.nv_wu-sign_in_error_message p').css('display', 'none');
        jqi.find('.nv_wu-sign_in_info_message').css('display', 'none');
        jqi.find('.nv_wu-sign_in_info_message p').css('display', 'none');

        if(username=="")
        {
            jqi.find('.nv_wu-sign_in_error_message').css('display', 'block');
            jqi.find('.nv_wu-sign_in_error_message p[class=forgot_password_missing_username]').css('display', 'block');
        }
        else
        {
            jqi.find("input[name=nv_wu_submit]").val("forgot_password");
            jqi.submit();
        }
    });
}