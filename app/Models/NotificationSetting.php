<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-company notification preferences (one row per company). Read through the
 * static helpers so callers get the safe "all on" default when no row exists.
 *
 * @property bool $leave_approvals_enabled
 * @property bool $overdue_invoices_enabled
 * @property bool $low_stock_enabled
 * @property int $overdue_days
 */
class NotificationSetting extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'leave_approvals_enabled', 'overdue_invoices_enabled',
        'low_stock_enabled', 'overdue_days',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'leave_approvals_enabled' => 'boolean',
            'overdue_invoices_enabled' => 'boolean',
            'low_stock_enabled' => 'boolean',
            'overdue_days' => 'integer',
        ];
    }

    /**
     * Whether a given alert is enabled for a company. Defaults to true when no
     * settings row exists, so alerts work out of the box. The company scope is
     * bypassed so this is safe to call from model hooks and scheduled jobs where
     * no company context may be set.
     */
    public static function enabled(int $companyId, string $key): bool
    {
        $row = static::query()->withoutGlobalScopes()->where('company_id', $companyId)->first();

        return (bool) ($row?->getAttribute($key.'_enabled') ?? true);
    }

    /** The company's overdue-days threshold (default 30). */
    public static function overdueDays(int $companyId): int
    {
        $row = static::query()->withoutGlobalScopes()->where('company_id', $companyId)->first();

        return (int) ($row?->overdue_days ?? 30);
    }
}
