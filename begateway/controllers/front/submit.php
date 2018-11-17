<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @property BeGateway $module
 */
class BeGatewaySubmitModuleFrontController extends ModuleFrontController
{
    /**
     * @var BeGateway
     */
    protected $gateway;

    public function __construct()
    {
        parent::__construct();

        $this->gateway = new BeGatewayGateway($this->module->getSession());
    }

    public function postProcess()
    {
        $cart = $this->context->cart;

        $submit_token       = Tools::getValue('submitToken');
        $submit_token_match = $this->module->getSession()->matchSubmitToken($submit_token);

        if ($cart->id_customer == 0
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active
            || !$submit_token_match
        ) {
            BeGatewayHelper::redirectBackToCart();
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            BeGatewayHelper::redirectBackToCart();
        }

        $currency = $this->context->currency;
        $total    = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $mailVars = [];

        $this->module->validateOrder(
            (int) $cart->id,
            Configuration::get('BEGATEWAY_OS_NEW'),
            $total,
            $this->module->displayName,
            null,
            $mailVars,
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $redirect = null;
        try {
            $redirect = $this->getCreateQuoteRedirectForCurrentOrder();
        } catch (Exception $e) {
            BeGatewayHelper::redirectBackToCart();
        }

        if (is_null($redirect)) {
            die($this->module->l('Payment processing failed.'));
        }

        BeGatewayHelper::redirect($redirect);
    }

    protected function getCreateQuoteRedirectForCurrentOrder()
    {
        $order = $this->module->getCurrentOrder();

        return $this->gateway->getGatewayRedirectUrlForOrder($order);
    }
}
