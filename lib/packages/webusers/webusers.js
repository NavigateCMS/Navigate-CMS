/* navigate webusers function javascript code */

function navigate_webusers_onload()
{
    // force removing the browser saved password
    setTimeout(function() {
        $("input[name=webuser-password]").val("");
    }, 100);

    function navigate_webusers_change_access()
    {
        $("#webuser-access-begin").parent().hide();
        $("#webuser-access-end").parent().hide();

        if($("#webuser-access").val() == "2")
        {
            $("#webuser-access-begin").parent().show();
            $("#webuser-access-end").parent().show();
        }
    }
    navigate_webusers_change_access();

    $("#webuser-country").bind("change blur", function()
    {
        if($(this).val() != webuser_country)
        {
            webuser_country = $(this).val();
            $.getJSON("?fid=webusers", { country: $(this).val(), act: 90 }, function(data)
            {
                $("#webuser-timezone").find("option").remove();

                $.each(data, function(value, text)
                {
                    $("<option />", {
                        value: value,
                        html: text
                    }).appendTo("#webuser-timezone");
                });
            });
        }
    });

    // help messages
    $("#webuser_username_info").qtip(
    {
        content: "<div>" + $("#webuser_username_info").data("message") + "</div>",
        show: {event: "mouseover"},
        hide: {event: "mouseout"},
        style: {tip: true, width: 300, classes: "qtip-cream"},
        position: {at: "top right", my: "bottom left"}
    });

    $("#webuser-username").qtip('disable');

    $("#webuser-username").data("original-value", $("#webuser-username").val());
    $("#webuser-username").on("keyup change blur", function()
    {
        if($(this).val() != $(this).data("original-value"))
        {
            if(!$("#webuser_username_info").is(":visible"))
            {
                $("#webuser_username_info").effect("pulsate", "slow");
            }
        }
        else
        {
            $("#webuser_username_info").hide();
        }

        // check username availability
        $("#webuser-username").qtip('destroy');
        if($(this).val() != $(this).data("original-value"))
        {
            $.post(
                '?fid=webusers&act=username_available',
                {
                    "username": $(this).val()
                },
                function (data)
                {
                    if (data == "false")
                    {
                        $("#webuser-username").qtip(
                            {
                                content: "<div>" + $("#webuser-username").data("message-not-available") + "</div>",
                                show: true,
                                hide: false,
                                style: {tip: true, classes: "qtip-cream"},
                                position: {at: "top left", my: "bottom left", adjust: {x: 8}}
                            });
                    }
                }
            );
        }
    });
}