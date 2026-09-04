<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable textile process type (Knitting, Dyeing, Finishing, …). The
 * behaviour flags let the shared process engine handle new processes without
 * code changes.
 *
 * @property bool $consumes_material
 * @property bool $produces_material
 * @property bool $requires_lab_dip
 * @property bool $requires_qc
 */
class ProcessType extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'code', 'name', 'consumes_material', 'produces_material',
        'requires_lab_dip', 'requires_qc', 'sort', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'consumes_material' => 'boolean',
            'produces_material' => 'boolean',
            'requires_lab_dip' => 'boolean',
            'requires_qc' => 'boolean',
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
