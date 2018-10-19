<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author eComCharge <techsupport@ecomcharge.com>
*  @copyright  2016 eComCharge
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

include(dirname(__FILE__). '/../../config/config.inc.php');
include(dirname(__FILE__). '/../../header.php');

class begatewayValidation extends begateway {

  public function initContent() {
		$this->be_gateway = new begateway();

		if ($this->be_gateway->active && isset($_REQUEST['action'])) {
      $action=$_REQUEST['action'];

			if ($action == 'callback') {
				$this->_processWebhook();
      }elseif ($action == 'success') {
        $this->_processReturn();
      }elseif ($action == 'payment') {
        $this->_processPayment();
      }else{
        $this->_processReturn(true);
      }
		}
    Tools::redirectLink(__PS_BASE_URI__.'index.php');
	}

  private function _processWebhook(){
    $webhook = new \BeGateway\Webhook;

    if ($webhook->isAuthorized() &&
        ($webhook->isSuccess() || $webhook->isFailed())
    ) {
      $Status = $webhook->getStatus();
      $Currency = $webhook->getResponse()->transaction->currency;
      $Amount = new \BeGateway\Money;
      $Amount->setCurrency($Currency);
      $Amount->setCents($webhook->getResponse()->transaction->amount);
      $TransId = $webhook->getUid();
      $orderno = $webhook->getTrackingId();

      $cart = new Cart((int)$orderno);
      if (!Validate::isLoadedObject($cart)) {
        Logger::addLog($this->l('Webhook: error to load cart'),4);
        die($this->l('Critical error to load order cart'));
      }

      $customer = new Customer((int)$cart->id_customer);
      if (!Validate::isLoadedObject($customer)) {
        Logger::addLog($this->l('Webhook: error to load customer details'),4);
        die($this->l('Critical error to load customer'));
      }

      $shop_ptype = trim(Configuration::get('BEGATEWAY_SHOP_PAYTYPE'));

      $payment_status = $webhook->isSuccess() ? Configuration::get('PS_OS_PAYMENT') : Configuration::get('PS_OS_ERROR');

      $this->be_gateway->validateOrder(
        (int)$orderno,
        $payment_status,
        $Amount->getAmount(),
        $this->be_gateway->displayName,
        $webhook->getMessage(),
        array('transaction_id' => $TransId),
        NULL,
        false,
        $customer->secure_key);

      $order_new = (empty($this->be_gateway->currentOrder)) ? $orderno : $this->be_gateway->currentOrder;
      Db::getInstance()->Execute('
        INSERT INTO '._DB_PREFIX_.'begateway_transaction (type, id_begateway_customer, id_cart, id_order,
          uid, amount, status, currency, date_add)
          VALUES ("'.$shop_ptype.'", '.$cart->id_customer.', '.$orderno.', '.$order_new.', "'.$TransId.'", '.$Amount->getAmount().', "'.$Status.'", "'.$Currency.'", NOW())');

      die('OK');
    }
  }

  private function _processReturn($error = false) {
    $auth_order = new Order($this->be_gateway->currentOrder);

    $redirect_url = Context::getContext()->link->getPageLink(
      'order-confirmation', null, null, array(
        'id_order' => $auth_order->id,
        'id_cart' => (int)@$_REQUEST['id_cart'],
        'id_module' => (int)$this->be_gateway->id,
        'key' => $auth_order->secure_key,
        'beGatewayerror' => ($error) ? '1':'0'
      )
    );
    Tools::redirect($redirect_url);
  }

  private function _processPayment($params)
  {
    if (!$this->active) return ;

    $errors = [];

    $cart = Context::getContext()->cart;
    $currency = new Currency((int)$cart->id_currency);
    $customer = new Customer((int)$cart->id_customer);
    $address = new Address(intval($cart->id_address_invoice));
    $country = Country::getIsoById((int)$address->id_country);
    $lang_iso_code = strtolower(Language::getIsoById(Context::getContext()->cookie->id_lang));
    $paymentUrl = null;

    if (!isset($_REQUEST['order_id']))
    {
    	Logger::addLog($this->l('No order id in params'), 4);
    	$errors[]=$this->l('Critical error: no order id in request');
    } elseif (!Validate::isLoadedObject($cart))
    {
    	Logger::addLog($this->l('Error to load cart data of order id ').(int)$_REQUEST['order_id'], 4);
    	$errors[]=$this->l('Critical error: no cart data for order id ').(int)$_REQUEST['order_id']);
    } elseif ($cart->id != $_REQUEST['order_id'])
    {
    	Logger::addLog($this->l('Cart Id does not match order Id'), 4);
    	$errors[]=$this->l('Critical error: cart id does not match order id ').(int)$_REQUEST['order_id']);
    } elseif (!Validate::isLoadedObject($currency))
    {
    	Logger::addLog($this->l('Error to load currency data'), 4);
    	$errors[]=$this->l('Critical error to load currency data of order id ').(int)$_REQUEST['order_id']);
    } elseif (!Validate::isLoadedObject($customer))
    {
    	Logger::addLog($this->l('Error to load customer data'), 4);
    	$errors[]=$this->l('Critical error to load customer data of order id ').(int)$_REQUEST['order_id']);
    } else {

      $email = (isset($customer->email)) ? $customer->email : Configuration::get('PS_SHOP_EMAIL');

      $shop_ptype = trim(Configuration::get('BEGATEWAY_SHOP_PAYTYPE'));
      $currency_code=trim($currency->iso_code);
      $amount = $cart->getOrderTotal(true, 3);

      $return_base_url=(Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/'.$this->name.'/validation.php?';
      $callbackurl = $return_base_url . 'action=callback';
      $callbackurl = str_replace('carts.local', 'webhook.begateway.com:8443', $callbackurl);

      $cancelurl=(Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__;
      $successurl = $return_base_url . 'action=success&id_cart='.(int)($params['cart']->id).'&id_module='.(int)($this->id).'&key='.$customer->secure_key;
      $failurl = $return_base_url . 'action=fail';

      $state_val = NULL;

      if (in_array($country, array('US','CA'))) {
        $state = new State((int)$address->id_state);
        if (Validate::isLoadedObject($state)) {
          $state_val = $state->iso_code;
        } else {
          $state_val = 'NA';
        }
      }

      $phone = ($address->phone) ? $address->phone : $address->phone_mobile;

      $transaction = new \BeGateway\GetPaymentToken;

      if ($shop_ptype == 'authorization') {
        $transaction->setAuthorizationTransactionType();
      } else {
        $transaction->setPaymentTransactionType();
      }

      $transaction->money->setCurrency($currency_code);
      $transaction->money->setAmount($amount);
      $transaction->setDescription($this->l('Order No. ').$params['cart']->id);
      $transaction->setTrackingId($params['cart']->id);
      $transaction->setLanguage($lang_iso_code);
      $transaction->setNotificationUrl($callbackurl);
      $transaction->setSuccessUrl($successurl);
      $transaction->setDeclineUrl($failurl);
      $transaction->setFailUrl($failurl);

      $transaction->customer->setFirstName($address->firstname);
      $transaction->customer->setLastName($address->lastname);
      $transaction->customer->setCountry($country);
      $transaction->customer->setAddress($address->address1.' '.$address->address2);
      $transaction->customer->setCity($address->city);
      $transaction->customer->setZip($address->postcode);
      $transaction->customer->setEmail($cookie->email);
      $transaction->customer->setPhone($phone);
      $transaction->customer->setState($state_val);


      try {
        $response = $transaction->submit();
        if ($response->isError()) $errors[]= $response->getMessage();
        if ($response->isSuccess()) {
          $paymentUrl = $response->getRedirectUrl();
        }
      } catch (Exception $e) {
        $errors[]= $e->getMessage();
      }
    }

    $this->context->smarty->assign(array(
      'paymentUrl' => $paymentUrl,
      'err_msg' => $errors,
      'this_path' => $this->_path
    ));

    return $this->display(__FILE__, 'views/templates/hook/orderconfirmation.tpl');
  }
}

$validation = new begatewayValidation();
$validation->initContent();
?>
