<?php

namespace EPG\EasyPaymentGateway\Helper;

use EPG\EasyPaymentGateway\Model\CustomerFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Customer\Model\Session as CustomerSession;
use EPG\EasyPaymentGateway\Model\Api as EpgApi;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    protected $epgCustomer;

    protected $epgCustomerId;

    protected $_customerSession;

    protected $_epgApi;

    protected $_scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var CustomerFactory
     */
    protected $_modelCustomerFactory;

    public function __construct(Context $context,
        CustomerFactory $modelCustomerFactory,
        CustomerSession $customerSession,
        EpgApi $epgApi,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $modelStoreManagerInterface)
    {

        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_modelCustomerFactory = $modelCustomerFactory;
        $this->_customerSession = $customerSession;
        $this->_epgApi = $epgApi;
        $this->_scopeConfig = $scopeConfig;

        parent::__construct($context);
    }

    public function getEpgCustomer() {
        if (ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->isLoggedIn()) {
            return $this->_modelCustomerFactory->create()->getByCustomerId(ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->getCustomer()->getId());
        }

        return [];
    }

    public function getAccounts() {
        if (ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->isLoggedIn()) {
            return $this->_modelCustomerFactory->create()->getCustomerAccounts(ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->getCustomer()->getId());
        }

        return [];
    }

    public function getAccountById($accountId) {
        $accounts  = $this->getAccounts();

        foreach($accounts as $key => $account) {
            if ($account['accountId'] == $accountId) {
                return $account;
            }
        }

        return null;
    }

    public function apiCashier()
    {
        $customerData = $this->_customerSession->getCustomer();
		    $epgCustomer = $this->getEpgCustomer();

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
                strtoupper($this->_scopeConfig->getValue('general/country/default'))
            );
            if (empty($authToken)) {
                throw new \Exception("Get authToken fails: Authentication returned null");
            }
        } catch(\Exception $e) {
            throw new \Exception(__("Get authToken fails") . ": " . $e->getMessage());
        }

        // Cashier
        if (!empty($authToken)) {
            try{
                $cashier = $this->_epgApi->cashier($authToken);
                if (empty($cashier)) {
                    throw new \Exception(__("Cashier response is empty.", 'woocommerce-gateway-epg'));
                }

                return [
                    'paymentMethods' => (isset($cashier['paymentMethods']))?$cashier['paymentMethods']:[],
                    'accounts' => (isset($cashier['accounts']))?$cashier['accounts']:[]
                    ];
            } catch(\Exception $e) {}
        }

        return [
            'paymentMethods' => [],
            'accounts' => []
        ];
    }
}
