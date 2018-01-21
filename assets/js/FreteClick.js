function maskCep(t, mask) {
    var i = t.value.length;
    var saida = mask.substring(1, 0);
    var texto = mask.substring(i)
    if (texto.substring(0, 1) != saida) {
        t.value += texto.substring(0, 1);
    }
}
function addRowTableFrete(nomeServico, imgLogo, deadline, valorServico) {
    return '<tr><td><img src="'+imgLogo+'" alt="'+nomeServico+'" title="'+nomeServico+'" width = "180" /> <br/><p> '+nomeServico+' </p></td><td> Entrega em '+deadline+' dia(s) <br/> '+valorServico+' </td></tr>';
}

function addRowError(message) {
    return '<tr><td> '+message+' </td></tr>';
}

jQuery(function ($) {
    $(document).ready(function () {
        $.fn.extend({
            propAttr: $.fn.prop || $.fn.attr
        });
        $('#module_form').find("[data-autocomplete-ajax-url]").each(function () {
            var cache = {};
            var search = [];
            var t = this;
            $(this).autocomplete({
                minLength: 2,
                change: function (event, ui) {
                    var val = $(this).val();
                    var exists = $.inArray(val, search);
                    var show_result_field = $(this).data('autocomplete-hidden-result');
                    if (exists < 0) {
                        $(this).val("");
                        $(show_result_field).val("");
                        return false;
                    } else {
                        return true;
                    }
                },
                select: function (event, ui) {
                    search.push(ui.item.label);
                    $(this).val(ui.item.label);
                    var show_result_field = $(this).data('autocomplete-hidden-result');
                    $(show_result_field).val(ui.item.id);
                },
                source: function (request, response) {
                    var term = request.term;
                    if (term in cache) {
                        response(cache[ term ]);
                        return;
                    }
                    $.ajax({
                        beforeSend: function (xhr) {
                        },
                        complete: function (jqXHR, textStatus) {
                        },
                        url: $(t).data('autocomplete-ajax-url'),
                        data: request,
                        method: 'GET',
                        cache: true,
                        dataType: 'json',
                        success: function (data, status, xhr) {
                            var result = typeof data === 'object' && typeof data.response === 'object' && typeof data.response.data === 'object' ? data.response.data : null;
                            if (!result) {
                                console.log($(t).data('required-msg'));
                            }
                            cache[ term ] = result;
                            response(result);
                        }
                    });
                }});
        });


        if ($('[name="fkcorreiosg2_cep"]') && 1 === 'a') {
            $('#calcular_frete,#box-frete-click').hide();
            $('.fkcorreiosg2-button').click(function () {
                $('#btCalcularFrete').click()
            });
        }


        $('#resultado-frete').hide();
        $('#btCalcularFrete').click(function () {
            var $btn = $(this).button('loading');
            $('#resultado-frete').hide();
            $("#frete-valores tbody").empty();
            var inputForm = $('#calcular_frete').serialize();
            $.ajax({
                url: $('#calcular_frete').attr('action'),
                type: 'post',
                dataType: 'json',
                data: inputForm,
                success: function (json) {
                    if (json.response.success === true) {
                        jQuery.each(json.response.data.quote, function (index, val) {
                            $("#frete-valores tbody").append(addRowTableFrete(val['carrier-name'], val['carrier-logo'], val.deadline, val.total));
                        });
                        $('#resultado-frete').show('slow');
                    } else {
                        //erro
                        $("#frete-valores tbody").append(addRowError(json.response.error));
                        $('#resultado-frete').show('slow');
                    }
                },
                complete: function () {
                    $btn.button('reset');
                }
            });
        });




    });
}
);
