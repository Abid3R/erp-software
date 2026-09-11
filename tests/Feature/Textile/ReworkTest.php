<?php

use App\Actions\Process\CreateReworkOrder;
use App\Enums\ProcessOrderStatus;
use App\Models\Company;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('creates a rework run linked to the original without modifying it', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $dyed = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYED', 'name' => 'Dyed', 'cost_price' => 0, 'selling_price' => 0]);
    $dye = ProcessType::create(['code' => 'DYE', 'name' => 'Dyeing', 'category' => 'dyeing', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true]);

    $original = ProcessOrder::create([
        'process_type_id' => $dye->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $dyed->getKey(), 'planned_quantity' => 500, 'produced_quantity' => 500,
        'wastage_quantity' => 0, 'colour' => 'Navy', 'status' => ProcessOrderStatus::Completed,
    ]);

    $rework = app(CreateReworkOrder::class)->handle($original, '50', 'Shade off — redye');

    expect($rework->is_rework)->toBeTrue()
        ->and($rework->rework_of_process_order_id)->toBe($original->getKey())
        ->and($rework->process_type_id)->toBe($original->process_type_id)
        ->and($rework->output_product_id)->toBe($dyed->getKey())
        ->and((float) $rework->planned_quantity)->toBe(50.0)
        ->and($rework->status)->toBe(ProcessOrderStatus::Planned)
        ->and($rework->colour)->toBe('Navy');

    // Original is untouched.
    expect($original->refresh()->status)->toBe(ProcessOrderStatus::Completed)
        ->and($original->is_rework)->toBeFalse()
        ->and($original->reworks()->count())->toBe(1);
});
