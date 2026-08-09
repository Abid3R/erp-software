<?php

use App\Actions\Accounting\PostJournal;
use App\Actions\Payments\RecordCustomerReceipt;
use App\Actions\Payments\RecordSupplierPayment;
use App\Actions\Purchasing\RecordSupplierInvoice;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\PartyLedger;
use App\Domain\Accounting\TrialBalance;
use App\Domain\Reporting\AccountBalances;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PeriodStatus;
use App\Exceptions\DuplicatePaymentException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * @return array{0: Company, 1: array<string, Account>}
 */
function paymentSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);

    $make = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $accounts = [
        'cash' => $make('1000', 'Cash', AccountType::Asset),
        'bank' => $make('1010', 'Bank', AccountType::Asset),
        'receivable' => $make('1100', 'Accounts Receivable', AccountType::Asset),
        'payable' => $make('2000', 'Accounts Payable', AccountType::Liability),
        'grni' => $make('2100', 'GRNI', AccountType::Liability),
        'sales' => $make('4000', 'Sales', AccountType::Revenue),
    ];

    return [$company, $accounts];
}

it('records a customer receipt that reduces the receivable', function () {
    [$company, $accounts] = paymentSetup();
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);

    // Sale on credit to this customer creates the receivable.
    app(PostJournal::class)->handle(
        JournalDraft::make('2026-06-01')
            ->debit($accounts['receivable'], '4200', party: $customer)
            ->credit($accounts['sales'], '4200'),
    );
    expect((string) PartyLedger::receivable($customer))->toBe('4200.00');

    $payment = app(RecordCustomerReceipt::class)->handle($customer, '2000', PaymentMethod::Cash, '2026-06-05', 'RCPT-1');

    expect((string) PartyLedger::receivable($customer))->toBe('2200.00')
        ->and($payment->journal_id)->not->toBeNull()
        ->and((string) AccountBalances::netForAccount($accounts['cash']))->toBe('2000.00')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('books a supplier invoice then a payment against the payable', function () {
    [$company] = paymentSetup();
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);

    app(RecordSupplierInvoice::class)->handle($supplier, '3200', '2026-06-02');
    expect((string) PartyLedger::payable($supplier))->toBe('3200.00');

    app(RecordSupplierPayment::class)->handle($supplier, '1200', PaymentMethod::Bank, '2026-06-06', 'PAY-1');

    expect((string) PartyLedger::payable($supplier))->toBe('2000.00')
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('prevents duplicate payment processing (idempotency key)', function () {
    [$company] = paymentSetup();
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);

    app(RecordCustomerReceipt::class)->handle($customer, '500', PaymentMethod::Cash, '2026-06-05', 'DUP-1');

    expect(fn () => app(RecordCustomerReceipt::class)->handle($customer, '500', PaymentMethod::Cash, '2026-06-05', 'DUP-1'))
        ->toThrow(DuplicatePaymentException::class);
    expect(Payment::query()->count())->toBe(1);
});
