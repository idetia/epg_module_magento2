<?php
namespace EPG\EasyPaymentGateway\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Model\ResourceModel\TransactionFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\InvoiceFactory;
use Magento\Store\Model\ScopeInterface;

abstract class AbstractObserver
{
    /**
     * @var ScopeConfigInterface
     */
    protected $_configScopeConfigInterface;

    /**
     * @var InvoiceFactory
     */
    protected $_orderInvoiceFactory;

    /**
     * @var TransactionFactory
     */
    protected $_resourceModelTransactionFactory;

    public function __construct(ScopeConfigInterface $configScopeConfigInterface, 
        InvoiceFactory $orderInvoiceFactory, 
        TransactionFactory $resourceModelTransactionFactory)
    {
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
        $this->_orderInvoiceFactory = $orderInvoiceFactory;
        $this->_resourceModelTransactionFactory = $resourceModelTransactionFactory;

    }

    public function addAutoloader()
    {
        require_once (Mage::getBaseDir() . '/vendor/autoload.php');
        return $this;
    }

    public function checkoutSucccess($observer)
    {

        //ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->unsQuoteId();
        //ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->clear();

        return $this;
    }

    public function invoiceCompleteOrder($observer)
    {
        if (ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getQuote()->getPayment()->getMethod() !== 'easypaymentgateway') {
            return $this;
        }

        $createInvoice = $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_create_invoice', ScopeInterface::SCOPE_STORE) == '1';
        if(!$createInvoice) {
            return $this;
        }

        $order = $observer->getEvent()->getOrder();

        $orders = $this->_orderInvoiceFactory->create()->getCollection()
                        ->addAttributeToFilter('order_id', ['eq'=>$order->getId()]);
        $orders->getSelect()->limit(1);

        if ((int)$orders->count() !== 0) {
            return $this;
        }

        try {
            $invoice = ObjectManager::getInstance()->create('sales/service_order', $order)->prepareInvoice();

            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->register();

            $invoice->getOrder()->setCustomerNoteNotify(true);
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->sendEmail(true, '');
            $order->addStatusHistoryComment(__('Automatically invoiced on payment.'), false);

            $transactionSave = $this->_resourceModelTransactionFactory->create()
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transactionSave->save();

        } catch (\Exception $e) {
            $order->addStatusHistoryComment(__('Exception occurred during invoiceCompleteOrder action. Exception message: ') . $e->getMessage(), false);
            $order->save();
        }

        return $this;
    }
}
