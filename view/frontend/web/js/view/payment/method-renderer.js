define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'easypaymentgateway',
                component: 'EPG_EasyPaymentGateway/js/view/payment/method-renderer/easypaymentgateway'
            }
        );

        return Component.extend({});
    }
);
