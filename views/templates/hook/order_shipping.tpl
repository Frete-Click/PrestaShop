{*
 * Módulo para o calculo do frete usando o webservice do FreteClick
 *  @author    Ederson Ferreira (ederson.dev@gmail.com)
 *  @copyright 2010-2015 FreteClick
 *  @license   LICENSE
 *}
{if $carrier_checked eq $fc_carrier_id}
  <div class="box">
    {if isset($error_message)}
      <h3>{$error_message|escape:'htmlall':'UTF-8}</h3>
    {else}
      <input type="hidden" name="url_transportadora" id="url_transportadora" value="{$url_transportadora|escape:'htmlall':'UTF-8}" />
      <p><strong>Lista de transportadoras do módulo {$display_name|escape:'htmlall':'UTF-8}</strong></p>
      <table class="table fctransportadoras" id="fc-transportadoras">
        <caption>Selecione uma transportadora</caption>
        <thead>
          <tr>
            <th>#</th>
            <th>Transportadora</th>
            <th>Prazo estimado de entrega</th>
            <th>Valor</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$arr_transportadoras->response->data->quote item=transportadora}
          <tr>
            <td>
              <input type="radio" name="fc_transportadora" value="{$transportadora->{'quote-id'}|escape:'htmlall':'UTF-8}" 
                data-price="{$transportadora->raw_total|escape:'htmlall':'UTF-8}" 
                data-name="{$transportadora->{'carrier-name'}|escape:'htmlall':'UTF-8}" 
                data-fprice="{$transportadora->total|escape:'htmlall':'UTF-8}"
                data-desc="">
            </td>
            <td>
              <img src="{$transportadora->{'carrier-logo'}|escape:'htmlall':'UTF-8}" alt="{$transportadora->{'carrier-name'}|escape:'htmlall':'UTF-8}" title="{$transportadora->{'carrier-name'}|escape:'htmlall':'UTF-8}" width="180" /><br />
              {$transportadora->{'carrier-name'}|escape:'htmlall':'UTF-8}
            </td>
            <td>{$transportadora->deadline|escape:'htmlall':'UTF-8} dia(s)</td>
            <td>{$transportadora->total|escape:'htmlall':'UTF-8}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>
      
    {/if}
  </div>
<input type="hidden" name="module_name" id="module_name" value="{$display_name|escape:'htmlall':'UTF-8}" />
{/if}