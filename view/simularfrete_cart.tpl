<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">Frete Click</div>
    <div class="panel-body">
        <form name="calcular_frete" id="calcular_frete" action="{$url_shipping_quote}" method="post" />
        <input type="hidden" name="city-origin-id" value="{$city_origin_id}" />
        <input type="hidden" name="product-type" value="{$cart_product_names}" />
        <input type="hidden" name="product-total-price" id="product-total-price" value="{$cart_total}" />
        <input type="text" id="fk-cep" value="{$cep}" onkeypress="maskCep(this, '#####-###')" maxlength="9" class="form-control" name="cep" placeholder="CEP de destino" required>
        {foreach key=key item=product from=$products}                        
            <input type="hidden" name="product-package[{$key}][qtd]" value="{$product['cart_quantity']}" />
            <input type="hidden" name="product-package[{$key}][weight]" value="{number_format($product['weight'], 10, ',', '')}" />
            <input type="hidden" name="product-package[{$key}][height]" value="{number_format($product['height']/100, 10, ',', '')}" />
            <input type="hidden" name="product-package[{$key}][width]" value="{number_format($product['width']/100, 10, ',', '')}" />
            <input type="hidden" name="product-package[{$key}][depth]" value="{number_format($product['depth']/100, 10, ',', '')}" />
        {/foreach}        
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