<?php

use App\Actions\Process\GenerateProductionPlanFromSalesOrder;
use App\Enums\DyeingType;
use App\Enums\LabDipStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LabDip;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: SalesOrder} */
function dyeingTypeScenario(DyeingType $type): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $customer = Customer::create(['company_id' => $company->getKey(), 'code' => 'C1', 'name' => 'Acme', 'is_active' => true]);

    $pt = fn (string $code, string $name, string $cat, int $sort, bool $qc = false) => ProcessType::create([
        'code' => $code, 'name' => $name, 'category' => $cat, 'consumes_material' => true, 'produces_material' => true,
        'requires_lab_dip' => false, 'requires_qc' => $qc, 'sort' => $sort,
    ]);
    $pt('KNIT', 'Knitting', 'knitting', 1, true);
    $pt('DYE', 'Dyeing', 'dyeing', 2, true);
    $pt('FIN', 'Finishing', 'finishing', 3, true);
    $pt('PRETREAT', 'Pretreatment', 'dyeing', 20);
    $pt('DYE1', 'Part 1 Dyeing', 'dyeing', 21, true);
    $pt('IWASH', 'Intermediate Wash', 'dyeing', 22);
    $pt('DYE2', 'Part 2 Dyeing', 'dyeing', 23, true);
    $pt('WASHOFF', 'Wash-off', 'finishing', 24);

    LabDip::create(['colour' => 'Navy', 'status' => LabDipStatus::CustomerApproved, 'dyeing_type' => $type]);

    $fabric = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'FG', 'name' => 'Fabric', 'cost_price' => 0, 'selling_price' => 0,
        'is_textile' => true, 'fabric_type' => 'Fleece', 'construction' => '100% Cotton', 'gsm' => '180', 'width' => '72 Open', 'colour' => 'Navy']);
    $so = SalesOrder::create(['so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(), 'order_date' => now(), 'delivery_date' => now()->addDays(30), 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $fabric->getKey(), 'quantity_ordered' => 500, 'unit_price' => 10]);

    return [$company, $so->refresh()->load('lines.product')];
}

it('expands one-part dyeing into Pretreatment → Dyeing → Wash-off', function () {
    [, $so] = dyeingTypeScenario(DyeingType::OnePart);

    $plan = app(GenerateProductionPlanFromSalesOrder::class)->handle($so);
    $codes = $plan->stages()->with('processType')->orderBy('sequence')->get()->map(fn ($s) => $s->processType->code)->all();

    // Knit + (Pretreat → Dye → Wash-off) + Finish.
    expect($codes)->toBe(['KNIT', 'PRETREAT', 'DYE', 'WASHOFF', 'FIN']);
});

it('expands two-part dyeing into Pretreat → Dye 1 → Inter. Wash → Dye 2 → Wash-off', function () {
    [, $so] = dyeingTypeScenario(DyeingType::TwoPart);

    $plan = app(GenerateProductionPlanFromSalesOrder::class)->handle($so);
    $codes = $plan->stages()->with('processType')->orderBy('sequence')->get()->map(fn ($s) => $s->processType->code)->all();

    expect($codes)->toBe(['KNIT', 'PRETREAT', 'DYE1', 'IWASH', 'DYE2', 'WASHOFF', 'FIN']);
});

it('leaves dyeing as a single stage when no dyeing type is set (unchanged)', function () {
    [, $so] = dyeingTypeScenario(DyeingType::OnePart);
    LabDip::query()->update(['dyeing_type' => null]);

    $plan = app(GenerateProductionPlanFromSalesOrder::class)->handle($so->refresh()->load('lines.product'));
    $codes = $plan->stages()->with('processType')->orderBy('sequence')->get()->map(fn ($s) => $s->processType->code)->all();

    expect($codes)->toBe(['KNIT', 'DYE', 'FIN']);
});
