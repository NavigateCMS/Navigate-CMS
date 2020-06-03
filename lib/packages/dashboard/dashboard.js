function navigate_dashboard_run()
{
    // add panel events
    $(".navigate-panel-header").on("mouseenter", function() { $(this).addClass("ui-state-active"); });
    $(".navigate-panel-header").on("mouseleave", function() { $(this).removeClass("ui-state-active"); });

    $(".navigate-panel-header").on("dblclick", function(event, save)
    {
        var height = $(this).parent().css("height");

        if(parseInt(height) > 28)
        {
            $(this).parent().data("default-height", height);
            $(this).parent().css({"height": "28px", "min-height": "28px", "overflow": "hidden"});
        }
        else
        {
            var height = $(this).parent().data("default-height");
            $(this).parent().css({"height": height, "min-height": "100px", "overflow": "auto"});
        }

        if(save!="false")
            navigate_dashboard_save_panels_status();
    });

    // distribute panels based on user preferences
    // panels without position will be placed at the bottom
    var navigate_dashboard_panels_unordered = [];
    $('.navigate-panel').each(function()
    {
        navigate_dashboard_panels_unordered.push($(this).attr("id"));
    });

    for(var c=0; c < 4; c++)
    {
        for(panel in navigate_dashboard_panels[c])
        {
            $("#" + navigate_dashboard_panels[c][panel]["id"]).appendTo($("#navigate-dashboard-column-" + (c+1)));

            // remove added panel from the unordered list
            navigate_dashboard_panels_unordered = $.grep(navigate_dashboard_panels_unordered, function(value)
            {
                return value != navigate_dashboard_panels[c][panel]["id"];
            });

            if(navigate_dashboard_panels[c][panel]["open"]=="false")
                $("#" + navigate_dashboard_panels[c][panel]["id"]).find(".navigate-panel-header").trigger("dblclick", ["false"]);

            if(navigate_dashboard_panels[c][panel]["hidden"]=="true")
                $("#" + navigate_dashboard_panels[c][panel]["id"]).addClass("hidden");

            // remove visibility:hidden on load
            $("#" + navigate_dashboard_panels[c][panel]["id"]).css('visibility', 'visible');
        }
    }

    // add any unordered items left
    for(panel in navigate_dashboard_panels_unordered)
    {
        $("#" + navigate_dashboard_panels_unordered[panel]).appendTo($("#navigate-dashboard-column-1"));
        $("#" + navigate_dashboard_panels_unordered[panel]).css('visibility', 'visible');
    }

    $("#navigate-dashboard-trashcan").droppable({
        classes: {
            "ui-droppable-hover": "ui-state-highlight"
        },
        tolerance: "pointer",
        drop: function(event, ui)
        {
            var id = ui.helper[0].id;
            $("#" + id).fadeOut("fast", function()
            {
                $(this).addClass("hidden");
                navigate_dashboard_save_panels_status();
            });
        }
    });

    // now allow sorting panels
    $( ".navigate-dashboard-column" ).sortable({
        connectWith: ".navigate-dashboard-column",
        handle: ".navigate-panel-header",
        helper: "clone",
        placeholder: "navigate-panel-placeholder ui-corner-all",
        forcePlaceholderSize: true,
        revert: true,
        containment: $("#navigate-content-tabs"),
        tolerance: "pointer",
        cursorAt: {
            right: 8,
            top: 8
        },
        start: function()
        {
            $("#navigate-dashboard-trashcan").removeClass("hidden");
            $("#navigate-dashboard-trashcan").fadeIn("fast");
        },
        stop: function()
        {
            $("#navigate-dashboard-trashcan").fadeOut("fast", function()
            {
                $("#navigate-dashboard-trashcan").addClass("hidden");
            });
            navigate_dashboard_save_panels_status();
        }
    });

    $('#navigate-dashboard-trashcan').parent().on("contextmenu", function(e)
    {
        e.stopPropagation();
        e.preventDefault();

        navigate_hide_context_menus();
        setTimeout(function()
        {
            $('#contextmenu-dashboard').menu();
            var xpos = e.clientX;
            var ypos = e.clientY;

            $('#contextmenu-dashboard').css({
                "top": ypos,
                "left": xpos,
                "z-index": 100000,
                "position": "absolute"
            });
            $('#contextmenu-dashboard').addClass('navi-ui-widget-shadow');

            $('#contextmenu-dashboard').show();

        }, 250);
    });

    navigate_dashboard_check_hidden_panels();
}

function navigate_dashboard_check_hidden_panels()
{
    $('#contextmenu-dashboard-panels-removed li').remove();
    $(".navigate-panel.hidden").each(function()
    {
        var title = $(this).find(".navigate-panel-header").html();
        title = $("<div>" + title + "</div>");
        title.find("div").remove();
        title = title.html();
        $('#contextmenu-dashboard-panels-removed').append('<li class="ui-menu-item"><a href="#" onclick="navigate_dashboard_restore_panel(this);" data-panel-id="'+$(this).attr("id")+'">'+title+'</a></li>');
    });

    $('#contextmenu-dashboard-panels-removed').parent().removeClass('ui-state-disabled');
    if($('#contextmenu-dashboard-panels-removed li').length < 1)
        $('#contextmenu-dashboard-panels-removed').parent().addClass('ui-state-disabled');
}

function navigate_dashboard_restore_panel(el)
{
    $('#' + $(el).data("panel-id")).removeClass("hidden");
    navigate_dashboard_save_panels_status();
}

function navigate_dashboard_save_panels_status()
{
    $(".navigate-panel-header").removeClass("ui-state-active");

    navigate_dashboard_panels = [];
    for(c = 0; c < 4; c++)
    {
        navigate_dashboard_panels[c] = [];
        $("#navigate-dashboard-column-" + (c+1)).find("> div").each(function()
        {
            var open = false;
            if(parseInt($(this).height()) > 28)
                open = true;

            var hidden = $(this).hasClass("hidden");

            navigate_dashboard_panels[c].push({
                id: $(this).attr("id"),
                open: open,
                hidden: hidden
            });
        });
    }

    navigate_dashboard_check_hidden_panels();

    $.post(
        "?fid=dashboard&act=json&oper=settings_panels",
        {
            dashboard_panels: navigate_dashboard_panels
        }
    );
}
