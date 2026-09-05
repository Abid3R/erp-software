<?php

namespace App\Enums;

/**
 * Export Shipment lifecycle, following a consignment from planning to completion.
 * Operational only — no ledger/stock impact of its own.
 */
enum ExportShipmentStatus: string
{
    case Draft = 'draft';
    case ReadyForShipment = 'ready_for_shipment';
    case Stuffing = 'stuffing';
    case Shipped = 'shipped';
    case DocumentsSubmitted = 'documents_submitted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed, self::Shipped => 'success',
            self::DocumentsSubmitted, self::ReadyForShipment => 'info',
            self::Stuffing => 'warning',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    /** Goods have physically left (or beyond). */
    public function hasShipped(): bool
    {
        return in_array($this, [self::Shipped, self::DocumentsSubmitted, self::Completed], true);
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    /** Ordered forward-only progression (Cancelled is reachable from any open state). */
    public function canAdvanceTo(self $target): bool
    {
        if ($target === self::Cancelled) {
            return $this !== self::Completed && $this !== self::Cancelled;
        }

        $order = [
            self::Draft->value => 0,
            self::ReadyForShipment->value => 1,
            self::Stuffing->value => 2,
            self::Shipped->value => 3,
            self::DocumentsSubmitted->value => 4,
            self::Completed->value => 5,
        ];

        return isset($order[$this->value], $order[$target->value])
            && $order[$target->value] > $order[$this->value];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
