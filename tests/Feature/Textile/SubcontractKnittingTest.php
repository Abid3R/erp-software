<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Process\IssueProcessMaterials;
use App\Actions\Process\RecordProcessProduction;
use App\Actions\Process\RecordSubcontractCharge;
use App\Enums\AccountType;
use App\Enums\InventoryTransactionType;
use App\Enums\PeriodStatus;
use App\Enums\ProcessMode;
use App\Exceptions\ProcessException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalLine;
use App\Models\ProcessOrder;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: ProcessOrder, 2: Supplier, 3: Product} */
function subcontractOrder(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    foreach ([['1200', 'Inventory', AccountType::Asset], ['1300', 'WIP', AccountType::Asset], ['2000', 'Payable', AccountType::Liability]] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }

    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $yarn = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'YARN', 'name' => 'Yarn', 'cost_price' => 10, 'selling_price' => 0]);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    $service = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'NS-KNIT', 'name' => 'Knitting Service', 'cost_price' => 0, 'selling_price' => 0, 'is_service' => true]);
    app(ReceiveStock::class)->handle($wh, $yarn, '200', '10', InventoryTransactionType::Opening, null);

    $knit = ProcessType::create(['code' => 'KNIT', 'name' => 'Knitting', 'category' => 'knitting', 'consumes_material' => true, 'produces_material' => true, 'requires_lab_dip' => false, 'requires_qc' => false, 'subcontractable' => true]);
    $sub = Supplier::create(['company_id' => $company->getKey(), 'name' => 'Acme Knitting', 'code' => 'SUP-KNIT', 'is_active' => true]);

    $order = ProcessOrder::create([
        'process_type_id' => $knit->getKey(), 'mode' => ProcessMode::Subcontract, 'warehouse_id' => $wh->getKey(),
        'output_product_id' => $grey->getKey(), 'subcontractor_id' => $sub->getKey(), 'service_item_id' => $service->getKey(),
        'service_rate' => 5, 'bill_quantity' => 200, 'planned_quantity' => 190, 'status' => 'planned',
    ]);
    $order->inputs()->create(['product_id' => $yarn->getKey(), 'planned_quantity' => 200]);

    return [$company, $order, $sub, $grey];
}

it('posts the knitting charge as a payable to the sub-contractor and capitalises it into WIP', function () {
    [, $order, $sub] = subcontractOrder();

    app(IssueProcessMaterials::class)->handle($order);   // yarn 200 × 10 = 2000 into WIP
    expect($order->refresh()->total_cost)->toBe('2000.00');

    app(RecordSubcontractCharge::class)->handle($order->refresh()); // 200 × 5 = 1000
    $order->refresh();

    // Charge captured and folded into WIP / total cost.
    expect($order->service_charge)->toBe('1000.00')
        ->and($order->total_cost)->toBe('3000.00')
        ->and($order->wip_cost)->toBe('3000.00');

    // The credit landed on Payable, tagged to the sub-contractor.
    $payable = Account::where('code', '2000')->first();
    $line = JournalLine::where('account_id', $payable->getKey())->where('credit', '>', 0)->latest('id')->first();
    expect((float) $line->credit)->toBe(1000.0)
        ->and($line->party_type)->toBe($sub->getMorphClass())
        ->and((int) $line->party_id)->toBe($sub->getKey());
});

it('refuses to record the sub-contract charge twice (idempotent)', function () {
    [, $order] = subcontractOrder();
    app(IssueProcessMaterials::class)->handle($order);
    app(RecordSubcontractCharge::class)->handle($order->refresh());

    app(RecordSubcontractCharge::class)->handle($order->refresh());
})->throws(ProcessException::class);

it('receives grey fabric at yarn + knitting cost', function () {
    [, $order, , $grey] = subcontractOrder();
    app(IssueProcessMaterials::class)->handle($order);
    app(RecordSubcontractCharge::class)->handle($order->refresh());

    // Produce 190 → unit cost = 3000 / 190 = 15.7895.
    app(RecordProcessProduction::class)->handle($order->refresh(), '190');
    $order->refresh();

    expect($order->output_unit_cost)->toBe('15.7895')
        ->and((float) StockBalance::where('product_id', $grey->getKey())->value('average_cost'))->toBeGreaterThan(15.0);
});

it('rejects a sub-contract charge on an in-house order', function () {
    [, $order] = subcontractOrder();
    $order->update(['mode' => ProcessMode::InHouse]);
    app(IssueProcessMaterials::class)->handle($order);

    app(RecordSubcontractCharge::class)->handle($order->refresh());
})->throws(ProcessException::class);
