<?php

use App\Actions\Process\CreateProcessOrderFromStage;
use App\Actions\Process\GenerateProductionPlanFromSalesOrder;
use App\Enums\ProcessOrderStatus;
use App\Enums\ProductionStageStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: SalesOrder} */
function planScenario(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $fabric = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'FG', 'name' => 'Finished Fabric', 'cost_price' => 0, 'selling_price' => 600]);
    $customer = Customer::create(['company_id' => $company->getKey(), 'code' => 'C1', 'name' => 'Acme', 'is_active' => true]);

    // The three standard textile stages, out of sequence to prove ordering.
    ProcessType::create(['code' => 'FIN', 'name' => 'Finishing', 'category' => 'finishing', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true, 'sort' => 3]);
    ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'category' => 'knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true, 'sort' => 1]);
    ProcessType::create(['code' => 'DYE', 'name' => 'Dyeing', 'category' => 'dyeing', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true, 'sort' => 2]);

    $so = SalesOrder::create([
        'so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(),
        'order_date' => '2026-09-01', 'delivery_date' => '2026-09-30', 'status' => 'confirmed',
    ]);
    $so->lines()->create(['product_id' => $fabric->getKey(), 'quantity_ordered' => 300, 'unit_price' => 600]);

    return [$company, $so->refresh()->load('lines')];
}

it('builds a plan from a sales order with the stages in production order', function () {
    [, $so] = planScenario();

    $plan = app(GenerateProductionPlanFromSalesOrder::class)->handle($so);

    expect($plan->sales_order_id)->toBe($so->getKey())
        ->and($plan->customer_id)->toBe($so->customer_id)
        ->and((float) $plan->planned_quantity)->toBe(300.0)
        ->and($plan->due_date->toDateString())->toBe('2026-09-30');

    $stages = $plan->stages;
    expect($stages)->toHaveCount(3)
        ->and($stages[0]->processType->code)->toBe('KNIT')
        ->and($stages[1]->processType->code)->toBe('DYE')
        ->and($stages[2]->processType->code)->toBe('FIN')
        ->and((float) $stages[0]->planned_quantity)->toBe(300.0);
});

it('consolidates knitting by fabric quality across colours, but dyes/finishes per colour', function () {
    [$company, $so] = planScenario();
    $kg = Unit::query()->where('code', 'KG')->first();
    // Same quality (composition + GSM + width) as the primary product, different colour.
    $so->lines->first()->product->update(['fabric_type' => 'Fleece', 'construction' => '100% Cotton', 'gsm' => '280', 'width' => '72 Open', 'colour' => 'Navy']);
    $black = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'FG-BLK', 'name' => 'Fleece Black', 'cost_price' => 0, 'selling_price' => 0,
        'is_textile' => true, 'fabric_type' => 'Fleece', 'construction' => '100% Cotton', 'gsm' => '280', 'width' => '72 Open', 'colour' => 'Black']);
    $so->lines()->create(['product_id' => $black->getKey(), 'quantity_ordered' => 200, 'unit_price' => 600]);

    $plan = app(GenerateProductionPlanFromSalesOrder::class)->handle($so->refresh()->load('lines.product'));

    // 1 knit (both colours, same quality → summed 500) + 2 dye + 2 finish = 5 stages.
    $knit = $plan->stages->filter(fn ($s) => $s->processType->code === 'KNIT');
    $dye = $plan->stages->filter(fn ($s) => $s->processType->code === 'DYE');
    expect($plan->stages)->toHaveCount(5)
        ->and($knit)->toHaveCount(1)
        ->and((float) $knit->first()->planned_quantity)->toBe(500.0)   // 300 Navy + 200 Black knitted together
        ->and($dye)->toHaveCount(2);                                    // one dye lot per colour
});

it('is idempotent per order — regenerating returns the same plan', function () {
    [, $so] = planScenario();

    $a = app(GenerateProductionPlanFromSalesOrder::class)->handle($so);
    $b = app(GenerateProductionPlanFromSalesOrder::class)->handle($so->refresh());

    expect($b->getKey())->toBe($a->getKey());
});

it('spawns a process order from a stage and links the whole chain', function () {
    [, $so] = planScenario();
    $plan = app(GenerateProductionPlanFromSalesOrder::class)->handle($so);
    $knitStage = $plan->stages->first();

    $order = app(CreateProcessOrderFromStage::class)->handle($knitStage);

    expect($order->production_plan_id)->toBe($plan->getKey())
        ->and($order->production_plan_stage_id)->toBe($knitStage->getKey())
        ->and($order->sales_order_id)->toBe($so->getKey())
        ->and($order->process_type_id)->toBe($knitStage->process_type_id)
        ->and($order->status)->toBe(ProcessOrderStatus::Planned)
        ->and((float) $order->planned_quantity)->toBe(300.0)
        ->and($knitStage->refresh()->status)->toBe(ProductionStageStatus::Released);
});
