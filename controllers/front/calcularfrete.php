<?php

/**
 * Description of validatedoc
 *
 * @author Ederson Ferreira <ederson.dev@gmail.com>
 */
class FreteclickCalcularfreteModuleFrontController extends ModuleFrontController {

    public function initContent() {
        $arrRetorno = array();
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->module->url_shipping_quote);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array_merge($_POST, array('key' => Configuration::get('FC_API_KEY')))));
            $resp = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            echo $this->filterJson($resp);
            exit;
        } catch (Exception $ex) {
            $arrRetorno = array(
                'response' => array('success' => false, 'error' => $ex->getMessage())
            );
            echo Tools::jsonEncode($arrRetorno);
            exit;
        }
    }

    public function filterJson($json) {
        $arrJson = Tools::jsonDecode($json);
        if (!$arrJson) {
            throw new Exception('Erro ao recuperar dados');
        }
        if ($arrJson->response->success === false) {
            if ($arrJson->response->error) {
                foreach ($arrJson->response->error as $error) {
                    throw new Exception($error->message);
                }
            }
            throw new Exception('Erro ao recuperar dados');
        }
        foreach ($arrJson->response->data->quote as $key => $quote) {
            $quote_price = number_format($quote->total, 2, ',', '.');
            $arrJson->response->data->quote[$key]->total = "R$ {$quote_price}";
        }
        return Tools::jsonEncode($arrJson);
    }

}
