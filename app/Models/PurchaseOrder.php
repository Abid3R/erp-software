<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PurchaseOrderStatus $status
 */
class PurchaseOrder extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'po_number', 'supplier_id', 'warehouse_id', 'order_date',
        'expected_date', 'status', 'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'status' => PurchaseOrderStatus::class,
        ];
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Order value = Σ (quantity ordered × unit price). */
    public function total(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $carry, PurchaseOrderLine $l): BigDecimal => $carry->plus(
                BigDecimal::of($l->quantity_ordered)->multipliedBy($l->unit_price),
            ),
            BigDecimal::zero(),
        );
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->every(fn (PurchaseOrderLine $l): bool => $l->outstanding()->isLessThanOrEqualTo(0));
    }
}
