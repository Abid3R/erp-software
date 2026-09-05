<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendLowStockAlerts;
use App\Actions\Notifications\SendOverdueInvoiceAlerts;
use App\Actions\Notifications\SendProductionAlerts;
use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Fans out the operational alerts (low stock, overdue receivables, production
 * awaiting QC) across active companies. Each generator respects that company's
 * Notification Settings. Runs daily via the scheduler and can be invoked manually
 * or per-company.
 */
class SendErpNotifications extends Command
{
    protected $signature = 'erp:notifications:send {--company= : Limit to a single company id}';

    protected $description = 'Send operational alerts (low stock, overdue receivables, production) to the relevant roles';

    public function handle(SendLowStockAlerts $lowStock, SendOverdueInvoiceAlerts $overdue, SendProductionAlerts $production): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey((int) $id))
            ->where('is_active', true)
            ->pluck('id');

        $lowTotal = 0;
        $overdueTotal = 0;
        $productionTotal = 0;

        foreach ($companies as $companyId) {
            $lowTotal += $lowStock->forCompany((int) $companyId);
            $overdueTotal += $overdue->forCompany((int) $companyId);
            $productionTotal += $production->forCompany((int) $companyId);
        }

        $this->info("Notifications sent. Low-stock: {$lowTotal}; overdue customers: {$overdueTotal}; production awaiting QC: {$productionTotal}.");

        return self::SUCCESS;
    }
}
