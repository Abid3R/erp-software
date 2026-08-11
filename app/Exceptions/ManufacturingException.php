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
}
