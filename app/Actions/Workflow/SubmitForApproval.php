<?php

namespace App\Actions\Workflow;

use App\Enums\ApprovalStatus;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalFlow;
use App\Models\ApprovalRequest;
use App\Support\CompanyContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Open an approval request for a document, selecting the configured flow whose
 * subject type and amount band match (spec #25). Thresholds and steps are data,
 * never hard-coded.
 */
class SubmitForApproval
{
    public function __construct(private CompanyContext $context) {}

    public function handle(Model $approvable, BigDecimal|string|int $amount, ?string $subjectType = null): ApprovalRequest
    {
        $subjectType ??= $approvable->getMorphClass();
        $companyId = $approvable->getAttribute('company_id') ?? $this->context->currentId();

        if ($companyId === null) {
            throw new ApprovalException('No company context for approval.');
        }

        $amount = (string) BigDecimal::of($amount)->toScale(2, RoundingMode::HALF_UP);

        $flow = ApprovalFlow::query()
            ->where('company_id', $companyId)
            ->where('subject_type', $subjectType)
            ->where('is_active', true)
            ->where('min_amount', '<=', $amount)
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount))
            ->orderByDesc('min_amount')   // most specific band wins
            ->first();

        $firstStep = $flow?->steps()->first();

        if ($flow === null || $firstStep === null) {
            throw ApprovalException::noFlow($subjectType);
        }

        return ApprovalRequest::query()->create([
            'company_id' => $companyId,
            'approval_flow_id' => $flow->getKey(),
            'approvable_type' => $approvable->getMorphClass(),
            'approvable_id' => $approvable->getKey(),
            'amount' => $amount,
            'status' => ApprovalStatus::Pending,
            'current_step' => $firstStep->sequence,
            'requested_by' => Auth::id(),
        ]);
    }
}
