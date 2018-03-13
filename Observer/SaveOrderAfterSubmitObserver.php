<?php

namespace EPG\EasyPaymentGateway\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\SessionFactory as CheckoutSessionFactory;
use Psr\Log\LoggerInterface;
use EPG\EasyPaymentGateway\Model\ProcessOrderFactory;

class SaveOrderAfterSubmitObserver implements ObserverInterface
{
    protected $_logger;

    protected $_checkoutSession;

    protected $_processOrder;

    public function __construct(
        LoggerInterface $logger,
        CheckoutSessionFactory $checkoutSessionFactory,
        ProcessOrderFactory $processOrderFactory
    ) {
        $this->_logger = $logger;
        $this->_checkoutSession = $checkoutSessionFactory->create();
        $this->_processOrder = $processOrderFactory->create();
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        $cart = $observer->getEvent()->getData('quote');
        $chargeData = $this->_checkoutSession->getEpgChargeData();

        if (empty($chargeData)) {
            $this->_logger->critical("There are not data in session.");
            return $this;
        }

        $this->_processOrder->process($order, $cart, $chargeData);

        return $this;
    }

}
