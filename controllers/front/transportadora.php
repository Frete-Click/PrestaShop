<?php

/**
 * Description of validatedoc
 *
 * @author Ederson Ferreira <ederson.dev@gmail.com>
 */
class FreteclickTransportadoraModuleFrontController extends ModuleFrontController {

    public function initContent() {
        global $cookie;

        $cookie->quote_id = filter_input(INPUT_POST, 'quote_id');
        $cookie->fc_nomeTransportadora = filter_input(INPUT_POST, 'nome_transportadora');
        $cookie->fc_valorFrete = filter_input(INPUT_POST, 'valor_frete');
        $this->chooseQuote();

        echo Tools::jsonEncode(['status' => true]);
        exit;
    }

    private function chooseQuote() {
        global $cookie;

        if (!$cookie->quote_id) {
            throw new Exception('Erro ao selecionar a cotação');
            return;
        }
        $ch = curl_init();        
        curl_setopt($ch, CURLOPT_URL, $this->module->url_choose_quote . '?' . http_build_query(array('quote' => $cookie->quote_id,'key' => Configuration::get('FC_API_KEY'))));
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);        
        $jsonData = $this->module->filterJson($resp);
        $arrData = json_decode($jsonData);
        if ($arrData->response->success === false) {
            throw new Exception('Erro ao selecionar a cotação');
        }        
        return true;
    }

}
