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

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Invoice\InvoiceWorkflowSettings;
use App\Jobs\Ninja\TransactionLog;
use App\Jobs\Payment\EmailPayment;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TransactionEvent;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Carbon;

class MarkPaid extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    private $payable_balance;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {

        /*Don't double pay*/
        if ($this->invoice->status_id == Invoice::STATUS_PAID) {
            return $this->invoice;
        }

        if ($this->invoice->status_id == Invoice::STATUS_DRAFT) {
            $this->invoice->service()->markSent()->save();
        }

        \DB::connection(config('database.default'))->transaction(function () {

            $this->invoice = Invoice::where('id', $this->invoice->id)->lockForUpdate()->first();

            $this->payable_balance = $this->invoice->balance;

            $this->invoice
                ->service()
                ->setExchangeRate()
                ->updateBalance($this->payable_balance * -1)
                ->updatePaidToDate($this->payable_balance)
                ->setStatus(Invoice::STATUS_PAID)
                ->save();

        }, 1);

        /* Create Payment */
        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $this->payable_balance;
        $payment->applied = $this->payable_balance;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $this->invoice->client_id;
        $payment->transaction_reference = ctrans('texts.manual_entry');
        $payment->currency_id = $this->invoice->client->getSetting('currency_id');
        $payment->is_manual = true;

        if ($this->invoice->company->timezone()) {
            $payment->date = now()->addSeconds($this->invoice->company->timezone()->utc_offset)->format('Y-m-d');
        }

        $payment_type_id = $this->invoice->client->getSetting('payment_type_id');

        if ((int) $payment_type_id > 0) {
            $payment->type_id = (int) $payment_type_id;
        }

        $payment->saveQuietly();

        $payment->service()->applyNumber()->save();
        
        if($payment->company->getSetting('send_email_on_mark_paid'))
            $payment->service()->sendEmail();

        $this->setExchangeRate($payment);

        /* Create a payment relationship to the invoice entity */
        $payment->invoices()->attach($this->invoice->id, [
            'amount' => $this->payable_balance,
        ]);

        event('eloquent.created: App\Models\Payment', $payment);

        $this->invoice->next_send_date = null;

        $this->invoice
                ->service()
                ->applyNumber()
                ->touchPdf()
                ->save();

        $payment->ledger()
                ->updatePaymentBalance($this->payable_balance * -1);

        //06-09-2022
        $this->invoice
             ->client
             ->service()
             ->updateBalanceAndPaidToDate($payment->amount*-1, $payment->amount)
             ->save();

        $this->invoice = $this->invoice
                             ->service()
                             ->workFlow()
                             ->save();

        /* Update Invoice balance */
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $transaction = [
            'invoice' => $this->invoice->transaction_event(),
            'payment' => $payment->transaction_event(),
            'client' => $this->invoice->client->transaction_event(),
            'credit' => [],
            'metadata' => [],
        ];

        TransactionLog::dispatch(TransactionEvent::INVOICE_MARK_PAID, $transaction, $this->invoice->company->db);

        return $this->invoice;
    }

    private function setExchangeRate(Payment $payment)
    {
        if ($payment->exchange_rate != 1) {
            return;
        }

        $client_currency = $payment->client->getSetting('currency_id');
        $company_currency = $payment->client->company->settings->currency_id;

        if ($company_currency != $client_currency) {
            $exchange_rate = new CurrencyApi();

            $payment->exchange_rate = $exchange_rate->exchangeRate($client_currency, $company_currency, Carbon::parse($payment->date));
            //$payment->exchange_currency_id = $client_currency; // 23/06/2021
            $payment->exchange_currency_id = $company_currency;

            $payment->saveQuietly();
        }
    }
}
