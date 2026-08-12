<?php

namespace App\Enums;

/**
 * Direction of a stock-adjustment line: In increases on-hand (found/gain), Out
 * decreases it (shrinkage/damage). Maps to the inventory ledger movement type.
 */
enum StockAdjustmentDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Increase (in)',
            self::Out => 'Decrease (out)',
        };
    }

    public function transactionType(): InventoryTransactionType
    {
        return match ($this) {
            self::In => InventoryTransactionType::AdjustmentIn,
            self::Out => InventoryTransactionType::AdjustmentOut,
        };
    }

    public function color(): string
    {
        return $this === self::In ? 'success' : 'warning';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
