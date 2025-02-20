function usePrecisionPayPaymentGateway($) {
  // ONLY RUN IF PLAID IS AVAILABLE
  if (typeof Plaid === 'undefined') {
    console.log('...MISSING PLAID...');
    return;
  }

  var SESSION_STORAGE_PLAID = 'mcPlaidData';
  var SESSION_STORAGE_PRECISION_PAY = 'mcPrecisionPayData';
  var SESSION_STORAGE_PRECISION_PAY_CC = 'mcPrecisionPayCCData';

  // From woocommerce-gateway-precisionpay.php
  var precisionPayNonce = precisionpay_data.precisionPayNonce;
  var ajaxUrl = precisionpay_data.ajaxUrl;
  var orderAmount = precisionpay_data.orderAmount;
  var errorMessageTokenExpired = precisionpay_data.errorMessageTokenExpired;
  var errorMessagePlaidTokenExpired = precisionpay_data.errorMessagePlaidTokenExpired;
  var errorMessageNoValidAccounts = precisionpay_data.errorMessageNoValidAccounts;
  var errorMessageInsufficientFunds = precisionpay_data.errorMessageInsufficientFunds;
  var defaultButtonBg = precisionpay_data.defaultButtonBg;
  var defaultCCButtonBg = precisionpay_data.defaultCCButtonBg;
  var defaultButtonTitle = precisionpay_data.defaultButtonTitle;
  var defaultCCButtonTitle = precisionpay_data.defaultCCButtonTitle;
  var logoMark = precisionpay_data.logoMark;
  var ccIcon = precisionpay_data.ccIcon;
  var loadingImg = precisionpay_data.loadingImg;
  var loadingImgLong = precisionpay_data.loadingImgLong;
  var plaidEnv = precisionpay_data.plaidEnv;
  var checkoutPortalURL = precisionpay_data.checkoutPortalURL;

  var mc_merchantNonce = '';

  function init() {
    // IF ALREADY REGISTERED OR LINKED BUT NOT YET REGISTERED SET BUTTON AS LINKED
    if (sessionStorage.getItem(SESSION_STORAGE_PLAID)) {
      // If the user already linked but hasn't registered yet, this saves the data on refresh
      var mcPlaidData = JSON.parse(sessionStorage.getItem(SESSION_STORAGE_PLAID));
      addDataToHiddenFields(mcPlaidData);
      updateUIToSuccess();
    } else if (sessionStorage.getItem(SESSION_STORAGE_PRECISION_PAY)) {
      var precisionPayToken = JSON.parse(sessionStorage.getItem(SESSION_STORAGE_PRECISION_PAY));
      addPPDataToHiddenField(precisionPayToken);
      updateUIToSuccess();
    } else if (sessionStorage.getItem(SESSION_STORAGE_PRECISION_PAY_CC)) {
      var precisionPayCCData = JSON.parse(sessionStorage.getItem(SESSION_STORAGE_PRECISION_PAY_CC));
      addPPCCDataToHiddenField(precisionPayCCData);
      updateUIToSuccess(true);
    }

    // We are now listening for submit of the form to add our own loader if user is using PrecisionPay
    $('.woocommerce-checkout').on('submit', function () {
      setPrecisionPayLoader($, loadingImg, loadingImgLong);
    }); // .addEventListener('submit', setLoader);

    // launch checkout portal on PP button click
    $('#precisionpay-link-button').click(authorizePayment);

    // launch Elavon lightbox if credit cards enables
    if ($('#precisionpay-cc-button').length > 0) {
      $('#precisionpay-cc-button').click(initCCPayment);
    }

    // keep an eye out for certain errors we need to handle
    $(document.body).on('checkout_error', function () {
      var errorText = $('.woocommerce-error')
        .find('li')
        .first()
        .text()
        .replace(/(\n|\t)/gm, ''); // For Older WooCommerce
      if (!errorText) {
        errorText = $('.is-error .wc-block-components-notice-banner__content')
          .text()
          .replace(/(\n|\t)/gm, ''); // For WooCommerce v8+
      }
      if (
        errorText &&
        (errorText === errorMessageTokenExpired ||
          errorText === errorMessagePlaidTokenExpired ||
          errorText === errorMessageNoValidAccounts ||
          errorText === errorMessageInsufficientFunds ||
          errorText.match(/Credit card error/))
      ) {
        resetButtonUI();
        removePPDataFromHiddenField();
        sessionStorage.removeItem(SESSION_STORAGE_PRECISION_PAY);
        removePlaidDataFromHiddenField();
        sessionStorage.removeItem(SESSION_STORAGE_PLAID);
        removePPCCDataToHiddenField();
        sessionStorage.removeItem(SESSION_STORAGE_PRECISION_PAY_CC);
      }
    });
  }

  function updateUIToSuccess(isCC = false) {
    if (isCC) {

      $('#precisionpay-cc-button')
        .html('✓ Payment Authorized')
        .css({
          backgroundColor: '#00cc00',
          color: 'white',
        })
        .prop('disabled', true);
      $('#precisionpay-link-button')
        .css({
          backgroundColor: '#aca29e',
          color: 'white'
        })
        .prop('disabled', true);
    } else {
      $('#precisionpay-link-button')
        .html('✓ Payment Authorized')
        .css({
          backgroundColor: '#00cc00',
          color: 'white',
        })
        .prop('disabled', true);
      $('#precisionpay-cc-button')
        .css({
          backgroundColor: '#859698',
          color: '#cdd7d7'
        })
        .prop('disabled', true);
    }
  }

  function resetButtonUI() {
    $('#precisionpay-link-button')
      .html('<img src="' + logoMark + '" alt="PrecisionPay logo mark" />' + defaultButtonTitle)
      .css({
        backgroundColor: defaultButtonBg,
      })
      .prop('disabled', false);
    if($('#precisionpay-cc-button').length > 0) {
      $('#precisionpay-cc-button')
        .html('<img src="' + ccIcon + '" alt="credit card icon" />' + defaultCCButtonTitle)
        .css({
          backgroundColor: defaultCCButtonBg,
        })
        .prop('disabled', false);
    }
  }

  function addDataToHiddenFields(pd) {
    $('#precisionpay_public_token').val(pd.public_token);
    $('#precisionpay_account_id').val(pd.accountId);
    $('#precisionpay_plaid_user_id').val(pd.precisionPayPlaidUserId);
    $('#precisionpay_registered_user_id').val(pd.precisionPayRegisteredUserId); // Used if a user does one time payment after logging in
  }

  function addPPDataToHiddenField(precisionPayToken) {
    $('#precisionpay_checkout_token').val(precisionPayToken);
  }

  function addPPCCDataToHiddenField(ccData) {
    $('#precisionpay_creditcard_token').val(ccData.ssl_token);
    $('#precisionpay_creditcard_exp_date').val(ccData.ssl_exp_date);
    $('#precisionpay_creditcard_oar_data').val(ccData.ssl_oar_data);
    $('#precisionpay_creditcard_ps2000_data').val(ccData.ssl_ps2000_data);
    $('#precisionpay_creditcard_approval_code').val(ccData.ssl_approval_code);
  }

  function removePPDataFromHiddenField() {
    $('#precisionpay_checkout_token').val('');
  }

  function removePlaidDataFromHiddenField() {
    $('#precisionpay_public_token').val('');
    $('#precisionpay_account_id').val('');
    $('#precisionpay_plaid_user_id').val('');
    $('#precisionpay_registered_user_id').val('');
  }
  function removePPCCDataToHiddenField() {
    $('#precisionpay_creditcard_token').val('');
    $('#precisionpay_creditcard_exp_date').val('');
    $('#precisionpay_creditcard_oar_data').val('');
    $('#precisionpay_creditcard_ps2000_data').val('');
    $('#precisionpay_creditcard_approval_code').val('');
  }

  function handlePlaidData(plaidData) {
    // Add data to hidden fields
    addDataToHiddenFields(plaidData);
    // Also add to session storage in case we get "refreshed"
    sessionStorage.setItem(SESSION_STORAGE_PLAID, JSON.stringify(plaidData));
    updateUIToSuccess();
  }

  function handlePPData(precisionPayToken) {
    addPPDataToHiddenField(precisionPayToken);
    sessionStorage.setItem(SESSION_STORAGE_PRECISION_PAY, JSON.stringify(precisionPayToken));
    updateUIToSuccess();
  }

  function handlePPCCData(ccData) {
    addPPCCDataToHiddenField(ccData);
    sessionStorage.setItem(SESSION_STORAGE_PRECISION_PAY_CC, JSON.stringify(ccData));
    updateUIToSuccess(true);
  }

  function authorizePayment(e) {
    e.preventDefault();

    let data = {
      precisionPayNonce: precisionPayNonce,
      action: 'prcsnpy_get_merch_nonce',
    };

    $('#payment').block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6,
      },
    });

    $.ajax({
      type: 'POST',
      url: ajaxUrl,
      data: data,
      success: function (data) {
        if (data && data.body) {
          // Remove any errors
          $('.payment_box.payment_method_wc_gateway_precisionpay .error').remove();
          mc_merchantNonce = data.body.merchantNonce;
          $('#payment').unblock();
          openPrecisionPay(mc_merchantNonce, orderAmount);
        } else {
          if (data.result === 'failed') {
            displayError('', data.message);
          } else {
            console.log('Whoops. Error.', data);
          }
          $('#payment').unblock();
        }
      },
      error: function (err) {
        console.log(err);
        if (err && err.message) {
          displayError('', err.message);
        }
        $('#payment').unblock();
      },
    });
  }

  function initCCPayment(e) {
    e.preventDefault();

    var zipCode = $('#billing_postcode').val();
    var address1 = $('#billing_address_1').val();
    const data = {
      amount: orderAmount,
      zipCode: zipCode,
      address1: address1,
      precisionPayNonce: precisionPayNonce,
      action: 'prcsnpy_get_cc_token',
    };

    $('#payment').block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6,
      },
    });

    $.ajax({
      type: 'POST',
      url: ajaxUrl,
      data: data,
      success: function (data) {
        if (data && data.body) {
          // Remove any errors
          $('.payment_box.payment_method_wc_gateway_precisionpay .error').remove();
          mc_ccToken = data.body.ccToken;
          $('#payment').unblock();
          openElavonLightbox(mc_ccToken);
        } else {
          if (data.result === 'failed') {
            displayError('', data.message);
          } else {
            console.log('Whoops. Error.', data);
          }
          $('#payment').unblock();
        }
      },
      error: function (err) {
        console.log(err);
        if (err && err.message) {
          displayError('', err.message);
        }
        $('#payment').unblock();
      },
    });
  }

  function openPrecisionPay(merchantNonce, amount) {
    var mcPaymentWindow = $('.mc-payment-portal');

    function removePPEventListener() {
      window.removeEventListener('message', handleCompletedLogin);
    }

    function handleCompletedLogin(event) {
      var message = event.data.message;

      switch (message) {
        case 'PrecisionPay::success':
          var precisionPayToken = event.data.precisionPayToken;
          if (precisionPayToken) {
            handlePPData(precisionPayToken);
          } else {
            var plaidData = event.data.plaidData;
            if (plaidData) {
              handlePlaidData(plaidData);
            }
          }

          mcPaymentWindow.remove();
          removePPEventListener();
          break;
        case 'PrecisionPay::failed':
          var error = event.data.error_message;
          displayError('', error);
          removePPEventListener();
          break;
        case 'PrecisionPay::canceled':
          mcPaymentWindow.hide();
          removePPEventListener();
          break;
      }
    }

    if (mcPaymentWindow.length) {
      mcPaymentWindow.show();
      removePPEventListener(); // clear any existing listeners first
      window.addEventListener('message', handleCompletedLogin);
    } else {
      mcPaymentWindow = $(`
      <div class="mc-payment-portal mc-overlay">
        <iframe class="mc-payment-window" src="${checkoutPortalURL}/checkout-login/${encodeURI(
        merchantNonce
      )}/amount/${encodeURI(amount)}/env/${plaidEnv}" title="Log in to PrecisionPay"></iframe>
      </div>
      `);
      var mcPaymentStyles = `
      <style>
        .mc-overlay {
          display:none;
          background: rgba(255,255,255,0.5);
          position: fixed;
          top: 0vh;
          left: 0vw;
          width: 100vw;
          height: 100vh;
          z-index: 524287;
          transition: 0.5s;
        }
        iframe.mc-payment-window { 
          position: fixed;
          top: 0vh;
          left: 0vw;
          border-width: 0px;
          width: 100vw;
          height: 100vh;
        }
      </style>
      `;
      $('body').append(mcPaymentStyles);
      $('body').append(mcPaymentWindow);
      $(mcPaymentWindow).show();

      window.addEventListener('message', handleCompletedLogin);
    }
  }

  function openElavonLightbox(mc_ccToken) {
    var paymentFields = { ssl_txn_auth_token: mc_ccToken };
    var callback = {
      onError: function (error) {
        var errorMessage = error && error.errorName ? error.errorName : 'there was an error processing your card at this time.';
        displayError('Error', errorMessage);
      },
      onCancelled: function () {
        // displayError("cancelled", "");
      },
      onDeclined: function (response) {
        var errorMessage = response && response.errorMessage ? response.errorMessage : 'your card was declined.';
        displayError('Declined', errorMessage);
      },
      onApproval: function (response) {
        pertinentData = {
          ssl_token: response.ssl_token,
          ssl_exp_date: response.ssl_exp_date,
          ssl_oar_data: response.ssl_oar_data,
          ssl_ps2000_data: response.ssl_ps2000_data,
          ssl_approval_code: response.ssl_approval_code,
        };
        handlePPCCData(pertinentData)
      }
    };
    PayWithConverge.open(paymentFields, callback);

  }

  function displayError(status, msg) {
    formattedMsg = msg.replace('<h3>', '').replace('</h3>', ''); // remove headings
    var errorMessage = status ? status + ': ' + formattedMsg : formattedMsg;
    var mcErrorMessage = '<p class="error" style="color: red">' + errorMessage + '</p>';
    $('.payment_box.payment_method_wc_gateway_precisionpay').prepend(mcErrorMessage);
  }

  return {
    init: init,
  };
}
