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

namespace App\Services\Scheduler;

use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Services\Scheduler\EmailProductSalesReport;

class SchedulerService
{
    use MakesHash;
    use MakesDates;

    private string $method;

    public function __construct(public Scheduler $scheduler)
    {
    }

    /**
     * Called from the TaskScheduler Cron
     *
     * @return void
     */
    public function runTask(): void
    {
        if (method_exists($this, $this->scheduler->template)) {
            $this->{$this->scheduler->template}();
        }
    }

    private function email_record()
    {
        (new EmailRecord($this->scheduler))->run();
    }

    private function email_statement()
    {
        (new EmailStatementService($this->scheduler))->run();
    }

    private function email_product_sales_report()
    {
        (new EmailProductSalesReport($this->scheduler))->run();
    }

    /**
     * Sets the next run date of the scheduled task
     *
     */


    //handle when the scheduler has been paused.
}
