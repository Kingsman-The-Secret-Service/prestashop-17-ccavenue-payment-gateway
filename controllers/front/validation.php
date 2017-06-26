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

        $customer = new Customer((int)$customer_id);
        $cart =  new Cart((int)$cart_id);

        $order_status = $responseData['order_status'];

        $total = $cart->getOrderTotal(true, Cart::BOTH);

        if ($customer_id == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'ccavenue') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            die(Tools::displayError('This payment method is not available.'));
        }
        
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

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
        $module_id = $this->module->id;

        $this->module->validateOrder($cart_id, $payment_status, $amount, $module_name, $message, array(), $currency_id, false, $secure_key);

        

        $order_id = Order::getOrderByCartId((int)$cart_id);

        if ($order_id) {
            /**
             * The order has been placed so we redirect the customer on the confirmation page.
             */

            Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$secure_key.'&id_cart='.(int)$cart_id.'&id_module='.(int)$module_id.'&id_order='.(int)$order_id);

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
            <a href="'.$this->context->link->getPageLink('order', null, null, 'step=1').'">'.$this->module->l('Payment').'</a>
            <span class="navigation-pipe">&gt;</span>'.$this->module->l('Error'));

        /**
         * Set error message and description for the template.
         */
        array_push($this->errors, $this->module->l($message), $description);

        $this->context->smarty->assign('errors', $this->errors);

        return $this->setTemplate('module:ccavenue/views/templates/front/error.tpl');
    }

    
}
