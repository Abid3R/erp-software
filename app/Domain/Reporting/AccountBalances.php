<?php

namespace App\Domain\Reporting;

use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;

/**
 * Balance helpers derived solely from posted journal lines (spec #32). All
 * reports (P&L, Balance Sheet, GL, Trial Balance) build on these — one
 * authoritative source, never a parallel calculation.
 */
final class AccountBalances
{
    private const SCALE = 2;

    /** Net balance for all accounts of a type, on the type's normal side. */
    public static function netForType(int $companyId, AccountType $type, ?string $from = null, ?string $to = null): BigDecimal
    {
        $row = self::base($companyId, $from, $to)
            ->where('accounts.type', $type->value)
            ->selectRaw('COALESCE(SUM(debit), 0) d, COALESCE(SUM(credit), 0) c')
            ->first();

        $debit = BigDecimal::of($row->d);
        $credit = BigDecimal::of($row->c);

        $net = $type->isDebitNormal() ? $debit->minus($credit) : $credit->minus($debit);

        return $net->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    /** Net movement for a single account (debit - credit), unsigned by type. */
    public static function debitMinusCredit(int $accountId, ?string $from = null, ?string $to = null): BigDecimal
    {
        $row = self::base(null, $from, $to)
            ->where('journal_lines.account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit), 0) d, COALESCE(SUM(credit), 0) c')
            ->first();

        return BigDecimal::of($row->d)->minus(BigDecimal::of($row->c))->toScale(self::SCALE, RoundingMode::HALF_UP);
    }

    /** Balance of one account on its own normal side (positive = normal balance). */
    public static function netForAccount(Account $account, ?string $from = null, ?string $to = null): BigDecimal
    {
        $raw = self::debitMinusCredit($account->getKey(), $from, $to);

        return $account->type->isDebitNormal() ? $raw : $raw->negated();
    }

    private static function base(?int $companyId, ?string $from, ?string $to): Builder
    {
        // Reports are explicit about company; bypass the global scope so a request's
        // active company never silently narrows a report for another company.
        $query = JournalLine::withoutGlobalScopes()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', JournalStatus::Posted->value)
            ->toBase();

        if ($companyId !== null) {
            $query->where('journal_lines.company_id', $companyId);
        }
        if ($from !== null) {
            $query->whereDate('journals.date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('journals.date', '<=', $to);
        }

        return $query;
    }
}
