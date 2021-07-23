<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\PayTrace\CreditCard;
use App\Utils\CurlUtils;
use App\Utils\Traits\MakesHash;

class PaytracePaymentDriver extends BaseDriver
{
    use MakesHash;
    
    public $refundable = true; 

    public $token_billing = true; 

    public $can_authorise_credit_card = true; 

    public $gateway; 

    public $payment_method; 

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class, //maps GatewayType => Implementation class
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYTRACE; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public function init()
    {
        return $this; /* This is where you boot the gateway with your auth credentials*/
    }

    /* Returns an array of gateway types for the payment gateway */
    public function gatewayTypes(): array
    {
        $types = [];

            $types[] = GatewayType::CREDIT_CARD;

        return $types;
    }

    /* Sets the payment method initialized */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data); //this is your custom implementation from here
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);  //this is your custom implementation from here
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        // $cgt = ClientGatewayToken::where('company_gateway_id', $payment->company_gateway_id)
        //                          ->where('gateway_type_id', $payment->gateway_type_id)
        //                          ->first();

        $data = [
            'amount' => $amount,
            //'customer_id' => $cgt->token,
            'transaction_id' => $payment->transaction_reference,
            'integrator_id' => '959195xd1CuC'
        ];

        $response = $this->gatewayRequest('/v1/transactions/refund/for_transaction', $data);

        if($response && $response->success)
        {

            SystemLogger::dispatch(['server_response' => $response, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_PAYTRACE, $this->client, $this->client->company);

                return [
                    'transaction_reference' => $response->transaction_id,
                    'transaction_response' => json_encode($response),
                    'success' => true,
                    'description' => $response->status_message,
                    'code' => $response->response_code,
                ];

        }

        SystemLogger::dispatch(['server_response' => $response, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_PAYTRACE, $this->client, $this->client->company);

        return [
            'transaction_reference' => null,
            'transaction_response' => json_encode($response),
            'success' => false,
            'description' => $response->status_message,
            'code' => 422,
        ];

    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $data = [
            'customer_id' => $cgt->token,
            'integrator_id' => '959195xd1CuC',
            'amount' => $amount,
        ];

        $response = $this->gatewayRequest('/v1/transactions/sale/by_customer', $data);

        if($response && $response->success)
        {
            $data = [
                'gateway_type_id' => $cgt->gateway_type_id,
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'transaction_reference' => $response->transaction_id,
                'amount' => $amount,
            ];

            $payment = $this->createPayment($data);
            $payment->meta = $cgt->meta;
            $payment->save();

            $payment_hash->payment_id = $payment->id;
            $payment_hash->save();

            return $payment;
        }

        $error = $response->status_message;

        if(property_exists($response, 'approval_message') && $response->approval_message)
            $error .= " - {$response->approval_message}";

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => 500,
        ];

        $this->processUnsuccessfulTransaction($data, false);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {
    }

    /*Helpers*/
    private function generateAuthHeaders()
    {

        $url = 'https://api.paytrace.com/oauth/token';
        $data = [
            'grant_type' => 'password',
            'username' => config('ninja.testvars.paytrace.username'),
            'password' => config('ninja.testvars.paytrace.password'),
            //'username' => $this->company_gateway->getConfigField('username'),
            //'password' => $this->company_gateway->getConfigField('password')
        ];

        $response = CurlUtils::post($url, $data, $headers = false);

        $auth_data = json_decode($response);

            $headers = [];
            $headers[] = 'Content-type: application/json';
            $headers[] = 'Authorization: Bearer '.$auth_data->access_token;

        return $headers;

    }

    public function getAuthToken()
    {

        $headers = $this->generateAuthHeaders();

        $response = CurlUtils::post('https://api.paytrace.com/v1/payment_fields/token/create', [], $headers);

        $response = json_decode($response);

        if($response)
            return $response->clientKey;

        return false;
    }

    public function gatewayRequest($uri, $data, $headers = false)
    {
        
        $base_url = "https://api.paytrace.com{$uri}";

        $headers = $this->generateAuthHeaders();

        $response = CurlUtils::post($base_url, json_encode($data), $headers);

        $response = json_decode($response);

        if($response)
            return $response;

        return false;

    }
}
