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

namespace App\Jobs\Cron;

use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\Auth;
use App\Utils\Traits\SubscriptionHooker;
use Illuminate\Foundation\Bus\Dispatchable;

class SubscriptionCron
{
    use Dispatchable;
    use SubscriptionHooker;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        nlog('Subscription Cron');

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            $invoices = Invoice::where('is_deleted', 0)
                              ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                              ->where('balance', '>', 0)
                              ->where('is_proforma', 0)
                              ->whereDate('due_date', '<=', now()->addDay()->startOfDay())
                              ->whereNull('deleted_at')
                              ->whereNotNull('subscription_id')
                              ->cursor();

            $invoices->each(function (Invoice $invoice) {
                $subscription = $invoice->subscription;

                $body = [
                    'context' => 'plan_expired',
                    'client' => $invoice->client->hashed_id,
                    'invoice' => $invoice->hashed_id,
                    'subscription' => $subscription->hashed_id,
                ];

                $this->sendLoad($subscription, $body);
                //This will send the notification daily.
                //We'll need to handle this by performing some action on the invoice to either archive it or delete it?
            });
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $invoices = Invoice::where('is_deleted', 0)
                                  ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                  ->where('balance', '>', 0)
                                  ->where('is_proforma', 0)
                                  ->whereDate('due_date', '<=', now()->addDay()->startOfDay())
                                  ->whereNull('deleted_at')
                                  ->whereNotNull('subscription_id')
                                  ->cursor();

                $invoices->each(function (Invoice $invoice) {
                    $subscription = $invoice->subscription;

                    $body = [
                        'context' => 'plan_expired',
                        'client' => $invoice->client->hashed_id,
                        'invoice' => $invoice->hashed_id,
                        'subscription' => $subscription->hashed_id,
                    ];

                    $this->sendLoad($subscription, $body);
                    //This will send the notification daily.
                    //We'll need to handle this by performing some action on the invoice to either archive it or delete it?
                });
            }
        }
    }

    //Requires the crons to be updated and set to hourly @ 00:01
    private function timezoneAware()
    {
        $grouped_company_ids = 

        Invoice::select('company_id')
                ->where('is_deleted', 0)
                ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                ->where('balance', '>', 0)
                ->where('is_proforma', 0)
                ->whereDate('due_date', '<=', now()->addDay()->startOfDay())
                ->whereNull('deleted_at')
                ->whereNotNull('subscription_id')
                ->groupBy('company_id')
                ->cursor()
                ->each(function ($company_id){

                    $company = Company::find($company_id);

                    $timezone_now = now()->setTimezone($company->timezone()->name);

                    //Capture companies within the window of 00:00 and 00:30
                    if($timezone_now->gt($timezone_now->copy()->startOfDay()) && $timezone_now->lt($timezone_now->copy()->startOfDay()->addMinutes(30))) {

                        Invoice::query()
                                ->where('company_id', $company->id)
                                ->whereNull('deleted_at')
                                ->where('is_deleted', 0)
                                ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                ->where('is_proforma', 0)
                                ->whereNotNull('subscription_id')
                                ->where('balance', '>', 0)
                                ->whereDate('due_date', '<=', now()->setTimezone($company->timezone()->name)->addDay()->startOfDay())
                                ->cursor()
                                ->each(function (Invoice $invoice) {

                                    $subscription = $invoice->subscription;

                                    $body = [
                                        'context' => 'plan_expired',
                                        'client' => $invoice->client->hashed_id,
                                        'invoice' => $invoice->hashed_id,
                                        'subscription' => $subscription->hashed_id,
                                    ];

                                    $this->sendLoad($subscription, $body);
                                    //This will send the notification daily.
                                    //We'll need to handle this by performing some action on the invoice to either archive it or delete it?
                        });

                    }


                });

    }
}
