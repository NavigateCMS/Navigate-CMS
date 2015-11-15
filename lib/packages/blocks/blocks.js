var links_table_row_models = [];
var poll_answers_table_row_models = [];

function navigate_blocks_action_change(lang, el)
{
    $("#action-web-"+lang).parents('.navigate-form-row').hide();
    $("#action-javascript-"+lang).parents('.navigate-form-row').hide();
    $("#action-file-"+lang).parents('.navigate-form-row').hide();
    $("#action-image-"+lang).parents('.navigate-form-row').hide();

    var action_type = $(el).val();

    //if(!$(el).is(':visible')) return;

    if(action_type == "web" || action_type == "web-n")
    {
        $("#action-web-" + lang).parents('.navigate-form-row').show();
    }
    else if(action_type == "javascript")
    {
        $("#action-javascript-" + lang).parents('.navigate-form-row').show();
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
    var tsid = new Date().getTime();
    $(this).parent().find("table").append('<tr></tr>').css('cursor', 'move');
    $(this).parent().find("tr:last").append(links_table_row_models[lang]);
    $(this).parent().find("tr:last").find('input,label').each(function()
    {
        if($(this).attr('name'))
        {
            var new_name = ($(this).attr('name').split('['))[0];
            $(this).attr('name', new_name + "[" + tsid + "]");
        }

        if($(this).attr('id'))
        {
            var new_name = ($(this).attr('id').split('['))[0];
            $(this).attr('id', new_name + "[" + tsid + "]");
        }

        if($(this).attr('for'))
        {
            var new_name = ($(this).attr('for').split('['))[0];
            $(this).attr('for', new_name + "[" + tsid + "]");
        }
    });
    $(this).parent().find("table").tableDnDUpdate();

    // enable icon selector?
    if($(this).parent().find("input[name^=trigger-links-table-icon]"))
    {
        navigate_blocks_trigger_links_table_icon_selector($(this).parent().find("tr:last"));
    }

    return false;
});

function navigate_blocks_trigger_links_table_icon_selector(tr)
{
    var input = $(tr).find("td:first").find("input");

    input.select2({
        placeholder: "",
        allowClear: true,
        data: navigate_fontawesome_classes,
        formatResult: function(object, container, query)
        {
            if(object.text != "")
                return "<i class=\"fa fa-fw fa-2x "+object.id+"\"></i> " + object.text;
            else
                return "(" + navigate_t(581, "None") + ")";
        }
    });

    input.on("select2-open", function() {
        $(".select2-drop.select2-drop-active").css("width", "250px");
        $(".select2-drop.select2-drop-active").find("input").on("keyup", function()
        {
            $(".select2-drop.select2-drop-active").css("width", "250px");
        });
    });

    input.on("change", function(e)
    {
        $(this).prev().find("span:first").html("<i class=\"fa fa-fw fa-2x "+e.val+"\"></i>");
    });

    $(tr).find("td:first").find(".select2-choice:first").css("width", "54px");
}

function navigate_blocks_trigger_links_table_row_remove(el)
{
    $(el).parents('tr').remove();
}

$("button[id^=\"poll-answers-table-add-\"]").on("click", function(e)
{
    e.preventDefault();
    e.stopPropagation();
    var lang = $(this).attr("data-lang");
    var uid = new Date().getTime();
    $(this).parent().find("table").append('<tr></tr>').css('cursor', 'move');
    $(this).parent().find("tr:last").append(poll_answers_table_row_models[lang]);
    $(this).parent().find("tr:last").attr("id", "poll-answers-table-row-" + uid);
    $(this).parent().find("tr:last").find('input').each(function(i)
    {
        var new_name = ($(this).attr('name').split('['))[0];
        $(this).attr('name', new_name + "[" + uid + "]");
        //if(i==1) $(this).val(uid);
    });

    $(this).parent().find("table").tableDnDUpdate();
    return false;
});

function navigate_blocks_poll_answers_table_row_remove(el)
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

function navigate_blocks_group_block_settings(el)
{
    var code = $(el).attr("data-block-group-block");
    var block_group = $(el).attr("data-block-group");

    $("<div>")
        .html('<iframe width="100%" height="100%" frameborder="0" src="?fid=blocks&act=block_group_block_options&block_group=' + block_group + '&code=' + code + '"></iframe>')
        .dialog(
        {
            width: $(window).width() * 0.95,
            height: $(window).height() * 0.95,
            modal: true,
            title: "<img src=\"img/icons/silk/cog.png\" align=\"absmiddle\"> " + $(this).parent().prev().prev().text()
        }).dialogExtend(
        {
            maximizable: true
        });
}

if($("#blocks_group_table").length > 0)
{
    $("#blocks_group_table").on(
        "click",
        "a[data-block-group-action=settings]",
        function()
        {
            navigate_blocks_group_block_settings(this);
        }
    );
}
