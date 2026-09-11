<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable process route (routing) — a reusable, ordered sequence of process
 * types to produce a product. Production plans are built from the product's route.
 */
class ProcessRoute extends Model
{
    use Auditable;
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'name', 'product_id', 'is_active', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProcessRouteStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ProcessRouteStep::class)->orderBy('sequence');
    }
}
