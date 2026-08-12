<?php

use App\Domain\Accounting\LedgerAccounts;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Company;
use App\Models\TaxRate;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function taxCompany(): Company
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    return $company;
}

it('computes tax on a tax-exclusive base', function () {
    taxCompany();
    $vat = TaxRate::create(['code' => 'VAT15', 'name' => 'VAT 15%', 'rate_percent' => '15', 'effective_from' => '2026-01-01']);

    expect((string) $vat->taxFor('1000'))->toBe('150.00'); // 1000 × 15%
});

it('extracts tax embedded in a tax-inclusive amount', function () {
    taxCompany();
    $vat = TaxRate::create(['code' => 'VAT15', 'name' => 'VAT 15%', 'rate_percent' => '15', 'is_inclusive' => true, 'effective_from' => '2026-01-01']);

    // 1150 gross at 15% inclusive → 150 tax (1150 × 15 / 115).
    expect((string) $vat->taxFor('1150'))->toBe('150.00');
});

it('resolves the rate effective on a given date', function () {
    $company = taxCompany();
    TaxRate::create(['code' => 'VAT', 'name' => 'Old 10%', 'rate_percent' => '10', 'effective_from' => '2020-01-01', 'effective_to' => '2025-12-31']);
    TaxRate::create(['code' => 'VAT', 'name' => 'New 15%', 'rate_percent' => '15', 'effective_from' => '2026-01-01']);

    expect(TaxRate::effective('VAT', '2024-06-01', $company->getKey())?->rate_percent)->toBe('10.0000')
        ->and(TaxRate::effective('VAT', '2026-06-01', $company->getKey())?->rate_percent)->toBe('15.0000')
        ->and(TaxRate::effective('VAT', '2019-01-01', $company->getKey()))->toBeNull();
});

it('ignores inactive rates when resolving', function () {
    $company = taxCompany();
    TaxRate::create(['code' => 'VAT', 'name' => 'Disabled', 'rate_percent' => '15', 'effective_from' => '2020-01-01', 'is_active' => false]);

    expect(TaxRate::effective('VAT', '2026-06-01', $company->getKey()))->toBeNull();
});

it('falls back to the config VAT accounts when none is set on the rate', function () {
    $company = taxCompany();
    $input = Account::create(['company_id' => $company->getKey(), 'code' => '1250', 'name' => 'Input VAT', 'type' => AccountType::Asset]);
    $output = Account::create(['company_id' => $company->getKey(), 'code' => '2200', 'name' => 'Output VAT', 'type' => AccountType::Liability]);
    $vat = TaxRate::create(['code' => 'VAT15', 'name' => 'VAT 15%', 'rate_percent' => '15', 'effective_from' => '2026-01-01']);

    $accounts = app(LedgerAccounts::class);
    expect($vat->inputAccount($accounts)->is($input))->toBeTrue()
        ->and($vat->outputAccount($accounts)->is($output))->toBeTrue();
});
