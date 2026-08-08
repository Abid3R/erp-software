<?php

namespace App\Domain\Accounting;

use App\Enums\JournalStatus;
use App\Models\JournalLine;
use Brick\Math\BigDecimal;

/**
 * Read model derived solely from posted journal lines (spec #32). For a correct
 * double-entry ledger the totals always balance; that property is what the
 * accounting tests assert.
 */
final class TrialBalance
{
    /** @return array{debit: BigDecimal, credit: BigDecimal} */
    public static function totals(int $companyId): array
    {
        $row = JournalLine::query()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('journal_lines.company_id', $companyId)
            ->where('journals.status', JournalStatus::Posted->value)
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        // The aggregate query (no GROUP BY) always returns exactly one row, and
        // COALESCE guarantees non-null totals.
        return [
            'debit' => BigDecimal::of($row->debit),
            'credit' => BigDecimal::of($row->credit),
        ];
    }

    public static function isBalanced(int $companyId): bool
    {
        $totals = self::totals($companyId);

        return $totals['debit']->isEqualTo($totals['credit']);
    }
}
