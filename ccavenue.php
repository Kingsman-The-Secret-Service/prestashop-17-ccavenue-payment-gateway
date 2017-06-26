<?php
/**
* 2007-2015 PrestaShop
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
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;


if (!defined('_PS_VERSION_')) {
    exit;
}

class Ccavenue extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'ccavenue';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Kaviarasan K K';
        $this->need_instance = 1;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('CCAvenue');
        $this->description = $this->l('CC Avenue Payment Gateway ');

        $this->confirmUninstall = $this->l('Are you sure wanna detele this module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        Configuration::updateValue('CCAVENUE_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('paymentOptions');
    }

    public function uninstall()
    {
        Configuration::deleteByName('CCAVENUE_LIVE_MODE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitCcavenueModule')) == true) {
            $this->postProcess();
        }

        // $this->context->smarty->assign('module_dir', $this->_path);

        return $this->renderForm();
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
        $helper->submit_action = 'submitCcavenueModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => Configuration::getMultiple(array('CCAVENUE_ORDER_STATUS','CCAVENUE_TITLE','CCAVENUE_CURRENCY','CCAVENUE_LIVE_MODE','CCAVENUE_MERCHANT_ID','CCAVENUE_ENCRYPTION_KEY','CCAVENUE_ACCESS_CODE')), 
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

        $currency = Currency::getCurrencies(false, true);
        
        $orderStatus = array(
                    array(
                        'id_option'=>'2',
                        'name' => 'Payment Accepted'
                        )
                    );
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'CCAVENUE_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
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
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid title'),
                        'name' => 'CCAVENUE_TITLE',
                        'label' => $this->l('Title'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'required' => true,
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid merchant ID'),
                        'name' => 'CCAVENUE_MERCHANT_ID',
                        'label' => $this->l('Merchant ID'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'required' => true,
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid access code'),
                        'name' => 'CCAVENUE_ACCESS_CODE',
                        'label' => $this->l('Access Code'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'required' => true,
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid encryption key'),
                        'name' => 'CCAVENUE_ENCRYPTION_KEY',
                        'label' => $this->l('Encryption Key'),
                    ),
                    array(
                          'type' => 'select',                              
                          'label' => $this->l('Currency:'),      
                          'desc' => $this->l('Choose a currency'), 
                          'name' => 'CCAVENUE_CURRENCY',                   
                          'required' => true,
                          'options' => array(
                            'query' => $currency,
                            'id' => 'iso_code',
                            'name' => 'name'
                          )                             
                    ),
                    array(
                          'type' => 'select',                              
                          'label' => $this->l('Order Status:'),        
                          'desc' => $this->l('Choose a order status'),  
                          'name' => 'CCAVENUE_ORDER_STATUS',
                          'required' => true,                              
                          'options' => array(
                            'query' => $orderStatus,
                            'id' => 'id_option',
                            'name' => 'name'
                          )
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
            'CCAVENUE_LIVE_MODE' => $_POST['CCAVENUE_LIVE_MODE'],
            'CCAVENUE_TITLE' => $_POST['CCAVENUE_TITLE'],
            'CCAVENUE_MERCHANT_ID' =>  $_POST['CCAVENUE_MERCHANT_ID'],
            'CCAVENUE_ENCRYPTION_KEY' => $_POST['CCAVENUE_ENCRYPTION_KEY'],
            'CCAVENUE_ACCESS_CODE' => $_POST['CCAVENUE_ACCESS_CODE'],
            'CCAVENUE_CURRENCY' => $_POST['CCAVENUE_CURRENCY'],
            'CCAVENUE_ORDER_STATUS' => $_POST['CCAVENUE_ORDER_STATUS']
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

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->trans('Pay by CCAvenue', array(), 'Modules.Ccavenue.Shop'))
            ->setAction("https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction")
            ->setInputs($this->getPaymentDetails())
            ->setAdditionalInformation($this->fetch('module:ccavenue/views/templates/hook/payment.tpl'));

        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

    public function hookDisplayPaymentReturn($params)
    {

        if ($this->active == false)
            return;

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR'))
            $this->smarty->assign('status', 'ok');

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

     public function getPaymentDetails(){
        
        $cart = Context::getContext()->cart;

        //Configuration
        $merchant_id    = trim(Configuration::get('CCAVENUE_MERCHANT_ID'));
        $access_code    = trim(Configuration::get('CCAVENUE_ACCESS_CODE'));
        $encryption_key = trim(Configuration::get('CCAVENUE_ENCRYPTION_KEY'));
        $ccavenue_title = trim(Configuration::get('CCAVENUE_TITLE'));
        $ccavenue_currency = trim(Configuration::get('CCAVENUE_CURRENCY'));


        $customer = new Customer(intval($cart->id_customer));
        $language = 'EN';
        $OrderId  = date('Ymdhis'). '-' .intval($cart->id) ;
        $lang_id  = $cart->id_lang;
        $ccavenue_payment_total = $cart->getOrderTotal(true, 3);

        //URL
        $Redirect_Url = $this->context->link->getModuleLink('ccavenue', 'validation', [], true);
        $Cancel_Url  = $this->context->link->getModuleLink('ccavenue', 'validation', ['action' => 'error'], true);

        //Billing Address
        $billing_address = new Address(intval($cart->id_address_invoice));
        $billing_name       = $billing_address->firstname ." ". $billing_address->lastname;
        $bill_address       = $billing_address->address1 ." ". $billing_address->address2;
        $billing_city       = $billing_address->city;
        $billing_zip        = $billing_address->postcode;
        $billing_tel        = $billing_address->phone;
        $billing_email      = $customer->email;
        $bill_country_obj   = new Country(intval($billing_address->id_country));
        $bill_state_obj     = new State(intval($billing_address->id_state));
        $billing_country    = $bill_country_obj->getNameById($lang_id,$billing_address->id_country);
        $billing_state      = $bill_state_obj->getNameById($billing_address->id_state); 


        //Delivery Address
        $delivery_address= new Address(intval($cart->id_address_delivery));
        $delivery_name      = $delivery_address->firstname." " . $delivery_address->lastname;
        $deli_address       = $delivery_address->address1 ." " . $delivery_address->address2;
        $delivery_city      = $delivery_address->city;
        $delivery_zip       = $delivery_address->postcode;
        $delivery_tel       = $delivery_address->phone;
        $deli_country_obj   = new Country(intval($delivery_address->id_country));
        $deli_state_obj     = new State(intval($delivery_address->id_state));
        $delivery_country   = $deli_country_obj->getNameById($lang_id,$delivery_address->id_country);
        $delivery_state     = $deli_state_obj->getNameById($delivery_address->id_state);    


        //Merchant Params
        $merchant_param1    = (int)($cart->id);
        $merchant_param2    = (int)$customer->id;
        $merchant_param3    = $cart->secure_key;
        $merchant_param4    = date('YmdHis');
        $merchant_param5    = "WINNER";

        $cust_notes_message = Message::getMessageByCartId(intval($cart->id));
        $cust_notes         = $cust_notes_message['message'];
        $billing_cust_notes = $cust_notes;


        //Merchant Data
        $merchant_data = array();
        $merchant_data['merchant_id']      = $merchant_id;
        $merchant_data['order_id']         = $OrderId;
        $merchant_data['currency']         = $ccavenue_currency;
        $merchant_data['amount']           = $ccavenue_payment_total;
        $merchant_data['redirect_url']     = $Redirect_Url;       
        $merchant_data['cancel_url']       = $Cancel_Url;
        $merchant_data['language']         = $language;
        $merchant_data['billing_name']     = $billing_name;
        $merchant_data['billing_address']  = $bill_address;    
        $merchant_data['billing_city']     = $billing_city;
        $merchant_data['billing_state']    = $billing_state;
        $merchant_data['billing_zip']      = $billing_zip; 
        $merchant_data['billing_country']  = $billing_country; 
        $merchant_data['billing_tel']      = $billing_tel;
        $merchant_data['billing_email']    = $billing_email;
        $merchant_data['delivery_name']    = $delivery_name;
        $merchant_data['delivery_address'] = $deli_address;
        $merchant_data['delivery_city']    = $delivery_city;
        $merchant_data['delivery_state']   = $delivery_state;
        $merchant_data['delivery_zip']     = $delivery_zip;
        $merchant_data['delivery_country'] = $delivery_country;
        $merchant_data['delivery_tel']     = $delivery_tel;
        $merchant_data['merchant_param1']  = $merchant_param1;
        $merchant_data['merchant_param2']  = $merchant_param2;
        $merchant_data['merchant_param3']  = $merchant_param3;
        $merchant_data['merchant_param4']  = $merchant_param4;
        $merchant_data['merchant_param5']  = $merchant_param5;


        $encrypted_data=$this->encrypt(http_build_query($merchant_data),$encryption_key);

        return array(
            array('type'=>'hidden','name'=>'encRequest','value' => $encrypted_data ),
            array('type'=>'hidden','name'=>'access_code','value' => $access_code)
        );
    }

    /****************** CRYPTO ccavenue *****************/
    function encrypt($plainText,$key)
    {
        $secretKey = $this->hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '','cbc', '');
        $blockSize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
        $plainPad = $this->pkcs5_pad($plainText, $blockSize);
        if (mcrypt_generic_init($openMode, $secretKey, $initVector) != -1) 
        {
              $encryptedText = mcrypt_generic($openMode, $plainPad);
                  mcrypt_generic_deinit($openMode);
                        
        } 
        return bin2hex($encryptedText);
    }

    function decrypt($encryptedText,$key)
    {
        $secretKey = $this->hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText=$this->hextobin($encryptedText);
        $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '','cbc', '');
        mcrypt_generic_init($openMode, $secretKey, $initVector);
        $decryptedText = mdecrypt_generic($openMode, $encryptedText);
        $decryptedText = rtrim($decryptedText, "\0");
        mcrypt_generic_deinit($openMode);
        return $decryptedText;
        
    }
    //*********** Padding Function *********************

     function pkcs5_pad ($plainText, $blockSize)
    {
        $pad = $blockSize - (strlen($plainText) % $blockSize);
        return $plainText . str_repeat(chr($pad), $pad);
    }

    //********** Hexadecimal to Binary function for php 4.0 version ********

    function hextobin($hexString) 
    { 
        $length = strlen($hexString); 
        $binString="";   
        $count=0; 
        while($count<$length) 
        {       
            $subString =substr($hexString,$count,2);           
            $packedString = pack("H*",$subString); 
            if ($count==0)
        {
            $binString=$packedString;
        } 
            
        else 
        {
            $binString.=$packedString;
        } 
            
        $count+=2; 
        } 
        return $binString; 
    } 

   
}
