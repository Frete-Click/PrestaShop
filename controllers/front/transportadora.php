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
        try {
            $this->chooseQuote();
            echo Tools::jsonEncode(['status' => true]);
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
        exit;
    }

    private function chooseQuote() {
        global $cookie;
        if ($cookie->quote_id) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->module->url_choose_quote . '?' . http_build_query(array('quote' => $cookie->quote_id, 'key' => Configuration::get('FC_API_KEY'))));
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $resp = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $arrData = $this->module->filterJson($resp);            
            if ($arrData->response->success === false) {
                throw new Exception('Erro ao selecionar a cotação');
            }
        }
        return true;
    }

}
