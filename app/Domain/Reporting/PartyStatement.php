<?php

namespace App\Domain\Reporting;

use App\Enums\JournalStatus;
use App\Models\Customer;
use App\Models\JournalLine;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer/supplier statement (spec: Reports — Phase 15): the party's control-account
 * activity in date order with a running balance, plus opening/closing. Derived from
 * the posted ledger; the closing always agrees with {@see PartyLedger}.
 *
 * @phpstan-type StatementLine array{date: string, journal_id: int, memo: string|null, debit: string, credit: string, running: string}
 */
final class PartyStatement
{
    private const SCALE = 2;

    /**
     * @return array{opening: string, closing: string, lines: list<StatementLine>}
     */
    public static function forCustomer(Customer $customer, ?string $from = null, ?string $to = null): array
    {
        return self::build($customer, (string) config('erp.accounts.receivable'), debitNormal: true, from: $from, to: $to);
    }

    /**
     * @return array{opening: string, closing: string, lines: list<StatementLine>}
     */
    public static function forSupplier(Supplier $supplier, ?string $from = null, ?string $to = null): array
    {
        return self::build($supplier, (string) config('erp.accounts.payable'), debitNormal: false, from: $from, to: $to);
    }

    /**
     * @return array{opening: string, closing: string, lines: list<StatementLine>}
     */
    private static function build(Model $party, string $accountCode, bool $debitNormal, ?string $from, ?string $to): array
    {
        $opening = self::openingBalance($party, $accountCode, $debitNormal, $from);

        $rows = JournalLine::withoutGlobalScopes()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', JournalStatus::Posted->value)
            ->where('journal_lines.company_id', $party->getAttribute('company_id'))
            ->where('accounts.code', $accountCode)
            ->where('journal_lines.party_type', $party->getMorphClass())
            ->where('journal_lines.party_id', $party->getKey())
            ->when($from, fn ($q) => $q->whereDate('journals.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journals.date', '<=', $to))
            ->orderBy('journals.date')
            ->orderBy('journal_lines.id')
            ->toBase()
            ->get(['journals.date', 'journal_lines.journal_id', 'journal_lines.memo', 'journal_lines.debit', 'journal_lines.credit']);

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

        return [
            'opening' => (string) $opening,
            'closing' => (string) $running,
            'lines' => $lines,
        ];
    }

    private static function openingBalance(Model $party, string $accountCode, bool $debitNormal, ?string $from): BigDecimal
    {
        if ($from === null) {
            return BigDecimal::zero()->toScale(self::SCALE);
        }

        $row = JournalLine::withoutGlobalScopes()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', JournalStatus::Posted->value)
            ->where('journal_lines.company_id', $party->getAttribute('company_id'))
            ->where('accounts.code', $accountCode)
            ->where('journal_lines.party_type', $party->getMorphClass())
            ->where('journal_lines.party_id', $party->getKey())
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
