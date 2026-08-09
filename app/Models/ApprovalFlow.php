<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $subject_type
 */
class ApprovalFlow extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'name', 'subject_type', 'min_amount', 'max_amount', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<ApprovalFlowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalFlowStep::class)->orderBy('sequence');
    }
}
