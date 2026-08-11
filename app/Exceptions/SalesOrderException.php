<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sales-order / delivery violations. Stable code (spec #67).
 */
class SalesOrderException extends RuntimeException
{
    public string $errorCode = 'INVALID_SALES_ORDER';

    public static function notDeliverable(): self
    {
        return new self('This sales order is not open for delivery.');
    }

    public static function overDelivery(): self
    {
        return new self('Cannot deliver more than the outstanding quantity on a line.');
    }

    public static function nothingToDeliver(): self
    {
        return new self('Enter a quantity to deliver on at least one line.');
    }
}
