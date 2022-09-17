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

namespace App\Transformers;

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;

/**
 * Class BankTransactionTransformer.
 */
class BankTransactionTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'company',
        'account',
        'invoice',
        'expense',
        'bank_account',
    ];

    /**
     * @param BankTransaction $bank_integration
     * @return array
     */
    public function transform(BankTransaction $bank_transaction)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($bank_transaction->id),
            'bank_integration_id' => (string) $this->encodePrimaryKey($bank_transaction->bank_integration_id),
            'transaction_id' => (int) $bank_transaction->transaction_id,
            'amount' => (float) $bank_transaction->amount ?: 0,
            'currency_code' => (string) $bank_transaction->currency_code ?: '',
            'account_type' => (string) $bank_transaction->account_type ?: '',
            'category_id' => (int) $bank_transaction->category_id,
            'category_type' => (string) $bank_transaction->category_type ?: '',
            'date' => (string) $bank_transaction->date ?: '',
            'bank_account_id' => (int) $bank_transaction->bank_account_id,
            'description' => (string) $bank_transaction->description ?: '',
            'base_type' => (string) $bank_transaction->base_type ?: '',
            'invoice_id' => (string) $this->encodePrimaryKey($bank_transaction->invoice_id) ?: '',
            'expense_id'=> (string) $this->encodePrimaryKey($bank_transaction->expense_id) ?: '',
            'is_matched'=> (bool) $bank_transaction->is_matched,
            'is_deleted' => (bool) $bank_transaction->is_deleted,
            'provisional_match' => (bool) $bank_transaction->provisional_match,
            'created_at' => (int) $bank_transaction->created_at,
            'updated_at' => (int) $bank_transaction->updated_at,
            'archived_at' => (int) $bank_transaction->deleted_at,
        ];
    }

    public function includeAccount(BankTransaction $bank_transaction)
    {
        $transformer = new AccountTransformer($this->serializer);

        return $this->includeItem($bank_transaction->account, $transformer, Account::class);
    }

    public function includeCompany(BankTransaction $bank_transaction)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($bank_transaction->company, $transformer, Company::class);
    }

    public function includeInvoice(BankTransaction $bank_transaction)
    {
        $transformer = new InvoiceTransformer($this->serializer);

        return $this->includeItem($bank_transaction->invoice, $transformer, Invoice::class);
    }

    public function includeExpense(BankTransaction $bank_transaction)
    {
        $transformer = new ExpenseTransformer($this->serializer);

        return $this->includeItem($bank_transaction->expense, $transformer, Expense::class);
    }

}
