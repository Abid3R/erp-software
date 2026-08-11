<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $output_quantity
 */
class BillOfMaterials extends Model
{
    use BelongsToCompany;

    // Table `bill_of_materials` is inferred by convention (last word pluralised).

    /** @var list<string> */
    protected $fillable = ['company_id', 'product_id', 'name', 'output_quantity', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['output_quantity' => 'decimal:4', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<BomComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(BomComponent::class);
    }

    /** Standard material cost of one BOM batch, from component list prices. */
    public function materialCost(): BigDecimal
    {
        return $this->components->reduce(
            fn (BigDecimal $carry, BomComponent $c): BigDecimal => $carry->plus(
                BigDecimal::of($c->quantity)->multipliedBy((string) data_get($c, 'component.cost_price', '0')),
            ),
            BigDecimal::zero(),
        );
    }
}
