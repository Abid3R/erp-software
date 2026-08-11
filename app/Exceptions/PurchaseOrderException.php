<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Purchase-order / receiving violations. Stable code (spec #67).
 */
class PurchaseOrderException extends RuntimeException
{
    public string $errorCode = 'INVALID_PURCHASE_ORDER';

    public static function notReceivable(): self
    {
        return new self('This purchase order is not open for receiving.');
    }

    public static function overReceipt(): self
    {
        return new self('Cannot receive more than the outstanding quantity on a line.');
    }

    public static function nothingToReceive(): self
    {
        return new self('Enter a quantity to receive on at least one line.');
    }
}
