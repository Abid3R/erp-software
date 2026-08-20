<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'branch_id', 'name', 'code',
        'address', 'contact_person', 'phone',
        'allow_negative_stock', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'allow_negative_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Effective negative-stock policy: warehouse override, else company default.
     */
    public function allowsNegativeStock(): bool
    {
        return $this->allow_negative_stock ?? (bool) $this->company->allow_negative_stock;
    }
}
