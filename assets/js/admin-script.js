(function ($) {
  $(document).ready(function () {
    maybeDisplayCCFields();

    function maybeDisplayCCFields() {
      function displayCCFields() {
        $('#woocommerce_wc_gateway_precisionpay_merchantID').closest('tr').show();
        $('#woocommerce_wc_gateway_precisionpay_merchantUserID').closest('tr').show();
        $('#woocommerce_wc_gateway_precisionpay_merchantPinCode').closest('tr').show();
      }
      function hideCCFields() {
        $('#woocommerce_wc_gateway_precisionpay_merchantID').closest('tr').hide();
        $('#woocommerce_wc_gateway_precisionpay_merchantUserID').closest('tr').hide();
        $('#woocommerce_wc_gateway_precisionpay_merchantPinCode').closest('tr').hide();
      }
      var ccEnabledCheckBox = $('#woocommerce_wc_gateway_precisionpay_enableCreditCards');
      if (ccEnabledCheckBox.length === 0) {
        return;
      }
      if (ccEnabledCheckBox[0].checked) {
        displayCCFields();
      } else {
        hideCCFields();
      }

      ccEnabledCheckBox.click(function () {
        console.log('ccEnabledCheckBox clicked!');
        console.log('ccEnabledCheckBox[0].checked =', ccEnabledCheckBox[0].checked);
        if (ccEnabledCheckBox[0].checked) {
          displayCCFields();
        } else {
          hideCCFields();
        }
      });
    }
  });
})(jQuery);
