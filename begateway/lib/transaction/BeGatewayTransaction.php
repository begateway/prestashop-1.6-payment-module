<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayTransaction implements BeGatewayTransactionInterface
{
    protected $order_id;
    protected $status = 'new';
    protected $amount = 0;
    protected $refunded_amount = 0;
    protected $captured_amount = 0;
    protected $transaction_id;
    protected $transaction_type;

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

    public function getTransactionType()
    {
        return $this->transaction_type;
    }

    public function getRefundedAmount()
    {
        return $this->refunded_amount;
    }

    public function getCapturedAmount()
    {
        return $this->captured_amount;
    }

    public function getVoidedAmount()
    {
        return $this->voided_amount;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function canBeRefunded()
    {
        return $this->isSuccess() && !$this->isRefunded() && $this->transaction_type == 'payment';
    }

    public function canBeCaptured()
    {
        return $this->isSuccess() && !$this->isCaptured() && $this->transaction_type == 'authorization';
    }

    public function canBeVoided()
    {
        return $this->isSuccess() && !$this->isVoided() && $this->transaction_type == 'authorization';
    }

    public function isSuccess()
    {
        return $this->isValid() && strcasecmp('successful', $this->status) === 0;
    }

    public function isRefunded()
    {
        return $this->isValid() && ($this->getAmount() == $this->getRefundedAmount());
    }

    public function isCaptured()
    {
        return $this->isValid() && $this->getCapturedAmount() > 0;
    }

    public function isVoided()
    {
        return $this->isValid() && $this->getVoidedAmount() > 0;
    }

    public function setTransactionId($transaction_id)
    {
        $this->transaction_id = $transaction_id;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function setTransactionType($type)
    {
        $this->transaction_type = $type;
    }

    public function addRefundedAmount($refunded_amount)
    {
        $this->refunded_amount += $refunded_amount;
    }

    public function addCapturedAmount($captured_amount)
    {
        $this->captured_amount = $captured_amount;
    }

    public function addVoidedAmount($voided_amount)
    {
        $this->voided_amount = $voided_amount;
    }

    public function addAmount($amount)
    {
        $this->amount = $amount;
    }

    protected function loadData()
    {
        $sql  = 'SELECT * FROM `' . _DB_PREFIX_ . 'begateway_transaction` WHERE ' . $this->getWhere();
        $data = Db::getInstance()->getRow($sql);
        if ($data) {
            $this->transaction_id  = $data['id_transaction'];
            $this->status          = $data['status'];
            $this->transaction_type= $data['type'];
            $this->amount          = (float) $data['amount'];
            $this->refunded_amount = (float) $data['refunded_amount'];
            $this->captured_amount = (float) $data['captured_amount'];
            $this->voided_amount   = (float) $data['voided_amount'];
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
            'id_transaction'  => '\'' . pSQL($this->transaction_id) . '\'',
            'status'          => '\'' . pSQL($this->status) . '\'',
            'amount'          => (float) $this->amount,
            'refunded_amount' => (float) $this->refunded_amount,
            'captured_amount' => (float) $this->captured_amount,
            'voided_amount'   => (float) $this->voided_amount,
            'type'            => '\'' . pSQL($this->transaction_type) . '\'',
            'date_upd'        => '\'' . date('Y-m-d H:i:s') . '\'',
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
