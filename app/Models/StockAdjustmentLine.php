<?php

namespace App\Models;

use App\Enums\StockAdjustmentDirection;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property StockAdjustmentDirection $direction
 * @property string $quantity
 * @property string|null $unit_cost
 */
class StockAdjustmentLine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'stock_adjustment_id', 'product_id', 'direction', 'quantity', 'unit_cost',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => StockAdjustmentDirection::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
        ];
    }

    /** @return BelongsTo<StockAdjustment, $this> */
    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
