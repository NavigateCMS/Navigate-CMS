var navigate_payment_methods_language_selected = "";
var navigate_payment_methods_regions_preselection = [];

function navigate_payment_methods_onload(onload_language)
{
    navigate_payment_methods_select_language(onload_language);
}

function navigate_payment_methods_select_language(el)
{
    var code;
    if(typeof(el)=="string")
    {
        code = el;
        $('input[name="language_selector[]"]').parent().find("label").removeClass("ui-state-active");
        $('label[for="language_selector_\' + code + \'"]').addClass("ui-state-active");
    }
    else
        code = $("#"+$(el).attr("for")).val();

    $(".language_fields").css("display", "none");
    $("#language_fields_" + code).css("display", "block");

    $("#language_selector_" + code).attr("checked", "checked");

    navigate_payment_methods_language_selected = code;
}
