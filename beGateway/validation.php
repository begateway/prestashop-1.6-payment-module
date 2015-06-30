<?php

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__). '/../../init.php');
require_once(dirname(__FILE__).'/beGateway.php');

$action=$_REQUEST['action'];

if($action!="") {
  $beGateway = new beGateway;
  if($action == "callback") {
    $webhook = new \beGateway\Webhook;

    if ($webhook->isAuthorized()) {
      $Status = $webhook->getStatus();
      $Currency = $webhook->getResponse()->transaction->currency;
      $Amount = new \beGateway\Money;
      $Amount->setCurrency($Currency);
      $Amount->setCents($webhook->getResponse()->transaction->amount);
      $TransId = $webhook->getUid();
      $orderno = $webhook->getTrackingId();
      $cart = new Cart((int)$orderno);

      $customer = new Customer((int)$cart->id_customer);

      $shop_ptype = trim(Configuration::get('BEGATEWAY_SHOP_PAYTYPE'));

      $payment_status = $webhook->isSuccess() ? Configuration::get('PS_OS_PAYMENT') : Configuration::get('PS_OS_ERROR');

      $beGateway->validateOrder(
        (int)$orderno,
        $payment_status,
        $Amount->getAmount(),
        $beGateway->displayName,
        $webhook->getMessage(),
        array('transaction_id' => $TransId),
        NULL,
        false,
        $customer->secure_key);

      $order_new = (empty($beGateway->currentOrder)) ? $orderno : $beGateway->currentOrder;
      Db::getInstance()->Execute('
        INSERT INTO '._DB_PREFIX_.'begateway_transaction (type, id_begateway_customer, id_cart, id_order,
          uid, amount, status, currency, date_add)
          VALUES ("'.$shop_ptype.'", '.$cart->id_customer.', '.$orderno.', '.$order_new.', "'.$TransId.'", '.$Amount->getAmount().', "'.$Status.'", "'.$Currency.'", NOW())');
      echo "OK" ;
      exit;
    }
  } elseif ($action == "success") {
    $url = 'index.php?controller=order-confirmation&';
    if (_PS_VERSION_ < '1.5')
      $url = 'order-confirmation.php?';
    Tools::redirect($url.'id_module='.$_REQUEST['id_module'].'&id_cart='.
      (int)$_REQUEST['id_cart'].'&key='.$_REQUEST['key']);

  } else {
    $beGateway_status = Configuration::get('PS_OS_ERROR');
    $checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
    $url = _PS_VERSION_ >= '1.5' ? 'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?';
    $url .= 'step=3&cgv=1&beGatewayerror=1';

    if (!isset($_SERVER['HTTP_REFERER']) ||
      strstr($_SERVER['HTTP_REFERER'], 'order'))
      Tools::redirect($url);
    elseif (strstr($_SERVER['HTTP_REFERER'], '?'))
      Tools::redirect(Tools::safeOutput($_SERVER['HTTP_REFERER']).'&beGatewayerror=1', '');
    else
      Tools::redirect(Tools::safeOutput($_SERVER['HTTP_REFERER']).'?beGatewayerror=1', '');
  }
} else {
  Tools::redirectLink(__PS_BASE_URI__.'index.php');
}
?>
