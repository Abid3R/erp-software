<?php

use App\Actions\Accounting\DepreciateAssets;
use App\Enums\AccountType;
use App\Enums\FixedAssetStatus;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function assetCompany(): Company
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    Account::create(['company_id' => $company->getKey(), 'code' => '5300', 'name' => 'Depreciation Expense', 'type' => AccountType::Expense]);
    Account::create(['company_id' => $company->getKey(), 'code' => '1600', 'name' => 'Accumulated Depreciation', 'type' => AccountType::Asset]);

    return $company;
}

it('computes straight-line monthly depreciation', function () {
    assetCompany();
    $asset = FixedAsset::create([
        'asset_code' => 'A1', 'name' => 'Machine', 'acquisition_date' => '2026-01-01',
        'acquisition_cost' => 100000, 'salvage_value' => 10000, 'useful_life_months' => 36, 'status' => 'active',
    ]);

    expect((string) $asset->monthlyDepreciation())->toBe('2500.00')  // 90000 / 36
        ->and((string) $asset->netBookValue())->toBe('100000.00');
});

it('posts monthly depreciation to the ledger and is idempotent per period', function () {
    $company = assetCompany();
    $asset = FixedAsset::create([
        'asset_code' => 'A1', 'name' => 'Machine', 'acquisition_date' => '2026-01-01',
        'acquisition_cost' => 100000, 'salvage_value' => 10000, 'useful_life_months' => 36, 'status' => 'active',
    ]);

    $result = app(DepreciateAssets::class)->handle($company, 2026, 1);
    expect($result['count'])->toBe(1)
        ->and($result['total'])->toBe('2500.00')
        ->and((string) $asset->fresh()->accumulated_depreciation)->toBe('2500.00')
        ->and($asset->depreciationEntries()->count())->toBe(1)
        ->and(Journal::query()->count())->toBe(1); // Dr Dep. Expense / Cr Accum. Dep.

    // Re-running the same period changes nothing (idempotent).
    $again = app(DepreciateAssets::class)->handle($company, 2026, 1);
    expect($again['count'])->toBe(0)
        ->and((string) $asset->fresh()->accumulated_depreciation)->toBe('2500.00');

    // The next month adds another charge.
    app(DepreciateAssets::class)->handle($company, 2026, 2);
    expect((string) $asset->fresh()->accumulated_depreciation)->toBe('5000.00');
});

it('caps at the depreciable base and marks the asset fully depreciated', function () {
    $company = assetCompany();
    $asset = FixedAsset::create([
        'asset_code' => 'A2', 'name' => 'Tool', 'acquisition_date' => '2026-01-01',
        'acquisition_cost' => 5000, 'salvage_value' => 0, 'useful_life_months' => 2, 'status' => 'active',
    ]); // base 5000, monthly 2500

    app(DepreciateAssets::class)->handle($company, 2026, 1);
    app(DepreciateAssets::class)->handle($company, 2026, 2);

    expect((string) $asset->fresh()->accumulated_depreciation)->toBe('5000.00')
        ->and($asset->fresh()->status)->toBe(FixedAssetStatus::FullyDepreciated);

    // A third month posts nothing.
    $result = app(DepreciateAssets::class)->handle($company, 2026, 3);
    expect($result['count'])->toBe(0);
});
