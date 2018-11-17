<?php
require_once _PS_MODULE_DIR_ . 'begateway/lib/BeGatewayAutoload.php';

class BeGateway extends PaymentModule
{
  const TEST_SHOP   = 361;
  const TEST_KEY    = 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d';
  const TEST_DOMAIN = 'checkout.begateway.com';
  private	$_html = '';
  private $_postErrors = array();
  protected $session;

  public function __construct()
  {
    $this->name = 'begateway';
    $this->tab = 'payments_gateways';
    $this->version = '1.4.0';
    $this->controllers = array('payment', 'validation');
    $this->author = 'eComCharge';

    $this->currencies = true;
    $this->currencies_mode = 'checkbox';

    parent::__construct();

    \BeGateway\Settings::$checkoutBase = 'https://' . trim(Configuration::get('BEGATEWAY_DOMAIN_CHECKOUT'));
    \BeGateway\Settings::$shopId  = trim(Configuration::get('BEGATEWAY_SHOP_ID'));
    \BeGateway\Settings::$shopKey = trim(Configuration::get('BEGATEWAY_SHOP_PASS'));

    $this->page = basename(__FILE__, '.php');
    $this->displayName = $this->l('BeGateway');
    $this->description = $this->l('Accepts online payments');
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    $this->session = new BeGatewaySession($this->context);
  }

  public function install()
  {
    if (!parent::install()
      OR !Configuration::updateValue('BEGATEWAY_SHOP_ID', $this::TEST_SHOP)
      OR !Configuration::updateValue('BEGATEWAY_SHOP_PASS', $this::TEST_KEY)
      OR !Configuration::updateValue('BEGATEWAY_SHOP_PAYTYPE', '')
      OR !Configuration::updateValue('BEGATEWAY_DOMAIN_CHECKOUT', $this::TEST_DOMAIN)
      OR !Configuration::updateValue('BEGATEWAY_TEST_MODE', '1')
      OR !$this->registerHook('payment')
      OR !$this->registerHook('backOfficeHeader')
      OR !$this->registerHook('paymentReturn')
      OR !$this->registerHook('orderConfirmation')
      OR !$this->installTable()
      OR !$this->createOrderState()) {
        return false;
    }

    if (_PS_VERSION_ > 1.4 && !$this->registerHook('displayHeader')) {
      return false;
    }

    return true;
  }

  public function installTable()
  {
    return Db::getInstance()->Execute('
      CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'begateway_transaction` (
        `id_begateway_transaction` int(11) NOT NULL AUTO_INCREMENT,
        `type` enum(\'payment\',\'refund\',\'authorization\') NOT NULL,
        `id_begateway_customer` int(10) unsigned NOT NULL,
        `id_cart` int(10) unsigned NOT NULL,
        `id_order` int(10) unsigned NOT NULL,
        `uid` varchar(60) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `status` enum(\'incomplete\',\'failed\',\'successful\', \'pending\') NOT NULL,
        `currency` varchar(3) NOT NULL,
        `id_refund` varchar(32) ,
        `refund_amount` decimal(10,2),
        `au_uid` varchar(60),
        `token` varchar(100),
        `date_add` datetime NOT NULL,
      PRIMARY KEY (`id_begateway_transaction`),
      KEY `idx_transaction` (`type`,`id_order`,`status`)
    )
    ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1');
  }

  public function uninstall()
  {
    if (!Configuration::deleteByName('BEGATEWAY_SHOP_ID')
      OR !Configuration::deleteByName('BEGATEWAY_SHOP_PASS')
      OR !Configuration::deleteByName('BEGATEWAY_SHOP_PAYTYPE')
      OR !Configuration::deleteByName('BEGATEWAY_DOMAIN_CHECKOUT')
      OR !Configuration::deleteByName('BEGATEWAY_TEST_MODE')
      OR !Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'begateway_transaction`')
      OR !$this->unregisterHook('payment')
      OR !$this->unregisterHook('backOfficeHeader')
      OR !$this->unregisterHook('displayHeader')
      OR !$this->unregisterHook('paymentReturn')
      OR !$this->unregisterHook('orderConfirmation')
      OR !parent::uninstall())
      return false;
    return true;
  }

  public function getContent()
  {
    $this->_html = '<h2>BeGateway</h2>';
    if (isset($_POST['submit_begateway']))
    {
      if (empty($_POST['shop_id']))
        $this->_postErrors[] = $this->l('Shop Id is required.');
      if (empty($_POST['shop_pass']))
        $this->_postErrors[] = $this->l('Shop secret key is required.');

      if (empty($_POST['domain_checkout']))
        $this->_postErrors[] = $this->l('Checkout page domain is required.');
      if (!sizeof($this->_postErrors))
      {
        Configuration::updateValue('BEGATEWAY_SHOP_ID', strval($_POST['shop_id']));
        Configuration::updateValue('BEGATEWAY_SHOP_PASS', strval($_POST['shop_pass']));
        Configuration::updateValue('BEGATEWAY_SHOP_PAYTYPE', strval($_POST['payment_type']));
        Configuration::updateValue('BEGATEWAY_DOMAIN_CHECKOUT', strval($_POST['domain_checkout']));
        Configuration::updateValue('BEGATEWAY_TEST_MODE', strval($_POST['test_mode']));
        $this->displayConf();
      }
      else
        $this->displayErrors();
    }

    $this->displaybeGateway();
    $this->displayFormSettings();
    return $this->_html;
  }

  public function displayConf()
  {
    $this->_html .= '
      <div class="conf confirm">
      <img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
      '.$this->l('Settings updated').'
      </div>';
  }

  public function displayErrors()
  {
    $nbErrors = sizeof($this->_postErrors);
    $this->_html .= '
      <div class="alert error">
      <h3>'.($nbErrors > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'</h3>
      <ol>';
    foreach ($this->_postErrors AS $error)
      $this->_html .= '<li>'.$error.'</li>';
    $this->_html .= '
      </ol>
      </div>';
  }

  public function displaybeGateway()
  {
    $this->_html .= '
      <img src="../modules/'.$this->name.'/views/img/admin_logo.png" style="float:left; margin-right:15px;" />
      <b>'.$this->l('This module allows you to accept online payments.').'</b><br /><br />
      '.$this->l('You need to configure your account with your payment processor first before using this module.').'
      <div style="clear:both;">&nbsp;</div>';
  }

  public function hookdisplayHeader()
  {
    if (!$this->active)
      return;

    $this->context->controller->addCSS(__PS_BASE_URI__.'modules/'.$this->name.'/views/css/front.css');
  }

  public function displayFormSettings()
  {
    $conf = Configuration::getMultiple(array('BEGATEWAY_SHOP_ID', 'BEGATEWAY_SHOP_PASS', 'BEGATEWAY_SHOP_PAYTYPE', 'BEGATEWAY_DOMAIN_CHECKOUT', 'BEGATEWAY_TEST_MODE'));
    $shop_id = array_key_exists('shop_id', $_POST) ? $_POST['shop_id'] : (array_key_exists('BEGATEWAY_SHOP_ID', $conf) ? $conf['BEGATEWAY_SHOP_ID'] : '');
    $shop_pass = array_key_exists('shop_pass', $_POST) ? $_POST['shop_pass'] : (array_key_exists('BEGATEWAY_SHOP_PASS', $conf) ? $conf['BEGATEWAY_SHOP_PASS'] : '');
    $shop_ptype = array_key_exists('payment_type', $_POST) ? $_POST['payment_type'] : (array_key_exists('BEGATEWAY_SHOP_PAYTYPE', $conf) ? $conf['BEGATEWAY_SHOP_PAYTYPE'] : '');
    $domain_checkout = array_key_exists('domain_checkout', $_POST) ? $_POST['domain_checkout'] : (array_key_exists('BEGATEWAY_DOMAIN_CHECKOUT', $conf) ? $conf['BEGATEWAY_DOMAIN_CHECKOUT'] : '');
    $test_mode = array_key_exists('test_mode', $_POST) ? $_POST['test_mode'] : (array_key_exists('BEGATEWAY_TEST_MODE', $conf) ? $conf['BEGATEWAY_TEST_MODE'] : '');
    $achk_str = '';
    $pchk_str = '';

    if ($shop_ptype == 'authorization') {
      $achk_str = 'checked=checked';
    } else {
      $pchk_str = 'checked=checked';
    }

    if ($test_mode == '1') {
      $m1chk_str = 'checked=checked';
    } else {
      $m2chk_str = 'checked=checked';
    }

    $lang_select = 'selected="selected"';


    $this->_html .= '
      <form action="'.$_SERVER['REQUEST_URI'].'" method="post" style="clear: both;">
        <fieldset>
          <legend><img src="../img/admin/contact.gif" />'.$this->l('Settings').'</legend>
          <label>'.$this->l('Shop Id').'</label>
          <div class="margin-form"><input type="text" size="33" name="shop_id" value="'.htmlentities($shop_id, ENT_COMPAT, 'UTF-8').'" /></div>
          <label>'.$this->l('Shop secret key').'</label>
          <div class="margin-form"><input type="text" size="82" name="shop_pass" value="'.htmlentities($shop_pass, ENT_COMPAT, 'UTF-8').'" />
          </div>
          <label>'.$this->l('Payment Type').'</label>
          <div class="margin-form">
            <input type="radio" name="payment_type" value="payment" '.$pchk_str.'  /> '.$this->l('Payment').'  &nbsp;&nbsp;
            <input type="radio" name="payment_type" value="authorization" '.$achk_str.' /> ' . $this->l('Authorization') .'
          </div>
          <label>'.$this->l('Checkout page domain').'</label>
          <div class="margin-form"><input type="text" size="82" name="domain_checkout" value="'.htmlentities($domain_checkout, ENT_COMPAT, 'UTF-8').'" />
          </div>
          <label>'.$this->l('Test mode').'</label>
          <div class="margin-form">
            <input type="radio" name="test_mode" value="1" '.$m1chk_str.'  /> '.$this->l('Enabled').'  &nbsp;&nbsp;
            <input type="radio" name="test_mode" value="" '.$m2chk_str.' /> ' . $this->l('Disabled') .'
          </div>

          <br /><br /><br />

          <br /><center><input type="submit" name="submit_begateway" value="'.$this->l('Update settings').'" class="button" /></center>
        </fieldset>
      </form>
      ';
  }

  public function hookPayment($params)
  {
    if (!$this->active)
      return;

    $this->context->smarty->assign(
      [
        'begateway_path' => $this->getPathUri(),
        'contoller_link' => BeGatewayHelper::controllerLink('payment')
      ]
    );

    return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
  }

  public function hookBackOfficeHeader()
  {

    if (!isset($_GET['vieworder']) || !isset($_GET['id_order'])) return;

    //Capture start
    if (Tools::isSubmit('Submit_beGateway_Capture') && isset($_POST['id_auth']))
    {
      $transaction_details = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'begateway_transaction WHERE id_begateway_transaction = '.(int)$_POST['id_auth'].' AND type = \'authorization\' AND status = \'successful\'');

      if (isset($transaction_details['uid'])){
        $capture = new \BeGateway\Capture;
        $capture->setParentUid($transaction_details['uid']);
        $capture->money->setCurrency($transaction_details['currency']);
        $capture->money->setAmount($transaction_details['amount']);

        $capture_response = $capture->submit();

        if ($capture_response->isSuccess()){
          Db::getInstance()->getRow('UPDATE '._DB_PREFIX_.'begateway_transaction SET type = \'payment\', au_uid = \''.$transaction_details['uid'].'\' , uid = \''.$capture_response->getUid().'\' WHERE id_begateway_transaction = '.(int)$_POST['id_auth']);
        }
      }
    }
    //Capture end

    if (Tools::isSubmit('Submit_beGateway_Refund') && isset($_POST['begateway_amount_to_refund']) && isset($_POST['id_refund']))
    {

      $transaction_details = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'begateway_transaction WHERE id_begateway_transaction = '.(int)($_POST['id_refund']).' AND type = \'payment\' AND status = \'successful\'');

      if (isset($transaction_details['uid']))
      {

        $already_refunded = Db::getInstance()->getValue('SELECT SUM(amount) FROM '._DB_PREFIX_.'begateway_transaction WHERE id_order = '.(int)$_GET['id_order'].' AND type = \'refund\' AND status = \'successful\'');

        if ($_POST['begateway_amount_to_refund'] <= number_format($transaction_details['amount'] - $already_refunded, 2, '.', '')){

          $refund = new \BeGateway\Refund;
          $refund->setParentUid($transaction_details['uid']);
          $refund->money->setCurrency($transaction_details['currency']);
          $refund->money->setAmount($_POST['begateway_amount_to_refund']);
          $refund->setReason($this->l('Order Refund :').$_GET['id_order']);

          $refund_response = $refund->submit();

          if ($refund_response->isSuccess()) {
            Db::getInstance()->Execute('
              INSERT INTO '._DB_PREFIX_.'begateway_transaction (type, id_begateway_customer, id_cart, id_order,
                uid, amount, status, currency, date_add)
                VALUES (\'refund\', '.(int)$transaction_details['id_begateway_customer'].', '.(int)$transaction_details['id_cart'].', '.
                (int)$_GET['id_order'].', \''.$refund_response->getUid().'\',
                  \''.(float)$_POST['begateway_amount_to_refund'].'\', \''.'successful'.'\', \''.$transaction_details['currency'].'\',
                  NOW())');
          }
        }

        else
          $this->_errors['refund_error'] = $this->l('You cannot refund more than').' '.Tools::displayPrice($transaction_details['amount'] - $already_refunded).' '.$this->l('on this order');
      }
    }

    /* Check if the order was paid with beGateway and display the transaction details */
    if (Db::getInstance()->getValue('SELECT module FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int)$_GET['id_order']) == $this->name)
    {
      /* Get the transaction details */
      $id_cart = Db::getInstance()->getValue('SELECT id_cart FROM '._DB_PREFIX_.'orders WHERE id_order = '.(int)$_GET['id_order']);

      $transaction_details = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'begateway_transaction WHERE id_cart = '.(int)$id_cart.' AND ((type = \'payment\') or (type = \'authorization\')) AND status = \'successful\'');


      $refunded = 0;
      $output_refund = '';
      $refund_details = Db::getInstance()->ExecuteS('SELECT amount, status, date_add, uid FROM '._DB_PREFIX_.'begateway_transaction
        WHERE id_order = '.(int)$_GET['id_order'].' AND type = \'refund\' ORDER BY date_add DESC');
      foreach ($refund_details as $refund_detail)
      {
        $refunded += ($refund_detail['status'] == 'successful' ? $refund_detail['amount'] : 0);
        $output_refund .= '<tr'.($refund_detail['status'] != 'successful' ? ' style="background: #FFBBAA;"': '').'><td>'.
          Tools::safeOutput($refund_detail['date_add']).'</td><td style="text-align: right;">'.Tools::displayPrice($refund_detail['amount']).
          '</td><td>'.($refund_detail['status'] == 'successful' ? $this->l('Processed') : $this->l('Error')).'</td><td>'.Tools::safeOutput($refund_detail['uid']).'</td></tr>';
      }

      $output = '
        <script type="text/javascript">
        $(document).ready(function() {
          $(\''. (_PS_VERSION_ < 1.6 ? '' : '<div class="row"><div class="col-lg-12">') . '<fieldset><legend><img src="../img/admin/money.gif" alt="" />'.$this->l('Payment Details').'</legend>';

      if (isset($transaction_details['uid'])){
        if ($transaction_details['type'] == 'authorization'){
          $stat_str = $this->l('Status:').' <span style="font-weight: bold; color: '.($transaction_details['status'] == 'successful' ? 'green;">'.$this->l('Authorized') : '#CC0000;">'.$this->l('Unauthorized')).'</span>';
          $stat_str .= '<form action="" method="post"><input type="hidden" name="id_auth" value="'.
            Tools::safeOutput($transaction_details['id_begateway_transaction']).'" />
            <input type="submit" class="btn btn-primary" onclick="return confirm(\\\''.addslashes($this->l('Do you want to capture this transaction?')).'\\\');" name="Submit_beGateway_Capture" value="'.
            $this->l('Capture this transaction').'" /></form>';
        } else {
          $stat_str = $this->l('Status:').' <span style="font-weight: bold; color: '.($transaction_details['status'] == 'successful' ? 'green;">'.$this->l('Paid') : '#CC0000;">'.$this->l('Unpaid')).'</span><br />';
        }
        $output .= $this->l('Transaction UID:').' '.Tools::safeOutput($transaction_details['uid']).'<br /><br />'.
          $stat_str.
          $this->l('Amount:').' '.Tools::displayPrice($transaction_details['amount']).'<br />'.
          $this->l('Processed on:').' '.Tools::safeOutput($transaction_details['date_add']).'<br />';
      } else {
        $output .= '<b style="color: #CC0000;">'.$this->l('Warning:').'</b> '.$this->l('The customer paid and an error occured (check details at the bottom of this page)');
      }
      $output .= '</fieldset><br />';

      if (($transaction_details['status'] == 'successful')&&($transaction_details['type'] == 'payment')){
        $output .= '<fieldset><legend><img src="../img/admin/money.gif" alt="" />'.$this->l('Proceed to a full or partial refund').'</legend>'.
          ((empty($this->_errors['refund_error']) && isset($_POST['uid_refund'])) ? '<div class="conf confirmation">'.$this->l('Your refund was successfully processed').'</div>' : '').
          (!empty($this->_errors['refund_error']) ? '<span style="color: #CC0000; font-weight: bold;">'.$this->l('Error:').' '.Tools::safeOutput($this->_errors['refund_error']).'</span><br /><br />' : '').
          $this->l('Already refunded:').' <b>'.Tools::displayPrice($refunded).'</b><br /><br />'.($refunded ? '<table class="table" cellpadding="0" cellspacing="0" style="font-size: 12px;"><tr><th>'.$this->l('Date').'</th><th>'.$this->l('Amount refunded').'</th><th>'.$this->l('Status').'</th><th>'.$this->l('UID').'</th></tr>'.$output_refund.'</table><br />' : '').
          ($transaction_details['amount'] > $refunded ? '<form action="" method="post">'.$this->l('Refund:').' <input type="text" value="'.number_format($transaction_details['amount'] - $refunded, 2, '.', '').
          '" name="begateway_amount_to_refund" style="text-align: right; width: 100px;" /> <input type="hidden" name="id_refund" value="'.
          Tools::safeOutput($transaction_details['id_begateway_transaction']).'" /><input type="submit" class="btn btn-primary" onclick="return confirm(\\\''.addslashes($this->l('Do you want to proceed to this refund?')).'\\\');" name="Submit_beGateway_Refund" value="'.
          $this->l('Process Refund').'" /></form>' : '').'</fieldset><br />';
      }

      if (_PS_VERSION_ < 1.6 ) {
        $output .='\').insertBefore($(\'select[name=id_order_state]\').parent().parent().find(\'fieldset\').first());';
      } else {
        $output .='</div></div>\').insertBefore($(\'select[name=id_order_state]\').closest(\'.row\'));';
      }

      $output .= '});
        </script>';

      return $output;
    }
  }

  public function hookPaymentReturn($params)
  {
    if (!$this->active) return;

    return $this->display(__FILE__, 'confirmation.tpl');
  }

  public function addOrderMessage($obj_order, $message) {
    $msg = new Message();
    $message = strip_tags($message, '<br>');
    if (Validate::isCleanHtml($message)) {
      $msg->message = $message;
      $msg->id_order = $obj_order->id;
      $msg->private = 1;
      $msg->add();
    }
  }

  /**
 * Change order status
 *
 * @param $obj_order
 * @param $id_status
 * @param $errors
 */
  public function changeOrderStatus($obj_order, $id_status)
  {
    // Create new OrderHistory
    $history = new OrderHistory();
    $history->id_order = $obj_order->id;
    $history->changeIdOrderState($id_status, (int)$obj_order->id);

    $template_vars = array();
    // Save all changes
    if ($history->addWithemail(true, $template_vars))
    {
      // synchronizes quantities if needed..
      if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT'))
      {
        foreach ($obj_order->getProducts() as $product)
        {
          if (StockAvailable::dependsOnStock($product['product_id']))
            StockAvailable::synchronize($product['product_id'], (int)$product['id_shop']);
        }
      }
    }
    else {
      Logger::addLog('Error to update order status',4);
    }
  }
  public function changeOrderStatusWithMessage($obj_order, $status, $message) {
    $this->changeOrderStatus($obj_order, $status);
    $this->addOrderMessage($obj_order, $message);
  }

  protected function createOrderState()
  {
    if (!Configuration::get('BEGATEWAY_OS_NEW')) {
      $state       = new OrderState();
      $state->name = [];

      foreach (Language::getLanguages() as $language) {
        $state->name[$language['id_lang']] = 'Awaiting payment';
      }

      $state->send_email  = false;
      $state->color       = '#017FBA';
      $state->hidden      = false;
      $state->delivery    = false;
      $state->logable     = false;
      $state->invoice     = false;
      $state->module_name = $this->name;

      if ($state->add()) {
        $dir         = dirname(__FILE__);
        $source      = $dir . '/views/img/os.gif';
        $destination = $dir . '/../../../img/os/' . (int) $state->id . '.gif';
        copy($source, $destination);
        Configuration::updateValue('BEGATEWAY_OS_NEW', (int) $state->id);

        return true;
      }
    }

    return false;
  }

  public function getPathUri()
  {
      return parent::getPathUri();
  }

  public function getOrderById($id)
  {
      if (empty($id)) {
          return;
      }

      $order = new Order($id);
      if (Validate::isLoadedObject($order)) {
          return new BeGatewayOrder($order);
      }
  }

  public function getCurrentOrder()
  {
      if (empty($this->currentOrder)) {
          return;
      }

      return $this->getOrderById($this->currentOrder);
  }

  public function getSession()
  {
      return $this->session;
  }

}
