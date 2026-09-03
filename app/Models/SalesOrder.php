<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SalesOrderStatus $status
 */
class SalesOrder extends Model
{
    use Auditable;
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'so_number', 'customer_id', 'warehouse_id', 'order_date',
        'delivery_date', 'status', 'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'delivery_date' => 'date',
            'status' => SalesOrderStatus::class,
        ];
    }

    /** @return HasMany<SalesOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
            fn (BigDecimal $carry, SalesOrderLine $l): BigDecimal => $carry->plus(
                BigDecimal::of($l->quantity_ordered)->multipliedBy($l->unit_price),
            ),
            BigDecimal::zero(),
        );
    }

    public function isFullyDelivered(): bool
    {
        return $this->lines->every(fn (SalesOrderLine $l): bool => $l->outstanding()->isLessThanOrEqualTo(0));
    }
}
