<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $is_off
 */
class RosterEntry extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'roster_id', 'employee_id', 'date', 'shift_id', 'is_off', 'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'date', 'is_off' => 'boolean'];
    }

    /** @return BelongsTo<Roster, $this> */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
