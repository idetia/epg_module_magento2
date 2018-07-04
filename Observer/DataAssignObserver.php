<?php

namespace EPG\EasyPaymentGateway\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Payment\Model\InfoInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @param Observer $observer
     * @return void
     */
     public function execute(Observer $observer)
     {
         $data = $this->readDataArgument($observer);

         $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
         if (!is_array($additionalData)) {
             return;
         }

         $paymentInfo = $this->readPaymentModelArgument($observer);

         foreach ($additionalData as $key => $itemData) {
           $paymentInfo->setAdditionalInformation(
               $key,
               $itemData
           );
         }
     }
}
