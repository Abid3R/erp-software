<?php

use App\Actions\Inventory\PostStockTransfer;
use App\Actions\Inventory\ReceiveStock;
use App\Enums\StockTransferStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockTransferException;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Product, 1: Warehouse, 2: Warehouse} */
function transferSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);

    $from = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Main', 'code' => 'WH1']);
    $to = Warehouse::create(['company_id' => $company->getKey(), 'name' => 'Branch', 'code' => 'WH2']);
    $unit = Unit::create(['company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['company_id' => $company->getKey(), 'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 100, 'selling_price' => 150]);
    app(ReceiveStock::class)->handle($from, $product, '40', '120'); // 40 @ 120 in source

    return [$product, $from, $to];
}

function whStock(Product $p, Warehouse $w): string
{
    $b = StockBalance::query()->where('product_id', $p->getKey())->where('warehouse_id', $w->getKey())->first();

    return $b ? (string) $b->quantity_on_hand : '0';
}

it('moves stock between warehouses preserving quantity and cost', function () {
    [$product, $from, $to] = transferSetup();
    $trf = StockTransfer::create([
        'number' => 'TRF-1', 'from_warehouse_id' => $from->getKey(), 'to_warehouse_id' => $to->getKey(),
        'transfer_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $trf->lines()->create(['product_id' => $product->getKey(), 'quantity' => 15]);

    $posted = app(PostStockTransfer::class)->handle($trf);

    expect($posted->status)->toBe(StockTransferStatus::Posted)
        ->and(whStock($product, $from))->toBe('25.0000')
        ->and(whStock($product, $to))->toBe('15.0000')
        // destination inherits the source average cost (120).
        ->and((string) StockBalance::query()->where('warehouse_id', $to->getKey())->where('product_id', $product->getKey())->firstOrFail()->average_cost)
        ->toBe('120.000000');
});

it('aborts a transfer when the source is short, moving nothing', function () {
    [$product, $from, $to] = transferSetup();
    $trf = StockTransfer::create([
        'number' => 'TRF-2', 'from_warehouse_id' => $from->getKey(), 'to_warehouse_id' => $to->getKey(),
        'transfer_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $trf->lines()->create(['product_id' => $product->getKey(), 'quantity' => 999]);

    expect(fn () => app(PostStockTransfer::class)->handle($trf))->toThrow(InsufficientStockException::class);
    expect(whStock($product, $from))->toBe('40.0000')
        ->and(whStock($product, $to))->toBe('0');
});

it('rejects a transfer to the same warehouse', function () {
    [$product, $from] = transferSetup();
    $trf = StockTransfer::create([
        'number' => 'TRF-3', 'from_warehouse_id' => $from->getKey(), 'to_warehouse_id' => $from->getKey(),
        'transfer_date' => '2026-06-05', 'status' => 'draft',
    ]);
    $trf->lines()->create(['product_id' => $product->getKey(), 'quantity' => 5]);

    expect(fn () => app(PostStockTransfer::class)->handle($trf))->toThrow(StockTransferException::class);
});
