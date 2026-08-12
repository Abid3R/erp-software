<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Supplier-invoice document violations (spec: Purchasing → Supplier Invoice).
 */
class SupplierInvoiceException extends RuntimeException
{
    public string $errorCode = 'INVALID_SUPPLIER_INVOICE';

    public static function alreadyPosted(): self
    {
        return new self('This supplier invoice has already been posted.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line with an amount.');
    }
}
