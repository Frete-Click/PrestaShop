{*
 * Módulo para o calculo do frete usando o webservice do FreteClick
 *  @author    Ederson Ferreira (ederson.dev@gmail.com)
 *  @copyright 2010-2015 FreteClick
 *  @license   LICENSE
 *}
<div id="box-frete-click" class="panel panel-info">
    <div class="panel-heading">Frete Click</div>
    <div class="panel-body">
        <form name="calcular_frete" id="calcular_frete" data-action="{$url_shipping_quote|escape:'htmlall':'UTF-8'}" method="post" />
        <input type="hidden" name="city-origin" value="{$city_origin|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="cep-origin" value="{$cep_origin|escape:'htmlall':'UTF-8'}" />
		<input type="hidden" name="street-origin" value="{$street_origin|escape:'htmlall':'UTF-8'}" />            
        <input type="hidden" name="address-number-origin" value="{$number_origin|escape:'htmlall':'UTF-8'}" />            
        <input type="hidden" name="complement-origin" value="{$complement_origin|escape:'htmlall':'UTF-8'}" />            
        <input type="hidden" name="district-origin" value="{$district_origin|escape:'htmlall':'UTF-8'}" />      
        <input type="hidden" name="state-origin" value="{$state_origin|escape:'htmlall':'UTF-8'}" />      
        <input type="hidden" name="country-origin" value="{$country_origin|escape:'htmlall':'UTF-8'}" /> 
        <input type="hidden" name="product-type" value="{$product_name|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" name="product-total-price" id="product-total-price" value="{$cart->getordertotal(false)|escape:'htmlall':'UTF-8'}" />
        <input type="text" id="fk-cep" value="{$cep|escape:'htmlall':'UTF-8'}" onkeypress="maskCep(this, '#####-###')" maxlength="9" class="form-control" name="cep-destination" placeholder="CEP de destino" required>
		<input type="hidden" id="city-destination" name="city-destination" value="" />             
        <input type="hidden" id="street-destination" name="street-destination" value="" />            
        <input type="hidden" id="number-destination" name="address-number-destination" value="1" />            
        <input type="hidden" id="complement-destination" name="complement-destination" value="" />            
        <input type="hidden" id="district-destination" name="district-destination" value="" />      
        <input type="hidden" id="state-destination" name="state-destination" value="" />      
        <input type="hidden" id="country-destination" name="country-destination" value="" /> 
        {foreach key=key item=product from=$products}                        
            <input type="hidden" name="product-package[{$key|escape:'htmlall':'UTF-8'}][qtd]" value="{$product['cart_quantity']}" />
            <input type="hidden" name="product-package[{$key|escape:'htmlall':'UTF-8'}][weight]" value="{number_format($product['weight'], 10, ',', '')}" />
            <input type="hidden" name="product-package[{$key|escape:'htmlall':'UTF-8'}][height]" value="{number_format($product['height']/100, 10, ',', '')}" />
            <input type="hidden" name="product-package[{$key|escape:'htmlall':'UTF-8'}][width]" value="{number_format($product['width']/100, 10, ',', '')}" />
            <input type="hidden" name="product-package[{$key|escape:'htmlall':'UTF-8'}][depth]" value="{number_format($product['depth']/100, 10, ',', '')}" />
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