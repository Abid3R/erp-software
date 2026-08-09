<?php

namespace App\Actions\Workflow;

use App\Domain\Workflow\ApprovalGuard;
use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Approve the current step of a request. Records the decision, then advances to
 * the next step or marks the request approved when the last step is done
 * (sequential approval, spec #25). Authorization is enforced server-side.
 */
class ApproveStep
{
    public function handle(ApprovalRequest $request, User $user, ?string $comment = null): ApprovalRequest
    {
        $step = ApprovalGuard::currentStepFor($request, $user);

        return DB::transaction(function () use ($request, $user, $comment, $step) {
            $request->actions()->create([
                'step' => $step->sequence,
                'user_id' => $user->getKey(),
                'action' => 'approved',
                'comment' => $comment,
            ]);

            $nextStep = $request->flow->steps()
                ->where('sequence', '>', $step->sequence)
                ->orderBy('sequence')
                ->first();

            if ($nextStep !== null) {
                $request->update(['current_step' => $nextStep->sequence]);
            } else {
                $request->update(['status' => ApprovalStatus::Approved, 'current_step' => null]);
            }

            return $request->refresh();
        });
    }
}
