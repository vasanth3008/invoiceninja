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

namespace App\Livewire\BillingPortal;

use App\Libraries\MultiDB;
use App\Livewire\BillingPortal\Cart\Cart;
use App\Livewire\BillingPortal\Payments\Methods;
use App\Models\Subscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Str;

class Purchase extends Component
{
    public Subscription $subscription;

    public string $db;

    public array $request_data;

    public string $hash;

    public ?string $campaign;

    // 

    public int $step = 0;

    public array $steps = [
        Setup::class,
        Cart::class,
        Authentication::class,
        RFF::class,
        Methods::class,
    ];

    public string $id;

    public array $context = [];

    #[On('purchase.context')]
    public function handleContext(string $property, $value): self
    {
        $clone = $this->context;

        data_set($this->context, $property, $value);

        // The following may not be needed, as we can pass arround $context.
        // cache()->set($this->hash, $this->context);

        if ($clone !== $this->context) {
            $this->id = Str::uuid();
        }

        return $this;
    }

    #[On('purchase.next')]
    public function handleNext(): void
    {
        if ($this->step < count($this->steps) - 1) {
            $this->step++;
        }

        $this->id = Str::uuid();
    }

    #[On('purchase.forward')]
    public function handleForward(string $component): void
    {
        $this->step = array_search($component, $this->steps);

        $this->id = Str::uuid();
    }

    #[Computed()]
    public function component(): string
    {
        return $this->steps[$this->step];
    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        return "purchase-{$this->id}";
    }

    #[Computed()]
    public function summaryUniqueId(): string
    {
        return "summary-{$this->id}";
    }

    public function mount()
    {
        $this->id = Str::uuid();

        MultiDB::setDb($this->db);

        $this
            ->handleContext('hash', $this->hash)
            ->handleContext('quantity', 1)
            ->handleContext('request_data', $this->request_data)
            ->handleContext('campaign', $this->campaign);
    }

    public function render()
    {
        return view('billing-portal.v3.purchase');
    }
}
