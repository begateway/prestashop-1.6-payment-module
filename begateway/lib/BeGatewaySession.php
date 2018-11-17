<?php
/**
 * @author    eComCharge Team
 * @copyright Copyright (c) 2018 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewaySession
{
    protected $context;

    public function __construct($context = null)
    {
        $this->context = is_null($context) ? Context::getContext() : $context;
    }

    public function storeOrderSecureData($order, $token = null)
    {
        if (is_null($token)) {
            $token = BeGatewayHelper::hash(Tools::passwdGen(32) . time());
        }

        $this->context->cookie->begateway_order_id    = $order->getId();
        $this->context->cookie->begateway_order_token = $token;

        return $token;
    }

    public function validateOrderSecureData($data)
    {
        if (!isset($data['order_id'])
            || empty($data['order_id'])
            || $this->context->cookie->begateway_order_id != $data['order_id']
        ) {
            throw new Exception('Invalid order');
        }

        if (!isset($data['token'])
            || empty($data['token'])
            || $this->context->cookie->begateway_order_token != $data['token']
        ) {
            throw new Exception('Invalid token');
        }
    }

    public function clearOrderSecureData()
    {
        unset($this->context->cookie->begateway_order_id);
        unset($this->context->cookie->begateway_order_token);
    }

    public function generateSubmitToken()
    {
        $token                                      = Tools::passwdGen(32);
        $this->context->cookie->begateway_submit_token = $token;

        return $token;
    }

    public function matchSubmitToken($match)
    {
        $result = isset($this->context->cookie->begateway_submit_token) && $this->context->cookie->begateway_submit_token === $match;
        unset($this->context->cookie->begateway_submit_token);

        return $result;
    }

    public function setValue($name, $value)
    {
        $this->context->cookie->__set($name, $value);
    }

    public function getValue($name, $default = null)
    {
        return $this->context->cookie->__isset($name) ? $this->context->cookie->__get($name) : $default;
    }
}
