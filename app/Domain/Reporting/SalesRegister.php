<?php

namespace App\Domain\Reporting;

use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Sales register (spec: Reports — Phase 15): every sales order in a period with its
 * ordered and delivered value. Derived from the sales-order documents.
 *
 * @phpstan-type SalesRow array{date: string, number: string, customer: string, status: string, ordered: string, delivered: string}
 */
final class SalesRegister
{
    private const MONEY = 2;

    /**
     * @return array{rows: list<SalesRow>, ordered: string, delivered: string}
     */
    public static function for(int $companyId, ?string $from = null, ?string $to = null): array
    {
        $orders = SalesOrder::query()
            ->with(['customer:id,name', 'lines'])
            ->where('company_id', $companyId)
            ->when($from, fn ($q) => $q->whereDate('order_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('order_date', '<=', $to))
            ->orderBy('order_date')
            ->get();

        $rows = [];
        $totalOrdered = BigDecimal::zero();
        $totalDelivered = BigDecimal::zero();

        foreach ($orders as $order) {
            $ordered = $order->lines->reduce(
                fn (BigDecimal $c, SalesOrderLine $l): BigDecimal => $c->plus(BigDecimal::of($l->quantity_ordered)->multipliedBy($l->unit_price)),
                BigDecimal::zero(),
            )->toScale(self::MONEY, RoundingMode::HALF_UP);
            $delivered = $order->lines->reduce(
                fn (BigDecimal $c, SalesOrderLine $l): BigDecimal => $c->plus(BigDecimal::of($l->quantity_delivered)->multipliedBy($l->unit_price)),
                BigDecimal::zero(),
            )->toScale(self::MONEY, RoundingMode::HALF_UP);

            $totalOrdered = $totalOrdered->plus($ordered);
            $totalDelivered = $totalDelivered->plus($delivered);

            $rows[] = [
                'date' => Carbon::parse($order->order_date)->toDateString(),
                'number' => $order->so_number,
                'customer' => (string) $order->customer->name,
                'status' => $order->status->label(),
                'ordered' => (string) $ordered,
                'delivered' => (string) $delivered,
            ];
        }

        return [
            'rows' => $rows,
            'ordered' => (string) $totalOrdered->toScale(self::MONEY, RoundingMode::HALF_UP),
            'delivered' => (string) $totalDelivered->toScale(self::MONEY, RoundingMode::HALF_UP),
        ];
    }
}
