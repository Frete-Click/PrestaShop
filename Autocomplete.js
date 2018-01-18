jQuery(function ($) {
    $(document).ready(function () {
        $.fn.extend({
            propAttr: $.fn.prop || $.fn.attr
        });
        $('#box-frete-click,#module_form').find("[data-autocomplete-ajax-url]").each(function () {
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
    });
});