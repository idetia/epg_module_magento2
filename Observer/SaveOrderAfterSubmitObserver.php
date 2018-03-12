<?php

namespace EPG\EasyPaymentGateway\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\App\ObjectManager;
use EPG\EasyPaymentGateway\Model\CustomerFactory as EpgCustomerFactory;
use Magento\Framework\Registry;
use Magento\Customer\Model\SessionFactory as CustomerSessionFactory;
use Magento\Checkout\Model\SessionFactory as CheckoutSessionFactory;
use Magento\Customer\Api\CustomerRepositoryInterfaceFactory;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class SaveOrderAfterSubmitObserver implements ObserverInterface
{
    protected $_logger;

    protected $_coreRegistry;

    protected $_customerRepository;

    protected $_customerSession;

    protected $_checkoutSession;

    protected $_epgCustomer;

    protected $_transactionBuilder;

    public function __construct(
        LoggerInterface $logger,
        Registry $coreRegistry,
        CustomerRepositoryInterfaceFactory $customerRepositoryFactory,
        CustomerSessionFactory $customerSessionFactory,
        CheckoutSessionFactory $checkoutSessionFactory,
        EpgCustomerFactory $epgCustomerFactory,
        BuilderInterface $transactionBuilder
    ) {
        $this->_logger = $logger;
        $this->_coreRegistry = $coreRegistry;
        $this->_customerRepository = $customerRepositoryFactory->create();
        $this->_customerSession = $customerSessionFactory->create();
        $this->_checkoutSession = $checkoutSessionFactory->create();
        $this->_epgCustomer = $epgCustomerFactory->create();
        $this->_transactionBuilder = $transactionBuilder;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        $cart = $observer->getEvent()->getData('quote');
        $chargeData = $this->_checkoutSession->getEpgChargeData();

        if (empty($chargeData)) {
            return $this;
        }

        $this->postProcessOrder($order, $cart, $chargeData);

        return $this;
    }

    private function postProcessOrder($order, $cart, $chargeData)
    {
        $chargeResult = $chargeData['chargeResult'];
        $epgOrder = $chargeData['epgOrder'];

        // Load customer
        // $epgCustomer = $this->_epgCustomer->loadByAttributes(['epg_customer_id' => $epgOrder->getEpgCustomerId()]);
        // $customer = $this->_customerRepository->getById($epgCustomer->getCustomerId());
        // ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->setCustomer($customer);

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
        if (empty($arr)) {
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
