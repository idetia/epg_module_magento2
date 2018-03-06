<?php

namespace EPG\EasyPaymentGateway\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Customer extends AbstractModel
{
    /**
     * @var CustomerFactory
     */
    protected $_modelCustomerFactory;

    public function __construct(Context $context, 
        Registry $registry, 
        CustomerFactory $modelCustomerFactory, 
        AbstractResource $resource = null, 
        AbstractDb $resourceCollection = null, 
        array $data = [])
    {
        $this->_modelCustomerFactory = $modelCustomerFactory;

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init('EPG\EasyPaymentGateway\Model\ResourceModel\Customer');
    }

    public function loadByAttributes($attributes)
    {
        $this->setData($this->getResource()->loadByAttributes($attributes));
        return $this;
    }

    public function getCustomerAccounts($customerId)
    {
        $epgCustomer = $this->loadByAttributes(['customer_id' => $customerId]);

        if (empty($epgCustomer) || empty($epgCustomer->getId())) {
            return null;
        }

        $accounts = [];
        try{
            $accounts = json_decode($epgCustomer->getAccounts(), true);
        } catch(\Exception $e) {}

        return $accounts;
    }

    public function getByCustomerId($customerId)
    {
        $epgCustomer = $this->loadByAttributes(['customer_id' => $customerId]);

        if (empty($epgCustomer) || empty($epgCustomer->getId())) {
            return null;
        }

        return $epgCustomer;
    }

    public function getByOrderId($customerId)
    {
        $epgCustomer = $this->loadByAttributes(['order_id' => $customerId]);

        if (empty($epgCustomer) || empty($epgCustomer->getId())) {
            return null;
        }

        return $epgCustomer;
    }

    public function create($customerId, $epgCustomerId)
    {
        $epgCustomer = $this->getByCustomerId($customerId);

        if (!empty($epgCustomer)) {
            return $epgCustomer;
        }
        $epgCustomer = $this->_modelCustomerFactory->create();
        $epgCustomer->setCustomerId($customerId);
        $epgCustomer->setEpgCustomerId($epgCustomerId);
        $epgCustomer->setAccounts(json_encode([]));
        $epgCustomer->setCreateAt(date("Y-m-d H:i:s"));
        $epgCustomer->setUpdateAt(date("Y-m-d H:i:s"));
        $epgCustomer->save();

        return $epgCustomer;
    }

    public function addAccount($newAccount)
    {
        $accounts = [];
        try{
            $accounts = json_decode($this->getAccounts(), true);

            foreach($accounts as $account) {
                if ($newAccount['accountId'] == $account['accountId']) {
                    // If already exists
                    return $accounts;
                }
            }

            $accounts[] = $newAccount;

            $this->setAccounts(json_encode($this->getSimpleArray($accounts)));
            $this->setUpdateAt(date("Y-m-d H:i:s"));
            $this->save();

        } catch(\Exception $e){}

        return $accounts;
    }

    public function removeAccount($accountId)
    {
        try{
            $accounts = json_decode($this->getAccounts(), true);

            $keyToDelete = -1;
            foreach($accounts as $key => $account) {
                if ($accountId == $account['accountId']) {
                    $keyToDelete = $key;
                    break;
                }
            }

            if ($keyToDelete >= 0) {
                unset($accounts[$keyToDelete]);

                $this->setAccounts(json_encode($this->getSimpleArray($accounts)));
                $this->setUpdateAt(date("Y-m-d H:i:s"));
                $this->save();

                return true;
            }

        }catch(\Exception $e){}

        return false;
    }

    public function checkAccount($accountId)
    {
        try{
            $accounts = json_decode($this->getAccounts(), true);

            foreach($accounts as $account) {
                if ($accountId == $account['accountId']) {
                    return true;
                }
            }

        }catch(\Exception $e){}

        return false;
    }

    private function getSimpleArray($array)
    {
        $result = [];

        foreach($array as $item) {
            $result[] = $item;
        }

        return $result;
    }
}
