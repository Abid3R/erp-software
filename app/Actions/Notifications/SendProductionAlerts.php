<?php

namespace App\Actions\Notifications;

use App\Models\NotificationSetting;
use App\Models\ProcessOrder;
use App\Support\Notifier;

/**
 * Alert production, inventory and management when process orders are waiting on a
 * business decision — currently, orders sitting at the QC gate. Respects the
 * company's production-alerts toggle. Returns the number of orders flagged.
 */
class SendProductionAlerts
{
    /** @var list<string> */
    private const RECIPIENT_ROLES = ['manufacturing', 'inventory', 'manager', 'super_admin'];

    public function forCompany(int $companyId): int
    {
        if (! NotificationSetting::enabled($companyId, 'production_alerts')) {
            return 0;
        }

        $awaitingQc = ProcessOrder::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', 'qc')
            ->count();

        if ($awaitingQc === 0) {
            return 0;
        }

        Notifier::toCompanyRoles(
            $companyId,
            self::RECIPIENT_ROLES,
            'Production awaiting QC',
            $awaitingQc.' process order'.($awaitingQc === 1 ? ' is' : 's are').' awaiting quality inspection.',
            url('/admin/process-orders'),
        );

        return $awaitingQc;
    }
}
