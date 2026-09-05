<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\CompleteProcessOrder;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessProduction;
use App\Enums\AccountType;
use App\Enums\InventoryTransactionType;
use App\Enums\LabDipStatus;
use App\Enums\PeriodStatus;
use App\Enums\ProcessOrderStatus;
use App\Exceptions\ProcessException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Batch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LabDip;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** Minimal dyeing setup: grey fabric + dye in stock, a dyed-fabric output, DYE process type, an approved lab dip. */
function dyeingSetup(): array
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
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY-1', 'name' => 'Grey Fabric', 'cost_price' => 40, 'selling_price' => 0]);
    $dye = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYE-1', 'name' => 'Reactive Dye', 'cost_price' => 200, 'selling_price' => 0]);
    $dyed = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYED-1', 'name' => 'Dyed Fabric', 'cost_price' => 0, 'selling_price' => 0]);

    app(ReceiveStock::class)->handle($wh, $grey, '100', '40', InventoryTransactionType::Opening, null);
    app(ReceiveStock::class)->handle($wh, $dye, '10', '200', InventoryTransactionType::Opening, null);
    $greyBatch = Batch::create(['product_id' => $grey->getKey(), 'warehouse_id' => $wh->getKey(), 'quantity' => 100]);

    $dyeType = ProcessType::create([
        'code' => 'DYE', 'name' => 'Dyeing', 'consumes_material' => true,
        'produces_material' => true, 'requires_lab_dip' => true, 'requires_qc' => true,
    ]);
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);
    $labDip = LabDip::create(['colour' => 'Navy Blue', 'customer_id' => $customer->getKey(), 'status' => LabDipStatus::CustomerApproved]);

    return compact('company', 'wh', 'grey', 'dye', 'dyed', 'greyBatch', 'dyeType', 'labDip');
}

it('runs a dyeing order consuming grey fabric + dye into dyed fabric with lab dip and traceability', function () {
    ['wh' => $wh, 'grey' => $grey, 'dye' => $dye, 'dyed' => $dyed, 'greyBatch' => $greyBatch, 'dyeType' => $dyeType, 'labDip' => $labDip] = dyeingSetup();

    $order = ProcessOrder::create([
        'process_type_id' => $dyeType->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $dyed->getKey(), 'lab_dip_id' => $labDip->getKey(),
        'planned_quantity' => 95, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $grey->getKey(), 'planned_quantity' => 100, 'batch_id' => $greyBatch->getKey()]);
    $order->inputs()->create(['product_id' => $dye->getKey(), 'planned_quantity' => 5]);

    expect($order->reference)->toBe('DYE-0001');

    // Issue: 100kg grey (৳40) + 5kg dye (৳200) = ৳4,000 + ৳1,000 = ৳5,000 into WIP.
    app(IssueProcessMaterials::class)->handle($order);
    $order->refresh();

    expect($order->status)->toBe(ProcessOrderStatus::InProgress)
        ->and($order->wip_cost)->toBe('5000.00')
        ->and((float) StockBalance::where('product_id', $grey->getKey())->value('quantity_on_hand'))->toBe(0.0)
        ->and((float) StockBalance::where('product_id', $dye->getKey())->value('quantity_on_hand'))->toBe(5.0);

    // Produce 95kg dyed fabric → dyed stock, batch, QC gate.
    app(RecordProcessProduction::class)->handle($order->refresh(), '95', '5');
    $order->refresh();

    $dyedBatch = Batch::where('product_id', $dyed->getKey())->first();
    expect($order->status)->toBe(ProcessOrderStatus::Qc)
        ->and($order->wip_cost)->toBe('0.00')
        ->and((float) StockBalance::where('product_id', $dyed->getKey())->value('quantity_on_hand'))->toBe(95.0)
        ->and($dyedBatch->consumedBatches->pluck('id'))->toContain($greyBatch->getKey());

    // Pass QC → completed.
    app(CompleteProcessOrder::class)->handle($order->refresh());
    expect($order->refresh()->status)->toBe(ProcessOrderStatus::Completed);
});

it('refuses to issue a dyeing order without an approved lab dip', function () {
    ['wh' => $wh, 'grey' => $grey, 'dyed' => $dyed, 'greyBatch' => $greyBatch, 'dyeType' => $dyeType] = dyeingSetup();

    $order = ProcessOrder::create([
        'process_type_id' => $dyeType->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $dyed->getKey(), 'lab_dip_id' => null, // no lab dip
        'planned_quantity' => 95, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $grey->getKey(), 'planned_quantity' => 100, 'batch_id' => $greyBatch->getKey()]);

    expect(fn () => app(IssueProcessMaterials::class)->handle($order))
        ->toThrow(ProcessException::class);

    // Nothing consumed; grey fabric untouched.
    expect((float) StockBalance::where('product_id', $grey->getKey())->value('quantity_on_hand'))->toBe(100.0);
});
