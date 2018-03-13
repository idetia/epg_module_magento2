<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\PageFactory;
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
     * @var PageFactory
     */
    protected $_resultPageFactory;

    public function __construct(Context $context,
        StoreManagerInterface $modelStoreManagerInterface,
        RequestInterface $appRequestInterface,
        PageFactory $resultPageFactory)
    {
        parent::__construct($context);
              
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_appRequestInterface = $appRequestInterface;
        $this->_resultPageFactory = $resultPageFactory;
    }

  public function execute()
  {
      $isSSL = ($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure());
      $errors = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgErrors();

      if (empty($errors)) {
          $this->_redirect('checkout/cart', ['_secure'=> $isSSL]);
          return null;
      }

      $resultPage = $this->_resultPageFactory->create();
      $block = $resultPage->getLayout()->getBlock('easypaymentgateway_payment_fail');

      return $resultPage;
  }
}
