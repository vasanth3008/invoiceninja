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

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;

class SEPA
{
    /** @var StripePaymentDriver */
    public StripePaymentDriver $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;

        $this->stripe->init();
    }

    public function authorizeView($data)
    {
        $data['gateway'] = $this->stripe;
        $data['payment_method_id'] = GatewayType::SEPA;
        $data['client'] = $this->stripe->client;
        $data['country'] = $this->stripe->client->country->iso_3166_2;
        $data['currency'] = $this->stripe->client->currency();
        
        return render('gateways.stripe.sepa.authorize', $data);
    }

    public function paymentView(array $data)
    {
        $data['gateway'] = $this->stripe;
        $data['payment_method_id'] = GatewayType::SEPA;
        $data['stripe_amount'] = $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency());
        $data['client'] = $this->stripe->client;
        $data['customer'] = $this->stripe->findOrCreateCustomer()->id;
        $data['country'] = $this->stripe->client->country->iso_3166_2;
        $data['payment_hash'] = $this->stripe->payment_hash->hash;

        $intent_data = [
            'amount' => $data['stripe_amount'],
            'currency' => 'eur',
            'payment_method_types' => ['sepa_debit'],
            'setup_future_usage' => 'off_session',
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $this->stripe->decodeUnicodeString(ctrans('texts.invoices') . ': ' . collect($data['invoices'])->pluck('invoice_number')),
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::SEPA,
            ],
        ];

        $intent = \Stripe\PaymentIntent::create($intent_data, $this->stripe->stripe_connect_auth);

        $data['pi_client_secret'] = $intent->client_secret;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $data['stripe_amount']]);
        $this->stripe->payment_hash->save();

        return render('gateways.stripe.sepa.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $gateway_response = json_decode($request->gateway_response);

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $request->all());
        $this->stripe->payment_hash->save();

        if (property_exists($gateway_response, 'status') && ($gateway_response->status == 'processing' || $gateway_response->status === 'succeeded')) {
            if ($request->store_card) {
                $this->storePaymentMethod($gateway_response);
            }

            return $this->processSuccessfulPayment($gateway_response->id);
        }

        return $this->processUnsuccessfulPayment();
    }

    public function processSuccessfulPayment(string $payment_intent)
    {
        $data = [
            'payment_method' => $payment_intent,
            'payment_type' => PaymentType::SEPA,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent,
            'gateway_type_id' => GatewayType::SEPA,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', $payment->hashed_id);
    }

    public function processUnsuccessfulPayment()
    {
        $server_response = $this->stripe->payment_hash->data;

        $this->stripe->sendFailureMail($server_response);

        $message = [
            'server_response' => $server_response,
            'data' => $this->stripe->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }


    private function storePaymentMethod($intent)
    {
        try {
            $method = $this->stripe->getStripePaymentMethod($intent->payment_method);

            $payment_meta = new \stdClass;
            $payment_meta->brand = (string) \sprintf('%s (%s)', $method->sepa_debit->bank_code, ctrans('texts.sepa'));
            $payment_meta->last4 = (string) $method->sepa_debit->last4;
            $payment_meta->state = 'authorized';
            $payment_meta->type = GatewayType::SEPA;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $intent->payment_method,
                'payment_method_id' => GatewayType::SEPA,
            ];

            $this->stripe->storeGatewayToken($data, ['gateway_customer_reference' => $method->customer]);
        } catch (\Exception $e) {
            return $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }
}
