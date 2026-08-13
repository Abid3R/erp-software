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
 * Invoice/document-level AR/AP aging (spec: Phase 5/15). Each obligation posted to
 * the party's control account is a document; settlements (payments/credits) are
 * FIFO-allocated to the oldest documents. Every open document is reported with its
 * original / paid / outstanding amount and its age bucket. Outstanding totals always
 * reconcile with {@see PartyLedger} and the GL control account.
 *
 * @phpstan-type DetailRow array{party_id: int, party: string, document: string, date: string, original: string, paid: string, outstanding: string, bucket: string}
 * @phpstan-type Totals array{current: string, d30: string, d60: string, d90plus: string, total: string}
 */
final class PartyAgingDetail
{
    private const SCALE = 2;

    /**
     * @return array{rows: list<DetailRow>, totals: Totals}
     */
    public static function receivables(int $companyId, ?string $asOf = null): array
    {
        return self::build($companyId, (string) config('erp.accounts.receivable'), Customer::class, debitNormal: true, asOf: $asOf);
    }

    /**
     * @return array{rows: list<DetailRow>, totals: Totals}
     */
    public static function payables(int $companyId, ?string $asOf = null): array
    {
        return self::build($companyId, (string) config('erp.accounts.payable'), Supplier::class, debitNormal: false, asOf: $asOf);
    }

    /**
     * @param  class-string  $morphClass
     * @return array{rows: list<DetailRow>, totals: Totals}
     */
    private static function build(int $companyId, string $accountCode, string $morphClass, bool $debitNormal, ?string $asOf): array
    {
        $asOfDate = Carbon::parse($asOf ?? Carbon::now()->toDateString())->startOfDay();

        $lines = JournalLine::withoutGlobalScopes()
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
            ->get([
                'journal_lines.party_id',
                'journals.date',
                'journals.reference',
                'journals.memo',
                'journal_lines.debit',
                'journal_lines.credit',
            ]);

        /** @var array<int, list<array{date: string, doc: string, amount: BigDecimal}>> $obligations */
        $obligations = [];
        /** @var array<int, BigDecimal> $settlements */
        $settlements = [];

        foreach ($lines as $line) {
            $partyId = (int) $line->party_id;
            $debit = BigDecimal::of((string) $line->debit);
            $credit = BigDecimal::of((string) $line->credit);
            $increase = $debitNormal ? $debit->minus($credit) : $credit->minus($debit);

            if ($increase->isPositive()) {
                $doc = (string) ($line->memo ?: $line->reference ?: 'Opening/journal');
                $obligations[$partyId][] = ['date' => (string) $line->date, 'doc' => $doc, 'amount' => $increase];
            } elseif ($increase->isNegative()) {
                $settlements[$partyId] = ($settlements[$partyId] ?? BigDecimal::zero())->plus($increase->abs());
            }
        }

        $names = self::partyNames($morphClass, array_keys($obligations));

        $rows = [];
        $totals = ['current' => '0', 'd30' => '0', 'd60' => '0', 'd90plus' => '0', 'total' => '0'];

        foreach ($obligations as $partyId => $docs) {
            $remaining = $settlements[$partyId] ?? BigDecimal::zero();

            foreach ($docs as $doc) {
                $original = $doc['amount'];
                $paid = BigDecimal::zero();
                if ($remaining->isPositive()) {
                    $paid = $remaining->isGreaterThanOrEqualTo($original) ? $original : $remaining;
                    $remaining = $remaining->minus($paid);
                }
                $outstanding = $original->minus($paid);
                if ($outstanding->isLessThanOrEqualTo(0)) {
                    continue; // fully settled — not an open item
                }

                $ageDays = (int) Carbon::parse($doc['date'])->startOfDay()->diffInDays($asOfDate, absolute: false);
                $bucket = self::bucketFor($ageDays);

                $rows[] = [
                    'party_id' => $partyId,
                    'party' => $names[$partyId] ?? ('#'.$partyId),
                    'document' => $doc['doc'],
                    'date' => $doc['date'],
                    'original' => (string) $original->toScale(self::SCALE, RoundingMode::HALF_UP),
                    'paid' => (string) $paid->toScale(self::SCALE, RoundingMode::HALF_UP),
                    'outstanding' => (string) $outstanding->toScale(self::SCALE, RoundingMode::HALF_UP),
                    'bucket' => $bucket,
                ];

                $totals[$bucket] = (string) BigDecimal::of($totals[$bucket])->plus($outstanding);
                $totals['total'] = (string) BigDecimal::of($totals['total'])->plus($outstanding);
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = (string) BigDecimal::of($value)->toScale(self::SCALE, RoundingMode::HALF_UP);
        }

        usort($rows, fn (array $a, array $b): int => [$a['party'], $a['date']] <=> [$b['party'], $b['date']]);

        return ['rows' => $rows, 'totals' => $totals];
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
}
