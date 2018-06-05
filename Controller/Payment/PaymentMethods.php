<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use EPG\EasyPaymentGateway\Helper\Data as HelperData;
use EPG\EasyPaymentGateway\Model\ApiFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use EPG\EasyPaymentGateway\Model\Form as EPGForm;

class PaymentMethods extends AbstractPayment
{
    /**
     * @var ApiFactory
     */
    protected $_modelApiFactory;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var ScopeConfigInterface
     */
    protected $_configScopeConfigInterface;

    public function __construct(Context $context,
        ApiFactory $modelApiFactory,
        HelperData $helperData,
        StoreManagerInterface $modelStoreManagerInterface,
        ScopeConfigInterface $configScopeConfigInterface)
    {
        parent::__construct($context);

        $this->_modelApiFactory = $modelApiFactory;
        $this->_helperData = $helperData;
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
    }

  public function execute(){
      $pMethod = (isset($_POST['method'])?(string)$_POST['method']:null);

      if (empty($pMethod)) {
              die('');
      }

      // Call cashier
      $cashierData = $this->_helperData->apiCashier();
      if (empty($cashierData)) {
          die('');
      }

      // Method selected info (name + operation)
      $methodInfo = explode('|', $pMethod);
      if (count($methodInfo) != 2) {
          die('');
      }

      $paymentMethod = null;
      foreach ($cashierData['paymentMethods'] as $method) {
        if ($methodInfo[0] == $method['name']) {
          $paymentMethod = $method;
          break;
        }
      }

      $accounts = [];
      foreach ($cashierData['accounts'] as $account) {
        if (strtolower($methodInfo[0]) != strtolower($account['paymentMethod'])) {
            continue;
        }
        $accounts[] = $account;
      }

      $form = new EPGForm($paymentMethod);
      $formHtml = $form->html();

      $result = array(
          'accounts' => $accounts,
          'formHtml' => $formHtml,
          'selectedPaymentMethod' => $methodInfo[0]
      );

      die(json_encode($result));
  }
}
