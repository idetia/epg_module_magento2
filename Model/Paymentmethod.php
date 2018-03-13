<?php

namespace EPG\EasyPaymentGateway\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Payment\Helper\Data as HelperData;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use EPG\EasyPaymentGateway\Model\Api as EpgApi;
use EPG\EasyPaymentGateway\Model\Order as EpgOrder;
use EPG\EasyPaymentGateway\Model\Customer as EpgCustomer;
use EPG\EasyPaymentGateway\Helper\Data as EpgHelper;
use Magento\Store\Model\ScopeInterface;

class Paymentmethod extends AbstractMethod {
    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var RequestInterface
     */
    protected $_appRequestInterface;

    protected $_customerSession;

    protected $_checkoutSession;

    protected $_epgApi;

    protected $_epgOrder;

    protected $_epgCustomer;

    protected $_epgHelper;

    protected $_urlInterface;

    protected $_responseFactory;

    public function __construct(Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        HelperData $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        StoreManagerInterface $modelStoreManagerInterface,
        RequestInterface $appRequestInterface,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        EpgApi $epgApi,
        EpgOrder $epgOrder,
        EpgCustomer $epgCustomer,
        EpgHelper $epgHelper,
        UrlInterface $urlInterface,
        ResponseFactory $responseFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [])
    {
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_appRequestInterface = $appRequestInterface;
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_epgApi = $epgApi;
        $this->_epgOrder = $epgOrder;
        $this->_epgCustomer = $epgCustomer;
        $this->_urlInterface = $urlInterface;
        $this->_responseFactory = $responseFactory;
        $this->_epgHelper = $epgHelper;

        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data);
    }

  const METHOD_CODE = 'easypaymentgateway';

  protected $_code  = self::METHOD_CODE;
  protected $_isGateway = true;
  protected $_canCapture = true;
  protected $_canCapturePartial = true;
  protected $_canRefund = false;
  protected $_minOrderTotal = 0;
  protected $_infoBlockType =  \EPG\EasyPaymentGateway\Block\Info::class;

  protected $errors = [];

  // Capture
  public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
  {
    parent::capture($payment, $amount);

    try{
        if (empty($this->_checkoutSession->getReturnPayment()) || !$this->_checkoutSession->getReturnPayment()) {
            return $this->charge();
        }

        $this->_checkoutSession->unsReturnPayment();
        return $this;

      } catch (\Exception $e) {
          $this->debugData(['exception' => $e->getMessage()]);
          throw new \Magento\Framework\Validator\Exception(__($e->getMessage()));
      }

   }

   // Charge
   public function charge()
   {
       $this->createEpgOrder();

       $cart = $this->_checkoutSession->getQuote();
       $billingAddress = $cart->getBillingAddress();
       $totals = $cart->collectTotals();
       $cartId = $billingAddress->getQuoteId();

       try{
           $isSSL = ($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure());
           $returnUrl = $this->_urlInterface->getUrl('easypaymentgateway/payment/returnPayment', ['_secure'=>$isSSL]);
           $prepayToken = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgPrepaymentToken();
           $epgCustomerId = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgCustomerId();
           $uniqId = uniqid();

           if (empty($prepayToken) || empty($epgCustomerId)) {
               throw new \Exception(__("There was an error trying to process this credit card.\nPlease, try to introduce your credit card data again."));
           }

           $chargeResult = $this->_epgApi->charge(
                   $cartId,
                   $prepayToken,
                   $epgCustomerId,
                   null,
                   $billingAddress,
                   (float)$totals->getGrandTotal(),
                   strtoupper($totals->getQuoteCurrencyCode()),
                   strtoupper($this->_scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_STORE)),
                   strtoupper(substr($this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE),0,2)),
                   $returnUrl . '?_=status|' . $cartId . '|' . md5($prepayToken . $uniqId . 'success'),
                   $returnUrl . '?_=success|' . $cartId . '|' . md5($prepayToken . $uniqId . 'success'),
                   $returnUrl . '?_=error|' . $cartId . '|' . md5($prepayToken . $uniqId . 'error'),
                   $returnUrl . '?_=cancel|' . $cartId . '|' . md5($prepayToken . $uniqId . 'cancel')
                   );

           if (empty($chargeResult)) {
               throw new \Exception(__('There was an error.'));
           }

       } catch(\Exception $e) {
           ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPrepaymentToken(null);
           ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgCustomerId(null);

           $errors = __('Charge fails:') . ' ' . $e->getMessage();
           throw new \Exception($errors);
           return false;
       }

       ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPrepaymentToken(null);
       ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgCustomerId(null);

       // Load epg order
       $epg_order = $this->_epgOrder->loadByAttributes([
           'id_cart' => $cartId,
           'epg_customer_id' => $epgCustomerId
       ]);

       if (empty($epg_order) || empty($epg_order->getIdEpgOrder())) {
           $errors = __('The order does not exists.');
           throw new \Exception($errors);
       }

       $epg_order->setIdTransaction($chargeResult['transactionId']);
       $epg_order->setPaymentDetails(json_encode($chargeResult['transactionResponse']));
       $epg_order->setPaymentStatus($chargeResult['status']);
       $epg_order->setUpdateAt(date("Y-m-d H:i:s"));
       $epg_order->setToken(md5($prepayToken . $uniqId . 'success'));
       $epg_order->setErrorToken(md5($prepayToken . $uniqId . 'error'));
       $epg_order->setCancelToken(md5($prepayToken . $uniqId . 'cancel'));
       $epg_order->save();

       // External redirection
       if ($chargeResult['status'] === 'REDIRECTED' && isset($chargeResult['redirectURL']) && !empty($chargeResult['redirectURL'])) {
           $cart->setIsActive(0)->save();
           ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->clearQuote();
           throw new \Exception(json_encode(['redirectURL' => $chargeResult['redirectURL']]));

       // Direct transaction
       } else {
           switch($chargeResult['status']) {
               case 'SUCCESS':
                   $this->_checkoutSession->setEpgChargeData(
                     [
                       'chargeResult' => $chargeResult,
                       'epgOrder' => $epg_order
                     ]
                   );
                   return true;
               case 'ERROR':
               case 'FAIL':
               case 'CANCELED':
               default:
                   $errors = __('Charge fails. Please, try to send your payment data again.');
                   throw new \Exception($errors);
                   return false;
           }
       }
   }

   private function createEpgOrder() {
     // Call API
     $apiData = null;

     try {
         $apiData = $this->getApiData();
     } catch(\Exception $e) {
         throw new \Exception($e->getMessage());
     }

     if (!isset($apiData['prepayToken'])) {
         throw new \Exception("There was an error trying to process this credit card.\nPlease, try it again.");
     }

     $this->_checkoutSession->setEpgPrepaymentToken($apiData['prepayToken']);
     $this->_checkoutSession->setEpgCustomerId($apiData['epgCustomerId']);
     $this->_checkoutSession->setEpgPaymentInfo($apiData['account']);

     return $this;
   }

   private function getApiData() {
       $prepayToken = null;
       $customerData = $this->_customerSession->getCustomer();
       $epgCustomer = $this->_epgCustomer->getByCustomerId($customerData->getId());
       if (!empty($epgCustomer)) {
           $epgCustomerId = $epgCustomer->getEpgCustomerId();
       } else {
           $epgCustomerId = $customerData->getId() . '-' . md5(microtime());
       }

       // Authentication
       try{
           $authToken = $this->_epgApi->authentication(
               $epgCustomerId,
               strtoupper($this->_modelStoreManagerInterface->getStore()->getCurrentCurrencyCode()),
               strtoupper($this->_scopeConfig->getValue('general/country/default'))
           );
           if (empty($authToken)) {
               throw new \Exception("Get authToken fails: Authentication returned null");
           }
       } catch(\Exception $e) {
           throw new \Exception(__("Get authToken fails") . ": " . $e->getMessage());
       }

       // Register account
       $paymentInfo = $this->getInfoInstance();
       $data = $paymentInfo->getAdditionalInformation();
       $account = [];
       $accountId = $data['account'];
       $cardCvn = $data['card_cvn'];
       if (empty($accountId) || $accountId == "0") {
           try{
               $account = $this->_epgApi->registerAccount($authToken, [
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
               if ($this->_customerSession->isLoggedIn()) {
                   if (empty($epgCustomer)) {
                       $epgCustomer = $this->_epgCustomer->create($customerData->getId(), $epgCustomerId);
                   }
                   $epgCustomer->addAccount($account);
               }
           } catch(\Exception $e) {
               throw new \Exception(__("Registration account fails") . ": " . $e->getMessage());
           }
       } else {
           $account = $this->_epgHelper->getAccountById($accountId);
       }

       // Prepay
       try{
           $prepayToken = $this->_epgApi->prepayToken($authToken, $account['accountId'], ['card_cvn' => $cardCvn]);
           if (empty($prepayToken)) {
               throw new \Exception("Prepay token fails: Prepay token process returned null");
           }
       } catch(\Exception $e) {
           throw new \Exception(__("Prepay token fails") . ": " . $e->getMessage());
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
           'epgCustomerId' => $epgCustomerId
         ];
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

}
