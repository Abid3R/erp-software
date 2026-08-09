<?php

namespace App\Actions\Workflow;

use App\Domain\Workflow\ApprovalGuard;
use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reject the current step, which rejects the whole request (spec #25). The
 * document may then be revised and resubmitted (a fresh request).
 */
class RejectStep
{
    public function handle(ApprovalRequest $request, User $user, ?string $comment = null): ApprovalRequest
    {
        $step = ApprovalGuard::currentStepFor($request, $user);

        return DB::transaction(function () use ($request, $user, $comment, $step) {
            $request->actions()->create([
                'step' => $step->sequence,
                'user_id' => $user->getKey(),
                'action' => 'rejected',
                'comment' => $comment,
            ]);

            $request->update(['status' => ApprovalStatus::Rejected, 'current_step' => null]);

            return $request->refresh();
        });
    }
}
