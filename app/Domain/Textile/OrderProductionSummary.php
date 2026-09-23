<?php

namespace App\Domain\Textile;

use App\Models\Batch;
use App\Models\FabricRoll;
use App\Models\ProcessOrder;
use App\Models\SalesOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Rolls up all production for one sales order into a compact per-process summary —
 * so an order with many runs/batches is readable at a glance instead of scrolling
 * every batch. Read-only; sums straight from the process orders, their QC, batches
 * and rolls.
 *
 * @phpstan-type Row array{process: string, runs: int, planned: string, produced: string, wastage: string, wastage_pct: string, passed: string, rejected: string, batches: int, rolls: int, cost: string}
 */
class OrderProductionSummary
{
    /**
     * @return array{order: SalesOrder, rows: list<Row>, totals: Row}
     */
    public static function forSalesOrder(SalesOrder $order): array
    {
        $runs = ProcessOrder::query()
            ->where('sales_order_id', $order->getKey())
            ->with(['processType', 'qualityInspections'])
            ->get();

        $ids = $runs->pluck('id');
        // Batches produced by these runs, and rolls, counted per run for grouping.
        $batchCounts = Batch::query()
            ->where('source_type', (new ProcessOrder)->getMorphClass())
            ->whereIn('source_id', $ids)
            ->get()
            ->groupBy('source_id')
            ->map->count();
        $rollCounts = FabricRoll::query()
            ->whereIn('process_order_id', $ids)
            ->get()
            ->groupBy('process_order_id')
            ->map->count();

        $rows = [];
        foreach ($runs->groupBy(fn (ProcessOrder $o): string => $o->processType?->name ?? 'Other') as $process => $group) {
            $rows[] = self::row((string) $process, $group, $batchCounts, $rollCounts);
        }
        // Keep process order (knit → dye → finish) by first appearance in the runs.
        usort($rows, fn (array $a, array $b): int => $a['process'] <=> $b['process']);

        $totals = self::row('TOTAL', $runs, $batchCounts, $rollCounts);

        return ['order' => $order, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ProcessOrder>  $group
     * @param  \Illuminate\Support\Collection<int, int>  $batchCounts
     * @param  \Illuminate\Support\Collection<int, int>  $rollCounts
     * @return Row
     */
    private static function row(string $process, $group, $batchCounts, $rollCounts): array
    {
        $sum = fn (string $field): BigDecimal => $group->reduce(
            fn (BigDecimal $c, ProcessOrder $o): BigDecimal => $c->plus($o->{$field} ?? '0'), BigDecimal::zero());

        $planned = $sum('planned_quantity');
        $produced = $sum('produced_quantity');
        $wastage = $sum('wastage_quantity');
        $cost = $sum('total_cost');

        $passed = $group->reduce(fn (BigDecimal $c, ProcessOrder $o): BigDecimal => $c->plus(
            $o->qualityInspections->reduce(fn (BigDecimal $x, $i): BigDecimal => $x->plus($i->passed_quantity), BigDecimal::zero())), BigDecimal::zero());
        $rejected = $group->reduce(fn (BigDecimal $c, ProcessOrder $o): BigDecimal => $c->plus(
            $o->qualityInspections->reduce(fn (BigDecimal $x, $i): BigDecimal => $x->plus($i->rejected_quantity), BigDecimal::zero())), BigDecimal::zero());

        $wastageBase = $produced->plus($wastage);
        $wastagePct = $wastageBase->isPositive()
            ? $wastage->dividedBy($wastageBase, 5, RoundingMode::HALF_UP)->multipliedBy(100)->toScale(2, RoundingMode::HALF_UP)
            : BigDecimal::zero();

        $batches = (int) $group->sum(fn (ProcessOrder $o): int => $batchCounts->get($o->getKey(), 0));
        $rolls = (int) $group->sum(fn (ProcessOrder $o): int => $rollCounts->get($o->getKey(), 0));

        $q = fn (BigDecimal $v): string => (string) $v->toScale(2, RoundingMode::HALF_UP);

        return [
            'process' => $process,
            'runs' => $group->count(),
            'planned' => $q($planned),
            'produced' => $q($produced),
            'wastage' => $q($wastage),
            'wastage_pct' => (string) $wastagePct,
            'passed' => $q($passed),
            'rejected' => $q($rejected),
            'batches' => $batches,
            'rolls' => $rolls,
            'cost' => $q($cost),
        ];
    }
}
