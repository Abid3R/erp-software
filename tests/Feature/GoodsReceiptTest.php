<?php

use App\Actions\Purchasing\PostGoodsReceipt;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\AccountBalances;
use App\Enums\AccountType;
use App\Enums\GoodsReceiptStatus;
use App\Enums\PeriodStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\GoodsReceiptException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: PurchaseOrder, 2: Product, 3: Warehouse, 4: Supplier, 5: array<string, Account>} */
function grnSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $year = (int) now()->format('Y');
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => "FY{$year}", 'fiscal_year' => $year,
        'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'status' => PeriodStatus::Open,
    ]);
    $mk = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $accounts = [
        'inventory' => $mk('1200', 'Inventory', AccountType::Asset),
        'grni' => $mk('2100', 'GRNI', AccountType::Liability),
    ];

    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-1', 'supplier_id' => $supplier->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'order_date' => now()->toDateString(), 'status' => 'approved',
    ]);
    $po->lines()->create(['product_id' => $product->getKey(), 'quantity_ordered' => 100, 'quantity_received' => 0, 'unit_price' => 320]);

    return [$company, $po, $product, $warehouse, $supplier, $accounts];
}

function makeGrn(PurchaseOrder $po, Product $product, Warehouse $wh, Supplier $sup, string $number, string $received, string $accepted, string $rejected = '0'): GoodsReceipt
{
    $grn = GoodsReceipt::create([
        'number' => $number, 'purchase_order_id' => $po->getKey(), 'supplier_id' => $sup->getKey(),
        'warehouse_id' => $wh->getKey(), 'receipt_date' => now()->toDateString(), 'status' => 'draft',
    ]);
    $grn->lines()->create([
        'purchase_order_line_id' => $po->lines->first()->getKey(),
        'product_id' => $product->getKey(),
        'ordered_quantity' => 100, 'received_quantity' => $received,
        'accepted_quantity' => $accepted, 'rejected_quantity' => $rejected,
        'unit_cost' => 320, 'qc_status' => 'passed',
    ]);

    return $grn;
}

function grnStock(Product $p): string
{
    $b = StockBalance::query()->where('product_id', $p->getKey())->first();

    return $b ? (string) $b->quantity_on_hand : '0';
}

it('posts a partial GRN: stock in, PO advanced, GRNI accrued, trial balance balanced', function () {
    [$company, $po, $product, $wh, $sup, $accounts] = grnSetup();

    $grn = makeGrn($po, $product, $wh, $sup, 'GRN-1', received: '60', accepted: '60');
    $posted = app(PostGoodsReceipt::class)->handle($grn);

    expect($posted->status)->toBe(GoodsReceiptStatus::Posted)
        ->and(grnStock($product))->toBe('60.0000')
        ->and((string) $po->lines()->first()->quantity_received)->toBe('60.0000')
        ->and($po->refresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and((string) AccountBalances::netForAccount($accounts['inventory']))->toBe('19200.00') // 60 × 320
        ->and((string) AccountBalances::netForAccount($accounts['grni']))->toBe('19200.00')      // credit accrual
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();

    // Second receipt completes the PO.
    $grn2 = makeGrn($po->refresh(), $product, $wh, $sup, 'GRN-2', received: '40', accepted: '40');
    app(PostGoodsReceipt::class)->handle($grn2);

    expect(grnStock($product))->toBe('100.0000')
        ->and($po->refresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('only takes the accepted quantity into stock, not the rejected', function () {
    [, $po, $product, $wh, $sup] = grnSetup();

    $grn = makeGrn($po, $product, $wh, $sup, 'GRN-3', received: '60', accepted: '50', rejected: '10');
    app(PostGoodsReceipt::class)->handle($grn);

    expect(grnStock($product))->toBe('50.0000') // only accepted
        ->and((string) $po->lines()->first()->quantity_received)->toBe('50.0000');
});

it('rejects accepting more than the ordered quantity', function () {
    [, $po, $product, $wh, $sup] = grnSetup();

    $grn = makeGrn($po, $product, $wh, $sup, 'GRN-4', received: '200', accepted: '200');

    expect(fn () => app(PostGoodsReceipt::class)->handle($grn))->toThrow(GoodsReceiptException::class);
    expect(grnStock($product))->toBe('0'); // nothing received
});

it('refuses to post an already posted GRN', function () {
    [, $po, $product, $wh, $sup] = grnSetup();
    $grn = makeGrn($po, $product, $wh, $sup, 'GRN-5', received: '10', accepted: '10');
    app(PostGoodsReceipt::class)->handle($grn);

    expect(fn () => app(PostGoodsReceipt::class)->handle($grn->refresh()))->toThrow(GoodsReceiptException::class);
});
