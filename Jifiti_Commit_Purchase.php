<?php
class Jifiti_Commit_Purchase
{
    const ACCOUNT_CREATED_STATUS = "AccountCreated";
    const PURCHASE_COMPLETE_STATUS = "PurchaseComplete";
    const APPLICATION_DECLINED_STATUS = "ApplicationDeclined";
    const APPLICATION_IN_PROGRESS_STATUS = "ApplicationInProgress";
    const APPLICATION_APPROVED = "ApplicationApproved";
    const APPLICATION_PENDING = "ApplicationPending";

    const PURCHASE_APPROVED = "Approved";
    const PURCHASE_DECLINED = "Declined";
    const PURCHASE_PENDING = "Pending";

    const PURCHASE_AUTH_API_ENDPOINT = "purchases/v2/Authorize";
    const PURCHASE_COMMIT_API_ENDPOINT = "purchases/v2/:AuthId/Commit";

    /**
     * var array
     */
    private $base_api_url = [
        'us' => [
            "staging" => "https://apiuat.jifiti.com/",
            "production" => "https://api.jifiti.com/",
        ],
        'eu' => [
            "staging" => "https://apiuat-eu.jifiti.com/",
            "production" => "https://api-eu.jifiti.com/",
        ]
    ];

    /**
     * @var WC_Jifiti_Payment_Gateway
     */
    private $wc_payment_obj;


    /**
     * Constructor
     * 
     * @param WC_Jifiti_Payment_Gateway $wc_payment_obj
     */
    public function __construct($wc_payment_obj)
    {
        $this->wc_payment_obj = $wc_payment_obj;
    }

    public function finalize_gatewaty_purchase($data, $is_immediately_commit = false, $call_commit = false, $throw_exception = false)
    {
        $logger = wc_get_logger();

        // Call Payment APIs
        $response = $this->call_authorize_purchase($data, $throw_exception);
        $result = $response["data"];
        $jifit_error_message = null;

        /**
         * Redirect the customer back to checkout page if
         * 1- APIs fail
         * 2- status property is "Declined"
         */
        if ($response["status"] !== 200 && !empty($result['Detail'])) {
            $jifit_error_message = $result['Detail'];
        
        } elseif ($result['Status'] !== self::PURCHASE_APPROVED && !empty($result['StatusReason'])) {
            $jifit_error_message = $result['StatusReason'];
        } elseif (empty($result['AuthId']) || empty($result['Status'])) {
            $jifit_error_message = __("There are an issue with authrize order");
        }

        if (!empty($jifit_error_message)) {
            if ($throw_exception) {
                throw new \Exception($jifit_error_message);
            }

            if (!session_id()) {
                session_start();
            }

            $_SESSION["jifit_error_message"] = $jifit_error_message;
            header("Location: " . wc_get_checkout_url());
            die();
        }

        if (!empty($result['AuthId']) && !empty($data['OrderId'])) {
            jifiti_update_transaction_auth_id($data['OrderId'],$result['Status'],$result['AuthId']);
        }

        if ($is_immediately_commit) {
            jifiti_update_transaction_is_commit($data['OrderId'], 1);
        }

        if (!$call_commit) {
            return $result;
        }
        
        $order = wc_get_order($data['OrderId']);
        // Commit the purchase payment in gateway
        $response = $this->call_commit_purchase($result['AuthId'], $order->get_total());

        $logger->info('=====  Place Order  === PurchaseCommit Response: ' . json_encode($response));


        $result = $response["data"];
        $jifit_error_message = null;

        /**
         * Redirect the customer back to checkout page if
         * 1- APIs fail
         * 2- status property is "Declined"
         */
        if ($response["status"] !== 200 && !empty($result['Detail'])) {
            $jifit_error_message = $result['Detail'];
        } elseif (!empty($result["Status"]) && $result["Status"] !== self::PURCHASE_APPROVED && !empty($result['StatusReason'])) {
            $jifit_error_message = $result['StatusReason'];
        } elseif (empty($result['AuthId']) || empty($result['Status'])) {
            $jifit_error_message = __("There are an issue with authrize order");
        }

        if (!empty($jifit_error_message)) {
            if ($throw_exception) {
                throw new \Exception($jifit_error_message);
            }

            if (!session_id()) {
                session_start();
            }

            $_SESSION["jifit_error_message"] = $jifit_error_message;
            header("Location: " . wc_get_checkout_url());
            die();
        }


        return $result;
    }

        /**
     * Check if payment authorized in payment gateway
     *
     * @return array
     */
    public function call_authorize_purchase($order, $throw_exception = false)
    {
        $logger = wc_get_logger();
        $payload = $this->get_purchase_authorize_payload($order, $throw_exception);
        
        $logger->info("===== Place Order  === callAuthorizePurchase - Request: " . print_r($payload, true));
        $api_url =  $this->base_api_url[$this->wc_payment_obj->location][$this->wc_payment_obj->environment]  . self::PURCHASE_AUTH_API_ENDPOINT;

       $logger->info("===== Place Order  === callAuthorizePurchase - apiUrl: " . $api_url);
       
        $request_helper = new Jifiti_Request_Client($this->wc_payment_obj->auth_token);
        ["data" => $result, "status" => $httpCode] =  $request_helper->callPost($api_url, $payload);
    

       $logger->info("===== Place Order  === callAuthorizePurchase - Response: " . json_encode($result));

        return [
            "status" => $httpCode,
            "data" => $result
        ];
    }

     /**
     * prepare payload request of purchase authorize API
     *
     * @return array
     */
    private function get_purchase_authorize_payload($data, $throw_exception = false)
    {
        $logger = wc_get_logger();

        $config_helper = new Jifiti_Config($this->wc_payment_obj);

        $payload = [];
        $config = $config_helper->get_general_config();
        $jifit_error_message = null;

        if (empty($data['IssuedCards'])) {
            $jifit_error_message =  __('Can\'t found Card Id, please try again later.', 'woocommerce');
        }
        else if(empty($data['OrderId'])) {
            $jifit_error_message =  __('Can\'t found order Id, please try again later', 'woocommerce');
        }

        if (!empty($jifit_error_message)) {
            if ($throw_exception) {
                throw new Exception($jifit_error_message);
            }
            else {
                if (!session_id()) {
                    session_start();
                }
                $_SESSION["jifit_error_message"] = $jifit_error_message;
                header("Location: " . wc_get_checkout_url());
                die();
            }
        }

        $cardId = $data['IssuedCards'][0]['Card']['CardId'];
        $order = wc_get_order($data['OrderId']);
        
        $payload = [
            "RequestedAmount" => $order->get_total(), 
            "Currency" => $data['Currency'],
            "CardId" => $cardId,
            "MerchantId" => $config["MerchantId"]
        ];

        if ($config_helper->is_purchase_immediately_commit()) {
            $payload["InstantCommit"] = "true";
            jifiti_update_order_payment_status($order, JIFITI_ORDER_STATUS_CAPTURED);
            $order->add_order_note( __("Payment has been captured", 'woocommerce') );
            $logger->info("===== Place Order  === PurchaseImmediatelyCommit === true");
        }
        else {
            $payload["InstantCommit"] = "false";
            jifiti_update_order_payment_status($order, JIFITI_ORDER_STATUS_AUTHORIZED);
            $order->add_order_note( __("Payment has been authorized", 'woocommerce') );
            $logger->info("===== Place Order  === PurchaseImmediatelyCommit === false");
        }

        return $payload;
    }

    
       /**
     * Commit the payment in the payment gateway by AuthId
     *
     * @param string $auth_id
     * @return array
     */
    public function call_commit_purchase($auth_id, $grand_total)
    {
        $logger = wc_get_logger();
        $payload = [
            "CommitAmount" =>$grand_total
        ];

       $logger->info("===== Observer Place Order  === callCommitPurchase - Request: " . json_encode($payload));

        $path = str_replace(":AuthId", $auth_id, self::PURCHASE_COMMIT_API_ENDPOINT);
        $api_url = $this->base_api_url[$this->wc_payment_obj->location][$this->wc_payment_obj->environment] .$path;
        $request_helper = new Jifiti_Request_Client($this->wc_payment_obj->auth_token);

         $response = $request_helper->callPost($api_url, $payload);

        $result = $response['data'];
        $httpCode = $response['status'];

       $logger->info("Observer: http_code: " . $httpCode  . "  authId: " . $auth_id);


       $logger->info("===== Observer Place Order  === callCommitPurchase - Response: " . json_encode($result));

        return [
            "status" => $httpCode,
            "data" => $result
        ];
    }

    public function order_processing($order_data, $offline_payment = false)
    {
       $logger = wc_get_logger();
       $throw_exception = $offline_payment;

       $logger->info('===== Place Order  ===== Start');

        try {
           $logger->info('===== Place Order  ===== Request: ' . json_encode($order_data));
           $logger->info('===== Place Order  ===== Validate the passed request data');
            
           $this->validate_request_data($order_data, $throw_exception);

           $logger->info('===== Place Order  ===== Continue the process depending on status from payment gateway');
        
           jifiti_update_transaction_status($order_data['OrderId'], $order_data['Status']);

            switch ($order_data['Status']) {

                case self::ACCOUNT_CREATED_STATUS: // The loan or line of credit account has been created. This notification will come with the payment tokens to run the transaction, if relevant.
                    $this->handle_create_account($order_data, $throw_exception);
                    break;

                case self::APPLICATION_IN_PROGRESS_STATUS: // The application is in progress (no result yet)
                    $this->handle_declined_payment($order_data['OrderId'], $offline_payment);
                    break;

                case self::APPLICATION_APPROVED:
                    $this->handle_approved_payment();
                    break;

                case self::APPLICATION_DECLINED_STATUS: // The application is declined by the lender.
                    $this->handle_declined_payment($order_data['OrderId'], $offline_payment);
                    break;

                default:
                    $this->handle_declined_payment($order_data['OrderId'], $offline_payment);
                    break;
            }
      
        } catch (\Exception $e) {
            $logger->error('===== Place Order  ===== Error reason: ' . $e->getMessage());

            if ($throw_exception) {
                throw new Exception(__($e->getMessage(), "woocommerce"));
            }

            if (!session_id()) {
                session_start();
            }

            $_SESSION["jifit_error_message"] =  __($e->getMessage(), "woocommerce");
            header("Location: " . wc_get_checkout_url());
            die();
        }

        $logger->info('===== Place Order  ===== End');
    }

        /**
     * check if config token equal request token.
     * throw exception if not valid
     *
     * @param AccountStatusResponse $data
     * @return boolean
     */
    private function validate_request_data($data, $throw_exception = false)
    {
       $logger = wc_get_logger();
       
       if (!session_id()) {
           session_start();
       }
       
       $logger->info("===== Place Order  === validate_request_data === Start");
       $logger->info("===== Data ===" . json_encode($data));

       $config_helper = new Jifiti_Config($this->wc_payment_obj);
       $config = $config_helper->get_general_config();
       $jifit_error_message = null;

       if ($data['MerchantId']!= $config['MerchantId']) {
            $jifit_error_message =  __('Validation error , "Parameter \"MerchantId\" not match.', "woocommerce");
       }
       else if (empty($data['OrderId'])) {
            $jifit_error_message =  __('Validation error , "Parameter \"OrderId\" not match.', "woocommerce");
       }
       else if (empty($data['ReferenceId'])) {
            $jifit_error_message =  __('Validation error , "Parameter \"ReferenceId\" not match.', "woocommerce");
       } 
       
       if (!empty($jifit_error_message)) {
            if ($throw_exception) {
                throw new \Exception($jifit_error_message);
            }

            if (!session_id()) {
                session_start();
            }

            $_SESSION["jifit_error_message"] = $jifit_error_message;
            header("Location: " . wc_get_checkout_url());
            die();
        }

        $logger->info("===== Place Order  === validate_request_data === END");
        return true;
    }

     /**
     * Handle the response from Payment Gateway when status equal "AccountCreated"
     * 1- Check if payment approved on payment gateway by calling Purchase Payment APIs
     *  a- Create order with invoice and payment object and navigate to succses page
     *  b- Otherwise navigate to failure page and rollback all created objects.
     *
     * @return void
     */
    private function handle_create_account($data, $throw_exception = false)
    {
       $logger = wc_get_logger();
       $logger->info('===== Place Order  === handleCreateAccount === Start');

       $logger->info('===== Place Order  === Call Purchase API == BEFORE');
       $config_helper = new Jifiti_Config($this->wc_payment_obj);
       $this->finalize_gatewaty_purchase($data, $config_helper->is_purchase_immediately_commit(), false, $throw_exception);
       $logger->info('===== Place Order  === Call Purchase API == AFTER');
       $logger->info('===== Place Order  === handleCreateAccount === End');
    }

      /**
     * handle declind the payment by Payment Gateway
     *
     * @return void
     */
    private function handle_declined_payment($order_id, $offline_payment = false)
    {
        $logger = wc_get_logger();

        $logger->info('===== Place Order  ===== Called HandleDeclinedPayment');
       
        if ($offline_payment) {
            $order = wc_get_order($order_id);
            $order->update_status( 'cancelled' );
            wp_update_post( array(
                'ID' => $order_id,
                'post_status' => 'cancelled'
            ) );
        }

        throw new \Exception("The payment has been declined");
    }

      /**
     * The application has been approved but not yet finalized (account not created yet)
     *
     * @return void
     */
    private function handle_approved_payment()
    {
        $logger = wc_get_logger();
        $logger->info('===== Place Order  ===== Called handle_approved_payment');
        
        // Clean session if saved on 
        session_destroy();
        new Exception("The minimum amount for financing not reached.");
    }

}
