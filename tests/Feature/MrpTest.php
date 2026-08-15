<?php

use App\Actions\Inventory\ReceiveStock;
use App\Domain\Manufacturing\MrpPlanner;
use App\Models\BillOfMaterials;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('nets sales-order demand exploded through a BOM against stock and incoming POs', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);

    $component = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'C', 'name' => 'Component', 'cost_price' => 0, 'selling_price' => 0]);
    $finished = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'FG', 'name' => 'Finished', 'cost_price' => 0, 'selling_price' => 0]);

    // 1 FG needs 2× component.
    $bom = BillOfMaterials::create(['product_id' => $finished->getKey(), 'name' => 'Default', 'output_quantity' => 1]);
    $bom->components()->create(['component_product_id' => $component->getKey(), 'quantity' => 2]);

    // 5 components on hand.
    app(ReceiveStock::class)->handle($warehouse, $component, '5', '10');

    // Demand: confirmed sales order for 10 finished goods.
    $customer = Customer::create(['name' => 'Buyer', 'code' => 'B1']);
    $so = SalesOrder::create(['so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(), 'order_date' => '2026-06-01', 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $finished->getKey(), 'quantity_ordered' => 10, 'unit_price' => 100]);

    // Incoming: approved purchase order for 3 components.
    $supplier = Supplier::create(['name' => 'Vendor', 'code' => 'V1']);
    $po = PurchaseOrder::create(['po_number' => 'PO-1', 'supplier_id' => $supplier->getKey(), 'warehouse_id' => $warehouse->getKey(), 'order_date' => '2026-06-01', 'status' => 'approved']);
    $po->lines()->create(['product_id' => $component->getKey(), 'quantity_ordered' => 3, 'unit_price' => 10]);

    $plan = collect(MrpPlanner::plan($company->getKey()))->keyBy('product_id');

    // Finished good: demand 10, none in stock/incoming → manufacture 10.
    expect($plan[$finished->getKey()]['net'])->toBe('10.0000')
        ->and($plan[$finished->getKey()]['action'])->toBe('manufacture');

    // Component: demand 20 (10 × 2), on hand 5, incoming 3 → purchase 12.
    expect($plan[$component->getKey()]['demand'])->toBe('20.0000')
        ->and($plan[$component->getKey()]['net'])->toBe('12.0000')
        ->and($plan[$component->getKey()]['action'])->toBe('purchase');
});

it('subtracts reserved stock from availability when netting demand', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $wh = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);
    app(ReceiveStock::class)->handle($wh, $product, '20', '10');

    // Reserve 12 out of 20 on hand (e.g. earmarked for another SO).
    StockBalance::query()->where('product_id', $product->getKey())->update(['reserved_quantity' => '12']);

    $customer = Customer::create(['name' => 'Buyer', 'code' => 'B1']);
    $so = SalesOrder::create(['so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(), 'order_date' => '2026-06-01', 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $product->getKey(), 'quantity_ordered' => 15, 'unit_price' => 100]);

    $plan = collect(MrpPlanner::plan($company->getKey()))->keyBy('product_id');

    // Available = 20 on-hand − 12 reserved = 8; need 15 → net 7 to purchase.
    expect($plan[$product->getKey()]['reserved'])->toBe('12.0000')
        ->and($plan[$product->getKey()]['net'])->toBe('7.0000')
        ->and($plan[$product->getKey()]['reason'])->toBe('Sales demand');
});

it('counts remaining quantity on open manufacturing orders as incoming supply', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $wh = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $component = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'C', 'name' => 'C', 'cost_price' => 0, 'selling_price' => 0]);
    $fg = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'FG', 'name' => 'FG', 'cost_price' => 0, 'selling_price' => 0]);
    $bom = BillOfMaterials::create(['product_id' => $fg->getKey(), 'name' => 'Default', 'output_quantity' => 1]);
    $bom->components()->create(['component_product_id' => $component->getKey(), 'quantity' => 1]);

    // Demand: SO for 10 FG. No stock. An open MO already covers 6 (planned).
    $customer = Customer::create(['name' => 'Buyer', 'code' => 'B1']);
    $so = SalesOrder::create(['so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(), 'order_date' => '2026-06-01', 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $fg->getKey(), 'quantity_ordered' => 10, 'unit_price' => 100]);
    ManufacturingOrder::create([
        'bill_of_materials_id' => $bom->getKey(), 'product_id' => $fg->getKey(),
        'warehouse_id' => $wh->getKey(), 'quantity' => 6, 'quantity_produced' => 0, 'status' => 'planned',
    ]);

    $plan = collect(MrpPlanner::plan($company->getKey()))->keyBy('product_id');

    // FG: demand 10, incoming from open MO = 6 → net 4 more to manufacture.
    expect($plan[$fg->getKey()]['incoming'])->toBe('6.0000')
        ->and($plan[$fg->getKey()]['net'])->toBe('4.0000')
        ->and($plan[$fg->getKey()]['action'])->toBe('manufacture');
});

it('flags a product below its reorder level even without sales demand', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $wh = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $product = Product::create([
        'unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P',
        'cost_price' => 0, 'selling_price' => 0, 'reorder_level' => 25,
    ]);
    app(ReceiveStock::class)->handle($wh, $product, '10', '10'); // below 25

    $plan = collect(MrpPlanner::plan($company->getKey()))->keyBy('product_id');

    expect($plan[$product->getKey()]['net'])->toBe('15.0000')  // 25 - 10
        ->and($plan[$product->getKey()]['reason'])->toBe('Below reorder level')
        ->and($plan[$product->getKey()]['action'])->toBe('purchase');
});

it('suggests nothing when stock and incoming cover demand', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'P', 'cost_price' => 0, 'selling_price' => 0]);
    app(ReceiveStock::class)->handle($warehouse, $product, '100', '10');

    $customer = Customer::create(['name' => 'Buyer', 'code' => 'B1']);
    $so = SalesOrder::create(['so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(), 'order_date' => '2026-06-01', 'status' => 'confirmed']);
    $so->lines()->create(['product_id' => $product->getKey(), 'quantity_ordered' => 20, 'unit_price' => 100]);

    expect(MrpPlanner::plan($company->getKey()))->toBe([]);
});
