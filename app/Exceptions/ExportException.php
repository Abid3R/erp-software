<?php

namespace App\Exceptions;

use Brick\Math\BigDecimal;
use RuntimeException;

/**
 * Export document violations (spec: Export — PI / LC / Commercial Invoice /
 * Packing List / Shipment). Business-rule guards that keep the export chain
 * consistent (LC over-allocation, expired/cancelled documents, duplicate posting).
 */
class ExportException extends RuntimeException
{
    public string $errorCode = 'INVALID_EXPORT_DOCUMENT';

    public static function lcOverAllocated(BigDecimal|string $remaining, BigDecimal|string $requested): self
    {
        return new self("Allocating {$requested} would exceed the letter of credit. Only {$remaining} remains available.");
    }

    public static function lcExpired(): self
    {
        return new self('This letter of credit has expired and cannot take new allocations.');
    }

    public static function lcCancelled(): self
    {
        return new self('This letter of credit has been cancelled.');
    }

    public static function lcInactive(): self
    {
        return new self('This letter of credit is not active (expired, cancelled or fully utilised).');
    }

    public static function piNotApproved(): self
    {
        return new self('Only an approved proforma invoice can be allocated to a letter of credit.');
    }

    public static function piCancelled(): self
    {
        return new self('This proforma invoice has been cancelled.');
    }

    public static function alreadyPosted(): self
    {
        return new self('This commercial invoice has already been posted.');
    }

    public static function notApproved(): self
    {
        return new self('This commercial invoice must be approved before it can be posted.');
    }

    public static function alreadyCancelled(): self
    {
        return new self('This document has already been cancelled.');
    }

    public static function notPosted(): self
    {
        return new self('Only a posted commercial invoice can be reversed.');
    }

    public static function emptyInvoice(): self
    {
        return new self('Add at least one line with a positive quantity and price.');
    }

    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self("A shipment cannot move from {$from} to {$to}.");
    }

    public static function overShipment(string $sku): self
    {
        return new self("Shipping this quantity would exceed the invoiced quantity for {$sku}.");
    }
}
