<?php

namespace EPG\EasyPaymentGateway\Model;

use Magento\Framework\Model\AbstractModel;

class Order extends AbstractModel
{
    protected function _construct()
    {
        $this->_init('EPG\EasyPaymentGateway\Model\ResourceModel\Order');
    }

    public function loadByAttributes($attributes)
    {
        $this->setData($this->getResource()->loadByAttributes($attributes));
        return $this;
    }

    public function getByOrderId($customerId)
    {
        $epgCustomer = $this->loadByAttributes(['id_order' => $customerId]);

        if (empty($epgCustomer) || empty($epgCustomer->getId())) {
            return null;
        }

        return $epgCustomer;
    }
}
