<?php

namespace App\Services;

use App\Domain\Reporting\DashboardMetrics;
use App\Domain\Reporting\DashboardTrends;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived caching layer over the dashboard read models. The underlying
 * services (DashboardMetrics, DashboardTrends) are pure — they still compute
 * from the ledger each time they run — so tests bypass this wrapper. Only the
 * Filament widgets go through here to avoid re-running the same aggregates
 * for every visitor of every panel page.
 *
 * IMPORTANT — cache primitives only, not value objects. Serialising BigDecimal
 * via Laravel's file/redis cache is fragile (an incomplete-class error surfaces
 * as `__PHP_Incomplete_Class` when the autoloader races the unserializer). So
 * we store decimal strings and rebuild BigDecimal after retrieval — same public
 * contract, zero serialization risk.
 *
 * TTL is deliberately short so figures stay live-feeling (60 s). Cache keys are
 * per-company so multi-tenant isolation is preserved.
 */
class DashboardCache
{
    private const TTL_SECONDS = 60;

    /**
     * @return array<string, BigDecimal>
     */
    public function metrics(int $companyId): array
    {
        $strings = Cache::remember(
            "dashboard.metrics.company:{$companyId}",
            self::TTL_SECONDS,
            function () use ($companyId): array {
                $m = app(DashboardMetrics::class)->for($companyId);

                // Persist as strings so the cache blob has no class references.
                return array_map(fn (BigDecimal $v): string => (string) $v, $m);
            },
        );

        return array_map(fn (string $v): BigDecimal => BigDecimal::of($v), $strings);
    }

    /**
     * @return array{labels: list<string>, revenue: list<string>, expenses: list<string>, sales: list<string>, purchases: list<string>}
     */
    public function monthlyTrends(int $companyId, string $from, string $to): array
    {
        // Trends already return string arrays, so caching is safe as-is.
        $key = "dashboard.trends.company:{$companyId}.{$from}.{$to}";

        return Cache::remember(
            $key,
            self::TTL_SECONDS,
            fn (): array => DashboardTrends::monthly($companyId, $from, $to),
        );
    }

    /** Manually invalidate the cached figures for a company (e.g. after posting). */
    public function forget(int $companyId): void
    {
        Cache::forget("dashboard.metrics.company:{$companyId}");
    }
}
