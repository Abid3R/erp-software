<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An output (produced) line of a process order — grey fabric, dyed fabric, and
 * any co-/by-products. Each carries its own batch for downstream traceability.
 *
 * @property bool $is_primary
 */
class ProcessOrderOutput extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'process_order_id', 'product_id', 'batch_id', 'quantity', 'is_primary',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'is_primary' => 'boolean'];
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
