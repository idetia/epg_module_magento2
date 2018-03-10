<?php

namespace EPG\EasyPaymentGateway\Model;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as HelperData;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Sales\Model\Order\Payment;

class Paymentmethod extends AbstractMethod {
    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var RequestInterface
     */
    protected $_appRequestInterface;

    public function __construct(Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        HelperData $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        StoreManagerInterface $modelStoreManagerInterface,
        RequestInterface $appRequestInterface,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [])
    {
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_appRequestInterface = $appRequestInterface;

        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data);
    }

  const METHOD_CODE = 'easypaymentgateway';

  protected $_code  = self::METHOD_CODE;
  protected $_isGateway = true;
  protected $_canCapture = true;
  protected $_canCapturePartial = true;
  protected $_canRefund = false;
  protected $_minOrderTotal = 0;
  protected $_formBlockType = 'easypaymentgateway/form_easypaymentgateway';
  protected $_infoBlockType = 'easypaymentgateway/info_easypaymentgateway';

  protected $errors = [];

  public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
  {
      parent::capture($payment, $amount);

      throw new \Magento\Framework\Validator\Exception(__('Payment error.'));

      /*
      // See https://magecomp.com/blog/create-custom-payment-method-in-magento-2/
      // See https://github.com/checkout/checkout-magento2-plugin
      // See https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Payment/Model/Method/AbstractMethod.php
      
      $order = $payment->getOrder();
      $billing = $order->getBillingAddress();
      try{
          $charge = \Stripe\Charge::create(array(
              'amount'	=> $amount*100,
              'currency'	=> strtolower($order->getBaseCurrencyCode()),
              'card'      => array(
                  'number'			=>	$payment->getCcNumber(),
                  'exp_month'			=>	sprintf('%02d',$payment->getCcExpMonth()),
                  'exp_year'			=>	$payment->getCcExpYear(),
                  'cvc'				=>	$payment->getCcCid(),
                  'name'				=>	$billing->getName(),
                  'address_line1'		=>	$billing->getStreet(1),
                  'address_line2'		=>	$billing->getStreet(2),
                  'address_zip'		=>	$billing->getPostcode(),
                  'address_state'		=>	$billing->getRegion(),
                  'address_country'	=>	$billing->getCountry(),
              ),
              'description'	=>	sprintf('#%s, %s', $order->getIncrementId(), $order->getCustomerEmail())
          ));

          $payment->setTransactionId($charge->id)->setIsTransactionClosed(0);

          return $this;

      } catch (\Exception $e) {
          $this->debugData(['exception' => $e->getMessage()]);
          throw new \Magento\Framework\Validator\Exception(__('Payment error.'));
      }
      */

   }

  public function validate()
  {
      parent::validate();

      // Check if payment method is valid
      if (!$this->isAvailable()) {
          throw new \Magento\Framework\Validator\Exception(__('This payment method is not available.'));
      }

      $paymentInfo = $this->getInfoInstance();
      if ($paymentInfo instanceof Payment) {
          $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
      } else {
          $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
      }

      // Check form fields
      $data = $paymentInfo->getAdditionalInformation();
      if (empty($data['account']) || $data['account'] == "0") {
          $cardNumber = isset($data['card_number'])?(string)$data['card_number']:null;
          $expDateMonth = isset($data['card_expiry_month'])?(int)$data['card_expiry_month']:null;
          $expDateYear = isset($data['card_expiry_year'])?(int)$data['card_expiry_year']:null;
          $chName = isset($data['card_holder_name'])?(string)$data['card_holder_name']:null;

          if (empty($cardNumber) || empty(self::checkCard($cardNumber, true))) {
              $this->errors[] = __('The card number is not valid.');
          }

          if (empty($expDateMonth) || !($expDateMonth>0 && $expDateMonth<13) || empty($expDateYear) || !($expDateYear>-1 && $expDateYear<100)) {
              $this->errors[] = __('The card expiration date is not valid.');
          }

          if (empty($chName)) {
              $this->errors[] = __('The card holder name is not valid.');
          }
      }

      $cvnNumber = isset($data['card_cvn'])?$data['card_cvn']:null;;
      if (empty($cvnNumber) || strlen($cvnNumber) !== 3 || !is_numeric($cvnNumber)) {
          $this->errors[] = __('The card cvn number is not valid.');
      }

      if (count($this->errors) > 0) {
        throw new \Magento\Framework\Validator\Exception(__(implode("\n", $this->errors)));
      }

    return $this;
  }

  private static final function checkCard($number, $extraCheck = false){
      $cards = array(
          "visa" => "(4\d{12}(?:\d{3})?)",
          "amex" => "(3[47]\d{13})",
          "maestro" => "((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)",
          "mastercard" => "(5[1-5]\d{14})"
      );

      $names = array("Visa", "American Express", "Maestro", "Mastercard");
      $matches = array();
      $pattern = "#^(?:".implode("|", $cards).")$#";
      $result = preg_match($pattern, str_replace(" ", "", $number), $matches);

      if($extraCheck && $result > 0){
          $result = (self::luhnValidation($number))?1:0;
      }

      return ($result>0)?$names[sizeof($matches)-2]:false;
  }

  private static final function luhnValidation($number){
      settype($number, 'string');
      $number = preg_replace("/[^0-9]/", "", $number);
      $numberChecksum= '';

      $reversedNumberArray = str_split(strrev($number));
      foreach ($reversedNumberArray as $i => $d) {
          $numberChecksum.= (($i % 2) !== 0) ? (string)((int)$d * 2) : $d;
      }

      $sum = array_sum(str_split($numberChecksum));
      return ($sum % 10) === 0;
  }

  public function getOrderPlaceRedirectUrl() {
    $isSSL = ($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure());
    return Mage::getUrl('easypaymentgateway/payment/charge', ['_secure' => $isSSL]);
  }

}
