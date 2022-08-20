<?php

/*
 * Helper class for using the new Paytrail API.
 * 
 * This class is designed to be used on websites, that don't 
 * have the ability to use composer and want a custom solution 
 * for payments.
 * 
 * This class does not wrap around the entire 'create payment'
 * endpoint, but instead gives you a more clear view on how 
 * payments are created. At least I hope so.
*/

class Paytrail_Exception extends Exception
{
    public function __construct($message)
    {
        parent::__construct("Paytrail exception: " . $message);
    }
}

class Paytrail {
    protected $merchant_id;
    protected $merchant_secret;

    /*
     * Request body properties
    */
    protected $customer             = array();
    protected $delivery_address     = array();
    protected $invoicing_address    = array();
    protected $items                = array();
    protected $callback_urls        = array();

    protected $body                 = array();

    public function __construct($merchant_id, $merchant_secret) {
        $this->merchant_id = $merchant_id;
        $this->merchant_secret = $merchant_secret;
    }

    public function addProduct($payload) {
        $all_fields = ['unitPrice', 'units', 'vatPercentage', 'productCode', 'deliveryDate', 'description', 'category', 'orderId', 'stamp', 'reference', 'merchant', 'commission'];
        $required_fields = ['unitPrice', 'units', 'vatPercentage', 'productCode'];

        //Validate all the fields mentioned in the above arrays. Make sure the payload matches.
        $this->items[] = $this->_validate_fields($all_fields, $required_fields, $payload);
    
        return $this;
    }

    public function addCustomer($payload) {
        $all_fields = ['email', 'firstName', 'lastName', 'phone', 'vatID', 'companyName'];
        $required_fields = ['email'];

        //Validate all the fields mentioned in the above arrays. Make sure the payload matches.
        $this->customer = $this->_validate_fields($all_fields, $required_fields, $payload);

        return $this;
    }

    public function addDeliveryAddress($payload) {
        $all_fields = ['streetAddress', 'postalCode', 'city', 'county', 'country'];
        $required_fields = ['streetAddress', 'postalCode', 'city', 'country'];

        //Validate all the fields mentioned in the above arrays. Make sure the payload matches.
        $this->delivery_address = $this->_validate_fields($all_fields, $required_fields, $payload);
    
        return $this;
    }

    public function addInvoicingAddress($payload) {
        $all_fields = ['streetAddress', 'postalCode', 'city', 'county', 'country'];
        $required_fields = ['streetAddress', 'postalCode', 'city', 'country'];

        //Validate all the fields mentioned in the above arrays. Make sure the payload matches.
        $this->invoicing_address = $this->_validate_fields($all_fields, $required_fields, $payload);
    
        return $this;
    }

    public function addCallbackURLs($payload) {
        $all_fields = ['success', 'cancel'];
        $required_fields = ['success', 'cancel'];

        //Validate all the fields mentioned in the above arrays. Make sure the payload matches.
        $this->callback_urls = $this->_validate_fields($all_fields, $required_fields, $payload);
    
        return $this;
    }

    public function addBody($payload) {
        $all_fields = [
            'stamp', 
            'reference', 
            'amount', 
            'currency', 
            'language', 
            'orderId', 
            'items', 
            'customer', 
            'deliveryAddress',
            'invoicingAddress',
            'manualInvoiceActivation',
            'redirectUrls',
            'callbackUrls',
            'callbackDelay',
            'groups',
            'usePricesWithoutVat'
        ];
        $required_fields = ['stamp', 'reference', 'amount', 'currency', 'language', 'customer', 'redirectUrls'];

        if($this->items) {
            $payload['items'] = $this->items;
            $payload['amount'] = 0;
            //Add up the amount by the item's 'unitPrice'.
            foreach($this->items as $item) {
                $payload['amount'] += $item['unitPrice'] * $item['units'];
            }
        }

        if($this->customer) {
            $payload['customer'] = $this->customer;
        }

        if($this->delivery_address) {
            $payload['deliveryAddress'] = $this->customer;
        }

        if($this->invoicing_address) {
            $payload['invoicingAddress'] = $this->customer;
        }

        if($this->callback_urls) {
            $payload['redirectUrls'] = $this->callback_urls;
        }

        //Validate all the fields mentioned in the above arrays. Make sure the payload matches.
        $this->body = $this->_validate_fields($all_fields, $required_fields, $payload);
    
        return $this;
    }

    public function createPayment() {
        $datetime = new DateTime();

        $headers = array(
            "checkout-account"      => $this->merchant_id,
            "checkout-algorithm"    => "sha256",
            "checkout-method"       => "POST",
            "checkout-nonce"        => uniqid(true),
            "checkout-timestamp"    => $datetime->format('Y-m-d\TH:i:s.u\Z'),
            "content-type"          => "application/json; charset=utf-8"
        );

        $body = json_encode($this->body, JSON_UNESCAPED_SLASHES);
        $headers['signature'] = $this->calculateHmac($this->merchant_secret, $headers, $body);
        
        //Map the headers so that we can insert them into cURL.
        $headers = array_map(function($key, $value) {
            return "{$key}: {$value}";
        }, array_keys($headers), $headers);

        $ch = curl_init('https://services.paytrail.com/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_close($ch);

        return curl_exec($ch);
    }
    
    protected function calculateHmac($secret, $params, $body = '') {

        // Keep only checkout- params, more relevant for response validation. Filter query
        // string parameters the same way - the signature includes only checkout- values.
        $includedKeys = array_filter(array_keys($params), function ($key) {
            return preg_match('/^checkout-/', $key);
        });

        // Keys must be sorted alphabetically
        sort($includedKeys, SORT_STRING);

        $hmacPayload =
            array_map(
                function ($key) use ($params) {
                    return join(':', [ $key, $params[$key] ]);
                },
                $includedKeys
            );

        array_push($hmacPayload, $body);

        return hash_hmac('sha256', join("\n", $hmacPayload), $secret);
    }

    /*
     * Global validation function for the request bodies.
    */
    protected function _validate_fields($all_fields, $required_fields, $payload) {

        if(!is_array($payload)) {
            throw new Paytrail_Exception("Invalid payload received for validating fields.");
        }

        //Check that all the required fields are filled in.
        if (count(array_intersect_key($required_fields, $payload)) === count($required_fields)) {
            throw new Paytrail_Exception("There are missing required fields from the payload.");
        }

        return $payload;
    }

}

?>