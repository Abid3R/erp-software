<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Purchase-return violations. Stable code (spec #67).
 */
class PurchaseReturnException extends RuntimeException
{
    public string $errorCode = 'INVALID_PURCHASE_RETURN';

    public static function alreadyPosted(): self
    {
        return new self('This purchase return has already been posted.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line with a quantity to return.');
    }
}
