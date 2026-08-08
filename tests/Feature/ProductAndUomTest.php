<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;
use Illuminate\Database\QueryException;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function pieceAndCarton(Company $company): array
{
    $piece = Unit::create([
        'company_id' => $company->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1,
    ]);
    $carton = Unit::create([
        'company_id' => $company->getKey(), 'base_unit_id' => $piece->getKey(),
        'name' => 'Carton', 'code' => 'CTN', 'factor' => 24,
    ]);

    return [$piece, $carton];
}

it('converts units with exact decimal math (1 carton = 24 pieces)', function () {
    $company = Company::factory()->create();
    [$piece, $carton] = pieceAndCarton($company);

    expect((string) $carton->toBase(2))->toBe('48.000000')
        ->and((string) $carton->convertTo($piece, 2))->toBe('48.0000')
        ->and((string) $piece->convertTo($carton, 48))->toBe('2.0000');
});

it('refuses to convert between units with different bases', function () {
    $company = Company::factory()->create();
    [, $carton] = pieceAndCarton($company);
    $kilogram = Unit::create([
        'company_id' => $company->getKey(), 'name' => 'Kilogram', 'code' => 'KG', 'factor' => 1,
    ]);

    expect(fn () => $carton->convertTo($kilogram, 1))
        ->toThrow(InvalidArgumentException::class);
});

it('allows the same SKU in different companies but not within one', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    $unitA = Unit::create(['company_id' => $a->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);
    $unitB = Unit::create(['company_id' => $b->getKey(), 'name' => 'Piece', 'code' => 'PCS', 'factor' => 1]);

    $make = fn (Company $c, Unit $u) => Product::create([
        'company_id' => $c->getKey(), 'unit_id' => $u->getKey(), 'sku' => 'SHARED-1',
        'name' => 'Widget', 'cost_price' => 10, 'selling_price' => 20,
    ]);

    $make($a, $unitA);
    $make($b, $unitB);            // same SKU, different company — allowed

    expect(Product::withoutGlobalScopes()->where('sku', 'SHARED-1')->count())->toBe(2);

    // Duplicate within the same company is rejected by the unique constraint.
    expect(fn () => $make($a, $unitA))->toThrow(QueryException::class);
});

it('scopes products to the active company', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    Product::factory()->create(['company_id' => $a->getKey(), 'unit_id' => Unit::factory()->create(['company_id' => $a->getKey()])->getKey(), 'sku' => 'A-1']);
    Product::factory()->create(['company_id' => $b->getKey(), 'unit_id' => Unit::factory()->create(['company_id' => $b->getKey()])->getKey(), 'sku' => 'B-1']);

    app(CompanyContext::class)->set($a);

    expect(Product::query()->pluck('sku')->all())->toBe(['A-1']);
});
