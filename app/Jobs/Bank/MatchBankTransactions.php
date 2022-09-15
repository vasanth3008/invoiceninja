<?php
/**
 * Credit Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Bank;

use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Helpers\Bank\Yodlee\Yodlee;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Libraries\MultiDB;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Bank\BankService;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MatchBankTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $company_id;

    private string $db;

    private array $input;

    protected Company $company;

    public Invoice $invoice;

    private BankTransaction $bt;

    /**
     * Create a new job instance.
     */
    public function __construct(int $company_id, string $db, array $input)
    {

        $this->company_id = $company_id;
        $this->db = $db;
        $this->input = $input;

    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        MultiDB::setDb($this->db);

        $this->company = Company::find($this->company_id);

        foreach($this->input as $match)
        {
            if(array_key_exists('invoice_id', $match) && strlen($match['invoice_id']) > 1)
                $this->matchInvoicePayment($match);
            elseif(array_key_exists('expense_id', $match) && strlen($match['expense_id']) > 1)
                $this->matchExpense($match);
        }

    }

    private function matchInvoicePayment(array $match) :void
    {
        $this->bt = BankTransaction::find($match['id']);

        $_invoice = Invoice::withTrashed()->find($match['invoice_id']);

        if(array_key_exists('amount', $match) && $match['amount'] > 0)
            $amount = $match['amount'];
        else
            $amount = $this->bt->amount;

        if($_invoice && $_invoice->isPayable()){

            $this->createPayment($match['id'], $amount);

        }

    }

    private function matchExpense(array $match) :void
    {

    }

    private function createPayment(int $invoice_id, float $amount) :void
    {

        \DB::connection(config('database.default'))->transaction(function () use($invoice_id, $amount) {

            $this->invoice = Invoice::withTrashed()->where('id', $invoice_id)->lockForUpdate()->first();

            $this->invoice
                ->service()
                ->setExchangeRate()
                ->updateBalance($amount * -1)
                ->updatePaidToDate($amount)
                ->setCalculatedStatus()
                ->save();

        }, 1);

        /* Create Payment */
        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $amount;
        $payment->applied = $amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $this->invoice->client_id;
        $payment->transaction_reference = $this->bt->transaction_id;
        $payment->currency_id = $this->harvestCurrencyId();
        $payment->is_manual = false;

        if ($this->invoice->company->timezone()) {
            $payment->date = now()->addSeconds($this->invoice->company->timezone()->utc_offset)->format('Y-m-d');
        }
        else {
            $payment->date = now();
        }

        /* Bank Transfer! */
        $payment_type_id = 1;

        $payment->saveQuietly();

        $payment->service()->applyNumber()->save();
        
        if($payment->client->getSetting('send_email_on_mark_paid'))
            $payment->service()->sendEmail();

        $this->setExchangeRate($payment);

        /* Create a payment relationship to the invoice entity */
        $payment->invoices()->attach($this->invoice->id, [
            'amount' => $amount,
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
             ->updateBalanceAndPaidToDate($amount*-1, $amount)
             ->save();

        $this->invoice = $this->invoice
                             ->service()
                             ->workFlow()
                             ->save();

        /* Update Invoice balance */
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $this->bt->is_matched = true;
        $this->bt->save();
    }

    private function harvestCurrencyId() :int
    {
        $currency = Currency::where('code', $this->bt->currency_code)->first();

        if($currency)
            return $currency->id;

        return $this->invoice->client->getSetting('currency_id');

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

    public function middleware()
    {
        return [new WithoutOverlapping($this->company_id)];
    }





}