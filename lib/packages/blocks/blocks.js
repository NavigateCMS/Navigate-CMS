var links_table_row_models = [];

function navigate_blocks_action_change(lang, el)
{
    $("#action-web-"+lang).parents('.navigate-form-row').hide();
    $("#action-file-"+lang).parents('.navigate-form-row').hide();
    $("#action-image-"+lang).parents('.navigate-form-row').hide();

    var action_type = $(el).val();

    //if(!$(el).is(':visible')) return;

    if(action_type == "web" || action_type == "web-n")
    {
        $("#action-web-" + lang).parents('.navigate-form-row').show();
    }
    else
        $("#action-" + action_type + "-" + lang).parents('.navigate-form-row').show();
}

function navigate_blocks_trigger_change(lang, el)
{
    var trigger_type = $(el).val();

    $("#trigger-image-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-video-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-rollover-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-flash-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-html-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-links-"+lang).parents('.navigate-form-row').hide();
    $("#trigger-content-"+lang).parents('.navigate-form-row').hide();

    $("#action-type-" + lang).parent().hide();

    if($.inArray(trigger_type, ["", "title", "image", "rollover", "content"]) > -1)
        $("#action-type-" + lang).parent().show();
    else
        $("#action-type-" + lang).select2("val", "");

    if($("#trigger-" + trigger_type + "-" + lang).length > 0)
        $("#trigger-" + trigger_type + "-" + lang).parents('.navigate-form-row').show();

    $(navigate_codemirror_instances).each(function() { this.refresh(); } );

    navigate_blocks_action_change(lang, $("#action-type-" + lang));
}

$("button[id^=\"trigger-links-table-add-\"]").on("click", function(e)
{
    e.preventDefault();
    e.stopPropagation();
    var lang = $(this).attr("data-lang");
    $(this).parent().find("table").append('<tr></tr>').css('cursor', 'move');
    $(this).parent().find("tr:last").append(links_table_row_models[lang]);
    $(this).parent().find("tr:last").find('input').each(function()
    {
        var new_name = ($(this).attr('name').split('['))[0];
        $(this).attr('name', new_name + "[" + new Date().getTime() + "]");
    });
    $(this).parent().find("table").tableDnDUpdate();
    return false;
});

function navigate_blocks_trigger_links_table_row_remove(el)
{
    $(el).parents('tr').remove();
}

function navigate_items_select_language(el)
{
    var code;
    if(typeof(el)=="string")
        code = el;
    else
        code = $("#"+$(el).attr("for")).val();

    $(".language_fields").css("display", "none");
    $("#language_fields_" + code).css("display", "block");

    $("#language_selector_" + code).attr("checked", "checked");

    $(navigate_codemirror_instances).each(function() { this.refresh(); } );
}