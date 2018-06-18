<?php
/**
 *  Module for the calculation of the shipping using the web service of Freteclick
 *  @author    Ederson Ferreira (ederson.dev@gmail.com)
 *  @copyright 2010-2015 Freteclick
 *  @license   LICENSE
 */
 
// Avoid direct access to the file
if (!defined('_PS_VERSION_')) {
    exit;
}
class Freteclick extends CarrierModule
{
    public $id_carrier;
    private $_html = '';
    private $_postErrors = array();
    public $url_shipping_quote;
    public $url_city_origin;
    public $url_city_destination;
    public $url_search_city_from_cep;
    public $url_choose_quote;
    public $url_add_quote_destination_client;
    public $url_add_quote_origin_company;
    public $cookie;
    protected static $error;

    public function __construct()
    {
        $this->module_key =  '787992febc148fba30e5885d08c14f8b';
        $this->cookie = new Cookie('Frete Click');
        $this->cookie->setExpire(time() + 20 * 60);

        $this->name = 'freteclick';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Frete Click';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Freteclick');
        $this->description = $this->l('Calculation of shipping with the web service Freteclick');

        if (self::isInstalled($this->name)) {
            // Verifica se a cidade de origem foi selecionada
            if (!Configuration::get('FC_CITY_ORIGIN')) {
                $this->warning = $this->l('The home city must be configured');
            }
            // Verifica se o CEP de origem foi selecionada
            if (!Configuration::get('FC_CEP_ORIGIN')) {
                $this->warning = $this->l('The home zip code must be configured');
            }			
            if (!Configuration::get('FC_STREET_ORIGIN')) {
                $this->warning = $this->l('The street origin field is required.');
            }
            if (!Configuration::get('FC_NUMBER_ORIGIN')) {
                $this->warning = $this->l('The number origin field is required.');
            }
            if (!Configuration::get('FC_DISTRICT_ORIGIN')) {
                $this->warning = $this->l('The district origin field is required.');
            }
            if (!Configuration::get('FC_STATE_ORIGIN')) {
                $this->warning = $this->l('The state origin field is required.');
            }
            if (!Configuration::get('FC_CONTRY_ORIGIN')) {
                $this->warning = $this->l('The country origin field is required.');
            }
        }
        $this->url_shipping_quote = 'https://api.freteclick.com.br/sales/shipping-quote.json';
        $this->url_city_origin = 'https://api.freteclick.com.br/carrier/search-city-origin.json';
        $this->url_city_destination = 'https://api.freteclick.com.br/carrier/search-city-destination.json';
        $this->url_search_city_from_cep = 'https://api.freteclick.com.br/carrier/search-city-from-cep.json';
        $this->url_choose_quote = 'https://api.freteclick.com.br/sales/choose-quote.json';
        $this->url_api_correios = 'http://ws.correios.com.br/calculador/CalcPrecoPrazo.asmx?WSDL';
        $this->url_add_quote_destination_client = 'https://api.freteclick.com.br/sales/add-quote-destination-client.json';
        $this->url_add_quote_origin_company = 'https://api.freteclick.com.br/sales/add-quote-origin-company.json.json';
    }

    public function install()
    {
        $carrierConfig = array(
            'name' => 'Freteclick',
            'id_tax_rules_group' => 0,
            'active' => true,
            'deleted' => 0,
            'shipping_handling' => false,
            'range_behavior' => 0,
            'delay' => array(Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')) => 'Select the desired carrier'),
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

    public function uninstall()
    {
        if (!parent::uninstall() || !Configuration::deleteByName('FC_INFO_PROD') || !Configuration::deleteByName('FC_SHOP_CART') || !Configuration::deleteByName('FC_CITY_ORIGIN') || !$this->unregisterHook('updateCarrier') || !$this->unregisterHook('extraCarrier') || !$this->unregisterHook('DisplayRightColumnProduct') || !$this->unregisterHook('OrderConfirmation') || !$this->unregisterHook('displayShoppingCartFooter')) {
            return false;
        }
        $objFC = new Carrier((int) (Configuration::get('FC_CARRIER_ID')));
        if (Configuration::get('PS_CARRIER_DEFAULT') == (int) ($objFC->id)) {
            $carriersD = Carrier::getCarriers($this->cookie->id_lang, true, false, false, null, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
            foreach ($carriersD as $carrierD) {
                if ($carrierD['active'] and ! $carrierD['deleted'] and ( $carrierD['name'] != $this->_config['name'])) {
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

    public static function installExternalCarrier($config)
    {
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
                Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery', array('id_carrier' => (int) ($carrier->id), 'id_range_price' => (int) ($rangePrice->id), 'id_range_weight' => null, 'id_zone' => (int) ($zone['id_zone']), 'price' => '0'), 'INSERT');
                Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_ . 'delivery', array('id_carrier' => (int) ($carrier->id), 'id_range_price' => null, 'id_range_weight' => (int) ($rangeWeight->id), 'id_zone' => (int) ($zone['id_zone']), 'price' => '0'), 'INSERT');
            }

            // Copy Logo
            if (!copy(dirname(__FILE__) . '/views/img/' . $config['logo_img'], _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg')) {
                return false;
            }

            // Return ID Carrier
            return (int) ($carrier->id);
        }

        return false;
    }

    public function getContent()
    {
        $this->context->controller->addJqueryUI('ui.autocomplete');
        $this->context->controller->addJS($this->_path . 'views/js/Freteclick.js');
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postProcess();

            if (count($this->_postErrors)) {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }
        $this->_html .= $this->renderForm();
        return $this->_html;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Freteclick configuration')
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Home zip code'),
                        'hint' => $this->l('Enter the zip code where the merchandise will be collected'),
                        'name' => 'FC_CEP_ORIGIN',
                        'id' => 'cep-origin',
                        'required' => true,
                        'maxlength' => 9,
                        'class' => 'form-control fc-input-cep'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Street'),
                        'hint' => $this->l('Enter the Street where the merchandise will be collected'),
                        'name' => 'FC_STREET_ORIGIN',
                        'id' => 'street-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Number'),
                        'hint' => $this->l('Enter the number where the merchandise will be collected'),
                        'name' => 'FC_NUMBER_ORIGIN',
                        'id' => 'number-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Complement'),
                        'hint' => $this->l('Enter the complement where the merchandise will be collected'),
                        'name' => 'FC_COMPLEMENT_ORIGIN',
                        'id' => 'complement-origin',
                        'required' => false,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('District'),
                        'hint' => $this->l('Enter the district where the merchandise will be collected'),
                        'name' => 'FC_DISTRICT_ORIGIN',
                        'id' => 'district-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Home city'),
                        'hint' => $this->l('Enter the city where the merchandise will be collected'),
                        'name' => 'FC_CITY_ORIGIN',
                        'id' => 'city-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Home state'),
                        'hint' => $this->l('Enter the state where the merchandise will be collected'),
                        'name' => 'FC_STATE_ORIGIN',
                        'id' => 'state-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Home Country'),
                        'hint' => $this->l('Enter the contry where the merchandise will be collected'),
                        'name' => 'FC_CONTRY_ORIGIN',
                        'id' => 'country-origin',
                        'required' => true,
                        'class' => 'form-control'
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('API Key'),
                        'hint' => $this->l('Enter the API key found in your dashboard http://www.freteclick.com.br'),
                        'name' => 'FC_API_KEY',
                        'required' => true,
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Product Information'),
                        'hint' => $this->l('Displays a shipping quote box on the product description screen.'),
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
                        'label' => $this->l('Shopping cart'),
                        'hint' => $this->l('Displays a shipping quote box on the Shopping Cart screen.'),
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

    private function getConfigFieldsValues()
    {
        $values = array(
            'FC_CITY_ORIGIN' => Tools::getValue('FC_CITY_ORIGIN', Configuration::get('FC_CITY_ORIGIN')),
            'FC_CEP_ORIGIN' => Tools::getValue('FC_CEP_ORIGIN', Configuration::get('FC_CEP_ORIGIN')),
			'FC_STREET_ORIGIN' => Tools::getValue('FC_STREET_ORIGIN', Configuration::get('FC_STREET_ORIGIN')),
			'FC_NUMBER_ORIGIN' => Tools::getValue('FC_NUMBER_ORIGIN', Configuration::get('FC_NUMBER_ORIGIN')),
			'FC_COMPLEMENT_ORIGIN' => Tools::getValue('FC_COMPLEMENT_ORIGIN', Configuration::get('FC_COMPLEMENT_ORIGIN')),
			'FC_STATE_ORIGIN' => Tools::getValue('FC_STATE_ORIGIN', Configuration::get('FC_STATE_ORIGIN')),
			'FC_CONTRY_ORIGIN' => Tools::getValue('FC_CONTRY_ORIGIN', Configuration::get('FC_CONTRY_ORIGIN')),
			'FC_DISTRICT_ORIGIN' => Tools::getValue('FC_DISTRICT_ORIGIN', Configuration::get('FC_DISTRICT_ORIGIN')),
            'FC_INFO_PROD' => Tools::getValue('FC_INFO_PROD', Configuration::get('FC_INFO_PROD')),
            'FC_SHOP_CART' => Tools::getValue('FC_SHOP_CART', Configuration::get('FC_SHOP_CART')),
            'FC_API_KEY' => Tools::getValue('FC_API_KEY', Configuration::get('FC_API_KEY')),
        );
        return $values;
    }

    private function _postProcess()
    {
        try {
            if (empty(Tools::getValue('FC_CITY_ORIGIN'))) {
                $this->addError('The city of origin field is required.');
            }
            if (empty(Tools::getValue('FC_CEP_ORIGIN'))) {
                $this->addError('The Zip Code field is required.');
            }
            if (empty(Tools::getValue('FC_STREET_ORIGIN'))) {
                $this->addError('The street origin field is required.');
            }
            if (empty(Tools::getValue('FC_NUMBER_ORIGIN'))) {
                $this->addError('The number origin field is required.');
            }
            if (empty(Tools::getValue('FC_DISTRICT_ORIGIN'))) {
                $this->addError('The district origin field is required.');
            }
            if (empty(Tools::getValue('FC_STATE_ORIGIN'))) {
                $this->addError('The state origin field is required.');
            }
            if (empty(Tools::getValue('FC_CONTRY_ORIGIN'))) {
                $this->addError('The country origin field is required.');
            }
            Configuration::updateValue('FC_CITY_ORIGIN', Tools::getValue('FC_CITY_ORIGIN'));
            Configuration::updateValue('FC_CEP_ORIGIN', Tools::getValue('FC_CEP_ORIGIN'));
            Configuration::updateValue('FC_STREET_ORIGIN', Tools::getValue('FC_STREET_ORIGIN'));
            Configuration::updateValue('FC_COMPLEMENT_ORIGIN', Tools::getValue('FC_COMPLEMENT_ORIGIN'));
            Configuration::updateValue('FC_NUMBER_ORIGIN', Tools::getValue('FC_NUMBER_ORIGIN'));
            Configuration::updateValue('FC_DISTRICT_ORIGIN', Tools::getValue('FC_DISTRICT_ORIGIN'));
            Configuration::updateValue('FC_STATE_ORIGIN', Tools::getValue('FC_STATE_ORIGIN'));
            Configuration::updateValue('FC_CONTRY_ORIGIN', Tools::getValue('FC_CONTRY_ORIGIN'));
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

    public function hookupdateCarrier($params)
    {
        if ((int) ($params['id_carrier']) == (int) (Configuration::get('FC_CARRIER_ID'))) {
            Configuration::updateValue('FC_CARRIER_ID', (int) ($params['carrier']->id));
        }
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        $total = 0;
        if (!$this->cookie->fc_valorFrete) {
            foreach ($this->context->cart->getProducts() as $product) {
                $total += $product['total'];
            }

            $arrPostFields = array(
                'city-origin' => Configuration::get('FC_CITY_ORIGIN'),
                'cep-origin' => Configuration::get('FC_CEP_ORIGIN'),
				'street-origin' => Configuration::get('FC_STREET_ORIGIN'),
				'address-number-origin' => Configuration::get('FC_NUMBER_ORIGIN'),
				'complement-origin' => Configuration::get('FC_COMPLEMENT_ORIGIN') ? : "",
				'district-origin' => Configuration::get('FC_DISTRICT_ORIGIN'),
				'state-origin' => Configuration::get('FC_STATE_ORIGIN'),
				'country-origin' => Configuration::get('FC_CONTRY_ORIGIN'),
                'product-type' => $this->getListProductsName(),
                'product-total-price' => number_format($total, 2, ',', '.')
            );
            $this->getTransportadoras($arrPostFields);
        }
        return ( isset($this->cookie->fc_valorFrete) ? $this->cookie->fc_valorFrete : 0 );
    }

    public function getOrderShippingCostExternal($params)
    {
        return 0;
    }

    public function hookDisplayRightColumnProduct($params)
    {
        $product = new Product((int)Tools::getValue('id_product'));
        $product_carriers = $product->getCarriers();
        $carriers = Carrier::getCarriers($this->cookie->id_lang, true, false, false, null, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
        $num_car = count($product_carriers);
        $fc_is_active = $num_car == 0 ? true : false;
        if ($num_car > 0) {
            foreach ($product_carriers as $key => $carrier) {
                if ($carrier["external_module_name"] == "freteclick") {
                    $fc_is_active = $carrier["active"];
                    break;
                }
            }
        } else {
            $fc_is_active = false;
            foreach ($carriers as $key => $carrier) {
                if ($carrier["external_module_name"] == "freteclick") {
                    $fc_is_active = true;
                    break;
                }
            }
        }
        $smarty = $this->smarty;

        if (Configuration::get('FC_INFO_PROD') != '1' || !$fc_is_active) {
            return false;
        }
        $this->context->controller->addJS($this->_path . 'views/js/Freteclick.js');
        $smarty->assign('cep_origin', Configuration::get('FC_CEP_ORIGIN'));
        $smarty->assign('street_origin', Configuration::get('FC_STREET_ORIGIN'));
        $smarty->assign('number_origin', Configuration::get('FC_NUMBER_ORIGIN'));
        $smarty->assign('complement_origin', Configuration::get('FC_COMPLEMENT_ORIGIN'));
        $smarty->assign('district_origin', Configuration::get('FC_DISTRICT_ORIGIN'));
        $smarty->assign('city_origin', Configuration::get('FC_CITY_ORIGIN'));
        $smarty->assign('state_origin', Configuration::get('FC_STATE_ORIGIN'));
        $smarty->assign('country_origin', Configuration::get('FC_CONTRY_ORIGIN'));
        $smarty->assign('url_shipping_quote', $this->context->link->getModuleLink('freteclick', 'calcularfrete'));
        $smarty->assign('url_city_destination', $this->context->link->getModuleLink('freteclick', 'citydestination'));
        $smarty->assign('url_city_origin', $this->context->link->getModuleLink('freteclick', 'cityorigin'));
        $smarty->assign('cep', $this->cookie->cep);
        return $this->display(__FILE__, 'views/templates/hook/simularfrete.tpl');
    }

    public function hookdisplayShoppingCartFooter($params)
    {
		$langID = $this->context->language->id;
        $products = Context::getContext()->cart->getProducts();
        $carriers = Carrier::getCarriers($this->cookie->id_lang, true, false, false, null, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
        $fc_is_active = false;
        foreach ($carriers as $key => $carrier) {
            if ($carrier["external_module_name"] == "freteclick") {
                $fc_is_active = true;
                break;
            }
        }
		
		$prod_name = false;
		
        if ($fc_is_active == true) {
            foreach ($products as $key => $prod) {
                $product = new Product((int)$prod["id_product"], false, $langID);
				if (!$prod_name){ $prod_name = $product->name; }
                $product_carriers = $product->getCarriers();
                $num_car = count($product_carriers);
                if ($num_car > 0) {
                    $fc_is_active = false;
                    foreach ($product_carriers as $key => $carrier) {
                        if ($carrier["external_module_name"] == "freteclick") {
                            $fc_is_active = $carrier["active"];
                            break;
                        }
                    }
                    if ($fc_is_active == false) {
                        break;
                    }
                }
            }
        }
		
        $smarty = $this->smarty;

        if (Configuration::get('FC_SHOP_CART') != '1' || !$fc_is_active) {
            return false;
        }
        $this->context->controller->addJS($this->_path . 'views/js/Freteclick.js');
        $smarty->assign('cep_origin', Configuration::get('FC_CEP_ORIGIN'));
        $smarty->assign('street_origin', Configuration::get('FC_STREET_ORIGIN'));
        $smarty->assign('number_origin', Configuration::get('FC_NUMBER_ORIGIN'));
        $smarty->assign('complement_origin', Configuration::get('FC_COMPLEMENT_ORIGIN'));
        $smarty->assign('district_origin', Configuration::get('FC_DISTRICT_ORIGIN'));
        $smarty->assign('city_origin', Configuration::get('FC_CITY_ORIGIN'));
        $smarty->assign('state_origin', Configuration::get('FC_STATE_ORIGIN'));
        $smarty->assign('country_origin', Configuration::get('FC_CONTRY_ORIGIN'));
        $smarty->assign('url_shipping_quote', $this->context->link->getModuleLink('freteclick', 'calcularfrete'));
        $smarty->assign('cep', $this->cookie->cep);
		$smarty->assign('product_name', $prod_name);
        return $this->display(__FILE__, 'views/templates/hook/simularfrete_cart.tpl');
    }

    public function hookextraCarrier($params)
    {
        $smarty = $this->smarty;
        $this->context->controller->addJS($this->_path . 'views/js/Freteclick.js');
        $arrSmarty = array(
            'display_name' => $this->displayName,
            'carrier_checked' => $params['cart']->id_carrier,
            'fc_carrier_id' => (int) (Configuration::get('FC_CARRIER_ID')),
            'url_transportadora' => $this->context->link->getModuleLink('freteclick', 'transportadora') . '?_=' . microtime()
        );

        try {
            if ($params['cart']->id_carrier == (int) (Configuration::get('FC_CARRIER_ID'))) {
                $arrPostFields = array(
                    'city-origin' => Configuration::get('FC_CITY_ORIGIN'),
                    'cep-origin' => Configuration::get('FC_CEP_ORIGIN'),
					'street-origin' => Configuration::get('FC_STREET_ORIGIN'),
					'address-number-origin' => Configuration::get('FC_NUMBER_ORIGIN'),
					'complement-origin' => Configuration::get('FC_COMPLEMENT_ORIGIN') ? : "",
					'district-origin' => Configuration::get('FC_DISTRICT_ORIGIN'),
					'state-origin' => Configuration::get('FC_STATE_ORIGIN'),
					'country-origin' => Configuration::get('FC_CONTRY_ORIGIN'),
                    'product-type' => $this->getListProductsName(),
                    'product-total-price' =>  number_format($this->context->cart->getOrderTotal(), 2, ',', '.')
                );
                $arrSmarty['arr_transportadoras'] = $this->getTransportadoras($arrPostFields);
				
                $arrSmarty['quote_id'] = ( isset($this->cookie->quote_id) ? $this->cookie->quote_id : null );
				$smarty->assign($arrSmarty);
            }
        } catch (Exception $ex) {
            $arrSmarty['error_message'] = $ex->getMessage();
			$smarty->assign($arrSmarty);
        }
        return $this->display(__FILE__, 'views/templates/hook/order_shipping.tpl');
    }

    /**
     * Hook that will run when finalizing an order
     */
    public function hookOrderConfirmation($params)
    {
        $params['objOrder']->setWsShippingNumber($this->cookie->delivery_order_id);
        $params['objOrder']->save();
        $this->addQuoteOriginCompany($params['objOrder']);
        $this->addQuoteDestinationClient($params['objOrder']);
    }

    private function addQuoteDestinationClient($order)
    {
		$langID = $this->context->language->id;
        $data = array();
        $address = new Address((int)$order->id_address_delivery);
        $customer = new Customer($order->id_customer);
        $data['quote'] = $this->cookie->quote_id;
		if ($address->company) {
            $data['choose-client'] = 'company';
            $data['company-document'] = '44.444.444/4444-44';
			$data['company-alias'] = $address->company;
			$data['company-name'] = $address->company;
        } else {
            $data['choose-client'] = 'client';
			$data['client-document'] = '444.444.444-44';
        }
        $data['email'] = $customer->email;
        $data['contact-name'] = $address->firstname . ' ' . $address->lastname;
        $data['ddd'] = Tools::substr(preg_replace('/[^0-9]/', '', $address->phone), 2);
        $data['phone'] = Tools::substr(preg_replace('/[^0-9]/', '', $address->phone), -9);
        $data['address-nickname'] = $address->alias;
		$data['cep'] = $address->postcode;
		$data['street'] = preg_replace('/[^A-Z a-z]/', '', preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "a A e E i I o O u U n N"), $address->address1));
        $data['address-number'] = preg_replace('/[^0-9]/', '', $address->address1);
		$data['complement'] = "";
        $data['district'] = $address->address2;
        $data['city'] = $address->city;
		
		$estados = State::getStates($langID, true);
		$iso_estado = "";
		foreach ($estados as $key => $estado){
			if ($estado["id_state"] == $address->id_state){
				$iso_estado = $estado["iso_code"];
				break;
			}
		}		
        $data['state'] = $iso_estado;
        $data['country'] = $address->country;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url_add_quote_destination_client . '?api-key=' . Configuration::get('FC_API_KEY'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp;
    }

    private function addQuoteOriginCompany($order)
    {
        $data = array();
        $data['quote'] = $this->cookie->quote_id;
        $data['contact-name'] = Configuration::get('PS_SHOP_NAME');
        $data['email'] = Configuration::get('PS_SHOP_EMAIL');
        $data['ddd'] = Tools::substr(preg_replace('/[^0-9]/', '', Configuration::get('PS_SHOP_PHONE')), 2);
        $data['phone'] = Tools::substr(preg_replace('/[^0-9]/', '', Configuration::get('PS_SHOP_PHONE')), -9);
        $data['address-nickname'] = "Endereço Principal";
        $data['cep'] = Configuration::get('FC_CEP_ORIGIN');
		$data['street'] = preg_replace('/[^A-Z a-z]/', '', preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "a A e E i I o O u U n N"), Configuration::get('FC_STREET_ORIGIN')));
        $data['address-number'] = Configuration::get('FC_NUMBER_ORIGIN');
        $data['complement'] = Configuration::get('FC_COMPLEMENT_ORIGIN') ? : "";
        $data['district'] = Configuration::get('FC_DISTRICT_ORIGIN');
        $data['city'] = Configuration::get('FC_CITY_ORIGIN');
        $data['state'] = Configuration::get('FC_STATE_ORIGIN');
        $data['country'] = Configuration::get('FC_CONTRY_ORIGIN');
		
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url_add_quote_origin_company . '?api-key=' . Configuration::get('FC_API_KEY'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $resp = curl_exec($ch);
        curl_close($ch);
        return $resp;
    }

    private function getListProductsName()
    {
        $arrProductsName = array();
        foreach ($this->context->cart->getProducts() as $product) {
            $arrProductsName[] = $product['name'];
        }
        return implode(", ", $arrProductsName);
    }
	public function quote($data){
		$arrRetorno = array();
        try {
                $this->cookie->cep = Tools::getValue('cep');
				$product_price = number_format($data['product-total-price'], 2, ',', '.');
				$data['product-total-price'] = $product_price;
				
				$ch = curl_init();
				$data['api-key'] = Configuration::get('FC_API_KEY');
				curl_setopt($ch, CURLOPT_URL, $this->url_shipping_quote);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
				$resp = curl_exec($ch);
				curl_close($ch);
				$arrJson = $this->orderByPrice($this->filterJson($resp));
				if (!$this->cookie->fc_valorFrete){
					$this->cookie->fc_valorFrete = $arrJson->response->data->quote[0]->total;
				}
                foreach ($arrJson->response->data->quote as $key => $quote) {
                    $quote_price = number_format($quote->total, 2, ',', '.');
					$arrJson->response->data->quote[$key]->raw_total = $quote->total;
                    $arrJson->response->data->quote[$key]->total = "R$ {$quote_price}";
                }
                $this->cookie->write();
                return Tools::jsonEncode($arrJson);
        } catch (Exception $ex) {
            $arrRetorno = array(
                'response' => array('success' => false, 'error' => $ex->getMessage())
            );
            return Tools::jsonEncode($arrRetorno);
        }
    }
    public function getTransportadoras($postFields)
    {
		$langID = $this->context->language->id;
        foreach ($this->context->cart->getProducts() as $key => $product) {
            $postFields['product-package'][$key]['qtd'] = $product['cart_quantity'];
            $postFields['product-package'][$key]['weight'] = number_format($product['weight'], 10, ',', '');
            $postFields['product-package'][$key]['height'] = number_format($product['height'] / 100, 10, ',', '');
            $postFields['product-package'][$key]['width'] = number_format($product['width'] / 100, 10, ',', '');
            $postFields['product-package'][$key]['depth'] = number_format($product['depth'] / 100, 10, ',', '');
        }
        $address = new Address((int)$this->context->cart->id_address_delivery);
		
        $postFields['cep-destination'] = $address->postcode;
		$postFields['city-destination'] = $address->city;
		$postFields['street-destination'] = preg_replace('/[^A-Z a-z]/', '', preg_replace(array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/", "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/", "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/"), explode(" ", "a A e E i I o O u U n N"), $address->address1));
		$postFields['address-number-destination'] = preg_replace('/[^0-9]/', '', $address->address1);
		$postFields['complement-destination'] = "";
		$postFields['district-destination'] = $address->address2;

		
		$estados = State::getStates($langID, true);
		$iso_estado = "";
		foreach ($estados as $key => $estado){
			if ($estado["id_state"] == $address->id_state){
				$iso_estado = $estado["iso_code"];
				break;
			}
		}		
		$postFields['state-destination'] = $iso_estado;
		$postFields['country-destination'] = "Brasil";
		
		$arrJson = Tools::jsonDecode($this->quote($postFields));

        if ($arrJson->response->success === false || $arrJson->response->data === false) {
            $this->addError('Nenhuma transportadora disponível.');
        }
		else{
			$this->cookie->delivery_order_id = $arrJson->response->data->id;
			$this->cookie->write();
		}
        return $arrJson;
    }

    public function orderByPrice($arrJson)
    {
        $quotes = (array) $arrJson->response->data->quote;
        usort($quotes, function ($a, $b) {
            return $a->total > $b->total;
        });
        $arrJson->response->data->quote = $quotes;
        return $arrJson;
    }
    
    public function filterJson($json)
    {
        $arrJson = Tools::jsonDecode($json);
        if (!$arrJson) {
            $this->addError('Erro ao recuperar dados');
        }
        if ($arrJson->response->success === false) {
            if ($arrJson->response->error) {
                foreach ($arrJson->response->error as $error) {
                    $this->addError($error->message);
                }
            }
            $this->addError('Erro ao recuperar dados');
        }
        return $this->getErrors() ? : $arrJson;
    }

    public function getErrors()
    {
        return self::$error ? array(
            'response' => array(
                'data' => 'false',
                'count' => 0,
                'success' => false,
                'error' => self::$error
            )
                ) : false;
    }

    public function addError($error)
    {
        self::$error[] = array(
            'code' => md5($error),
            'message' => $error
        );
        return $this->getErrors();
    }
}
