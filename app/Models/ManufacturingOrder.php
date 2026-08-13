<?php

namespace App\Models;

use App\Enums\ManufacturingOrderStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property ManufacturingOrderStatus $status
 * @property string $quantity
 * @property string $quantity_produced
 * @property string $wip_cost
 * @property \Illuminate\Support\Carbon|null $materials_issued_at
 */
class ManufacturingOrder extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'reference', 'bill_of_materials_id', 'product_id', 'warehouse_id',
        'quantity', 'quantity_produced', 'status', 'output_unit_cost', 'total_cost',
        'wip_cost', 'materials_issued_at', 'completed_at', 'created_by', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'quantity_produced' => 'decimal:4',
            'status' => ManufacturingOrderStatus::class,
            'output_unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'wip_cost' => 'decimal:2',
            'materials_issued_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** Whether the BOM components have been issued into WIP. */
    public function materialsIssued(): bool
    {
        return $this->materials_issued_at !== null;
    }

    /** Quantity still to be produced against the order. */
    public function remainingToProduce(): BigDecimal
    {
        return BigDecimal::of($this->quantity)->minus($this->quantity_produced);
    }

    public function isFullyProduced(): bool
    {
        return $this->remainingToProduce()->isLessThanOrEqualTo(0);
    }

    /** @return BelongsTo<BillOfMaterials, $this> */
    public function billOfMaterials(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class);
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

    /** Inventory ledger movements (material issues + production receipts) for this order. */
    /** @return MorphMany<InventoryTransaction, $this> */
    public function inventoryTransactions(): MorphMany
    {
        return $this->morphMany(InventoryTransaction::class, 'reference');
    }
}
