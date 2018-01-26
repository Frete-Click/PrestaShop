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
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array_merge($_POST, array('city-destination-id' => $city_destination_id, 'key' => Configuration::get('FC_API_KEY')))));
                $resp = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $arrJson = $this->filterJson($resp);
                $arrJson = $this->orderByPrice($this->calculaPrecoPrazo($_POST, $arrJson));

                foreach ($arrJson->response->data->quote as $key => $quote) {
                    $quote_price = number_format($quote->total, 2, ',', '.');
                    $arrJson->response->data->quote[$key]->total = "R$ {$quote_price}";
                }
                echo Tools::jsonEncode($arrJson);
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

    public function calculaDimencoesCorreios($data) {
        foreach ($data['product-package'] AS $p) {
            $total += $p['qtd'] * (str_replace(',', '.', $p['height']) * 100) * (str_replace(',', '.', $p['width']) * 100) * (str_replace(',', '.', $p['depth']));
        }
        $raiz = pow($total, (1 / 3)) * 100;
        $data['width'] = round($raiz);
        $data['height'] = round($raiz);
        $data['depth'] = round($raiz);
        return $data;
    }

    public function calculaPrecoPrazo($data, $arrJson) {
        $data = $this->calculaDimencoesCorreios($data);        
        $dados = array(
            '4510' => 'PAC',
            '4014' => 'SEDEX'
        );
        $parm = array(
            'nCdEmpresa' => $this->empresa,
            'sDsSenha' => $this->senha,
            'nCdServico' => implode(',', array_keys($dados)),
            'sCepOrigem' => $data['cep'],
            'sCepDestino' => $data['cep'],
            'nVlPeso' => 10,
            'nCdFormato' => 1,
            'nVlComprimento' => (string) ($data['depth'] > 16?$data['depth'] : 16),
            'nVlAltura' => (string) ($data['height'] > 2? $data['height']: 2),
            'nVlLargura' => (string) ($data['width'] > 16?$data['width'] : 16),
            'nVlDiametro' => '0',
            'sCdMaoPropria' => 'n',
            'nVlValorDeclarado' => $data['product-total-price'],
            'sCdAvisoRecebimento' => 'n'
        );

        try {
            $ws = new SoapClient($this->module->url_api_correios);
            $arrayRetorno = $ws->CalcPrecoPrazo($parm);
            $retornos = $arrayRetorno->CalcPrecoPrazoResult->Servicos->cServico;
            foreach ($retornos as $retorno) {
                if (!$retorno->MsgErro) {
                    $arrJson->response->data->quote[] = array(
                        "carrier-alias" => $dados[$retorno->Codigo],
                        "carrier-logo" => $this->module->path . 'assets/img/' . $dados[$retorno->Codigo] . '.png',
                        "carrier-name" => $dados[$retorno->Codigo],
                        "deadline" => $retorno->PrazoEntrega,
                        "delivery-restricted" => false,
                        "logo" => $this->module->path . 'assets/img/' . $dados[$retorno->Codigo] . '.png',
                        'total' => $retorno->Valor
                    );
                }
            }            
            return $arrJson;
        } catch (Exception $e) {
            return $arrJson;
        }
    }

    public function orderByPrice($arrJson) {
        $quotes = (array) $arrJson->response->data->quote;
        usort($quotes, function($a, $b) {
            return $a->total > $b->total;
        });
        $arrJson->response->data->quote = $quotes;
        return $arrJson;
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
        return $arrJson;
    }

}
