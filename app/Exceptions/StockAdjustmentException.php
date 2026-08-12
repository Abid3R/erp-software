<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Stock-adjustment document violations (spec: Inventory → Adjustments).
 */
class StockAdjustmentException extends RuntimeException
{
    public string $errorCode = 'INVALID_STOCK_ADJUSTMENT';

    public static function alreadyPosted(): self
    {
        return new self('This stock adjustment has already been posted.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line with a quantity to adjust.');
    }
}
