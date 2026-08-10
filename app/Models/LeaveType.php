<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $annual_quota
 */
class LeaveType extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'name', 'code', 'annual_quota', 'is_paid', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['annual_quota' => 'integer', 'is_paid' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
