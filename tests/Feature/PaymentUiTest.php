<?php

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\PartyLedger;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Livewire\Livewire;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: array<string, Account>} */
function paymentUiSetup(): array
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
        'receivable' => $make('1100', 'AR', AccountType::Asset),
        'payable' => $make('2000', 'AP', AccountType::Liability),
        'grni' => $make('2100', 'GRNI', AccountType::Liability),
        'sales' => $make('4000', 'Sales', AccountType::Revenue),
    ];

    return [$company, $accounts];
}

it('records a customer receipt from the payments screen', function () {
    [$company, $accounts] = paymentUiSetup();
    $this->actingAs(superAdminFor($company));
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);

    // Establish a receivable to collect against.
    app(PostJournal::class)->handle(
        JournalDraft::make('2026-06-01')
            ->debit($accounts['receivable'], '4200', party: $customer)
            ->credit($accounts['sales'], '4200'),
    );

    Livewire::test(ListPayments::class)
        ->callAction('recordReceipt', data: [
            'customer_id' => $customer->getKey(),
            'date' => '2026-06-05',
            'amount' => '2000',
            'method' => 'cash',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(Payment::query()->count())->toBe(1)
        ->and((string) PartyLedger::receivable($customer))->toBe('2200.00')
        ->and(Payment::query()->first()->journal_id)->not->toBeNull();
});

it('records a supplier payment from the payments screen', function () {
    [$company, $accounts] = paymentUiSetup();
    $this->actingAs(superAdminFor($company));
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);

    // Establish a payable to settle.
    app(PostJournal::class)->handle(
        JournalDraft::make('2026-06-01')
            ->debit($accounts['grni'], '3200')
            ->credit($accounts['payable'], '3200', party: $supplier),
    );

    Livewire::test(ListPayments::class)
        ->callAction('recordPayment', data: [
            'supplier_id' => $supplier->getKey(),
            'date' => '2026-06-06',
            'amount' => '1200',
            'method' => 'bank',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(Payment::query()->count())->toBe(1)
        ->and((string) PartyLedger::payable($supplier))->toBe('2000.00');
});
