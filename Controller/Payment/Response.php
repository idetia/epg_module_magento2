<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use EPG\EasyPaymentGateway\Model\CustomerFactory;
use EPG\EasyPaymentGateway\Model\Type\OnepageFactory;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\CustomerFactory as ModelCustomerFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Magento\Framework\Action;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

class Response extends AbstractPayment
{
    /**
     * @var OrderFactory
     */
    protected $_modelOrderFactory;

    public function __construct(Context $context, 
        CustomerFactory $modelCustomerFactory, 
        ModelCustomerFactory $customerModelCustomerFactory, 
        OnepageFactory $typeOnepageFactory, 
        Cart $modelCart, 
        OrderFactory $modelOrderFactory)
    {
        $this->_modelOrderFactory = $modelOrderFactory;

        parent::__construct($context, $modelCustomerFactory, $customerModelCustomerFactory, $typeOnepageFactory, $modelCart);
    }

  public function execute()
  {
    if ($this->getRequest()->get("flag") == "1" && $this->getRequest()->get("orderId"))
    {
      $orderId = $this->getRequest()->get("orderId");
      $order = $this->_modelOrderFactory->create()->loadByIncrementId($orderId);
      $order->setState(Order::STATE_PAYMENT_REVIEW, true, 'Payment Success.');
      $order->save();

      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->unsQuoteId();
      Action::_redirect('checkout/onepage/success', ['_secure'=> false]);
    }
    else
    {
      Action::_redirect('checkout/onepage/error', ['_secure'=> false]);
    }
  }
}
