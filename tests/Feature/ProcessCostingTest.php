<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessCosts;
use App\Actions\Process\RecordProcessProduction;
use App\Enums\AccountType;
use App\Enums\InventoryTransactionType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Machine;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('absorbs labour, machine, utility and overhead into the actual unit cost', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    foreach ([['1200', 'Inventory', AccountType::Asset], ['1300', 'WIP', AccountType::Asset], ['2400', 'Accrued Overhead', AccountType::Liability]] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }

    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $yarn = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'YARN', 'name' => 'Yarn', 'cost_price' => 10, 'selling_price' => 0]);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    app(ReceiveStock::class)->handle($wh, $yarn, '100', '10', InventoryTransactionType::Opening, null);

    $machine = Machine::create(['code' => 'KN-1', 'name' => 'Knitter', 'type' => 'knitting', 'hourly_cost' => 100]);
    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => false]);

    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'warehouse_id' => $wh->getKey(), 'machine_id' => $machine->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 10, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => 40]); // 40 × 10 = 400 material

    app(IssueProcessMaterials::class)->handle($order);

    // Conversion: labour 500 + machine (2h × 100 = 200) + utility 100 + overhead 50 = 850.
    app(RecordProcessCosts::class)->handle($order->refresh(), [
        'labour' => 500, 'machine_hours' => 2, 'utility' => 100, 'overhead' => 50,
    ]);
    $order->refresh();

    expect($order->machine_cost)->toBe('200.00')
        ->and($order->labour_cost)->toBe('500.00')
        ->and($order->total_cost)->toBe('1250.00')  // 400 material + 850 conversion
        ->and($order->wip_cost)->toBe('1250.00');

    // Produce 10 → actual unit cost = 1250 / 10 = ৳125; full cost absorbed into stock.
    app(RecordProcessProduction::class)->handle($order->refresh(), '10');
    $order->refresh();

    expect($order->output_unit_cost)->toBe('125.0000')
        ->and($order->wip_cost)->toBe('0.00')
        ->and((float) StockBalance::where('product_id', $grey->getKey())->value('average_cost'))->toBe(125.0);
});
