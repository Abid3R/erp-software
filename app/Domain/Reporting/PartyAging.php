<?php

namespace App\Domain\Reporting;

use App\Enums\JournalStatus;
use App\Models\Customer;
use App\Models\JournalLine;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * AR/AP aging (spec: Reports — Phase 15). Derives per-party open balances from the
 * party-tagged control-account lines on the posted ledger, allocates settlements
 * FIFO against the oldest documents, and buckets the remaining open amount by age.
 * Bucket totals always reconcile with {@see PartyLedger} and the GL control account.
 *
 * @phpstan-type Buckets array{current: string, d30: string, d60: string, d90plus: string, total: string}
 * @phpstan-type Row array{party_id: int, name: string, code: string|null, current: string, d30: string, d60: string, d90plus: string, total: string}
 */
final class PartyAging
{
    private const SCALE = 2;

    /**
     * @return array{rows: list<Row>, totals: Buckets}
     */
    public static function receivables(int $companyId, ?string $asOf = null): array
    {
        return self::build(
            $companyId,
            (string) config('erp.accounts.receivable'),
            Customer::class,
            debitNormal: true,
            asOf: $asOf,
        );
    }

    /**
     * @return array{rows: list<Row>, totals: Buckets}
     */
    public static function payables(int $companyId, ?string $asOf = null): array
    {
        return self::build(
            $companyId,
            (string) config('erp.accounts.payable'),
            Supplier::class,
            debitNormal: false,
            asOf: $asOf,
        );
    }

    /**
     * @param  class-string  $morphClass
     * @return array{rows: list<Row>, totals: Buckets}
     */
    private static function build(int $companyId, string $accountCode, string $morphClass, bool $debitNormal, ?string $asOf): array
    {
        $asOfDate = Carbon::parse($asOf ?? Carbon::now()->toDateString())->startOfDay();

        $rows = JournalLine::withoutGlobalScopes()
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.status', JournalStatus::Posted->value)
            ->where('journal_lines.company_id', $companyId)
            ->where('accounts.code', $accountCode)
            ->where('journal_lines.party_type', $morphClass)
            ->whereNotNull('journal_lines.party_id')
            ->whereDate('journals.date', '<=', $asOfDate->toDateString())
            ->orderBy('journals.date')
            ->orderBy('journal_lines.id')
            ->toBase()
            ->get(['journal_lines.party_id', 'journals.date', 'journal_lines.debit', 'journal_lines.credit']);

        /** @var array<int, list<array{date: string, amount: BigDecimal}>> $increasesByParty */
        $increasesByParty = [];
        /** @var array<int, BigDecimal> $settlementsByParty */
        $settlementsByParty = [];

        foreach ($rows as $row) {
            $partyId = (int) $row->party_id;
            $debit = BigDecimal::of((string) $row->debit);
            $credit = BigDecimal::of((string) $row->credit);
            // An "increase" is a debit for AR, a credit for AP; the opposite settles.
            $increase = $debitNormal ? $debit->minus($credit) : $credit->minus($debit);

            if ($increase->isPositive()) {
                $increasesByParty[$partyId][] = ['date' => (string) $row->date, 'amount' => $increase];
            } elseif ($increase->isNegative()) {
                $settlementsByParty[$partyId] = ($settlementsByParty[$partyId] ?? BigDecimal::zero())->plus($increase->abs());
            }
        }

        $names = self::partyNames($morphClass, array_keys($increasesByParty));

        $result = [];
        $totals = self::emptyBuckets();

        foreach ($increasesByParty as $partyId => $lots) {
            $settlement = $settlementsByParty[$partyId] ?? BigDecimal::zero();
            $buckets = self::bucketOpenLots($lots, $settlement, $asOfDate);

            // Skip parties fully settled (nothing open).
            if (BigDecimal::of($buckets['total'])->isZero()) {
                continue;
            }

            $result[] = array_merge([
                'party_id' => $partyId,
                'name' => $names[$partyId] ?? ('#'.$partyId),
                'code' => null,
            ], $buckets);

            foreach (self::bucketKeys() as $key) {
                $totals[$key] = (string) BigDecimal::of($totals[$key])->plus($buckets[$key]);
            }
            $totals['total'] = (string) BigDecimal::of($totals['total'])->plus($buckets['total']);
        }

        // Normalise every total to currency scale (so an empty report reads 0.00).
        foreach ($totals as $key => $value) {
            $totals[$key] = (string) BigDecimal::of($value)->toScale(self::SCALE, RoundingMode::HALF_UP);
        }

        usort($result, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return ['rows' => $result, 'totals' => $totals];
    }

    /**
     * FIFO-allocate the settlement total against the oldest open lots, then bucket
     * whatever remains by age.
     *
     * @param  list<array{date: string, amount: BigDecimal}>  $lots
     * @return Buckets
     */
    private static function bucketOpenLots(array $lots, BigDecimal $settlement, Carbon $asOf): array
    {
        $buckets = self::emptyBuckets();
        $remainingSettlement = $settlement;

        foreach ($lots as $lot) {
            $open = $lot['amount'];
            if ($remainingSettlement->isPositive()) {
                $applied = $remainingSettlement->isGreaterThanOrEqualTo($open) ? $open : $remainingSettlement;
                $open = $open->minus($applied);
                $remainingSettlement = $remainingSettlement->minus($applied);
            }
            if ($open->isZero()) {
                continue;
            }

            $ageDays = Carbon::parse($lot['date'])->startOfDay()->diffInDays($asOf, absolute: false);
            $key = self::bucketFor((int) $ageDays);
            $buckets[$key] = (string) BigDecimal::of($buckets[$key])->plus($open);
        }

        // Any settlement beyond all open lots (an advance/overpayment) reduces current.
        if ($remainingSettlement->isPositive()) {
            $buckets['current'] = (string) BigDecimal::of($buckets['current'])->minus($remainingSettlement);
        }

        $total = BigDecimal::zero();
        foreach (self::bucketKeys() as $key) {
            $buckets[$key] = (string) BigDecimal::of($buckets[$key])->toScale(self::SCALE, RoundingMode::HALF_UP);
            $total = $total->plus($buckets[$key]);
        }
        $buckets['total'] = (string) $total->toScale(self::SCALE, RoundingMode::HALF_UP);

        return $buckets;
    }

    private static function bucketFor(int $ageDays): string
    {
        return match (true) {
            $ageDays <= 30 => 'current',
            $ageDays <= 60 => 'd30',
            $ageDays <= 90 => 'd60',
            default => 'd90plus',
        };
    }

    /**
     * @param  class-string  $morphClass
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private static function partyNames(string $morphClass, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $morphClass;

        return $model->newQuery()->withoutGlobalScopes()->whereIn('id', $ids)
            ->pluck('name', 'id')->map(fn ($n): string => (string) $n)->all();
    }

    /** @return Buckets */
    private static function emptyBuckets(): array
    {
        return ['current' => '0', 'd30' => '0', 'd60' => '0', 'd90plus' => '0', 'total' => '0'];
    }

    /** @return list<string> */
    private static function bucketKeys(): array
    {
        return ['current', 'd30', 'd60', 'd90plus'];
    }
}
