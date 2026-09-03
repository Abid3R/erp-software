<?php

use App\Actions\Manufacturing\IssueManufacturingMaterials;
use App\Actions\Manufacturing\RecordProduction;
use App\Domain\Reporting\ProductionRegister;
use App\Domain\Reporting\WipValuation;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\BillOfMaterials;
use App\Models\Company;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use App\Actions\Inventory\ReceiveStock;
use App\Enums\InventoryTransactionType;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** Minimal manufacturing setup: a finished good, one raw material, a BOM, stock, and the COA accounts production needs. */
function mfgReportSetup(): array
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

    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);
    $raw = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'RM-1', 'name' => 'Raw', 'cost_price' => 10, 'selling_price' => 0]);
    $fg = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'FG-1', 'name' => 'Widget', 'cost_price' => 0, 'selling_price' => 100]);

    // Stock the raw material so materials can be issued.
    app(ReceiveStock::class)->handle($warehouse, $raw, '100', '10', InventoryTransactionType::Opening, null);

    $bom = BillOfMaterials::create(['product_id' => $fg->getKey(), 'name' => 'v1', 'output_quantity' => 1]);
    $bom->components()->create(['component_product_id' => $raw->getKey(), 'quantity' => 2]);

    $mo = ManufacturingOrder::create([
        'reference' => 'MO-0001', 'bill_of_materials_id' => $bom->getKey(), 'product_id' => $fg->getKey(),
        'warehouse_id' => $warehouse->getKey(), 'quantity' => 10, 'status' => 'planned',
    ]);

    return [$company, $mo];
}

it('shows capitalised WIP after materials are issued', function () {
    [$company, $mo] = mfgReportSetup();

    app(IssueManufacturingMaterials::class)->handle($mo);

    $wip = WipValuation::for($company->getKey());

    expect($wip['rows'])->toHaveCount(1)
        ->and($wip['rows'][0]['reference'])->toBe('MO-0001')
        ->and($wip['total'])->toBe('200.00'); // 10 units × 2 raw × ৳10
});

it('records produced finished goods in the production register and clears WIP', function () {
    [$company, $mo] = mfgReportSetup();

    app(IssueManufacturingMaterials::class)->handle($mo);
    app(RecordProduction::class)->handle($mo->fresh(), '10'); // produce all

    $production = ProductionRegister::for($company->getKey(), '2026-01-01', '2026-12-31');
    expect($production['rows'])->toHaveCount(1)
        ->and($production['rows'][0]['product'])->toBe('Widget')
        ->and($production['total_qty'])->toBe('10.0000')
        ->and($production['total_value'])->toBe('200.00');

    // Fully produced → no WIP left.
    expect(WipValuation::for($company->getKey())['total'])->toBe('0.00');
});
