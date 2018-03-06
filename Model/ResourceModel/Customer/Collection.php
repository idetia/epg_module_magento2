<?php

namespace EPG\EasyPaymentGateway\Model\ResourceModel\Customer;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    public function _construct()
    {
        $this->_init('EPG\EasyPaymentGateway\Model\Customer', 'EPG\EasyPaymentGateway\Model\ResourceModel\Customer');
    }
}
