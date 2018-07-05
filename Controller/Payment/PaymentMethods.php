<?php

namespace EPG\EasyPaymentGateway\Controller\Payment;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;

class PaymentMethods extends AbstractPayment
{
    /**
     * @var Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    public function __construct(Context $context,
        PageFactory $resultPageFactory)
    {
        parent::__construct($context);

        $this->_resultPageFactory = $resultPageFactory;
    }

  public function execute(){

      $resultPage = $this->_resultPageFactory->create();
      $html = $resultPage->getLayout()
              ->createBlock('EPG\EasyPaymentGateway\Block\Form')
              ->setTemplate('EPG_EasyPaymentGateway::form.phtml')
              ->toHtml();

      die(json_encode([
        'html' => $html
      ]));
  }
}
