<?php

namespace EPG\EasyPaymentGateway\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\App\ObjectManager;
use Magento\Checkout\Model\SessionFactory as CheckoutSessionFactory;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class ProcessOrder extends AbstractModel
{
    protected $_logger;

    protected $_checkoutSession;

    protected $_transactionBuilder;

    public function __construct(
        LoggerInterface $logger,
        CheckoutSessionFactory $checkoutSessionFactory,
        BuilderInterface $transactionBuilder
    ) {
        $this->_logger = $logger;
        $this->_checkoutSession = $checkoutSessionFactory->create();
        $this->_transactionBuilder = $transactionBuilder;
    }

    public function process($order, $cart, $chargeData)
    {
        $chargeResult = $chargeData['chargeResult'];
        $epgOrder = $chargeData['epgOrder'];

        $epgOrder->setIdOrder($order->getEntityId());
        $epgOrder->save();

        $order->setEpgTransactionId($chargeResult['transactionId']);

        $order->setState(Order::STATE_PROCESSING);

        $details = [];
        try{
          if (isset($chargeResult['transactionResponse'])) {
             $details = $this->buildTransactionInformation($chargeResult['transactionResponse']);
          }
        } catch(\Exception $e) {
          $this->_logger->critical($e->getMessage());
        }

        $payment = $order->getPayment();
        $payment->setTransactionId($chargeResult['transactionId']);
        $payment->setLastTransId($chargeResult['transactionId']);
        $payment->setAdditionalInformation($details);

        $transaction = $this->_transactionBuilder
            ->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($chargeResult['transactionId'])
            ->setAdditionalInformation(
                [Transaction::RAW_DETAILS => (array) $details]
            )
            ->setFailSafe(true)
            ->build(Transaction::TYPE_CAPTURE);

        $transaction->save();
        $payment->setParentTransactionId(null);
        $payment->save();

        $order->save();

        $cart->setIsActive(0)->save();
        ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setQuoteId(null);

        $this->_checkoutSession->unsEpgChargeData();
    }

    private $transactionInfo = [];
    private function buildTransactionInformation($arr, $tab = 0)
    {
        if (empty($arr) || !is_array($arr)) {
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

        foreach($arr as $key => $elem){
            if(!is_array($elem)){
                $this->transactionInfo[$mark . ' ' . $key] = $elem;
            } else {
                $this->buildTransactionInformation($elem, $tab+1);
            }
        }

        return $this->transactionInfo;
    }
}
