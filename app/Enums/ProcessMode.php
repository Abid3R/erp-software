<?php

namespace App\Enums;

/**
 * How a process run is executed. In-house work is done on the mill's own machines
 * (conversion cost accrues internally); sub-contract (job-work) is done by an
 * outside supplier for a service charge (a real payable to that supplier).
 */
enum ProcessMode: string
{
    case InHouse = 'in_house';
    case Subcontract = 'subcontract';

    public function label(): string
    {
        return match ($this) {
            self::InHouse => 'In-house',
            self::Subcontract => 'Sub-contract',
        };
    }

    public function color(): string
    {
        return $this === self::Subcontract ? 'warning' : 'gray';
    }

    public function isSubcontract(): bool
    {
        return $this === self::Subcontract;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
