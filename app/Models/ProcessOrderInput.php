<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An input (consumed) line of a process order — yarn, dye, chemical, etc.
 */
class ProcessOrderInput extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'process_order_id', 'product_id', 'batch_id',
        'planned_quantity', 'consumed_quantity', 'unit_cost', 'total_cost',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'consumed_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ProcessOrder, $this> */
    public function processOrder(): BelongsTo
    {
        return $this->belongsTo(ProcessOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
