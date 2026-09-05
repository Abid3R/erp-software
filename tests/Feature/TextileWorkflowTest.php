<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\CompleteProcessOrder;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Sales\RecordSale;
use App\Domain\Accounting\PartyLedger;
use App\Domain\Manufacturing\BatchTrace;
use App\Enums\AccountType;
use App\Enums\InventoryTransactionType;
use App\Enums\LabDipStatus;
use App\Enums\PeriodStatus;
use App\Enums\ProcessOrderStatus;
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

/** Run a process order fully: issue → produce → pass QC. Returns the produced output batch. */
function runProcess(ProcessType $type, Warehouse $wh, Product $output, int $qty, array $inputs, ?LabDip $labDip = null): Batch
{
    $order = ProcessOrder::create([
        'process_type_id' => $type->getKey(), 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $output->getKey(), 'lab_dip_id' => $labDip?->getKey(),
        'planned_quantity' => $qty, 'status' => 'planned',
    ]);
    foreach ($inputs as [$product, $inQty, $batch]) {
        $order->inputs()->create(['product_id' => $product->getKey(), 'planned_quantity' => $inQty, 'batch_id' => $batch?->getKey()]);
    }
    app(IssueProcessMaterials::class)->handle($order);
    app(RecordProcessProduction::class)->handle($order->refresh(), (string) $qty);
    app(CompleteProcessOrder::class)->handle($order->refresh()); // pass QC

    return $order->refresh()->outputBatch;
}

it('connects the whole textile chain: yarn -> knit -> dye -> finish -> sell, fully traceable', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    foreach ([
        ['1100', 'AR', AccountType::Asset], ['1200', 'Inventory', AccountType::Asset],
        ['1300', 'WIP', AccountType::Asset], ['4000', 'Sales', AccountType::Revenue],
        ['5000', 'COGS', AccountType::Expense],
    ] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }

    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $yarn = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'YARN', 'name' => 'Yarn', 'cost_price' => 10, 'selling_price' => 0]);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey Fabric', 'cost_price' => 0, 'selling_price' => 0]);
    $dyed = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYED', 'name' => 'Dyed Fabric', 'cost_price' => 0, 'selling_price' => 0]);
    $finished = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'FIN', 'name' => 'Finished Fabric', 'cost_price' => 0, 'selling_price' => 500]);

    app(ReceiveStock::class)->handle($wh, $yarn, '200', '10', InventoryTransactionType::Opening, null);
    $yarnBatch = Batch::create(['product_id' => $yarn->getKey(), 'warehouse_id' => $wh->getKey(), 'quantity' => 200]);

    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true]);
    $dye = ProcessType::create(['code' => 'DYE', 'name' => 'Dyeing', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => true, 'requires_qc' => true]);
    // Finishing is configurable — here the "Compacting" method (not hard-coded).
    $compacting = ProcessType::create(['code' => 'COMP', 'name' => 'Compacting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => true]);

    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);
    $labDip = LabDip::create(['colour' => 'Navy', 'customer_id' => $customer->getKey(), 'status' => LabDipStatus::CustomerApproved]);

    // Knit → Dye → Finish, each output batch feeding the next stage.
    $greyBatch = runProcess($knit, $wh, $grey, 100, [[$yarn, 100, $yarnBatch]]);
    $dyedBatch = runProcess($dye, $wh, $dyed, 100, [[$grey, 100, $greyBatch]], $labDip);
    $finishedBatch = runProcess($compacting, $wh, $finished, 100, [[$dyed, 100, $dyedBatch]]);

    // Finished fabric is on hand.
    expect((float) StockBalance::where('product_id', $finished->getKey())->value('quantity_on_hand'))->toBe(100.0);

    // Full backward traceability: finished → dyed → grey → yarn.
    $origin = collect(BatchTrace::origin($finishedBatch))->map(fn ($r) => $r['batch']->getKey());
    expect($origin)->toContain($dyedBatch->getKey())
        ->toContain($greyBatch->getKey())
        ->toContain($yarnBatch->getKey());

    // The finished fabric flows into the existing sales → AR → accounting pipeline.
    app(RecordSale::class)->handle($wh, $finished, '30', '500', '2026-06-01', customer: $customer);

    expect((float) StockBalance::where('product_id', $finished->getKey())->value('quantity_on_hand'))->toBe(70.0)
        ->and((string) PartyLedger::receivable($customer))->toBe('15000.00'); // 30 × ৳500 booked to AR
});
