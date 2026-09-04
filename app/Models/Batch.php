<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A batch / lot of a product, used for end-to-end traceability. Batch numbers are
 * auto-generated (atomic, per company). Parent → child links are recorded in
 * batch_consumptions so a batch can be traced back to its inputs or forward to
 * where it was used.
 *
 * @property string $batch_number
 */
class Batch extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'batch_number', 'product_id', 'warehouse_id',
        'quantity', 'status', 'source_type', 'source_id', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public static function booted(): void
    {
        // Auto-assign a unique per-company batch number when not supplied.
        static::creating(function (Batch $batch): void {
            if (empty($batch->batch_number)) {
                $batch->batch_number = DocumentNumber::next('batch', 'B-', static::query()->count(), 6);
            }
        });
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** What produced/received this batch (goods receipt, process order, …). @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Input batches consumed to make this batch (its parents / origin). @return BelongsToMany<Batch, $this> */
    public function consumedBatches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'batch_consumptions', 'output_batch_id', 'input_batch_id')
            ->withPivot('quantity')->withTimestamps();
    }

    /** Batches this one was consumed into (where it was used). @return BelongsToMany<Batch, $this> */
    public function usedInBatches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'batch_consumptions', 'input_batch_id', 'output_batch_id')
            ->withPivot('quantity')->withTimestamps();
    }
}
