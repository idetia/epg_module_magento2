<?php

namespace EPG\EasyPaymentGateway\Model\ResourceModel\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    public function _construct()
    {
        $this->_init('EPG\EasyPaymentGateway\Model\Order', 'EPG\EasyPaymentGateway\Model\ResourceModel\Order');
    }
}
