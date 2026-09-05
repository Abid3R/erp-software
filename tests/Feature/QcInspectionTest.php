<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordQualityInspection;
use App\Enums\AccountType;
use App\Enums\InventoryTransactionType;
use App\Enums\PeriodStatus;
use App\Enums\ProcessOrderStatus;
use App\Enums\QualityStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Batch;
use App\Models\Company;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** A knitting order produced and sitting at the QC gate. */
function orderAtQc(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    foreach ([['1200', 'Inventory', AccountType::Asset], ['1300', 'WIP', AccountType::Asset], ['5100', 'Inventory Adjustment', AccountType::Expense]] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }

    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $yarn = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'YARN-1', 'name' => 'Yarn', 'cost_price' => 10, 'selling_price' => 0]);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY-1', 'name' => 'Grey Fabric', 'cost_price' => 0, 'selling_price' => 0]);

    app(ReceiveStock::class)->handle($wh, $yarn, '100', '10', InventoryTransactionType::Opening, null);

    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true]);

    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 10, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => 40]);
    app(IssueProcessMaterials::class)->handle($order);
    app(RecordProcessProduction::class)->handle($order->refresh(), '10');

    return [$company, $order->refresh(), $grey];
}

it('removes rejected quantity from available stock and records a partial inspection', function () {
    [$company, $order, $grey] = orderAtQc();

    // 10 kg produced and in stock before QC.
    expect((float) StockBalance::where('product_id', $grey->getKey())->value('quantity_on_hand'))->toBe(10.0)
        ->and($order->status)->toBe(ProcessOrderStatus::Qc);

    $inspection = app(RecordQualityInspection::class)->handle($order, '10', '7', '3', 'holes', 'partial batch');

    expect($inspection->status)->toBe(QualityStatus::Partial)
        ->and($inspection->rejected_quantity)->toBe('3.0000')
        // Rejected 3 kg is gone from stock — only the 7 passed remain.
        ->and((float) StockBalance::where('product_id', $grey->getKey())->value('quantity_on_hand'))->toBe(7.0)
        ->and($order->refresh()->status)->toBe(ProcessOrderStatus::Completed);

    // The output batch was reduced to the passed quantity.
    $batch = Batch::where('product_id', $grey->getKey())->first();
    expect((float) $batch->quantity)->toBe(7.0);
});

it('passes QC cleanly when nothing is rejected', function () {
    [$company, $order, $grey] = orderAtQc();

    $inspection = app(RecordQualityInspection::class)->handle($order, '10', '10', '0');

    expect($inspection->status)->toBe(QualityStatus::Passed)
        ->and((float) StockBalance::where('product_id', $grey->getKey())->value('quantity_on_hand'))->toBe(10.0)
        ->and($order->refresh()->status)->toBe(ProcessOrderStatus::Completed);
});
