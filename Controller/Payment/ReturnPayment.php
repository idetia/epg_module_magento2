<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use EPG\EasyPaymentGateway\Model\OrderFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory as ModelOrderFactory;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Store\Model\StoreManagerInterface;
use EPG\EasyPaymentGateway\Model\CustomerFactory as EpgCustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterfaceFactory;
use Magento\Checkout\Model\Type\Onepage as TypeOnepage;
use Psr\Log\LoggerInterface;

class ReturnPayment extends AbstractPayment
{
    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var RequestInterface
     */
    protected $_appRequestInterface;

    /**
     * @var OrderFactory
     */
    protected $_modelOrderFactory;

    /**
     * @var QuoteFactory
     */
    protected $_modelQuoteFactory;

    /**
     * @var ModelOrderFactory
     */
    protected $_salesModelOrderFactory;

    protected $_epgCustomer;

    protected $_customerRepository;

    protected $_typeOnepage;

    protected $_logger;

    public function __construct(Context $context,
        StoreManagerInterface $modelStoreManagerInterface,
        RequestInterface $appRequestInterface,
        OrderFactory $modelOrderFactory,
        QuoteFactory $modelQuoteFactory,
        ModelOrderFactory $salesModelOrderFactory,
        EpgCustomerFactory $epgCustomerFactory,
        CustomerRepositoryInterfaceFactory $customerRepositoryFactory,
        TypeOnepage $typeOnepage,
        LoggerInterface $logger)
    {
        parent::__construct($context);

        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_appRequestInterface = $appRequestInterface;
        $this->_modelOrderFactory = $modelOrderFactory;
        $this->_modelQuoteFactory = $modelQuoteFactory;
        $this->_salesModelOrderFactory = $salesModelOrderFactory;
        $this->_epgCustomer = $epgCustomerFactory->create();
        $this->_customerRepository = $customerRepositoryFactory->create();
        $this->_typeOnepage = $typeOnepage;
        $this->_logger = $logger;
    }

  public function execute()
  {
    try {
        $isSSL = ($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure());
        $errors = [];
        $params = $this->_appRequestInterface->getParam('_');
        $paramsArray = explode('|', $params);

        if (empty($paramsArray) || count($paramsArray) != 3) {
            $errors[] = __('The order does not exists.');
            ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgErrors($errors);
            $this->_redirect('easypaymentgateway/payment/fail', ['_secure' => $isSSL]);
        }

        $returnType = $paramsArray[0];
        $cartId = $paramsArray[1];
        $token = $paramsArray[2];

        $tokenType = 'token';
        if ($returnType == 'error') {
            $tokenType = 'error_token';
        } elseif($returnType == 'cancel') {
            $tokenType = 'cancel_token';
        }
        $epg_order = $this->_modelOrderFactory->create()->loadByAttributes(['id_cart' => $cartId, $tokenType => $token]);

        if (empty($epg_order) || empty($epg_order->getIdEpgOrder())) {
            $errors[] = __('The order does not exists.');
            ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgErrors($errors);
            $this->_redirect('easypaymentgateway/payment/fail', ['_secure' => $isSSL]);
            return null;
        }

        $quote = $this->_modelQuoteFactory->create()->load($cartId);
        $quote
            ->setIsActive(1)
            ->setTotalsCollectedFlag(false)
            ->collectTotals()
            ->save();

        ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setQuoteId($cartId);
        ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->replaceQuote($quote);
        ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->setCartWasUpdated(true);
        ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setReturnPayment(true);

        // SUCCESS
        if ($returnType == 'success') {
            try {
              // Load customer
              $epgCustomer = $this->_epgCustomer->loadByAttributes(['epg_customer_id' => $epg_order->getEpgCustomerId()]);
              $customer = ObjectManager::getInstance()->create('Magento\Customer\Model\Customer')->load($epgCustomer->getCustomerId());
              ObjectManager::getInstance()->get('Magento\Customer\Model\Session')->setCustomer($customer);

              ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgChargeData(
                [
                  'chargeResult' => [
                      'transactionId' => $epg_order->getIdTransaction(),
                      'transactionResponse' => json_decode($epg_order->getPaymentDetails(), true)
                  ],
                  'epgOrder' => $epg_order
                ]
              );

              $this->_typeOnepage->saveOrder();

              $this->_redirect('checkout/onepage/success', ['_secure'=> $isSSL]);
              return null;

            } catch (\Exception $e) {
              $errors[] = __('There was an error processing the order: ') . $e->getMessage();
              ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgErrors($errors);
              $this->_redirect('easypaymentgateway/payment/fail', ['_secure'=> $isSSL]);
              return null;
            }

        // STATUS
        } elseif ($returnType == 'status') {
            $orderId = $epg_order->getIdOrder();

            $responseParams = [
                'status' => strtolower($this->_appRequestInterface->getParam('status')),
                'o_status' => strtolower($this->_appRequestInterface->getParam('operation.status')),
                'message' => $this->_appRequestInterface->getParam('message'),
                'operations' => $this->_appRequestInterface->getParam('operations'),
                'o_amount' => $this->_appRequestInterface->getParam('operation.amount'),
                'o_currency' => $this->_appRequestInterface->getParam('operation.currency'),
                'o_details' => $this->_appRequestInterface->getParam('operation.details'),
                'o_merchantTransactionId' => $this->_appRequestInterface->getParam('operation.merchantTransactionId'),
                'o_message' => $this->_appRequestInterface->getParam('operation.message'),
                'o_operationType' => $this->_appRequestInterface->getParam('operation.operationType'),
                'o_payFrexTransactionId' => $this->_appRequestInterface->getParam('operation.payFrexTransactionId'),
                'o_paySolTransactionId' => $this->_appRequestInterface->getParam('operation.paySolTransactionId'),
                'o_paymentSolution' => $this->_appRequestInterface->getParam('operation.paymentSolution')
            ];

            if ($epg_order->getIdTransaction() !== $responseParams['o_merchantTransactionId']) {
                return false;
            }

            // UC-01: EPG status is REDIRECTED && operation status is ERROR or VOIDED && order id is null
            if ($epg_order->getPaymentStatus() == 'REDIRECTED' &&
                ($responseParams['o_status'] == 'ERROR' || $responseParams['o_status'] == 'VOIDED') &&
                empty($orderId)
                ) {
                // Nothing to do
            }

            // UC-02: EPG status is REDIRECTED && operation status is SUCCESS && order id is null
            if ($epg_order->getPaymentStatus() == 'REDIRECTED' &&
                $responseParams['o_status'] == 'SUCCESS' &&
                empty($orderId)
                ) {
                // Order must be created
                $cart = $this->_modelQuoteFactory->create()->load($epg_order->getIdCart());
                $this->processOrder([
                    'transactionId' => $epg_order->getIdTransaction(),
                    'transactionResponse' => $responseParams
                ], $epg_order, $cart);
            }

            // UC-03: EPG status is SUCCESS && operation status is ERROR or VOIDED && order id is not null
            if ($epg_order->getPaymentStatus() == 'SUCCESS' &&
                ($responseParams['o_status'] == 'ERROR' || $responseParams['o_status'] == 'VOIDED') &&
                !empty($orderId)
                ) {

                // If order is not completed must be cancelled
                $order = $this->_salesModelOrderFactory->create()->load($orderId);
                if (empty($order) || empty($order->getId())) {
                    return false;
                }

                if ($order->getState() !== Order::STATE_COMPLETE) {
                    $order->setState(Order::STATE_CANCELED);
                    $order->save();
                }
            }

            // Save transaction data in order
            if (!empty($orderId)) {
                $order = $this->_salesModelOrderFactory->create()->load($orderId);
                $payment = $order->getPayment();
                $transaction = $payment->addTransaction('order');
                $transaction->setTxnId($responseParams['o_payFrexTransactionId']);
                $transaction->setParentTxnId($responseParams['o_merchantTransactionId']);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $responseParams);
                $transaction->setIsClosed(true);
                $transaction->save();
                $payment->save();

                $order->save();
            }

            // Save transaction data in EPG order
            $epg_order->setPaymentStatus($responseParams['o_status']);
            $epg_order->setPaymentDetails(json_encode($responseParams));
            $epg_order->setUpdateAt(date("Y-m-d H:i:s"));
            $epg_order->save();

            return true;

        // CANCEL
        } elseif ($returnType == 'cancel') {
            $errors[] = __('The payment was cancelled.');

        // ERROR
        } elseif ($returnType == 'error') {
            $errors[] = __('There was an error processing your payment.');
        }

        ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgErrors($errors);
        $this->_redirect('easypaymentgateway/payment/fail', ['_secure' => $isSSL]);
        return false;

    } catch(\Exception $e) {
        $errors[] = __('There was an error processing your payment: ') . $e->getMessage();
        ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->setEpgErrors($errors);
        $this->_redirect('easypaymentgateway/payment/fail', ['_secure' => $isSSL]);
        return false;
    }
  }
}
