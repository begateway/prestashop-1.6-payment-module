<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayOrderItemDiscount implements BeGatewayOrderItemInterface
{
    protected $discount;

    public function __construct($discount)
    {
        $this->discount = $discount;
    }

    public function getId() {
        return $this->getSku();
    }

    public function getSku()
    {
        return $this->discount['id_cart_rule'];
    }

    public function getName()
    {
        return $this->discount['name'];
    }

    public function getAmount()
    {
        $discount = BeGatewayHelper::round($this->discount['value']);

        return -$discount;
    }

    public function getPrice()
    {
        return $this->getAmount();
    }

    public function getQty()
    {
        return 1;
    }

    public function getType()
    {
        return 'discount';
    }

    public function getExtra()
    {
        return '';
    }
}
