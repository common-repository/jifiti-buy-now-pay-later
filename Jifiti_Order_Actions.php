<?php

const JIFITI_PURCHASE_COMMIT_API_ENDPOINT = "purchases/v2/:AuthId/Commit";
const JIFITI_REFUND_API_ENDPOINT = "purchases/v2/:AuthId/Refund";
const JIFITI_CANCEL_API_ENDPOINT = "purchases/v2/:AuthId/Cancel";



function jifiti_custom_shop_order_column($columns)
{
	
    // Inserting columns to a specific location
    foreach( $columns as $key => $column){
        $reordered_columns[$key] = $column;
        if( $key ==  'order_status' ){
            // Inserting after "Status" column
            $reordered_columns['jifiti_payment_status'] = __( 'Payment Status', 'woocommerce');
        }
    }
    return $reordered_columns;
}

add_filter( 'manage_edit-shop_order_columns', 'jifiti_custom_shop_order_column');
add_filter( 'woocommerce_shop_order_list_table_columns', 'jifiti_custom_shop_order_column');


function jifiti_shop_order_column_value_old($column)
{
	global $post;

	switch ( $column ) {
		case "jifiti_payment_status":
			$order_status = jifiti_get_order_payment_status(wc_get_order($post->ID));
			if (!empty($order_status)) {
				echo ucwords($order_status);
			}
			break;
	}

}
add_action( 'manage_shop_order_posts_custom_column', 'jifiti_shop_order_column_value_old');





function jifiti_shop_order_column_value($column, $order)
{
	switch ( $column ) {
		case "jifiti_payment_status":
			
			$order_status = jifiti_get_order_payment_status($order);
			if (!empty($order_status)) {
				echo ucwords($order_status);
			}
			break;
	}

}
add_action( 'woocommerce_shop_order_list_table_custom_column', 'jifiti_shop_order_column_value', 10, 2);

function jifiti_custom_order_action($actions, $order)
{
	$order_action_status = strtolower(jifiti_get_order_payment_status($order));
	
	if ($order_action_status !== null) {
		if ($order_action_status == JIFITI_ORDER_STATUS_AUTHORIZED) {
			$actions['jifiti_capture_order_action'] = __( 'Capture Order', 'woocommerce' );
		}

		if ($order_action_status == JIFITI_ORDER_STATUS_AUTHORIZED) {
			$actions['jifiti_void_order_action'] = __( 'Void Order', 'woocommerce' );
		}
	}
	
	return $actions;
}
add_action( 'woocommerce_order_actions', 'jifiti_custom_order_action', 10, 2 );

function fired_jifiti_capture_order_action($order)
{
	$jifiti_payment_gateway = jifiti_get_payment_instance();

	if ($jifiti_payment_gateway == null) {
		jifiti_admin_error_notice(__("There are an error with loading Jifiti payment method."));
		return;
	}

	$transaction = jifiti_get_transaction_by_order_id($order->get_id());
	$jifiti_helper = new Jifiti_Config($jifiti_payment_gateway);
	$request_client = new Jifiti_Request_Client($jifiti_payment_gateway->auth_token);
	$apiurl = str_replace(":AuthId", $transaction['auth_id'], $jifiti_helper->get_base_url() . JIFITI_PURCHASE_COMMIT_API_ENDPOINT);
	$payload = [
		"CommitAmount" => $order->get_total()
	];
	$commit_result = $request_client->callPost($apiurl, $payload);

	if ($commit_result['status'] != 200) {
		jifiti_admin_error_notice(__("The are error with the capture process, please try again later."));
		return false;
	}
	

	jifiti_update_order_payment_status($order, JIFITI_ORDER_STATUS_CAPTURED);
	jifiti_update_transaction_is_commit($order->get_id(), 1);
	jifiti_admin_notice(__("The order has been captured.<br><pre>" . print_r($commit_result, true) . "</pre>"));
	$order->add_order_note( __("Payment has been captured", 'woocommerce') );
}
add_action( 'woocommerce_order_action_jifiti_capture_order_action', 'fired_jifiti_capture_order_action' );

function fired_jifiti_void_order_action($order)
{
	$jifiti_payment_gateway = jifiti_get_payment_instance();
	
	if ($jifiti_payment_gateway == null) {
		jifiti_admin_error_notice(__("There are an error with loading Jifiti payment method."));
		return;
	}
	
	$transaction = jifiti_get_transaction_by_order_id($order->get_id());
	$jifiti_helper = new Jifiti_Config($jifiti_payment_gateway);
	$request_client = new Jifiti_Request_Client($jifiti_payment_gateway->auth_token);

	$apiurl = str_replace(":AuthId", $transaction['auth_id'], $jifiti_helper->get_base_url() . JIFITI_CANCEL_API_ENDPOINT);

	$cancel_result = $request_client->callPost($apiurl, []);

	if ($cancel_result['status'] != 200) {
		jifiti_admin_error_notice(__("The are error with the void order proccess, please try again later."));
		return false;
	}

	jifiti_update_order_payment_status($order, JIFITI_ORDER_STATUS_VOID);
	jifiti_admin_notice(__("The order has been voided."));
	$order->add_order_note( __("Payment has been voided", 'woocommerce') );
	return true;
}
add_action( 'woocommerce_order_action_jifiti_void_order_action', 'fired_jifiti_void_order_action' );