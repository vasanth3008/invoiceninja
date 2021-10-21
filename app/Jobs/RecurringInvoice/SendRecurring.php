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

namespace App\Jobs\RecurringInvoice;

use App\DataMapper\Analytics\SendRecurringFailure;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Factory\InvoiceInvitationFactory;
use App\Factory\RecurringInvoiceToInvoiceFactory;
use App\Jobs\Entity\EmailEntity;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceValues;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Turbo124\Beacon\Facades\LightLogs;

class SendRecurring implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use GeneratesCounter;
    use MakesHash;

    public $recurring_invoice;

    protected $db;

    public $tries = 1;
    
    /**
     * Create a new job instance.
     *
     * @param RecurringInvoice $recurring_invoice
     * @param string $db
     */
    public function __construct(RecurringInvoice $recurring_invoice, string $db = 'db-ninja-01')
    {
        $this->recurring_invoice = $recurring_invoice;
        $this->db = $db;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {

        // Generate Standard Invoice
        $invoice = RecurringInvoiceToInvoiceFactory::create($this->recurring_invoice, $this->recurring_invoice->client);

        if($this->recurring_invoice->auto_bill == "always")
            $invoice->auto_bill_enabled = true;
        elseif($this->recurring_invoice->auto_bill == "off")
            $invoice->auto_bill_enabled = false;

        $invoice->date = now()->format('Y-m-d');
        $invoice->due_date = $this->recurring_invoice->calculateDueDate(now()->format('Y-m-d'));
        $invoice->recurring_id = $this->recurring_invoice->id;
        
        if($invoice->client->getSetting('auto_email_invoice'))
        {
            $invoice = $invoice->service()
                               ->markSent()
                               ->applyNumber()
                               // ->createInvitations() //need to only link invitations to those in the recurring invoice
                               ->fillDefaults()
                               ->save();
            
            $invoice = $this->createRecurringInvitations($invoice);

        }
        else{

            $invoice = $invoice->service()
                               ->fillDefaults()
                               ->save();            
        }

        nlog("updating recurring invoice dates");
        /* Set next date here to prevent a recurring loop forming */
        $this->recurring_invoice->next_send_date = $this->recurring_invoice->nextSendDate();
        $this->recurring_invoice->remaining_cycles = $this->recurring_invoice->remainingCycles();
        $this->recurring_invoice->last_sent_date = now();

        /* Set completed if we don't have any more cycles remaining*/
        if ($this->recurring_invoice->remaining_cycles == 0) {
            $this->recurring_invoice->setCompleted();
        }

        nlog("next send date = " . $this->recurring_invoice->next_send_date);
        nlog("remaining cycles = " . $this->recurring_invoice->remaining_cycles);
        nlog("last send date = " . $this->recurring_invoice->last_sent_date);

        $this->recurring_invoice->save();
        
        /*

        if ($this->recurring_invoice->company->pause_recurring_until_paid){
            $this->recurring_invoice->service()
                                    ->stop();
        }

        */


        //Admin notification for recurring invoice sent. 
        if ($invoice->invitations->count() >= 1 ) {
            $invoice->entityEmailEvent($invoice->invitations->first(), 'invoice', 'email_template_invoice');
        }
    
        nlog("Invoice {$invoice->number} created");

        $invoice->invitations->each(function ($invitation) use ($invoice) {
            if ($invitation->contact && !$invitation->contact->trashed() && strlen($invitation->contact->email) >=1 && $invoice->client->getSetting('auto_email_invoice')) {

                try{
                    EmailEntity::dispatch($invitation, $invoice->company)->delay(now()->addSeconds(1));
                }
                catch(\Exception $e) {
                    nlog($e->getMessage());
                }

                nlog("Firing email for invoice {$invoice->number}");
            }
        });
    
        if ($invoice->client->getSetting('auto_bill_date') == 'on_send_date' && $invoice->auto_bill_enabled) {
            nlog("attempting to autobill {$invoice->number}");
            $invoice->service()->autoBill()->save();

        }
        elseif($invoice->client->getSetting('auto_bill_date') == 'on_due_date' && $invoice->auto_bill_enabled) {

            if($invoice->due_date && Carbon::parse($invoice->due_date)->startOfDay()->lte(now()->startOfDay())) {
            
                nlog("attempting to autobill {$invoice->number}");
                $invoice->service()->autoBill()->save();
            
            }

        }

    }

    /**
     * Only create the invitations that are defined on the recurring invoice.
     * @param  Invoice $invoice 
     * @return Invoice $invoice
     */
    private function createRecurringInvitations($invoice) :Invoice
    {

        $this->recurring_invoice->invitations->each(function ($recurring_invitation) use($invoice){

            $ii = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
            $ii->key = $this->createDbHash(config('database.default'));
            $ii->invoice_id = $invoice->id;
            $ii->client_contact_id = $recurring_invitation->client_contact_id;
            $ii->save();

        });


        return $invoice->fresh();
    }

    public function failed($exception = null)
    {
        nlog('the job failed');

        $job_failure = new SendRecurringFailure();
        $job_failure->string_metric5 = get_class($this);
        $job_failure->string_metric6 = $exception->getMessage();

        LightLogs::create($job_failure)
                 ->batch();

        nlog(print_r($exception->getMessage(), 1));
    }
}
