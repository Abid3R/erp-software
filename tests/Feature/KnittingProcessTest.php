<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\CompleteProcessOrder;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessProduction;
use App\Domain\Accounting\PartyLedger;
use App\Enums\AccountType;
use App\Enums\InventoryTransactionType;
use App\Enums\PeriodStatus;
use App\Enums\ProcessOrderStatus;
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

/** @return array{0: Company, 1: Warehouse, 2: Product, 3: Product, 4: ProcessType, 5: Batch} */
function knittingSetup(): array
{
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
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $yarn = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'YARN-1', 'name' => 'Cotton Yarn', 'cost_price' => 10, 'selling_price' => 0]);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY-1', 'name' => 'Grey Fabric', 'cost_price' => 0, 'selling_price' => 0]);

    // Stock the yarn (with a batch we can trace).
    app(ReceiveStock::class)->handle($warehouse, $yarn, '100', '10', InventoryTransactionType::Opening, null);
    $yarnBatch = Batch::create(['product_id' => $yarn->getKey(), 'warehouse_id' => $warehouse->getKey(), 'quantity' => 100]);

    $knit = ProcessType::create([
        'code' => 'KNIT', 'name' => 'Knitting', 'consumes_material' => true,
        'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true,
    ]);

    return [$company, $warehouse, $yarn, $grey, $knit, $yarnBatch];
}

it('runs a knitting order: consumes yarn into WIP, produces a grey-fabric batch, and clears WIP', function () {
    [$company, $warehouse, $yarn, $grey, $knit, $yarnBatch] = knittingSetup();

    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 10, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => 40, 'batch_id' => $yarnBatch->getKey()]);

    // Reference auto-generated per process type.
    expect($order->reference)->toBe('KNIT-0001');

    // 1. Issue materials → 40kg yarn consumed into WIP (40 × ৳10 = ৳400).
    app(IssueProcessMaterials::class)->handle($order);
    $order->refresh();

    $yarnOnHand = StockBalance::where('product_id', $yarn->getKey())->value('quantity_on_hand');
    expect($order->status)->toBe(ProcessOrderStatus::InProgress)
        ->and($order->wip_cost)->toBe('400.00')
        ->and((float) $yarnOnHand)->toBe(60.0); // 100 - 40

    // 2. Record production → 10kg grey fabric received; requires QC so it lands in QC.
    app(RecordProcessProduction::class)->handle($order->refresh(), '10', '2');
    $order->refresh();

    $greyBatch = Batch::where('product_id', $grey->getKey())->first();
    $greyOnHand = StockBalance::where('product_id', $grey->getKey())->value('quantity_on_hand');

    expect($order->status)->toBe(ProcessOrderStatus::Qc)
        ->and($order->wip_cost)->toBe('0.00')
        ->and($order->wastage_quantity)->toBe('2.0000')
        ->and((float) $greyOnHand)->toBe(10.0)
        ->and($greyBatch)->not->toBeNull()
        ->and($greyBatch->source_id)->toBe($order->getKey());

    // Batch traceability: the grey batch traces back to the yarn batch.
    expect($greyBatch->consumedBatches->pluck('id'))->toContain($yarnBatch->getKey());

    // 3. Pass QC → Completed.
    app(CompleteProcessOrder::class)->handle($order->refresh());
    expect($order->refresh()->status)->toBe(ProcessOrderStatus::Completed)
        ->and($order->completed_at)->not->toBeNull();
});

it('blocks issuing more material than is in stock (never negative stock)', function () {
    [$company, $warehouse, $yarn, $grey, $knit] = knittingSetup();

    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'output_product_id' => $grey->getKey(), 'planned_quantity' => 10, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => 500]); // only 100 in stock

    expect(fn () => app(IssueProcessMaterials::class)->handle($order))
        ->toThrow(App\Exceptions\InsufficientStockException::class);

    // Nothing was consumed; order stays open.
    expect($order->refresh()->status)->toBe(ProcessOrderStatus::Planned)
        ->and((float) StockBalance::where('product_id', $yarn->getKey())->value('quantity_on_hand'))->toBe(100.0);
});
