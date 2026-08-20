<?php

namespace App\Models;

use App\Enums\DeliveryOrderStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property DeliveryOrderStatus $status
 */
class DeliveryOrder extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'sales_order_id', 'customer_id', 'warehouse_id',
        'delivery_date', 'delivery_address', 'vehicle_no', 'driver_name', 'driver_phone',
        'transporter', 'customer_reference', 'received_by', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'status' => DeliveryOrderStatus::class];
    }

    /** @return HasMany<DeliveryOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryOrderLine::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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

    public function totalQuantity(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, DeliveryOrderLine $l): BigDecimal => $c->plus(BigDecimal::of($l->quantity)),
            BigDecimal::zero(),
        );
    }
}
