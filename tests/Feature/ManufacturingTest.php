<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Manufacturing\CompleteManufacturingOrder;
use App\Actions\Manufacturing\IssueManufacturingMaterials;
use App\Actions\Manufacturing\RecordProduction;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Manufacturing\MaterialAvailability;
use App\Domain\Reporting\AccountBalances;
use App\Enums\AccountType;
use App\Enums\ManufacturingOrderStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ManufacturingException;
use App\Models\Account;
use App\Models\AccountingPeriod;
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

/** @return array{0: Warehouse, 1: Product, 2: Product, 3: Product, 4: BillOfMaterials, 5: Company, 6: array<string, Account>} */
function manufacturingSetup(): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false]);
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
        'wip' => $mk('1300', 'Work In Progress', AccountType::Asset),
    ];

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

    return [$warehouse, $c1, $c2, $fg, $bom, $company, $accounts];
}

function onHand(Warehouse $w, Product $p): StockBalance
{
    return StockBalance::query()->where('warehouse_id', $w->getKey())->where('product_id', $p->getKey())->firstOrFail();
}

it('rolls up the standard material cost of a BOM', function () {
    [, , , , $bom] = manufacturingSetup();

    expect((string) $bom->load('components.component')->materialCost()->toScale(2))->toBe('35.00'); // 2×10 + 3×5
});

it('reports material availability and shortage from the BOM and stock', function () {
    [$wh, $c1, , $fg, $bom] = manufacturingSetup();
    app(ReceiveStock::class)->handle($wh, $c1, '20', '10'); // C1 only; no C2

    $order = ManufacturingOrder::create([
        'bill_of_materials_id' => $bom->getKey(), 'product_id' => $fg->getKey(),
        'warehouse_id' => $wh->getKey(), 'quantity' => 4, 'status' => 'planned',
    ]);

    $check = MaterialAvailability::for($order);
    expect($check['ok'])->toBeFalse()
        ->and($check['lines'][0]['required'])->toBe('8.0000')   // 4 × 2 C1
        ->and($check['lines'][0]['shortage'])->toBe('0.0000')   // 20 on hand
        ->and($check['lines'][1]['required'])->toBe('12.0000')  // 4 × 3 C2
        ->and($check['lines'][1]['shortage'])->toBe('12.0000'); // none on hand
});

it('completes an order — issuing components and receiving finished goods at rolled-up cost', function () {
    [$wh, $c1, $c2, $fg, $bom, $company] = manufacturingSetup();
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
        ->and((string) $result->quantity_produced)->toBe('4.0000')
        ->and((string) $result->wip_cost)->toBe('0.00') // WIP fully cleared
        ->and((string) onHand($wh, $c1)->quantity_on_hand)->toBe('12.0000')  // 20 − 8
        ->and((string) onHand($wh, $c2)->quantity_on_hand)->toBe('8.0000')   // 20 − 12
        ->and((string) onHand($wh, $fg)->quantity_on_hand)->toBe('4.0000')
        ->and((string) onHand($wh, $fg)->average_cost)->toBe('35.000000')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('runs the staged flow: issue to WIP, then partial then full production', function () {
    [$wh, $c1, $c2, $fg, $bom, $company, $accounts] = manufacturingSetup();
    app(ReceiveStock::class)->handle($wh, $c1, '20', '10');
    app(ReceiveStock::class)->handle($wh, $c2, '20', '5');

    $order = ManufacturingOrder::create([
        'bill_of_materials_id' => $bom->getKey(), 'product_id' => $fg->getKey(),
        'warehouse_id' => $wh->getKey(), 'quantity' => 4, 'status' => 'planned',
    ]);

    // Issue all materials → WIP holds 140, components leave stock.
    $order = app(IssueManufacturingMaterials::class)->handle($order);
    expect($order->status)->toBe(ManufacturingOrderStatus::InProgress)
        ->and((string) $order->wip_cost)->toBe('140.00')
        ->and((string) AccountBalances::netForAccount($accounts['wip']))->toBe('140.00')
        ->and((string) onHand($wh, $c1)->quantity_on_hand)->toBe('12.0000');

    // Produce 1 of 4 → finished goods 1 @ 35, WIP down to 105.
    $order = app(RecordProduction::class)->handle($order, '1');
    expect($order->status)->toBe(ManufacturingOrderStatus::InProgress)
        ->and((string) $order->quantity_produced)->toBe('1.0000')
        ->and((string) $order->wip_cost)->toBe('105.00')
        ->and((string) onHand($wh, $fg)->quantity_on_hand)->toBe('1.0000');

    // Produce remaining 3 → completed, WIP fully cleared.
    $order = app(RecordProduction::class)->handle($order, '3');
    expect($order->status)->toBe(ManufacturingOrderStatus::Completed)
        ->and((string) $order->quantity_produced)->toBe('4.0000')
        ->and((string) $order->wip_cost)->toBe('0.00')
        ->and((string) onHand($wh, $fg)->quantity_on_hand)->toBe('4.0000')
        ->and((string) AccountBalances::netForAccount($accounts['wip']))->toBe('0.00') // WIP reconciles to zero
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('rejects producing more than the remaining quantity', function () {
    [$wh, $c1, $c2, $fg, $bom] = manufacturingSetup();
    app(ReceiveStock::class)->handle($wh, $c1, '20', '10');
    app(ReceiveStock::class)->handle($wh, $c2, '20', '5');
    $order = ManufacturingOrder::create([
        'bill_of_materials_id' => $bom->getKey(), 'product_id' => $fg->getKey(),
        'warehouse_id' => $wh->getKey(), 'quantity' => 4, 'status' => 'planned',
    ]);
    $order = app(IssueManufacturingMaterials::class)->handle($order);

    expect(fn () => app(RecordProduction::class)->handle($order, '5'))
        ->toThrow(ManufacturingException::class);
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
