<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class BeGatewayOrderItemOther implements BeGatewayOrderItemInterface
{
    protected $amount;

    public function __construct($amount)
    {
        $this->amount = $amount;
    }

    public function getSku()
    {
        return 'other';
    }

    public function getName()
    {
        return 'Other';
    }

    public function getAmount()
    {
        return BeGatewayHelper::round($this->amount);
    }

    public function getQty()
    {
        return 1;
    }

    public function getType()
    {
        return 'other';
    }

    public function getExtra()
    {
        return '';
    }
}
