<?php

namespace App\Actions\Manufacturing;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\ManufacturingOrderStatus;
use App\Exceptions\ManufacturingException;
use App\Models\ManufacturingOrder;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Short-closes a manufacturing order that produced LESS than the planned quantity
 * (real-factory reality — spec: partial production). Whatever WIP value remains
 * (materials issued but not turned into finished goods) is written off to the
 * inventory-adjustment account as a production variance, so the books stay clean
 * and WIP nets to zero. The reason is appended to the order notes for audit.
 *
 * Guards:
 *  - order must be InProgress (materials already issued).
 *  - at least one unit must have been produced. If none, the user should Cancel.
 */
class ShortCloseManufacturingOrder
{
    public function __construct(
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(ManufacturingOrder $order, string $reason): ManufacturingOrder
    {
        if ($order->status->isTerminal()) {
            throw ManufacturingException::notOpen();
        }
        if ($order->status !== ManufacturingOrderStatus::InProgress) {
            throw ManufacturingException::materialsNotIssued();
        }
        if (BigDecimal::of($order->quantity_produced)->isLessThanOrEqualTo(0)) {
            throw ManufacturingException::nothingToShortClose();
        }

        return DB::transaction(function () use ($order, $reason): ManufacturingOrder {
            $company = $order->warehouse->company;
            $companyId = (int) $order->company_id;
            $wip = BigDecimal::of($order->wip_cost);

            // Write off any remaining WIP value as a production variance.
            if ($wip->isPositive()) {
                $draft = JournalDraft::make(
                    Carbon::now()->toDateString(),
                    memo: 'Short-close write-off '.($order->reference ?? "MO #{$order->getKey()}").': '.$reason,
                    source: $order,
                )
                    ->debit($this->accounts->get('inventory_adjustment', $companyId), $wip)
                    ->credit($this->accounts->get('wip', $companyId), $wip);
                $this->postJournal->handle($draft, $company);
            }

            $note = trim(($order->notes ?? '')."\nShort-closed: {$reason}");
            $order->update([
                'status' => ManufacturingOrderStatus::ShortClosed,
                'wip_cost' => '0',
                'completed_at' => Carbon::now(),
                'notes' => $note,
            ]);

            return $order->refresh();
        });
    }
}
