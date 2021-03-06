<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

interface BeGatewayOrderItemInterface
{
    public function getSku();

    public function getName();

    public function getAmount();

    public function getQty();

    public function getType();

    public function getExtra();
}
