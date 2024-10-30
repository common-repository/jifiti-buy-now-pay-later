<?php

class Jifiti_Request_Client
{   
    /**
     * var string
     */
    private $auth_token;

    /**
     * Constructor
     * 
     * @param string $auth_token
     */
    public function __construct($auth_token)
    {
        $this->auth_token = $auth_token;
    }

    /**
     * Return Jifiti Headers
     * 
     * @return array
     */
    public function getHeaders()
    {
        return array(
            'Token' => $this->auth_token,
            'Content-Type' => 'application/json'
        );
    }

    /**
     * Call GET HTTP Request
     *
     * @param string $url
     * @return array
     */
    public function callGet($url)
    {
        $headers = $this->getHeaders();

        $response = wp_remote_get($url, array(
            'headers'     => $headers
        ));

        $result = wp_remote_retrieve_body( $response );

        $result = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                "status" => 500,
                "data" => null
            ];
        }

        return [
            "data" => $result,
            "status" =>  wp_remote_retrieve_response_code( $response )
        ];
    }

    /**
     * Call POST HTTP Request
     *
     * @param string $url
     * @param array $payload
     * @return array
     */
    public function callPost($url, $payload, $extraHeaders = array())
    {
        $headers = array_merge($this->getHeaders(), $extraHeaders);
        
        $response = wp_remote_post($url, array(
            'headers'     => $headers,
            'timeout' => 120,
            'body' => wp_json_encode(!empty($payload) ? $payload : ['default_param' => 1])
        ));

        $result = wp_remote_retrieve_body( $response );

        if (!empty($result)) {
            $result = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    "status" => 500,
                    "data" => null
                ];
            }
        }
        else {
            $result = [];
        }

        return [
            "data" => $result,
            "status" => wp_remote_retrieve_response_code( $response )
        ];
    }
}