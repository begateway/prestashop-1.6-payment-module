<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @property BeGateway $module
 */
class BeGatewaySuccessModuleFrontController extends ModuleFrontController
{
    /**
     * @var BeGatewayGateway
     */
    protected $gateway;

    /**
     * @var BeGatewayConfirmation
     */
    protected $confirmation;

    public function __construct()
    {
        parent::__construct();

        $this->gateway      = new BeGatewayGateway($this->module->getSession());
        $this->confirmation = new BeGatewayConfirmation($this->module->getSession());
    }

    public function postProcess()
    {
        $data = [
            'order_id' => Tools::getValue('order_id'),
            'token'    => Tools::getValue('order_token'),
        ];

        $order = $this->module->getOrderById($data['order_id']);
        try {
            $this->gateway->processSuccessCallback($order, $data);
        } catch (Exception $e) {
            BeGatewayHelper::redirect($this->context->link->getPageLink('history.php', true));
        }

        $this->confirmation->reset();

        $module_dir = $this->module->getPathUri();
        $this->context->smarty->assign([
            'begateway_path' => $module_dir,
            'order_id'    => $order->getId(),
            'status_link' => BeGatewayHelper::controllerLink('status'),
        ]);

        $this->addJquery();
        $this->addJS($module_dir . 'views/js/success.js');
        $this->addCSS($module_dir . 'views/css/success.css');

        $this->setTemplate('success.tpl');
    }
}
