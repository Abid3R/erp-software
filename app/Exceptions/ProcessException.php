<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Textile process-order violations (knitting, dyeing, finishing, …). Stable code.
 */
class ProcessException extends RuntimeException
{
    public string $errorCode = 'INVALID_PROCESS_ORDER';

    public static function notOpen(): self
    {
        return new self('This process order is not open.');
    }

    public static function alreadyIssued(): self
    {
        return new self('Materials have already been issued for this process order.');
    }

    public static function materialsNotIssued(): self
    {
        return new self('Issue the input materials before recording production.');
    }

    public static function notInProgress(): self
    {
        return new self('Production can only be recorded while the order is in progress.');
    }

    public static function invalidQuantity(): self
    {
        return new self('Process quantities must be greater than zero.');
    }

    public static function overProduction(): self
    {
        return new self('Cannot produce more than the remaining order quantity.');
    }

    public static function noInputs(): self
    {
        return new self('Add at least one input material before issuing.');
    }

    public static function noOutputProduct(): self
    {
        return new self('Set the output product before recording production.');
    }

    public static function notInQc(): self
    {
        return new self('Only an order awaiting QC can be passed.');
    }

    public static function labDipRequired(): self
    {
        return new self('This process requires an approved lab dip. Select one before issuing materials.');
    }

    public static function rejectedExceedsProduced(): self
    {
        return new self('Rejected quantity cannot exceed the quantity produced.');
    }
}
