<?php

/**
 * Módulo para o calculo do frete usando o webservice do FreteClick
 * @author Ederson Ferreira (ederson.dev@gmail.com)
 * http://freteclick.com.br/carrier/search-city-origin.json
 * http://freteclick.com.br/carrier/search-city-destination.json
 * 
 */
// Avoid direct access to the file
if (!defined('_PS_VERSION_'))
    exit;

class freteclick extends CarrierModule {

    public $id_carrier;
    private $_html = '';
    private $_postErrors = array();
    public $url_shipping_quote;
    public $url_city_origin;
    public $url_city_destination;
    public $url_search_city_from_cep;
    public $url_choose_quote;

    public function __construct() {
        $this->name = 'freteclick';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0';
        $this->author = 'Ederson Ferreira';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('FreteClick');
        $this->description = $this->l('Calculo do frete com o serviço web FreteClick');

        if (self::isInstalled($this->name)) {
            // Verifica se a cidade de origem foi selecionada
            if (!Configuration::get('FC_CITY_ORIGIN')) {
                $this->warning = $this->l('A cidade de origem precisa estar configurada no módulo');
            }
        }
        $this->url_shipping_quote = 'https://www.freteclick.com.br/sales/shipping-quote.json';
        $this->url_city_origin = 'https://www.freteclick.com.br/carrier/search-city-origin.json';
        $this->url_city_destination = 'https://www.freteclick.com.br/carrier/search-city-destination.json';
        $this->url_search_city_from_cep = 'https://www.freteclick.com.br/carrier/search-city-from-cep.json';
        $this->url_choose_quote = 'https://www.freteclick.com.br/sales/choose-quote.json';
    }

    public function install() {
        $carrierConfig = array(
            'name' => 'FreteClick',
            'id_tax_rules_group' => 0,
            'active' => true,
            'deleted' => 0,
            'shipping_handling' => false,
            'range_behavior' => 0,
            'delay' => array(Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')) => 'Selecione a transportadora desejada'),
            'id_zone' => 1,
            'is_module' => true,
            'shipping_external' => true,
            'external_module_name' => 'freteclick',
            'need_range' => true,
            'logo_img' => 'logo_carrier.jpg'
        );
        $idCarrier = $this->installExternalCarrier($carrierConfig);
        Configuration::updateValue('FC_CARRIER_ID', (int) $idCarrier);

        if (!parent::install() || !Configuration::updateValue('FC_INFO_PROD', 1) || !Configuration::updateValue('FC_SHOP_CART', 1) || !$this->registerHook('updateCarrier') || !$this->registerHook('DisplayRightColumnProduct') || !$this->registerHook('displayShoppingCartFooter') || !$this->registerHook('extraCarrier') || !$this->registerHook('OrderConfirmation') || !Configuration::updateValue('FC_CITY_ORIGIN', '')) {
            return false;
        }

        return true;
    }

    public function uninstall() {
        global $cookie;

        if (!parent::uninstall() || !Configuration::deleteByName('FC_INFO_PROD') || !Configuration::deleteByName('FC_SHOP_CART') || !Configuration::deleteByName('FC_CITY_ORIGIN') || !$this->unregisterHook('updateCarrier') || !$this->unregisterHook('extraCarrier') || !$this->unregisterHook('DisplayRightColumnProduct') || !$this->unregisterHook('OrderConfirmation') || !$this->unregisterHook('displayShoppingCartFooter')) {
            return false;
        }
        $objFC = new Carrier((int) (Configuration::get('FC_CARRIER_ID')));
        if (Configuration::get('PS_CARRIER_DEFAULT') == (int) ($objFC->id)) {
            $carriersD = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
            foreach ($carriersD as $carrierD) {
                if ($carrierD['active'] AND ! $carrierD['deleted'] AND ( $carrierD['name'] != $this->_config['name'])) {
                    Configuration::updateValue('PS_CARRIER_DEFAULT', $carrierD['id_carrier']);
                }
            }
        }

        $objFC->deleted = 1;
        if (!$objFC->update()) {
            return false;
        }

        return true;
    }

    public static function installExternalCarrier($config) {
        $carrier = new Carrier();
        $carrier->name = $config['name'];
        $carrier->id_tax_rules_group = $config['id_tax_rules_group'];
        $carrier->id_zone = $config['id_zone'];
        $carrier->active = $config['active'];
        $carrier->deleted = $config['deleted'];
        $carrier->delay = $config['delay'];
        $carrier->shipping_handling = $config['shipping_handling'];
        $carrier->range_behavior = $config['range_behavior'];
        $carrier->is_module = $config['is_module'];
        $carrier->shipping_external = $config['shipping_external'];
        $carrier->external_module_name = $config['external_module_name'];
        $carrier->need_range = $config['need_range'];

        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            if ($language['iso_code'] == Language::getIsoById(Configuration::get('PS_LANG_DEFAULT'))) {
                $carrier->delay[(int) $language['id_lang']] = $config['delay'][$language['iso_code']];
            }
        }

        if ($carrier->add()) {
            $groups = Group::getGroups(true);
            foreach ($groups as $group) {
                Db::getInstance()->autoExecute(_DB_PREFIX_ . 'carrier_group', array('id_carrier' => (int) ($carrier->id), 'id_group' => (int) ($group['id_group'])), 'INSERT');
            }

            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '10000';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '30';
            $rangeWeight->add();

            $zones = Zone::getZones(true);
            foreach ($zones as $zone) {
                Db::getInstance()->autoExecute(_DB_PREFIX_ . 'carrier_zone', array('id_carrier' => (int) ($carrier->id), 'id_zone' => (int) ($zone['id_zone'])), 'INSERT');
                Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery', array('id_carrier' => (int) ($carrier->id), 'id_range_price' => (int) ($rangePrice->id), 'id_range_weight' => NULL, 'id_zone' => (int) ($zone['id_zone']), 'price' => '0'), 'INSERT');
                Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery', array('id_carrier' => (int) ($carrier->id), 'id_range_price' => NULL, 'id_range_weight' => (int) ($rangeWeight->id), 'id_zone' => (int) ($zone['id_zone']), 'price' => '0'), 'INSERT');
            }

            // Copy Logo
            if (!copy(dirname(__FILE__) . '/assets/img/' . $config['logo_img'], _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg')) {
                return false;
            }

            // Return ID Carrier
            return (int) ($carrier->id);
        }

        return false;
    }

    public function getContent() {        
        $this->context->controller->addJS($this->_path . 'assets/js/FreteClick.js');
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postProcess();

            if (count($this->_postErrors)) {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }
        $this->_html .= $this->renderForm();
        $this->_html = preg_replace('/FC_CITY_ORIGIN_NAME/i', 'FC_CITY_ORIGIN_NAME" data-autocomplete-hidden-result="#FC_CITY_ORIGIN" data-autocomplete-ajax-url="' . $this->context->link->getModuleLink('freteclick', 'cityorigin'), $this->_html);
        return $this->_html;
    }

    public function renderForm() {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuração FreteClick')
                ),
                'input' => array(
                    array(
                        'type' => 'hidden',
                        'name' => 'FC_CITY_ORIGIN'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Cidade de origem'),
                        'hint' => $this->l('Digite a cidade onde a mercadoria será coletada'),
                        'name' => 'FC_CITY_ORIGIN_NAME',
                        'id' => 'city-origin',
                        'required' => true,
                        'class' => 'form-control ui-autocomplete-input'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Chave de API'),
                        'hint' => $this->l('Digite a chave de API encontrada em seu painel http://www.freteclick.com.br'),
                        'name' => 'FC_API_KEY',
                        'required' => true,
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Informação do Produto'),
                        'hint' => $this->l('Exibe o box de cotação de frete na tela de descrição do produto.'),
                        'name' => 'FC_INFO_PROD',
                        'required' => true,
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'fcip_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'fcip_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Carrinho de compras'),
                        'hint' => $this->l('Exibe o box de cotação de frete na tela Carrinho de compras.'),
                        'name' => 'FC_SHOP_CART',
                        'required' => true,
                        'class' => 't',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'fcsc_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'fcsc_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        )
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save')
                )
            )
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    private function getConfigFieldsValues() {
        $values = array(
            'FC_CITY_ORIGIN' => Tools::getValue('FC_CITY_ORIGIN', Configuration::get('FC_CITY_ORIGIN')),
            'FC_CITY_ORIGIN_NAME' => Tools::getValue('FC_CITY_ORIGIN_NAME', Configuration::get('FC_CITY_ORIGIN_NAME')),
            'FC_INFO_PROD' => Tools::getValue('FC_INFO_PROD', Configuration::get('FC_INFO_PROD')),
            'FC_SHOP_CART' => Tools::getValue('FC_SHOP_CART', Configuration::get('FC_SHOP_CART')),
            'FC_API_KEY' => Tools::getValue('FC_API_KEY', Configuration::get('FC_API_KEY')),
        );
        return $values;
    }

    private function _postProcess() {
        try {
            if (empty(Tools::getValue('FC_CITY_ORIGIN'))) {
                throw new Exception('O campo cidade de origem é obrigatório.');
            }
            Configuration::updateValue('FC_CITY_ORIGIN', Tools::getValue('FC_CITY_ORIGIN'));
            Configuration::updateValue('FC_CITY_ORIGIN_NAME', Tools::getValue('FC_CITY_ORIGIN_NAME'));
            Configuration::updateValue('FC_INFO_PROD', Tools::getValue('FC_INFO_PROD'));
            Configuration::updateValue('FC_SHOP_CART', Tools::getValue('FC_SHOP_CART'));
            Configuration::updateValue('FC_API_KEY', Tools::getValue('FC_API_KEY'));
            $this->_html .= $this->displayConfirmation($this->l('Configurações atualizadas'));
        } catch (Exception $ex) {
            $this->_postErrors[] = $ex->getMessage();
        }
    }

    /*
     * * Hook update carrier
     * *
     */

    public function hookupdateCarrier($params) {
        if ((int) ($params['id_carrier']) == (int) (Configuration::get('FC_CARRIER_ID'))) {
            Configuration::updateValue('FC_CARRIER_ID', (int) ($params['carrier']->id));
        }
    }

    public function getOrderShippingCost($params, $shipping_cost) {
        global $cookie;

        return ( isset($cookie->fc_valorFrete) ? $cookie->fc_valorFrete : 0 );
    }

    public function getOrderShippingCostExternal($params) {
        return 0;
    }

    public function hookDisplayRightColumnProduct($params) {
        global $smarty;

        if (Configuration::get('FC_INFO_PROD') != '1') {
            return false;
        }        
        $this->context->controller->addJS($this->_path . 'assets/js/FreteClick.js');
        $smarty->assign('city_origin_id', Configuration::get('FC_CITY_ORIGIN'));
        $smarty->assign('url_shipping_quote', $this->context->link->getModuleLink('freteclick', 'calcularfrete'));
        $smarty->assign('url_city_destination', $this->context->link->getModuleLink('freteclick', 'citydestination'));
        $smarty->assign('url_city_origin', $this->context->link->getModuleLink('freteclick', 'cityorigin'));
        return $this->display(__FILE__, 'view/simularfrete.tpl');
    }

    public function hookdisplayShoppingCartFooter($params) {
        global $smarty;

        if (Configuration::get('FC_SHOP_CART') != '1') {
            return false;
        }        
        $this->context->controller->addJS($this->_path . 'assets/js/FreteClick.js');
        $smarty->assign('city_origin_id', Configuration::get('FC_CITY_ORIGIN'));
        $smarty->assign('cart_total', $this->context->cart->getOrderTotal());
        $smarty->assign('cart_product_names', $this->getListProductsName());
        $smarty->assign('cart_total_weight', $this->context->cart->getTotalWeight());
        $smarty->assign('url_shipping_quote', $this->context->link->getModuleLink('freteclick', 'calcularfrete'));
        $smarty->assign('url_city_destination', $this->context->link->getModuleLink('freteclick', 'citydestination'));
        $smarty->assign('url_city_origin', $this->context->link->getModuleLink('freteclick', 'cityorigin'));
        return $this->display(__FILE__, 'view/simularfrete_cart.tpl');
    }

    public function hookextraCarrier($params) {
        global $smarty, $cookie;

        $arrSmarty = array(
            'display_name' => $this->displayName,
            'carrier_checked' => $params['cart']->id_carrier,
            'fc_carrier_id' => (int) (Configuration::get('FC_CARRIER_ID')),
            'url_transportadora' => $this->context->link->getModuleLink('freteclick', 'transportadora') . '?_=' . microtime()
        );

        try {
            if ($params['cart']->id_carrier == (int) (Configuration::get('FC_CARRIER_ID'))) {
                $arrPostFields = array(
                    'city-origin-id' => Configuration::get('FC_CITY_ORIGIN'),
                    'product-type' => $this->getListProductsName(),
                    'product-total-price' => $this->context->cart->getOrderTotal(),
                    'key' => Configuration::get('FC_API_KEY')
                );
                $arrSmarty['arr_transportadoras'] = $this->getTransportadoras($arrPostFields);
                $arrSmarty['quote_id'] = ( isset($cookie->quote_id) ? $cookie->quote_id : null );
            }
        } catch (Exception $ex) {
            $arrSmarty['error_message'] = $ex->getMessage();
        }

        $smarty->assign($arrSmarty);
        return $this->display(__FILE__, 'view/order_shipping.tpl');
    }

    /**
     * Hook que será executado ao finalizar um pedido
     */
    public function hookOrderConfirmation($params) {
        global $cookie;
        $params['objOrder']->setWsShippingNumber($cookie->delivery_order_id);
        $params['objOrder']->save();
        //$this->addQuoteOriginCompany($params['objOrder']);
        //$this->addQuoteDestinationClient($params['objOrder']);
    }

    private function addQuoteDestinationClient($order) {
        //https://www.freteclick.com.br/sales/add-quote-destination-client.json
        global $cookie;
        $address = new Address(intval($order->id_address_delivery));
        echo '<pre>';
        $customer = new Customer($order->id_customer);
        $data['quote'] = $cookie->quote_id;
        $data['complement'] = $address->address2;
        $data['street'] = preg_replace('/[^A-Z a-z]/', '', preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "a A e E i I o O u U n N"), $address->address1));
        $data['address-number'] = preg_replace('/[^0-9]/', '', $address->address1);
        $data['cep'] = $address->postcode;
        $data['city'] = $address->city;
        $data['contact-name'] = $address->firstname . ' ' . $address->lastname;
        $data['address-nickname'] = $address->alias;
        $data['country'] = $address->country;
        $data['company-alias'] = $address->company;
        $data['company-name'] = $address->company;
        $data['ddd'] = substr(preg_replace('/[^0-9]/', '', $address->phone), 2);
        $data['phone'] = substr(preg_replace('/[^0-9]/', '', $address->phone), -9);
        if ($address->company) {
            $data['choose-client'] = 'company';
        } else {
            $data['choose-client'] = 'client';
        }
        $data['email'] = $customer->email;


        $data['district'] = $cookie->quote_id;
        $data['state'] = $cookie->quote_id;

        print_r($data);
        die();
        /*
          company-document:65.465.465/4654-65
          client-document:
         */
        print_r($data);
    }

    private function addQuoteOriginCompany($order) {
        //add-quote-origin-company.json
        global $cookie;
        $address = new Address(intval($order->id_address_delivery));
        echo '<pre>';
        print_r($address);
        die();

        /*
          quote:6453
          contact-id:7
          contact-name:Luiz Kim Dias Ferreira
          email:luizkim@gmail.com
          ddd:(11)
          phone:98180-5659
          address-id:272
          address-nickname:Endereço de entrega
          cep:07055-030
          street:Rua Gago Coutinho
          address-number:12
          complement:
          district:Jardim Vila Galvao
          city:Guarulhos
          state:São Paulo
          country:Brazil
          lat:-23.4673357
          lng:-46.56053480000003
         */
    }

    private function getListProductsName() {
        $arrProductsName = array();
        foreach ($this->context->cart->getProducts() as $product) {
            $arrProductsName[] = $product['name'];
        }
        return implode(", ", $arrProductsName);
    }

    public function getTransportadoras($postFields) {
        foreach ($this->context->cart->getProducts() as $key => $product) {
            $postFields['product-package'][$key]['qtd'] = $product['cart_quantity'];
            $postFields['product-package'][$key]['weight'] = number_format($product['weight'], 2, ',', '');
            $postFields['product-package'][$key]['height'] = number_format($product['height'] / 100, 2, ',', '');
            $postFields['product-package'][$key]['width'] = number_format($product['width'] / 100, 2, ',', '');
            $postFields['product-package'][$key]['depth'] = number_format($product['depth'] / 100, 2, ',', '');
        }
        $address = new Address(intval($this->context->cart->id_address_delivery));
        $postFields['city-destination-id'] = $this->getCityIdFromCep($address->postcode);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url_shipping_quote);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        $resp = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $jsonData = $this->filterJson($resp);
        $arrData = json_decode($jsonData);
        if ($arrData->response->success === false || $arrData->response->data === false) {
            throw new Exception('Nenhuma transportadora disponível.');
        }
        global $cookie;
        $cookie->delivery_order_id = $arrData->response->data->id;
        return $arrData;
    }

    public function getCityIdFromCep($cep) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url_search_city_from_cep);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('cep' => $cep)));
        $resp = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $jsonData = $this->filterJson($resp);
        $arrData = json_decode($jsonData);
        if ($arrData->response->success === false || $arrData->response->data === false || $arrData->response->data->id === false) {
            throw new Exception('Nenhuma transportadora disponível para este CEP: ' . $cep);
        }

        return $arrData->response->data->id;
    }

    public function filterJson($json) {
        $arrJson = Tools::jsonDecode($json);
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
            $arrJson->response->data->quote[$key]->raw_total = number_format($quote->total, 2, '.', '');
            $arrJson->response->data->quote[$key]->total = "R$ {$quote_price}";
        }
        return Tools::jsonEncode($arrJson);
    }

}
