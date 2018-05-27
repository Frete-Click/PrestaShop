<?php
/**
 *  Módulo para o calculo do frete usando o webservice do FreteClick
 *  @author    Ederson Ferreira (ederson.dev@gmail.com)
 *  @copyright 2010-2015 FreteClick
 *  @license   LICENSE
 */
 
class FreteclickTransportadoraModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->module->cookie->quote_id = filter_input(INPUT_POST, 'quote_id');
        $this->module->cookie->fc_nomeTransportadora = filter_input(INPUT_POST, 'nome_transportadora');
        $this->module->cookie->fc_valorFrete = filter_input(INPUT_POST, 'valor_frete');
        $this->module->cookie->write();
        try {
            $this->chooseQuote();
            echo Tools::jsonEncode(array('status' => true));
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
        exit;
    }

    private function chooseQuote()
    {
        if ($this->module->cookie->quote_id) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->module->url_choose_quote . '?' . http_build_query(
                array(
                    'quote' => $this->module->cookie->quote_id,
                    'api-key' => Configuration::get('FC_API_KEY')
                )
            ));
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $resp = curl_exec($ch);
            curl_close($ch);
            $arrData = $this->module->filterJson($resp);
            if ($arrData->response->success === false) {
                $this->module->addError('Erro ao selecionar a cotação');
                return;
            }
        }
        return true;
    }
}
