<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised for structural posting violations: too few lines, a line with both or
 * neither debit/credit, negative amounts, or posting to an inactive/non-postable/
 * cross-company account (spec #12).
 */
class PostingException extends RuntimeException
{
    public string $errorCode = 'INVALID_POSTING';
}
