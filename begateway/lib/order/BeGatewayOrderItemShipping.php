<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayOrderItemShipping implements BeGatewayOrderItemInterface
{
    protected $shipping;

    public function __construct($shipping)
    {
        $this->shipping = $shipping;
    }

    public function getId()
    {
        return $this->getSku();
    }

    public function getSku()
    {
        return $this->shipping['id_carrier'];
    }

    public function getName()
    {
        return $this->shipping['carrier_name'];
    }

    public function getAmount()
    {
        return BeGatewayHelper::round($this->shipping['shipping_cost_tax_incl']);
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
        return 'shipping';
    }

    public function getExtra()
    {
        return $this->shipping['type'];
    }
}
