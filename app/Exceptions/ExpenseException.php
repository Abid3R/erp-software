<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Expense-voucher violations (spec: Accounts → Expenses).
 */
class ExpenseException extends RuntimeException
{
    public string $errorCode = 'INVALID_EXPENSE';

    public static function alreadyPosted(): self
    {
        return new self('This expense has already been posted.');
    }

    public static function empty(): self
    {
        return new self('Add at least one line with an amount.');
    }

    public static function supplierRequiredForCredit(): self
    {
        return new self('Select a supplier for an on-credit expense.');
    }
}
