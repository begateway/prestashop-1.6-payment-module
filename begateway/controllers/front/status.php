<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @property BeGateway $module
 */
class BeGatewayStatusModuleFrontController extends ModuleFrontController
{
    /**
     * @var BeGatewayConfirmation
     */
    protected $confirmation;

    public function __construct()
    {
        parent::__construct();

        $this->confirmation = new BeGatewayConfirmation($this->module->getSession());
    }

    public function postProcess()
    {
        $order = $this->getOrder();

        $success = false;
        $retry   = false;
        $message = '';

        try {
            $this->validateAccess($order);

            if ($this->confirmation->isPaymentComplete($order)) {
                $success = true;
                $message = $this->module->l('Your payment was completed! Please check order history for details.');
            } elseif ($this->confirmation->hasValidTransaction($order)) {
                $message = $this->module->l('Your payment status was updated. Please check order history for details.');
            } else {
                $retry = true;
            }
        } catch (Exception $e) {
            $message = $this->module->l($e->getMessage());
        }

        $response = Tools::jsonEncode([
            'success' => $success,
            'retry'   => $retry,
            'message' => $message,
        ]);

        if (method_exists($this, 'ajaxDie')) {
            $this->ajaxDie($response);
        } else {
            die($response);
        }
    }

    /**
     * @return BeGatewayOrder|null
     */
    protected function getOrder()
    {
        $order_id = Tools::getValue('order_id');
        $order    = $this->module->getOrderById($order_id);

        return $order;
    }

    /**
     * @param BeGatewayOrderInterface $order
     * @return bool
     */
    protected function isAccessAllowed($order)
    {
        $customer_id = (int) $this->context->customer->id;

        return !empty($customer_id) && !is_null($order) && $order->getCustomerId() == $customer_id;
    }

    /**
     * @param $order
     * @throws Exception
     */
    protected function validateAccess($order)
    {
        if (!$this->isAccessAllowed($order)) {
            throw new Exception('Access not allowed');
        }

        if (!$this->confirmation->canCheck()) {
            throw new Exception(
                'Your order payment processing is taking longer than usually. Please check later your order history.'
            );
        }
    }
}
