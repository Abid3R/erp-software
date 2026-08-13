<?php

use App\Domain\Reporting\PurchaseRegister;
use App\Domain\Reporting\SalesRegister;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('lists sales orders with ordered and delivered value in the period', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $customer = Customer::create(['company_id' => $company->getKey(), 'name' => 'Buyer', 'code' => 'B1']);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);

    $so = SalesOrder::create([
        'so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'order_date' => '2026-06-01', 'status' => 'partially_delivered',
    ]);
    $so->lines()->create(['product_id' => $product->getKey(), 'quantity_ordered' => 10, 'quantity_delivered' => 4, 'unit_price' => 100]);

    // A different-period order should be excluded.
    SalesOrder::create([
        'so_number' => 'SO-2', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'order_date' => '2025-01-01', 'status' => 'draft',
    ]);

    $register = SalesRegister::for($company->getKey(), '2026-01-01', '2026-12-31');

    expect($register['rows'])->toHaveCount(1)
        ->and($register['rows'][0]['ordered'])->toBe('1000.00')   // 10 × 100
        ->and($register['rows'][0]['delivered'])->toBe('400.00')  // 4 × 100
        ->and($register['ordered'])->toBe('1000.00')
        ->and($register['delivered'])->toBe('400.00');
});

it('lists supplier invoices with net value in the period', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $account = Account::create(['company_id' => $company->getKey(), 'code' => '2100', 'name' => 'GRNI', 'type' => AccountType::Liability]);

    $inv = SupplierInvoice::create([
        'number' => 'SINV-1', 'supplier_id' => $supplier->getKey(), 'invoice_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $inv->lines()->create(['account_id' => $account->getKey(), 'amount' => 1200]);
    $inv->lines()->create(['account_id' => $account->getKey(), 'amount' => 800]);

    $register = PurchaseRegister::for($company->getKey(), '2026-01-01', '2026-12-31');

    expect($register['rows'])->toHaveCount(1)
        ->and($register['rows'][0]['net'])->toBe('2000.00') // 1200 + 800
        ->and($register['net'])->toBe('2000.00');
});
