<?php

use App\Actions\Sales\ImportSalesOrderFromCsv;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function importCsvScenario(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $customer = Customer::create(['company_id' => $company->getKey(), 'code' => 'CZ', 'name' => 'CZ Group', 'is_active' => true]);
    $wh = Warehouse::create(['company_id' => $company->getKey(), 'code' => 'MAIN', 'name' => 'Main']);

    return [$company, $customer, $wh];
}

$csv = <<<CSV
Item Name,Item Code,Style / PO[10],Composition[20],Gsm[30],Stitch Length[35],Width[40],Size[50],Color / Code[60],Specials Instruction[80],QTY,Qty Unit,Unit Price,Requested Date of Delivery,Backorder Type
,,,,,,,,,,,,,,
2X2 Lycra Rib,FG-00007,MS09B,60% Cotton 40% Polyester,375/380,,50/52 Inch Open,,LIGHT NAVY,,53,KG,2.1,,Order Now
2X2 Lycra Rib,FG-00007,MS10B,60% Cotton 40% Polyester,375/380,,50/52 Inch Open,,BLACK,,201,KG,2.1,,Order Now
2X2 Lycra Rib,FG-00007,MS10B,60% Cotton 40% Polyester,375/380,,50/52 Inch Open,,LIGHT NAVY,,87,KG,2.1,,Order Now
Fleece,FG-00003,MS09B,60% Cotton 40% Polyester,280/290,,72/74 Inch Open,,LIGHT NAVY,,382,KG,2.1,,Order Now
Fleece,FG-00003,MS10B,60% Cotton 40% Polyester,280/290,,72/74 Inch Open,,BLACK,,1447,KG,2.1,,Order Now
Single Jersey,FG-00010,MS09B,60% Cotton 40% Polyester,150,,74/76 Inch Open,,LIGHT NAVY,,26,KG,2.1,,Order Now
CSV;

it('creates a sales order with a line per CSV row and skips header/blank rows', function () use ($csv) {
    [, $customer, $wh] = importCsvScenario();

    $order = app(ImportSalesOrderFromCsv::class)->handle($csv, [
        'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(),
    ]);

    expect($order->customer_id)->toBe($customer->getKey())
        ->and($order->status->value)->toBe('draft')
        ->and($order->lines()->count())->toBe(6)                       // 6 data rows; blank + field rows skipped
        ->and((float) $order->lines()->sum('quantity_ordered'))->toBe(2196.0); // 53+201+87+382+1447+26
});

it('creates a self-describing textile product per code + style + colour', function () use ($csv) {
    [, $customer, $wh] = importCsvScenario();

    app(ImportSalesOrderFromCsv::class)->handle($csv, [
        'customer_id' => $customer->getKey(), 'warehouse_id' => $wh->getKey(),
    ]);

    // FG-00003 Fleece, MS10B, Black → its own SKU with textile spec.
    $p = Product::query()->where('sku', 'FG00003-MS10B-BLACK')->first();
    expect($p)->not->toBeNull()
        ->and($p->is_textile)->toBeTrue()
        ->and($p->textile_type)->toBe('finished_fabric')
        ->and($p->gsm)->toBe('280/290')
        ->and($p->colour)->toBe('BLACK')
        ->and($p->fabric_type)->toBe('Fleece');

    // The two MS10B Light Navy / MS09B Light Navy rib rows are distinct SKUs.
    expect(Product::query()->where('sku', 'like', 'FG00007-%')->count())->toBe(3);
});

it('rejects a CSV without the required columns', function () {
    importCsvScenario();
    app(ImportSalesOrderFromCsv::class)->handle("Foo,Bar\n1,2\n", ['customer_id' => 1, 'warehouse_id' => 1]);
})->throws(RuntimeException::class);
