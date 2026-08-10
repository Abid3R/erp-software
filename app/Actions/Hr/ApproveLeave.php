<?php

namespace App\Actions\Hr;

use App\Domain\Hr\LeaveDecision;
use App\Enums\ApprovalStatus;
use App\Models\LeaveRequest;
use App\Models\User;

class ApproveLeave
{
    public function handle(LeaveRequest $request, User $user, ?string $note = null): LeaveRequest
    {
        LeaveDecision::authorize($request, $user);

        $request->update([
            'status' => ApprovalStatus::Approved,
            'decided_by' => $user->getKey(),
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        return $request->refresh();
    }
}
