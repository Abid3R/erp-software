<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendLowStockAlerts;
use App\Actions\Notifications\SendOverdueInvoiceAlerts;
use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Fans out the operational alerts (low stock, overdue receivables) across active
 * companies. Each generator respects that company's Notification Settings. Runs
 * daily via the scheduler and can be invoked manually or per-company.
 */
class SendErpNotifications extends Command
{
    protected $signature = 'erp:notifications:send {--company= : Limit to a single company id}';

    protected $description = 'Send operational alerts (low stock, overdue receivables) to the relevant roles';

    public function handle(SendLowStockAlerts $lowStock, SendOverdueInvoiceAlerts $overdue): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey((int) $id))
            ->where('is_active', true)
            ->pluck('id');

        $lowTotal = 0;
        $overdueTotal = 0;

        foreach ($companies as $companyId) {
            $lowTotal += $lowStock->forCompany((int) $companyId);
            $overdueTotal += $overdue->forCompany((int) $companyId);
        }

        $this->info("Notifications sent. Low-stock products flagged: {$lowTotal}; overdue customers flagged: {$overdueTotal}.");

        return self::SUCCESS;
    }
}
