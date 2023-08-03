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

namespace App\Repositories;

use App\Models\Expense;
use App\Models\BankTransaction;
use App\Jobs\Bank\MatchBankTransactions;

/**
 * Class for bank transaction repository.
 */
class BankTransactionRepository extends BaseRepository
{
    
    public function save($data, BankTransaction $bank_transaction)
    {
        if (array_key_exists('bank_integration_id', $data)) {
            $bank_transaction->bank_integration_id = $data['bank_integration_id'];
        }


        $bank_transaction->fill($data);
        $bank_transaction->save();

        $bank_transaction->service()->processRules();

        return $bank_transaction->fresh();
    }

    public function convert_matched($bank_transactions)
    {
        $data['transactions'] = $bank_transactions->map(function ($bt) {
            return ['id' => $bt->id, 'invoice_ids' => $bt->invoice_ids, 'ninja_category_id' => $bt->ninja_category_id];
        })->toArray();

        $bts = (new MatchBankTransactions(auth()->user()->company()->id, auth()->user()->company()->db, $data))->handle();
    }

    public function unlink($bt)
    {
        if($bt->payment()->exists()){
            $bt->payment->transaction_id = null;
            $bt->payment_id = null;
        }

        $e = Expense::whereIn('id', $this->transformKeys(explode(",", $bt->expense_id)))
        ->cursor()
        ->each(function ($expense){
            
            $expense->transaction_id = null;
            $expense->saveQuietly();

        });
        
        $bt->expense_id = null;
        $bt->vendor_id = null;
        $bt->status_id = 1;
        $bt->invoice_ids = null;
        $bt->ninja_category_id = null;
        $bt->push();
    
    }
}
