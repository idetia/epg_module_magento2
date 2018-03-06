<?php

namespace EPG\EasyPaymentGateway\Block\Info;

use EPG\EasyPaymentGateway\Helper\Data as HelperData;
use EPG\EasyPaymentGateway\Model\OrderFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;

class Easypaymentgateway extends Info
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
      if (null !== $this->_paymentSpecificInformation)
      {
        return $this->_paymentSpecificInformation;
      }
      $data = [];
      $account = null;

      if (!empty(ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgPaymentInfo())) {
          $account = ObjectManager::getInstance()->get('Magento\Checkout\Model\Session')->getEpgPaymentInfo();
      } else {
          $order = $this->getInfo()->getOrder();
          if (!empty($order) && !empty($order->getId())) {
              $epgOrder = $this->_modelOrderFactory->create()->getByOrderId($order->getId());

              if (!empty($epgOrder) && !empty($epgOrder->getIdEpgOrder())) {
                  $account = $this->_helperData->getAccountById($epgOrder->getIdAccount());
              }
          }
      }

      if (!empty($account)) {
          foreach ($account['values'] as $value) {
              $accountInfo[$value['name']] = $value['value'];
          }

          $data[__('Card type')] = $accountInfo['cardType'];
          $data[__('Card number')] = $accountInfo['maskedCardNumber'];
      }

      $transport = parent::_prepareSpecificInformation($transport);

      if (empty($transport)) {
          return;
      }

      return $transport->setData(array_merge($data, $transport->getData()));
    }

}
