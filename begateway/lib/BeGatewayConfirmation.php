<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayConfirmation
{
    const COUNTER_NAME = 'begateway_confirmation_counter';
    const MAX_ATTEMPTS = 10;

    /**
     * @var BeGateway
     */
    protected $session;

    public function __construct($session)
    {
        $this->session = $session;
    }

    /**
     * @param BeGatewayOrderInterface $order
     */
    public function isPaymentComplete($order)
    {
        return $order->getTransaction()->isSuccess();
    }

    /**
     * @param BeGatewayOrderInterface $order
     */
    public function hasValidTransaction($order)
    {
        return $order->getTransaction()->isValid();
    }

    public function canCheck()
    {
        $can_check = $this->checkCounter();
        $this->increaseCounter();

        return $can_check;
    }

    public function reset()
    {
        $this->session->setValue(self::COUNTER_NAME, 0);
    }

    protected function checkCounter()
    {
        $counter = (int) $this->session->getValue(self::COUNTER_NAME, 0);

        return $counter <= self::MAX_ATTEMPTS;
    }

    protected function increaseCounter()
    {
        $counter = (int) $this->session->getValue(self::COUNTER_NAME, 0);
        $this->session->setValue(self::COUNTER_NAME, ++$counter);
    }
}
