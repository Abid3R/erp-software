<?php

use App\Enums\LabDipStatus;
use App\Models\Company;
use App\Models\DyeingSpecification;
use App\Models\LabDip;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('versions and approves a dyeing recipe', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $kg = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $dyed = Product::create(['unit_id' => $kg->getKey(), 'sku' => 'DYED', 'name' => 'Dyed', 'cost_price' => 0, 'selling_price' => 0]);

    $spec = DyeingSpecification::create([
        'product_id' => $dyed->getKey(), 'recipe_number' => 'R-100', 'version' => 2, 'dyeing_process' => 'reactive',
    ]);

    expect($spec->version)->toBe(2)
        ->and($spec->isApproved())->toBeFalse();

    $spec->update(['approval_status' => 'approved', 'approved_at' => now()]);
    expect($spec->refresh()->isApproved())->toBeTrue();
});

it('supports the Cancelled lab-dip status as terminal and not approved', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $dip = LabDip::create(['colour' => 'Navy', 'status' => LabDipStatus::Cancelled]);

    expect($dip->status)->toBe(LabDipStatus::Cancelled)
        ->and(LabDipStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(LabDipStatus::Cancelled->isApproved())->toBeFalse()
        ->and(LabDip::query()->approved()->count())->toBe(0);
});
