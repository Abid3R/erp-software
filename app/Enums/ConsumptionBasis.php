<?php

namespace App\Enums;

/**
 * How a recipe line's rate is dosed — the standard Bangladesh textile conventions.
 */
enum ConsumptionBasis: string
{
    /** Material units per 1 unit of output (e.g. 1.0 kg yarn per kg grey fabric). */
    case PerUnit = 'per_unit';

    /** Percent on weight of fabric — "% owf" — used for dyes. */
    case PercentOwf = 'percent_owf';

    /** Grams per litre of dye bath — used for salt, soda ash, auxiliaries. */
    case GramsPerLitre = 'g_per_litre';

    public function label(): string
    {
        return match ($this) {
            self::PerUnit => 'Per unit of output',
            self::PercentOwf => '% on weight of fabric (owf)',
            self::GramsPerLitre => 'Grams per litre (g/L)',
        };
    }

    /** A short unit hint for the rate input. */
    public function rateHint(): string
    {
        return match ($this) {
            self::PerUnit => 'material units per 1 output unit',
            self::PercentOwf => '% of fabric weight',
            self::GramsPerLitre => 'grams per litre of bath',
        };
    }

    /** g/L needs the dye-bath volume (fabric weight × liquor ratio). */
    public function needsLiquorRatio(): bool
    {
        return $this === self::GramsPerLitre;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
