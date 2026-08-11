<?php

namespace App\Models;

use App\Enums\ManufacturingOrderStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ManufacturingOrderStatus $status
 * @property string $quantity
 */
class ManufacturingOrder extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'reference', 'bill_of_materials_id', 'product_id', 'warehouse_id',
        'quantity', 'status', 'output_unit_cost', 'total_cost', 'completed_at', 'created_by', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'status' => ManufacturingOrderStatus::class,
            'output_unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
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
}
