<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $quantity_ordered
 * @property string $quantity_delivered
 * @property string $unit_price
 */
class SalesOrderLine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'sales_order_id', 'product_id',
        'quantity_ordered', 'quantity_delivered', 'unit_price',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_delivered' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    /** Quantity still to be delivered. */
    public function outstanding(): BigDecimal
    {
        return BigDecimal::of($this->quantity_ordered)->minus($this->quantity_delivered);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
