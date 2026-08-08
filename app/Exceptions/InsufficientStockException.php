<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an issue would drive stock below zero and the warehouse does not
 * permit negative stock (spec #17). Carries a stable error code (spec #67).
 */
class InsufficientStockException extends RuntimeException
{
    public string $errorCode = 'INSUFFICIENT_STOCK';

    public static function for(string $product, string $warehouse, string $requested, string $available): self
    {
        return new self(
            "Insufficient stock for {$product} at {$warehouse}: requested {$requested}, available {$available}."
        );
    }
}
