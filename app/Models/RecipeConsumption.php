<?php

namespace App\Models;

use App\Enums\ConsumptionBasis;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of a material recipe: a material and how much of it is needed per unit
 * of output, so a run's total requirement can be calculated from the order
 * quantity. Attached to a fabric specification (knitting) or a lab dip (dyeing).
 *
 * @property ConsumptionBasis $basis
 */
class RecipeConsumption extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'holder_type', 'holder_id', 'product_id', 'basis', 'rate',
        'wastage_percent', 'sort', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'basis' => ConsumptionBasis::class,
            'rate' => 'decimal:6',
            'wastage_percent' => 'decimal:3',
            'sort' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
