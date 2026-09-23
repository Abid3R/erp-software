<?php

namespace App\Enums;

/**
 * Dyeing method chosen on the approved lab dip (per the approved recipe — not derived
 * from composition). It expands the dyeing phase of a production plan into the correct
 * ordered sub-steps, each a normal process order.
 *
 *  - One-Part : Pretreatment → Dyeing → Wash-off (→ Finishing)
 *  - Two-Part : Pretreatment → Part 1 Dyeing → Intermediate Wash → Part 2 Dyeing → Wash-off (→ Finishing)
 */
enum DyeingType: string
{
    case OnePart = 'one_part';
    case TwoPart = 'two_part';

    public function label(): string
    {
        return $this === self::OnePart ? 'One-Part' : 'Two-Part';
    }

    /** Ordered process-type codes for the dyeing phase (Finishing is a separate phase). */
    public function stepCodes(): array
    {
        return match ($this) {
            self::OnePart => ['PRETREAT', 'DYE', 'WASHOFF'],
            self::TwoPart => ['PRETREAT', 'DYE1', 'IWASH', 'DYE2', 'WASHOFF'],
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
