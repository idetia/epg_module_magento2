<?php

namespace EPG\EasyPaymentGateway\Block;

use EPG\EasyPaymentGateway\Helper\Data as HelperData;
use EPG\EasyPaymentGateway\Model\OrderFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info as BlockInfo;

class Info extends BlockInfo
{
    /**
     * @var OrderFactory
     */
    protected $_modelOrderFactory;

    /**
     * @var HelperData
     */
    protected $_helperData;

    public function __construct(Context $context,
        OrderFactory $modelOrderFactory,
        HelperData $helperData,
        array $data = [])
    {
        $this->_modelOrderFactory = $modelOrderFactory;
        $this->_helperData = $helperData;

        parent::__construct($context, $data);
    }

    protected function _prepareSpecificInformation($transport = null)
    {
      if (null !== $this->_paymentSpecificInformation) {
        return $this->_paymentSpecificInformation;
      }

      $data = [];
      $account = null;

      if (null === $this->_paymentSpecificInformation) {
          if (null === $transport) {
              $transport = new \Magento\Framework\DataObject();
          } elseif (is_array($transport)) {
              $transport = new \Magento\Framework\DataObject($transport);
          }

          if (!empty(ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgPaymentInfo())) {
              $account = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgPaymentInfo();
          } else {
              $order = $this->getInfo()->getOrder();
              if (!empty($order) && !empty($order->getId())) {
                  $epgOrder = $this->_modelOrderFactory->create()->getByOrderId($order->getId());

                  if (!empty($epgOrder) && !empty($epgOrder->getIdEpgOrder())) {
                      // Call cashier
                      $cashier = $this->_helperData->apiCashier();
                      $account = null;
                      foreach ($cashier['accounts'] as $itemAccount) {
                        if ($epgOrder->getIdAccount() == $itemAccount['accountId']) {
                            $account = $itemAccount;
                            break;
                        }
                      }
                  }
              }
          }

          if (!empty($account)) {
              foreach ($account['values'] as $value) {
                  $accountInfo[$value['name']] = $value['value'];
              }

              if (isset($account['paymentMethod'])) {
                  $data[(string)__('Payment Method')] = $account['paymentMethod'];
              }

              if (isset($accountInfo['cardType']) && isset($accountInfo['maskedCardNumber'])) {
                $data[(string)__('Card type')] = isset($accountInfo['cardType'])?$accountInfo['cardType']:'';
                $data[(string)__('Card number')] = isset($accountInfo['maskedCardNumber'])?$accountInfo['maskedCardNumber']:'';
              }
          }

          $transport->setData(array_merge($data, $transport->getData()));

          $this->_paymentSpecificInformation = $transport;
      }

      return $this->_paymentSpecificInformation;
    }

}
