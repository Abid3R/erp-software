<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Approval-workflow violations (spec #25): no matching flow, acting out of turn,
 * approving without the required role, or acting on a closed request. Stable code
 * (spec #67).
 */
class ApprovalException extends RuntimeException
{
    public string $errorCode = 'INVALID_APPROVAL';

    public static function noFlow(string $subject): self
    {
        return new self("No active approval flow matches {$subject} for this amount.");
    }

    public static function notPending(): self
    {
        return new self('This approval request is not open for action.');
    }

    public static function notAuthorized(string $role): self
    {
        return new self("Approving this step requires the '{$role}' role.");
    }
}
