<?php

use App\Domain\Textile\MaterialRequirement;
use App\Models\Company;
use App\Models\DyeingSpecification;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function dyeCompany(): Company
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    return $company;
}

it('calculates a dyeing recipe (owf + g/L) from a dyeing specification', function () {
    dyeCompany();
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $dyed = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYED', 'name' => 'Dyed', 'cost_price' => 0, 'selling_price' => 0]);
    $dye = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYE', 'name' => 'Dye', 'cost_price' => 0, 'selling_price' => 0]);
    $salt = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'SALT', 'name' => 'Salt', 'cost_price' => 0, 'selling_price' => 0]);

    $spec = DyeingSpecification::create([
        'product_id' => $dyed->getKey(), 'dyeing_process' => 'reactive', 'liquor_ratio' => '1:8',
        'temperature' => 60, 'shade_percentage' => 3,
    ]);
    $spec->consumptions()->create(['product_id' => $dye->getKey(), 'basis' => 'percent_owf', 'rate' => 3, 'wastage_percent' => 0]);
    $spec->consumptions()->create(['product_id' => $salt->getKey(), 'basis' => 'g_per_litre', 'rate' => 40, 'wastage_percent' => 0]);

    $lines = MaterialRequirement::calculate($spec->consumptions()->with('product')->get(), '460', $spec->liquorFactor());

    expect((string) $lines[0]['required'])->toBe('13.8000')   // 460 × 3%
        ->and((string) $lines[1]['required'])->toBe('147.2000'); // 460 × 8 × 40 / 1000
});

it('renders the dyeing specification recipe sheet', function () {
    $company = dyeCompany();
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $dyed = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYED', 'name' => 'Dyed', 'cost_price' => 0, 'selling_price' => 0]);
    $spec = DyeingSpecification::create(['product_id' => $dyed->getKey(), 'dyeing_process' => 'reactive', 'colour' => 'Navy', 'liquor_ratio' => '1:8']);

    $this->actingAs(superAdminFor($company))
        ->get('/print/dyeing-specification/'.$spec->getKey())
        ->assertOk();
});
