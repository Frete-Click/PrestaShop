<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">CALCULAR FRETE</div>
    <div class="panel-body">
        <form name="calcular_frete" id="calcular_frete" action="{$url_shipping_quote}" method="post" />
        <input type="hidden" name="city-origin-id" value="{$city_origin_id}" />                
        <input type="text" id="fk-cep" onkeypress="maskCep(this, '#####-###')" maxlength="9" class="form-control" name="cep" placeholder="CEP de destino" required>
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