<?php

use App\Domain\Reporting\VoucherRegister;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('lists supplier payment vouchers in the period and totals them', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);

    Payment::create([
        'company_id' => $company->getKey(), 'direction' => PaymentDirection::Payment,
        'party_type' => $supplier->getMorphClass(), 'party_id' => $supplier->getKey(),
        'date' => '2026-06-10', 'amount' => '12000', 'method' => PaymentMethod::Bank,
        'reference' => 'PV-1', 'idempotency_key' => 'VR-PAY-1',
    ]);
    Payment::create([
        'company_id' => $company->getKey(), 'direction' => PaymentDirection::Payment,
        'party_type' => $supplier->getMorphClass(), 'party_id' => $supplier->getKey(),
        'date' => '2026-06-20', 'amount' => '8000', 'method' => PaymentMethod::Cash,
        'reference' => 'PV-2', 'idempotency_key' => 'VR-PAY-2',
    ]);
    // Out of period — excluded.
    Payment::create([
        'company_id' => $company->getKey(), 'direction' => PaymentDirection::Payment,
        'party_type' => $supplier->getMorphClass(), 'party_id' => $supplier->getKey(),
        'date' => '2025-01-01', 'amount' => '999', 'method' => PaymentMethod::Cash,
        'idempotency_key' => 'VR-PAY-OLD',
    ]);
    // A receipt — must not appear in the payments register.
    $customer = Customer::create(['company_id' => $company->getKey(), 'name' => 'Buyer', 'code' => 'B1']);
    Payment::create([
        'company_id' => $company->getKey(), 'direction' => PaymentDirection::Receipt,
        'party_type' => $customer->getMorphClass(), 'party_id' => $customer->getKey(),
        'date' => '2026-06-15', 'amount' => '5000', 'method' => PaymentMethod::Cash,
        'idempotency_key' => 'VR-RCPT-X',
    ]);

    $reg = VoucherRegister::payments($company->getKey(), '2026-01-01', '2026-12-31');

    expect($reg['count'])->toBe(2)
        ->and($reg['total'])->toBe('20000.00')            // 12000 + 8000
        ->and($reg['rows'][0]['reference'])->toBe('PV-1')
        ->and($reg['rows'][0]['method'])->toBe('Bank');
});

it('lists customer receipt vouchers separately from payments', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $customer = Customer::create(['company_id' => $company->getKey(), 'name' => 'Buyer', 'code' => 'B1']);

    Payment::create([
        'company_id' => $company->getKey(), 'direction' => PaymentDirection::Receipt,
        'party_type' => $customer->getMorphClass(), 'party_id' => $customer->getKey(),
        'date' => '2026-07-01', 'amount' => '3000', 'method' => PaymentMethod::Cash,
        'idempotency_key' => 'VR-RCPT-1',
    ]);

    $receipts = VoucherRegister::receipts($company->getKey(), '2026-01-01', '2026-12-31');
    $payments = VoucherRegister::payments($company->getKey(), '2026-01-01', '2026-12-31');

    expect($receipts['count'])->toBe(1)
        ->and($receipts['total'])->toBe('3000.00')
        ->and($receipts['rows'][0]['party'])->toBe('Buyer')
        ->and($payments['count'])->toBe(0);
});

it('filters the voucher register by party', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $s1 = Supplier::factory()->create(['company_id' => $company->getKey()]);
    $s2 = Supplier::factory()->create(['company_id' => $company->getKey()]);

    Payment::create([
        'company_id' => $company->getKey(), 'direction' => PaymentDirection::Payment,
        'party_type' => $s1->getMorphClass(), 'party_id' => $s1->getKey(),
        'date' => '2026-06-10', 'amount' => '1000', 'method' => PaymentMethod::Cash, 'idempotency_key' => 'VR-F-1',
    ]);
    Payment::create([
        'company_id' => $company->getKey(), 'direction' => PaymentDirection::Payment,
        'party_type' => $s2->getMorphClass(), 'party_id' => $s2->getKey(),
        'date' => '2026-06-11', 'amount' => '2000', 'method' => PaymentMethod::Cash, 'idempotency_key' => 'VR-F-2',
    ]);

    $reg = VoucherRegister::payments($company->getKey(), null, null, (int) $s1->getKey());

    expect($reg['count'])->toBe(1)->and($reg['total'])->toBe('1000.00');
});
