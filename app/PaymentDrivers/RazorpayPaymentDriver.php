<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;

class RazorpayPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    public $gateway;

    public $payment_method;

    public static $methods = [
        GatewayType::HOSTED_PAGE => Hosted::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_RAZORPAY;

    public function gatewayTypes(): array
    {
        return [
            GatewayType::HOSTED_PAGE,
        ];
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        return $this->payment_method->yourRefundImplementationHere();
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return $this->payment_method->yourTokenBillingImplmentation();
    }
}
