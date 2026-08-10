<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $start_time
 * @property string $end_time
 * @property int $break_minutes
 */
class Shift extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = ['company_id', 'name', 'code', 'start_time', 'end_time', 'break_minutes', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['break_minutes' => 'integer', 'is_active' => 'boolean'];
    }

    public function label(): string
    {
        return $this->name.' ('.substr((string) $this->start_time, 0, 5).'–'.substr((string) $this->end_time, 0, 5).')';
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
