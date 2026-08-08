<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One immutable row on the inventory ledger. Append-only: no updated_at, and the
 * app exposes no update/delete path (spec #14, #29).
 */
class InventoryTransaction extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'warehouse_id', 'product_id', 'type',
        'reference_type', 'reference_id', 'quantity', 'unit_cost', 'total_cost',
        'balance_after', 'average_cost_after', 'note', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => InventoryTransactionType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:2',
            'balance_after' => 'decimal:4',
            'average_cost_after' => 'decimal:6',
            'created_at' => 'datetime',
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

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
