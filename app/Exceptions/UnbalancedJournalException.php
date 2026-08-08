<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a journal's debits do not equal its credits (spec #9, #12).
 */
class UnbalancedJournalException extends RuntimeException
{
    public string $errorCode = 'UNBALANCED_JOURNAL';

    public static function make(string $debit, string $credit): self
    {
        return new self("Journal is not balanced: debits {$debit} != credits {$credit}.");
    }
}
