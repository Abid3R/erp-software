<?php

namespace App\Enums;

/**
 * Direction of a payment: money received from a customer, or money paid to a
 * supplier (spec #23).
 */
enum PaymentDirection: string
{
    case Receipt = 'receipt';   // from a customer — settles a receivable
    case Payment = 'payment';   // to a supplier — settles a payable

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
