<div id="box-frete-correios" class="panel panel-info">
  <div class="panel-heading">CALCULAR FRETE</div>
  <div class="panel-body">
    <form name="calcular_frete" id="calcular_frete" action="{$url_shipping_quote}" method="post" />
      
      
      <input type="hidden" name="city-origin-id" value="{$city_origin_id}" />
      <input type="hidden" name="product-type" value="{$product->name}" />
      <input type="hidden" name="product-total-price" id="product-total-price" value="{$product->price}" />
      <input type="hidden" name="product-package[0][qtd]" value="1" />
      <input type="hidden" name="product-package[0][weight]" value="{round($product->weight,2)}" />
      <input type="hidden" name="product-package[0][height]" value="{round($product->height/100,2)}" />
      <input type="hidden" name="product-package[0][width]" value="{round($product->width/100,2)}" />
      <input type="hidden" name="product-package[0][depth]" value="{round($product->depth/100,2)}" />

      <div class="form-group">
        <select name="city-destination-id" id="city-destination-id" class="form-control chosen-select">
          {foreach $arrCityDestination as $city}
            <option value="{$city['id_option']}">{$city['name']}</option>
          {/foreach}
        </select>
      </div>

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
    $(".chosen-select").chosen();
    $('#resultado-frete').hide();

    $('#btCalcularFrete').click(function (){
      var $btn = $(this).button('loading');
      $('#resultado-frete').hide();
      $( "#frete-valores tbody" ).empty();

      var inputForm = $('#calcular_frete').serialize();
      $.ajax({
        url: $('#calcular_frete').attr('action'),
        type: 'post',
        dataType: 'json',
        data: inputForm,
        success: function(json){
          if(json.response.success === true){
            jQuery.each(json.response.data.quote,function(index,val){
              console.log(val);
              $("#frete-valores tbody").append(addRowTableFrete(val['carrier-name'],val['carrier-logo'],val.total));
            });
            $('#resultado-frete').show('slow');
          }else{
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

  });// FIM function

  function addRowTableFrete(nomeServico, imgLogo, valorServico)
  {
    return `
      <tr>
        <td>
          <img src="${imgLogo}" alt="${nomeServico}" title="${nomeServico}" width="180" /><br />
          <p>${nomeServico}</p>
        </td>
        <td>${valorServico}</td>
      </tr>
    `;
  }

  function addRowError(message)
  {
    return `
      <tr>
        <td>${message}</td>
      </tr>
    `;
  }

</script>
{/literal}
