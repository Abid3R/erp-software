<?php

namespace App\Actions\Notifications;

use App\Domain\Reporting\PartyAging;
use App\Models\NotificationSetting;
use App\Support\Notifier;
use Brick\Math\BigDecimal;

/**
 * Raise an in-app alert when customers have receivables aged past the company's
 * overdue-days threshold, to accounting and managers. Reuses {@see PartyAging}
 * (which reconciles with the GL control account). Respects the company's
 * overdue-invoice toggle. Returns the number of customers with overdue balances.
 */
class SendOverdueInvoiceAlerts
{
    /** @var list<string> */
    private const RECIPIENT_ROLES = ['accounting', 'accountant', 'manager', 'super_admin'];

    public function forCompany(int $companyId): int
    {
        if (! NotificationSetting::enabled($companyId, 'overdue_invoices')) {
            return 0;
        }

        $days = NotificationSetting::overdueDays($companyId);

        // Which aging buckets count as "overdue" given the threshold.
        $overdueBuckets = match (true) {
            $days <= 30 => ['d30', 'd60', 'd90plus'],
            $days <= 60 => ['d60', 'd90plus'],
            default => ['d90plus'],
        };

        $aging = PartyAging::receivables($companyId);

        $overdueParties = 0;
        $overdueTotal = BigDecimal::zero();

        foreach ($aging['rows'] as $row) {
            $partyOverdue = BigDecimal::zero();
            foreach ($overdueBuckets as $bucket) {
                $partyOverdue = $partyOverdue->plus($row[$bucket]);
            }
            if ($partyOverdue->isPositive()) {
                $overdueParties++;
                $overdueTotal = $overdueTotal->plus($partyOverdue);
            }
        }

        if ($overdueParties === 0) {
            return 0;
        }

        $symbol = config('erp.currency.symbol');
        $amount = $symbol.number_format((float) (string) $overdueTotal, (int) config('erp.currency.precision', 2));

        Notifier::toCompanyRoles(
            $companyId,
            self::RECIPIENT_ROLES,
            'Overdue receivables',
            $overdueParties.' customer'.($overdueParties === 1 ? '' : 's').' owe '.$amount.' overdue by '.$days.'+ days.',
            url('/admin/receivables-aging'),
        );

        return $overdueParties;
    }
}
