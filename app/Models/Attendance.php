<?php

namespace App\Models;

use App\Domain\Hr\AttendanceCalculator;
use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AttendanceStatus $status
 * @property string|null $check_in
 * @property string|null $check_out
 */
class Attendance extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'employee_id', 'shift_id', 'date', 'check_in', 'check_out',
        'status', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'remarks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'overtime_minutes' => 'integer',
        ];
    }

    public static function booted(): void
    {
        // Recompute derived minutes from the check times + shift on every save.
        static::saving(function (Attendance $attendance): void {
            $shift = $attendance->shift_id !== null ? Shift::find($attendance->shift_id) : null;
            $result = AttendanceCalculator::compute($attendance->check_in, $attendance->check_out, $shift);

            $attendance->worked_minutes = $result['worked'];
            $attendance->late_minutes = $result['late'];
            $attendance->overtime_minutes = $result['overtime'];
        });
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
