<?php

namespace App\Domain\Reporting;

use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * General Ledger for one account: opening balance, each posted line in date order
 * with a running balance on the account's normal side, and the closing balance
 * (spec #31). Derived from the ledger.
 */
final class GeneralLedger
{
    private const SCALE = 2;

    /**
     * @return array{
     *   opening: BigDecimal, closing: BigDecimal,
     *   lines: list<array{date: string, journal_id: int, memo: string|null, debit: string, credit: string, running: string}>
     * }
     */
    public static function forAccount(Account $account, ?string $from = null, ?string $to = null): array
    {
        $debitNormal = $account->type->isDebitNormal();
        $opening = self::openingBalance($account->getKey(), $debitNormal, $from);

        $rows = JournalLine::withoutGlobalScopes()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('journal_lines.account_id', $account->getKey())
            ->where('journals.status', JournalStatus::Posted->value)
            ->when($from, fn ($q) => $q->whereDate('journals.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journals.date', '<=', $to))
            ->orderBy('journals.date')
            ->orderBy('journal_lines.id')
            ->toBase()
            ->get(['journals.date as date', 'journal_lines.journal_id', 'journal_lines.memo', 'journal_lines.debit', 'journal_lines.credit']);

        $running = $opening;
        $lines = [];

        foreach ($rows as $row) {
            $debit = BigDecimal::of((string) $row->debit);
            $credit = BigDecimal::of((string) $row->credit);
            $delta = $debitNormal ? $debit->minus($credit) : $credit->minus($debit);
            $running = $running->plus($delta)->toScale(self::SCALE, RoundingMode::HALF_UP);

            $lines[] = [
                'date' => (string) $row->date,
                'journal_id' => (int) $row->journal_id,
                'memo' => $row->memo === null ? null : (string) $row->memo,
                'debit' => (string) $debit->toScale(self::SCALE, RoundingMode::HALF_UP),
                'credit' => (string) $credit->toScale(self::SCALE, RoundingMode::HALF_UP),
                'running' => (string) $running,
            ];
        }

        return ['opening' => $opening, 'closing' => $running, 'lines' => $lines];
    }

    private static function openingBalance(int $accountId, bool $debitNormal, ?string $from): BigDecimal
    {
        if ($from === null) {
            return BigDecimal::zero()->toScale(self::SCALE);
        }

        $row = JournalLine::withoutGlobalScopes()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('journal_lines.account_id', $accountId)
            ->where('journals.status', JournalStatus::Posted->value)
            ->whereDate('journals.date', '<', $from)
            ->toBase()
            ->selectRaw('COALESCE(SUM(debit), 0) d, COALESCE(SUM(credit), 0) c')
            ->first();

        $debit = BigDecimal::of((string) ($row->d ?? '0'));
        $credit = BigDecimal::of((string) ($row->c ?? '0'));

        return ($debitNormal ? $debit->minus($credit) : $credit->minus($debit))
            ->toScale(self::SCALE, RoundingMode::HALF_UP);
    }
}
