<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessProduction;
use App\Enums\AccountType;
use App\Enums\InventoryTransactionType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('stores textile fields on a product without affecting normal products', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);

    $normal = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'CHAIR', 'name' => 'Chair', 'cost_price' => 0, 'selling_price' => 0]);
    $fabric = Product::create([
        'unit_id' => $kg->getKey(), 'sku' => 'FAB', 'name' => 'Fabric', 'cost_price' => 0, 'selling_price' => 0,
        'is_textile' => true, 'textile_type' => 'grey_fabric', 'fabric_type' => 'Single Jersey',
        'gsm' => '160', 'width' => '72 Inch Open', 'is_roll_tracked' => true, 'default_wastage_percent' => 4,
    ]);

    expect($normal->refresh()->is_textile)->toBeFalse()
        ->and($normal->is_roll_tracked)->toBeFalse()
        ->and($fabric->refresh()->is_textile)->toBeTrue()
        ->and($fabric->textile_type)->toBe('grey_fabric')
        ->and($fabric->gsm)->toBe('160')
        ->and((float) $fabric->default_wastage_percent)->toBe(4.0);
});

it('defaults expected wastage from the process type and computes expected output', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'category' => 'knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => false, 'default_wastage_percent' => 3]);

    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 1000, 'status' => 'planned',
    ]);

    // Expected wastage inherited from the process type; expected output = 1000 × (1 − 3%).
    expect((float) $order->expected_wastage_percent)->toBe(3.0)
        ->and((string) $order->expectedOutput())->toBe('970.0000');
});

it('computes actual wastage % after production', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    foreach ([['1200', 'Inventory', AccountType::Asset], ['1300', 'WIP', AccountType::Asset]] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $yarn = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'YARN', 'name' => 'Yarn', 'cost_price' => 10, 'selling_price' => 0]);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    app(ReceiveStock::class)->handle($wh, $yarn, '1000', '10', InventoryTransactionType::Opening, null);
    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'category' => 'knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => false]);

    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 1000, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => 1000]);

    app(IssueProcessMaterials::class)->handle($order);
    // Produce 965, wastage 35 → actual wastage % = 35 / 1000 × 100 = 3.5.
    app(RecordProcessProduction::class)->handle($order->refresh(), '965', '35');

    expect((string) $order->refresh()->actualWastagePercent())->toBe('3.500');
});
