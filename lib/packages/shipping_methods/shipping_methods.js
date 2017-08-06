var navigate_shipping_methods_language_selected = "";
var navigate_shipping_methods_regions_preselection = [];

function navigate_shipping_methods_onload(onload_language)
{
    navigate_shipping_methods_select_language(onload_language);
    nv_shipping_methods_dialog_regions_how_change();
    $("#shipping_methods_rates_table").on("click", "i", function()
    {
        if($(this).data("action")=="remove")
        {
            // remove TR
            $(this).parent().parent().remove();
        }
    });

    $("#shipping_methods_dialog-tax_class").on("change", function()
    {
        if($(this).val() == "custom")
            $("#shipping_methods_dialog-tax_value").css("visibility", "visible");
        else
            $("#shipping_methods_dialog-tax_value").css("visibility", "hidden");
    });

    $("#shipping_methods_dialog-tax_class").trigger("change");
}

function navigate_shipping_methods_select_language(el)
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

    navigate_shipping_methods_language_selected = code;
}


function navigate_shipping_methods_rates_table_edit_dialog(row_id)
{
    var shipping_method_rate = {};

    if(!row_id)
    {
        // add new => reset dialog fields
        $("#shipping_methods_dialog-country").val("").trigger("change");
        navigate_shipping_methods_regions_preselection = [];
        $("#shipping_methods_dialog-regions").val("").trigger("change");
        $("#shipping_methods_dialog-weight_min").val("").trigger("change");
        $("#shipping_methods_dialog-weight_max").val("").trigger("change");
        $("#shipping_methods_dialog-subtotal_min").val("").trigger("change");
        $("#shipping_methods_dialog-subtotal_max").val("").trigger("change");
        $("#shipping_methods_dialog-cost").val("").trigger("change");
        $("#shipping_methods_dialog-tax_class").val("included").trigger("change");
        $("#shipping_methods_dialog-tax_value").val("").trigger("change");
        $("#shipping_methods_dialog-enabled").val("").trigger("change");
    }
    else
    {
        // edit, load row data
        for(r in navigate_shipping_methods_rates)
            if(navigate_shipping_methods_rates[r].id == row_id)
                shipping_method_rate = navigate_shipping_methods_rates[r];

        $("#shipping_methods_dialog-country").val(shipping_method_rate.country).trigger("change");
        $("#shipping_methods_dialog-regions-how").val("all").trigger("change");
        navigate_shipping_methods_regions_preselection = [];
        if(shipping_method_rate.regions.length > 0)
        {
            navigate_shipping_methods_regions_preselection = shipping_method_rate.regions;
            $("#shipping_methods_dialog-regions-how").val("selection").trigger("change"); // will force a refresh using the preselection
            //$("#shipping_methods_dialog-regions").val(shipping_method_rate.regions).trigger("change");
        }

        $("#shipping_methods_dialog-regions").val(shipping_method_rate.regions).trigger("change");
        $("#shipping_methods_dialog-weight_min").val(shipping_method_rate.weight.min).trigger("change");
        $("#shipping_methods_dialog-weight_max").val(shipping_method_rate.weight.max).trigger("change");
        $("#shipping_methods_dialog-weight_unit").val(shipping_method_rate.weight.unit).trigger("change");
        $("#shipping_methods_dialog-subtotal_min").val(shipping_method_rate.subtotal.min).trigger("change");
        $("#shipping_methods_dialog-subtotal_max").val(shipping_method_rate.subtotal.max).trigger("change");
        $("#shipping_methods_dialog-subtotal_currency").val(shipping_method_rate.subtotal.currency).trigger("change");
        $("#shipping_methods_dialog-cost").val(shipping_method_rate.cost.value).trigger("change");
        $("#shipping_methods_dialog-cost_currency").val(shipping_method_rate.cost.currency).trigger("change");
        $("#shipping_methods_dialog-tax_class").val(shipping_method_rate.tax.class).trigger("change");
        $("#shipping_methods_dialog-tax_value").val(shipping_method_rate.tax.value).trigger("change");
        if(shipping_method_rate.enabled)
            $("#shipping_methods_dialog-enabled").attr("checked", "checked")
        else
            $("#shipping_methods_dialog-enabled").removeAttr("checked")
    }

    $("#shipping_methods_rates_edit_dialog").dialog({
        title: navigate_lang_dictionary[170], // edit
        height: "auto",
        width: 860,
        modal: true,
        buttons: [
            {
                text: navigate_lang_dictionary[190], // ok
                click: function()
                {
                    // add a new row ?
                    if(!shipping_method_rate.id)
                    {
                        $("#shipping_methods_rates_table").append("<tr>");
                        var tr = $("#shipping_methods_rates_table tr:last");
                        var trid = new Date().getTime();
                        $(tr).attr("id", trid);

                        shipping_method_rate.id = trid;

                        console.log(tr);
                    }
                    else
                    {
                        // edit an existing row
                        var trid = shipping_method_rate.id;
                        var tr = $("#shipping_methods_rates_table tr[id="+trid+"]");
                        $(tr).empty();
                    }

                    // columns
                    //      country, regions, weight, subtotal, cost, tax, published, edit

                    // COUNTRY

                    var country_id = $("#shipping_methods_dialog-country").val();
                    var country_name = $("#shipping_methods_dialog-country option[value='"+country_id+"']").text();
                    shipping_method_rate.country = country_id;

                    $(tr).append("\<td\>");
                    $(tr).find("td:last").append(country_name);
                    //$(tr).find("td:last").append('<input type="hidden" name="country['+trid+']" value="'+country_id+'" />');

                    // REGIONS

                    var regions_codes = "";
                    var regions_names = [];

                    if( $("#shipping_methods_dialog-regions-how").val() == "selection" )
                    {
                        regions_codes = $("#shipping_methods_dialog-regions").val();
                        for(i in regions_codes)
                            regions_names.push($("#shipping_methods_dialog-regions option[value='"+regions_codes[i]+"']").text());
                        regions_names = regions_names.join(", ");
                    }

                    shipping_method_rate.regions = regions_codes;

                    $(tr).append("\<td\>");
                    $(tr).find("td:last").append(regions_names);
                    //$(tr).find("td:last").append('<input type="hidden" name="regions['+trid+']" value="'+regions_codes+'" />');
                    $(tr).find("td").css("vertical-align", "top");


                    // WEIGHT

                    var weight_min = $("#shipping_methods_dialog-weight_min").val() + 0;
                    var weight_max = $("#shipping_methods_dialog-weight_max").val() + 0;
                    var weight_unit = $("#shipping_methods_dialog-weight_unit").val();
                    var weight_unit_text = $("#shipping_methods_dialog-weight_unit option[value='"+weight_unit+"']").text();
                    shipping_method_rate.weight = {min: weight_min, max: weight_max, unit: weight_unit};

                    $(tr).append("\<td\>");
                    $(tr).find("td:last").append((weight_min!=0? weight_min : "&infin;") + " - " + (weight_max!=0? weight_max : "&infin;") + " " + weight_unit_text);
                    //$(tr).find("td:last").append('<input type="hidden" name="weight['+trid+']" value="'+weight_min+'#'+weight_max+'#'+weight_unit+'" />');
                    $(tr).find("td").css("vertical-align", "top");

                    
                    // SUBTOTAL

                    var subtotal_min = $("#shipping_methods_dialog-subtotal_min").val() + 0;
                    var subtotal_max = $("#shipping_methods_dialog-subtotal_max").val() + 0;
                    var subtotal_currency = $("#shipping_methods_dialog-subtotal_currency").val();
                    var subtotal_currency_text = $("#shipping_methods_dialog-subtotal_currency option[value='"+subtotal_currency+"']").text();
                    shipping_method_rate.subtotal = {min: subtotal_min, max: subtotal_max, currency: subtotal_currency};

                    $(tr).append("\<td\>");
                    $(tr).find("td:last").append((subtotal_min!=0? subtotal_min : "&infin;") + " - " + (subtotal_max!=0? subtotal_max : "&infin;") + " " + subtotal_currency_text);
                    //$(tr).find("td:last").append('<input type="hidden" name="subtotal['+trid+']" value="'+subtotal_min+'#'+subtotal_max+'#'+subtotal_currency+'" />');
                    $(tr).find("td").css("vertical-align", "top");


                    // COST

                    var cost = $("#shipping_methods_dialog-cost").val() + 0;
                    var cost_currency = $("#shipping_methods_dialog-cost_currency").val();
                    var cost_currency_text = $("#shipping_methods_dialog-cost_currency option[value='"+cost_currency+"']").text();
                    shipping_method_rate.cost = { value: cost, currency: cost_currency };

                    $(tr).append("\<td\>");
                    $(tr).find("td:last").append(cost + " " + cost_currency_text);
                    //$(tr).find("td:last").append('<input type="hidden" name="cost['+trid+']" value="'+cost+'#'+cost_currency+'" />');
                    $(tr).find("td").css("vertical-align", "top");


                    // TAX

                    var tax_class = $("#shipping_methods_dialog-tax_class").val();
                    var tax_value = $("#shipping_methods_dialog-tax_value").val();
                    shipping_method_rate.tax = { class: tax_class, value: tax_value };

                    $(tr).append("\<td\>");
                    $(tr).find("td:last").append((tax_class=="custom"? tax_value : $("#shipping_methods_dialog-tax_class option[value="+tax_class+"]").text()));
                    //$(tr).find("td:last").append('<input type="hidden" name="tax['+trid+']" value="'+tax_class+'#'+tax_value+'" />');


                    // ENABLED
                    var shipping_method_enabled = $("#shipping_methods_dialog-enabled").is(":checked");
                    $(tr).append("\<td\>");
                    shipping_method_rate.enabled = shipping_method_enabled;

                    if(shipping_method_enabled)
                        $(tr).find("td:last").append('<i class="fa fa-fw fa-lg fa-check"></i>');
                    else
                        $(tr).find("td:last").append('<i class="fa fa-fw fa-lg fa-eye-slash"></i>');
                    $(tr).find("td:last").css({"text-align": "center"});


                    // REMOVE
                    $(tr).append("\<td\>");
                    $(tr).find("td:last").append('<i class="fa fa-fw fa-lg fa-trash" data-action="remove"></i>');
                    $(tr).find("td:last").append('<input type="hidden" name="rate_details['+trid+']" value="" />');
                    $(tr).find("input:last").val($.base64.encode(JSON.stringify(shipping_method_rate)));
                    $(tr).find("td:last").css({"text-align": "center"});

                    $(tr).find("td").css("vertical-align", "top");

                    $("#shipping_methods_rates_table").tableDnD({
                       onDrop: function(table, row) {
                           navigate_naviorderedtable_shipping_methods_rates_table_reorder();
                       }
                    });

                    navigate_naviorderedtable_shipping_methods_rates_table_reorder();

                    $( this ).dialog( "close" );
                }
            },
            {
                text: navigate_lang_dictionary[58], // cancel
                click: function()
                {
                    $( this ).dialog( "close" );
                }
            }
        ]
    });
}

function navigate_shipping_methods_rates_table_remove(el)
{
    $(el).parent().parent().remove();
}

function navigate_naviorderedtable_shipping_methods_rates_table_reorder()
{
    var new_order = [];
    navigate_shipping_methods_rates = [];

    $("#shipping_methods_rates_table tr").not(".nodrag").each(function()
    {
        if($(this).find('input[name^="rate_details"]'))
        {
            new_order.push($(this).attr("id"));

            navigate_shipping_methods_rates.push(
                $.parseJSON(
                    $.base64.decode(
                        $(this).find('input[name^=rate_details]:first').val()
                    )
                )
            );
        }
    });

    $("#shipping_methods-order").val(new_order.join(","));
    $("#shipping_methods-rates").val($.base64.encode(JSON.stringify(navigate_shipping_methods_rates)));
}

function nv_shipping_methods_dialog_regions_how_change()
{
    $("#shipping_methods_dialog-regions-selection").hide();

    if($("#shipping_methods_dialog-country").val()!="")
    {
        $("#shipping_methods_dialog-regions-how").parent().show();

        if(navigate_shipping_methods_country_selected != $("#shipping_methods_dialog-country").val())
        {
            navigate_shipping_methods_country_selected = $("#shipping_methods_dialog-country").val();

            $("#shipping_methods_dialog-regions").empty();

            $.getJSON(
                "?fid=shipping_methods&act=json&oper=load_regions",
                {
                    country: navigate_shipping_methods_country_selected,
                    id: navigate_shipping_methods_object_id
                },
                function(data)
                {
                    $.each(data, function(i)
                    {
                        $("#shipping_methods_dialog-regions")
                            .append( $("<option></option>")
                                .attr("value", data[i].numeric)
                                .text(data[i].name)
                            );
                    });

                    $("#shipping_methods_dialog-regions").multiselect("refresh");

                    // autoselect regions, if any matches the pre-selection
                    if(navigate_shipping_methods_regions_preselection.length > 0)
                        $("#shipping_methods_dialog-regions").val(navigate_shipping_methods_regions_preselection).trigger("change");
                }
            );
        }

        if($("#shipping_methods_dialog-regions-how").val()=="selection")
        {
            $("#shipping_methods_dialog-regions-selection").show();
            $("#shipping_methods_dialog-regions").multiselect("refresh");
        }
    }
    else
    {
        $("#shipping_methods_dialog-regions-how").parent().hide();
    }
}