<?php
/**
 * @author    eComCharge
 * @copyright Copyright (c) 2016 ecomcharge.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BeGatewayGateway
{
    protected $session;

    public function __construct($session)
    {
        $this->session = $session;
    }

    public function getGatewayRedirectUrlForOrder($order)
    {
        if (is_null($order)) {
            throw new Exception('Order not found');
        }

        $token = $this->getCreateQuoteTokenForOrder($order);

        return $token ? $token : null;
    }

    protected function getCreateQuoteTokenForOrder($order)
    {
        $items   = [];
        $link    = new Link();

        $order_token = $this->session->storeOrderSecureData($order);
        $url_params  = [
            'order_id'    => $order->getId(),
            'order_token' => $order_token
        ];

        $transaction = new \BeGateway\GetPaymentToken;

        $email = ($order->getCustomerEmail()) ? $order->getCustomerEmail() : null;
        $callbackurl = BeGatewayHelper::controllerLink('ipn', []);
        $address = new Address($order->id_address_invoice);
        $country = Country::getIsoById($address->id_country);
        $customer = new Customer($order->getCustomerId());
        $lang_iso_code = strtolower(Language::getIsoById(Context::getContext()->cookie->id_lang));

        if (Configuration::get('BEGATEWAY_SHOP_PAYTYPE') == 'authorization') {
          $transaction->setAuthorizationTransactionType();
        } else {
          $transaction->setPaymentTransactionType();
        }

        $transaction->setTestMode(intval(Configuration::get('BEGATEWAY_TEST_MODE')) == 1);
        error_log(Configuration::get('BEGATEWAY_TEST_MODE'));

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

        $transaction->money->setCurrency($order->getCurrency());
        $transaction->money->setAmount(BeGatewayHelper::round($order->getAmount()));
        $transaction->setDescription($order->getDescription());
        $transaction->setTrackingId(implode('|', [$order->getId(), $order_token, $customer->secure_key]));
        $transaction->setLanguage($lang_iso_code);
        $transaction->setNotificationUrl($callbackurl);
        $transaction->setSuccessUrl(BeGatewayHelper::controllerLink('success', $url_params));
        $transaction->setDeclineUrl(BeGatewayHelper::controllerLink('failure', $url_params));
        $transaction->setFailUrl(BeGatewayHelper::controllerLink('failure', $url_params));

        $transaction->customer->setFirstName($address->firstname);
        $transaction->customer->setLastName($address->lastname);
        $transaction->customer->setCountry($country);
        $transaction->customer->setAddress($address->address1.' '.$address->address2);
        $transaction->customer->setCity($address->city);
        $transaction->customer->setZip($address->postcode);
        $transaction->customer->setEmail($email);
        $transaction->customer->setPhone($phone);
        $transaction->customer->setState($state_val);

        $paymentUrl = null;

        if (Configuration::get('BEGATEWAY_VISA') ||
            Configuration::get('BEGATEWAY_MASTERCARD') ||
            Configuration::get('BEGATEWAY_BELKART')) {

          $transaction->addPaymentMethod(new \BeGateway\PaymentMethod\CreditCard);
        }

        if (Configuration::get('BEGATEWAY_HALVA')) {
          $transaction->addPaymentMethod(new \BeGateway\PaymentMethod\CreditCardHalva);
        }

        if (Configuration::get('BEGATEWAY_ERIP')) {
          $transaction->addPaymentMethod(new \BeGateway\PaymentMethod\Erip(
            array(
                'order_id' => $order->getId(),
                'account_number' => strval($order->getId())
            )
          ));
        }

        try {
          $response = $transaction->submit();
          if ($response->isSuccess()) {
            $paymentUrl = $response->getRedirectUrl();
          }
        } catch (Exception $e) {
        }

        return $paymentUrl;
    }

    public function processSuccessCallback($order, $data)
    {
        $this->validateCallbackData($order, $data);
        $this->session->clearOrderSecureData();
    }

    public function processFailureCallback($order, $data)
    {
        $this->validateCallbackData($order, $data);
        $order->cancel();
        $this->session->clearOrderSecureData();
    }

    protected function validateCallbackData($order, $data)
    {
        if (is_null($order)) {
            throw new Exception('Order not found');
        }
        $this->session->validateOrderSecureData($data);
    }
}
