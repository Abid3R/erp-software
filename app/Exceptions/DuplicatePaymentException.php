<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a payment with an already-seen idempotency key is submitted again
 * (spec #23 — prevent duplicate payment processing). Stable code (spec #67).
 */
class DuplicatePaymentException extends RuntimeException
{
    public string $errorCode = 'DUPLICATE_PAYMENT';

    public static function forKey(string $key): self
    {
        return new self("A payment with idempotency key '{$key}' has already been processed.");
    }
}
