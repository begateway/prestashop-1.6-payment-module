<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayOrder implements BeGatewayOrderInterface
{
    /**
     * @var BeGatewayOrderInterface
     */
    protected $order;

    protected $customer;
    protected $currency;

    /**
     * @var BeGatewayTransactionInterface
     */
    protected $transaction;

    /**
     * @param Order $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * Get order id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->order->id;
    }

    /**
     * Get total order amount.
     *
     * @return float
     */
    public function getAmount()
    {
        return BeGatewayHelper::round($this->order->total_paid_tax_incl);
    }

    /**
     * Get currency symbol.
     *
     * @return string
     */
    public function getCurrency()
    {
        $this->initCurrency();

        return $this->currency->iso_code;
    }

    /**
     * Get customer id.
     *
     * @return int
     */
    public function getCustomerId()
    {
        $this->initCustomer();

        return $this->customer->id;
    }

    /**
     * Get customer email.
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        $this->initCustomer();

        return $this->customer->email;
    }

    /**
     * Get order items array.
     *
     * @return array
     */
    public function getItems()
    {
        $items    = [];
        $products = $this->order->getProducts();
        foreach ($products as $product) {
            $item = new BeGatewayOrderItemProduct($product);
            if ($item->getAmount() != 0) {
                $items[] = $item;
            }
        }

        $shipping = $this->order->getShipping();
        foreach ($shipping as $ship) {
            $item = new BeGatewayOrderItemShipping($ship);
            if ($item->getAmount() != 0) {
                $items[] = $item;
            }
        }

        $discounts = $this->order->getCartRules();
        foreach ($discounts as $discount) {
            $item = new BeGatewayOrderItemDiscount($discount);
            if ($item->getAmount() != 0) {
                $items[] = $item;
            }
        }

        $totalsDiff = $this->getAmount() - $this->sumItemsAmount($items);
        if (abs($totalsDiff) > 0.0001) {
            $item = new BeGatewayOrderItemOther($totalsDiff);
            if ($item->getAmount() != 0) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Get order transaction.
     *
     * @return BeGatewayTransactionInterface
     */
    public function getTransaction()
    {
        $this->initTransaction();

        return $this->transaction;
    }

    /**
     * Init order transaction.
     */
    protected function initTransaction()
    {
        if (is_null($this->transaction)) {
            $this->transaction = new BeGatewayTransaction($this->order->id);
        }
    }

    /**
     * Init order currency.
     */
    protected function initCurrency()
    {
        if (is_null($this->currency)) {
            $this->currency = new Currency($this->order->id_currency);
        }
    }

    /**
     * Get order description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->order->reference;
    }

    /**
     * Init order customer.
     */
    protected function initCustomer()
    {
        if (is_null($this->customer)) {
            $this->customer = new Customer($this->order->id_customer);
        }
    }

    /**
     * Mark order as  complete.
     *
     * @return bool
     */
    public function complete()
    {
        if (!$this->order->hasInvoice()) {
            $transaction = $this->getTransaction();
            // complete should be done when full amount is paid
            $this->order->addOrderPayment($this->getAmount(), null, $transaction->getTransactionId());
            $this->order->setInvoice(true);
        }

        $status = Configuration::get('PS_OS_PAYMENT') ? Configuration::get('PS_OS_PAYMENT') : _PS_OS_PAYMENT_;

        return $this->updateState($status);
    }

    /**
     * Mark order as  canceled.
     *
     * @return bool
     */
    public function cancel()
    {
        $status = Configuration::get('PS_OS_CANCELED') ? Configuration::get('PS_OS_CANCELED') : _PS_OS_CANCELED_;

        return $this->updateState($status);
    }

    /**
     * Mark order as refunded.
     *
     * @return bool
     */
    public function refund()
    {
        $status = Configuration::get('PS_OS_REFUND') ? Configuration::get('PS_OS_REFUND') : _PS_OS_REFUND_;

        return $this->updateState($status);
    }

    /**
     * Add order private message.
     *
     * @param $text
     * @return bool
     */
    public function addMessage($text)
    {
        $message = new Message();
        $text    = strip_tags($text, '<br>');

        if (!Validate::isCleanHtml($text)) {
            $text = 'Invalid payment message.';
        }

        $message->message  = $text;
        $message->id_order = (int) $this->order->id;
        $message->private  = 1;

        return $message->add();
    }

    /**
     * Update order state.
     *
     * @param $state
     * @param bool $email
     * @return bool
     */
    protected function updateState($state, $email = false)
    {
        $state = new OrderState($state);

        if (!Validate::isLoadedObject($state)) {
            return false;
        }

        $current_state = $this->order->getCurrentOrderState();
        if ($current_state->id != $state->id) {
            $history              = new OrderHistory();
            $history->id_order    = $this->order->id;
            $history->id_employee = 0;
            $history->changeIdOrderState((int) $state->id, $this->order, $this->order->hasInvoice());

            return $email ? $history->addWithemail() : $history->add();
        }

        return true;
    }

    public function canRefundAmount($amount = 0)
    {
        $transaction = $this->getTransaction();

        return $transaction->canBeRefunded() && $amount >= 0 && $this->getMaxRefundAmount() >= $amount;
    }

    public function getMaxRefundAmount()
    {
        $transaction = $this->getTransaction();

        return BeGatewayHelper::round($this->getAmount() - $transaction->getRefundedAmount());
    }

    public function canCaptureAmount($amount = 0)
    {
        $transaction = $this->getTransaction();

        return $transaction->canBeCaptured() && $amount >= 0 && $this->getMaxCaptureAmount() >= $amount;
    }

    public function getMaxCaptureAmount()
    {
        $transaction = $this->getTransaction();

        return BeGatewayHelper::round($this->getAmount() - $transaction->getCapturedAmount());
    }

    public function canVoidAmount($amount = 0)
    {
        $transaction = $this->getTransaction();

        return $transaction->canBeVoided() && $amount >= 0 && $this->getMaxVoidAmount() >= $amount;
    }

    public function getMaxVoidAmount()
    {
        $transaction = $this->getTransaction();

        return BeGatewayHelper::round($this->getAmount() - $transaction->getVoidedAmount());
    }


    protected function sumItemsAmount($items)
    {
        $amount = 0;
        /** @var BeGatewayOrderItemInterface $item */
        foreach ($items as $item) {
            $amount += $item->getAmount();
        }

        return $amount;
    }
}
