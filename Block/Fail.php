<?php
namespace EPG\EasyPaymentGateway\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session as CheckoutSession;

class Fail extends Template
{
  protected $_checkoutSession;

  public function __construct(
    Context $context,
    CheckoutSession $checkoutSession
  )
	{
		parent::__construct($context);

    $this->_checkoutSession = $checkoutSession;
	}

  public function getEpgErrors()
  {
      $errors = $this->_checkoutSession->getEpgErrors();
      $this->_checkoutSession->unsEpgErrors();

      return $errors;
  }
}
