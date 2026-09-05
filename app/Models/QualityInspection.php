<?php

namespace App\Models;

use App\Enums\QualityStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A quality inspection of production output. Records inspected/passed/rejected
 * quantities, defects and outcome, linked to the process order and batch. The
 * rejected quantity is removed from stock by {@see \App\Actions\Process\RecordQualityInspection}.
 *
 * @property QualityStatus $status
 */
class QualityInspection extends Model
{
    use Auditable;
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'reference', 'inspectable_type', 'inspectable_id', 'batch_id', 'product_id',
        'inspected_quantity', 'passed_quantity', 'rejected_quantity', 'defects', 'remarks',
        'status', 'inspected_by', 'inspected_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => QualityStatus::class,
            'inspected_quantity' => 'decimal:4',
            'passed_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'inspected_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (QualityInspection $inspection): void {
            if (empty($inspection->reference)) {
                $inspection->reference = DocumentNumber::next('quality_inspection', 'QC-', static::query()->count());
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function inspectable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
