<?php

use App\Filament\Resources\ProcessOrderResource;
use App\Models\Company;
use App\Models\LabDip;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\Unit;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function specCompany(): Company
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    return $company;
}

it('fills the fabric fields from a product specification master', function () {
    specCompany();
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    ProductSpecification::create([
        'product_id' => $grey->getKey(), 'fabric_composition' => '80% Cotton 20% Polyester',
        'gsm' => '280/290', 'fabric_width' => '72 Inch Open', 'colour' => 'Grey',
        'machine_diameter' => '30', 'gauge' => '24', 'stitch_length' => '4.1+5.0+0.5', 'yarn_count' => '30s',
    ]);

    $filled = [];
    $set = function (string $key, $value) use (&$filled): void {
        $filled[$key] = $value;
    };

    ProcessOrderResource::applyProductSpecification($grey->getKey(), $set);

    expect($filled['fabric_composition'])->toBe('80% Cotton 20% Polyester')
        ->and($filled['gsm'])->toBe('280/290')
        ->and($filled['fabric_width'])->toBe('72 Inch Open')
        ->and($filled['specifications.machine_diameter'])->toBe('30')
        ->and($filled['specifications.gauge'])->toBe('24')
        ->and($filled['specifications.stitch_length'])->toBe('4.1+5.0+0.5');
});

it('fills colour and dyeing parameters from an approved lab dip', function () {
    specCompany();
    $dip = LabDip::create([
        'colour' => 'Navy Blue', 'colour_ref' => 'Pantone 19-3832', 'dyeing_process' => 'reactive',
        'liquor_ratio' => '1:8', 'temperature' => 60, 'shade_percentage' => 3,
        'status' => \App\Enums\LabDipStatus::CustomerApproved,
    ]);

    $filled = [];
    $set = function (string $key, $value) use (&$filled): void {
        $filled[$key] = $value;
    };

    ProcessOrderResource::applyLabDip($dip->getKey(), $set);

    expect($filled['colour'])->toBe('Navy Blue')
        ->and($filled['colour_ref'])->toBe('Pantone 19-3832')
        ->and($filled['specifications.process'])->toBe('reactive')
        ->and($filled['specifications.liquor_ratio'])->toBe('1:8')
        ->and($filled['specifications.shade_percentage'])->toBe('3.000');
});

it('renders the spec sheet and lab-dip recipe print views', function () {
    $company = specCompany();
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $grey = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'GREY', 'name' => 'Grey', 'cost_price' => 0, 'selling_price' => 0]);
    $spec = ProductSpecification::create(['product_id' => $grey->getKey(), 'fabric_composition' => 'Cotton', 'gauge' => '24']);
    $dip = LabDip::create(['colour' => 'Navy', 'status' => \App\Enums\LabDipStatus::CustomerApproved]);

    $user = superAdminFor($company);
    $this->actingAs($user)->get('/print/fabric-specification/'.$spec->getKey())->assertOk();
    $this->actingAs($user)->get('/print/lab-dip/'.$dip->getKey())->assertOk();
});
