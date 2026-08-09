<?php

namespace App\Domain\Workflow;

use App\Exceptions\ApprovalException;
use App\Models\ApprovalFlowStep;
use App\Models\ApprovalRequest;
use App\Models\User;

/**
 * Server-side authorization for acting on an approval step (spec #25, #5): the
 * request must be open, and the acting user must belong to the company and hold
 * the role the current step requires. Returns the step to act on.
 */
final class ApprovalGuard
{
    public static function currentStepFor(ApprovalRequest $request, User $user): ApprovalFlowStep
    {
        if (! $request->status->isOpen()) {
            throw ApprovalException::notPending();
        }

        $step = $request->currentStep();
        if ($step === null) {
            throw ApprovalException::notPending();
        }

        $isMember = $user->companies()->whereKey($request->company_id)->exists();

        if (! $isMember || ! $user->hasRole($step->role)) {
            throw ApprovalException::notAuthorized($step->role);
        }

        return $step;
    }
}
