<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\GenerateRolls;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessProduction;
use App\Enums\AccountType;
use App\Enums\InventoryTransactionType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FabricRoll;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('splits a produced quantity into rolls linked to the batch and order', function () {
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
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0, 'is_textile' => true, 'is_roll_tracked' => true, 'gsm' => '160', 'width' => '72"']);
    app(ReceiveStock::class)->handle($wh, $yarn, '1000', '10', InventoryTransactionType::Opening, null);
    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'category' => 'knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => false]);

    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 300, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => 300]);
    app(IssueProcessMaterials::class)->handle($order);
    app(RecordProcessProduction::class)->handle($order->refresh(), '300');

    $rolls = app(GenerateRolls::class)->handle($order->refresh(), '300', 4);

    expect($rolls)->toHaveCount(4)
        ->and(FabricRoll::where('process_order_id', $order->getKey())->count())->toBe(4)
        ->and((string) FabricRoll::where('process_order_id', $order->getKey())->sum('weight'))->toBe('300.0000')
        ->and($rolls[0]->batch_id)->toBe($order->output_batch_id)
        ->and($rolls[0]->gsm)->toBe('160');
});
