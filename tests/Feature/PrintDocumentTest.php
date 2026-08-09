<?php

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

function voucherFor(Company $company, string $partyName): Payment
{
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey(), 'name' => $partyName]);

    return Payment::create([
        'company_id' => $company->getKey(),
        'direction' => PaymentDirection::Payment,
        'party_type' => $supplier->getMorphClass(),
        'party_id' => $supplier->getKey(),
        'date' => '2026-06-01',
        'amount' => '1500',
        'method' => PaymentMethod::Cash,
        'idempotency_key' => 'PRINT-'.$partyName,
    ]);
}

it('lets a company member print a payment voucher with Bangla intact', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $payment = voucherFor($company, 'রহিম ট্রেডার্স');

    $user = User::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)->get(route('print.payment', $payment))
        ->assertOk()
        ->assertSee('Payment Voucher')
        ->assertSee('রহিম ট্রেডার্স')            // raw Bangla delivered to the browser
        ->assertSee('One thousand five hundred Taka only');
});

it('forbids printing another company\'s voucher', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $payment = voucherFor($company, 'Acme');
    app(CompanyContext::class)->forget();

    $outsider = User::factory()->create(); // authenticated but not a member

    $this->actingAs($outsider)->get(route('print.payment', $payment))->assertForbidden();
});
