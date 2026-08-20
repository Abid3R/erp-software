<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Session-backed date range used by the dashboard widgets. Kept small on
 * purpose: the custom Dashboard page writes to it, every widget reads from it.
 * Defaults to the trailing twelve months when nothing has been set.
 */
final class DashboardFilter
{
    private const SESSION_KEY = 'dashboard.filter';

    /** @return array{from: string, to: string} */
    public static function range(): array
    {
        /** @var array{from?: string|null, to?: string|null} $stored */
        $stored = session(self::SESSION_KEY, []);

        $to = self::parseOrNull($stored['to'] ?? null) ?? Carbon::now()->endOfMonth();
        $from = self::parseOrNull($stored['from'] ?? null) ?? $to->copy()->subMonths(11)->startOfMonth();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfMonth(), $from->copy()->endOfMonth()];
        }

        return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    public static function set(?string $from, ?string $to): void
    {
        session([self::SESSION_KEY => ['from' => $from, 'to' => $to]]);
    }

    public static function reset(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private static function parseOrNull(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
