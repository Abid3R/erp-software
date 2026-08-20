<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Delivery Order document violations (spec: DO module).
 */
class DeliveryOrderException extends RuntimeException
{
    public string $errorCode = 'INVALID_DELIVERY_ORDER';

    public static function alreadyPosted(): self
    {
        return new self('This delivery order has already been posted.');
    }

    public static function notPosted(): self
    {
        return new self('Only a posted delivery order can be cancelled.');
    }

    public static function alreadyCancelled(): self
    {
        return new self('This delivery order has already been cancelled.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line with a positive quantity.');
    }

    public static function overDelivery(string $sku): self
    {
        return new self("Delivering this quantity would exceed the sales order for {$sku}.");
    }
}
