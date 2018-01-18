<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">CALCULAR FRETE</div>
    <div class="panel-body">
        <form name="calcular_frete" id="calcular_frete" action="{$url_shipping_quote}" method="post" />
        <input type="hidden" name="city-origin-id" value="{$city_origin_id}" />        
        <input type="hidden" id="city-destination-id" name="city-destination-id" value="{$city_origin_id}" />
        <input type="text" class="form-control ui-autocomplete-input" name="city-destination" placeholder="Cidade de destino" data-autocomplete-ajax-url="{$url_city_destination}" data-autocomplete-hidden-result="#city-destination-id" data-required-msg="Selecione uma cidade da lista" required autocomplete="off">
        <input type="hidden" name="product-type" value="{$product->name}" />
        <input type="hidden" name="product-total-price" id="product-total-price" data-value="{$product->price}" value="{$product->price}" />
        <input type="hidden" name="product-package[0][qtd]" value="1" />
        <input type="hidden" name="product-package[0][weight]" value="{number_format($product->weight, 2, ',', '')}" />
        <input type="hidden" name="product-package[0][height]" value="{number_format($product->height/100, 2, ',', '')}" />
        <input type="hidden" name="product-package[0][width]" value="{number_format($product->width/100, 2, ',', '')}" />
        <input type="hidden" name="product-package[0][depth]" value="{number_format($product->depth/100, 2, ',', '')}" />        
        <button class="btn btn-default" type="button" id="btCalcularFrete" data-loading-text="Carregando...">Calcular</button>
        </form>
        <div id="resultado-frete" style="padding-top:20px;">
            <table class="table" id="frete-valores">
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

{literal}        
    <script>        
        jQuery(function($){
 $(document).ready(function (){
        if ($('[name="fkcorreiosg2_cep"]') && 1 === 'a'){
        $('#calcular_frete,#box-frete-click').hide();
                $('.fkcorreiosg2-button').click(function(){$('#btCalcularFrete').click()});
        }        
                $('#resultado-frete').hide();
                $("#quantity_wanted").change(function(){
        $('[name="product-package[0][qtd]"]').attr('value', $(this).val());
                $('#product-total-price').attr('value', $('#product-total-price').data('value') * $(this).val()).replace('.', ',');
        });
                $('#btCalcularFrete').click(function (){
        var $btn = $(this).button('loading');
                $('#box-frete-click').show();
                $('#resultado-frete').hide();
                $("#frete-valores tbody").empty();
                var inputForm = $('#calcular_frete').serialize();
                $.ajax({
                url: $('#calcular_frete').attr('action'),
                        type: 'post',
                        dataType: 'json',
                        data: inputForm,
                        success: function(json){
                        if (json.response.success === true){
                        $.each(json.response.data.quote, function(index, val){
                        console.log(val);
                                $("#frete-valores tbody").append(addRowTableFrete(val['carrier-name'], val['carrier-logo'], val.total));
                        });
                                $('#resultado-frete').show('slow');
                        } else{
                        //erro
                        $("#frete-valores tbody").append(addRowError(json.response.error));
                                $('#resultado-frete').show('slow');
                        }
                        },
                        complete: function(){
                        $btn.button('reset');
                        }
                });
        });                                                                                                                
        });
        
        }); // FIM function

                function addRowTableFrete(nomeServico, imgLogo, valorServico)
                {
                return `
                        <tr>
                        <td>
                        <img src = "${imgLogo}" alt = "${nomeServico}" title = "${nomeServico}" width = "180" /> <br/>
                        <p> ${nomeServico} </p>
                        </td>
                        <td> ${valorServico} </td>
                        </tr>
                        `;
                }

        function addRowError(message)
        {
        return ` <tr>
                <td> ${message} </td>
                </tr>`;
        }

    </script>
{/literal}
