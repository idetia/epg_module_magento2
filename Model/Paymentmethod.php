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

  public function assignData(\Magento\Framework\DataObject $data)
  {
    $info = $this->getInfoInstance();

    if ($data->getAccount())
    {
      $info->setAccount($data->getAccount());
    }

    if ($data->getCardNumber())
    {
      $info->setCardNumber($data->getCardNumber());
    }

    if ($data->getCardHolderName())
    {
      $info->setCardHolderName($data->getCardHolderName());
    }

    if ($data->getCardCvn())
    {
      $info->setCardCvn($data->getCardCvn());
    }

    if ($data->getCardExpiryMonth())
    {
      $info->setCardExpiryMonth($data->getCardExpiryMonth());
    }

    if ($data->getCardExpiryYear())
    {
      $info->setCardExpiryYear($data->getCardExpiryYear());
    }

    parent::assignData($data);

    return $this;
  }

  public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
  {
      parent::capture();

      $order = $payment->getOrder();
      $billing = $order->getBillingAddress();
      throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
   }

  public function validate()
  {
      $paymentInfo = $this->getInfoInstance();
      if ($paymentInfo instanceof Payment) {
          $billingCountry = $paymentInfo->getOrder()->getBillingAddress()->getCountryId();
      } else {
          $billingCountry = $paymentInfo->getQuote()->getBillingAddress()->getCountryId();
      }

      // Check form fields
      if (empty($data['account']) || $data['account'] == "0") {
          $cardNumber = $data['card_number'];
          $expDateMonth = $data['card_expiry_month'];
          $expDateYear = $data['card_expiry_year'];
          $chName = $data['card_holder_name'];

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

      $cvnNumber = $data['card_cvn'];
      if (empty($cvnNumber) || strlen($cvnNumber) !== 3) {
          $this->errors[] = __('The card cvn number is not valid.');
      }

      if (count($this->errors) > 0) {
        throw new \Exception(implode("\n", $this->errors));
      }

    parent::validate();

    // Check if payment method is valid
    if (!$this->isAvailable()) {
        throw new \Magento\Framework\Validator\Exception(__('This payment method is not available.'));
    }

    return $this;
  }

  public function getOrderPlaceRedirectUrl() {
    $isSSL = ($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure());
    return Mage::getUrl('easypaymentgateway/payment/charge', ['_secure' => $isSSL]);
  }

}
