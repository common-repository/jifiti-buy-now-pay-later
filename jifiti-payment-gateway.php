<?php
/*
 * Plugin Name: Jifiti - Buy Now Pay Later
 * Description: Jifiti is a platform for connecting lenders, merchants and customers in order to provide point-of-sale financing both in-store and online.
 * Author: Jifiti
 * Author URI: https://www.jifiti.com/
 * Version: 1.3.0
 */

const JIFITI_ORDER_STATUS_AUTHORIZED = "authorized";
const JIFITI_ORDER_STATUS_VOID = "voided";
const JIFITI_ORDER_STATUS_CAPTURED = "captured";
const JIFITI_ORDER_STATUS_REFUND = "refunded";

add_filter('woocommerce_payment_gateways', 'jifiti_add_gateway_class');
function jifiti_add_gateway_class($gateways)
{
	$gateways[] = 'WC_Jifiti_Payment_Gateway';
	return $gateways;
}


add_action('plugins_loaded', 'jifiti_init_gateway_class', 0);
function jifiti_init_gateway_class()
{
	if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class 

    include(plugin_dir_path(__FILE__) . 'WC_Jifiti_Payment_Gateway.php');
}

/**
 * Function to declare compatibility with cart_checkout_blocks feature 
*/
function declare_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

// Hook the function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type' );

/**
 * Function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'WC_Jifiti_Payment_Gateway_Blocks.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new WC_Jifiti_Payment_Gateway_Blocks );
        }
    );
}

register_activation_hook(__FILE__, 'jifiti_payment_activate');

function jifiti_payment_activate()
{	
	if (class_exists( 'woocommerce' )) {
		jifiti_payment_initiate_db();
	}
	else {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( __( 'Please install and activate WooCommerce plugin before active our plugin.', 'woocommerce' ), 'Plugin dependency check', array( 'back_link' => true ) );
	}
}

function jifiti_payment_initiate_db()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'jifiti_pay_transaction';
  
	$prepareQuery = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );
    if (!$wpdb->get_var($prepareQuery) == $table_name) {

        $sql = "CREATE TABLE $table_name(
            transaction_id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            initiate_flow_request text ,
            initiate_flow_response text,
            initiate_flow_request_at date ,
            order_id varchar(255),
            auth_id varchar(255),
            status varchar(255),
            token varchar(255),
            reference_id varchar(255),
			is_committed TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT now() NOT NULL,
            UNIQUE KEY id(transaction_id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function jifiti_add_transaction($transaction)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'jifiti_pay_transaction';

	$wpdb->insert(
		$table_name,
		array(
			'order_id' => $transaction['order_id'],
			'initiate_flow_request' => $transaction['initiate_flow_request'],
			'initiate_flow_response' => $transaction['initiate_flow_response'],
			'initiate_flow_request_at' => $transaction['initiate_flow_request_at'],
			'status' => $transaction['status'],
			'token' => $transaction['token'],
			'reference_id' => $transaction['reference_id']
		)
	);
}

function jifiti_update_transaction($transaction)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'jifiti_pay_transaction';

	$wpdb->update(
		$table_name,
		array(
			'initiate_flow_request' => $transaction['initiate_flow_request'],
			'initiate_flow_response' => $transaction['initiate_flow_response'],
			'initiate_flow_request_at' => $transaction['initiate_flow_request_at'],
			'status' => $transaction['status'],
			'token' => $transaction['token'],
			'reference_id' => $transaction['reference_id']
		),
		array('order_id' => $transaction['order_id'])
	);
}

function jifiti_get_transaction_by_token($token)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'jifiti_pay_transaction';
	$result = $wpdb->get_results("SELECT * FROM {$table_name} WHERE token = '{$token}'", OBJECT);

	return json_decode(json_encode($result[0]), true);
}

function jifiti_get_transaction_by_order_id($order_id)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'jifiti_pay_transaction';
	$result = $wpdb->get_results("SELECT * FROM {$table_name} WHERE order_id = '{$order_id}'", OBJECT);

	if (!empty($result[0])) {
		return json_decode(json_encode($result[0]), true);
	}

	return [];
}

function jifiti_update_transaction_auth_id($order_id, $status, $auth_id)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'jifiti_pay_transaction';

	return $wpdb->update($table_name, array('auth_id' => $auth_id, 'status' => $status), array('order_id' => $order_id));
}

function jifiti_update_transaction_is_commit($order_id, $is_committed)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'jifiti_pay_transaction';

	return $wpdb->update($table_name, array('is_committed' => $is_committed), array('order_id' => $order_id));
}

function jifiti_update_transaction_status($order_id, $status)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'jifiti_pay_transaction';

	return $wpdb->update($table_name, array('status' => $status), array('order_id' => $order_id));
}

function jifiti_admin_error_notice($error_message) {
	add_action('admin_notices', function() use ($error_message) {
		?>
		<div class="error notice">
			<p><?php _e( $error_message, 'jifitipayment' ); ?></p>
		</div>
		<?php
	});
}

function jifiti_admin_notice($error_message) {
	add_action( 'admin_notices', function() use ($error_message) {
		?>
		<div class="notice notice-success">
			<p><?php _e( $error_message, 'jifitipayment' ); ?></p>
		</div>
		<?php
	});
}

function jifiti_update_order_payment_status($order, $status = '')
{
	if ($order->meta_exists("jifiti_payment_status")) {
		$order->update_meta_data("jifiti_payment_status", $status);
	}
	else {
		$order->add_meta_data("jifiti_payment_status", $status);
	}
	$order->save_meta_data();
}

function jifiti_get_order_payment_status($order)
{
	if ($order->meta_exists("jifiti_payment_status")) {
		return $order->get_meta("jifiti_payment_status");
	}
	return null;
}

function jifiti_get_payment_instance()
{
	foreach ( WC()->payment_gateways->payment_gateways() as $payment_gateway_id => $payment_gateway_item ) {
		if ($payment_gateway_id == "jifitipayment") {
			return $payment_gateway_item;
		}
	}
	return null;
}

include_once "Jifiti_Initiate_Flow.php";
include_once "Helper/Jifiti_Request_Client.php";
include_once "Helper/Jifiti_Config.php";
include_once "Jifiti_Commit_Purchase.php";
include_once "Jifiti_Offer_By_Price_Widget.php";
include_once "Jifiti_Offer_By_Price_Widget_Settings.php";
include_once "Jifiti_Order_Actions.php";

function set_default_js_widget_url()
{
	if ($_POST['jobpw_options']['jobpw_pm_environment'] == "prod") {
		$_POST['jobpw_options']['jobpw_js_widget_url'] = "https://toolbox.jifiti.com/sdk/jifiti-multi-iff.js";
	}
	else if ($_POST['jobpw_options']['jobpw_pm_environment'] == "uat") {
		$_POST['jobpw_options']['jobpw_js_widget_url'] = "https://toolbox-uat.jifiti.com/sdk/jifiti-multi-iff.js";
	}
	else {
		$_POST['jobpw_options']['jobpw_js_widget_url'] = "https://toolbox-dev.jifiti.com/sdk/jifiti-multi-iff.js";
	}
}

function jifiti_settings_submit_catch_js_widget_url() {
	if (!empty($_GET['page']) && !empty($_GET['tab']) && !empty($_GET['section'])) {
		if ($_GET['page'] == "wc-settings" && $_GET['tab'] == "checkout" && $_GET['section'] == "jifitipayment") {
			$jobpw_options = get_option("jobpw_options");
	
			if (!empty($_POST['woocommerce_jifitipayment_allowed_product_types'])) {
				if (!in_array("SplitPayments", $_POST['woocommerce_jifitipayment_allowed_product_types']) && $jobpw_options['jobpw_template_name'] == "spbar") {
					$jobpw_options['jobpw_template_name'] = 'info';
				}
			}
			else if ($jobpw_options['jobpw_template_name'] == "spbar") {
				$jobpw_options['jobpw_template_name'] = 'info';
			}
	
			update_option("jobpw_options", $jobpw_options);
		}
	}
	if (!empty($_POST['jobpw_options']) && !empty($_POST['jobpw_options']['jobpw_pm_auth_token'])) {
		$jifiti_payment_gateway = jifiti_get_payment_instance();
		$config = new Jifiti_Config($jifiti_payment_gateway);
		$req_client = new Jifiti_Request_Client($_POST['jobpw_options']['jobpw_pm_auth_token']);
		$pm_environment = $_POST['jobpw_options']['jobpw_pm_environment'];
		$environment = '';
		if($pm_environment == 'prod'){
			$environment = 'production';
		}
		elseif($pm_environment == 'uat'){
			$environment = 'staging';
		}
		$branding_results = $req_client->callGet($config->get_base_url(strtolower($_POST['jobpw_options']['jobpw_region']), $environment) . WC_Jifiti_Payment_Gateway::JIFITI_BRANDING_API_ENDPOINT); 

		if ($branding_results['status'] == 200) {
			if (!empty($branding_results['data'])) {
				$branding_data = $branding_results['data'];
				if (!($branding_data === null && json_last_error() !== JSON_ERROR_NONE)) {
					if (!empty($branding_data['JSWidgetUrl'])) {
						$_POST['jobpw_options']['jobpw_js_widget_url'] = $branding_data['JSWidgetUrl'];
					}
					else {
						set_default_js_widget_url();
					}
				}
				else {
					set_default_js_widget_url();
				}
			}
			else {
				set_default_js_widget_url();
			}
		}
		else {
			set_default_js_widget_url();
		}
	}
}
add_action('admin_init', 'jifiti_settings_submit_catch_js_widget_url');
