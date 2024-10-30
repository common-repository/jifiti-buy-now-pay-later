<?php

use Automattic\Jetpack\Constants;

class WC_Jifiti_Payment_Gateway extends WC_Payment_Gateway
{
    const JIFITI_BRANDING_API_ENDPOINT = "applications/v2/LenderBranding/GetLenderDetails";
    const JIFITI_REFUND_API_ENDPOINT = "purchases/v2/:AuthId/Refund";

    private static $calling_api_error = false;
    public $sort_order;
    public $environment;
    public $location;
    public $auth_token;
    public $store_identifier;
    public $merchant_id;
    public $merchant_location;
    public $completion_method;
    public $allowed_product_types;
    public $customer_experience_setting_section_title;
    public $window_behavior;
    public $commit_purchase;
    public $order_id;
    public $enable_offline_payment;
    
    /**
     * Class constructor, more about it in Step 3
     */
    public function __construct()
    {
        $this->id = 'jifitipayment'; // payment gateway plugin ID
        $this->icon = $this->get_option('payment_logo'); // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields = true; // in case you need a custom credit card form
        $this->method_title = 'Cred payment';
        $this->method_description = 'Jifiti is a platform for connecting lenders, merchants and customers in order to provide point-of-sale financing both in-store and online.'; // will be displayed on the options page

        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->enable_offline_payment = $this->get_option('enable_offline_payment');
        $this->sort_order = $this->get_option('sort_order');
        $this->environment = $this->get_option('environment');
        $this->location = $this->get_option('location');
        $this->auth_token = $this->get_option('auth_token');
        $this->store_identifier = $this->get_option('store_identifier ');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_location = $this->get_option('merchant_location');
        $this->completion_method = $this->get_option('completion_method');
        $this->allowed_product_types = $this->get_option('allowed_product_types');
        $this->customer_experience_setting_section_title = $this->get_option('customer_experience_setting_section_title');
        $this->description = $this->get_option('description');
        $this->window_behavior = $this->get_option('window_behavior');
        $this->commit_purchase = $this->get_option('commit_purchase');
        $this->supports[] = 'refunds';

        if (empty(session_id()) && !headers_sent()) {
            session_start();
        }
        
        if (!empty($_SESSION["jifit_error_message"])) {
            if(!empty($_SESSION["isNewCheckout"])){
                add_action('woocommerce_check_cart_items', function ($arg) {
                    if (!empty($_SESSION["jifit_error_message"])){
                        wc_add_notice($_SESSION["jifit_error_message"], 'error');
                        unset($_SESSION["jifit_error_message"]);
                    }
                });
            }
            else{
                add_action('woocommerce_before_checkout_form', function ($arg) {
                    if (!empty($_SESSION["jifit_error_message"])){
                        wc_add_notice($_SESSION["jifit_error_message"], 'error');
                        unset($_SESSION["jifit_error_message"]);
                    }
                });
            }
        }

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        $posted_settings = $this->get_post_data();

        if (!empty($posted_settings['woocommerce_jifitipayment_auth_token'])) {
            $wc_payment_settings = [];
            foreach ($posted_settings as $key => $value) {
                $wc_payment_settings[str_replace("woocommerce_jifitipayment_", "", $key)] = $value;
            }

            $config = new Jifiti_Config((object)$wc_payment_settings);
            $req_client = new Jifiti_Request_Client($wc_payment_settings['auth_token']);
            $admin_error_message = null;

            $branding_results = $req_client->callGet($config->get_base_url() . self::JIFITI_BRANDING_API_ENDPOINT);
            
            if ($branding_results['status'] == 200) {
                if (!empty($branding_results['data'])) {
                    $branding_data = $branding_results['data'];

                    if (!($branding_data === null && json_last_error() !== JSON_ERROR_NONE)) {
                        $this->update_option("title", $branding_data['Title']);
                        $this->update_option("description", $branding_data['Description']);
                        $this->update_option("payment_logo", $branding_data['Logo']);
                    }
                    else {
                        $admin_error_message = "Branding API Data format is wrong, please try again later.";
                    }
                }
                else {
                    $admin_error_message = "Branding API return empty data, please try again later.";
                }
            }
            else {
                $admin_error_message = "Error With branding API, please try again later";
            }

            if (!WC_Jifiti_Payment_Gateway::$calling_api_error && $admin_error_message) {
                WC_Jifiti_Payment_Gateway::$calling_api_error = true;
                jifiti_admin_error_notice($admin_error_message);
            }
        }
    }

    /**
     * Plugin options, we deal with it in Step 3 too
     */
    public function init_form_fields()
    {

        $this->form_fields = array(

            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'default'     => 'Buy Now ,Pay later',
                'custom_attributes' => array('readonly' => 'readonly')
            ),

            'enabled' => array(
                'title'       => 'Enabled',
                'label'       => 'Enable Jifiti Gateway pay',
                'id'       => 'jifiti_enabled',
                'default'  => 'no',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select',
                'css'      => 'min-width: 350px;',
                'options'  => array(
                    'yes'        => __('Yes', 'woocommerce'),
                    'no' => __('No', 'woocommerce'),
                ),
            ),

            'enable_offline_payment' => array(
                'title'       => 'Enable Offline Payment',
                'label'       => 'Enable Jifiti Offline Payment Flow',
                'id'       => 'jifiti_enable_offline_payment',
                'default'  => 'no',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select',
                'css'      => 'min-width: 350px;',
                'options'  => array(
                    'yes'        => __('Yes', 'woocommerce'),
                    'no' => __('No', 'woocommerce'),
                ),
            ),

            'sort_order' => array(
                'title'       => 'Sort Order',
                'type'        => 'text',
                'default'     => '1',
            ),

            'required_settings_section_title' => array(
                'title' => __('Required Settings', 'woocommerce'),
                'type'  => 'title',
                'id'    => 'required_settings_section_title',
            ),

            'environment' => array(
                'title'       => 'Environment',
                'id'       => 'jifiti_environment',
                'default'  => 'staging',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select',
                'css'      => 'min-width: 350px;',
                'description' => 'Select UAT/staging environment for development or testing stores, and production env for your online live store.',
                'options'  => array(
                    'staging'        => __('UAT/Staging', 'woocommerce'),
                    'production'        => __('Production', 'woocommerce'),
                ),
            ),

            'location' => array(
                'title'       => 'Region',
                'default'  => 'us',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select',
                'css'      => 'min-width: 350px;',
                'description' => 'Select your closest region.',
                'options'  => array(
                    'us'        => __('North America', 'woocommerce'),
                    'eu'        => __('Europe', 'woocommerce'),
                ),
            ),

            'auth_token' => array(
                'title'       => 'Server Auth Token',
                'description' => 'Get your Authentication Token from the payment method.',
                'type'        => 'text',
            ),

            'store_identifier' => array(
                'title'       => 'Store Identifier (optional)',
                'description' => 'This is the ID of your store for reporting purposes.',
                'type'        => 'text',
            ),
            'merchant_id' => array(
                'title'       => 'Merchant ID',
                'description' => 'Get your Merchant ID from the payment method.',
                'type'        => 'text',
            ),

            'merchant_location' => array(
                'title'       => 'Merchant Location',
                'type'        => 'text',
            ),

            'completion_method' => array(
                'title'       => 'Completion Method',
                'id'       => 'jifiti_completion_method',
                'default'  => 'TransferCard',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select',
                'css'      => 'min-width: 350px;',
                'options'  => array(
                    /*'None'        => __('None', 'woocommerce'),
                    'DisplayCard'        => __('DisplayCard', 'woocommerce'),*/
                    'TransferCard'        => __('TransferCard', 'woocommerce')/*,
                    'CompletePurchase'        => __('CompletePurchase', 'woocommerce'),
                    'CreateAccount'        => __('CreateAccount', 'woocommerce'),*/
                ),
            ),

            'allowed_product_types' => array(
                'title'       => __('Financing Product (optional)', 'woocommerce'),
                'type'        => 'multiselect',
                'description' => __('Define which financing products your lender will be offering. If not selected, we will use the default set by the payment method.'),
                'css'      => 'min-width: 350px;min-height: 200px;',
                'options'  => array(
                    'SplitPayments'        => __('Split Payments', 'woocommerce'),
                    'OTL'        => __('One Time Loan', 'woocommerce'),
                    'LC'        => __('Line Of Credit', 'woocommerce'),
                    'LTO'        => __('Lease To Own', 'woocommerce'),
                    'B2B_OTL'        => __('Business One Time Loan', 'woocommerce'),
                    'B2B_LOC'        => __('Business Line Of Credit', 'woocommerce'),
                    'B2B_LTO'        => __('Business Lease To Own', 'woocommerce'),
                    'B2B_SP'        => __('Business Split Payments', 'woocommerce')
                ),
                'custom_attributes' => array('multiple' => 'multiple')
            ),

            'customer_experience_setting_section_title' => array(
                'title' => __('Customer Experience Setting', 'woocommerce'),
                'type'  => 'title',
                'id'    => 'customer_experience_setting_section_title',
            ),

            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'css'      => 'max-width: 400px;',
                'custom_attributes' => array('readonly' => 'readonly')
            ),

            'payment_logo' => array(
                'title'       => 'Logo',
                'type'        => 'text',
                'custom_attributes' => array('readonly' => 'readonly')
            ),

            'window_behavior' => array(
                'title'       => __('Window behavior (optional)', 'woocommerce'),
                'id'       => 'jifiti_window_behavior',
                'default'  => 'lightbox',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select',
                'css'      => 'min-width: 350px;',
                'description' => 'Checkout opened as new window or iframe lightbox.',
                'options'  => array(
                    'lightbox'        => __('LightBox (Recommended)', 'woocommerce'),
                    'sametab'        => __('Same tab', 'woocommerce')
                    /*'newtab'        => __('New tab', 'woocommerce'),*/
                ),
            ),

            'commit_purchase' => array(
                'title'       => __('Commit / Capture event', 'woocommerce'),
                'id'       => 'jifiti_commit_purchase',
                'default'  => 'immediately',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select',
                'css'      => 'min-width: 350px;',
                'description' => 'This will define when we capture the payment after authorization',
                'options'  => array(
                    'immediately'        => __('When loan is approved', 'woocommerce'),
                    'upondelivery'        => __('Manually', 'woocommerce'),
                ),
            ),
        );
    }


    /**
     * You will need it if you want your custom credit card form, Step 4 is about it
     */
    public function payment_fields()
    {
        // ok, let's display some description before the payment formdd

        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }

        echo "<input type='hidden' id='windowBehavior' value = '" . $this->window_behavior . "'>";

        if ($this->window_behavior == "newtab") {
            echo "<input type='hidden' id='paymentGatewayURL'>";
            echo "<button id='newTabWindow' style='display:none;'>NewTab</button>";
        } else if ($this->window_behavior == "lightbox") {
            $this->renderJifitPaymentGatewayPopup();
        }
    }

    /*
    * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
    */
    public function payment_scripts()
    {
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            return;
        }

        if ($this->window_behavior == "lightbox") {
            wp_enqueue_style('woocommerce_payment_gateway_popup_style', plugins_url('css/popup.css', __FILE__));
        }
        
        wp_enqueue_style('woocommerce_payment_gateway_general_style', plugins_url('css/jifiti.style.css', __FILE__));
        wp_enqueue_script('woocommerce_payment_gateway_script', plugins_url('js/jifiti.checkout.js', __FILE__));
        wp_enqueue_script('woocommerce_payment_gateway_checkout_script', plugins_url('js/jifiti.checkout-script.js', __FILE__));
        
        wp_localize_script( 'woocommerce_payment_gateway_checkout_script', 'jifity_js_variable', array( 'X_Wp_Nonce' => wp_create_nonce( 'wp_rest' ) ) );
    }

    /*
    * We're processing the payments here, everything about it is in Step 5
    */
    public function process_payment($order_id)
    {
        $this->order_id = $order_id;

        //init the process
        $init_process = new Jifiti_Initiate_Flow($this);
        $responseData = $init_process->start();

        if ($responseData['status'] == 200 && !empty($responseData['data'])) {
            return array(
                'result' => 'success',
                'redirect' => $responseData['data']['RedirectURL'],
                'CallbackURL' => $responseData['data']['CallbackURL']

            );
        }
    }

    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $payload = [
            "RequestedAmount" => $amount,
            "Currency" => get_woocommerce_currency(),
            "MerchantId" => $this->merchant_id
        ];
        $jifiti_helper = new Jifiti_Config($this);
	    $request_client = new Jifiti_Request_Client($this->auth_token);

        $transaction = jifiti_get_transaction_by_order_id($order_id);

        $apiurl = str_replace(":AuthId", $transaction['auth_id'], $jifiti_helper->get_base_url() . JIFITI_REFUND_API_ENDPOINT);
        
        $refund_result = $request_client->callPost($apiurl, $payload, ["AuthId" => $transaction['auth_id']]);
        
        if ($refund_result['status'] != 200) {
            return false;
        }

        $order = new WC_Order($order_id);
        $order->add_order_note( __("Payment has been refunded", 'woocommerce') );
        jifiti_update_order_payment_status($order, JIFITI_ORDER_STATUS_REFUND);
		
        return true;
	}

    private function renderJifitPaymentGatewayPopup()
    {
        echo '<!-- The Modal -->
        <div id="paymentGatewayModal" class="modal">
            <!-- Modal content -->
            <div class="modal-content">
                <span class="close">&times;</span>
                
            </div>
        </div>';
    }
}

/*
* Payment Gateway Callback
*/
function jifiti_callback_action()
{
    $logger = wc_get_logger();

    if (!session_id()) {
        session_start();
    }

    if (empty($_GET['token'])) {
        $_SESSION["jifit_error_message"] = __("There are an error, please try again later.");
        header("Location: " . wc_get_checkout_url());
        die();
    }
    $token = sanitize_text_field($_GET['token']);
    $transaction = jifiti_get_transaction_by_token($token);

    if (!$transaction['transaction_id'] || !$transaction['reference_id']) {

        $_SESSION["jifit_error_message"] = __("A wrong Token is specified.");
        header("Location: " . wc_get_checkout_url());
        die();
    }

    $reference_id = $transaction['reference_id'];

    $logger->info("Cred - Return - ReferenceId => " . $reference_id);
    $wc_gateway = new WC_Jifiti_Payment_Gateway();
    $callback_helper = new Jifiti_Initiate_Flow($wc_gateway);
    $response = $callback_helper->call_check_account_status($reference_id);
    $jifiti_payment_gateway = jifiti_get_payment_instance();

    $logger->info("Cred - Return - Result => " . print_r($response, true));

    if ($response['status'] == 200 && !empty($response['data']) && !empty($response['data']['Status'])) {
        $order_id = $response['data']['OrderId'];
        $order = wc_get_order($order_id);
        $commit_purchase_helper = new Jifiti_Commit_Purchase($wc_gateway);

        //check if the order will be offline or not
        $orderStatus = $response['data']['Status'];
        
        if ($jifiti_payment_gateway->enable_offline_payment == "no") {
            update_post_meta( $order_id, 'is_offline_payment', 0 );
            $commit_purchase_helper->order_processing($response['data']);

            // Update order status
            $order->payment_complete();
    
            $_SESSION["jifit_empty_cart"] = true;
            
            // Return thankyou redirect.
            header("Location: " .get_return_url($order));
        }
        else if ($jifiti_payment_gateway->enable_offline_payment == "yes" && $orderStatus == Jifiti_Commit_Purchase::APPLICATION_PENDING) {
            $order->update_status( 'on-hold' );
            wp_update_post( array( 'ID' => $order_id, 'post_status' => 'wc-on-hold' ) );
            wp_set_object_terms( $order_id, 'on-hold', 'shop_order_status' );
            update_post_meta( $order_id, 'is_offline_payment', 1 );
            
            $_SESSION["jifit_empty_cart"] = true;

            // Return thankyou redirect.
            header("Location: " .get_return_url($order));
        }
        else {
            $_SESSION["jifit_error_message"] = __("We can't process your payment approval.");
            header("Location: " . wc_get_checkout_url());
        }

    } 
    else {
       $_SESSION["jifit_error_message"] = __("We can't process your payment approval.");
       header("Location: " . wc_get_checkout_url());
    }
    die();

    $logger->info("Cred - Return - END");   
}

function jifiti_offline_payment_action()
{
    $logger = wc_get_logger();
    $rowData = file_get_contents('php://input');
    $order_data = json_decode($rowData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $response = rest_ensure_response(array(
            'status' => 400,
            'error_message' => "wrong data format " . json_last_error_msg()
        ));
        $response->set_status( 400 );
        
        return $response;
    }
    
    if (empty($_GET['token'])) {
        $response = rest_ensure_response(array(
            'status' => 400,
            'error_message' => "token parameter is required."
        ));
        $response->set_status( 400 );
        
        return $response;
    }

    $token = sanitize_text_field($_GET['token']);
    $transaction = jifiti_get_transaction_by_token($token);

    if (empty($transaction)) {
        $response = rest_ensure_response(array(
            'status' => 400,
            'error_message' => "please provide valid token"
        ));
        
        $response->set_status( 400 );
        
        return $response;
    }
    
    if (!$transaction['transaction_id'] || !$transaction['reference_id']) {
        $response = rest_ensure_response(array(
            'status' => 400,
            'error' => "A wrong Token is specified."
        ));
        $response->set_status( 400 );
        
        return $response;
    }

    try {
        $order = wc_get_order($transaction['order_id']);

        if (!empty($transaction['auth_id']) || $order_data['Status'] == Jifiti_Commit_Purchase::APPLICATION_PENDING || get_post_meta($transaction['order_id'], 'is_offline_payment', true) != 1) {
            return rest_ensure_response(array(
                'status' => 200,
                'error' => "Skipped the order."
            ));
        }

        $jifiti_payment_gateway = jifiti_get_payment_instance();

        $commit_purchase_helper = new Jifiti_Commit_Purchase($jifiti_payment_gateway);
        $commit_purchase_helper->order_processing($order_data, true);
        
        
        // Update order status
        $order->payment_complete();

        $response = rest_ensure_response(array(
            'status' => 200,
            'message' => "Order Processed."
        ));
        $response->set_status( 200 );
        return $response;
    }
    catch (\Exception $e) {
        $response = rest_ensure_response(array(
            'status' => 400,
            'error' => $e->getMessage()
        ));
        $response->set_status( 200 );
        
        return $response;
    }
}

function get_return_url( $order = null ) {
    if ( $order ) {
        $return_url = $order->get_checkout_order_received_url();
    } else {
        $return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
    }

    return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
}

add_action('rest_api_init', function ($data) {

    register_rest_route('jifiti-cred', '/payment/callback',
        array(
            'methods' => 'GET',
            'callback' => 'jifiti_callback_action',
            'permission_callback' => '__return_true',
        )
    );
    register_rest_route('jifiti-cred', '/payment/offlineaction',
        array(
            'methods' => 'POST',
            'callback' => 'jifiti_offline_payment_action',
            'permission_callback' => '__return_true',
        )
    );
});

function jifiti_woocommerce_order_status($order, $data_store)
{
    $disallowed_user_roles = array( 'shop_manager');

    $transaction = jifiti_get_transaction_by_order_id($order->get_id());
    
    if (empty($transaction)) {
        return $order;
    }

    $logger = wc_get_logger();
    $wc_payment_obj = new WC_Jifiti_Payment_Gateway();

    $changes = $order->get_changes();
    
    if (!empty($changes) && isset($changes['status']) && $transaction['is_committed'] != 1) {
        if ($changes['status'] == "completed") {
            $callback_helper = new Jifiti_Initiate_Flow($wc_payment_obj);
            $response = $callback_helper ->call_check_account_status($transaction['reference_id']);
            $user          = wp_get_current_user();
            $matched_roles = array_intersect($user->roles, $disallowed_user_roles);

            if ($response['status'] == 200) {
                $commit_helper = new Jifiti_Commit_Purchase($wc_payment_obj);

                if (!empty($transaction['auth_id'])) {
                    $commit_purchase_results = $commit_helper->call_commit_purchase($transaction['auth_id'], $order->get_total());

                    $commit_purchase_data = $commit_purchase_results['data'];
                    
                    if ($commit_purchase_results["status"] !== 200) {
                        throw new Exception( sprintf( __($commit_purchase_data['Detail'], "woocommerce" )));
                        return false;
                    }
                    else if ($commit_purchase_data["Status"] !== Jifiti_Commit_Purchase::PURCHASE_APPROVED) {
                        throw new Exception( sprintf( __($commit_purchase_data['StatusReason'], "woocommerce" )));
                        return false;
                    }
            
                    jifiti_update_transaction_is_commit($order->get_id(), 1);
                }
            } else {
                throw new Exception(sprintf(__("We can't process the payment approval, the check account status error", "woocommerce")));
                return false;
            }
        }
    }
	
    return $order;
}

//add_action('woocommerce_before_order_object_save', 'jifiti_woocommerce_order_status', 10, 2);


add_action( 'woocommerce_thankyou', 'jifiti_empty_cart_thankyou' );
  
function jifiti_empty_cart_thankyou($order_id) {
    if (!session_id()) {
        session_start();
    }

    if (!empty($_SESSION["jifit_empty_cart"])) {
        unset($_SESSION["jifit_empty_cart"]);
        
        if ( is_null( WC()->cart ) ) {
            wc_load_cart();
        }
    
        WC()->cart->empty_cart();
    }

    if (get_post_meta( $order_id, 'is_offline_payment', true) == 1) {
        echo '<p>Your order is currently being processed, we will get back to you soon with an update on the status.</p>';
    }
}

add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', 'add_payment_fields');

function add_payment_fields(){
    $gateway = new WC_Jifiti_Payment_Gateway();
    $gateway->payment_fields();
}
