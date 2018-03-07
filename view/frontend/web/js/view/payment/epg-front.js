!(function($){
  jQuery.noConflict();

  var _EPGInitFunctions = function() {
      console.log(window.checkoutConfig);

      if (!jQuery('.epg-form').get(0)) {
        return;
      }

      // Accounts management
      jQuery('.epg-form .accounts input[type=radio]').on('click', function(event){

          _EPGCheckPayment();

      });
      _EPGCheckPayment();

      // Disable account
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
                          _EPGSelectLastAccount();
                      }
                  }
              });
          }
      });

  };

  var _EPGCheckPayment = function() {
      var radios = jQuery('.epg-form .accounts input[type=radio]');

      radios.each(function(i, el){
          var item = jQuery(el);

          if (item.attr('value') == 0 && item.is(':checked')) {
              jQuery('.epg-form .row.card-holder-name, .epg-form .row.card-number, .epg-form .row.card-expiration').removeClass('hidden');
              jQuery('.epg-form .row.card-holder-name input, .epg-form .row.card-number input, .epg-form .row.card-expiration select').addClass('required-entry');
          } else {
              jQuery('.epg-form .row.card-holder-name, .epg-form .row.card-number, .epg-form .row.card-expiration').addClass('hidden');
              jQuery('.epg-form .row.card-holder-name input, .epg-form .row.card-number input, .epg-form .row.card-expiration select').removeClass('required-entry');
          }
      });
  };

  var _EPGSelectLastAccount = function() {
      var radios = jQuery('.epg-form .accounts input[type=radio]');

      radios.each(function(i, el){
          var item = jQuery(el);

          if (i == radios.length - 1) {
              item.prop('checked', true);
              _EPGCheckPayment();
          }
      });
  };

  jQuery(document).ready(function(){
      _EPGInitFunctions();
  });

})(jQuery);
