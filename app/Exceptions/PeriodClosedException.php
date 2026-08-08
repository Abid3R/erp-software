<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a posting targets a closed/locked or missing accounting period
 * (spec #13).
 */
class PeriodClosedException extends RuntimeException
{
    public string $errorCode = 'PERIOD_CLOSED';

    public static function forDate(string $date): self
    {
        return new self("No open accounting period contains {$date}.");
    }
}
