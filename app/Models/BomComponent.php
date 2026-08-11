<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $quantity
 */
class BomComponent extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'bill_of_materials_id', 'component_product_id', 'quantity'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    /** @return BelongsTo<BillOfMaterials, $this> */
    public function billOfMaterials(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
