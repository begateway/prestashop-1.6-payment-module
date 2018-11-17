<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class BeGatewayOrderItemProduct implements BeGatewayOrderItemInterface
{
    protected $product;

    public function __construct($product)
    {
        $this->product = $product;
    }

    public function getId()
    {
        return $this->product['product_id'];
    }

    public function getSku()
    {
        $product_ids = [$this->product['product_id']];
        if (!empty($this->product['product_attribute_id'])) {
            $product_ids[] = $this->product['product_attribute_id'];
        }

        return implode(',', $product_ids);
    }

    public function getName()
    {
        return $this->product['product_name'];
    }

    public function getPrice()
    {
        return $this->getAmount() / $this->getQty();
    }

    public function getAmount()
    {
        return $this->product['total_price_tax_incl'];
    }

    public function getQty()
    {
        return $this->product['product_quantity'];
    }

    public function getType()
    {
        return 'product';
    }

    public function getExtra()
    {
        return $this->product['product_reference'];
    }
}
