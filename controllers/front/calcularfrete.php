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
            $city_destination_id = $this->getCity();
            
            if ($city_destination_id) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->module->url_shipping_quote);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array_merge($_POST, array('city-destination-id'=>$city_destination_id,'key' => Configuration::get('FC_API_KEY')))));
                $resp = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                echo $this->filterJson($resp);
            }
            exit;
        } catch (Exception $ex) {
            $arrRetorno = array(
                'response' => array('success' => false, 'error' => $ex->getMessage())
            );
            echo Tools::jsonEncode($arrRetorno);
            exit;
        }
    }

    public function getCity() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->module->url_search_city_from_cep . '?' . http_build_query($_POST, array('key' => Configuration::get('FC_API_KEY'))));
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $arrJson = Tools::jsonDecode($resp);
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
        if ($arrJson->response->data->id) {
            return $arrJson->response->data->id;
        } else {
            throw new Exception('Cidade não encontrada à partir deste CEP');
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
