<?php

namespace App\Models;

use App\Enums\ProductionStageStatus;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One stage of a production plan (Knitting, Dyeing, Finishing, …). The stage is the
 * *intent*; the process orders linked to it are the execution. Stage progress is a
 * live roll-up of those runs.
 *
 * @property ProductionStageStatus $status
 * @property string $planned_quantity
 */
class ProductionPlanStage extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'production_plan_id', 'process_type_id', 'machine_id', 'output_product_id',
        'sequence', 'planned_quantity', 'planned_start', 'planned_end', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProductionStageStatus::class,
            'planned_quantity' => 'decimal:4',
            'planned_start' => 'date',
            'planned_end' => 'date',
            'sequence' => 'integer',
        ];
    }

    /** Good quantity produced across the process orders spawned from this stage. */
    public function producedQuantity(): BigDecimal
    {
        return BigDecimal::of((string) ($this->processOrders()->sum('produced_quantity') ?: '0'));
    }

    /** @return BelongsTo<ProductionPlan, $this> */
    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    /** @return BelongsTo<ProcessType, $this> */
    public function processType(): BelongsTo
    {
        return $this->belongsTo(ProcessType::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id');
    }

    /** @return HasMany<ProcessOrder, $this> */
    public function processOrders(): HasMany
    {
        return $this->hasMany(ProcessOrder::class, 'production_plan_stage_id');
    }
}
