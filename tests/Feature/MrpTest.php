<?php

use App\Actions\Inventory\ReceiveStock;
use App\Domain\Manufacturing\MrpPlanner;
use App\Models\BillOfMaterials;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
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
