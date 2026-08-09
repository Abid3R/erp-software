<?php

namespace App\Console\Commands;

use App\Actions\Inventory\IssueStock;
use App\Enums\InventoryTransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use Illuminate\Console\Command;

/**
 * Test-only helper invoked as a separate OS process by the concurrency suite to
 * create genuine parallel load on IssueStock (spec #17, #47). Exit 0 = issued,
 * 1 = insufficient stock (the expected, correctly-rejected outcome).
 */
class IssueStockTestCommand extends Command
{
    protected $signature = 'erp:issue-test {warehouse} {product} {qty}';

    protected $description = 'Concurrency-test helper: issue stock and report success/insufficient.';

    public function handle(IssueStock $issueStock, CompanyContext $context): int
    {
        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($this->argument('warehouse'));
        $product = Product::withoutGlobalScopes()->findOrFail($this->argument('product'));

        $context->set((int) $warehouse->getAttribute('company_id'));

        try {
            $issueStock->handle($warehouse, $product, $this->argument('qty'), InventoryTransactionType::SalesIssue);
            $this->line('OK');

            return self::SUCCESS;
        } catch (InsufficientStockException) {
            $this->line('INSUFFICIENT');

            return self::FAILURE;
        }
    }
}
