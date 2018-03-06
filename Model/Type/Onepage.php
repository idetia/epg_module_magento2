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

class Onepage extends TypeOnepage
{
    /**
     * @var ApiFactory
     */
    protected $_modelApiFactory;

    /**
     * @var ModelCustomerFactory
     */
    protected $_modelCustomerFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $_configScopeConfigInterface;

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
        $this->_modelApiFactory = $modelApiFactory;
        $this->_modelCustomerFactory = $modelCustomerFactory;
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
        $this->_helperData = $helperData;
        $this->_modelOrderFactory = $modelOrderFactory;

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
                throw new \Exception(__("There was an error trying to process this credit card.\nPlease, try to introduce your credit card data again."));
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

    private function saveEpgPayment($data) {
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

      // Call API
      $apiData = null;
      try {
          $apiData = $this->getApiData($data);
      } catch(\Exception $e) {
          throw new \Exception($e->getMessage());
      }
      if (!isset($apiData['prepayToken'])) {
          throw new \Exception(__("There was an error trying to process this credit card.\nPlease, try it again."));
      }

      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPrepaymentToken($apiData['prepayToken']);
      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgCustomerId($apiData['epgCustomerId']);
      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPaymentInfo($apiData['account']);

      return $this;
    }

    private function getApiData($data) {
        $prepayToken = null;

        $customerData = ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->getCustomer();
        $epgApi = $this->_modelApiFactory->create();
        $epgCustomer = $this->_modelCustomerFactory->create()->getByCustomerId($customerData->getId());
        if (!empty($epgCustomer)) {
            $epgCustomerId = $epgCustomer->getEpgCustomerId();
        } else {
            $epgCustomerId = $customerData->getId() . '-' . md5(microtime());
        }

        // Authentication
        try{
            $authToken = $epgApi->authentication(
                $epgCustomerId,
                strtoupper($this->_modelStoreManagerInterface->getStore()->getCurrentCurrencyCode()),
                strtoupper($this->_configScopeConfigInterface->getValue('general/country/default', ScopeInterface::SCOPE_STORE))
            );

            if (empty($authToken)) {
                throw new \Exception(__("Get authToken fails: Authentication returned null"));
            }

        } catch(\Exception $e) {
            throw new \Exception(__("Get authToken fails") . ": " . $e->getMessage());
        }

        // Register account
        $account = [];
        $accountId = $data['account'];
        $cardCvn = $data['card_cvn'];
        if (empty($accountId) || $accountId == "0") {
            try{
                $account = $epgApi->registerAccount($authToken, [
                    'card_number' => $data['card_number'],
                    'card_expiry_month' => $data['card_expiry_month'],
                    'card_expiry_year' => $data['card_expiry_year'],
                    'card_holder_name' => $data['card_holder_name'],
                    'card_cvn' => $cardCvn
                ]);

                if (empty($account)) {
                    throw new \Exception(__("Registration account fails: Registration returned null"));
                }

                // If is not a guest
                if (ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->isLoggedIn()) {
                    if (empty($epgCustomer)) {
                        $epgCustomer = $this->_modelCustomerFactory->create()->create($customerData->getId(), $epgCustomerId);
                    }

                    $epgCustomer->addAccount($account);
                }

            } catch(\Exception $e) {
                throw new \Exception(__("Registration account fails") . ": " . $e->getMessage());
            }
        } else {
            $account = $this->_helperData->getAccountById($accountId);
        }

        // Prepay
        try{
            $prepayToken = $epgApi->prepayToken($authToken, $account['accountId'], ['card_cvn' => $cardCvn]);

            if (empty($prepayToken)) {
                throw new \Exception(__("Prepay token fails: Prepay token process returned null"));
            }

        } catch(\Exception $e) {
            throw new \Exception(__("Prepay token fails") . ": " . $e->getMessage());
        }

        // Create EPG order
        $cart = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getQuote();
        $billingAddress = $cart->getBillingAddress();
        $totals = $cart->collectTotals();
        $cartId = $billingAddress->getQuoteId();

        $epg_order = $this->_modelOrderFactory->create()->loadByAttributes([
          'id_cart' => $cartId,
          'epg_customer_id' => $epgCustomerId
        ]);

        if (empty($epg_order) || empty($epg_order->getIdEpgOrder())) {
            $epg_order = $this->_modelOrderFactory->create();
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
            'epgCustomerId' => $epgCustomerId
          ];
    }

    private static final function checkCard($number, $extraCheck = false){
        $cards = [
            "visa" => "(4\d{12}(?:\d{3})?)",
            "amex" => "(3[47]\d{13})",
            "maestro" => "((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)",
            "mastercard" => "(5[1-5]\d{14})"
        ];

        $names = ["Visa", "American Express", "Maestro", "Mastercard"];
        $matches = [];
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

}
