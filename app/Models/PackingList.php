<?php

namespace App\Models;

use App\Enums\PackingListStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Packing List — how an export consignment is packed (cartons/rolls, weights,
 * marks & numbers). Generated from a Commercial Invoice / Delivery Order so
 * quantities are not re-keyed. Documentary only: no ledger or stock impact.
 *
 * @property PackingListStatus $status
 */
class PackingList extends Model
{
    use Auditable;
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'pl_date', 'customer_id', 'commercial_invoice_id',
        'export_shipment_id', 'delivery_order_id', 'total_packages', 'status', 'marks_numbers', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pl_date' => 'date',
            'total_packages' => 'integer',
            'status' => PackingListStatus::class,
        ];
    }

    public static function booted(): void
    {
        static::creating(function (self $pl): void {
            if (empty($pl->number)) {
                $pl->number = DocumentNumber::next('packing_list', 'PL-', static::query()->count());
            }
        });
    }

    /** Σ net weight across lines. */
    public function totalNetWeight(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, PackingListLine $l): BigDecimal => $c->plus($l->net_weight ?? 0),
            BigDecimal::zero(),
        );
    }

    /** Σ gross weight across lines. */
    public function totalGrossWeight(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, PackingListLine $l): BigDecimal => $c->plus($l->gross_weight ?? 0),
            BigDecimal::zero(),
        );
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CommercialInvoice, $this> */
    public function commercialInvoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class);
    }

    /** @return BelongsTo<ExportShipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ExportShipment::class, 'export_shipment_id');
    }

    /** @return BelongsTo<DeliveryOrder, $this> */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    /** @return HasMany<PackingListLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PackingListLine::class);
    }
}
