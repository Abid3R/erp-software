<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Payroll-run posting violations (spec Phase 17).
 */
class PayrollException extends RuntimeException
{
    public string $errorCode = 'INVALID_PAYROLL';

    public static function alreadyPosted(): self
    {
        return new self('This payroll run has already been posted to the ledger.');
    }

    public static function nothingToPost(): self
    {
        return new self('The payroll run has no payslip amounts to post.');
    }
}
