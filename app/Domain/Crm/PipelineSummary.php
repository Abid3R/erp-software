<?php

namespace App\Domain\Crm;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Sales pipeline summary (spec: CRM): open opportunities grouped by stage, with
 * each stage's deal count, total value and probability-weighted value. Read-only.
 */
final class PipelineSummary
{
    /** @return list<array{stage: string, label: string, count: int, total: string, weighted: string}> */
    public static function for(int $companyId): array
    {
        $rows = [];

        foreach (OpportunityStage::cases() as $stage) {
            if (! $stage->isOpen()) {
                continue; // pipeline covers open deals only
            }

            $opportunities = Opportunity::query()->where('company_id', $companyId)->where('stage', $stage->value)->get();
            $total = $opportunities->reduce(fn (BigDecimal $c, Opportunity $o) => $c->plus(BigDecimal::of($o->expected_value)), BigDecimal::zero());
            $weighted = $opportunities->reduce(fn (BigDecimal $c, Opportunity $o) => $c->plus($o->weightedValue()), BigDecimal::zero());

            $rows[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $opportunities->count(),
                'total' => (string) $total->toScale(2, RoundingMode::HALF_UP),
                'weighted' => (string) $weighted->toScale(2, RoundingMode::HALF_UP),
            ];
        }

        return $rows;
    }
}
