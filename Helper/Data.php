<?php

namespace EPG\EasyPaymentGateway\Helper;

use EPG\EasyPaymentGateway\Model\CustomerFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ObjectManager;

class Data extends AbstractHelper
{
    /**
     * @var CustomerFactory
     */
    protected $_modelCustomerFactory;

    public function __construct(Context $context, 
        CustomerFactory $modelCustomerFactory)
    {
        $this->_modelCustomerFactory = $modelCustomerFactory;

        parent::__construct($context);
    }

    public function getEpgCustomer() {
        if (Mage::app()->isInstalled() && ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->isLoggedIn()) {
            return $this->_modelCustomerFactory->create()->getByCustomerId(ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->getCustomer()->getId());
        }

        return [];
    }

    public function getAccounts() {
        if (Mage::app()->isInstalled() && ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->isLoggedIn()) {
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
}
