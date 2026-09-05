<?php

use App\Actions\Export\CreateProformaFromSalesOrder;
use App\Enums\ProformaInvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('builds a draft proforma invoice from a sales order without re-entry', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $unit = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);
    $p1 = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'A', 'name' => 'Item A', 'cost_price' => 0, 'selling_price' => 0]);
    $p2 = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'B', 'name' => 'Item B', 'cost_price' => 0, 'selling_price' => 0]);

    $so = SalesOrder::create([
        'company_id' => $company->getKey(), 'so_number' => 'SO-1', 'customer_id' => $customer->getKey(),
        'warehouse_id' => $wh->getKey(), 'order_date' => now(), 'status' => 'confirmed',
    ]);
    $so->lines()->create(['product_id' => $p1->getKey(), 'quantity_ordered' => 5, 'unit_price' => 120]);
    $so->lines()->create(['product_id' => $p2->getKey(), 'quantity_ordered' => 3, 'unit_price' => 200]);

    $pi = app(CreateProformaFromSalesOrder::class)->handle($so->refresh());

    expect($pi->status)->toBe(ProformaInvoiceStatus::Draft)
        ->and($pi->customer_id)->toBe($customer->getKey())
        ->and($pi->sales_order_id)->toBe($so->getKey())
        ->and($pi->lines)->toHaveCount(2)
        // 5×120 + 3×200 = 1200
        ->and((string) $pi->total())->toBe('1200.00');
});
