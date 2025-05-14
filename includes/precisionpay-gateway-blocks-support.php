<?php
// Do We need a namespace?
// namespace PrecisionPay\PrecisionPayPaymentsForWooCommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

final class PrecisionPay_Gateway_Blocks_Support extends AbstractPaymentMethodType {
  private $gateway;

  protected $name = 'wc_gateway_precisionpay'; // payment gateway id

  public function initialize() {
    // get payment gateway settings
    $this->settings = get_option("woocommerce_{$this->name}_settings", []); // Seems like PHP is moving to [] from array()...

    // you can also initialize your payment gateway here
    $gateways = WC()->payment_gateways->payment_gateways();
    $this->gateway  = $gateways[$this->name];
  }

  public function is_active() {
    return ! empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
  }

  public function get_payment_method_script_handles() {

    wp_register_script(
      'wc-precisionpay-blocks-integration',
      plugin_dir_url(__DIR__) . 'build/index.js',
      array(
        'wc-blocks-registry',
        'wc-settings',
        'wp-element',
        'wp-html-entities',
      ),
      null, // TODO: changed?
      true
    );

    return array('wc-precisionpay-blocks-integration');
  }

  public function get_payment_method_data() {
    return array(
      // almost the same way:
      // 'title'     => isset( $this->settings[ 'title' ] ) ? $this->settings[ 'title' ] : 'Default value';
      // if $this->gateway was initialized on line 15
      // 'supports'  => array_filter($this->gateway->supports, [$this->gateway, 'supports']),

      // example of getting a public key
      // 'publicKey' => $this->get_publishable_key(),

      'id'          => $this->gateway->id,
      'title'       => $this->gateway->title,
      'description' => $this->gateway->description,
      'logo'        => $this->gateway->logo,
      'buttonTitle' => $this->gateway->button_title,
      'buttonLogo'  => $this->gateway->logo_white,
      'brandColor'  => PrecisionPay_Payments_For_WC::PRECISION_PAY_BRAND_COLOR,
      'pluginUrl'   => PRCSNPY_PLUGIN_URL,
      'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports'])
    );
  }

  //private function get_publishable_key() {
  //	$test_mode   = ( ! empty( $this->settings[ 'testmode' ] ) && 'yes' === $this->settings[ 'testmode' ] );
  //	$setting_key = $test_mode ? 'test_publishable_key' : 'publishable_key';
  //	return ! empty( $this->settings[ $setting_key ] ) ? $this->settings[ $setting_key ] : '';
  //}

}
