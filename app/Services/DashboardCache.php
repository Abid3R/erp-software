<?php

namespace App\Services;

use App\Domain\Reporting\DashboardMetrics;
use App\Domain\Reporting\DashboardTrends;
use Illuminate\Support\Facades\Cache;

/**
 * Short-lived caching layer over the dashboard read models. The underlying
 * services (DashboardMetrics, DashboardTrends) are pure — they still compute
 * from the ledger each time they run — so tests bypass this wrapper. Only the
 * Filament widgets go through here to avoid re-running the same aggregates
 * for every visitor of every panel page.
 *
 * TTL is deliberately short so figures stay live-feeling (60 s). Cache keys are
 * per-company so multi-tenant isolation is preserved.
 */
class DashboardCache
{
    private const TTL_SECONDS = 60;

    /**
     * @return array<string, \Brick\Math\BigDecimal>
     */
    public function metrics(int $companyId): array
    {
        return Cache::remember(
            "dashboard.metrics.company:{$companyId}",
            self::TTL_SECONDS,
            fn (): array => app(DashboardMetrics::class)->for($companyId),
        );
    }

    /**
     * @return array{labels: list<string>, revenue: list<string>, expenses: list<string>, sales: list<string>, purchases: list<string>}
     */
    public function monthlyTrends(int $companyId, string $from, string $to): array
    {
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
