<?php

namespace EPG\EasyPaymentGateway\Block\Form;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Form;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class Easypaymentgateway extends Form
{
    /**
     * @var ScopeConfigInterface
     */
    protected $_configScopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    protected $_modelStoreManagerInterface;

    /**
     * @var RequestInterface
     */
    protected $_appRequestInterface;

    /**
     * @var UrlInterface
     */
    protected $_frameworkUrlInterface;

    public function __construct(Context $context, 
        ScopeConfigInterface $configScopeConfigInterface, 
        StoreManagerInterface $modelStoreManagerInterface, 
        RequestInterface $appRequestInterface, 
        UrlInterface $frameworkUrlInterface, 
        array $data = [])
    {
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_appRequestInterface = $appRequestInterface;
        $this->_frameworkUrlInterface = $frameworkUrlInterface;

        parent::__construct($context, $data);
    }

  protected function _construct()
  {
    parent::_construct();

    $_isSandbox = $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_sandbox', ScopeInterface::SCOPE_STORE);
    $this->assign('_allowedCards', $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_channels', ScopeInterface::SCOPE_STORE));
    $this->assign('_isSandbox', $_isSandbox);
    $this->assign('_isSSL', (($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure()) || ($_isSandbox && $_isSandbox == '1')));
    $this->assign('_baseSkinUrl', $this->_frameworkUrlInterface->getBaseUrl(Store::URL_TYPE_SKIN) . 'frontend/base/default/easypaymentgateway/images/');

    $this->setTemplate('EPG_EasyPaymentGateway::easypaymentgateway/form/payment.phtml');
  }

  /**
   * Retrieve credit card expire months
   *
   * @return array
   */
  public function getMonths()
  {
      $months = [];
      for ($i = 1; $i <= 12; $i++) {
          $months[] = sprintf("%02d", $i);
      }
      return $months;
  }

  /**
   * Retrieve credit card expire years
   *
   * @return array
   */
  public function getYears()
  {
      return range(date('y'), date('y') + 10);
  }

}
