<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayHelper
{
    /**
     * Round amount helper.
     * @param $amount
     * @return float
     */
    public static function round($amount)
    {
        return round($amount, 2);
    }

    public static function hash($string)
    {
        return hash('sha256', $string);
    }

    /**
     * Create link to module controller.
     * @param $name
     * @param array $params
     * @return mixed
     */
    public static function controllerLink($name, array $params = [])
    {
        $context = Context::getContext();
        $link    = $context->link->getModuleLink('begateway', $name, $params);

        return $link;
    }

    /**
     * Create link to module.
     * @return string
     */
    public static function getModuleUri()
    {
        return _MODULE_DIR_ . 'begateway/';
    }

    /**
     * Get shop domain.
     * @return string
     */
    public static function getShopDomain()
    {
        if (method_exists('Tools', 'getShopDomainSsl')) {
            return Tools::getShopDomainSsl(true, false);
        } else {
            $domain = Configuration::get('PS_SHOP_DOMAIN_SSL');
            if (!$domain) {
                $domain = Tools::getHttpHost();
            }

            return (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . $domain;
        }
    }

    /**
     * Redirect to specified destination.
     * @param $redirect
     */
    public static function redirect($redirect)
    {
        Tools::redirect($redirect);
    }

    /**
     * Redirect to cart.
     */
    public static function redirectBackToCart()
    {
        Tools::redirect('index.php?controller=order&step=1');
    }

    /**
     * Check backward compatibility.
     * @return mixed
     */
    public static function hasBackwardCompatibility()
    {
        return version_compare(_PS_VERSION_, '1.5', '<');
    }
}
