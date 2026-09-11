<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A dyed-fabric product's standard dyeing specification (process + parameters +
 * recipe). Auto-fills a dyeing process order and prints as an operator recipe
 * sheet — the dyeing parallel of the fabric/knitting specification. One per product.
 */
class DyeingSpecification extends Model
{
    use Auditable;
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'product_id', 'recipe_number', 'version', 'approval_status', 'approved_by', 'approved_at',
        'dyeing_process', 'substrate', 'gsm', 'colour', 'colour_ref',
        'liquor_ratio', 'temperature', 'dyeing_time', 'ph', 'shade_percentage',
        'fastness_wash', 'fastness_rubbing', 'fastness_light', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'approved_at' => 'datetime',
            'temperature' => 'decimal:2',
            'dyeing_time' => 'integer',
            'ph' => 'decimal:2',
            'shade_percentage' => 'decimal:3',
        ];
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The dyeing recipe (dyes % owf, chemicals g/L). @return MorphMany<RecipeConsumption, $this> */
    public function consumptions(): MorphMany
    {
        return $this->morphMany(RecipeConsumption::class, 'holder')->orderBy('sort');
    }

    /** Litres of bath per unit of fabric, from the liquor ratio (e.g. "1:8" → 8). */
    public function liquorFactor(): ?float
    {
        return LabDip::parseLiquorFactor($this->liquor_ratio);
    }

    /**
     * The dyeing parameters as the process order's `specifications` JSON shape.
     *
     * @return array<string, string|null>
     */
    public function toSpecificationsArray(): array
    {
        return array_filter([
            'process' => $this->dyeing_process,
            'liquor_ratio' => $this->liquor_ratio,
            'temperature' => $this->temperature,
            'shade_percentage' => $this->shade_percentage,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
