<?php

namespace App\Actions\Process;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\ProcessMode;
use App\Enums\ProcessOrderStatus;
use App\Exceptions\ProcessException;
use App\Models\ProcessOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records the knitting (or dyeing) service charge on a *sub-contract* process
 * order — the Bangladesh job-work practice where an outside knitter is paid a rate
 * per KG to convert the mill's yarn into grey fabric.
 *
 * Charge = billed quantity × service rate. Unlike in-house conversion cost (which
 * accrues to an internal overhead account), the sub-contract charge is a real
 * liability to the sub-contractor, so it posts:
 *
 *     Dr Work-in-Progress   (the fabric absorbs the knitting cost)
 *     Cr Accounts Payable   (party = the sub-contractor)
 *
 * The charge is capitalised into the order's WIP/total cost so the received grey
 * fabric carries yarn + knitting cost. Idempotent: a second call is refused.
 * The service currency is treated as the base currency (single-currency GL).
 */
class RecordSubcontractCharge
{
    public function __construct(
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    /**
     * @param  array{bill_quantity?: string|float|int|null, service_rate?: string|float|int|null}  $overrides
     */
    public function handle(ProcessOrder $order, array $overrides = []): ProcessOrder
    {
        if ($order->mode !== ProcessMode::Subcontract) {
            throw ProcessException::notSubcontract();
        }
        if ($order->status !== ProcessOrderStatus::InProgress) {
            throw ProcessException::notInProgress();
        }
        if ($order->service_charged_at !== null) {
            throw ProcessException::alreadyCharged();
        }
        if ($order->subcontractor_id === null) {
            throw ProcessException::subcontractorRequired();
        }

        $billQty = BigDecimal::of((string) ($overrides['bill_quantity'] ?? $order->bill_quantity ?? '0'));
        $rate = BigDecimal::of((string) ($overrides['service_rate'] ?? $order->service_rate ?? '0'));
        $charge = $billQty->multipliedBy($rate);

        if (! $charge->isPositive()) {
            throw ProcessException::noServiceCharge();
        }

        return DB::transaction(function () use ($order, $billQty, $rate, $charge): ProcessOrder {
            $order->loadMissing('warehouse.company', 'subcontractor');
            $companyId = (int) $order->company_id;
            $charge = $charge->toScale(2, RoundingMode::HALF_UP);

            $draft = JournalDraft::make(
                Carbon::now()->toDateString(),
                memo: 'Sub-contract charge '.$order->reference.' — '.($order->subcontractor?->name ?? 'sub-contractor'),
                source: $order,
            )
                ->debit($this->accounts->get('wip', $companyId), $charge)
                ->credit($this->accounts->get('payable', $companyId), $charge, null, $order->subcontractor);
            $this->postJournal->handle($draft, $order->warehouse->company);

            $newTotal = BigDecimal::of($order->total_cost)->plus($charge);
            $baseQty = BigDecimal::of($order->produced_quantity)->isPositive()
                ? BigDecimal::of($order->produced_quantity)
                : BigDecimal::of($order->planned_quantity);

            $order->update([
                'bill_quantity' => (string) $billQty->toScale(4, RoundingMode::HALF_UP),
                'service_rate' => (string) $rate->toScale(4, RoundingMode::HALF_UP),
                'service_charge' => (string) $charge,
                'service_charged_at' => Carbon::now(),
                'total_cost' => (string) $newTotal->toScale(2, RoundingMode::HALF_UP),
                'wip_cost' => (string) BigDecimal::of($order->wip_cost)->plus($charge)->toScale(2, RoundingMode::HALF_UP),
                'output_unit_cost' => (string) ($baseQty->isPositive()
                    ? $newTotal->dividedBy($baseQty, 4, RoundingMode::HALF_UP)
                    : BigDecimal::zero()),
            ]);

            return $order->refresh();
        });
    }
}
