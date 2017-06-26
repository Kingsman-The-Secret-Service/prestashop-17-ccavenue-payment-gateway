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

class CcavenueValidationModuleFrontController extends ModuleFrontController
{
    /**
     * This class should be use by your Instant Payment
     * Notification system to validate the order remotely
     */
    public function postProcess()
    {

         /**
         * If the module is not active anymore, no need to process anything.
         */
        if ($this->module->active == false) {
            die;
        }

        //Fetch the response from CCAvenue
        $encResp = Tools::getValue('encResp');
        $encryptionKey = Configuration::get('CCAVENUE_ENCRYPTION_KEY');
        $response = $this->module->decrypt($encResp, $encryptionKey);
        parse_str($response, $responseData);

        // Cart_ID -> merchant_param1
        $cart_id = $responseData['merchant_param1'];

        // customer_id -> merchant_param2
        $customer_id = $responseData['merchant_param2'];

        // Secure_key -> merchant_param3
        $secure_key = $responseData['merchant_param3'];
        $amount = $responseData['amount'];

        /**
         * Restore the context from the $cart_id & the $customer_id to process the validation properly.
         */
        Context::getContext()->cart = new Cart((int)$cart_id);
        Context::getContext()->customer = new Customer((int)$customer_id);
        Context::getContext()->currency = new Currency((int)Context::getContext()->cart->id_currency);
        Context::getContext()->language = new Language((int)Context::getContext()->customer->id_lang);

        $secure_key = Context::getContext()->customer->secure_key;

        $order_status = $responseData['order_status'];

        if($order_status==="Success")
        {

            $payment_status = Configuration::get('PS_OS_PAYMENT'); 
            $message = "Thank you for shopping with us. Your transaction is successful. We will be shipping your order to you soon.";            
        }
        else if($order_status==="Aborted")
        {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $message =  "Transaction Aborted/Canceled";
            $description = $responseData["status_message"];
            return $this->displayError($message, $description);
        }
        else if($order_status==="Failure")
        {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $message = "Transaction Declined";
            $description = $responseData["status_message"];
            return $this->displayError($message, $description);
        }
        else
        {
            $payment_status = Configuration::get('PS_OS_ERROR');
            $message = "Transaction Declined";
            $description = $responseData["status_message"];
            return $this->displayError($message, $description);
        }

        $module_name = $this->module->displayName;
        $currency_id = (int)Context::getContext()->currency->id;

        $this->module->validateOrder($cart_id, $payment_status, $amount, $module_name, $message, array(), $currency_id, false, $secure_key);

                /**
         * If the order has been validated we try to retrieve it
         */
        $order_id = Order::getOrderByCartId((int)$cart_id);

        if ($order_id) {
            /**
             * The order has been placed so we redirect the customer on the confirmation page.
             */

            $module_id = $this->module->id;
            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart_id.'&id_module='.$module_id.'&id_order='.$order_id.'&key='.$secure_key);
        } else {
            /**
             * An error occured and is shown on a new page.
             */
            $message = 'An error occured. Please contact the merchant to have more informations';
            return $this->displayError($message);
        }
    }

    protected function displayError($message, $description = false)
    {
        /**
         * Create the breadcrumb for your ModuleFrontController.
         */
        $this->context->smarty->assign('path', '
            <a href="'.$this->context->link->getPageLink('order', null, null, 'step=3').'">'.$this->module->l('Payment').'</a>
            <span class="navigation-pipe">&gt;</span>'.$this->module->l('Error'));

        /**
         * Set error message and description for the template.
         */
        array_push($this->errors, $this->module->l($message), $description);
        return $this->setTemplate('error.tpl');
    }
}
