<?php

use App\Actions\Process\GenerateProductionPlanFromSalesOrder;
use App\Actions\Purchasing\CreateRequisitionFromMrp;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProcessRoute;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('generates a production plan from the product process route', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $fabric = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'FG', 'name' => 'Finished Fabric', 'cost_price' => 0, 'selling_price' => 0]);
    $customer = Customer::create(['company_id' => $company->getKey(), 'code' => 'C1', 'name' => 'Acme', 'is_active' => true]);
    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'category' => 'knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true]);
    $fin = ProcessType::create(['code' => 'COMP', 'name' => 'Compacting', 'category' => 'finishing', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true]);

    // A 2-step route (no dyeing) — proves the plan follows the route, not the default 3.
    $route = ProcessRoute::create(['name' => 'Knit → Compact', 'product_id' => $fabric->getKey(), 'is_active' => true]);
    $route->steps()->create(['process_type_id' => $knit->getKey(), 'sequence' => 1]);
    $route->steps()->create(['process_type_id' => $fin->getKey(), 'sequence' => 2]);

    $so = SalesOrder::create(['so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(), 'order_date' => '2026-09-01', 'delivery_date' => '2026-09-30', 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $fabric->getKey(), 'quantity_ordered' => 300, 'unit_price' => 600]);

    $plan = app(GenerateProductionPlanFromSalesOrder::class)->handle($so->refresh()->load('lines'));

    expect($plan->stages)->toHaveCount(2)
        ->and($plan->stages[0]->processType->code)->toBe('KNIT')
        ->and($plan->stages[1]->processType->code)->toBe('COMP');
});

it('creates a purchase requisition from MRP purchase shortages', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $dye = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYE', 'name' => 'Reactive Dye', 'cost_price' => 800, 'selling_price' => 0]);
    $customer = Customer::create(['company_id' => $company->getKey(), 'code' => 'C1', 'name' => 'Acme', 'is_active' => true]);

    // Demand with no stock and no BOM → MRP suggests purchasing it.
    $so = SalesOrder::create(['so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(), 'order_date' => '2026-09-01', 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $dye->getKey(), 'quantity_ordered' => 500, 'unit_price' => 0]);

    $req = app(CreateRequisitionFromMrp::class)->handle($company->getKey());

    expect($req)->not->toBeNull()
        ->and($req->status->value)->toBe('draft')
        ->and($req->lines)->toHaveCount(1)
        ->and((int) $req->lines->first()->product_id)->toBe($dye->getKey())
        ->and((float) $req->lines->first()->quantity)->toBe(500.0);
});
