<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Sales\PostSalesReturn;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Enums\SalesReturnStatus;
use App\Exceptions\SalesReturnException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: SalesReturn, 1: Product, 2: Warehouse} */
function salesReturnSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    foreach ([['1100', 'AR', AccountType::Asset], ['1200', 'Inventory', AccountType::Asset], ['4100', 'Sales Returns', AccountType::Revenue], ['5000', 'COGS', AccountType::Expense]] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }

    $customer = Customer::create(['company_id' => $company->getKey(), 'name' => 'Buyer', 'code' => 'B1']);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 300, 'selling_price' => 420]);
    app(ReceiveStock::class)->handle($warehouse, $product, '50', '300'); // establishes 300 average cost

    $return = SalesReturn::create([
        'number' => 'SR-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'return_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $return->lines()->create(['product_id' => $product->getKey(), 'quantity' => 10, 'unit_price' => 420]);

    return [$return, $product, $warehouse];
}

it('posts a sales return: stock back at cost and status posted', function () {
    [$return, $product] = salesReturnSetup();

    $posted = app(PostSalesReturn::class)->handle($return);

    expect($posted->status)->toBe(SalesReturnStatus::Posted)
        ->and((string) StockBalance::query()->where('product_id', $product->getKey())->firstOrFail()->quantity_on_hand)
        ->toBe('60.0000'); // 50 + 10 back in
});

it('refuses to post an already posted return', function () {
    [$return] = salesReturnSetup();
    app(PostSalesReturn::class)->handle($return);

    expect(fn () => app(PostSalesReturn::class)->handle($return->refresh()))
        ->toThrow(SalesReturnException::class);
});
