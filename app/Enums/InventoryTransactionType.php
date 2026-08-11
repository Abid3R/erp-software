<?php

namespace App\Enums;

/**
 * Every kind of movement recorded on the inventory ledger (spec #14). Inbound
 * movements add stock and may recompute the moving average; outbound movements
 * remove stock at the current average and never change it (see INVENTORY.md).
 */
enum InventoryTransactionType: string
{
    case Opening = 'opening';
    case PurchaseReceipt = 'purchase_receipt';
    case SalesIssue = 'sales_issue';
    case PurchaseReturn = 'purchase_return';
    case SalesReturn = 'sales_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Damage = 'damage';
    case ManufactureIssue = 'manufacture_issue';     // raw materials consumed by production
    case ManufactureReceipt = 'manufacture_receipt'; // finished goods produced

    /** True if this movement increases on-hand quantity. */
    public function isInbound(): bool
    {
        return match ($this) {
            self::Opening, self::PurchaseReceipt, self::SalesReturn,
            self::AdjustmentIn, self::TransferIn, self::ManufactureReceipt => true,
            default => false,
        };
    }

    /** +1 for inbound, -1 for outbound — the sign of the stored quantity. */
    public function sign(): int
    {
        return $this->isInbound() ? 1 : -1;
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
