<?php
namespace EPG\EasyPaymentGateway\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\InvoiceFactory;
use Magento\Store\Model\ScopeInterface;

class Observer
{
    /**
     * @var ScopeConfigInterface
     */
    protected $_configScopeConfigInterface;

    /**
     * @var InvoiceFactory
     */
    protected $_orderInvoiceFactory;

    public function __construct(ScopeConfigInterface $configScopeConfigInterface,
        InvoiceFactory $orderInvoiceFactory)
    {
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
        $this->_orderInvoiceFactory = $orderInvoiceFactory;
    }

    public function checkoutSucccess($observer)
    {

        //ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->unsQuoteId();
        //ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->clear();

        return $this;
    }
}
