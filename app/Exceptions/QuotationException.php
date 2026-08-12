<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sales-quotation violations (spec: Order Processing → Quotations).
 */
class QuotationException extends RuntimeException
{
    public string $errorCode = 'INVALID_QUOTATION';

    public static function notConvertible(): self
    {
        return new self('Only a sent or accepted quotation can be converted to an order.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line before converting this quotation.');
    }
}
