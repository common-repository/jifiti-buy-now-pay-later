<?php
class Jifiti_Initiate_Flow
{
    const STATUS_REQUEST = "request";
    const API_ENDPOINT = "applications/v2/InitiateFlow";

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
    protected $transaction;


    /**
     * Constructor
     * 
     * @param WC_Jifiti_Payment_Gateway $wc_payment_obj
     */
    public function __construct($wc_payment_obj)
    {
        $this->wc_payment_obj = $wc_payment_obj;
    }

    public function start()

    {
        $logger = wc_get_logger();
        $logger->info('===== Initiate_Flow Controller ===== Start');
        
        try {
            $result = $this->call_initiate_flow_api();
  
            $this->transaction['initiate_flow_response'] = json_encode($result);
            $this->transaction['initiate_flow_request_at'] = date("Y-m-d H:i:s");

            $resultData = $result["data"];
     

            $logger->info("('===== InitiateFlow Controller ===== InitiateFlow Response: " . print_r($result, true));

            if ($result['status'] != 200) {
                $response = [
                    "success" => false,
                    "error" => $resultData ? $resultData->Detail : __("Something went wrong!"),
                    "status" => $result['status']
                ];
            } else {
                $response = [
                    "success" => true,
                    "data" => $resultData,
                    "status" => $result['status']
                ];

                // Save ReferenceId for checking in next controllers
                $this->store_reference_id($resultData['ReferenceId']);
            }
        } catch (\Exception $e) {
             $logger->info('===== InitiateFlow Controller ===== Error reason: ' . $e->getMessage());
            $response = [
                "success" => false,
                "error" => $e->getMessage(),
                "status" => 500,
            ];
        }
        $logger->info('===== InitiateFlow Controller ===== END');
        $this->transaction['status'] = self::STATUS_REQUEST;
        
        $transaction_data = jifiti_get_transaction_by_order_id($this->wc_payment_obj->order_id);
        
        // save transaction data
        if (!empty($transaction_data)) {
            jifiti_update_transaction($this->transaction);
        }
        else {
            jifiti_add_transaction($this->transaction);
        }

        return $response;
    }

    private function call_initiate_flow_api()
    
    {

        $logger = wc_get_logger();

        $payload = $this->get_initiate_flow_payload();


        $this->transaction['initiate_flow_request'] =  json_encode($payload);
        $logger->info('===== InitiateFlow Controller ===== Request ' . print_r($payload, true));

        $api_url = $this->base_api_url[$this->wc_payment_obj->location][$this->wc_payment_obj->environment] . self::API_ENDPOINT;
        $logger->info('===== InitiateFlow Controller ===== apiUrl ' . print_r($api_url, true));

        $request_helper = new Jifiti_Request_Client($this->wc_payment_obj->auth_token);


        ["data" => $result, "status" => $httpCode] =  $request_helper->callPost($api_url, $payload);
        $logger->info('===== InitiateFlow Controller ===== result ' . print_r($result, true));

        if (!$result) {
            return [
                "status" => 500,
                "data" => null
            ];
        }

        // Return back the callback URL in response
        $result['CallbackURL'] = $payload["CallbackURL"];

        return [
            "status" => $httpCode,
            "data" => $result,
        ];
    }

    public function get_order_data()

    {   
        $order_id = $this->wc_payment_obj->order_id;
        $order = wc_get_order($order_id);

        $generated_token = !empty($this->transaction['token']) ? $this->transaction['token'] : $this->init_token($order_id+$order->get_customer_id());

       
        $this->transaction['order_id'] = $order_id;
        $this->transaction['token'] = $generated_token;

        $orderData = [
            "Customer" => [
                "FirstName" => $order->get_billing_first_name(),
                "LastName" => $order->get_billing_last_name(),
                "Email" => $order->get_billing_email(),
                "MobilePhoneNumber" => $order->get_billing_phone()
            ],
            "BillingAddress" => [
                "AddressLine1" => $order->get_billing_address_1(),
                "City" => $order->get_billing_city(),
                "State" => $order->get_billing_state(),
                "Country" => $order->get_billing_country(),
                "PostalCode" => $order->get_billing_postcode()
            ],
            "ShippingAddress" => [
                "AddressLine1" => !empty($order->get_shipping_address_1()) ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
                "City" => !empty($order->get_shipping_city()) ? $order->get_shipping_city() : $order->get_billing_city(),
                "State" => !empty($order->get_shipping_state()) ? $order->get_shipping_state() : $order->get_billing_state(),
                "Country" => !empty($order->get_shipping_country()) ? $order->get_shipping_country() : $order->get_billing_country(),
                "PostalCode" => !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : $order->get_billing_postcode()
            ],
            "RequestedAmount" => $order->get_total(),
            "Currency" => $order->get_currency(),
            "OrderId" => $this->wc_payment_obj->order_id,
            "LoyaltyNumber" => strval($order->get_customer_id()),
        ];

        $callbacksUrls = [
            "CallbackURL" => get_site_url().'/wp-json/jifiti-cred/payment/callback?token='.$generated_token,
        ];
        
        if ($this->wc_payment_obj->enable_offline_payment == "yes") {
            $callbacksUrls['NotificationAPIURL'] = get_site_url() . '/wp-json/jifiti-cred/payment/offlineaction?token=' . $generated_token;
        }

        $order_items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $image_id  = $product->get_image_id();
            $image_url = wp_get_attachment_image_url($image_id, 'full');

            $order_items[] = [
                "Name" => $product->get_name(),
                "SKU" => $product->get_id(),
                "Quantity" => $item->get_quantity(),
                "Price" => $product->get_price(),
                "Currency" => $order->get_currency(),
                "ImageURL" => $image_url,
                "SalesTax" => $item->get_total_tax(),
                "Fees" => 0,
                "TotalCost" => wc_get_price_including_tax($product) * $item->get_quantity(),
                "Eligible" => true
            ];
        }

        $orderData["Items"] = $order_items;

        return array_merge( $orderData, $callbacksUrls);
    }

    public function get_initiate_flow_payload()
    {
        $config = (new Jifiti_Config($this->wc_payment_obj)) ->get_general_config();
        $config["StartedFrom"] = "WooCommerce";
        $orderData = $this->get_order_data();

        return array_merge($config, $orderData);
    }


     /**
     * Store ReferenceId in quote and storage variable
     *
     * @param string $referenceId
     * @return void
     */
    
    protected function store_reference_id($reference_id)
    {

        $this->transaction['reference_id'] = $reference_id;

        if (!session_id()) {
            session_start();
        }

        if (isset($_POST['wp-submit'])) {
            $_SESSION['reference_id'] = $reference_id;
        }
    }

     /**
     * set token
     *
     * @param string $data
     * @return string
     */
    protected function init_token($data)
    {
        return hash("sha256", time() . $data);
    }  
    
    public function call_check_account_status($reference_id)
    {
        $api_url = $this->base_api_url[$this->wc_payment_obj->location][$this->wc_payment_obj->environment] .'applications/v2/CheckAccountStatus';
        $parameters = [
            "ReferenceId" => $reference_id,
        ];
        $url = $api_url . "?" . http_build_query($parameters);
      
        $request_helper = new Jifiti_Request_Client($this->wc_payment_obj->auth_token);
        ["data" => $result, "status" => $httpCode] =  $request_helper->callGet($url);
        return [
            "status" => $httpCode,
            "data" => $result
        ];
    }
}
