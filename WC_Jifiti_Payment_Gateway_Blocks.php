<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Jifiti_Payment_Gateway_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'jifitipayment';// your payment gateway name

    public function initialize() {
        $this->settings = get_option( 'jobpw_options', [] );
        $this->gateway = new WC_Jifiti_Payment_Gateway();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

        wp_register_script(
            'jifitipayment-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {            
            wp_set_script_translations( 'jifitipayment-blocks-integration');
            
        }
        return [ 'jifitipayment-blocks-integration' ];
    }

    public function get_payment_method_data() {
        $_SESSION["isNewCheckout"] = true;
        return [
            'title' => $this->gateway->title,
            'icon' => $this->gateway->icon
        ];
    }

}
?>