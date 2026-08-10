<?php

namespace App\Domain\Hr;

use App\Exceptions\ApprovalException;
use App\Models\LeaveRequest;
use App\Models\User;

/**
 * Server-side authorization for deciding a leave request (spec #5, #25): the
 * request must be open, and the acting user must hold an approver role.
 */
final class LeaveDecision
{
    /** @var list<string> */
    private const APPROVER_ROLES = ['super_admin', 'hr', 'manager'];

    public static function authorize(LeaveRequest $request, User $user): void
    {
        if (! $request->status->isOpen()) {
            throw ApprovalException::notPending();
        }

        if (! $user->hasAnyRole(self::APPROVER_ROLES)) {
            throw ApprovalException::notAuthorized('hr/manager');
        }
    }
}
