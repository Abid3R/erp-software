<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Goods-receipt (GRN) document violations (spec: Purchasing → GRN).
 */
class GoodsReceiptException extends RuntimeException
{
    public string $errorCode = 'INVALID_GOODS_RECEIPT';

    public static function alreadyPosted(): self
    {
        return new self('This goods receipt has already been posted.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line with an accepted quantity.');
    }

    public static function overReceipt(string $sku): self
    {
        return new self("Accepting this quantity would exceed the ordered quantity for {$sku}.");
    }
}
