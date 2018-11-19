<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once _PS_MODULE_DIR_ . 'begateway/lib/BeGatewayAutoload.php';

class BeGateway extends PaymentModule
{
  const TEST_SHOP      = 361;
  const TEST_KEY       = 'b8647b68898b084b836474ed8d61ffe117c9a01168d867f24953b776ddcb134d';
  const TEST_DOMAIN    = 'checkout.begateway.com';
  const TEST_DOMAIN_GW = 'demo-gateway.begateway.com';
  const MIN_AMOUNT     = 0.01;

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
    \BeGateway\Settings::$gatewayBase = 'https://' . trim(Configuration::get('BEGATEWAY_DOMAIN_GATEWAY'));
    \BeGateway\Settings::$shopId  = trim(Configuration::get('BEGATEWAY_SHOP_ID'));
    \BeGateway\Settings::$shopKey = trim(Configuration::get('BEGATEWAY_SHOP_PASS'));

    $this->page = basename(__FILE__, '.php');
    $this->displayName = $this->l('BeGateway');
    $this->description = $this->l('Accept online payments');
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
      OR !Configuration::updateValue('BEGATEWAY_DOMAIN_GATEWAY', $this::TEST_DOMAIN_GW)
      OR !Configuration::updateValue('BEGATEWAY_TEST_MODE', 1)
      OR !Configuration::updateValue('BEGATEWAY_VISA', 1)
      OR !Configuration::updateValue('BEGATEWAY_MASTERCARD', 1)
      OR !Configuration::updateValue('BEGATEWAY_BELKART', 0)
      OR !Configuration::updateValue('BEGATEWAY_HALVA', 0)
      OR !Configuration::updateValue('BEGATEWAY_ERIP', 0)
      OR !$this->registerHook('payment')
      OR !$this->registerHook('adminOrder')
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
        `id_transaction` varchar(60) NOT NULL,
        `id_order` int(10) unsigned NOT NULL,
        `type` enum(\'new\',\'payment\', \'authorization\') NOT NULL DEFAULT \'new\',
        `amount` decimal(10,6) NOT NULL DEFAULT \'0.000000\',
        `status` enum(\'incomplete\',\'failed\',\'successful\', \'pending\') NOT NULL,
        `refunded_amount` decimal(10,6) NOT NULL DEFAULT \'0.000000\',
        `captured_amount` decimal(10,6) NOT NULL DEFAULT \'0.000000\',
        `voided_amount` decimal(10,6) NOT NULL DEFAULT \'0.000000\',
        `date_add` datetime NOT NULL,
  			`date_upd` datetime NOT NULL,
      PRIMARY KEY (`id_transaction`),
      KEY `idx_order` (`id_order`)
    )
    ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8');
  }

  public function uninstall()
  {
    if (!Configuration::deleteByName('BEGATEWAY_SHOP_ID')
      OR !Configuration::deleteByName('BEGATEWAY_SHOP_PASS')
      OR !Configuration::deleteByName('BEGATEWAY_SHOP_PAYTYPE')
      OR !Configuration::deleteByName('BEGATEWAY_DOMAIN_CHECKOUT')
      OR !Configuration::deleteByName('BEGATEWAY_DOMAIN_GATEWAY')
      OR !Configuration::deleteByName('BEGATEWAY_TEST_MODE')
      OR !Configuration::deleteByName('BEGATEWAY_VISA')
      OR !Configuration::deleteByName('BEGATEWAY_ERIP')
      OR !Configuration::deleteByName('BEGATEWAY_HALVA')
      OR !Configuration::deleteByName('BEGATEWAY_BELKART')
      OR !Configuration::deleteByName('BEGATEWAY_MASTERCARD')
      OR !Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'begateway_transaction`')
      OR !$this->unregisterHook('payment')
      OR !$this->unregisterHook('adminOrder')
      OR !$this->unregisterHook('displayHeader')
      OR !parent::uninstall())
      return false;
    return true;
  }

  public function getContent()
  {
      if (Tools::isSubmit('submit' . $this->name)) {
          $this->updateConfigurationPost();
      }

      $data       = [
          'base_url'    => _PS_BASE_URL_ . __PS_BASE_URI__,
          'module_name' => $this->name,
          'form'        => $this->displayForm(),
      ];

      $this->context->smarty->assign($data);
      $output = $this->display(__FILE__, 'views/templates/admin/configuration.tpl');

      return $output;
  }

  public function hookdisplayHeader()
  {
    if (!$this->active)
      return;

    $this->context->controller->addCSS($this->getPathUri() . 'views/css/begateway.css');

    if (_PS_VERSION_ < 1.6)
      $this->context->controller->addCSS($this->getPathUri() . 'views/css/begateway_1_5.css');
  }

  public function hookPayment($params)
  {
    if (!$this->active)
      return;

    $this->context->smarty->assign(
      [
        'begateway_path' => $this->getPathUri(),
        'contoller_link' => BeGatewayHelper::controllerLink('payment'),
        'images'         => $this->getPaymentMethodNames()
      ]
    );

    return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
  }

  public function hookAdminOrder($params)
  {
      if (!$this->active) {
          return;
      }

      $order  = $this->getOrderById($params['id_order']);
      $errors = [];
      $html   = null;

      if (Tools::isSubmit('submitBeGatewayOnlineRefund')) {
          $amount = BeGatewayHelper::round((float) Tools::getValue('BEGATEWAY_REFUND_AMOUNT', 0));
          if ($order->canRefundAmount($amount)) {
              $this->refund($order, $amount);
          } else {
              $errors[] = $this->l('This amount cannot be refunded');
          }
      }

      if (Tools::isSubmit('submitBeGatewayOnlineCapture')) {
          $amount = BeGatewayHelper::round((float) Tools::getValue('BEGATEWAY_CAPTURE_AMOUNT', 0));
          if ($order->canCaptureAmount($amount)) {
              $this->capture($order, $amount);
          } else {
              $errors[] = $this->l('This amount cannot be captured');
          }
      }

      if (Tools::isSubmit('submitBeGatewayOnlineVoid')) {
          $amount = BeGatewayHelper::round((float) $order->getMaxVoidAmount());
          if ($order->canVoidAmount($amount)) {
              $this->void($order, $amount);
          } else {
              $errors[] = $this->l('This amount cannot be voided');
          }
      }

      if ($order->canRefundAmount(self::MIN_AMOUNT)) {
          $this->context->smarty->assign(
              [
                  'base_url'                 => _PS_BASE_URL_ . __PS_BASE_URI__,
                  'params'                   => $params,
                  'errors'                   => $errors,
                  'max_online_refund_amount' => $order->getMaxRefundAmount(),
                  'module_name'              => $this->name,
              ]
          );

          $html = $this->display(__FILE__, 'views/templates/admin/order/refund.tpl');

      } else {
        if ($order->canCaptureAmount(self::MIN_AMOUNT)) {
          $this->context->smarty->assign(
              [
                  'base_url'                 => _PS_BASE_URL_ . __PS_BASE_URI__,
                  'params'                   => $params,
                  'errors'                   => $errors,
                  'max_online_capture_amount'=> $order->getMaxCaptureAmount(),
                  'module_name'              => $this->name,
              ]
          );

          $html = $this->display(__FILE__, 'views/templates/admin/order/capture.tpl');
        }
        if ($order->canVoidAmount(self::MIN_AMOUNT)) {
          $this->context->smarty->assign(
              [
                  'base_url'                 => _PS_BASE_URL_ . __PS_BASE_URI__,
                  'params'                   => $params,
                  'errors'                   => $errors,
                  'module_name'              => $this->name,
              ]
          );

          $html .= $this->display(__FILE__, 'views/templates/admin/order/void.tpl');
        }
      }

      return $html;
  }

  protected function refund($order, $amount)
  {
      $transaction = $order->getTransaction();
      $refund = new \BeGateway\RefundOperation;
      $refund->setParentUid($transaction->getTransactionId());
      $refund->money->setCurrency($order->getCurrency());
      $refund->money->setAmount($amount);
      $refund->setReason($this->l('Manual order refund :') . $order->getId());

      $response = $refund->submit();

      if ($response->isSuccess()) {

        $transaction->addRefundedAmount($amount);
        $transaction->save();

        if ($transaction->isRefunded()) {
          $order->cancel();
        }

        $order->addMessage($this->l('Sent online refund request for amount: ') . $amount . '. UID: ' . $response->getUid());
      } else {
        $order->addMessage($this->l('Online refund request failed for amount: ') . $amount . '. UID: ' . $response->getUid());
      }

      Tools::redirect($_SERVER['HTTP_REFERER']);
  }

  protected function capture($order, $amount) {
      $transaction = $order->getTransaction();
      $capture = new \BeGateway\CaptureOperation;
      $capture->setParentUid($transaction->getTransactionId());
      $capture->money->setCurrency($order->getCurrency());
      $capture->money->setAmount($amount);

      $response = $capture->submit();

      if ($response->isSuccess()) {

        $transaction->setTransactionType('payment');
        $transaction->setTransactionId($response->getUid());
        $transaction->addCapturedAmount($amount);
        $transaction->save();

        $order->addMessage($this->l('Sent online capture request for amount: ') . $amount . '. UID: ' . $response->getUid());
      } else {
        $order->addMessage($this->l('Online capture request failed for amount: ') . $amount . '. UID: ' . $response->getUid());
      }

      Tools::redirect($_SERVER['HTTP_REFERER']);
  }

  protected function void($order, $amount) {
      $transaction = $order->getTransaction();
      $void = new \BeGateway\VoidOperation;
      $void->setParentUid($transaction->getTransactionId());
      $void->money->setCurrency($order->getCurrency());
      $void->money->setAmount($amount);

      $response = $void->submit();

      if ($response->isSuccess()) {

        $order->cancel();

        $transaction->addVoidedAmount($amount);
        $transaction->save();

        $order->addMessage($this->l('Sent online void request for amount: ') . $amount . '. UID: ' . $response->getUid());
      } else {
        $order->addMessage($this->l('Online void request failed for amount: ') . $amount . '. UID: ' . $response->getUid());
      }

      Tools::redirect($_SERVER['HTTP_REFERER']);
  }

  protected function updateConfigurationPost()
  {
      Configuration::updateValue('BEGATEWAY_SHOP_ID', trim(Tools::getValue('BEGATEWAY_SHOP_ID')));
      Configuration::updateValue('BEGATEWAY_SHOP_PASS', trim(Tools::getValue('BEGATEWAY_SHOP_PASS')));
      Configuration::updateValue('BEGATEWAY_DOMAIN_CHECKOUT', trim(Tools::getValue('BEGATEWAY_DOMAIN_CHECKOUT')));
      Configuration::updateValue('BEGATEWAY_DOMAIN_GATEWAY', trim(Tools::getValue('BEGATEWAY_DOMAIN_GATEWAY')));
      Configuration::updateValue('BEGATEWAY_SHOP_PAYTYPE', trim(Tools::getValue('BEGATEWAY_SHOP_PAYTYPE')));
      Configuration::updateValue('BEGATEWAY_TEST_MODE', (trim(Tools::getValue('BEGATEWAY_TEST_MODE'))));
      Configuration::updateValue('BEGATEWAY_VISA', (trim(Tools::getValue('BEGATEWAY_VISA'))));
      Configuration::updateValue('BEGATEWAY_MASTERCARD', (trim(Tools::getValue('BEGATEWAY_MASTERCARD'))));
      Configuration::updateValue('BEGATEWAY_BELKART', (trim(Tools::getValue('BEGATEWAY_BELKART'))));
      Configuration::updateValue('BEGATEWAY_HALVA', (trim(Tools::getValue('BEGATEWAY_HALVA'))));
      Configuration::updateValue('BEGATEWAY_ERIP', (trim(Tools::getValue('BEGATEWAY_ERIP'))));
  }

  protected function displayForm()
  {
      $fieldsForm = [];

      $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

      $fieldsForm[0]['form'] = [
          'legend' => [
              'title' => $this->l('Configuration'),
              'icon'  => 'icon-cogs',
          ],
          'input' => [
              [
                  'type'     => 'text',
                  'label'    => $this->l('Shop Id'),
                  'name'     => 'BEGATEWAY_SHOP_ID',
                  'size'     => 40,
                  'required' => true,
              ],
              [
                  'type'     => 'text',
                  'label'    => $this->l('Shop secret key'),
                  'name'     => 'BEGATEWAY_SHOP_PASS',
                  'size'     => 40,
                  'required' => true,
              ],
              [
                  'type'     => 'text',
                  'label'    => $this->l('Checkout page domain'),
                  'name'     => 'BEGATEWAY_DOMAIN_CHECKOUT',
                  'size'     => 40,
                  'required' => true,
              ],
              [
                  'type'     => 'text',
                  'label'    => $this->l('Gateway page domain'),
                  'name'     => 'BEGATEWAY_DOMAIN_GATEWAY',
                  'size'     => 40,
                  'required' => true,
              ],
              [
                  'type'   => 'select',
                  'label'  => $this->l('Transaction type'),
                  'name'   => 'BEGATEWAY_SHOP_PAYTYPE',
                  'options' => array(
                    'query' => array(
                      array('id' => 'payment', 'name' => $this->l('Payment')),
                      array('id' => 'authorization', 'name' => $this->l('Authorization'))
                    ),
                    'name' => 'name',
                    'id' => 'id'
                  ),
                  'required' => true,
              ],
              [
                  'type'   => 'switch',
                  'label'  => $this->l('Test mode'),
                  'name'   => 'BEGATEWAY_TEST_MODE',
                  'values' => [
                      [
                          'id'    => 'active_on',
                          'value' => 1,
                          'label' => $this->l('Yes'),
                      ],
                      [
                          'id'    => 'active_off',
                          'value' => 0,
                          'label' => $this->l('No'),
                      ],
                  ],
                  'required' => true,
              ],
              [
                  'type'   => 'switch',
                  'label'  => $this->l('Enable VISA'),
                  'name'   => 'BEGATEWAY_VISA',
                  'values' => [
                      [
                          'id'    => 'active_on',
                          'value' => 1,
                          'label' => $this->l('Yes'),
                      ],
                      [
                          'id'    => 'active_off',
                          'value' => 0,
                          'label' => $this->l('No'),
                      ],
                  ],
                  'required' => true,
              ],
              [
                  'type'   => 'switch',
                  'label'  => $this->l('Enable Mastercard'),
                  'name'   => 'BEGATEWAY_MASTERCARD',
                  'values' => [
                      [
                          'id'    => 'active_on',
                          'value' => 1,
                          'label' => $this->l('Yes'),
                      ],
                      [
                          'id'    => 'active_off',
                          'value' => 0,
                          'label' => $this->l('No'),
                      ],
                  ],
                  'required' => true,
              ],
              [
                  'type'   => 'switch',
                  'label'  => $this->l('Enable BELKART'),
                  'name'   => 'BEGATEWAY_BELKART',
                  'values' => [
                      [
                          'id'    => 'active_on',
                          'value' => 1,
                          'label' => $this->l('Yes'),
                      ],
                      [
                          'id'    => 'active_off',
                          'value' => 0,
                          'label' => $this->l('No'),
                      ],
                  ],
                  'required' => true,
              ],
              [
                  'type'   => 'switch',
                  'label'  => $this->l('Enable Halva'),
                  'name'   => 'BEGATEWAY_HALVA',
                  'values' => [
                      [
                          'id'    => 'active_on',
                          'value' => 1,
                          'label' => $this->l('Yes'),
                      ],
                      [
                          'id'    => 'active_off',
                          'value' => 0,
                          'label' => $this->l('No'),
                      ],
                  ],
                  'required' => true,
              ],
              [
                  'type'   => 'switch',
                  'label'  => $this->l('Enable ERIP'),
                  'name'   => 'BEGATEWAY_ERIP',
                  'values' => [
                      [
                          'id'    => 'active_on',
                          'value' => 1,
                          'label' => $this->l('Yes'),
                      ],
                      [
                          'id'    => 'active_off',
                          'value' => 0,
                          'label' => $this->l('No'),
                      ],
                  ],
                  'required' => true,
              ],
          ],
          'submit' => [
              'title' => $this->l('Save'),
              'class' => 'btn btn-default pull-right',
          ],
      ];

      $index = AdminController::$currentIndex;

      $helper = new HelperForm();

      $helper->module          = $this;
      $helper->name_controller = $this->name;
      $helper->token           = Tools::getAdminTokenLite('AdminModules');
      $helper->currentIndex    = $index . '&configure=' . $this->name;

      $helper->default_form_language    = $defaultLang;
      $helper->allow_employee_form_lang = $defaultLang;

      $helper->title          = $this->displayName;
      $helper->show_toolbar   = true;
      $helper->toolbar_scroll = true;
      $helper->submit_action  = 'submit' . $this->name;

      $helper->toolbar_btn = [
          'save' => [
                  'desc' => $this->l('Save'),
                  'href' => $index . '&configure=' . $this->name . '&save' . $this->name .
                      '&token=' . Tools::getAdminTokenLite('AdminModules'),
              ],
          'back' => [
              'href' => $index . '&token=' . Tools::getAdminTokenLite('AdminModules'),
              'desc' => $this->l('Back to list'),
          ],
      ];

      $helper->fields_value['BEGATEWAY_SHOP_ID']         = Configuration::get('BEGATEWAY_SHOP_ID');
      $helper->fields_value['BEGATEWAY_SHOP_PASS']       = Configuration::get('BEGATEWAY_SHOP_PASS');
      $helper->fields_value['BEGATEWAY_DOMAIN_CHECKOUT'] = Configuration::get('BEGATEWAY_DOMAIN_CHECKOUT');
      $helper->fields_value['BEGATEWAY_DOMAIN_GATEWAY']  = Configuration::get('BEGATEWAY_DOMAIN_GATEWAY');
      $helper->fields_value['BEGATEWAY_SHOP_PAYTYPE']    = Configuration::get('BEGATEWAY_SHOP_PAYTYPE');
      $helper->fields_value['BEGATEWAY_TEST_MODE']       = Configuration::get('BEGATEWAY_TEST_MODE');
      $helper->fields_value['BEGATEWAY_VISA']            = Configuration::get('BEGATEWAY_VISA');
      $helper->fields_value['BEGATEWAY_MASTERCARD']      = Configuration::get('BEGATEWAY_MASTERCARD');
      $helper->fields_value['BEGATEWAY_BELKART']         = Configuration::get('BEGATEWAY_BELKART');
      $helper->fields_value['BEGATEWAY_HALVA']           = Configuration::get('BEGATEWAY_HALVA');
      $helper->fields_value['BEGATEWAY_ERIP']            = Configuration::get('BEGATEWAY_ERIP');

      return $helper->generateForm($fieldsForm);
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

  public function getPaymentMethodNames() {
    $methods = [];

    foreach ($this->getSupportedPaymentMethods() as $method) {
      if (Configuration::get("BEGATEWAY_{$method}")) {
        $methods[]=$method;
      }
    }

    return $methods;
  }

  public function getSupportedPaymentMethods() {
    $methods = ['VISA', 'MASTERCARD', 'BELKART', 'HALVA', 'ERIP'];
    return $methods;
  }
}
