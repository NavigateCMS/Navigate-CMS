function navigate_themes_init()
{

    $(".navigrid-themes-installed").parent().parent().addClass("navigrid-item-highlight");

    $(".navigrid-themes-info").on("click", function()
    {
        var theme = $(this).attr("theme");
        $("#navigrid-themes-information").prop("title", $(this).attr("theme-title"));
        $("#navigrid-themes-information").load(NAVIGATE_APP + "?fid=themes&act=theme_info&theme=" + theme, function()
        {
            $("#navigrid-themes-information").dialog(
                {
                    width: 700,
                    height: 500,
                    modal: true
                }).dialogExtend(
                {
                    maximizable: true
                }).css("padding", "3px");
        });
    });

    $(".navigrid-themes-install").on("click", function()
    {
        var theme = $(this).attr("theme");

        $("#navigrid-themes-install-confirmation").dialog(
            {
                resizable: true,
                width: 400,
                height: 200,
                modal: true,
                buttons:
                [
                    {
                        text: navigate_lang_dictionary[190],
                        click: function()
                        {
                            window.location.href = "?fid=themes&act=themes&opt=install&theme=" + theme;
                            $( this ).dialog( "close" );
                        }
                    },
                    {
                        text: navigate_lang_dictionary[58],
                        click: function()
                        {
                            $( this ).dialog( "close" );
                        }
                    }
                ]
            });
        return false;
    });

    $(".navigrid-theme-install-demo").on("click", function()
    {
        $("#navigrid-themes-install-demo-confirmation").dialog(
            {
                resizable: true,
                width: 400,
                height: 200,
                modal: true,
                buttons:
                    [
                        {
                            text: navigate_lang_dictionary[190],
                            click: function()
                            {
                                window.location.href = "?fid=themes&act=theme_sample_content_import";
                                $( this ).dialog( "close" );
                            }
                        },
                        {
                            text: navigate_lang_dictionary[58],
                            click: function()
                            {
                                $( this ).dialog( "close" );
                            }
                        }
                    ]
            });
        return false;
    });

    $("#theme-upload-button").on("click", function()
    {
        $("#theme-upload-button").parent().find("form").remove();
        $("#theme-upload-button").after('<form action="?fid=themes&act=theme_upload" enctype="multipart/form-data" method="post"><input type="file" name="theme-upload" style=" display: none;" /></form>');
        $("#theme-upload-button").next().find("input").bind("change", function()
        {
            if($(this).val()!="")
                $(this).parent().submit();
        });
        $("#theme-upload-button").next().find("input").trigger("click");

        return false;
    });

    $(".navigrid-themes-remove").on("click", function()
    {
        var theme = $(this).attr("theme");

        $("#navigrid-themes-remove-confirmation").dialog(
            {
                resizable: true,
                width: 300,
                height: 150,
                modal: true,
                title: $(this).attr("theme-title"),
                buttons:
                [
                    {
                        text: navigate_lang_dictionary[190],
                        click: function()
                        {
                            $.post(
                                NAVIGATE_APP + "?fid=themes&act=remove&theme=" + theme,
                                { },
                                function(data)
                                {
                                    if(data=="true")
                                    {
                                        $("#item-" + theme).fadeOut("slow", function(){ $("#item-" + theme).remove(); });
                                    }
                                    else
                                    {
                                        navigate_notification(navigate_lang_dictionary[56]);
                                    }
                                }
                            );
                            $( this ).dialog( "close" );
                        }
                    },
                    {
                        text: navigate_lang_dictionary[58],
                        click: function()
                        {
                            $( this ).dialog( "close" );
                        }
                    }
                ]
            });
        return false;
    });
}

