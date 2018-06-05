define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/translate',
        'EPG_EasyPaymentGateway/js/model/credit-card-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success'
    ],
    function (Component, $t, paymentData, additionalValidators, redirectOnSuccessAction) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'EPG_EasyPaymentGateway/payment/easypaymentgateway',
                creditCardAccount: 0,
                epgPaymentMethod: '',
            },

            /** @inheritdoc */
            initObservable: function () {
                this._super()
                    .observe([
                        'creditCardAccount',
                        'epgPaymentMethod'
                    ]);

                return this;
            },

            /**
             * Init component
             */
            initialize: function () {
                var self = this;

                this._super();

                this.epgPaymentMethod.subscribe(function (value) {
                    paymentData.epgPaymentMethod = value;

                    if (value != '' && self.validate()) {
                        self.isPlaceOrderActionAllowed(true);
                    }
                });

                this.creditCardAccount.subscribe(function (value) {
                    paymentData.account = value;
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
                        'epg_payment_method': paymentData.epgPaymentMethod,
                        'account': paymentData.account
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

                // Payment methods
                jQuery('.epg-form .epg-payment-methods select').unbind('change');
                jQuery('.epg-form .epg-payment-methods select').on('change', function(event){
                      //event.preventDefault();

                      var blockForm = jQuery('.epg-form .block-form');
                      var paymentMethodURL = blockForm.attr('data-payment-method-url');

                      if (this.value != '') {

                        jQuery('.payment-method-template', blockForm).html('<div class="epg-loading"></div>');

                        jQuery.post({
                            url: paymentMethodURL,
                            cache: false,
                            dataType: 'json',
                            data: {
                                method: this.value
                            },
                            success: function (data) {
                                jQuery('.payment-method-template', blockForm).html('');
                                jQuery('.payment-method-template', blockForm).append(jQuery('<fieldset class="fieldset payment method" id="payment_form_' + self.getCode() +'">' + data.formHtml + '</fieldset>'));
                                self._EPGBindCheckEvents();
                            }
                        });
                      } else {
                        jQuery('.payment-method-template', blockForm).html('');
                      }
                });


                /*
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
                */

            },

            _EPGCheckPayment: function() {
                var radios = jQuery('.epg-form .accounts input[type=radio]');

                radios.each(function(i, el){
                    var item = jQuery(el);

                    if (item.attr('value') == 0 && item.is(':checked')) {
                        jQuery('.epg-form .row.card-holder-name, .epg-form .row.card-number, .epg-form .row.card-expiration').removeClass('hidden');
                        jQuery('.epg-form .row.card-holder-name, .epg-form .row.card-number, .epg-form .row.card-expiration').addClass('validate-required');
                        jQuery('.epg-form .row.card-holder-name, .epg-form .row.card-number, .epg-form .row.card-expiration').removeClass('woocommerce-validated');
                    } else {
                        jQuery('.epg-form .row.card-holder-name, .epg-form .row.card-number, .epg-form .row.card-expiration').addClass('hidden');
                        jQuery('.epg-form .row.card-holder-name, .epg-form .row.card-number, .epg-form .row.card-expiration').removeClass('validate-required');
                    }
                });
            },

            _EPGBindCheckEvents: function() {
              jQuery('.epg-form .accounts input[type=radio]').unbind('click');
              jQuery('.epg-form .accounts input[type=radio]').on('click', function(event){
                  this._EPGCheckPayment();
                  this._EPGDisableAccount();
              });

              this._EPGCheckPayment();
              this._EPGDisableAccount();
              this._EPGSelectLastAccount();
            },

            _EPGSelectLastAccount: function() {
                var radios = jQuery('.epg-form .accounts input[type=radio]');
                var self = this;

                radios.each(function(i, el){
                    var item = jQuery(el);

                    if (i == radios.length - 1) {
                        item.prop('checked', true);
                        self._EPGCheckPayment();
                    }
                });
            },

            _EPGDisableAccount: function() {
              jQuery('.epg-form .accounts .account .disable').unbind('click');
              jQuery('.epg-form .accounts .account .disable').on('click', function(event){
                  event.preventDefault();

                  var self = this;
                  var item = jQuery(event.currentTarget);
                  var confirDisableAccount = jQuery('.epg-form .block-form').attr('data-confirm-disable-account');
                  var disableAccountURL = jQuery('.epg-form .block-form').attr('data-disable-account-url');

                  if(confirm(confirDisableAccount)) {
                      jQuery.post({
                          url: disableAccountURL,
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
