<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ApprovalStatus $status
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 */
class LeaveRequest extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'days', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        // Inclusive calendar-day count derived from the (NOT NULL) date range.
        static::saving(function (LeaveRequest $request): void {
            $start = Carbon::parse($request->start_date);
            $end = Carbon::parse($request->end_date);
            $request->days = max(1, (int) $start->diffInDays($end) + 1);
        });

        // Alert HR / managers that a request is waiting.
        static::created(function (LeaveRequest $request): void {
            Notifier::toCompanyRoles(
                (int) $request->company_id,
                ['hr', 'manager'],
                'Leave request submitted',
                'A leave request is pending review.',
                url('/admin/leave-requests'),
            );
        });
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
