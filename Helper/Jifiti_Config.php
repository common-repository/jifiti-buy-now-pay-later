<?php

class Jifiti_Config {

    const STAGING_ENVIRONMENT = "staging";
    const PRODUCTION_ENVIRONMENT = "production";

    /**
     * Default initateFlow API parameters
     */
    const SOURCE_PAGE_TYPE = "Checkout";        // [ Financing, PIP, Cart, Checkout ]
    const COMPLETION_METHOD = "TransferCard";   // [ None, DisplayCard, TransferCard, CompletePurchase, CreateAccount ]
    const DEFAULT_WINDOW_BEHAVIOR = "Lightbox"; // [ NewTab, SameTab, Lightbox ]
    const SOURCE_CHANNEL = "Online";

    /**
     * Cred settings config path
     */
    const XML_PATH_CONFIG = 'payment/cred';

    const DEFAULT_CONFIG = [
        "keys" => [],
        "frontend" => []
    ];

    /**
     * var array
     */
    public $base_api_url = [
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


       /**
     * return config data from admin
     *
     * @return array
     */
    public function get_general_config()
    {
        $config = [
            "MerchantId" => $this->wc_payment_obj->merchant_id,
            "StoreId" => $this->wc_payment_obj->merchant_location,
            "SourcePageType" => self::SOURCE_PAGE_TYPE,
            "CompletionMethod" => $this->wc_payment_obj->completion_method,
            "WindowBehavior" => $this->wc_payment_obj->window_behavior,
            "SourceChannel" => self::SOURCE_CHANNEL
        ];

        if (!empty($this->wc_payment_obj->allowed_product_types)) {
            $config["AllowedProductTypes"] = $this->wc_payment_obj->allowed_product_types;
        }
        return $config;
    }

    public function is_purchase_immediately_commit(): bool
    {
        return $this->wc_payment_obj->commit_purchase == 'immediately';
    }

    public function get_base_url($location = '', $environment = '') {
        if(empty($location)){
            $location = $this->wc_payment_obj->location;
        }
        if(empty($environment)){
            $environment = $this->wc_payment_obj->environment;
        }
        return $this->base_api_url[$location][$environment];
    }
}