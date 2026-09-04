<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A machine / work centre used by textile process orders. Its hourly cost feeds
 * machine-cost absorption during production costing.
 */
class Machine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'code', 'name', 'type', 'hourly_cost', 'capacity_per_hour', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hourly_cost' => 'decimal:2',
            'capacity_per_hour' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ProcessOrder, $this> */
    public function processOrders(): HasMany
    {
        return $this->hasMany(ProcessOrder::class);
    }
}
