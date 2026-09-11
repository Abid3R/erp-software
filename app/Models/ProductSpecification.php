<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A product's standard fabric/knitting specification (the master recipe for how it
 * is made). Auto-fills the knitting process order / sub-contract when the product
 * is selected, and prints as an operator spec sheet. One per product.
 */
class ProductSpecification extends Model
{
    use Auditable;
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'product_id', 'recipe_number', 'version', 'approval_status', 'approved_by', 'approved_at',
        'fabric_composition', 'gsm', 'fabric_width',
        'colour', 'colour_ref', 'yarn_count', 'fabric_type', 'quality',
        'machine_diameter', 'gauge', 'stitch_length', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The per-unit material recipe (yarns) used to calculate an order's requirement. @return MorphMany<RecipeConsumption, $this> */
    public function consumptions(): MorphMany
    {
        return $this->morphMany(RecipeConsumption::class, 'holder')->orderBy('sort');
    }

    /**
     * The knitting parameters as the process order's `specifications` JSON shape.
     *
     * @return array<string, string|null>
     */
    public function toSpecificationsArray(): array
    {
        return array_filter([
            'machine_diameter' => $this->machine_diameter,
            'gauge' => $this->gauge,
            'stitch_length' => $this->stitch_length,
            'yarn_count' => $this->yarn_count,
            'fabric_type' => $this->fabric_type,
            'quality' => $this->quality,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
