<?php
/**
 * Description of validatedoc
 *
 * @author Ederson Ferreira <ederson.dev@gmail.com>
 */
class FreteclickCitydestinationModuleFrontController extends ModuleFrontController
{
  public function initContent()
  {
    $arrRetorno = array();
    try{
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->module->url_city_destination.'?'.http_build_query($_GET));
      curl_setopt($ch, CURLOPT_HTTPGET, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);      
      $resp = curl_exec($ch);
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      echo $this->filterJson($resp);exit;
    }catch(Exception $ex){
      $arrRetorno = array(
        'response' => array('success' => false, 'error' => $ex->getMessage())
      );
      echo Tools::jsonEncode($arrRetorno);exit;
    }
  }

  public function filterJson($json)
  {
    $arrJson = Tools::jsonDecode($json);
    if(!$arrJson){
      $this->module->addError('Erro ao recuperar dados');
    }
    if($arrJson->response->success === false){
      if($arrJson->response->error){
        foreach($arrJson->response->error as $error){
          $this->module->addError($error->message);
        }
      }
      $this->module->addError('Erro ao recuperar dados');
    }        
    return Tools::jsonEncode($this->module->getErrors()?:$arrJson);
  }
}