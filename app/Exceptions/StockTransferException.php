<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Stock-transfer document violations (spec: Inventory → Transfers).
 */
class StockTransferException extends RuntimeException
{
    public string $errorCode = 'INVALID_STOCK_TRANSFER';

    public static function alreadyPosted(): self
    {
        return new self('This stock transfer has already been posted.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line with a quantity to transfer.');
    }

    public static function sameWarehouse(): self
    {
        return new self('The source and destination warehouses must be different.');
    }
}
