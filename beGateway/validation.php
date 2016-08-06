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

class beGatewayValidation extends beGateway {

  public function initContent() {
		$this->be_gateway = new beGateway();

		if ($this->be_gateway->active && isset($_REQUEST['action'])) {
      $action=$_REQUEST['action'];

			if ($action == 'callback') {
				$this->_processWebhook();
      }elseif ($action == 'success') {
        $this->_processReturn();
      }else{
        $this->_processReturn(true);
      }
		}
    Tools::redirectLink(__PS_BASE_URI__.'index.php');
	}

  private function _processWebhook(){
    $webhook = new \beGateway\Webhook;

    if ($webhook->isAuthorized() &&
        ($webhook->isSuccess() || $webhook->isFailed())
    ) {
      $Status = $webhook->getStatus();
      $Currency = $webhook->getResponse()->transaction->currency;
      $Amount = new \beGateway\Money;
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
}

$validation = new beGatewayValidation();
$validation->initContent();
?>
