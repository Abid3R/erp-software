<?php

namespace App\Enums;

/**
 * Lab dip (colour development) lifecycle. A lab dip carries no inventory or
 * accounting effect — it is a colour-approval workflow whose end state
 * (customer/internal approved) makes it selectable on a dyeing order.
 */
enum LabDipStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InLab = 'in_lab';
    case InternalApproved = 'internal_approved';
    case SentToCustomer = 'sent_to_customer';
    case CustomerApproved = 'customer_approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::InLab => 'In Lab',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CustomerApproved => 'success',
            self::InternalApproved => 'info',
            self::Submitted, self::InLab, self::SentToCustomer => 'warning',
            self::Rejected => 'danger',
            self::Draft => 'gray',
        };
    }

    /** Approved enough to be used on a dyeing order. */
    public function isApproved(): bool
    {
        return in_array($this, [self::InternalApproved, self::CustomerApproved], true);
    }

    /** No further transitions expected. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::CustomerApproved, self::Rejected], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
