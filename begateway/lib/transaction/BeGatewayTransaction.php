<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayTransaction implements BeGatewayTransactionInterface
{
    protected $order_id;
    protected $status          = 'new';
    protected $refunded_amount = 0;
    protected $transaction_id;

    protected $is_new = true;

    public static function getByOrderId($order_id)
    {
        return new self($order_id);
    }

    public function __construct($order_id)
    {
        $this->order_id = (int) $order_id;
        $this->loadData();
    }

    public function isValid()
    {
        return null != $this->transaction_id;
    }

    public function getTransactionId()
    {
        return $this->transaction_id;
    }

    public function getRefundedAmount()
    {
        return $this->refunded_amount;
    }

    public function canBeRefunded()
    {
        return $this->isSuccess() || $this->isRefunded();
    }

    public function isSuccess()
    {
        return $this->isValid() && strcasecmp('complete', $this->status) === 0;
    }

    public function isRefunded()
    {
        return $this->isValid() && (strcasecmp('partial_refunded', $this->status) === 0
            || strcasecmp('refunded', $this->status) === 0);
    }

    public function setTransactionId($transaction_id)
    {
        $this->transaction_id = $transaction_id;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function addRefundedAmount($refunded_amount)
    {
        $this->refunded_amount += $refunded_amount;
    }

    protected function loadData()
    {
        $sql  = 'SELECT `id_transaction`, `status`, `refund_amount` FROM `' . _DB_PREFIX_ . 'begateway_transaction` WHERE ' . $this->getWhere();
        $data = Db::getInstance()->getRow($sql);
        if ($data) {
            $this->transaction_id  = $data['id_transaction'];
            $this->status          = $data['status'];
            $this->refunded_amount = (float) $data['refund_amount'];
            $this->is_new          = false;
        }
    }

    protected function getWhere()
    {
        return '`id_order`=' . $this->order_id;
    }

    public function save()
    {
        $data = [
            'uid'             => '\'' . pSQL($this->transaction_id) . '\'',
            'status'          => '\'' . pSQL($this->status) . '\'',
            'refund_amount'   => (float) $this->refunded_amount
        ];

        if ($this->is_new) {
            $data['date_add'] = '\'' . date('Y-m-d H:i:s') . '\'';
            $data['id_order'] = (int) $this->order_id;
            $columns          = '`' . implode('`,`', array_keys($data)) . '`';
            $values           = implode(', ', $data);
            $sql              = 'INSERT INTO `' . _DB_PREFIX_ . 'begateway_transaction` (' . $columns . ') VALUES (' . $values . ')';

            $success      = Db::getInstance()->Execute($sql);
            $this->is_new = !$success;
        } else {
            $set = [];
            foreach ($data as $key => $value) {
                $set[] = $key . '=' . $value;
            }

            $sql     = 'UPDATE `' . _DB_PREFIX_ . 'begateway_transaction` SET ' . implode(', ', $set) . ' WHERE ' . $this->getWhere();
            $success = Db::getInstance()->Execute($sql);
        }

        return $success;
    }
}
