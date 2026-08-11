<?php

use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: PurchaseOrder, 1: int, 2: Product, 3: Company} */
function poSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    Account::create(['company_id' => $company->getKey(), 'code' => '1200', 'name' => 'Inventory', 'type' => AccountType::Asset]);
    Account::create(['company_id' => $company->getKey(), 'code' => '2100', 'name' => 'GRNI', 'type' => AccountType::Liability]);

    $supplier = Supplier::create(['company_id' => $company->getKey(), 'name' => 'Vendor', 'code' => 'V1']);
    $warehouse = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-1', 'supplier_id' => $supplier->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'order_date' => '2026-06-01', 'status' => 'approved',
    ]);
    $line = $po->lines()->create(['product_id' => $product->getKey(), 'quantity_ordered' => 50, 'unit_price' => 320]);

    return [$po, $line->getKey(), $product, $company];
}

function stockOf(Product $p): string
{
    return (string) StockBalance::query()->where('product_id', $p->getKey())->firstOrFail()->quantity_on_hand;
}

it('totals a purchase order from its lines', function () {
    [$po] = poSetup();

    expect((string) $po->load('lines')->total()->toScale(2))->toBe('16000.00'); // 50 × 320
});

it('receives partially then fully, posting stock and advancing status', function () {
    [$po, $lineId, $product] = poSetup();

    $result = app(ReceivePurchaseOrder::class)->handle($po, [$lineId => '30'], '2026-06-02');
    expect($result->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and((string) $result->lines()->first()->quantity_received)->toBe('30.0000')
        ->and(stockOf($product))->toBe('30.0000');

    $result = app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$lineId => '20'], '2026-06-03');
    expect($result->status)->toBe(PurchaseOrderStatus::Received)
        ->and(stockOf($product))->toBe('50.0000');
});

it('rejects receiving more than the outstanding quantity', function () {
    [$po, $lineId] = poSetup();

    expect(fn () => app(ReceivePurchaseOrder::class)->handle($po, [$lineId => '60'], '2026-06-02'))
        ->toThrow(PurchaseOrderException::class);
});

it('cannot receive against a draft order', function () {
    [$po, $lineId] = poSetup();
    $po->update(['status' => 'draft']);

    expect(fn () => app(ReceivePurchaseOrder::class)->handle($po->refresh(), [$lineId => '10'], '2026-06-02'))
        ->toThrow(PurchaseOrderException::class);
});

it('prints a purchase order for a company member', function () {
    [$po, , , $company] = poSetup();

    $this->actingAs(superAdminFor($company))->get(route('print.purchase-order', $po))
        ->assertOk()
        ->assertSee('Purchase Order')
        ->assertSee('PO-1');
});
