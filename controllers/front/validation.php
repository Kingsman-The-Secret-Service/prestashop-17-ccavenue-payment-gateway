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

class Kk_CcavenueValidationModuleFrontController extends ModuleFrontController
{
    /**
     * This class should be use by your Instant Payment
     * Notification system to validate the order remotely
     */
    public function postProcess()
    {
        // URLS
        $checkoutUrl = __PS_BASE_URI__.'order.php?step=1';

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'kk_ccavenue') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            $this->errors[] = $this->l('This payment method is not available.');
            $this->redirectWithNotifications($checkoutUrl);
        }

        //Fetch the response from CCAvenue
        $encResp = Tools::getValue('encResp');

        if (empty($encResp)) {

            $this->errors[] = $this->l('An error occured');
            $this->redirectWithNotifications($checkoutUrl);
        }

        $encryptionKey = Configuration::get('CCAVENUE_ENCRYPTION_KEY');
        $response = $this->module->decrypt($encResp, $encryptionKey);
        parse_str($response, $responseData);

        // Cart_ID -> merchant_param1
        $cart_id = $responseData['merchant_param1'];

        // customer_id -> merchant_param2
        $customer_id = $responseData['merchant_param2'];

        // Secure_key -> merchant_param3
        $secure_key = $responseData['merchant_param3'];

        // Order
        $order_id = $responseData['order_id'];
        $order_status = $responseData['order_status'];
        $amount = $responseData['amount'];

        $customer = new Customer((int)$customer_id);
        $cart =  new Cart((int)$cart_id);
        $total = $cart->getOrderTotal(true, Cart::BOTH);
        $currency_id = (int)Context::getContext()->currency->id;

        // Modules
        $module_name = $this->module->displayName;
        $module_id = $this->module->id;

        if ($customer_id == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {

            $this->errors[] = $this->l('An error occured');
            $this->redirectWithNotifications($checkoutUrl);
        }
        
        if (!Validate::isLoadedObject($customer)) {
            $this->errors[] = $this->l('An error occured');
            $this->redirectWithNotifications($checkoutUrl);
        }

        if($order_status === "Success")
        {   

            $currency_id = (int)Context::getContext()->currency->id;
            
            $this->success[] = $this->l("Thank you for shopping with us. Your transaction is successful. We will be shipping your order to you soon.");

            $payment_status = Configuration::get('PS_OS_PREPARATION');              
            $this->module->validateOrder($cart_id, $payment_status, $amount, $module_name, null, array('transaction_id' => $responseData['tracking_id']), $currency_id, false, $secure_key);

            $confirmationUrl = __PS_BASE_URI__.'order-confirmation.php?key='.$secure_key.'&id_cart='.(int)$cart_id.'&id_module='.(int)$module_id.'&id_order='.(int)$order_id;

            $this->redirectWithNotifications($confirmationUrl);
        }
        else if($order_status === "Aborted")
        {
            $this->errors[] = $this->l("Transaction Aborted/Canceled");
        }
        else if($order_status === "Failure")
        {
            $this->errors[] = $this->l("Transaction Declined");
        }

        if($responseData["status_message"] != "null")
                $this->errors[] = $this->l($responseData["status_message"]);

        $this->errors[] = $this->l("Please try again or contact the merchant to have more informations");
        
        return $this->redirectWithNotifications($checkoutUrl);
    }   
}
