<?php

namespace App\Models;

use App\Enums\ProductionPlanStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A master production schedule (Time & Action plan) for a customer order. It holds
 * the target quantity/dates and an ordered list of stages; the actual runs are the
 * process orders spawned from those stages. No stock or accounting effect — the
 * schedule reflects reality by rolling up its process orders.
 *
 * @property ProductionPlanStatus $status
 * @property string $planned_quantity
 */
class ProductionPlan extends Model
{
    use Auditable;
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'reference', 'sales_order_id', 'customer_id', 'buyer', 'style_no',
        'po_no', 'colour', 'fabric_composition', 'gsm', 'fabric_width', 'fabric_type',
        'product_id', 'planned_quantity', 'order_quantity', 'order_unit', 'unit',
        'plan_date', 'booking_date', 'start_date', 'due_date', 'shipment_date',
        'status', 'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProductionPlanStatus::class,
            'planned_quantity' => 'decimal:4',
            'order_quantity' => 'decimal:4',
            'plan_date' => 'date',
            'booking_date' => 'date',
            'start_date' => 'date',
            'due_date' => 'date',
            'shipment_date' => 'date',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (ProductionPlan $plan): void {
            if (empty($plan->reference)) {
                $plan->reference = DocumentNumber::next('production_plan', 'PLAN-', static::query()->count());
            }
        });
    }

    /** Total good quantity produced across all process orders under this plan's stages. */
    public function producedQuantity(): BigDecimal
    {
        $produced = $this->processOrders()->sum('produced_quantity');

        return BigDecimal::of((string) ($produced ?: '0'));
    }

    /** Completion 0–100 against the planned quantity. */
    public function progressPercent(): float
    {
        $planned = BigDecimal::of($this->planned_quantity);
        if (! $planned->isPositive()) {
            return 0.0;
        }

        return (float) (string) $this->producedQuantity()
            ->dividedBy($planned, 4, RoundingMode::HALF_UP)
            ->multipliedBy(100)
            ->toScale(1, RoundingMode::HALF_UP);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductionPlanStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(ProductionPlanStage::class)->orderBy('sequence');
    }

    /** Process orders spawned from this plan's stages. @return HasMany<ProcessOrder, $this> */
    public function processOrders(): HasMany
    {
        return $this->hasMany(ProcessOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Plans still being worked. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProductionPlanStatus::Draft->value,
            ProductionPlanStatus::Scheduled->value,
            ProductionPlanStatus::InProgress->value,
            ProductionPlanStatus::OnHold->value,
        ]);
    }
}
