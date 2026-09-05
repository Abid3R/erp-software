<?php

use App\Actions\Export\AllocateProformaToLc;
use App\Enums\LetterOfCreditStatus;
use App\Enums\ProformaInvoiceStatus;
use App\Exceptions\ExportException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LetterOfCredit;
use App\Models\ProformaInvoice;
use App\Models\Product;
use App\Models\Unit;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** A company with a customer + one product, and an approved PI factory. */
function lcSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $unit = Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);
    $product = Product::create(['unit_id' => $unit->getKey(), 'sku' => 'FAB-1', 'name' => 'Fabric', 'cost_price' => 0, 'selling_price' => 0]);
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);

    $makePi = function (float $amount, ProformaInvoiceStatus $status = ProformaInvoiceStatus::Approved) use ($company, $customer, $product): ProformaInvoice {
        $pi = ProformaInvoice::create([
            'company_id' => $company->getKey(), 'pi_date' => now(), 'customer_id' => $customer->getKey(),
            'currency_code' => 'USD', 'exchange_rate' => 110, 'status' => $status,
        ]);
        $pi->lines()->create(['product_id' => $product->getKey(), 'quantity' => 1, 'unit_price' => $amount]);

        return $pi->refresh()->load('lines');
    };

    $lc = LetterOfCredit::create([
        'company_id' => $company->getKey(), 'lc_date' => now(), 'customer_id' => $customer->getKey(),
        'amount' => 100000, 'currency_code' => 'USD', 'exchange_rate' => 110,
        'expiry_date' => now()->addMonths(3), 'status' => LetterOfCreditStatus::Confirmed,
    ]);

    return [$company, $lc, $makePi];
}

it('links many PIs to one LC and tracks allocated / remaining', function () {
    [, $lc, $makePi] = lcSetup();
    $allocate = app(AllocateProformaToLc::class);

    $allocate->handle($makePi(40000), $lc);
    $allocate->handle($makePi(35000), $lc);

    $lc->refresh()->load('proformaInvoices.lines');
    expect((string) $lc->allocated())->toBe('75000.00')
        ->and((string) $lc->remaining())->toBe('25000.00')
        ->and($lc->utilisationStatus())->toBe(LetterOfCreditStatus::PartiallyUtilized);
});

it('marks the LC fully utilised when allocation reaches the amount', function () {
    [, $lc, $makePi] = lcSetup();
    $allocate = app(AllocateProformaToLc::class);

    $allocate->handle($makePi(60000), $lc);
    $allocate->handle($makePi(40000), $lc);

    $lc->refresh()->load('proformaInvoices.lines');
    expect((string) $lc->remaining())->toBe('0.00')
        ->and($lc->fresh()->status)->toBe(LetterOfCreditStatus::FullyUtilized);
});

it('blocks allocation that would exceed the LC amount', function () {
    [, $lc, $makePi] = lcSetup();
    $allocate = app(AllocateProformaToLc::class);

    $allocate->handle($makePi(90000), $lc);

    $allocate->handle($makePi(20000), $lc);
})->throws(ExportException::class);

it('allows over-allocation only with an authorised override', function () {
    [, $lc, $makePi] = lcSetup();
    $allocate = app(AllocateProformaToLc::class);

    $allocate->handle($makePi(90000), $lc);
    $allocate->handle($makePi(20000), $lc, allowOverride: true);

    $lc->refresh()->load('proformaInvoices.lines');
    expect((string) $lc->allocated())->toBe('110000.00')
        ->and($lc->remaining()->isNegative())->toBeTrue();
});

it('excludes a cancelled PI from LC utilisation', function () {
    [, $lc, $makePi] = lcSetup();
    $allocate = app(AllocateProformaToLc::class);

    $pi = $makePi(40000);
    $allocate->handle($pi, $lc);
    $pi->update(['status' => ProformaInvoiceStatus::Cancelled]);

    $lc->refresh()->load('proformaInvoices.lines');
    expect((string) $lc->allocated())->toBe('0.00');
});

it('refuses to allocate to an expired LC', function () {
    [$company, $lc, $makePi] = lcSetup();
    $lc->update(['expiry_date' => now()->subDay()]);

    app(AllocateProformaToLc::class)->handle($makePi(1000), $lc->refresh());
})->throws(ExportException::class);

it('refuses to allocate a non-approved PI', function () {
    [, $lc, $makePi] = lcSetup();

    app(AllocateProformaToLc::class)->handle($makePi(1000, ProformaInvoiceStatus::Draft), $lc);
})->throws(ExportException::class);
