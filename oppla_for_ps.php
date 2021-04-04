<?php
/**
* 2007-2021 PrestaShop, 2021 Delivery Aggregator
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Oppla_For_PS extends Module
{
    protected $config_form = false;
    const BASEURLAPI = 'http://oppla.delivery/api/v1/'; 

    public function __construct()
    {
        $this->name = 'oppla_for_ps';
        $this->tab = 'shipping_logistics';
        $this->version = '0.1.0';
        $this->author = 'Delivery Aggregator';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Oppla for PrestaShop');
        $this->description = $this->l('This module allows to integrate your PrestaShop store with Oppla.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('OPPLA_FOR_PS_ENABLED', false);

        return parent::install() &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('actionOrderStatusUpdate');
    }

    public function uninstall()
    {
        Configuration::deleteByName('OPPLA_FOR_PS_ENABLED');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $output = "";

        /**
         * If values have been submitted in the form, process.
         */
        if (Tools::isSubmit('submitOpplaForPSModule')) {
            $this->postProcess();
            $output .= $this->displayConfirmation($this->l('Configuration updated'));
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitOpplaForPSModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enabled'),
                        'name' => 'OPPLA_FOR_PS_ENABLED',
                        'is_bool' => true,
                        'desc' => $this->l('Enable Oppla service'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Enter your personal access token'),
                        'name' => 'OPPLA_FOR_PS_TOKEN',
                        'label' => $this->l('Token'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-map-marker"></i>',
                        'desc' => $this->l('Enter your pickup address'),
                        'name' => 'OPPLA_FOR_PS_PICKUP_ADDRESS',
                        'label' => $this->l('Pickup address'),
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'OPPLA_FOR_PS_ENABLED' => Configuration::get('OPPLA_FOR_PS_ENABLED', false),
            'OPPLA_FOR_PS_TOKEN' => Configuration::get('OPPLA_FOR_PS_TOKEN', ""),
            'OPPLA_FOR_PS_PICKUP_ADDRESS' => Configuration::get('OPPLA_FOR_PS_PICKUP_ADDRESS', "")
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    public function hookActionOrderStatusUpdate($params)
    {
        $enabled = Configuration::get('OPPLA_FOR_PS_ENABLED', false);
        $token = Configuration::get('OPPLA_FOR_PS_TOKEN', "");
        $pickup = Configuration::get('OPPLA_FOR_PS_PICKUP_ADDRESS', "");

        if ($enabled == false)
            return;
        
        if (empty($token) || empty($pickup))    
            throw new PrestaShopException($this->l("Oppla for PS module is misconfigured. Token or address missing."));
    
        if ($params['newOrderStatus']->name != "Delivered")
            return;      
        
        $client = new \GuzzleHttp\Client();

        $request = $client->createRequest('GET', "$this->BASEURLAPI/timeslots", [
            'headers' => ['Authorization' => "Bearer $token"]
        ]);

        $timeslots = $client->send($request);
        
        // TODO:
        // get current date
        // if AM shipped in PM
        // if PM shipped in AM

        $orderId = $params['newOrderStatus']->id_order;

        // get order object
        $order = new Order((int) $orderId);

        // get address object
        $address = new Address($order->id_address_delivery);

        // create the shipping
        $shipping = array(
            "content_description"   => "Ordine Web $orderId",
            "from"                  => "2020-01-01T09:00:00.000000Z",
            "to"                    => "2020-01-01T13:00:00.000000Z",
            "notes"                 => "$order->note",
            "package_type"          => "1",
            "recipient_name"        => "$address->firstname $address->firstname",
            "delivery_address"      => "$address->address1 $address->address1 $address->postcode $address->city", // FIX ME: missing country
            "pickup_addresses"=> array(             
                "save"                  => false,
                "name"                  => "Punto di ritiro",
                "address"               => "$pickup"
            ),
            "content_value"         => $order->total_paid,
            "recipient_payment"     => $params['newOrderStatus']->paid == "0" ? $order->total_paid_real : "",
            "recipient_phone"       => "$address->phone_mobile"
        );

        $request = $client->createRequest('POST', "$this->baseUrlAPI/deliveries", [
            'headers'   => ['Authorization' => "Bearer $token"],
            'body'      => json_encode($shipping)
        ]);

        $response = $client->send($request);

        if ($response->getStatusCode() != 200)
            throw new PrestaShopException($this->l("Can't insert your order into Oppla."));        
    }
}
