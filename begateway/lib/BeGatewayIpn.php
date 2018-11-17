<?php
/**
 * @author    eComCharge Team
 * @copyright Copyright (c) 2018 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayIpn
{
    protected static $VALID_STATUSES = [
        'successful',
        'failed',
        'pending',
        'incomplete',
        'refund'
    ];

    /**
     * @var BeGateway
     */
    protected $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    public function validateIpnAuthorization($webhook)
    {
        if (!$webhook->isAuthorized()) {
            throw new Exception('Invalid IPN authorizaton');
        }
    }

    public function processData($order, $data)
    {
        $this->validateOrder($order);
        $this->validateData($order, $data);
        $this->updateOrderState($order, $data);
    }

    protected function validateOrder($order)
    {
        if (is_null($order)) {
            throw new Exception('Order not found');
        }
    }

    /**
     * @param BeGatewayOrder $order
     * @param $data
     * @throws Exception
     */
    protected function validateData($order, $data)
    {
        if ($order->getId() != $data['userOrderId']) {
            throw new Exception('Order id does not match');
        }

        if (empty($data['transactionId']) || Tools::strlen(trim($data['transactionId'])) == 0) {
            throw new Exception('Invalid transaction id');
        }

        if ($order->getAmount() != $data['amount']) {
            throw new Exception('Invalid amount');
        }

        if ($order->getCurrency() !== $data['currency']) {
            throw new Exception('Invalid currency');
        }

        if (!in_array(Tools::strtolower((string) $data['status']), self::$VALID_STATUSES)) {
            throw new Exception('Unknown status');
        }
    }

    /**
     * @param BeGatewayOrder $order
     * @param $data
     */
    protected function updateOrderState($order, $data)
    {
        $transaction_id = $data['transactionId'];
        $state          = Tools::strtolower($data['status']);
        $transaction    = $order->getTransaction();
        $transaction->setTransactionId($transaction_id);
        $transaction->setStatus($state);
        $message = $data['test'] ? $this->module->l('TEST') : '';

        if ('successful' === $state && ($data['type'] == 'payment' || $data['type'] == 'authorization')) {
            $order->complete();
            $message .= ' '. $this->module->l('BeGateway IPN update: payment complete. Transaction id: ') . $transaction_id;
            $order->addMessage($message);
        } elseif ('successful' === $state && $data['type'] == 'void') {
            $order->cancel();
            $message .= ' '. $this->module->l('BeGateway IPN update: payment canceled. Transaction id: ') . $transaction_id;
            $order->addMessage($message);
        } elseif ('failed' === $state && ($data['type'] == 'payment' || $data['type'] == 'authorization')) {
            $order->cancel();
            $message .= ' '. $this->module->l('BeGateway IPN update: payment rejected. Transaction id: ') . $transaction_id;
            $order->addMessage($message);
        } elseif ('successful' === $state && $data['type'] == 'refund') {
            $refund = $data['refundedAmount'];
            $transaction->addRefundedAmount($refund);
            $order->refund();
            $message .= ' '. $this->module->l('BeGateway IPN update: payment refunded. Transaction id: ') . $transaction_id;
            $message .= ' ' . $this->module->l('Refunded amount: ') . $refund;
            $order->addMessage($message);
        }
        $transaction->save();
    }
}
