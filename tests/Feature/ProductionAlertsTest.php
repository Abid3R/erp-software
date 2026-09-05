<?php

use App\Actions\Notifications\SendProductionAlerts;
use App\Models\Company;
use App\Models\NotificationSetting;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function qcAwaitingOrder(Company $company): ProcessOrder
{
    $unit = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'GREY-'.uniqid(), 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    $type = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true]);

    return ProcessOrder::create([
        'process_type_id' => $type->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $product->getKey(), 'planned_quantity' => 10,
        'produced_quantity' => 10, 'status' => 'qc',
    ]);
}

it('alerts production/management when an order is awaiting QC', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $manufacturer = memberWithRoles($company, 'manufacturing');
    $outsider = memberWithRoles($company);
    qcAwaitingOrder($company);

    $flagged = app(SendProductionAlerts::class)->forCompany($company->getKey());

    expect($flagged)->toBe(1)
        ->and($manufacturer->notifications()->count())->toBe(1)
        ->and($outsider->notifications()->count())->toBe(0);
});

it('does not send production alerts when the toggle is off', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $manufacturer = memberWithRoles($company, 'manufacturing');
    NotificationSetting::create(['company_id' => $company->getKey(), 'production_alerts_enabled' => false]);
    qcAwaitingOrder($company);

    expect(app(SendProductionAlerts::class)->forCompany($company->getKey()))->toBe(0)
        ->and($manufacturer->notifications()->count())->toBe(0);
});
