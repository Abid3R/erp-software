<?php

namespace App\Actions\Process;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\ProcessOrderStatus;
use App\Exceptions\ProcessException;
use App\Models\ProcessOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Adds the conversion costs (labour, machine, utilities, overhead) of a process
 * run and capitalises them into WIP — Dr WIP / Cr Accrued Production Overhead —
 * so finished-goods absorb the full production cost, not just materials. Machine
 * cost is derived from the machine's hourly rate × runtime hours. The order's
 * cost breakdown and actual unit cost are recomputed. Atomic.
 *
 * @param array{labour?: string|float, machine_hours?: string|float, utility?: string|float, overhead?: string|float}  $costs
 */
class RecordProcessCosts
{
    public function __construct(
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    /**
     * @param  array<string, string|float|int|null>  $costs
     */
    public function handle(ProcessOrder $order, array $costs): ProcessOrder
    {
        // Capture conversion costs after materials are issued and before production
        // is recorded, so finished goods absorb them into the unit cost.
        if ($order->status !== ProcessOrderStatus::InProgress) {
            throw ProcessException::notInProgress();
        }

        return DB::transaction(function () use ($order, $costs): ProcessOrder {
            $order->loadMissing('machine', 'warehouse.company');
            $companyId = (int) $order->company_id;

            $labour = BigDecimal::of((string) ($costs['labour'] ?? '0'));
            $utility = BigDecimal::of((string) ($costs['utility'] ?? '0'));
            $overhead = BigDecimal::of((string) ($costs['overhead'] ?? '0'));
            $machineHours = BigDecimal::of((string) ($costs['machine_hours'] ?? '0'));
            $hourly = BigDecimal::of((string) ($order->machine?->hourly_cost ?? '0'));
            $machine = $machineHours->multipliedBy($hourly);

            $addition = $labour->plus($machine)->plus($utility)->plus($overhead);

            if ($addition->isPositive()) {
                $draft = JournalDraft::make(
                    Carbon::now()->toDateString(),
                    memo: 'Production conversion cost '.$order->reference,
                    source: $order,
                )
                    ->debit($this->accounts->get('wip', $companyId), $addition)
                    ->credit($this->accounts->get('accrued_overhead', $companyId), $addition);
                $this->postJournal->handle($draft, $order->warehouse->company);
            }

            $newTotal = BigDecimal::of($order->total_cost)->plus($addition);
            $baseQty = BigDecimal::of($order->produced_quantity)->isPositive()
                ? BigDecimal::of($order->produced_quantity)
                : BigDecimal::of($order->planned_quantity);

            $order->update([
                'labour_cost' => (string) BigDecimal::of($order->labour_cost)->plus($labour)->toScale(2, RoundingMode::HALF_UP),
                'machine_cost' => (string) BigDecimal::of($order->machine_cost)->plus($machine)->toScale(2, RoundingMode::HALF_UP),
                'utility_cost' => (string) BigDecimal::of($order->utility_cost)->plus($utility)->toScale(2, RoundingMode::HALF_UP),
                'overhead_cost' => (string) BigDecimal::of($order->overhead_cost)->plus($overhead)->toScale(2, RoundingMode::HALF_UP),
                'total_cost' => (string) $newTotal->toScale(2, RoundingMode::HALF_UP),
                'wip_cost' => (string) BigDecimal::of($order->wip_cost)->plus($addition)->toScale(2, RoundingMode::HALF_UP),
                'output_unit_cost' => (string) ($baseQty->isPositive()
                    ? $newTotal->dividedBy($baseQty, 4, RoundingMode::HALF_UP)
                    : BigDecimal::zero()),
            ]);

            return $order->refresh();
        });
    }
}
