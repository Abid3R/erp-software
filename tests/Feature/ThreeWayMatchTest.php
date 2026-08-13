<?php

use App\Actions\Purchasing\PostGoodsReceipt;
use App\Domain\Purchasing\ThreeWayMatch;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: PurchaseOrder, 2: Product, 3: Warehouse, 4: Supplier} */
function matchSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $year = (int) now()->format('Y');
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => "FY{$year}", 'fiscal_year' => $year,
        'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'status' => PeriodStatus::Open,
    ]);
    foreach ([['1200', 'Inventory', AccountType::Asset], ['2100', 'GRNI', AccountType::Liability]] as [$code, $name, $type]) {
        Account::create(['company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type]);
    }
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $p = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'Widget', 'cost_price' => 0, 'selling_price' => 0]);
    $sup = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $po = PurchaseOrder::create([
        'po_number' => 'PO-1', 'supplier_id' => $sup->getKey(), 'warehouse_id' => $wh->getKey(),
        'order_date' => now()->toDateString(), 'status' => 'approved',
    ]);
    $po->lines()->create(['product_id' => $p->getKey(), 'quantity_ordered' => 100, 'quantity_received' => 0, 'unit_price' => 320]);

    return [$company, $po, $p, $wh, $sup];
}

function grn(PurchaseOrder $po, Product $p, Warehouse $wh, Supplier $sup, string $number, string $accepted): void
{
    $grn = GoodsReceipt::create([
        'number' => $number, 'purchase_order_id' => $po->getKey(), 'supplier_id' => $sup->getKey(),
        'warehouse_id' => $wh->getKey(), 'receipt_date' => now()->toDateString(), 'status' => 'draft',
    ]);
    $grn->lines()->create([
        'purchase_order_line_id' => $po->lines->first()->getKey(),
        'product_id' => $p->getKey(),
        'ordered_quantity' => 100, 'received_quantity' => $accepted,
        'accepted_quantity' => $accepted, 'rejected_quantity' => '0',
        'unit_cost' => 320, 'qc_status' => 'passed',
    ]);
    app(PostGoodsReceipt::class)->handle($grn);
}

it('matches when full quantity is received and invoiced at PO price', function () {
    [, $po, $p, $wh, $sup] = matchSetup();
    grn($po->fresh(), $p, $wh, $sup, 'GRN-1', '100'); // full receipt

    $grniAccount = Account::query()->where('code', '2100')->firstOrFail();
    $inv = SupplierInvoice::create([
        'number' => 'SINV-1', 'supplier_id' => $sup->getKey(), 'purchase_order_id' => $po->getKey(),
        'invoice_date' => now()->toDateString(), 'status' => 'draft',
    ]);
    $inv->lines()->create(['account_id' => $grniAccount->getKey(), 'amount' => 32000]); // 100 × 320

    $report = ThreeWayMatch::for($inv);
    expect($report['matched'])->toBeTrue()
        ->and($report['has_po'])->toBeTrue()
        ->and($report['ordered_total'])->toBe('32000.00')
        ->and($report['received_total'])->toBe('32000.00')
        ->and($report['invoiced_total'])->toBe('32000.00')
        ->and($report['lines'][0]['status'])->toBe('OK');
});

it('flags a mismatch when the received quantity is short of what was ordered', function () {
    [, $po, $p, $wh, $sup] = matchSetup();
    grn($po->fresh(), $p, $wh, $sup, 'GRN-1', '60'); // short: only 60 of 100 received

    $grniAccount = Account::query()->where('code', '2100')->firstOrFail();
    $inv = SupplierInvoice::create([
        'number' => 'SINV-2', 'supplier_id' => $sup->getKey(), 'purchase_order_id' => $po->getKey(),
        'invoice_date' => now()->toDateString(), 'status' => 'draft',
    ]);
    $inv->lines()->create(['account_id' => $grniAccount->getKey(), 'amount' => 32000]); // supplier billed for the full 100

    $report = ThreeWayMatch::for($inv);
    expect($report['matched'])->toBeFalse()
        ->and($report['lines'][0]['ordered_qty'])->toBe('100.0000')
        ->and($report['lines'][0]['received_qty'])->toBe('60.0000')
        ->and($report['lines'][0]['status'])->toBe('Mismatch')
        ->and($report['lines'][0]['notes'])->toContain('Under-received: 40 short');
});

it('reports has_po=false when no PO is linked', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $sup = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $inv = SupplierInvoice::create([
        'number' => 'SINV-3', 'supplier_id' => $sup->getKey(),
        'invoice_date' => now()->toDateString(), 'status' => 'draft',
    ]);

    $report = ThreeWayMatch::for($inv);
    expect($report['has_po'])->toBeFalse()
        ->and($report['matched'])->toBeFalse()
        ->and($report['lines'])->toBe([]);
});
