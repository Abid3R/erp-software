<?php

use App\Domain\Textile\OrderProductionSummary;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\QualityInspection;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('rolls up an order\'s production into per-process totals', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    $customer = Customer::create(['company_id' => $company->getKey(), 'code' => 'C1', 'name' => 'Acme', 'is_active' => true]);
    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'category' => 'knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true]);

    $so = SalesOrder::create(['so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(), 'order_date' => now(), 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $grey->getKey(), 'quantity_ordered' => 300, 'unit_price' => 10]);

    // Two knitting runs against the order, each producing a batch + a QC record.
    foreach ([[100, 5], [200, 8]] as [$prod, $waste]) {
        $o = ProcessOrder::create(['process_type_id' => $knit->getKey(), 'warehouse_id' => $wh->getKey(), 'output_product_id' => $grey->getKey(),
            'sales_order_id' => $so->getKey(), 'planned_quantity' => $prod, 'produced_quantity' => $prod, 'wastage_quantity' => $waste,
            'total_cost' => $prod * 20, 'status' => 'completed']);
        $b = Batch::create(['product_id' => $grey->getKey(), 'warehouse_id' => $wh->getKey(), 'quantity' => $prod, 'source_type' => $o->getMorphClass(), 'source_id' => $o->getKey()]);
        QualityInspection::create(['inspectable_type' => $o->getMorphClass(), 'inspectable_id' => $o->getKey(), 'batch_id' => $b->getKey(),
            'product_id' => $grey->getKey(), 'inspected_quantity' => $prod, 'passed_quantity' => $prod - 2, 'rejected_quantity' => 2, 'status' => 'partial']);
    }

    $summary = OrderProductionSummary::forSalesOrder($so->refresh());

    expect($summary['rows'])->toHaveCount(1)                       // one process: Knitting
        ->and($summary['rows'][0]['process'])->toBe('Knitting')
        ->and($summary['rows'][0]['runs'])->toBe(2)
        ->and($summary['totals']['produced'])->toBe('300.00')      // 100 + 200
        ->and($summary['totals']['wastage'])->toBe('13.00')        // 5 + 8
        ->and((int) $summary['totals']['batches'])->toBe(2)
        ->and($summary['totals']['rejected'])->toBe('4.00')        // 2 + 2
        ->and($summary['totals']['cost'])->toBe('6000.00');        // 100×20 + 200×20
});

it('renders the item-grouped order spec sheet', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $customer = Customer::create(['company_id' => $company->getKey(), 'code' => 'C1', 'name' => 'Acme', 'is_active' => true]);
    $body = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'FLC-A', 'name' => 'Fleece A', 'cost_price' => 0, 'selling_price' => 0, 'is_textile' => true, 'fabric_type' => 'Fleece', 'style' => 'MS09B', 'colour' => 'Navy', 'gsm' => '280']);
    $rib = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'RIB-A', 'name' => 'Rib A', 'cost_price' => 0, 'selling_price' => 0, 'is_textile' => true, 'fabric_type' => '2X2 Rib', 'style' => 'MS09B', 'colour' => 'Navy', 'gsm' => '375']);
    $so = SalesOrder::create(['so_number' => 'SO-3', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(), 'order_date' => now(), 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $body->getKey(), 'quantity_ordered' => 382, 'unit_price' => 2.1]);
    $so->lines()->create(['product_id' => $rib->getKey(), 'quantity_ordered' => 53, 'unit_price' => 2.1]);

    $this->actingAs(superAdminFor($company))->get('/print/order-spec-sheet/'.$so->getKey())->assertOk()
        ->assertSee('MS09B')->assertSee('Fleece')->assertSee('2X2 Rib');
});

it('renders the order summary print view', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $customer = Customer::create(['company_id' => $company->getKey(), 'code' => 'C1', 'name' => 'Acme', 'is_active' => true]);
    $so = SalesOrder::create(['so_number' => 'SO-2', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(), 'order_date' => now(), 'status' => 'confirmed']);

    $this->actingAs(superAdminFor($company))->get('/print/order-summary/'.$so->getKey())->assertOk();
});
