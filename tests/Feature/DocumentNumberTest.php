<?php

use App\Models\Company;
use App\Support\CompanyContext;
use App\Support\DocumentNumber;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('generates sequential, zero-padded numbers', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    expect(DocumentNumber::next('sales_order', 'SO-', 0))->toBe('SO-0001')
        ->and(DocumentNumber::next('sales_order', 'SO-', 0))->toBe('SO-0002')
        ->and(DocumentNumber::next('sales_order', 'SO-', 0))->toBe('SO-0003');
});

it('keeps separate counters per document type and per company', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();

    app(CompanyContext::class)->set($a);
    expect(DocumentNumber::next('sales_order', 'SO-', 0))->toBe('SO-0001')
        ->and(DocumentNumber::next('purchase_order', 'PO-', 0))->toBe('PO-0001');

    // A different company starts its own sequence from 1.
    app(CompanyContext::class)->set($b);
    expect(DocumentNumber::next('sales_order', 'SO-', 0))->toBe('SO-0001');
});

it('heals from the seed when rows were created ahead of the counter', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    // Counter is fresh, but 9 rows already exist (e.g. imported directly).
    expect(DocumentNumber::next('sales_order', 'SO-', 9))->toBe('SO-0010');
    // Subsequent calls continue from there regardless of the seed.
    expect(DocumentNumber::next('sales_order', 'SO-', 0))->toBe('SO-0011');
});

it('never repeats a number across many sequential calls', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $numbers = [];
    for ($i = 0; $i < 50; $i++) {
        $numbers[] = DocumentNumber::next('sales_order', 'SO-', 0);
    }

    expect($numbers)->toHaveCount(50)
        ->and(array_unique($numbers))->toHaveCount(50)
        ->and($numbers[49])->toBe('SO-0050');
});
