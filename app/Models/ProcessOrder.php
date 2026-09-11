<?php

namespace App\Models;

use App\Enums\ProcessMode;
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
        'company_id', 'reference', 'process_type_id', 'mode', 'is_rework', 'rework_of_process_order_id', 'manufacturing_order_id',
        'sales_order_id', 'production_plan_id', 'production_plan_stage_id', 'machine_id',
        'operator_id', 'subcontractor_id', 'service_item_id', 'service_rate', 'bill_quantity',
        'service_currency', 'service_charge', 'service_charged_at', 'supplier_invoice_id',
        'warehouse_id', 'output_product_id', 'fabric_composition', 'gsm', 'fabric_width',
        'colour', 'colour_ref', 'specifications', 'output_batch_id', 'lab_dip_id',
        'planned_quantity', 'produced_quantity', 'wastage_quantity',
        'expected_wastage_percent', 'expected_output', 'wastage_reason', 'status',
        'material_cost', 'labour_cost', 'machine_cost', 'utility_cost', 'overhead_cost',
        'total_cost', 'wip_cost', 'output_unit_cost', 'started_at', 'completed_at',
        'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProcessOrderStatus::class,
            'mode' => ProcessMode::class,
            'is_rework' => 'boolean',
            'specifications' => 'array',
            'planned_quantity' => 'decimal:4',
            'produced_quantity' => 'decimal:4',
            'wastage_quantity' => 'decimal:4',
            'expected_wastage_percent' => 'decimal:3',
            'expected_output' => 'decimal:4',
            'service_rate' => 'decimal:4',
            'bill_quantity' => 'decimal:4',
            'service_charge' => 'decimal:2',
            'service_charged_at' => 'datetime',
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

    /** True when this run is sub-contracted (job-work) to an outside supplier. */
    public function isSubcontract(): bool
    {
        return $this->mode === ProcessMode::Subcontract;
    }

    /** The knitting service charge = bill quantity × service rate (0 when not set). */
    public function computedServiceCharge(): BigDecimal
    {
        if ($this->service_rate === null || $this->bill_quantity === null) {
            return BigDecimal::zero();
        }

        return BigDecimal::of($this->bill_quantity)->multipliedBy($this->service_rate);
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

            // Resolve the expected wastage from the wastage hierarchy when not given:
            // order override → process type default → output product default. Then the
            // expected (good) output follows from it. Actual is captured at production.
            if ($order->expected_wastage_percent === null) {
                $order->expected_wastage_percent = ProcessType::query()->whereKey($order->process_type_id)->value('default_wastage_percent')
                    ?? ($order->output_product_id ? Product::query()->whereKey($order->output_product_id)->value('default_wastage_percent') : null);
            }
            if ($order->expected_output === null && $order->expected_wastage_percent !== null && $order->planned_quantity !== null) {
                $order->expected_output = (string) BigDecimal::of($order->planned_quantity)
                    ->multipliedBy(BigDecimal::of(100)->minus($order->expected_wastage_percent))
                    ->dividedBy(100, 4, \Brick\Math\RoundingMode::HALF_UP);
            }
        });
    }

    /** Expected good output = planned × (1 − expected wastage %). Falls back to planned. */
    public function expectedOutput(): BigDecimal
    {
        if ($this->expected_output !== null) {
            return BigDecimal::of($this->expected_output);
        }
        $pct = BigDecimal::of($this->expected_wastage_percent ?? '0');

        return BigDecimal::of($this->planned_quantity)
            ->multipliedBy(BigDecimal::of(100)->minus($pct))
            ->dividedBy(100, 4, \Brick\Math\RoundingMode::HALF_UP);
    }

    /** Actual wastage % = wastage ÷ (produced + wastage) × 100 (0 when nothing produced). */
    public function actualWastagePercent(): BigDecimal
    {
        $waste = BigDecimal::of($this->wastage_quantity ?? '0');
        $base = BigDecimal::of($this->produced_quantity ?? '0')->plus($waste);
        if (! $base->isPositive()) {
            return BigDecimal::zero();
        }

        return $waste->dividedBy($base, 5, \Brick\Math\RoundingMode::HALF_UP)->multipliedBy(100)->toScale(3, \Brick\Math\RoundingMode::HALF_UP);
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

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** The original order this run reworks (null unless this is a rework). @return BelongsTo<ProcessOrder, $this> */
    public function reworkOf(): BelongsTo
    {
        return $this->belongsTo(ProcessOrder::class, 'rework_of_process_order_id');
    }

    /** Rework runs raised against this order. @return HasMany<ProcessOrder, $this> */
    public function reworks(): HasMany
    {
        return $this->hasMany(ProcessOrder::class, 'rework_of_process_order_id');
    }

    /** @return BelongsTo<ProductionPlan, $this> */
    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    /** @return BelongsTo<ProductionPlanStage, $this> */
    public function productionPlanStage(): BelongsTo
    {
        return $this->belongsTo(ProductionPlanStage::class);
    }

    /** The sub-contractor (supplier) doing the job-work. @return BelongsTo<Supplier, $this> */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'subcontractor_id');
    }

    /** The non-stock service item billed for the sub-contract. @return BelongsTo<Product, $this> */
    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_item_id');
    }

    /** @return BelongsTo<SupplierInvoice, $this> */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
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

    /** Individual fabric rolls produced by this order (roll-tracked products). @return HasMany<FabricRoll, $this> */
    public function rolls(): HasMany
    {
        return $this->hasMany(FabricRoll::class);
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
