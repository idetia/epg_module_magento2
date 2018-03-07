define(
    [
        'Magento_Checkout/js/view/payment/default',
        'require',
        'jquery',
        'epg-front-js'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'EPG_EasyPaymentGateway/payment/easypaymentgateway'
            }
        });
    }
);
