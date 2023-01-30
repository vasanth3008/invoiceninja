<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Mail;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\ClientPaymentFailureObject;
use App\Mail\Admin\EntityNotificationMailer;
use App\Mail\Admin\PaymentFailureObject;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer implemented*/

class PaymentFailedMailer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UserNotifies, MakesHash;

    public ?PaymentHash $payment_hash;

    public $error;

    public Company $company;

    public Client $client;

    /**
     * Create a new job instance.
     *
     * @param $client
     * @param $message
     * @param $company
     * @param $amount
     */
    public function __construct(?PaymentHash $payment_hash, Company $company, Client $client, $error)
    {
        $this->payment_hash = $payment_hash;
        $this->client = $client;
        $this->error = $error;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(!is_string($this->error)){
            $this->error = "Payment failed, no reason given.";
        }

        //Set DB
        MultiDB::setDb($this->company->db);
        App::setLocale($this->client->locale());

        $settings = $this->client->getMergedSettings();

        $amount = 0;
        $invoice = false;

        if ($this->payment_hash) {
            $amount = array_sum(array_column($this->payment_hash->invoices(), 'amount')) + $this->payment_hash->fee_total;
            $invoice = Invoice::whereIn('id', $this->transformKeys(array_column($this->payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();
        }

        //iterate through company_users
        $this->company->company_users->each(function ($company_user) use ($amount, $settings, $invoice) {
            $methods = $this->findUserEntityNotificationType($invoice ?: $this->client, $company_user, ['payment_failure_user', 'payment_failure_all', 'payment_failure', 'all_notifications']);

            //if mail is a method type -fire mail!!
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $mail_obj = (new PaymentFailureObject($this->client, $this->error, $this->company, $amount, $this->payment_hash))->build();

                $nmo = new NinjaMailerObject;
                $nmo->mailable = new NinjaMailer($mail_obj);
                $nmo->company = $this->company;
                $nmo->to_user = $company_user->user;
                $nmo->settings = $settings;

                NinjaMailerJob::dispatch($nmo);
            }
        });

        //add client payment failures here.
        //
        if ($this->client->contacts()->whereNotNull('email')->exists() && $this->payment_hash) {
            $contact = $this->client->contacts()->whereNotNull('email')->first();

            $mail_obj = (new ClientPaymentFailureObject($this->client, $this->error, $this->company, $this->payment_hash))->build();

            $nmo = new NinjaMailerObject;
            $nmo->mailable = new NinjaMailer($mail_obj);
            $nmo->company = $this->company;
            $nmo->to_user = $contact;
            $nmo->settings = $settings;

            NinjaMailerJob::dispatch($nmo);
        }
    }
}
