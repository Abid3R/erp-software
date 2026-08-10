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

    /** Scheduled working minutes (span − break); night shifts cross midnight. */
    public function scheduledMinutes(): int
    {
        $toMinutes = static function (string $time): int {
            $parts = explode(':', $time);

            return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
        };

        $start = $toMinutes((string) $this->start_time);
        $end = $toMinutes((string) $this->end_time);
        if ($end < $start) {
            $end += 1440;
        }

        return max(0, ($end - $start) - $this->break_minutes);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
