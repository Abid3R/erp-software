<?php

namespace App\Enums;

/**
 * Letter of Credit lifecycle. The PartiallyUtilized / FullyUtilized states are
 * derived from PI allocation vs the LC amount (see LetterOfCredit::utilisationStatus()),
 * while Draft/Applied/Received/Confirmed/Expired/Cancelled are set by the user as
 * the banking process advances.
 */
enum LetterOfCreditStatus: string
{
    case Draft = 'draft';
    case Applied = 'applied';
    case Received = 'received';
    case Confirmed = 'confirmed';
    case PartiallyUtilized = 'partially_utilized';
    case FullyUtilized = 'fully_utilized';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed, self::FullyUtilized => 'success',
            self::Received, self::PartiallyUtilized => 'info',
            self::Applied, self::Draft => 'gray',
            self::Expired, self::Cancelled => 'danger',
        };
    }

    /** The LC is live and can still take allocations. */
    public function isActive(): bool
    {
        return ! in_array($this, [self::Expired, self::Cancelled, self::FullyUtilized], true);
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isExpired(): bool
    {
        return $this === self::Expired;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
