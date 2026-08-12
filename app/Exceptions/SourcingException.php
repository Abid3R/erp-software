<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Requisition / RFQ sourcing violations. Stable code (spec #67).
 */
class SourcingException extends RuntimeException
{
    public string $errorCode = 'INVALID_SOURCING';

    public static function requisitionNotApproved(): self
    {
        return new self('Only an approved requisition can be turned into an RFQ.');
    }

    public static function rfqNotAwardable(): self
    {
        return new self('This RFQ is not open to be awarded.');
    }

    public static function supplierHasNoQuotes(): self
    {
        return new self('The chosen supplier has not quoted any line on this RFQ.');
    }
}
