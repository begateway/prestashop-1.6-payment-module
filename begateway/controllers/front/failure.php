<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @property BeGateway $module
 */
class BeGatewayFailureModuleFrontController extends ModuleFrontController
{
    /**
     * @var BeGatewayGateway
     */
    protected $gateway;

    public function __construct()
    {
        parent::__construct();

        $this->gateway = new BeGatewayGateway($this->module->getSession());
    }

    public function postProcess()
    {
        $data = [
            'order_id' => Tools::getValue('order_id'),
            'token'    => Tools::getValue('order_token'),
        ];

        $order = $this->module->getOrderById($data['order_id']);
        try {
            $this->gateway->processFailureCallback($order, $data);
        } catch (Exception $e) {
            BeGatewayHelper::redirectBackToCart();
        }

        // Reorder cancelled order
        $opc = (bool) Configuration::get('PS_ORDER_PROCESS_TYPE');
        if ($opc) {
            $link = $this->context->link->getPageLink('order-opc.php', true) . '?submitReorder&id_order=' . $order->getId();
        } else {
            $link = $this->context->link->getPageLink('order.php', true) . '?submitReorder&id_order=' . $order->getId();
        }

        BeGatewayHelper::redirect($link);
    }
}
