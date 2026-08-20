<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Resolves a report date-range preset (Today / This Month / This Year / Custom)
 * into concrete from/to dates. Presentation helper only — no data access — so
 * every report page can offer the same quick ranges consistently.
 */
final class ReportRange
{
    public const TODAY = 'today';

    public const THIS_MONTH = 'this_month';

    public const THIS_YEAR = 'this_year';

    public const CUSTOM = 'custom';

    /** @return array<string, string> Preset key => label, for a Filament select. */
    public static function options(): array
    {
        return [
            self::TODAY => 'Today',
            self::THIS_MONTH => 'This Month',
            self::THIS_YEAR => 'This Year',
            self::CUSTOM => 'Custom',
        ];
    }

    /**
     * Resolve a preset (plus optional custom dates) to concrete from/to strings.
     * For Custom, the caller's own from/to are passed through unchanged (nullable).
     *
     * @return array{from: string|null, to: string|null}
     */
    public static function resolve(?string $preset, ?string $customFrom = null, ?string $customTo = null): array
    {
        $now = Carbon::now();

        return match ($preset) {
            self::TODAY => [
                'from' => $now->copy()->startOfDay()->toDateString(),
                'to' => $now->copy()->endOfDay()->toDateString(),
            ],
            self::THIS_MONTH => [
                'from' => $now->copy()->startOfMonth()->toDateString(),
                'to' => $now->copy()->endOfMonth()->toDateString(),
            ],
            self::THIS_YEAR => [
                'from' => $now->copy()->startOfYear()->toDateString(),
                'to' => $now->copy()->endOfYear()->toDateString(),
            ],
            default => [
                'from' => $customFrom !== '' ? $customFrom : null,
                'to' => $customTo !== '' ? $customTo : null,
            ],
        };
    }
}
