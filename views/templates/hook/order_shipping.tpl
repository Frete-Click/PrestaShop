{if $carrier_checked eq $fc_carrier_id}
  <div class="box">
    {if isset($error_message)}
      <h3>{$error_message}</h3>
    {else}
      <input type="hidden" name="url_transportadora" id="url_transportadora" value="{$url_transportadora}" />
      <p><strong>Lista de transportadoras do módulo {$display_name}</strong></p>
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
              <input type="radio" name="fc_transportadora" value="{$transportadora->{'quote-id'}}" 
                data-price="{$transportadora->raw_total}" 
                data-name="{$transportadora->{'carrier-name'}}" 
                data-fprice="{$transportadora->total}"
                data-desc="">
            </td>
            <td>
              <img src="{$transportadora->{'carrier-logo'}}" alt="{$transportadora->{'carrier-name'}}" title="{$transportadora->{'carrier-name'}}" width="180" /><br />
              {$transportadora->{'carrier-name'}}
            </td>
            <td>{$transportadora->deadline} dia(s)</td>
            <td>{$transportadora->total}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>
      
    {/if}
  </div>
<input type="hidden" name="module_name" id="module_name" value="{$display_name}" />
{/if}