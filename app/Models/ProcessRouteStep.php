<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered step of a process route (routing): the process type and its output.
 */
class ProcessRouteStep extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'process_route_id', 'process_type_id', 'output_product_id', 'sequence', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    /** @return BelongsTo<ProcessRoute, $this> */
    public function processRoute(): BelongsTo
    {
        return $this->belongsTo(ProcessRoute::class);
    }

    /** @return BelongsTo<ProcessType, $this> */
    public function processType(): BelongsTo
    {
        return $this->belongsTo(ProcessType::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id');
    }
}
