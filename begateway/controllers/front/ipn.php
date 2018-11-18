<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once dirname(__FILE__) . '/../../lib/BeGatewayAutoload.php';

/**
 * @property BeGateway $module
 */
class BeGatewayIpnModuleFrontController extends ModuleFrontController
{
    /**
     * @var BeGatewayIpn
     */
    protected $ipn;

    public function __construct()
    {
        parent::__construct();

        $this->ipn = new BeGatewayIpn($this->module);
    }

    public function postProcess()
    {
        $webhook = new \BeGateway\Webhook;
        $tracking_id = explode('|', $webhook->getTrackingId());
        $money = new \BeGateway\Money;
        $money->setCents($webhook->getResponse()->transaction->amount);
        $money->setCurrency($webhook->getResponse()->transaction->currency);

        $data = [
            'transactionType' => $webhook->getResponse()->transaction->type,
            'transactionId'   => $webhook->getUid(),
            'userOrderId'     => $tracking_id[0],
            'amount'          => $money->getAmount(),
            'status'          => $webhook->getStatus(),
            'currency'        => $money->getCurrency(),
            'orderCreatedAt'  => $webhook->getResponse()->transaction->created_at,
            'orderCompleteAt' => $webhook->getResponse()->transaction->paid_at,
            'test'            => $webhook->isTest()
        ];

        if ($webhook->getResponse()->transaction->type == 'refund') {
          $data['refundedAmount'] = $data['amount'];
        }

        if ($webhook->getResponse()->transaction->type == 'capture') {
          $data['capturedAmount'] = $data['amount'];
        }

        try {
            $this->ipn->validateIpnAuthorization($webhook);
            $order = $this->module->getOrderById($data['userOrderId']);
            $this->ipn->processData($order, $data);
            die('ok');
        } catch (Exception $e) {
            BeGatewayLog::saveException($e);
            die($this->module->l('Something went wrong.'));
        }
    }
}
