<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use EPG\EasyPaymentGateway\Model\CustomerFactory;
use EPG\EasyPaymentGateway\Model\Type\OnepageFactory;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\CustomerFactory as ModelCustomerFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\LayoutFactory;
use Magento\Store\Model\StoreManagerInterface;

class Fail extends AbstractPayment
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
     * @var LayoutFactory
     */
    protected $_viewLayoutFactory;

    public function __construct(Context $context, 
        CustomerFactory $modelCustomerFactory, 
        ModelCustomerFactory $customerModelCustomerFactory, 
        OnepageFactory $typeOnepageFactory, 
        Cart $modelCart, 
        StoreManagerInterface $modelStoreManagerInterface, 
        RequestInterface $appRequestInterface, 
        LayoutFactory $viewLayoutFactory)
    {
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_appRequestInterface = $appRequestInterface;
        $this->_viewLayoutFactory = $viewLayoutFactory;

        parent::__construct($context, $modelCustomerFactory, $customerModelCustomerFactory, $typeOnepageFactory, $modelCart);
    }

  public function execute()
  {
      $isSSL = ($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure());
      $errors = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgErrors();
      if (empty($errors)) {
          $this->_redirect('checkout/cart', ['_secure'=> $isSSL]);
      }

      $this->loadLayout();
      $block = $this->_viewLayoutFactory->create()->createBlock('Magento\Framework\View\Element\Template','easypaymentgateway',['template' => 'easypaymentgateway/fail.phtml']);
      $this->_viewLayoutFactory->create()->getBlock('content')->append($block);
      $this->renderLayout();
      ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->unsEpgErrors();
  }
}
