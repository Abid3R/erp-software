<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryTransactionType;
use App\Enums\StockTransferStatus;
use App\Exceptions\StockTransferException;
use App\Models\StockTransfer;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Stock Transfer (spec: Inventory → Transfers). Each line issues stock out
 * of the source warehouse at its moving-average cost, then receives that same
 * quantity into the destination warehouse at the same unit cost — total company
 * inventory value is preserved, so no accounting entry is posted. Atomic: a
 * short-stock line aborts the whole transfer.
 */
class PostStockTransfer
{
    public function __construct(
        private IssueStock $issueStock,
        private ReceiveStock $receiveStock,
    ) {}

    public function handle(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status === StockTransferStatus::Posted) {
            throw StockTransferException::alreadyPosted();
        }
        if ((int) $transfer->from_warehouse_id === (int) $transfer->to_warehouse_id) {
            throw StockTransferException::sameWarehouse();
        }

        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer->loadMissing('lines.product', 'fromWarehouse', 'toWarehouse');
            $moved = 0;

            foreach ($transfer->lines as $line) {
                $qty = BigDecimal::of($line->quantity);
                if ($qty->isLessThanOrEqualTo(0)) {
                    continue;
                }

                $issued = $this->issueStock->handle(
                    $transfer->fromWarehouse, $line->product, (string) $qty,
                    InventoryTransactionType::TransferOut, $transfer,
                );
                $this->receiveStock->handle(
                    $transfer->toWarehouse, $line->product, (string) $qty, (string) $issued->unit_cost,
                    InventoryTransactionType::TransferIn, $transfer,
                );
                $moved++;
            }

            if ($moved === 0) {
                throw StockTransferException::empty();
            }

            $transfer->update(['status' => StockTransferStatus::Posted]);

            return $transfer->refresh();
        });
    }
}
