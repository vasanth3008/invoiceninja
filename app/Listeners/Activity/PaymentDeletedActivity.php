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

namespace App\Listeners\Activity;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class PaymentDeletedActivity implements ShouldQueue
{
    protected $activity_repo;

    /**
     * Create the event listener.
     *
     * @param ActivityRepository $activity_repo
     */
    public function __construct(ActivityRepository $activity_repo)
    {
        $this->activity_repo = $activity_repo;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $payment = $event->payment;

        $user_id = array_key_exists('user_id', $event->event_vars) ? $event->event_vars['user_id'] : $event->payment->user_id;

        $invoices = $payment->invoices;

        $fields = new stdClass;

        $fields->payment_id = $payment->id;
        $fields->client_id = $payment->client_id;
        $fields->user_id = $user_id;
        $fields->company_id = $payment->company_id;
        $fields->activity_type_id = Activity::DELETE_PAYMENT;

        foreach ($invoices as $invoice) { //todo we may need to add additional logic if in the future we apply payments to other entity Types, not just invoices
            $fields->invoice_id = $invoice->id;

            $this->activity_repo->save($fields, $invoice, $event->event_vars);
        }

        if (count($invoices) == 0) {
            $this->activity_repo->save($fields, $payment, $event->event_vars);
        }
    }
}
