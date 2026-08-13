<?php

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\ProductResource;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;
use App\Support\CsvActions;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('imports customers — creating, updating by code, and skipping bad rows', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    Customer::create(['name' => 'Old Name', 'code' => 'C-1']);

    $csv = writeCsv([
        'Code,Name,Phone,Email,Address,Active',
        'C-1,Updated Name,,,,yes',    // updates the existing customer
        'C-2,New Customer,017,,,yes', // creates
        'C-9,,017,,,yes',             // skipped — has a code but no name
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

it('imports employees from a hand-made file with spaced headers and a BOM', function () {
    // Reproduces the real-world file that previously skipped every row: a UTF-8 BOM,
    // CRLF endings, and human-friendly headers ("Employee code", "First name", …)
    // that differ from the export template ("Code", "FirstName", …).
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $bom = "\xEF\xBB\xBF";
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents(
        $path,
        $bom."Employee code,First name,Last name,Email,Phone,Department,Designation,Employment type,Status,Join date,Base salary\r\n".
        "EMP-0001,Nayeem,Talukder,nayeem@example.com,01884996357,Planning,Technician,Contract,Active,2021-10-05,21500\r\n".
        "EMP-0002,Mahi,Karim,mahi@example.com,01474497391,Production,Operator,Permanent,Active,2026-02-25,21000\r\n"
    );

    $result = CsvActions::process($path, [EmployeeResource::class, 'importRow']);
    unlink($path);

    expect($result['created'])->toBe(2)
        ->and($result['skipped'])->toBe(0)
        ->and(Employee::query()->where('employee_code', 'EMP-0001')->first()->first_name)->toBe('Nayeem')
        ->and((string) Employee::query()->where('employee_code', 'EMP-0002')->first()->base_salary)->toBe('21000.00');
});

it('exposes column headers used for export', function () {
    expect(array_keys(CustomerResource::csvColumns()))
        ->toBe(['Code', 'Name', 'Phone', 'Email', 'Address', 'Active']);
});
