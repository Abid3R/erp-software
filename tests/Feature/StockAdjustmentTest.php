<?php

use App\Actions\Inventory\PostStockAdjustment;
use App\Actions\Inventory\ReceiveStock;
use App\Domain\Reporting\AccountBalances;
use App\Domain\Accounting\TrialBalance;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Enums\StockAdjustmentStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockAdjustmentException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: Product, 2: Warehouse, 3: array<string, Account>} */
function adjustmentSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $make = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $accounts = [
        'inventory' => $make('1200', 'Inventory', AccountType::Asset),
        'inventory_adjustment' => $make('5100', 'Inventory Adjustment', AccountType::Expense),
    ];

    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 100, 'selling_price' => 150]);
    app(ReceiveStock::class)->handle($warehouse, $product, '50', '100'); // 50 @ 100

    return [$company, $product, $warehouse, $accounts];
}

function adjStock(Product $p): string
{
    return (string) StockBalance::query()->where('product_id', $p->getKey())->firstOrFail()->quantity_on_hand;
}

it('posts an "in" adjustment: stock up and value to the adjustment account', function () {
    [$company, $product, $warehouse, $accounts] = adjustmentSetup();
    $adj = StockAdjustment::create([
        'number' => 'ADJ-1', 'warehouse_id' => $warehouse->getKey(), 'adjustment_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $adj->lines()->create(['product_id' => $product->getKey(), 'direction' => 'in', 'quantity' => 10, 'unit_cost' => 100]);

    $posted = app(PostStockAdjustment::class)->handle($adj);

    // Stock goes to 60; the adjustment journal books the +1000 (10 × 100) value only —
    // the opening ReceiveStock in the setup moves the ledger, not the GL.
    expect($posted->status)->toBe(StockAdjustmentStatus::Posted)
        ->and(adjStock($product))->toBe('60.0000')
        ->and((string) AccountBalances::netForAccount($accounts['inventory']))->toBe('1000.00')
        ->and((string) AccountBalances::netForAccount($accounts['inventory_adjustment']))->toBe('-1000.00')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('posts an "out" adjustment at average cost, reducing stock', function () {
    [$company, $product, $warehouse] = adjustmentSetup();
    $adj = StockAdjustment::create([
        'number' => 'ADJ-2', 'warehouse_id' => $warehouse->getKey(), 'adjustment_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $adj->lines()->create(['product_id' => $product->getKey(), 'direction' => 'out', 'quantity' => 5]);

    app(PostStockAdjustment::class)->handle($adj);

    expect(adjStock($product))->toBe('45.0000')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('aborts an adjustment when an "out" line exceeds stock', function () {
    [, $product, $warehouse] = adjustmentSetup();
    $adj = StockAdjustment::create([
        'number' => 'ADJ-3', 'warehouse_id' => $warehouse->getKey(), 'adjustment_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $adj->lines()->create(['product_id' => $product->getKey(), 'direction' => 'out', 'quantity' => 999]);

    expect(fn () => app(PostStockAdjustment::class)->handle($adj))->toThrow(InsufficientStockException::class);
    expect($adj->fresh()->status)->toBe(StockAdjustmentStatus::Draft)
        ->and(adjStock($product))->toBe('50.0000'); // unchanged
});

it('refuses to post an already posted adjustment', function () {
    [, $product, $warehouse] = adjustmentSetup();
    $adj = StockAdjustment::create([
        'number' => 'ADJ-4', 'warehouse_id' => $warehouse->getKey(), 'adjustment_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $adj->lines()->create(['product_id' => $product->getKey(), 'direction' => 'in', 'quantity' => 1, 'unit_cost' => 100]);
    app(PostStockAdjustment::class)->handle($adj);

    expect(fn () => app(PostStockAdjustment::class)->handle($adj->refresh()))->toThrow(StockAdjustmentException::class);
});
