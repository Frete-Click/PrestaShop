/**
 * Módulo para o calculo do frete usando o webservice do FreteClick
 *  @author    Ederson Ferreira (ederson.dev@gmail.com)
 *  @copyright 2010-2015 FreteClick
 *  @license   LICENSE
 */
function maskCep(t, mask) {
    var i = t.value.length;
    var saida = mask.substring(1, 0);
    var texto = mask.substring(i)
    if (texto.substring(0, 1) != saida) {
        t.value += texto.substring(0, 1);
    }
}
function addRowTableFrete(nomeServico, imgLogo, deadline, valorServico) {
    return '<tr><td><img src="' + imgLogo + '" alt="' + nomeServico + '" title="' + nomeServico + '" width = "180" /> <br/><p> ' + nomeServico + ' </p></td><td> Entrega em ' + deadline + ' dia(s) <br/> ' + valorServico + ' </td></tr>';
}

function addRowError(message) {
    return '<tr><td> ' + message + ' </td></tr>';
}

jQuery(function ($) {
    $(document).ready(function () {
        $.fn.extend({
            propAttr: $.fn.prop || $.fn.attr
        });
        $("[data-field-qty=qty],.cart_quantity_button a").click(function () {
            setTimeout(function () {
                $("#quantity_wanted,.cart_quantity input").trigger('change');
            }, 300);
        });
        $("#quantity_wanted").change(function () {
            $('[name="product-package[0][qtd]"]').attr('value', $(this).val());
            var price = $('#product-total-price').data('value') * $(this).val();
            $('#product-total-price').attr('value', price.toString().replace('.', ','));
        });

        $('#fk-cep').keydown(function (event) {
            if (event.keyCode == 13) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                return false;
            }
        });

        $('#btCalcularFrete').click(function () {
            var $btn = $(this).button('loading');
            $('#resultado-frete').hide();
            $("#frete-valores tbody").empty();
            var inputForm = $('#calcular_frete').serialize();
            $.ajax({
                url: $('#calcular_frete').attr('data-action'),
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
						if (typeof json.response.error == "string"){
							$("#frete-valores tbody").append(addRowError(json.response.error));
						}
						else if (typeof json.response.error == "object" ){
							var erros = json.response.error;
							if (erros.length > 0) {
								for (var i = 0; i < erros.length; i++){
									$("#frete-valores tbody").append(addRowError(erros[i].message));
								}
							}
							else{
								$("#frete-valores tbody").append(addRowError(erros.message));
							}
						}
						$('#resultado-frete').show('slow');
                    }
                },
                complete: function () {
                    $btn.button('reset');
                }
            });
        });

        $('#resultado-frete').hide();
        if ($('[name="fkcorreiosg2_cep"]').length > 0) {
            $(".cart_quantity input").change(function () {
                setTimeout(function () {
                    $('.fkcorreiosg2-button').trigger('click');
                }, 3000);
            });
            $('#calcular_frete,#box-frete-click').hide();
            $('[name="fkcorreiosg2_cep"]').change(function () {
                $('#fk-cep').val($('[name="fkcorreiosg2_cep"]').val());
            });
            $('#fk-cep').val($('[name="fkcorreiosg2_cep"]').val());
            if ($('#fk-cep').val().length == 9) {
                $('#btCalcularFrete').click();
                $('#box-frete-click').show();
            }
            $('.fkcorreiosg2-button').click(function () {
                $('#btCalcularFrete').click();
                $('#box-frete-click').show();
            });

            $("#quantity_wanted").change(function () {
                setTimeout(function () {
                    $('#btCalcularFrete').trigger('click');
                }, 1000);
            });
        }



        /*
         delivery_option_radio
         $(button).prop('disabled', true);
         */

        $('input[name="fc_transportadora"]').click(function () {
            var button = $('[name="processCarrier"]');
            var fprice = $(this).attr('data-fprice'),
                    nome_transportadora = $(this).attr('data-name'),
                    module_name = $('#module_name').val();
            var descricao = '<strong>' + module_name + '</strong><br/>' + 'Transportadora:' + nome_transportadora + '<br/>';
            $.ajax({
                url: $('#url_transportadora').val(),
                type: "post",
                dataType: "json",
                data: {
                    quote_id: $(this).val(),
                    nome_transportadora: nome_transportadora,
                    valor_frete: $(this).attr('data-price')
                },
                success: function (json) {
                    if (json.status === true) {
                        $('.delivery_option_radio:checked').closest('tr').find('td.delivery_option_price').prev().html(descricao);
                        $('.delivery_option_radio:checked').closest('tr').find('td.delivery_option_price').html(fprice);
                    }
                }
            });
        });

        $(document).on('submit', 'form[name=carrier_area]', function () {
            var valTransportadora = $('input[name="fc_transportadora"]:checked').length;
            if (valTransportadora === 0 && $('input[name="fc_transportadora"]').length) {
                alert('Selecione uma transportadora');
                return false;
            }
        });
		
		$(".fc-input-cep").keypress(function (event) {
			maskCep(this, "#####-###");
		});
		
		//ViaCep
		var tb_cep, tb_rua, tb_cidade, tb_bairro, se_estado, span_estado, tb_pais;

		tb_cep = document.getElementById("cep-origin") ? document.getElementById("cep-origin") : document.getElementById("fk-cep");
		tb_rua = document.getElementById("street-origin") ? document.getElementById("street-origin") : document.getElementById("street-destination");
		tb_cidade = document.getElementById("city-origin") ? document.getElementById("city-origin") : document.getElementById("city-destination");
		tb_bairro = document.getElementById("district-origin") ? document.getElementById("district-origin") : document.getElementById("district-destination");
		se_estado = document.getElementById("state-origin") ? document.getElementById("state-origin") : document.getElementById("state-destination");
		tb_pais = document.getElementById("country-origin") ? document.getElementById("country-origin") : document.getElementById("country-destination");
		if (tb_pais){
			tb_pais.value = "Brasil";
		}
		if (tb_cep){
		 var tb_cep_keyup = function(){
			 var reseta = function () {
			   tb_cep.disabled = false;
			   tb_rua.disabled = false;
			   tb_cidade.disabled = false;
			   tb_bairro.disabled = false;
			   se_estado.disabled = false;			 
			 };
		  var num = this.value.length;
		  if (num == 9){
		   tb_cep.disabled = true;
		   tb_rua.disabled = true;
		   tb_cidade.disabled = true;
		   tb_bairro.disabled = true;
		   se_estado.disabled = true;
		   
		   $.ajax({
			url: "https://viacep.com.br/ws/"+tb_cep.value+"/json/",
			data: null,
			success: function (data) {
			  if (!data.erro){
			  tb_rua.value = data.logradouro;
			  tb_cidade.value = data.localidade;
			  tb_bairro.value = data.bairro;
			  se_estado.value = data.uf;
			  }
			  reseta();
			},
			dataType: "json"
		   });
		  }
		  else{
			  reseta();
		  }
		 }
		 
		 tb_cep.onkeyup = tb_cep_keyup;
		}

    });
}
);