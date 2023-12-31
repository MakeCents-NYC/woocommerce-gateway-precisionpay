<?php

/**
 * Plugin Name:          PrecisionPay Payments for WooCommerce
 * Plugin URI:           https://github.com/MakeCents-NYC/woocommerce-gateway-precisionpay
 * Description:          Accept online bank payments in your WooCommerce store with PrecisionPay.
 * Version:              3.2.0
 * Requires at least:    5.9
 * Requires PHP:         7.2
 * WC requires at least: 3.9
 * WC tested up to:      8.3
 * Author:               PrecisionPay
 * Author URI:           https://www.myprecisionpay.com
 * License:              GPL-3.0
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          wc-gateway-precisionpay
 * Domain Path:          /languages
 */

if (!defined('ABSPATH')) {
  exit;
}

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
  return;
}

// Ajax call to get the merchant API Key
function wc_precisionpay_get_merch_key()
{
  if (!class_exists('WC_PrecisionPay')) return;

  $mcInstance = new WC_PrecisionPay();
  $mcInstance->get_api_key();
}

add_action('wp_ajax_wc_precisionpay_get_merch_key', 'wc_precisionpay_get_merch_key');
add_action('wp_ajax_nopriv_wc_precisionpay_get_merch_key', 'wc_precisionpay_get_merch_key');

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_precisionpay_add_to_gateways($gateways)
{
  $gateways[] = 'WC_PrecisionPay';
  return $gateways;
}
add_filter('woocommerce_payment_gateways', 'wc_precisionpay_add_to_gateways');

/**
 * PrecisionPay Payment Gateway
 *
 * Provides direct bank payments easily.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_PrecisionPay
 * @extends     WC_Payment_Gateway
 * @version     3.2.0
 * @package     WooCommerce/Classes/Payment
 * @author      PrecisionPay
 */
add_action('plugins_loaded', 'wc_precisionpay_init', 11);

function wc_precisionpay_init()
{
  if (!class_exists('WC_PrecisionPay')) :
    define('WC_PRECISIONPAY_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
    define('WC_PRECISIONPAY_PLUGIN_NAME', 'PrecisionPay Payments for WooCommerce');
    define('WC_PRECISIONPAY_VERSION', '3.2.0');

    class WC_PrecisionPay extends WC_Payment_Gateway
    {
      const PRECISION_PAY_BRAND_COLOR = '#F15A29';
      const PRECISION_PAY_TITLE = 'PrecisionPay';
      const ERROR_MESSAGE_EXPIRED_TOKEN = 'Your PrecisionPay token expired, please log back in again';
      const ERROR_MESSAGE_EXPIRED_PLAID_TOKEN = 'Your account authorization has expired, authorizations expire after 30 minutes';

      // Session constants
      const SESSION_STORAGE_PRECISION_PAY = 'mcPrecisionPayData';
      const SESSION_STORAGE_PLAID = 'mcPlaidData';

      // Plaid Environments
      const PLAID_ENV_SANDBOX = 'sandbox';
      const PLAID_ENV_PRODUCTION = 'production';

      // PrecisionPay Environments
      const PP_ENV_SANDBOX = 'sandbox';
      const PP_ENV_PRODUCTION = 'live';


      // API URLs
      const API_URL_PROD = 'https://api.myprecisionpay.com/api';
      const API_URL_STAGING = 'https://staging.mymakecents.com/api';
      const API_URL_LOCAL = 'http://localhost:9000/api';

      // Checkout portal URLs
      const CHECKOUT_PORTAL_URL_PROD = 'https://checkout.myprecisionpay.com';
      const CHECKOUT_PORTAL_URL_STAGING = 'https://staging-checkout.mymakecents.com';
      const CHECKOUT_PORTAL_URL_LOCAL = 'http://localhost:5173';

      // Environments
      const PRECISION_PAY_ENV_PROD = 'production';
      const PRECISION_PAY_ENV_STAGING = 'staging';
      const PRECISION_PAY_ENV_LOCAL = 'local';

      // ** Set the environment - Everything gets set from here ** //
      const PRECISION_PAY_ENV = self::PRECISION_PAY_ENV_PROD;

      // Class Variables
      public $id;
      public $icon;
      public $has_fields;
      public $method_title;
      public $method_description;
      public $title;
      public $description;
      public $enabled;
      private $logo_mark;
      private $loading_icon;
      private $loading_icon_long;
      private $brand_title;
      private $button_title;
      private $enableTestMode;
      private $env;
      private $plaid_env;
      private $api_key;
      private $api_secret;
      private $hasAPIKeys;
      private $api_key_header;
      private $api_url;
      private $checkout_url;

      public function __construct()
      {
        // URLs by environment
        $current_api_url = self::API_URL_PROD;
        $current_checkout_portal_url = self::CHECKOUT_PORTAL_URL_PROD;
        switch (self::PRECISION_PAY_ENV) {
          case self::PRECISION_PAY_ENV_PROD:
            break;
          case self::PRECISION_PAY_ENV_STAGING:
            $current_api_url = self::API_URL_STAGING;
            $current_checkout_portal_url = self::CHECKOUT_PORTAL_URL_STAGING;
            break;
          case self::PRECISION_PAY_ENV_LOCAL:
            $current_api_url = self::API_URL_LOCAL;
            $current_checkout_portal_url = self::CHECKOUT_PORTAL_URL_LOCAL;
            break;
        }

        $this->id                  = 'wc_gateway_precisionpay';
        $this->icon                = WC_PRECISIONPAY_PLUGIN_URL . '/assets/img/precisionpay_logo_2x.png';
        $this->logo_mark           = WC_PRECISIONPAY_PLUGIN_URL . '/assets/img/logo_mark_white.svg';
        $this->loading_icon        = WC_PRECISIONPAY_PLUGIN_URL . '/assets/img/pp_loading_screen_300.png';
        $this->loading_icon_long   = WC_PRECISIONPAY_PLUGIN_URL . '/assets/img/pp_loading_screen_w_text.png';
        $this->has_fields          = true;
        $this->method_title        = self::PRECISION_PAY_TITLE;
        $this->method_description  = __('Welcome to PrecisionPay, the Seconds Amendment payments company.<br />If you already have a merchant account, enter your keys below. If not, visit myprecisionpay.com to apply for a merchant account.', 'wc-gateway-precisionpay');
        $this->brand_title         = self::PRECISION_PAY_TITLE;
        $this->title               = self::PRECISION_PAY_TITLE;
        $this->button_title        = __('Authorize Payment', 'wc-gateway-precisionpay');
        $this->description         = __('Liberate your Second Amendment purchases with our fast, secure and private payment service. It\'s free, and no membership required!', 'wc-gateway-precisionpay');
        $this->enabled             = $this->get_option('enabled');
        $this->enableTestMode      = 'yes' === $this->get_option('enableTestMode'); // Checkbox comes in as yes if checked
        $this->env                 = $this->enableTestMode ? self::PP_ENV_SANDBOX : self::PP_ENV_PRODUCTION;
        $this->plaid_env           = $this->enableTestMode ? self::PLAID_ENV_SANDBOX : self::PLAID_ENV_PRODUCTION;
        $this->api_key             = $this->get_option('api_key');
        $this->api_secret          = $this->get_option('api_secret');
        $this->hasAPIKeys          = $this->api_key && $this->api_secret;
        $this->api_key_header      = json_encode(array('apiKey'    => $this->api_key, 'apiSecret' => $this->api_secret));
        $this->api_url             = $current_api_url;
        $this->checkout_url        = $current_checkout_portal_url;

        // Admin actions
        add_action('admin_init', array($this, 'add_privacy_message'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Customer actions after checkout is complete
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
      }

      /**
       * Gets the privacy message for merchants
       */
      public function add_privacy_message()
      {
        if (!function_exists('wp_add_privacy_policy_content')) {
          return;
        }

        $content = wpautop(
          sprintf(
            wp_kses(
              __('By using this plugin, you consent to storage and use of personal data required to send and receive payments. <a href="%s" target="_blank">View the PrecisionPay Privacy Policy to see what you may want to include in your privacy policy.</a>', 'wc-gateway-precisionpay'),
              array(
                'a' => array(
                  'href'   => array(),
                  'target' => array(),
                ),
              )
            ),
            'https://www.myprecisionpay.com/privacy-policy'
          )
        );

        wp_add_privacy_policy_content(WC_PRECISIONPAY_PLUGIN_NAME, $content);
      }

      /**
       * Get the merchant API Key for use with PrecisionPay Login
       */
      public function get_api_key()
      {
        if (!wp_verify_nonce($_POST['nonce'], "mc_payment_gateway_nonce")) {
          exit("SERVER VERIFICATION ERROR");
        }

        $merchantApiKey = $this->api_key;

        if (!$merchantApiKey) {
          $errorNotice = __('We are unable to process your pay request at the moment. Please refresh the page and try again', 'wc-gateway-precisionpay');
          $this->ajaxFailedResponse($errorNotice);
          return;
        }

        wp_send_json(
          array(
            'result'  => 'success',
            'message' => 'key retrieved',
            'body'    => array(
              'merchantKey'  => $merchantApiKey
            ),
          )
        );
      }

      private function ajaxFailedResponse($message)
      {
        wp_send_json(
          array(
            'result'  => 'failed',
            'message' => $message,
          )
        );
      }

      /**
       * Renders a custom settings page
       */
      function admin_options()
      {
        echo $this->render_admin_styles();
        echo $this->render_header() . $this->render_settings();
      }

      public function render_header()
      {
        return '
        <div class="precisionpay-settings-page-header">
            <div class="top-section">
              <img alt="PrecisionPay" class="precisionpay-logo" width="380" src="' . $this->icon . '"/>
              <h4 class="precisionpay-tagline">' . __('Fast, Secure, Payments, for the Second Amendment Community.', 'wc-gateway-precisionpay') . '</h4>
              <a class="button" target="_blank" href="mailto:support@myprecisionpay.com">'
          . __('Get Help', 'wc-gateway-precisionpay') .
          '</a>
          </span>
            </div>
            <h3>Welcome to PrecisionPay, the Seconds Amendment payments company.</h3>
            <p>If you already have a merchant account, enter your keys below. If not, visit 
            <a target="_blank" href="https://www.myprecisionpay.com">myprecisionpay.com</a> 
            to apply for a merchant account.</p>
          </div>
          ';

        // TODO: add these links once we have the corresponding pages
        //     <a class="button" target="_blank" href="https://myprecisionpay.com/document/woocommerce-precisionpay/">'
        // . __('Documentation', 'wc-gateway-precisionpay') .
        // '</a>
        //     <a class="button" target="_blank" href="https://myprecisionpay.com/document/woocommerce-precisionpay/#get-help">'
        // . __('Get Help', 'wc-gateway-precisionpay') .
        // '</a>
        //     <span class="precisionpay-right-align">
        //       <a target="_blank" href="https://myprecisionpay.com/feature-requests/woocommerce-precisionpay/">'
        // . __('Request a feature', 'wc-gateway-precisionpay') .
        // '</a>
        //       <a target="_blank" href="https://github.com/MakeCents-NYC/woocommerce-gateway-precisionpay/issues/new?assignees=&labels=type%3A+bug&template=bug_report.md">'
        // . __('Submit a bug', 'wc-gateway-precisionpay') .
        // '</a>
      }

      public function render_settings()
      {
        return '
          <table class="form-table">' . $this->generate_settings_html($this->get_form_fields(), false) . '</table>
        ';
      }

      public function render_admin_styles()
      {
        return '
          <style type="text/css">
            .precisionpay-settings-page-header {
              padding-top: 10px;
            }

            .precisionpay-settings-page-header .top-section {
              display: flex;
              gap: 15px;
              align-items: center;
              margin-bottom: 20px;
            }

            .precisionpay-tagline {
              margin: 0;
            }

            .precisionpay-settings-page-header p {
              font-size: 14px;
            }
          </style>
        ';
      }

      /**
       * Initialize Gateway Settings Form Fields
       */
      public function init_form_fields()
      {
        $this->form_fields = apply_filters('wc_precisionpay_form_fields', array(
          'enabled' => array(
            'title'   => __('Enable/Disable', 'wc-gateway-precisionpay'),
            'type'    => 'checkbox',
            'label'   => __('Enable PrecisionPay Payment Gateway', 'wc-gateway-precisionpay'),
            'default' => 'yes'
          ),
          'enableTestMode' => array(
            'title'       => __('Enable Test Mode', 'wc-gateway-precisionpay'),
            'label'       => __('Enable Test Mode', 'wc-gateway-precisionpay'),
            'type'        => 'checkbox',
            'description' => __('Place the payment gateway in test mode to test the plugin without needing to spend any money.', 'wc-gateway-precisionpay'),
            'default'     => '',
            'desc_tip'    => true,
          ),
          'api_key' => array(
            'title'       => __('API Key', 'wc-gateway-precisionpay'),
            'type'        => 'text'
          ),
          'api_secret' => array(
            'title'       => __('API Secret', 'wc-gateway-precisionpay'),
            'type'        => 'password'
          ),
        ));
      }

      /**
       * Needed for custom payment gateway form
       */
      public function payment_fields()
      {
        // Let's require SSL unless the website is in test mode
        if (!$this->enableTestMode && !is_ssl()) {
          echo '<div>
                  <p class="error" style="color: red">
                    ' . __(
            'SSL is required for the PrecisionPay payment gateway. Please enable SSL on your site to continue.',
            'wc-gateway-precisionpay'
          ) . '
                  </p>
                </div>';
          return;
        }

        // We want the business owner to know that the api key and secret are necessary so they don't go live without them
        if (!$this->hasAPIKeys) {
          echo '<p class="error" style="color: red">The ' . $this->brand_title . ' plugin is not fully configured yet and will not work. Please complete the configuration process before going live with this plugin.</p>';
          return;
        }

        if ($this->enableTestMode) {
          $this->description .= ' TEST MODE ENABLED. Use this mode for testing purposes only. You can find the Guest/One-time-pay test credentials in this <a href="https://plaid.com/docs/quickstart/" target="_blank" rel="noopener noreferrer">documentation</a>.';
          $this->description  = trim($this->description);
        }

        if ($this->description != '') {
          // display the description with <p> tags
          echo wpautop(wp_kses_post($this->description));
        }

        // For now we will echo the form, but we could also close PHP tags and print it directly in HTML
        echo '<fieldset id="wc-' . esc_attr($this->id) . '-mc-form" class="wc-precisionpay-form wc-payment-form" style="background:transparent;">
                <div style="display: none;">
                  <input name="precisionpay_public_token" id="precisionpay_public_token" type="hidden">
                  <input name="precisionpay_account_id" id="precisionpay_account_id" type="hidden">
                  <input name="precisionpay_plaid_user_id" id="precisionpay_plaid_user_id" value="" type="hidden">
                  <input name="precisionpay_registered_user_id" id="precisionpay_registered_user_id" value="" type="hidden">
                  <input name="precisionpay_checkout_token" id="precisionpay_checkout_token" value="" type="hidden">
                </div>
                <button id="precisionpay-link-button" class="precisionpay-plaid-link-button" style="background-color: ' . self::PRECISION_PAY_BRAND_COLOR . ';"
                ><img src="' . $this->logo_mark . '" alt="PrecisionPay logo mark">' . $this->button_title . '</button>
                <div class="clear"></div>
                <script src = "' . WC_PRECISIONPAY_PLUGIN_URL . '/assets/js/init.js"></script>
                <div class="clear"><img class="precisionPayLoadingFullPNG" src="' . $this->loading_icon_long . '" /></div>
              </fieldset>
              ';
      }

      /**
       * Fields validation before process_payment() is triggered
       */
      public function validate_fields()
      {
        if (empty($_POST['precisionpay_public_token']) && empty($_POST['precisionpay_checkout_token'])) {
          wc_add_notice('You must authorize the payment before you can complete the order', 'error');
          return false;
        }
        return true;
      }

      /**
       * Custom JS for admin side of the plugin
       */
      public function admin_scripts($hook)
      {
        // Only add to the PrecisionPay woocommerce settings page
        if ('woocommerce_page_wc-settings' !== $hook) {
          return;
        }

        // Only add this script if SSL is not enabled
        if (!is_ssl()) {
          wp_enqueue_script('mc_admin_script_ssl', WC_PRECISIONPAY_PLUGIN_URL . '/assets/js/admin-script-ssl.js');
        }
      }

      /**
       * Custom CSS and JS, in most cases required only when you decided to go with a custom form
       */
      public function payment_scripts()
      {
        // We only need to add styles and scripts on cart/checkout and pay-for-order pages
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
          return;
        }

        // If our payment gateway is disabled, we do not have to enqueue JS
        if ('no' === $this->enabled) {
          return;
        }

        // Add styles
        wp_enqueue_style('precisionpay-styles', WC_PRECISIONPAY_PLUGIN_URL . '/assets/css/main.css');

        // Require SSL unless the website is in a test mode
        if (!$this->enableTestMode && !is_ssl()) {
          return;
        }

        // Using Plaid Link to obtain a token
        wp_enqueue_script('plaid_link', 'https://cdn.plaid.com/link/v2/stable/link-initialize.js', array(), null, true);

        // Scripts to handle authorize payment button
        global $woocommerce;

        $nonce = wp_create_nonce("mc_payment_gateway_nonce");

        // Let's check to see if this is an invoice (if it is then the page will have an order-pay parameter). 
        $order_amount = $woocommerce->cart->total; // In the checkout page we'll use the cart total
        if (isset($_GET["order-pay"])) {
          $order_id = htmlspecialchars($_GET["order-pay"]);
          $order = wc_get_order($order_id);
          $order_amount = $order->get_total();
        }

        wp_enqueue_script(
          'pp-loader-script',
          WC_PRECISIONPAY_PLUGIN_URL . '/assets/js/pp-loader.js',
          array('jquery'),
          WC_PRECISIONPAY_VERSION,
          array('in_footer' => true,)
        );
        wp_enqueue_script(
          'precisionpay-script',
          WC_PRECISIONPAY_PLUGIN_URL . '/assets/js/precisionpay.js',
          array('jquery'),
          WC_PRECISIONPAY_VERSION,
          array('strategy' => 'defer', 'in_footer' => true,)
        );
        wp_localize_script(
          'precisionpay-script',
          'precisionpay_data',
          array(
            'nonce' => $nonce,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'orderAmount' => $order_amount,
            'errorMessageTokenExpired' => __(self::ERROR_MESSAGE_EXPIRED_TOKEN, 'wc-gateway-precisionpay'),
            'errorMessagePlaidTokenExpired' => __(self::ERROR_MESSAGE_EXPIRED_PLAID_TOKEN, 'wc-gateway-precisionpay'),
            'defaultButtonBg' => self::PRECISION_PAY_BRAND_COLOR,
            'defaultButtonTitle' => $this->button_title,
            'logoMark' => $this->logo_mark,
            'loadingImg' => $this->loading_icon,
            'loadingImgLong' => $this->loading_icon_long,
            'plaidEnv' => $this->plaid_env,
            'checkoutPortalURL' => $this->checkout_url,
          )
        );
      }

      /**
       * Once the form is validated we can process the payment. 
       * First we'll process the payment with the PrecisionPay API.
       * Next we'll go through the woocommerce checkout process to finish up.
       */
      public function process_payment($order_id)
      {
        global $woocommerce;
        $order = new WC_Order($order_id);
        $payResponse_body = null;
        $precisionpayCheckoutToken = sanitize_text_field($_POST['precisionpay_checkout_token']);

        if ($precisionpayCheckoutToken) {
          $payResponse_body = $this->pay_with_precisionpay($order, $order_id, $precisionpayCheckoutToken);
        } else {
          $payResponse_body = $this->pay_with_plaid($order, $order_id);
        }

        try {
          if ($payResponse_body && isset($payResponse_body->message) && $payResponse_body->message == 'success') {
            $order->add_order_note(__('Customer successfully paid through PrecisionPay payment gateway', 'wc-gateway-precisionpay'));
            wc_reduce_stock_levels($order_id);
            $order->payment_complete();
            $woocommerce->cart->empty_cart();

            // Redirect to thank you page
            return array(
              'result'   => 'success',
              'redirect' => $this->get_return_url($order),
            );
          } else {
            // Transaction was not successful ...Add notice to the cart
            $errorNotice = __('An unknown error occured while attempting to charge your account', 'wc-gateway-precisionpay');
            $responseErrorMessage = $payResponse_body ? $payResponse_body->detail : null;
            if ($responseErrorMessage) {
              if ($responseErrorMessage === 'PrecisionPay token invalid') {
                // Handle expired token
                $errorNotice = __(self::ERROR_MESSAGE_EXPIRED_TOKEN, 'wc-gateway-precisionpay');
              } else if ($responseErrorMessage === self::ERROR_MESSAGE_EXPIRED_PLAID_TOKEN) {
                $errorNotice = __(self::ERROR_MESSAGE_EXPIRED_PLAID_TOKEN, 'wc-gateway-precisionpay');
              } else {
                $errorNotice = '';
                if (is_array($responseErrorMessage)) {
                  $errorNotice .= $payResponse_body->detail[0];
                } else {
                  $errorNotice .= $payResponse_body->detail;
                }
              }
            }
            wc_add_notice($errorNotice, 'error');
            // Also add note to the order for wp-admin reference
            $order->add_order_note('Error: ' . $errorNotice);
          }
        } catch (Exception $e) {
          wc_add_notice($e->getMessage(), 'error');
          return;
        }
      }

      private function validate_hidden_field($field, $message = '')
      {
        if (!preg_match("/^[0-9a-zA-Z\-]+$/", $field)) {
          $error_message = __('Error processing payment.', 'wc-gateway-precisionpay') . ' ' . $message;
          throw new Exception($error_message);
        }
      }

      private function pay_with_precisionpay($order, $order_id, $precisionpayCheckoutToken)
      {
        // Validate field first
        $this->validate_hidden_field($precisionpayCheckoutToken, __('Invalid checkout token.', 'wc-gateway-precisionpay'));

        $orderNumber = $this->get_order_number($order, $order_id);
        $paymentData = array(
          'precisionPayToken' => $precisionpayCheckoutToken,
          'amount' => floatval($order->get_total()),
          'order'  => strval($orderNumber),
          'env'    => $this->env,
        );

        $payResponse = $this->api_post('/checkout/pay', $paymentData);

        if (is_wp_error($payResponse)) {
          throw new Exception(__('Error processing payment.', 'wc-gateway-precisionpay'));
        }

        if (empty($payResponse['body'])) {
          throw new Exception(__('Error processing payment at this time. Please try again later.', 'wc-gateway-precisionpay'));
        }

        // Retrieve the body's resopnse if no top level errors found
        $payResponse_body = json_decode(wp_remote_retrieve_body($payResponse));

        return $payResponse_body;
      }

      private function pay_with_plaid($order, $order_id)
      {
        $publicToken = sanitize_text_field($_POST['precisionpay_public_token']);
        $accountId = sanitize_text_field($_POST['precisionpay_account_id']);
        $precisionpay_user_id = sanitize_text_field($_POST['precisionpay_plaid_user_id']);
        $precisionpay_registered_user_id = sanitize_text_field($_POST['precisionpay_registered_user_id']);

        // Make sure the plaid link returned what we needed
        if (!$publicToken || !$accountId || !$precisionpay_user_id) {
          throw new Exception(__('Your bank account is not linked. Please link your account.', 'wc-gateway-precisionpay'));
        }

        // validate the input:
        $this->validate_hidden_field($publicToken, __('Invalid token.', 'wc-gateway-precisionpay'));
        $this->validate_hidden_field($accountId, __('Invalid id.', 'wc-gateway-precisionpay'));
        $this->validate_hidden_field($precisionpay_user_id, __('Invalid Plaid user id.', 'wc-gateway-precisionpay'));

        // This is optional and only gets set if the customer logs into our Customer Portal but does one-time-pay with Plaid
        if ($precisionpay_registered_user_id) {
          $this->validate_hidden_field($precisionpay_registered_user_id, __('Invalid PrecisionPay Account.', 'wc-gateway-precisionpay'));
        }

        $orderNumber = $this->get_order_number($order, $order_id);

        $paymentData = array(
          'public_token'     => $publicToken,
          'account_id'       => $accountId,
          'first_name'       => $order->get_billing_first_name(),
          'last_name'        => $order->get_billing_last_name(),
          'business_name'    => $order->get_billing_company(), //? $order->get_billing_company() : 'PrecisionPay Default',
          'email'            => $order->get_billing_email(),
          'one_time_user_id' => $precisionpay_user_id, // string
          'external_user_id' => $precisionpay_registered_user_id, // We need to pass this, but it will usually be an empty string
          'amount'           => floatval($order->get_total()), // number (int or decimal)
          'order'            => strval($orderNumber), // <- Needs to come in as a string
          'env'              => $this->env, // Lets API know if we are in sandbox or live mode
        );

        $payResponse = $this->api_post('/checkout/one-time-payment', $paymentData);

        if (is_wp_error($payResponse)) {
          throw new Exception(__('Error processing payment.', 'wc-gateway-precisionpay'));
        }

        if (empty($payResponse['body'])) {
          throw new Exception(__('Error processing payment at this time. Please try again later.', 'wc-gateway-precisionpay'));
        }

        // Retrieve the body's resopnse if no top level errors found
        $payResponse_body = json_decode(wp_remote_retrieve_body($payResponse));

        return $payResponse_body;
      }

      private function api_post($endpoint, $paymentData)
      {
        $referer = $_SERVER['HTTP_REFERER'];
        $response = wp_remote_post($this->api_url . $endpoint, array(
          'method'  => 'POST',
          'headers' => array(
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'X-Application-Access' => $this->api_key_header,
            'Referer' => $referer
          ),
          'body'    => json_encode($paymentData), // http_build_query($payload),
          'timeout' => 90,
        ));

        return $response;
      }

      private function get_order_number($order, $order_id)
      {
        $orderNumber = $order_id;
        $orderMetaOrderNumber = $order->get_meta('_order_number');

        // Check for custom order number
        if ($orderMetaOrderNumber) {
          $orderNumber = $orderMetaOrderNumber;
        }

        return $orderNumber;
      }

      /**
       * Output for the order received page.
       */
      public function thankyou_page()
      {
        echo '
          <script type="text/javascript">
          function completePrecisionPayExperience($) {
            // Remove session PrecisionPay session storage variables
            sessionStorage.removeItem("' . self::SESSION_STORAGE_PRECISION_PAY . '");
            sessionStorage.removeItem("' . self::SESSION_STORAGE_PLAID . '");
          }

          jQuery(document).ready(function completePrecisionPay() {
            completePrecisionPayExperience(jQuery);
          });
          </script>
        ';
      }

      /**
       * Add content to the WC emails.
       *
       * @access public
       * @param WC_Order $order
       * @param bool $sent_to_admin
       * @param bool $plain_text
       */
      public function email_instructions($order, $sent_to_admin, $plain_text = false)
      {
        // if ($this->instructions && !$sent_to_admin && 'precisionpay' == $order->payment_method && $order->has_status('on-hold')) {
        //   echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        // }
      }
    } // end WC_PrecisionPay class
  endif;
}
