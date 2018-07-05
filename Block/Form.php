<?php

namespace EPG\EasyPaymentGateway\Block;

use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Form as BlockForm;
use EPG\EasyPaymentGateway\Helper\Data as HelperData;
use EPG\EasyPaymentGateway\Model\ApiFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use EPG\EasyPaymentGateway\Model\Form as EPGForm;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\App\RequestInterface;

class Form extends BlockForm
{

  /**
   * @var ApiFactory
   */
  protected $_modelApiFactory;

  /**
   * @var HelperData
   */
  protected $_helperData;

  /**
   * @var StoreManagerInterface
   */
  protected $_modelStoreManagerInterface;

  /**
   * @var ScopeConfigInterface
   */
  protected $_configScopeConfigInterface;

  /**
   * @var AssetRepository
   */
  protected $_assetRepo;

  /**
   * @var RequestInterface
   */
  protected $_appRequestInterface;

  public $accounts = [];
  public $formHtml = '';
  public $selectedPaymentMethod = null;
  public $icons = '';
  public $isSandbox = false;
  public $isSSL = false;

  public function __construct(
    Context $context,
    ApiFactory $modelApiFactory,
    HelperData $helperData,
    StoreManagerInterface $modelStoreManagerInterface,
    ScopeConfigInterface $configScopeConfigInterface,
    AssetRepository $assetRepo,
    RequestInterface $appRequestInterface
  )
	{
		parent::__construct($context);

    $this->_modelApiFactory = $modelApiFactory;
    $this->_helperData = $helperData;
    $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
    $this->_configScopeConfigInterface = $configScopeConfigInterface;
    $this->_assetRepo = $assetRepo;
    $this->_appRequestInterface = $appRequestInterface;

    $pMethod = (isset($_POST['method'])?(string)$_POST['method']:null);

    if (empty($pMethod)) {
      return;
    }

    // Call cashier
    $cashierData = $this->_helperData->apiCashier();
    if (empty($cashierData)) {
      return;
    }

    // Method selected info (name + operation)
    $methodInfo = explode('|', $pMethod);
    if (count($methodInfo) != 2) {
      return;
    }

    $paymentMethod = null;
    foreach ($cashierData['paymentMethods'] as $method) {
      if ($methodInfo[0] == $method['name']) {
        $paymentMethod = $method;
        break;
      }
    }

    $accounts = [];
    foreach ($cashierData['accounts'] as $account) {
      if (strtolower($methodInfo[0]) != strtolower($account['paymentMethod'])) {
          continue;
      }
      $accounts[] = $account;
    }

    $form = new EPGForm($paymentMethod);
    $this->accounts = $accounts;
    $this->formHtml = $form->html();
    $this->selectedPaymentMethod = $methodInfo[0];
    $this->icons = $this->creditCardIcons();
    $this->isSandbox = $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_sandbox', ScopeInterface::SCOPE_STORE);
    $this->isSSL = (($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure()) || $this->isSandbox == '1');

	}

  /**
   * Credit card icons
   */
  private function creditCardIcons()
  {
      $logosURL = $this->_assetRepo->getUrl('EPG_EasyPaymentGateway::images') . '/';
      $channels = explode(',', $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_channels', ScopeInterface::SCOPE_STORE));

      $icons = [
          'visa' => in_array('visa', $channels)?$logosURL . 'visa.png':false,
          'master_card' => in_array('master_card', $channels)?$logosURL . 'master_card.png':false,
          'maestro' => in_array('maestro', $channels)?$logosURL . 'maestro.png':false,
          'american_express' => in_array('american_express', $channels)?$logosURL . 'american_express.png':false,
      ];

      $icons_str = '';
      foreach ($icons as $icon) {
          if (empty($icon)) {
              continue;
          }
          $icons_str .= '<img src="'.$icon . '" class="icon"/>';
      }

      return '<div class="epg-icons">'.$icons_str.'</div>';
  }
}
