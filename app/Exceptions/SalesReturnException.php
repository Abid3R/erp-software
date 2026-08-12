<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sales-return document violations (spec: Order Processing → Returns).
 */
class SalesReturnException extends RuntimeException
{
    public string $errorCode = 'INVALID_SALES_RETURN';

    public static function alreadyPosted(): self
    {
        return new self('This sales return has already been posted.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line with a quantity to return.');
    }
}
