<?php

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\ProductResource;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;
use App\Support\CsvActions;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @param list<string> $lines */
function writeCsv(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}

it('imports customers — creating, updating by code, and skipping bad rows', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    Customer::create(['name' => 'Old Name', 'code' => 'C-1']);

    $csv = writeCsv([
        'Code,Name,Phone,Email,Address,Active',
        'C-1,Updated Name,,,,yes',   // updates the existing customer
        'C-2,New Customer,017,,,yes', // creates
        ',,,,,',                      // skipped — no name
    ]);

    $result = CsvActions::process($csv, [CustomerResource::class, 'importRow']);
    unlink($csv);

    expect($result)->toBe(['created' => 1, 'updated' => 1, 'skipped' => 1])
        ->and(Customer::query()->where('code', 'C-1')->first()->name)->toBe('Updated Name')
        ->and(Customer::query()->where('code', 'C-2')->exists())->toBeTrue();
});

it('imports products, resolving the unit and skipping new rows without one', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $unit = Unit::create(['name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);

    $csv = writeCsv([
        'SKU,Name,Unit,CostPrice,SellingPrice,ReorderLevel,Active',
        'P-1,Widget,PCS,10,15,5,yes',  // creates, unit resolved
        'P-2,No Unit,BAD,1,2,,yes',     // skipped — unit not found for a new product
    ]);

    $result = CsvActions::process($csv, [ProductResource::class, 'importRow']);
    unlink($csv);

    expect($result['created'])->toBe(1)
        ->and($result['skipped'])->toBe(1);

    $product = Product::query()->where('sku', 'P-1')->first();
    expect($product->unit_id)->toBe($unit->getKey())
        ->and((string) $product->selling_price)->toBe('15.00');
});

it('exposes column headers used for export', function () {
    expect(array_keys(CustomerResource::csvColumns()))
        ->toBe(['Code', 'Name', 'Phone', 'Email', 'Address', 'Active']);
});
