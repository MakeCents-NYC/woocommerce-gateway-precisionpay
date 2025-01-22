function completePrecisionPayExperience($) {
  var sessionStoragePrecisionPay = precisionpay_data.sessionStoragePrecisionPay;
  var sessionStoragePlaid = precisionpay_data.sessionStoragePlaid;
  var sessionStoragePrecisionPayCC = precisionpay_data.sessionStoragePrecisionPayCC;
  // Remove Plaid & PrecisionPay session storage data
  sessionStorage.removeItem(sessionStoragePrecisionPay);
  sessionStorage.removeItem(sessionStoragePlaid);
  sessionStorage.removeItem(sessionStoragePrecisionPayCC);
}

jQuery(document).ready(function completePrecisionPay() {
  completePrecisionPayExperience(jQuery);
});
