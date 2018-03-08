define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/translate',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Payment/js/model/credit-card-validation/validator'        
    ],
    function (Component, $t) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'EPG_EasyPaymentGateway/payment/easypaymentgateway'
            },

            getCode: function() {
                return 'easypaymentgateway';
            },

            getEpgData: function() {
              return window.checkoutConfig.payment.easypaymentgateway;
            },

            getAccountInfo: function(account) {
              var accountInfo = [];
              for (var aValue of account['values']) {
                  accountInfo[aValue['name']] = aValue['value'];
              }
              return accountInfo;
            },

            _EPGInitFunctions: function() {

                var self = this;

                if (!jQuery('.epg-form').get(0)) {
                  return;
                }

                // Accounts management
                jQuery('.epg-form .accounts input[type=radio]').unbind('click');
                jQuery('.epg-form .accounts input[type=radio]').on('click', function(event){
                    self._EPGCheckPayment(false);
                });
                self._EPGCheckPayment(true);

                // Disable account
                jQuery('.epg-form .accounts .account .disable').unbind('click');
                jQuery('.epg-form .accounts .account .disable').on('click', function(event){
                    event.preventDefault();

                    var item = jQuery(event.currentTarget);

                    if(confirm(jQuery('.epg-form .block-form').attr('data-confirm-disable-account'))) {
                        jQuery.post({
                            url: jQuery('.epg-form .block-form').attr('data-disable-account-url'),
                            cache: false,
                            dataType: 'json',
                            data: {
                                account_id: item.closest('.account').attr('data-account-id')
                            },
                            success: function (data) {
                                if (data.result) {
                                    item.closest('li').remove();
                                    self._EPGSelectLastAccount();
                                }
                            }
                        });
                    }
                });

            },

            _EPGCheckPayment: function(initial) {
                var radios = jQuery('.epg-form .accounts input[type=radio]');
                radios.each(function(i, el){
                    var item = jQuery(el);

                    if (item.attr('value') == 0 && item.is(':checked')) {
                        jQuery('.epg-form .field.card-holder-name, .epg-form .field.card-number, .epg-form .field.card-expiration').removeClass('hidden');
                        jQuery('.epg-form .field.card-holder-name input, .epg-form .field.card-number input, .epg-form .field.card-expiration select').addClass('required-entry');
                    } else {
                        jQuery('.epg-form .field.card-holder-name, .epg-form .field.card-number, .epg-form .field.card-expiration').addClass('hidden');
                        jQuery('.epg-form .field.card-holder-name input, .epg-form .field.card-number input, .epg-form .field.card-expiration select').removeClass('required-entry');
                    }

                    if (initial && i == 0) {
                      item.prop('checked', true);
                    }
                });
            },

            _EPGSelectLastAccount: function() {
                var radios = jQuery('.epg-form .accounts input[type=radio]');
                var self = this;

                radios.each(function(i, el){
                    var item = jQuery(el);

                    if (i == radios.length - 1) {
                        item.prop('checked', true);
                        self._EPGCheckPayment(false);
                    }
                });
            },

            getInfo: function() {
                return [
                    {'name': 'Credit Card Type', value: ''},
                    {'name': 'Credit Card Number', value: ''}
                ];
            },

            validate: function() {
                var form = jQuery('#' + this.getCode() + '-form');
                return form.validation() && form.validation('isValid');
            }
        });
    }
);
