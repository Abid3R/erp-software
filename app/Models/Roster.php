<?php

namespace App\Models;

use App\Enums\RosterStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property RosterStatus $status
 */
class Roster extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'name', 'start_date', 'end_date', 'status',
        'off_days_per_week', 'max_hours_per_week', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => RosterStatus::class,
            'off_days_per_week' => 'integer',
            'max_hours_per_week' => 'integer',
        ];
    }

    /** @return HasMany<RosterEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }
}
