<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;

abstract class AbstractPayment extends Action
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    private function getOrderStatus($returnType) {
      $paymentStatus = Order::STATE_PENDING_PAYMENT;
      switch($returnType) {
          case 'SUCCESS':
              $paymentStatus = Order::STATE_COMPLETE;
              break;
          case 'ERROR':
              $paymentStatus = Order::STATE_CANCELED;
              break;
          case 'CANCELED':
              $paymentStatus = Order::STATE_CANCELED;
              break;
      }

      return $paymentStatus;
    }
}
