<?php

namespace EPG\EasyPaymentGateway\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'easypaymentgateway';

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
    protected $_assetRepo;

    /**
     * @var AssetRepository
     */
    protected $_moduleReader;

    public function __construct(
        ScopeConfigInterface $configScopeConfigInterface,
        StoreManagerInterface $modelStoreManagerInterface,
        RequestInterface $appRequestInterface,
        UrlInterface $frameworkUrlInterface,
        AssetRepository $assetRepo)
    {
        $this->_configScopeConfigInterface = $configScopeConfigInterface;
        $this->_modelStoreManagerInterface = $modelStoreManagerInterface;
        $this->_appRequestInterface = $appRequestInterface;
        $this->_frameworkUrlInterface = $frameworkUrlInterface;
        $this->_assetRepo = $assetRepo;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $isSandbox = $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_sandbox', ScopeInterface::SCOPE_STORE);
        $isSSL = (($this->_modelStoreManagerInterface->getStore()->isFrontUrlSecure() && $this->_appRequestInterface->isSecure()) || ($isSandbox && $isSandbox == '1'));

        return [
            'payment' => [
                self::CODE => [
                    'allowedCards' => $this->_configScopeConfigInterface->getValue('payment/easypaymentgateway/epg_channels', ScopeInterface::SCOPE_STORE),
                    'isSandbox' => $isSandbox,
                    'isSSL' => $isSSL,
                    'moduleImagesUrl' => $this->_assetRepo->getUrl('EPG_EasyPaymentGateway::images') . '/',
                    'months' => $this->getMonths(),
                    'years' => $this->getYears(),
                    'accounts' => \Magento\Framework\App\ObjectManager::getInstance()->get('EPG\EasyPaymentGateway\Helper\Data')->getAccounts(),
                    'removeAccountUrl' =>  $this->_frameworkUrlInterface->getUrl('easypaymentgateway/payment/removeAccount', ['_secure' => $isSSL])
                ]
            ]
        ];
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
