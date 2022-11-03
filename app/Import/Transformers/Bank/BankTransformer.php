<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2022. client Ninja LLC (https://clientninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformers\Bank;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use App\Models\BankTransaction;
use App\Utils\Number;

/**
 * Class BankTransformer.
 */
class BankTransformer extends BaseTransformer
{
    /**
     * @param $line_items_data
     *
     * @return bool|array
     */
    public function transform($transaction)
    {
    	$now = now();

    	$transformed = [
            'bank_integration_id' => $transaction['bank.bank_integration_id'],
            'transaction_id' => $this->getNumber($transaction,'bank.transaction_id'),
            'amount' => abs($this->getFloat($transaction, 'bank.amount')),
            'currency_id' => $this->getCurrencyByCode($transaction, 'bank.currency'),
            'account_type' => strlen($this->getString($transaction, 'bank.account_type')) > 1 ? $this->getString($transaction, 'bank.account_type') : 'bank',
            'category_id' => $this->getNumber($transaction, 'bank.category_id') > 0 ? $this->getNumber($transaction, 'bank.category_id') : null,
            'category_type' => $this->getString($transaction, 'bank.category_type'),
            'date' => array_key_exists('bank.date', $transaction) ? $this->parseDate($transaction['bank.date'])
                : now()->format('Y-m-d'),
            'bank_account_id' => array_key_exists('bank.bank_account_id', $transaction) ? $transaction['bank.bank_account_id'] : 0,
            'description' => array_key_exists('bank.description', $transaction) ? $transaction['bank.description'] : '',
            'base_type' => $this->calculateType($transaction),
            'created_at' => $now,
            'updated_at' => $now,
            'company_id' => $this->company->id,
            'user_id' => $this->company->owner()->id,
        ];

        return $transformed;
    }


    private function calculateType($transaction)
    {

    	if(array_key_exists('bank.base_type', $transaction) && $transaction['bank.base_type'] == 'CREDIT')
    		return 'CREDIT';

    	if(array_key_exists('bank.base_type', $transaction) && $transaction['bank.base_type'] == 'DEBIT')
    		return 'DEBIT';

    	if(array_key_exists('bank.category_id', $transaction))
    		return 'DEBIT';

    	if(array_key_exists('bank.category_type', $transaction) && $transaction['bank.category_type'] == 'Income')
    		return 'CREDIT';

    	if(array_key_exists('bank.category_type', $transaction))
    		return 'DEBIT';

    	if(array_key_exists('bank.amount', $transaction) && is_numeric($transaction['bank.amount']) && $transaction['bank.amount'] > 0)
    		return 'CREDIT';

    	return 'DEBIT';

    }

}