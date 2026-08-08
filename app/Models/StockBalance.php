<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current stock position for a product in a warehouse. Derived from the ledger;
 * used for fast availability checks and as the row locked during issues (spec #17).
 *
 * Decimal casts return exact strings (never floats — spec #52), so these are typed
 * as string rather than the float Larastan would otherwise infer.
 *
 * @property string $quantity_on_hand
 * @property string $average_cost
 * @property string $reserved_quantity
 */
class StockBalance extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'warehouse_id', 'product_id',
        'quantity_on_hand', 'average_cost', 'reserved_quantity',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:4',
            'average_cost' => 'decimal:6',
            'reserved_quantity' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Quantity that can be issued right now (on hand minus reserved). */
    public function available(): BigDecimal
    {
        return BigDecimal::of($this->quantity_on_hand)
            ->minus(BigDecimal::of($this->reserved_quantity));
    }
}
