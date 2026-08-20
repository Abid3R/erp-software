<?php

namespace App\Domain\Reporting;

use App\Enums\PaymentDirection;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Payment / Receipt voucher register (spec: Reports — Phase 15). Lists the actual
 * Payment vouchers of one direction in a period with party, method and reference,
 * and a grand total. Derived from the live `payments` table — the same records the
 * cash/bank ledger postings are built from, so the total reconciles with the GL
 * cash/bank movement and the AR/AP settlements.
 *
 * @phpstan-type VoucherRow array{date: string, reference: string, party: string, method: string, note: string, amount: string}
 */
final class VoucherRegister
{
    private const MONEY = 2;

    /**
     * @return array{rows: list<VoucherRow>, total: string, count: int}
     */
    public static function payments(int $companyId, ?string $from = null, ?string $to = null, ?int $partyId = null): array
    {
        return self::build($companyId, PaymentDirection::Payment, $from, $to, $partyId);
    }

    /**
     * @return array{rows: list<VoucherRow>, total: string, count: int}
     */
    public static function receipts(int $companyId, ?string $from = null, ?string $to = null, ?int $partyId = null): array
    {
        return self::build($companyId, PaymentDirection::Receipt, $from, $to, $partyId);
    }

    /**
     * @return array{rows: list<VoucherRow>, total: string, count: int}
     */
    private static function build(int $companyId, PaymentDirection $direction, ?string $from, ?string $to, ?int $partyId): array
    {
        $payments = Payment::query()
            ->withoutGlobalScopes()
            ->with('party:id,name')
            ->where('company_id', $companyId)
            ->where('direction', $direction->value)
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->when($partyId !== null, fn ($q) => $q->where('party_id', $partyId))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $rows = [];
        $total = BigDecimal::zero();

        foreach ($payments as $payment) {
            $amount = BigDecimal::of((string) $payment->amount)->toScale(self::MONEY, RoundingMode::HALF_UP);
            $total = $total->plus($amount);

            /** @var object{name?: string}|null $party */
            $party = $payment->party;

            $rows[] = [
                'date' => Carbon::parse($payment->date)->toDateString(),
                'reference' => (string) ($payment->reference ?? ''),
                'party' => (string) ($party->name ?? '—'),
                'method' => $payment->method->label(),
                'note' => (string) ($payment->note ?? ''),
                'amount' => (string) $amount,
            ];
        }

        return [
            'rows' => $rows,
            'total' => (string) $total->toScale(self::MONEY, RoundingMode::HALF_UP),
            'count' => count($rows),
        ];
    }
}
