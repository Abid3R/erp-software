<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $quantity
 * @property string $unit_price
 */
class DeliveryOrderLine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'delivery_order_id', 'sales_order_line_id', 'product_id',
        'quantity', 'unit_price', 'batch_no', 'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4'];
    }

    /** @return BelongsTo<DeliveryOrder, $this> */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    /** @return BelongsTo<SalesOrderLine, $this> */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
