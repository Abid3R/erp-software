<?php

namespace App\Models;

use App\Enums\ProcessCategory;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable textile process type (Knitting, Dyeing, Finishing, …). The
 * behaviour flags let the shared process engine handle new processes without
 * code changes. The category drives the technical spec fields shown on the order.
 *
 * @property ProcessCategory $category
 * @property bool $consumes_material
 * @property bool $produces_material
 * @property bool $requires_lab_dip
 * @property bool $requires_qc
 * @property bool $subcontractable
 */
class ProcessType extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'code', 'name', 'category', 'consumes_material', 'produces_material',
        'requires_lab_dip', 'requires_qc', 'subcontractable', 'default_wastage_percent', 'sort', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => ProcessCategory::class,
            'consumes_material' => 'boolean',
            'produces_material' => 'boolean',
            'requires_lab_dip' => 'boolean',
            'requires_qc' => 'boolean',
            'subcontractable' => 'boolean',
            'default_wastage_percent' => 'decimal:3',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ProcessOrder, $this> */
    public function processOrders(): HasMany
    {
        return $this->hasMany(ProcessOrder::class);
    }
}
