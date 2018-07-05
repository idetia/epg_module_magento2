<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use EPG\EasyPaymentGateway\Helper\Data as HelperData;
use EPG\EasyPaymentGateway\Model\ApiFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class RemoveAccount extends AbstractPayment
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
      $epgApi = $this->_modelApiFactory->create();
      $epgCustomer = $this->_helperData->getEpgCustomer();
      if (empty($epgCustomer) || empty($epgCustomer->getId())) {
          die(json_encode(['result' => false]));
      }

      // Authentication
      try{
          $authToken = $epgApi->authentication(
                  $epgCustomer->getEpgCustomerId(),
                  strtoupper($this->_modelStoreManagerInterface->getStore()->getCurrentCurrencyCode()),
                  strtoupper($this->_configScopeConfigInterface->getValue('general/country/default', ScopeInterface::SCOPE_STORE)));

          if (empty($authToken)) {
              die(json_encode(['result' => false]));
          }

      } catch(\Exception $e) {
          die(json_encode(['result' => false]));
      }

      // Disable account
      try{
          $accountId = (isset($_POST['account_id'])?(string)$_POST['account_id']:null);

          if (empty($accountId)) {
              die(json_encode(['result' => false]));
          }

          $account = $epgApi->disableAccount($authToken, $accountId);

          if (empty($account)) {
              die(json_encode(['result' => false]));
          }

          die(json_encode(['result' => true]));

      } catch(\Exception $e) {
          die(json_encode(['result' => false]));
      }
  }
}
