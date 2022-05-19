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

namespace App\PaymentDrivers\Forte;

use App\Models\Payment;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\PaymentDrivers\FortePaymentDriver;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class CreditCard
{
    use MakesHash;
    
    public $forte;

    private $forte_base_uri="";
    private $forte_api_access_id="";
    private $forte_secure_key="";
    private $forte_auth_organization_id="";
    private $forte_organization_id="";
    private $forte_location_id="";
    
    public function __construct(FortePaymentDriver $forte)
    {
        $this->forte = $forte;

        $this->forte_base_uri = "https://sandbox.forte.net/api/v3/";
            if($this->forte->company_gateway->getConfigField('testMode') == false){
                $this->forte_base_uri = "https://api.forte.net/v3/";
            }
        $this->forte_api_access_id = $this->forte->company_gateway->getConfigField('apiAccessId');
        $this->forte_secure_key = $this->forte->company_gateway->getConfigField('secureKey');
        $this->forte_auth_organization_id = $this->forte->company_gateway->getConfigField('authOrganizationId');
        $this->forte_organization_id = $this->forte->company_gateway->getConfigField('organizationId');
        $this->forte_location_id = $this->forte->company_gateway->getConfigField('locationId');
    }

    public function authorizeView(array $data)
    {
        return render('gateways.forte.credit_card.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $payment_meta = new \stdClass;
        $payment_meta->exp_month = (string) $request->expire_month;
        $payment_meta->exp_year = (string) $request->expire_year;
        $payment_meta->brand = (string) $request->card_type;
        $payment_meta->last4 = (string) $request->last_4;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $request->one_time_token,
            'payment_method_id' => $request->payment_method_id,
        ];

        $this->forte->storeGatewayToken($data);

        return redirect()->route('client.payment_methods.index')->withSuccess('Payment Method added.');
    }

    public function paymentView(array $data)
    {
        $this->forte->payment_hash->data = array_merge((array) $this->forte->payment_hash->data, $data);
        $this->forte->payment_hash->save();

        $data['gateway'] = $this;
        return render('gateways.forte.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $payment_hash = PaymentHash::whereRaw('BINARY `hash`= ?', [$request->input('payment_hash')])->firstOrFail();

        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->forte_base_uri.'organizations/'.$this->forte_organization_id.'/locations/'.$this->forte_location_id.'/transactions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>'{
                     "action":"sale", 
                     "authorization_amount":'.$payment_hash->data->total->amount_with_fee.',
                     "service_fee_amount":'.$payment_hash->data->total->fee_total.',
                     "billing_address":{
                        "first_name":"'.$this->forte->client->name.'",
                        "last_name":"'.$this->forte->client->name.'"
                     },
                     "card":{
                        "one_time_token":"'.$request->payment_token.'"
                     }
              }',
                CURLOPT_HTTPHEADER => array(
                  'Content-Type: application/json',
                  'X-Forte-Auth-Organization-Id: '.$this->forte_organization_id,
                  'Authorization: Basic '.base64_encode($this->forte_api_access_id.':'.$this->forte_secure_key)
                ),
              ));

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            $response=json_decode($response);
        } catch (\Throwable $th) {
            throw $th;
        }
        if ($httpcode>299) {
            $error = Validator::make([], []);
            $error->getMessageBag()->add('gateway_error', $response->response->response_desc);
            return redirect('client/invoices')->withErrors($error);
        }

        $data = [
            'payment_method' => $request->payment_method_id,
            'payment_type' => PaymentType::parseCardType(strtolower($request->card_brand)) ?: PaymentType::CREDIT_CARD_OTHER,
            'amount' => $payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->transaction_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];
        $payment=$this->forte->createPayment($data, Payment::STATUS_COMPLETED);
        return redirect('client/invoices')->withSuccess('Invoice paid.');
    }
}
