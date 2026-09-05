<?php

namespace App\Models;

use App\Enums\ProcessOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A run of a configurable textile process (knitting, dyeing, finishing, …). It
 * consumes input materials and produces an output product/batch, reusing the
 * existing inventory + WIP + accounting engine. Optionally linked to a parent
 * manufacturing order so the whole chain stays connected.
 *
 * @property ProcessOrderStatus $status
 * @property string $planned_quantity
 * @property string $produced_quantity
 * @property string $wip_cost
 */
class ProcessOrder extends Model
{
    use Auditable;
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'reference', 'process_type_id', 'manufacturing_order_id', 'machine_id',
        'operator_id', 'warehouse_id', 'output_product_id', 'output_batch_id', 'lab_dip_id',
        'planned_quantity', 'produced_quantity', 'wastage_quantity', 'status',
        'material_cost', 'labour_cost', 'machine_cost', 'utility_cost', 'overhead_cost',
        'total_cost', 'wip_cost', 'output_unit_cost', 'started_at', 'completed_at',
        'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProcessOrderStatus::class,
            'planned_quantity' => 'decimal:4',
            'produced_quantity' => 'decimal:4',
            'wastage_quantity' => 'decimal:4',
            'material_cost' => 'decimal:2',
            'labour_cost' => 'decimal:2',
            'machine_cost' => 'decimal:2',
            'utility_cost' => 'decimal:2',
            'overhead_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'wip_cost' => 'decimal:2',
            'output_unit_cost' => 'decimal:4',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        // Auto-assign a per-type reference (e.g. KNIT-0001) when not supplied.
        static::creating(function (ProcessOrder $order): void {
            if (empty($order->reference)) {
                $code = ProcessType::query()->whereKey($order->process_type_id)->value('code') ?? 'PRO';
                $order->reference = DocumentNumber::next(
                    'process_order:'.$code,
                    strtoupper((string) $code).'-',
                    static::query()->where('process_type_id', $order->process_type_id)->count(),
                );
            }
        });
    }

    /** Quantity still to be produced against the order. */
    public function remainingToProduce(): BigDecimal
    {
        return BigDecimal::of($this->planned_quantity)->minus($this->produced_quantity);
    }

    /** @return BelongsTo<ProcessType, $this> */
    public function processType(): BelongsTo
    {
        return $this->belongsTo(ProcessType::class);
    }

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function manufacturingOrder(): BelongsTo
    {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id');
    }

    /** @return BelongsTo<Batch, $this> */
    public function outputBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'output_batch_id');
    }

    /** @return BelongsTo<LabDip, $this> */
    public function labDip(): BelongsTo
    {
        return $this->belongsTo(LabDip::class);
    }

    /** @return HasMany<ProcessOrderInput, $this> */
    public function inputs(): HasMany
    {
        return $this->hasMany(ProcessOrderInput::class);
    }

    /** @return HasMany<ProcessOrderOutput, $this> */
    public function outputs(): HasMany
    {
        return $this->hasMany(ProcessOrderOutput::class);
    }

    /** Inventory ledger movements (input issues + output receipts) for this order. @return MorphMany<InventoryTransaction, $this> */
    public function inventoryTransactions(): MorphMany
    {
        return $this->morphMany(InventoryTransaction::class, 'reference');
    }

    /** QC inspections recorded against this order. @return MorphMany<QualityInspection, $this> */
    public function qualityInspections(): MorphMany
    {
        return $this->morphMany(QualityInspection::class, 'inspectable');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Orders still open (draft, planned, in progress or in QC). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProcessOrderStatus::Draft->value,
            ProcessOrderStatus::Planned->value,
            ProcessOrderStatus::InProgress->value,
            ProcessOrderStatus::Qc->value,
        ]);
    }
}
