define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/translate',
        'EPG_EasyPaymentGateway/js/model/credit-card-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success'
    ],
    function (Component, $t, creditCardData, additionalValidators, redirectOnSuccessAction) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'EPG_EasyPaymentGateway/payment/easypaymentgateway',
                creditCardAccount: '',
                creditCardHoldName: '',
                creditCardNumber: '',
                creditCardCvn: '',
                creditCardExpMonth: '',
                creditCardExpYear: '',
            },

            /** @inheritdoc */
            initObservable: function () {
                this._super()
                    .observe([
                        'creditCardAccount',
                        'creditCardHoldName',
                        'creditCardNumber',
                        'creditCardCvn',
                        'creditCardExpMonth',
                        'creditCardExpYear'
                    ]);

                return this;
            },

            /**
             * Init component
             */
            initialize: function () {
                var self = this;

                this._super();

                creditCardData.expMonth = this.getEpgData().months[0];
                creditCardData.expYear = this.getEpgData().years[0];

                this.creditCardAccount.subscribe(function (value) {
                    creditCardData.account = value;
                });

                this.creditCardHoldName.subscribe(function (value) {
                    creditCardData.holdName = value;
                });

                this.creditCardNumber.subscribe(function (value) {
                    creditCardData.cardNumber = value;
                });

                this.creditCardCvn.subscribe(function (value) {
                    creditCardData.cardCvn = value;
                });

                this.creditCardExpMonth.subscribe(function (value) {
                    creditCardData.expMonth = value;
                });

                this.creditCardExpYear.subscribe(function (value) {
                    creditCardData.expYear = value;
                });
            },

            /**
             * Get data
             * @returns {Object}
             */
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'account': creditCardData.account,
                        'card_holder_name': creditCardData.holdName,
                        'card_number': creditCardData.cardNumber,
                        'card_cvn': creditCardData.cardCvn,
                        'card_expiry_month': creditCardData.expMonth,
                        'card_expiry_year': creditCardData.expYear,
                    }
                };
            },

            getCode: function() {
                return 'easypaymentgateway';
            },

            isActive: function() {
                return true;
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

                    if (initial && i == 0) {
                      item.prop('checked', true);
                    }

                    if (item.attr('value') == 0 && item.is(':checked')) {
                        jQuery('.epg-form .field.card-holder-name, .epg-form .field.card-number, .epg-form .field.card-expiration').removeClass('hidden');
                        jQuery('.epg-form .field.card-holder-name input, .epg-form .field.card-number input, .epg-form .field.card-expiration select').addClass('required-entry');
                    } else {
                        jQuery('.epg-form .field.card-holder-name, .epg-form .field.card-number, .epg-form .field.card-expiration').addClass('hidden');
                        jQuery('.epg-form .field.card-holder-name input, .epg-form .field.card-number input, .epg-form .field.card-expiration select').removeClass('required-entry');
                    }

                    if (item.is(':checked')) {
                      creditCardData.account = item.attr('value');
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
            },

            // Override original placeOrder
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function (response) {
                                // If return a redirectURL
                                if (response['responseJSON'] && response['responseJSON']['message']) {
                                  try {
                                      var result = JSON.parse(response['responseJSON']['message']);
                                      if (result['redirectURL']) {
                                        jQuery('.messages').css('display', 'none');
                                        window.location.href = result['redirectURL'];
                                      }
                                  } catch (e) {}
                                }

                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                            function (response) {
                                self.afterPlaceOrder();

                                if (self.redirectAfterPlaceOrder) {
                                    redirectOnSuccessAction.execute();
                                }
                            }
                        );

                    return true;
                }

                return false;
            }

        });
    }
);
