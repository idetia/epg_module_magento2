<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use EPG\EasyPaymentGateway\Model\ApiFactory;
use EPG\EasyPaymentGateway\Model\CustomerFactory;
use EPG\EasyPaymentGateway\Model\OrderFactory;
use EPG\EasyPaymentGateway\Model\Type\OnepageFactory;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\CustomerFactory as ModelCustomerFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class Charge extends AbstractPayment
{
    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var RequestInterface
     */
    protected $_appRequestInterface;

    /**
     * @var ApiFactory
     */
    protected $_modelApiFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $_configScopeConfigInterface;

    /**
     * @var OrderFactory
     */
    protected $_modelOrderFactory;

    protected $_urlInterface;

    public function __construct(Context $context,
        CustomerFactory $modelCustomerFactory,
        ModelCustomerFactory $customerModelCustomerFactory,
        OnepageFactory $typeOnepageFactory,
        Cart $modelCart,
        StoreManagerInterface $modelStoreManagerInterface,
        RequestInterface $appRequestInterface,
        ApiFactory $modelApiFactory,
        ScopeConfigInterface $configScopeConfigInterface,
        OrderFactory $modelOrderFactory,
        UrlInterface $urlInterface)
    {
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_appRequestInterface = $appRequestInterface;
        $this->_modelApiFactory = $modelApiFactory;
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
        $this->_modelOrderFactory = $modelOrderFactory;
        $this->_urlInterface = $urlInterface;

        parent::__construct($context, $modelCustomerFactory, $customerModelCustomerFactory, $typeOnepageFactory, $modelCart);
    }

  public function execute()
  {
      $errors = [];

      $cart = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getQuote();
      $billingAddress = $cart->getBillingAddress();
      $totals = $cart->collectTotals();
      $cartId = $billingAddress->getQuoteId();

      // Charge
      try{
          $isSSL = ($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure());
          $returnUrl = $this->_urlInterface->getUrl('easypaymentgateway/payment/return', ['_secure'=>$isSSL]);
          $prepayToken = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgPrepaymentToken();
          $epgCustomerId = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgCustomerId();
          $epgApi = $this->_modelApiFactory->create();
          $uniqId = uniqid();

          if (empty($prepayToken) || empty($epgCustomerId)) {
              throw new \Exception(__("There was an error trying to process this credit card.\nPlease, try to introduce your credit card data again."));
          }

          $chargeResult = $epgApi->charge(
                  $cartId,
                  $prepayToken,
                  $epgCustomerId,
                  null,
                  $billingAddress,
                  (float)$totals->getGrandTotal(),
                  strtoupper($totals->getQuoteCurrencyCode()),
                  strtoupper($this->_configScopeConfigInterface->getValue('general/country/default', ScopeInterface::SCOPE_STORE)),
                  strtoupper(substr($this->_configScopeConfigInterface->getValue('general/locale/code', ScopeInterface::SCOPE_STORE),0,2)),
                  $returnUrl . '?_=status|' . $cartId . '|' . md5($prepayToken . $uniqId . 'success'),
                  $returnUrl . '?_=success|' . $cartId . '|' . md5($prepayToken . $uniqId . 'success'),
                  $returnUrl . '?_=error|' . $cartId . '|' . md5($prepayToken . $uniqId . 'error'),
                  $returnUrl . '?_=cancel|' . $cartId . '|' . md5($prepayToken . $uniqId . 'cancel')
                  );

          if (empty($chargeResult)) {
              throw new \Exception();
          }

      } catch(\Exception $e) {
          ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPrepaymentToken(null);
          ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgCustomerId(null);

          $errors[] = __('Charge fails:') . ' ' . $e->getMessage();
          ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgErrors($errors);
          $this->_redirect('easypaymentgateway/payment/fail', ['_secure' => $isSSL]);
          return false;
      }

      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgPrepaymentToken(null);
      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgCustomerId(null);

      // Load epg order
      $epg_order = $this->_modelOrderFactory->create()->loadByAttributes([
          'id_cart' => $cartId,
          'epg_customer_id' => $epgCustomerId
      ]);

      if (empty($epg_order) || empty($epg_order->getIdEpgOrder())) {
          $errors[] = __('The order does not exists.');
          ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgErrors($errors);
          $this->_redirect('easypaymentgateway/payment/fail', ['_secure' => $isSSL]);
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
          ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setQuoteId(null);
          ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->clear();
          $this->_redirectUrl($chargeResult['redirectURL']);
          return false;

      // Direct transaction
      } else {
          switch($chargeResult['status']) {
              case 'SUCCESS':
                  $this->processOrder($chargeResult, $epg_order, $cart);
                  return true;
              case 'ERROR':
              case 'FAIL':
              case 'CANCELED':
              default:
                  $errors[] = __('Charge fails.');
                  ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgErrors($errors);
                  $this->_redirect('easypaymentgateway/payment/fail', ['_secure' => $isSSL]);
                  return false;
          }

      }

  }
}
