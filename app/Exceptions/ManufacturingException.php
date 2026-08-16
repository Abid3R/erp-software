<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Manufacturing-order violations. Stable code (spec #67).
 */
class ManufacturingException extends RuntimeException
{
    public string $errorCode = 'INVALID_MANUFACTURING_ORDER';

    public static function notOpen(): self
    {
        return new self('This manufacturing order is not open for completion.');
    }

    public static function noBom(): self
    {
        return new self('A manufacturing order needs a bill of materials to be completed.');
    }

    public static function invalidQuantity(): self
    {
        return new self('Manufacturing quantities must be greater than zero.');
    }

    public static function alreadyIssued(): self
    {
        return new self('Materials have already been issued for this order.');
    }

    public static function materialsNotIssued(): self
    {
        return new self('Issue the BOM materials before recording production.');
    }

    public static function overProduction(): self
    {
        return new self('Cannot produce more than the remaining order quantity.');
    }

    public static function nothingToShortClose(): self
    {
        return new self('Short-close requires some production on the order. Cancel the order instead if nothing was produced.');
    }
}
