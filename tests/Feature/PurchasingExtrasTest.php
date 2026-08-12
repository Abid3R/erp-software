<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Purchasing\RecordPurchaseReturn;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Enums\PurchaseReturnStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PurchaseReturnException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('returns the active buying price for a supplier and product', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);
    $supplier = Supplier::create(['name' => 'Vendor', 'code' => 'V1']);
    SupplierPrice::create(['supplier_id' => $supplier->getKey(), 'product_id' => $product->getKey(), 'unit_price' => 315]);

    expect(SupplierPrice::priceFor((int) $supplier->getKey(), (int) $product->getKey()))->toBe('315.0000')
        ->and(SupplierPrice::priceFor((int) $supplier->getKey(), 999999))->toBeNull();
});

/** @return array{0: PurchaseReturn, 1: Product} */
function purchaseReturnSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    Account::create(['company_id' => $company->getKey(), 'code' => '1200', 'name' => 'Inventory', 'type' => AccountType::Asset]);
    Account::create(['company_id' => $company->getKey(), 'code' => '2000', 'name' => 'Accounts Payable', 'type' => AccountType::Liability]);

    $supplier = Supplier::create(['name' => 'Vendor', 'code' => 'V1']);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);
    app(ReceiveStock::class)->handle($warehouse, $product, '50', '300'); // 50 @ 300

    $pr = PurchaseReturn::create([
        'number' => 'PR-1', 'supplier_id' => $supplier->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'return_date' => '2026-06-02', 'status' => 'draft',
    ]);
    $pr->lines()->create(['product_id' => $product->getKey(), 'quantity' => 10]);

    return [$pr, $product];
}

it('posts a purchase return, removing stock at cost and reversing the liability', function () {
    [$pr, $product] = purchaseReturnSetup();

    $result = app(RecordPurchaseReturn::class)->handle($pr);

    expect($result->status)->toBe(PurchaseReturnStatus::Posted)
        ->and((string) StockBalance::query()->where('product_id', $product->getKey())->first()->quantity_on_hand)->toBe('40.0000'); // 50 − 10
});

it('will not post a purchase return twice', function () {
    [$pr] = purchaseReturnSetup();
    app(RecordPurchaseReturn::class)->handle($pr);

    expect(fn () => app(RecordPurchaseReturn::class)->handle($pr->fresh()))
        ->toThrow(PurchaseReturnException::class);
});

it('aborts a purchase return when stock is short', function () {
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    Account::create(['company_id' => $company->getKey(), 'code' => '1200', 'name' => 'Inventory', 'type' => AccountType::Asset]);
    Account::create(['company_id' => $company->getKey(), 'code' => '2000', 'name' => 'AP', 'type' => AccountType::Liability]);
    $supplier = Supplier::create(['name' => 'V', 'code' => 'V1']);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);
    app(ReceiveStock::class)->handle($warehouse, $product, '5', '300'); // only 5 in stock

    $pr = PurchaseReturn::create(['number' => 'PR-1', 'supplier_id' => $supplier->getKey(), 'warehouse_id' => $warehouse->getKey(), 'return_date' => '2026-06-02', 'status' => 'draft']);
    $pr->lines()->create(['product_id' => $product->getKey(), 'quantity' => 20]); // more than stock

    expect(fn () => app(RecordPurchaseReturn::class)->handle($pr))->toThrow(InsufficientStockException::class);
    expect($pr->fresh()->status)->toBe(PurchaseReturnStatus::Draft);
});
