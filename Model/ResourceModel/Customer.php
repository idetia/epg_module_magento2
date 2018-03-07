<?php

namespace EPG\EasyPaymentGateway\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Customer extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('easypaymentgateway_customer', 'id');
    }

    public function loadByAttributes($attributes)
    {
        $adapter = $this->getConnection();
        $where   = [];
        foreach ($attributes as $attributeCode=> $value) {
            $where[] = sprintf('%s=:%s', $attributeCode, $attributeCode);
        }

        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where(implode(' AND ', $where));

        $binds = $attributes;

        return $adapter->fetchRow($select, $binds);
    }
}
