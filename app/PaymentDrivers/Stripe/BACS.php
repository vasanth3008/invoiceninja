<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
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
use App\PaymentDrivers\Stripe\Jobs\UpdateCustomer;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use App\Utils\Number;

class BACS
{
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView(array $data)
    {
        $customer = $this->stripe->findOrCreateCustomer();
        $data['session'] = Session::create([
            'payment_method_types' => ['bacs_debit'],
            'mode' => 'setup',
            'customer' => $customer->id,
            'success_url' => str_replace("%7B", "{", str_replace("%7D", "}", $this->buildAuthorizeUrl())),
            'cancel_url' => route('client.payment_methods.index'),
        ]);
        return render('gateways.stripe.bacs.authorize', $data);
    }
    private function buildAuthorizeUrl(): string
    {
        return route('client.payment_methods.confirm', [
            'method' => GatewayType::BACS,
            'session_id' => "{CHECKOUT_SESSION_ID}",
        ]);
    }

    public function authorizeResponse($request)
    {
        $this->stripe->init();
        if ($request->session_id) {
            $session = $this->stripe->stripe->checkout->sessions->retrieve($request->session_id, ['expand' => ['setup_intent']]);

            $customer = $this->stripe->findOrCreateCustomer();
            $this->stripe->attach($session->setup_intent->payment_method, $customer);
            $payment_method =  $this->stripe->getStripePaymentMethod($session->setup_intent->payment_method);
            $this->storePaymentMethod($payment_method, $customer);
        }
        return redirect()->route('client.payment_methods.index');
    }
    public function paymentView(array $data)
    {

        // $description = $this->stripe->decodeUnicodeString(ctrans('texts.invoices') . ': ' . collect($data['invoices'])->pluck('invoice_number')) . " for client {$this->stripe->client->present()->name()}";
        $invoice_numbers = collect($data['invoices'])->pluck('invoice_number')->implode(',');
        $description = ctrans('texts.stripe_payment_text', ['invoicenumber' => $invoice_numbers, 'amount' => Number::formatMoney($data['total']['amount_with_fee'], $this->stripe->client), 'client' => $this->stripe->client->present()->name()]);

        $payment_intent_data = [
            'amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $description,
            'payment_method_types' => ['bacs_debit'],
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::BACS,
            ],
            'confirm' => true,
        ];
        $data['intent'] = $payment_intent_data;
        $data['gateway'] = $this->stripe;

        return render('gateways.stripe.bacs.pay', $data);
    }
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->stripe->init();
        nlog($request);

        $state = [
            'server_response' => json_decode($request->gateway_response),
            'payment_hash' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());

        if ($request->has('token') && ! is_null($request->token)) {
            $state['store_card'] = false;
        }

        $state['payment_intent'] = PaymentIntent::retrieve($state['server_response']->id, array_merge($this->stripe->stripe_connect_auth, ['idempotency_key' => uniqid("st",true)]));
        $state['customer'] = $state['payment_intent']->customer;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
        $this->stripe->payment_hash->save();

        $server_response = $this->stripe->payment_hash->data->server_response;

        if ($server_response->status == 'succeeded') {
            $this->stripe->logSuccessfulGatewayResponse(['response' => json_decode($request->gateway_response), 'data' => $this->stripe->payment_hash], SystemLog::TYPE_STRIPE);

            return $this->processSuccessfulPayment();
        }

        return $this->processUnsuccessfulPayment($server_response);
    }

    public function processSuccessfulPayment()
    {
        UpdateCustomer::dispatch($this->stripe->company_gateway->company->company_key, $this->stripe->company_gateway->id, $this->stripe->client->id);

        $stripe_method = $this->stripe->getStripePaymentMethod($this->stripe->payment_hash->data->server_response->payment_method);

        $data = [
            'payment_method' => $this->stripe->payment_hash->data->server_response->payment_method,
            'payment_type' => PaymentType::parseCardType(strtolower($stripe_method->card->brand)) ?: PaymentType::CREDIT_CARD_OTHER,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->server_response->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => isset($this->stripe->payment_hash->data->payment_intent->latest_charge) ? $this->stripe->payment_hash->data->payment_intent->latest_charge : optional($this->stripe->payment_hash->data->payment_intent->charges->data[0])->id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['amount' => $data['amount']]);
        $this->stripe->payment_hash->save();

        if ($this->stripe->payment_hash->data->store_card) {
            $customer = new \stdClass;
            $customer->id = $this->stripe->payment_hash->data->customer;

            $this->stripe->attach($this->stripe->payment_hash->data->server_response->payment_method, $customer);

            $stripe_method = $this->stripe->getStripePaymentMethod($this->stripe->payment_hash->data->server_response->payment_method);

            $this->storePaymentMethod($stripe_method, $this->stripe->payment_hash->data->payment_method_id, $customer);
        }

        $payment = $this->stripe->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data->server_response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        //If the user has come from a subscription double check here if we need to redirect.
        //08-08-2022
        if($payment->invoices()->whereHas('subscription')->exists()){
            $subscription = $payment->invoices()->first()->subscription;

            if($subscription && array_key_exists('return_url', $subscription->webhook_configuration) && strlen($subscription->webhook_configuration['return_url']) >=1)
            return redirect($subscription->webhook_configuration['return_url']);

        }
        //08-08-2022

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($server_response)
    {
        $this->stripe->sendFailureMail($server_response->cancellation_reason);

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

    private function storePaymentMethod($method, $customer)
    {
        try {
            $payment_meta = new \stdClass;
            $payment_meta->brand = (string) $method->bacs_debit->sort_code;
            $payment_meta->last4 = (string) $method->bacs_debit->last4;
            $payment_meta->state = 'authorized';
            $payment_meta->type = GatewayType::BACS;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->id,
                'payment_method_id' => GatewayType::BACS,
            ];

            $this->stripe->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);
        } catch (\Exception $e) {
            return $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }
}
