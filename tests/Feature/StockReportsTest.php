<?php

use App\Actions\Inventory\IssueStock;
use App\Actions\Inventory\ReceiveStock;
use App\Domain\Reporting\StockLedger;
use App\Domain\Reporting\StockValuation;
use App\Enums\InventoryTransactionType;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: Product, 2: Warehouse} */
function stockReportSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'Widget', 'cost_price' => 0, 'selling_price' => 0]);

    return [$company, $product, $warehouse];
}

it('values on-hand stock at the moving-average cost', function () {
    [$company, $product, $warehouse] = stockReportSetup();
    app(ReceiveStock::class)->handle($warehouse, $product, '100', '40'); // 100 @ 40
    app(ReceiveStock::class)->handle($warehouse, $product, '100', '60'); // now avg 50, qty 200
    app(IssueStock::class)->handle($warehouse, $product, '50', InventoryTransactionType::SalesIssue); // 150 @ 50

    $valuation = StockValuation::for($company->getKey());

    expect($valuation['rows'])->toHaveCount(1)
        ->and($valuation['rows'][0]['quantity'])->toBe('150.0000')
        ->and($valuation['rows'][0]['value'])->toBe('7500.00') // 150 × 50
        ->and($valuation['total'])->toBe('7500.00');
});

it('excludes zero-quantity balances from the valuation', function () {
    [$company, $product, $warehouse] = stockReportSetup();
    app(ReceiveStock::class)->handle($warehouse, $product, '10', '40');
    app(IssueStock::class)->handle($warehouse, $product, '10', InventoryTransactionType::SalesIssue); // back to 0

    $valuation = StockValuation::for($company->getKey());

    expect($valuation['rows'])->toHaveCount(0)
        ->and($valuation['total'])->toBe('0.00');
});

it('lists every movement in the stock ledger with running balance and totals', function () {
    [, $product, $warehouse] = stockReportSetup();
    app(ReceiveStock::class)->handle($warehouse, $product, '100', '40');
    app(IssueStock::class)->handle($warehouse, $product, '30', InventoryTransactionType::SalesIssue);

    $ledger = StockLedger::forProduct($product);

    expect($ledger['rows'])->toHaveCount(2)
        ->and($ledger['rows'][0]['balance_after'])->toBe('100.0000')
        ->and($ledger['rows'][1]['quantity'])->toBe('-30.0000')
        ->and($ledger['rows'][1]['balance_after'])->toBe('70.0000')
        ->and($ledger['in'])->toBe('100.0000')
        ->and($ledger['out'])->toBe('30.0000');
});
