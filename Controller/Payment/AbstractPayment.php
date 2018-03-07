<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use EPG\EasyPaymentGateway\Model\CustomerFactory;
use EPG\EasyPaymentGateway\Model\Type\OnepageFactory;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\CustomerFactory as ModelCustomerFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

abstract class AbstractPayment extends Action
{
    /**
     * @var CustomerFactory
     */
    protected $_modelCustomerFactory;

    /**
     * @var ModelCustomerFactory
     */
    protected $_customerModelCustomerFactory;

    /**
     * @var OnepageFactory
     */
    protected $_typeOnepageFactory;

    /**
     * @var Cart
     */
    protected $_modelCart;

    public function __construct(Context $context,
        CustomerFactory $modelCustomerFactory,
        ModelCustomerFactory $customerModelCustomerFactory,
        OnepageFactory $typeOnepageFactory,
        Cart $modelCart)
    {
        $this->_modelCustomerFactory = $modelCustomerFactory;
        $this->_customerModelCustomerFactory = $customerModelCustomerFactory;
        $this->_typeOnepageFactory = $typeOnepageFactory;
        $this->_modelCart = $modelCart;

        parent::__construct($context);
    }


  public function processOrder($chargeResult, $epg_order, $cart) {
      // Load customer
      $epgCustomer = $this->_modelCustomerFactory->create()->loadByAttributes(['epg_customer_id' => $epg_order->getEpgCustomerId()]);
      $customer = $this->_customerModelCustomerFactory->create()->load($epgCustomer->getCustomerId());
      ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->setCustomer($customer);

      // Create Mage order after payment
      $saveResult = $this->_typeOnepageFactory->create()->saveMageOrder();
      $order = new Order();
      $orderId = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getLastRealOrderId();
      $order->loadByIncrementId($orderId);

      $epg_order->setIdOrder($order->getEntityId());
      $epg_order->save();

      $order->setEpgTransactionId($chargeResult['transactionId']);
      $order->setState(Order::STATE_PROCESSING, true, __('Payment success.'));

      $payment = $order->getPayment();
      $payment->setTransactionId($chargeResult['transactionId']);
      $payment->setLastTransId($chargeResult['transactionId']);
      $transaction = $payment->addTransaction('order');
      $transaction->setIsClosed(true);
      if (isset($chargeResult['transactionResponse'])) {
          $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $this->buildTransactionInformation($chargeResult['transactionResponse']));
      }
      $transaction->save();
      $payment->save();

      $order->save();

      $this->_eventManager->dispatch(
          'epg_checkout_order_saved',
          ['order' => $order, 'quote' => $cart]
      );

      $cart->setIsActive(0)->save();
      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setQuoteId(null);
      // $this->_modelCart->truncate()->save();
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



  private $transactionInfo = [];
  private function buildTransactionInformation($array, $tab = 0)
  {
      if (empty($array)) {
          return $this->transactionInfo;
      }

      $mark = '';
      if ($tab == 0){
          $this->transactionInfo = [];
      } else {
          for($i = 0; $i < $tab; $i++){
              $mark .= "-";
          }
      }

      foreach($array as $key => $elem){
          if(!is_array($elem)){
              $this->transactionInfo[$mark . ' ' . $key] = $elem;
          }
          $this->buildTransactionInformation($elem, $tab+1);
      }

      return $this->transactionInfo;
  }

/*
*/
}
