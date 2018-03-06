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

  protected $_code  = 'easypaymentgateway';
  protected $_isGateway = false;
  protected $_canReviewPayment = true;
  protected $_canCapture = true;
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

    return $this;
  }

  public function validate()
  {
    parent::validate();

    // Check if payment method is valid
    if (!$this->isAvailable()) {
        throw new \Exception(__('This payment method is not available.'));
    }

    return $this;
  }

  public function getOrderPlaceRedirectUrl() {
    $isSSL = ($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure());
    return Mage::getUrl('easypaymentgateway/payment/charge', ['_secure' => $isSSL]);
  }

}
