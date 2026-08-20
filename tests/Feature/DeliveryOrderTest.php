<?php

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Sales\CancelDeliveryOrder;
use App\Actions\Sales\FulfillSalesOrder;
use App\Actions\Sales\PostDeliveryOrder;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\AccountBalances;
use App\Enums\AccountType;
use App\Enums\DeliveryOrderStatus;
use App\Enums\PeriodStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\DeliveryOrderException;
use App\Exceptions\InsufficientStockException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: SalesOrder, 2: Product, 3: Warehouse, 4: Customer, 5: array<string, Account>} */
function deliveryOrderSetup(int $onHand = 100): array
{
    $company = Company::factory()->create(['allow_negative_stock' => false, 'require_delivery_order' => true]);
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $mk = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $accounts = [
        'receivable' => $mk('1100', 'AR', AccountType::Asset),
        'inventory' => $mk('1200', 'Inventory', AccountType::Asset),
        'sales' => $mk('4000', 'Sales', AccountType::Revenue),
        'cogs' => $mk('5000', 'COGS', AccountType::Expense),
    ];
    $customer = Customer::create(['name' => 'Acme', 'code' => 'C1']);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH1']);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'P', 'name' => 'Widget', 'cost_price' => 320, 'selling_price' => 420]);
    app(ReceiveStock::class)->handle($warehouse, $product, (string) $onHand, '320');

    $so = SalesOrder::create([
        'so_number' => 'SO-1', 'customer_id' => $customer->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'order_date' => now()->toDateString(), 'status' => 'confirmed',
    ]);
    $so->lines()->create(['product_id' => $product->getKey(), 'quantity_ordered' => 100, 'quantity_delivered' => 0, 'unit_price' => 420]);

    return [$company, $so, $product, $warehouse, $customer, $accounts];
}

function makeDo(SalesOrder $so, Product $p, Warehouse $wh, Customer $c, string $number, string $qty): DeliveryOrder
{
    $do = DeliveryOrder::create([
        'number' => $number, 'sales_order_id' => $so->getKey(), 'customer_id' => $c->getKey(),
        'warehouse_id' => $wh->getKey(), 'delivery_date' => now()->toDateString(), 'status' => 'draft',
    ]);
    $do->lines()->create([
        'sales_order_line_id' => $so->lines->first()->getKey(),
        'product_id' => $p->getKey(), 'quantity' => $qty, 'unit_price' => 420,
    ]);

    return $do;
}

function doStock(Product $p): string
{
    return (string) StockBalance::query()->where('product_id', $p->getKey())->firstOrFail()->quantity_on_hand;
}

it('posts a delivery order: stock out + COGS, SO advanced, no AR/revenue yet', function () {
    [$company, $so, $product, $wh, $customer, $accounts] = deliveryOrderSetup();

    $do = makeDo($so, $product, $wh, $customer, 'DO-1', '40');
    $posted = app(PostDeliveryOrder::class)->handle($do);

    expect($posted->status)->toBe(DeliveryOrderStatus::Delivered)
        ->and(doStock($product))->toBe('60.0000')                                        // 100 - 40
        ->and((string) $so->lines()->first()->quantity_delivered)->toBe('40.0000')
        ->and($so->refresh()->status)->toBe(SalesOrderStatus::PartiallyDelivered)
        ->and((string) AccountBalances::netForAccount($accounts['cogs']))->toBe('12800.00')     // 40 × 320
        ->and((string) AccountBalances::netForAccount($accounts['inventory']))->toBe('-12800.00')
        // DO must NOT create AR or revenue — that is the invoice's job.
        ->and((string) AccountBalances::netForAccount($accounts['receivable']))->toBe('0.00')
        ->and((string) AccountBalances::netForAccount($accounts['sales']))->toBe('0.00')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('supports multiple partial deliveries that complete the sales order', function () {
    [, $so, $product, $wh, $customer] = deliveryOrderSetup();

    app(PostDeliveryOrder::class)->handle(makeDo($so->refresh(), $product, $wh, $customer, 'DO-1', '40'));
    app(PostDeliveryOrder::class)->handle(makeDo($so->refresh(), $product, $wh, $customer, 'DO-2', '30'));
    app(PostDeliveryOrder::class)->handle(makeDo($so->refresh(), $product, $wh, $customer, 'DO-3', '30'));

    expect((string) $so->lines()->first()->quantity_delivered)->toBe('100.0000')
        ->and($so->refresh()->status)->toBe(SalesOrderStatus::Delivered)
        ->and(doStock($product))->toBe('0.0000');
});

it('blocks delivery when stock is insufficient (negative stock not allowed)', function () {
    [, $so, $product, $wh, $customer] = deliveryOrderSetup(onHand: 30);

    $do = makeDo($so, $product, $wh, $customer, 'DO-1', '50'); // only 30 on hand

    expect(fn () => app(PostDeliveryOrder::class)->handle($do))->toThrow(InsufficientStockException::class);
    expect(doStock($product))->toBe('30.0000') // unchanged
        ->and($do->fresh()->status)->toBe(DeliveryOrderStatus::Draft);
});

it('blocks delivery beyond the sales-order quantity', function () {
    [, $so, $product, $wh, $customer] = deliveryOrderSetup(onHand: 500);

    $do = makeDo($so, $product, $wh, $customer, 'DO-1', '120'); // SO is only 100

    expect(fn () => app(PostDeliveryOrder::class)->handle($do))->toThrow(DeliveryOrderException::class);
});

it('cancels a posted delivery: stock returns, COGS reversed, SO rolled back', function () {
    [$company, $so, $product, $wh, $customer, $accounts] = deliveryOrderSetup();
    $do = app(PostDeliveryOrder::class)->handle(makeDo($so, $product, $wh, $customer, 'DO-1', '40'));

    $cancelled = app(CancelDeliveryOrder::class)->handle($do, 'Customer refused delivery');

    expect($cancelled->status)->toBe(DeliveryOrderStatus::Cancelled)
        ->and(doStock($product))->toBe('100.0000')                                       // 60 + 40 back
        ->and((string) $so->lines()->first()->quantity_delivered)->toBe('0.0000')
        ->and($so->refresh()->status)->toBe(SalesOrderStatus::Confirmed)
        ->and((string) AccountBalances::netForAccount($accounts['cogs']))->toBe('0.00')   // reversed
        ->and((string) AccountBalances::netForAccount($accounts['inventory']))->toBe('0.00')
        ->and($cancelled->notes)->toContain('Cancelled: Customer refused delivery')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('leaves the direct FulfillSalesOrder flow working when DO is not required', function () {
    [$company, $so, $product, , , $accounts] = deliveryOrderSetup();
    $company->update(['require_delivery_order' => false]);

    // Direct delivery posts stock + AR + revenue + COGS in one step (existing behaviour).
    app(FulfillSalesOrder::class)->handle($so, [$so->lines->first()->getKey() => '25'], '2026-06-05');

    expect(doStock($product))->toBe('75.0000')
        ->and((string) AccountBalances::netForAccount($accounts['receivable']))->toBe('10500.00') // 25 × 420
        ->and((string) AccountBalances::netForAccount($accounts['sales']))->toBe('10500.00')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});
