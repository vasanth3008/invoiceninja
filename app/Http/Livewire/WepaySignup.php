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

namespace App\Http\Livewire;

use App\Factory\CompanyGatewayFactory;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\User;
use App\PaymentDrivers\WePayPaymentDriver;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use WePay;

class WepaySignup extends Component
{
    public $user;
    public $user_id;
    public $company_key;
    public $first_name;
    public $last_name;
    public $email;
    public $company_name;
    public $country;
    public $ach;
    public $wepay_payment_tos_agree;
    public $debit_cards;

    public $terms;
    public $privacy_policy;

    public $saved;
    public $company;

    protected $rules = [
        'first_name' => ['required'],
        'last_name' => ['required'],
        'email' => ['required', 'email'],
        'company_name' => ['required'],
        'country' => ['required'],
        'ach' => ['sometimes'],
        'wepay_payment_tos_agree' => ['accepted'],
        'debit_cards' => ['sometimes'],
    ];

    public function mount()
    {
        $user = User::find($this->user_id);
        $this->company = Company::where('company_key', $this->company_key)->firstOrFail();
            
        $this->fill([
            'wepay_payment_tos_agree' => '',
            'ach' => '',
            'country' => 'US',
            'user' => $user,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'company_name' => $this->company->present()->name(),
            'saved' => ctrans('texts.confirm'),
            'terms' => '<a href="https://go.wepay.com/terms-of-service" target="_blank">'.ctrans('texts.terms_of_service').'</a>',
            'privacy_policy' => '<a href="https://go.wepay.com/privacy-policy" target="_blank">'.ctrans('texts.privacy_policy').'</a>',
        ]);
    }

    public function render()
    {
      return render('gateways.wepay.signup.wepay-signup');
    }

    public function submit()
    {
        //need to create or get a new WePay CompanyGateway
        $cg = CompanyGateway::where('id', 49)
                            ->where('company_id', $this->company->id)
                            ->firstOrNew();

        if(!$cg->id) {
            $cg = CompanyGatewayFactory::create($this->company->id, $this->user->id);
            $cg->gateway_key = '8fdeed552015b3c7b44ed6c8ebd9e992';
            $cg->require_cvv = false;
            $cg->require_billing_address = false;
            $cg->require_shipping_address = false;
            $cg->update_details = false;
            $cg->config = encrypt(config('ninja.testvars.checkout'));
            $cg->save();
        }

        $data = $this->validate($this->rules);


// nlog($data);

        $this->saved = ctrans('texts.processing');

        $wepay_driver = new WePayPaymentDriver($cg, null, null);

        $wepay = $wepay_driver->init()->wepay;

        $user_details = [
            'client_id' => config('ninja.wepay.client_id'),
            'client_secret' => config('ninja.wepay.client_secret'),
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'original_ip' => request()->ip(),
            'original_device' => request()->server('HTTP_USER_AGENT'),
            'tos_acceptance_time' => time(),
            'redirect_uri' => route('wepay.process_signup'),
            'scope' => 'manage_accounts,collect_payments,view_user,preapprove_payments,send_money',
        ];

        $wepay_user = $wepay->request('user/register/', $user_details);

        $access_token = $wepay_user->access_token;

        $access_token_expires = $wepay_user->expires_in ? (time() + $wepay_user->expires_in) : null;

        $wepay = new WePay($access_token);

            $account_details = [
                'name' => $data['company_name'],
                'description' => ctrans('texts.wepay_account_description'),
                'theme_object' => json_decode('{"name":"Invoice Ninja","primary_color":"0b4d78","secondary_color":"0b4d78","background_color":"f8f8f8","button_color":"33b753"}'),
                'callback_uri' => route('payment_webhook', ['company_key' => $this->company->company_key, 'company_gateway_id' => $cg->hashed_id]),
                'rbits' => $this->company->rBits(),
                'country' => $data['country'],
            ];

            if ($data['country'] == 'CA') {
                $account_details['currencies'] = ['CAD'];
                $account_details['country_options'] = ['debit_opt_in' => boolval($data['debit_cards'])];
            } elseif ($data['country'] == 'GB') {
                $account_details['currencies'] = ['GBP'];
            }

            $wepay_account = $wepay->request('account/create/', $account_details);

            try {
                $wepay->request('user/send_confirmation/', []);
                $confirmation_required = true;
            } catch (\WePayException $ex) {
                if ($ex->getMessage() == 'This access_token is already approved.') {
                    $confirmation_required = false;
                } else {
                    throw $ex;
                }
            }

    }
}
