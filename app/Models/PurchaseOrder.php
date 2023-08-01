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

namespace App\Models;

use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Jobs\Vendor\CreatePurchaseOrderPdf;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\PurchaseOrder
 *
 * @property int $id
 * @property int|null $client_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int $status_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property int|null $recurring_id
 * @property int|null $design_id
 * @property int|null $invoice_id
 * @property string|null $number
 * @property float $discount
 * @property bool $is_amount_discount
 * @property string|null $po_number
 * @property string|null $date
 * @property string|null $last_sent_date
 * @property string|null $due_date
 * @property int $is_deleted
 * @property object|null $line_items
 * @property object|null $backup
 * @property string|null $footer
 * @property string|null $public_notes
 * @property string|null $private_notes
 * @property string|null $terms
 * @property string|null $tax_name1
 * @property string $tax_rate1
 * @property string|null $tax_name2
 * @property string $tax_rate2
 * @property string|null $tax_name3
 * @property string $tax_rate3
 * @property string $total_taxes
 * @property int $uses_inclusive_taxes
 * @property string|null $reminder1_sent
 * @property string|null $reminder2_sent
 * @property string|null $reminder3_sent
 * @property string|null $reminder_last_sent
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $next_send_date
 * @property string|null $custom_surcharge1
 * @property string|null $custom_surcharge2
 * @property string|null $custom_surcharge3
 * @property string|null $custom_surcharge4
 * @property int $custom_surcharge_tax1
 * @property int $custom_surcharge_tax2
 * @property int $custom_surcharge_tax3
 * @property int $custom_surcharge_tax4
 * @property string $exchange_rate
 * @property string $balance
 * @property float|null $partial
 * @property string $amount
 * @property string $paid_to_date
 * @property string|null $partial_due_date
 * @property string|null $last_viewed
 * @property int|null $deleted_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $expense_id
 * @property int|null $currency_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Expense|null $expense
 * @property-read mixed $hashed_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read int|null $history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\PurchaseOrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @mixin \Eloquent
 */
class PurchaseOrder extends BaseModel
{
    use Filterable;
    use SoftDeletes;
    use MakesDates;

    protected $fillable = [
        'number',
        'discount',
        'status_id',
        'last_sent_date',
        'is_deleted',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'tax_name3',
        'tax_rate3',
        'total_taxes',
        'uses_inclusive_taxes',
        'is_amount_discount',
        'partial',
        'recurring_id',
        'next_send_date',
        'reminder1_sent',
        'reminder2_sent',
        'reminder3_sent',
        'reminder_last_sent',
        'partial_due_date',
        'project_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'backup',
        'footer',
        'line_items',
        'client_id',
        'custom_surcharge1',
        'custom_surcharge2',
        'custom_surcharge3',
        'custom_surcharge4',
        'design_id',
        'invoice_id',
        'assigned_user_id',
        'exchange_rate',
        'balance',
        'partial',
        'paid_to_date',
        'vendor_id',
        'last_viewed',
        'currency_id',
    ];

    protected $casts = [
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_amount_discount' => 'bool',

    ];

    const STATUS_DRAFT = 1;
    const STATUS_SENT = 2;
    const STATUS_ACCEPTED = 3;
    const STATUS_RECEIVED = 4;
    const STATUS_CANCELLED = 5;

    public static function stringStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return ctrans('texts.draft');
            case self::STATUS_SENT:
                return ctrans('texts.sent');
            case self::STATUS_ACCEPTED:
                return ctrans('texts.accepted');
            case self::STATUS_CANCELLED:
                return ctrans('texts.cancelled');
            default:
                return ctrans('texts.sent');
                
        }
    }


    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
            case self::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
            case self::STATUS_ACCEPTED:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.accepted').'</span></h5>';
            case self::STATUS_CANCELLED:
                return '<h5><span class="badge badge-secondary">'.ctrans('texts.cancelled').'</span></h5>';
            default:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
        }
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function assigned_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo 
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function history()
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(50);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function expense(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }
    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->saveQuietly();
            }
        });
    }

    public function pdf_file_path($invitation = null, string $type = 'path', bool $portal = false)
    {
        if (! $invitation) {
            if ($this->invitations()->exists()) {
                $invitation = $this->invitations()->first();
            } else {
                $this->service()->createInvitations();
                $invitation = $this->invitations()->first();
            }
        }

        if (!$invitation) {
            throw new \Exception('Hard fail, could not create an invitation - is there a valid contact?');
        }

        $file_path = $this->vendor->purchase_order_filepath($invitation).$this->numberFormatter().'.pdf';

        if (Ninja::isHosted() && $portal && Storage::disk(config('filesystems.default'))->exists($file_path)) {
            return Storage::disk(config('filesystems.default'))->{$type}($file_path);
        } elseif (Ninja::isHosted() && $portal) {
            $file_path = (new CreatePurchaseOrderPdf($invitation, config('filesystems.default')))->handle();
            return Storage::disk(config('filesystems.default'))->{$type}($file_path);
        }

        if (Storage::disk('public')->exists($file_path)) {
            return Storage::disk('public')->{$type}($file_path);
        }

        $file_path = (new CreatePurchaseOrderPdf($invitation))->handle();
        return Storage::disk('public')->{$type}($file_path);
    }

    public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrderInvitation::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return PurchaseOrderService  */
    public function service() :PurchaseOrderService
    {
        return new PurchaseOrderService($this);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class)->using(Paymentable::class);
    }

    public function payments()
    {
        return $this->morphToMany(Payment::class, 'paymentable');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Access the invoice calculator object.
     *
     * @return InvoiceSumInclusive | InvoiceSum The invoice calculator object getters
     */
    public function calc(): InvoiceSumInclusive | InvoiceSum
    {
        $purchase_order_calc = null;

        if ($this->uses_inclusive_taxes) {
            $purchase_order_calc = new InvoiceSumInclusive($this);
        } else {
            $purchase_order_calc = new InvoiceSum($this);
        }

        return $purchase_order_calc->build();
    }

    public function translate_entity()
    {
        return ctrans('texts.purchase_order');
    }
}
