<?php

namespace EPG\EasyPaymentGateway\Model\Type;

use EPG\EasyPaymentGateway\Helper\Data as EasyPaymentGatewayHelperData;
use EPG\EasyPaymentGateway\Model\ApiFactory;
use EPG\EasyPaymentGateway\Model\CustomerFactory as ModelCustomerFactory;
use EPG\EasyPaymentGateway\Model\OrderFactory as ModelOrderFactory;
use Magento\Checkout\Helper\Data as HelperData;
use Magento\Checkout\Model\Session;
use Magento\Checkout\Model\Type\Onepage as TypeOnepage;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\FormFactory;
use Magento\Customer\Model\Metadata\FormFactory as MetadataFormFactory;
use Magento\Customer\Model\Session as ModelSession;
use Magento\Customer\Model\Url;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject\Copy;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use EPG\EasyPaymentGateway\Model\Form as EPGForm;

class Onepage extends TypeOnepage
{
    /**
     * @var EPGApi
     */
    protected $_epgApi;

    /**
     * @var ModelCustomerFactory
     */
    protected $_modelCustomerFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var EasyPaymentGatewayHelperData
     */
    protected $_helperData;

    /**
     * @var ModelOrderFactory
     */
    protected $_modelOrderFactory;

    public function __construct(ManagerInterface $eventManager,
        HelperData $helper,
        Url $customerUrl,
        LoggerInterface $logger,
        Session $checkoutSession,
        ModelSession $customerSession,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        AddressFactory $customrAddrFactory,
        FormFactory $customerFormFactory,
        CustomerFactory $customerFactory,
        OrderFactory $orderFactory,
        Copy $objectCopyService,
        MessageManagerInterface $messageManager,
        MetadataFormFactory $formFactory,
        CustomerInterfaceFactory $customerDataFactory,
        Random $mathRandom,
        EncryptorInterface $encryptor,
        AddressRepositoryInterface $addressRepository,
        AccountManagementInterface $accountManagement,
        OrderSender $orderSender,
        CustomerRepositoryInterface $customerRepository,
        CartRepositoryInterface $quoteRepository,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        CartManagementInterface $quoteManagement,
        DataObjectHelper $dataObjectHelper,
        TotalsCollector $totalsCollector,
        ApiFactory $modelApiFactory,
        ModelCustomerFactory $modelCustomerFactory,
        ScopeConfigInterface $configScopeConfigInterface,
        EasyPaymentGatewayHelperData $helperData,
        ModelOrderFactory $modelOrderFactory)
    {
        $this->_modelCustomerFactory = $modelCustomerFactory;
        $this->_scopeConfig = $configScopeConfigInterface;
        $this->_helperData = $helperData;
        $this->_modelOrderFactory = $modelOrderFactory;
        $this->_epgApi = $modelApiFactory->create();
        parent::__construct($eventManager, $helper, $customerUrl, $logger, $checkoutSession, $customerSession, $storeManager, $request, $customrAddrFactory, $customerFormFactory, $customerFactory, $orderFactory, $objectCopyService, $messageManager, $formFactory, $customerDataFactory, $mathRandom, $encryptor, $addressRepository, $accountManagement, $orderSender, $customerRepository, $quoteRepository, $extensibleDataObjectConverter, $quoteManagement, $dataObjectHelper, $totalsCollector);
    }

    /**
     * Add event and call parent method
     *
     * @return null
     */
    public function saveOrder()
    {
        $this->_eventManagerInterface->dispatch('checkout_type_onepage_save_order_before',
            ['quote' => $this->getQuote(), 'checkout' => $this->getCheckout()]);

        // If payment is easypaymentgateway
        if ($this->getQuote()->getPayment()->getMethod() == 'easypaymentgateway') {

            $prepayToken = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgPrepaymentToken();
            if (empty($prepayToken)) {
                throw new \Exception(__("There was an error trying to process this payment.\nPlease, try to introduce your payment data again."));
                return $this;
            }

            $redirectUrl = $this->getQuote()->getPayment()->getOrderPlaceRedirectUrl();
            $this->_checkoutSession->setRedirectUrl($redirectUrl);
            return $this;
        }

        parent::saveOrder();
    }

  	public function saveMageOrder() {
  		$this->getCheckout()->unsRedirectUrl();
  		return parent::saveOrder();
  	}

    public function savePayment($data) {
        $paymentResult =  parent::savePayment($data);

	    // If payment is easypaymentgateway
        if ($this->getQuote()->getPayment()->getMethod() == 'easypaymentgateway') {
            $this->saveEpgPayment($data);
        }

        return $paymentResult;
    }

    private function checkForm($data)
    {
        if (!isset($data['epg_payment_method']) || empty($data['epg_payment_method'])) {
            throw new \Magento\Framework\Validator\Exception(__('Sorry, there are not any payment method selected.'));
        }

        // Call cashier
        $cashierData = $this->_helperData->apiCashier();
        if (empty($cashierData)) {
            throw new \Magento\Framework\Validator\Exception(__('Sorry, there is a problem with the validations.'));
        }

        // Method selected info (name + operation)
        $methodInfo = explode('|', $data['epg_payment_method']);
        if (count($methodInfo) != 2) {
            throw new \Magento\Framework\Validator\Exception(__('Sorry, there is a problem with the validations.'));
        }

        $paymentMethod = null;
        foreach ($cashierData['paymentMethods'] as $method) {
          if ($methodInfo[0] == $method['name']) {
            $paymentMethod = $method;
            break;
          }
        }

        $account = null;
        foreach ($cashierData['accounts'] as $itemAccount) {
          if (strtolower($methodInfo[0]) != strtolower($itemAccount['paymentMethod'])) {
              continue;
          }

          if ($data['payment_account'] == $itemAccount['accountId']) {
              $account = $itemAccount;
              break;
          }
        }

        // Check form fields
        $form = new EPGForm($paymentMethod);
        $formValidation = $form->validate($data, $account);

        if (!empty($formValidation['errors'])) {
            foreach ($formValidation['errors'] as $error) {
                $this->errors[] = $error;
            }
        }

        if (count($this->errors) > 0) {
            return false;
        }

        return true;
    }

    private function saveEpgPayment($data) {

      if (!$this->checkForm($data)) {
        throw new \Magento\Framework\Validator\Exception(__(implode("<br/>", $this->errors)));
      }

      // Call API
      $apiData = null;

      try {
          $apiData = $this->getApiData();
      } catch(\Exception $e) {
          throw new \Exception($e->getMessage());
      }

      if (!isset($apiData['prepayToken'])) {
          throw new \Exception("Prepay token fails: Prepay token process returned null.");
      }

      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPrepaymentToken($apiData['prepayToken']);
      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgCustomerId($apiData['epgCustomerId']);
      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPaymentInfo($apiData['account']);
      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPaymentMethodInfo($apiData['paymentMethodInfo']);

      return $this;
    }

    private function getApiData($data) {
      $prepayToken = null;
      $paymentInfo = $this->getInfoInstance();
      $data = $paymentInfo->getAdditionalInformation();

      // Payment method selected
      $pMethod = (isset($data['epg_payment_method'])?(string)$data['epg_payment_method']:null);
      if (empty($pMethod)) {
          throw new \Exception(__('Sorry, there are not any payment method selected.'));
      }

      // Call cashier
      $cashier = $this->_helperData->apiCashier();

      // Method selected info (name + operation)
      $methodInfo = explode('|', $pMethod);
      if (count($methodInfo) != 2) {
          throw new \Exception(__('Sorry, payment method is not right.'));
      }

      $paymentMethod = null;
      foreach ($cashier['paymentMethods'] as $method) {
        if ($methodInfo[0] == $method['name']) {
          $paymentMethod = $method;
          break;
        }
      }

      // Account
      $account = null;
      foreach ($cashier['accounts'] as $itemAccount) {
        if (strtolower($methodInfo[0]) != strtolower($itemAccount['paymentMethod'])) {
            continue;
        }

        if ($data['payment_account'] == $itemAccount['accountId']) {
            $account = $itemAccount;
            break;
        }
      }

      $form = new EPGForm($paymentMethod);
      $customerData = ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->getCustomer();
      $epgCustomer = $this->_modelCustomerFactory->create()->getByCustomerId($customerData->getId());
      if (!empty($epgCustomer)) {
          $epgCustomerId = $epgCustomer->getEpgCustomerId();
      } else {
          $epgCustomerId = $this->_epgApi->idGenerator($customerData->getId() . '-');
      }

      // Authentication
      try{
          $authToken = $this->_epgApi->authentication(
              $epgCustomerId,
              strtoupper($this->_modelStoreManagerInterface->getStore()->getCurrentCurrencyCode()),
              strtoupper($this->_scopeConfig->getValue('general/country/default')),
              $methodInfo[1]
          );
          if (empty($authToken)) {
              throw new \Exception(__("Get authToken fails: Authentication returned null"));
          }
      } catch(\Exception $e) {
          throw new \Exception(__("Get authToken fails.") . " " . $e->getMessage());
      }

      // Register account
      if (empty($accountId)) {
          try{
              $fields = $form->fields($data);
              $account = $this->_epgApi->registerAccount($authToken, $fields, strtolower($methodInfo[0]));

              if (empty($account)) {
                  throw new \Exception(__("Registration account fails: Registration returned null"));
              }

              // If is not a guest
              if (ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->isLoggedIn()) {
                  if (empty($epgCustomer)) {
                      $epgCustomer = $this->_modelCustomerFactory->create()->create($customerData->getId(), $epgCustomerId);
                  }
              }
          } catch(\Exception $e) {
              throw new \Exception(__("Registration account fails.") . " " . $e->getMessage());
          }
      }

      // Prepay
      try{
          $fields = $form->fields($data, $account);
          $prepayToken = $this->_epgApi->prepayToken($authToken, $account['accountId'], $fields);

          if (empty($prepayToken)) {
              throw new \Exception("Prepay token fails: Prepay token process returned null");
          }
      } catch(\Exception $e) {
          throw new \Exception(__("Prepay token fails.") . " " . $e->getMessage());
      }

      // Create EPG order
      $cart = $this->_checkoutSession->getQuote();
      $billingAddress = $cart->getBillingAddress();
      $totals = $cart->collectTotals();
      $cartId = $billingAddress->getQuoteId();

      $epg_order = $this->_epgOrder->loadByAttributes([
        'id_cart' => $cartId,
        'epg_customer_id' => $epgCustomerId
      ]);

      if (empty($epg_order) || empty($epg_order->getIdEpgOrder())) {
          $epg_order = $this->_epgOrder;
          $epg_order->setIdCart($cartId);
          $epg_order->setTotalPaid((float)$totals->getGrandTotal());
          $epg_order->setCreateAt(date("Y-m-d H:i:s"));
          $epg_order->setUpdateAt(date("Y-m-d H:i:s"));
          $epg_order->setEpgCustomerId($epgCustomerId);
          $epg_order->setIdAccount($account['accountId']);
          $epg_order->save();
      } else {
          $epg_order->setIdAccount($account['accountId']);
          $epg_order->setUpdateAt(date("Y-m-d H:i:s"));
          $epg_order->save();
      }

      return [
          'prepayToken' => $prepayToken,
          'account' => $account,
          'epgCustomerId' => $epgCustomerId,
          'paymentMethodInfo' => $methodInfo
        ];
    }

}
