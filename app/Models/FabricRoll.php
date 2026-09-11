<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An individual fabric roll — a sub-division of an output batch produced by a process
 * order. Carries its own weight/length/GSM/width/shade and QC status for roll-level
 * traceability. The batch remains the stock of record; rolls do not post inventory.
 */
class FabricRoll extends Model
{
    use Auditable;
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'roll_number', 'product_id', 'batch_id', 'process_order_id',
        'warehouse_id', 'weight', 'length', 'gsm', 'width', 'shade', 'qc_status', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:4',
            'length' => 'decimal:4',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (FabricRoll $roll): void {
            if (empty($roll->roll_number)) {
                $roll->roll_number = DocumentNumber::next('fabric_roll', 'R-', static::query()->count(), 6);
            }
        });
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

    /** @return BelongsTo<ProcessOrder, $this> */
    public function processOrder(): BelongsTo
    {
        return $this->belongsTo(ProcessOrder::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
