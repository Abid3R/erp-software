<?php

use App\Actions\Inventory\IssueStock;
use App\Actions\Inventory\ReceiveStock;
use App\Enums\InventoryTransactionType;
use App\Exceptions\InsufficientStockException;
use App\Models\Company;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * @return array{0: Warehouse, 1: Product}
 */
function stockSetup(bool $allowNegative = false): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);

    $warehouse = Warehouse::create([
        'company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1',
        'allow_negative_stock' => $allowNegative,
    ]);
    $unit = Unit::create([
        'company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1,
    ]);
    $product = Product::create([
        'company_id' => $company->getKey(), 'unit_id' => $unit->getKey(),
        'sku' => 'P1', 'name' => 'Product', 'cost_price' => 0, 'selling_price' => 0,
    ]);

    return [$warehouse, $product];
}

it('receives stock and computes weighted average cost', function () {
    [$wh, $p] = stockSetup();
    $receive = app(ReceiveStock::class);

    $receive->handle($wh, $p, '100', '50');
    $receive->handle($wh, $p, '50', '60');

    $balance = StockBalance::query()->first();
    expect((string) $balance->quantity_on_hand)->toBe('150.0000')
        ->and((string) $balance->average_cost)->toBe('53.333333')
        ->and(InventoryTransaction::query()->count())->toBe(2);
});

it('issues at the current average and leaves the average unchanged', function () {
    [$wh, $p] = stockSetup();
    $receive = app(ReceiveStock::class);
    $receive->handle($wh, $p, '100', '50');
    $receive->handle($wh, $p, '50', '60');   // avg 53.333333, qty 150

    $txn = app(IssueStock::class)->handle($wh, $p, '30');

    $balance = StockBalance::query()->first();
    expect((string) $balance->quantity_on_hand)->toBe('120.0000')
        ->and((string) $balance->average_cost)->toBe('53.333333')
        ->and((string) $txn->quantity)->toBe('-30.0000')
        ->and((string) $txn->unit_cost)->toBe('53.333333')
        ->and((string) $txn->total_cost)->toBe('1600.00')
        ->and($txn->type)->toBe(InventoryTransactionType::SalesIssue);
});

it('rejects issuing more than available when negative stock is disabled', function () {
    [$wh, $p] = stockSetup(allowNegative: false);
    app(ReceiveStock::class)->handle($wh, $p, '5', '10');

    expect(fn () => app(IssueStock::class)->handle($wh, $p, '6'))
        ->toThrow(InsufficientStockException::class);

    // The failed issue rolled back — stock is untouched.
    expect((string) StockBalance::query()->first()->quantity_on_hand)->toBe('5.0000');
});

it('permits negative stock when the warehouse policy allows it', function () {
    [$wh, $p] = stockSetup(allowNegative: true);

    app(IssueStock::class)->handle($wh, $p, '5'); // no prior stock

    expect((string) StockBalance::query()->first()->quantity_on_hand)->toBe('-5.0000');
});
