<?php

use App\Domain\Textile\MaterialRequirement;
use App\Enums\ConsumptionBasis;
use App\Models\Company;
use App\Models\LabDip;
use App\Models\Product;
use App\Models\RecipeConsumption;
use App\Models\Unit;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function recipeCompany(): Company
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    return $company;
}

function material(string $sku): Product
{
    $kg = Unit::firstOrCreate(['code' => 'KG'], ['name' => 'Kilogram', 'factor' => 1]);

    return Product::create(['unit_id' => $kg->getKey(), 'sku' => $sku, 'name' => $sku, 'cost_price' => 0, 'selling_price' => 0]);
}

it('calculates per-unit yarn with wastage against the order quantity', function () {
    recipeCompany();
    $yarn = material('YARN');
    // 1.0 kg yarn per kg fabric + 5% knitting loss. Order 400 kg → 420 kg.
    $c = new RecipeConsumption([
        'product_id' => $yarn->getKey(), 'basis' => ConsumptionBasis::PerUnit, 'rate' => 1, 'wastage_percent' => 5,
    ]);
    $c->setRelation('product', $yarn);

    $lines = MaterialRequirement::calculate([$c], '400');

    expect($lines)->toHaveCount(1)
        ->and((string) $lines[0]['required'])->toBe('420.0000')
        ->and($lines[0]['product_id'])->toBe($yarn->getKey());
});

it('calculates dye as % on weight of fabric (owf)', function () {
    recipeCompany();
    $dye = material('DYE');
    // 3% owf, no wastage. 460 kg fabric → 13.8 kg dye.
    $c = new RecipeConsumption([
        'product_id' => $dye->getKey(), 'basis' => ConsumptionBasis::PercentOwf, 'rate' => 3, 'wastage_percent' => 0,
    ]);
    $c->setRelation('product', $dye);

    $lines = MaterialRequirement::calculate([$c], '460');

    expect((string) $lines[0]['required'])->toBe('13.8000');
});

it('calculates chemical as grams per litre against the dye-bath volume', function () {
    recipeCompany();
    $salt = material('SALT');
    // 40 g/L salt, liquor ratio 1:8 → 8 L/kg. 460 kg fabric → 3680 L → 147.2 kg salt.
    $c = new RecipeConsumption([
        'product_id' => $salt->getKey(), 'basis' => ConsumptionBasis::GramsPerLitre, 'rate' => 40, 'wastage_percent' => 0,
    ]);
    $c->setRelation('product', $salt);

    $lines = MaterialRequirement::calculate([$c], '460', LabDip::parseLiquorFactor('1:8'));

    expect((string) $lines[0]['required'])->toBe('147.2000');
});

it('parses liquor ratio into litres per kg', function () {
    expect(LabDip::parseLiquorFactor('1:8'))->toBe(8.0)
        ->and(LabDip::parseLiquorFactor('1:6.5'))->toBe(6.5)
        ->and(LabDip::parseLiquorFactor('2:16'))->toBe(8.0)
        ->and(LabDip::parseLiquorFactor('7'))->toBe(7.0)
        ->and(LabDip::parseLiquorFactor(''))->toBeNull()
        ->and(LabDip::parseLiquorFactor(null))->toBeNull();
});

it('yields zero for a g/L chemical when no liquor ratio is given', function () {
    recipeCompany();
    $salt = material('SALT2');
    $c = new RecipeConsumption([
        'product_id' => $salt->getKey(), 'basis' => ConsumptionBasis::GramsPerLitre, 'rate' => 40, 'wastage_percent' => 0,
    ]);
    $c->setRelation('product', $salt);

    $lines = MaterialRequirement::calculate([$c], '460', null);

    expect((string) $lines[0]['required'])->toBe('0.0000');
});
