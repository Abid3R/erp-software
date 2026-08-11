<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Sales\FulfillSalesOrder;
use App\Domain\Accounting\PartyLedger;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SalesOrderException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: SalesOrder, 1: int, 2: Product, 3: Warehouse, 4: Customer} */
function soSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    foreach ([['1100', 'AR', AccountType::Asset], ['1200', 'Inventory', AccountType::Asset], ['4000', 'Sales', AccountType::Revenue], ['5000', 'COGS', AccountType::Expense]] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }

    $customer = Customer::create(['company_id' => $company->getKey(), 'name' => 'Buyer', 'code' => 'B1']);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);
    app(ReceiveStock::class)->handle($warehouse, $product, '50', '300'); // 50 in stock @ 300

    $so = SalesOrder::create([
        'so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'order_date' => '2026-06-01', 'status' => 'confirmed',
    ]);
    $line = $so->lines()->create(['product_id' => $product->getKey(), 'quantity_ordered' => 30, 'unit_price' => 420]);

    return [$so, $line->getKey(), $product, $warehouse, $customer];
}

function soStock(Product $p): string
{
    return (string) StockBalance::query()->where('product_id', $p->getKey())->firstOrFail()->quantity_on_hand;
}

it('totals a sales order from its lines', function () {
    [$so] = soSetup();

    expect((string) $so->load('lines')->total()->toScale(2))->toBe('12600.00'); // 30 × 420
});

it('delivers partially then fully, posting stock, revenue and receivable', function () {
    [$so, $lineId, $product, , $customer] = soSetup();

    $result = app(FulfillSalesOrder::class)->handle($so, [$lineId => '10'], '2026-06-02');
    expect($result->status)->toBe(SalesOrderStatus::PartiallyDelivered)
        ->and(soStock($product))->toBe('40.0000')
        ->and((string) PartyLedger::receivable($customer)->toScale(2))->toBe('4200.00'); // 10 × 420

    $result = app(FulfillSalesOrder::class)->handle($so->refresh(), [$lineId => '20'], '2026-06-03');
    expect($result->status)->toBe(SalesOrderStatus::Delivered)
        ->and(soStock($product))->toBe('20.0000')
        ->and((string) PartyLedger::receivable($customer)->toScale(2))->toBe('12600.00'); // 30 × 420
});

it('rejects delivering more than the outstanding quantity', function () {
    [$so, $lineId] = soSetup();

    expect(fn () => app(FulfillSalesOrder::class)->handle($so, [$lineId => '40'], '2026-06-02'))
        ->toThrow(SalesOrderException::class);
});

it('aborts delivery when stock is short, leaving the order deliverable', function () {
    [$so] = soSetup(); // 50 in stock

    $big = SalesOrder::create([
        'so_number' => 'SO-2', 'customer_id' => $so->customer_id, 'warehouse_id' => $so->warehouse_id,
        'order_date' => '2026-06-01', 'status' => 'confirmed',
    ]);
    $line = $big->lines()->create(['product_id' => $so->lines()->first()->product_id, 'quantity_ordered' => 100, 'unit_price' => 420]);

    expect(fn () => app(FulfillSalesOrder::class)->handle($big, [$line->getKey() => '60'], '2026-06-02'))
        ->toThrow(InsufficientStockException::class);
    expect($big->fresh()->status)->toBe(SalesOrderStatus::Confirmed);
});

it('cannot deliver against a draft order', function () {
    [$so, $lineId] = soSetup();
    $so->update(['status' => 'draft']);

    expect(fn () => app(FulfillSalesOrder::class)->handle($so->refresh(), [$lineId => '10'], '2026-06-02'))
        ->toThrow(SalesOrderException::class);
});
