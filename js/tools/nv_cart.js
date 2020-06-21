var nv_cart_decimal_separator = ".";

function nv_cart_view_init()
{
    $('tr.nv_cart_line').on("dblclick", ".nv_cart_line_quantity_value", function()
    {
        var quantity = $.trim($(this).text());
        var parent = $(this).parent();
        var modify_url = $(this).data('update_qty-href');
        var line_quantity_backup = $(parent).html();

        $(parent).data('old-value', quantity);
        $(this).parent().html('<input class="nv_cart_line_quantity_field" type="text" value="' + quantity  + '" />');
        $(parent).find('input').on("blur", function(e)
        {
            e.stopPropagation();
            e.preventDefault();

            var new_qty = Math.ceil($(this).val());

            if(new_qty != quantity)
            {
                window.location.replace(modify_url + $(this).val());
            }
            else
            {
                $(parent).html(line_quantity_backup);
            }
        })
    });

    nv_cart_keep_alive();
}

function nv_cart_identification_init()
{
    var jqi = jQuery('#nv_cart_identification_form');

    jqi.find("button.nv_cart_wu_submit_btn").on("click", function(e)
    {
        e.preventDefault();
        e.stopPropagation();
        jqi.find("input[name=nv_cart_wu_submit]").val(jQuery(this).data("action"));
        jqi.submit();
        return false;
    });

    jqi.find('div.nv_cart-sign_in_info_message p.custom_message').not(":empty").each(function()
    {
        jQuery(this).css('display', 'block');
        jQuery(this).parent().css('display', 'block');
    });

    jqi.find('div.nv_cart-sign_in_error_message p.custom_message').not(":empty").each(function()
    {
        jQuery(this).css('display', 'block');
        jQuery(this).parent().css('display', 'block');
    });

    jqi.find('div.nv_cart-sign_up_info_message p.custom_message').not(":empty").each(function()
    {
        jQuery(this).css('display', 'block');
        jQuery(this).parent().css('display', 'block');
    });

    jqi.find('div.nv_cart-sign_up_error_message p.custom_message').not(":empty").each(function()
    {
        jQuery(this).css('display', 'block');
        jQuery(this).parent().css('display', 'block');
    });


    jqi.find('a[data-action=forgot_password]').on("click", function(e)
    {
        e.preventDefault();
        e.stopPropagation();

        var username = jqi.find('input[name=nv_cart_wu_sign_in_emailusername]').val();

        jqi.find('.nv_cart-sign_in_error_message').css('display', 'none');
        jqi.find('.nv_cart-sign_in_error_message p').css('display', 'none');
        jqi.find('.nv_cart-sign_in_info_message').css('display', 'none');
        jqi.find('.nv_cart-sign_in_info_message p').css('display', 'none');

        if(username=="")
        {
            jqi.find('.nv_cart-sign_in_error_message').css('display', 'block');
            jqi.find('.nv_cart-sign_in_error_message p[class=forgot_password_missing_username]').css('display', 'block');
        }
        else
        {
            jqi.find("input[name=nv_cart_wu_submit]").val("forgot_password");
            jqi.submit();
        }
    });

    nv_cart_keep_alive();
}

function nv_cart_shipping_options_init(decimal_separator)
{
    if(decimal_separator)
    {
        nv_cart_decimal_separator = decimal_separator;
    }

    $(".nv_cart_shipping_method_option").on("click", function()
    {
        $(".nv_cart_shipping_method_option").removeClass("selected");
        $(this).addClass("selected");
        $(this).find("input[type=radio]").prop("checked", true);
        var order_total = parseFloat($(".nv_cart_shipping_order_total").data("order-subtotal"));
        order_total += parseFloat($(this).data("shipping_method-cost"));

        $(".nv_cart_shipping_order_total span").html(nv_cart_decimal_to_string(order_total, 2, nv_cart_decimal_separator));

        $('.button.nv_cart_button_continue').prop("disabled", false);
    });

    if($(".nv_cart_shipping_method_option").length == 1)
    {
        // auto select the only available shipping method
        $(".nv_cart_shipping_method_option").click();
    }

    nv_cart_keep_alive();
}

function nv_cart_summary_init(decimal_separator)
{
    if(decimal_separator)
        nv_cart_decimal_separator = decimal_separator;

    $('.nv_cart_summary_form button[type=submit]').prop("disabled", true);
    $("input[name='payment_method[]']").on("click", function()
    {
        $('.nv_cart_summary_form button[type=submit]').prop("disabled", false);
    });

    nv_cart_keep_alive();
}

function nv_cart_decimal_to_string(value, decimals, decimal_separator)
{
    value = parseFloat(value).toFixed(decimals) + "";
    value = value.replace(/\./g, decimal_separator);
    return value;
}

function nv_cart_keep_alive()
{
    setInterval(function()
    {
        $.post('?_nvka');
    }, 30000);
}