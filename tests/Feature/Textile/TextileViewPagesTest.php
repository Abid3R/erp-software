<?php

use App\Enums\ProcessMode;
use App\Models\Company;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: ProcessType, 2: Warehouse, 3: Product} */
function textileViewScenario(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'category' => 'knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => false, 'subcontractable' => true]);

    return [$company, $knit, $wh, $grey];
}

it('renders the knitting sub-contract view page (spec fields resolve the category)', function () {
    [$company, $knit, $wh, $grey] = textileViewScenario();
    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'mode' => ProcessMode::Subcontract, 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 100, 'status' => 'planned',
    ]);

    $this->actingAs(superAdminFor($company))
        ->get('/admin/knitting-subcontracts/'.$order->getKey())
        ->assertOk();
});

it('renders the in-house process order view page', function () {
    [$company, $knit, $wh, $grey] = textileViewScenario();
    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'mode' => ProcessMode::InHouse, 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 100, 'status' => 'planned',
    ]);

    $this->actingAs(superAdminFor($company))
        ->get('/admin/process-orders/'.$order->getKey())
        ->assertOk();
});

it('renders the textile create forms (recipe repeaters + calculate action)', function () {
    [$company] = textileViewScenario();
    $user = superAdminFor($company);

    // Fabric Specifications use a single Manage page (create/edit via modal), so no /create route.
    foreach ([
        '/admin/process-orders/create',
        '/admin/knitting-subcontracts/create',
        '/admin/lab-dips/create',
        '/admin/production-plans/create',
    ] as $path) {
        $this->actingAs($user)->get($path)->assertOk();
    }
});

it('renders the production plan sheet print view', function () {
    [$company, $knit] = textileViewScenario();
    $plan = \App\Models\ProductionPlan::create([
        'buyer' => 'H&M', 'style_no' => 'ST-1', 'planned_quantity' => 500, 'order_quantity' => 2000,
        'order_unit' => 'PCS', 'colour' => 'Navy', 'status' => \App\Enums\ProductionPlanStatus::Scheduled,
    ]);
    $plan->stages()->create([
        'process_type_id' => $knit->getKey(), 'sequence' => 1, 'planned_quantity' => 500,
        'status' => \App\Enums\ProductionStageStatus::Pending,
    ]);

    $this->actingAs(superAdminFor($company))
        ->get('/print/production-plan/'.$plan->getKey())
        ->assertOk();
});
