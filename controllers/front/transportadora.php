<?php
/**
 * Description of validatedoc
 *
 * @author Ederson Ferreira <ederson.dev@gmail.com>
 */

class FreteclickTransportadoraModuleFrontController extends ModuleFrontController
{
  public function initContent()
  {
    global $cookie;
    
    $cookie->quote_id = filter_input(INPUT_POST,'fc_transportadora');
    $cookie->fc_nomeTransportadora = filter_input(INPUT_POST,'nome_transportadora');
    $cookie->fc_valorFrete = filter_input(INPUT_POST,'valor_frete');

    echo Tools::jsonEncode(['status' => true]);exit;
  }
}