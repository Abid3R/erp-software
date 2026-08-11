<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Manufacturing\CompleteManufacturingOrder;
use App\Enums\InventoryTransactionType;
use App\Enums\ManufacturingOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\BillOfMaterials;
use App\Models\Company;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Warehouse, 1: Product, 2: Product, 3: Product, 4: BillOfMaterials} */
function manufacturingSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN']);

    $make = fn (string $sku, string $cost): Product => Product::create([
        'unit_id' => $unit->getKey(), 'sku' => $sku, 'name' => $sku, 'cost_price' => $cost, 'selling_price' => 0,
    ]);
    $c1 = $make('C1', '10');
    $c2 = $make('C2', '5');
    $fg = $make('FG', '0');

    // 1 FG needs 2× C1 + 3× C2.
    $bom = BillOfMaterials::create(['product_id' => $fg->getKey(), 'name' => 'Default', 'output_quantity' => 1]);
    $bom->components()->create(['component_product_id' => $c1->getKey(), 'quantity' => 2]);
    $bom->components()->create(['component_product_id' => $c2->getKey(), 'quantity' => 3]);

    return [$warehouse, $c1, $c2, $fg, $bom];
}

function onHand(Warehouse $w, Product $p): StockBalance
{
    return StockBalance::query()->where('warehouse_id', $w->getKey())->where('product_id', $p->getKey())->firstOrFail();
}

it('rolls up the standard material cost of a BOM', function () {
    [, , , , $bom] = manufacturingSetup();

    expect((string) $bom->load('components.component')->materialCost()->toScale(2))->toBe('35.00'); // 2×10 + 3×5
});

it('completes an order — issuing components and receiving finished goods at rolled-up cost', function () {
    [$wh, $c1, $c2, $fg, $bom] = manufacturingSetup();
    $receive = app(ReceiveStock::class);
    $receive->handle($wh, $c1, '20', '10');
    $receive->handle($wh, $c2, '20', '5');

    $order = ManufacturingOrder::create([
        'bill_of_materials_id' => $bom->getKey(), 'product_id' => $fg->getKey(),
        'warehouse_id' => $wh->getKey(), 'quantity' => 4, 'status' => 'planned',
    ]);

    $result = app(CompleteManufacturingOrder::class)->handle($order);

    // 4 units → 8× C1 (80) + 12× C2 (60) = 140 total; unit cost 35.
    expect($result->status)->toBe(ManufacturingOrderStatus::Completed)
        ->and((string) $result->total_cost)->toBe('140.00')
        ->and((string) $result->output_unit_cost)->toBe('35.0000')
        ->and((string) onHand($wh, $c1)->quantity_on_hand)->toBe('12.0000')  // 20 − 8
        ->and((string) onHand($wh, $c2)->quantity_on_hand)->toBe('8.0000')   // 20 − 12
        ->and((string) onHand($wh, $fg)->quantity_on_hand)->toBe('4.0000')
        ->and((string) onHand($wh, $fg)->average_cost)->toBe('35.000000'); // average_cost is decimal:6
});

it('refuses to complete when a component is short, leaving the order open', function () {
    [$wh, $c1, $c2, $fg, $bom] = manufacturingSetup();
    app(ReceiveStock::class)->handle($wh, $c1, '5', '10'); // not enough C1, no C2

    $order = ManufacturingOrder::create([
        'bill_of_materials_id' => $bom->getKey(), 'product_id' => $fg->getKey(),
        'warehouse_id' => $wh->getKey(), 'quantity' => 10, 'status' => 'planned',
    ]);

    expect(fn () => app(CompleteManufacturingOrder::class)->handle($order))
        ->toThrow(InsufficientStockException::class);

    expect($order->fresh()->status)->toBe(ManufacturingOrderStatus::Planned)
        ->and(StockBalance::query()->where('product_id', $c1->getKey())->first()->quantity_on_hand)->toBe('5.0000'); // unchanged
});
