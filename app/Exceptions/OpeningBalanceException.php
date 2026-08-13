<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Opening-balance document violations (spec: onboarding — Phase 26/27).
 */
class OpeningBalanceException extends RuntimeException
{
    public string $errorCode = 'INVALID_OPENING_BALANCE';

    public static function alreadyPosted(): self
    {
        return new self('These opening balances have already been posted.');
    }

    public static function empty(): self
    {
        return new self('Add at least one opening balance line.');
    }
}
