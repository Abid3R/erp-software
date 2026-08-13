<?php

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\PartyLedger;
use App\Domain\Reporting\PartyAging;
use App\Domain\Reporting\PartyAgingDetail;
use App\Domain\Reporting\PartyStatement;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/** @return array{0: Company, 1: Customer, 2: array<string, Account>} */
function agingSetup(): array
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
        'receivable' => $make('1100', 'AR', AccountType::Asset),
        'sales' => $make('4000', 'Sales', AccountType::Revenue),
    ];
    $customer = Customer::factory()->create(['company_id' => $company->getKey()]);

    return [$company, $customer, $accounts];
}

it('buckets receivables by age after FIFO-applying receipts, reconciling to the ledger', function () {
    [$company, $customer, $accounts] = agingSetup();

    // Old invoice (Jan) and a recent one (Jun); a partial receipt applies FIFO to Jan.
    app(PostJournal::class)->handle(JournalDraft::make('2026-01-01')
        ->debit($accounts['receivable'], '1000', party: $customer)->credit($accounts['sales'], '1000'));
    app(PostJournal::class)->handle(JournalDraft::make('2026-06-01')
        ->debit($accounts['receivable'], '500', party: $customer)->credit($accounts['sales'], '500'));
    app(PostJournal::class)->handle(JournalDraft::make('2026-06-10')
        ->debit($accounts['cash'], '300')->credit($accounts['receivable'], '300', party: $customer));

    $aging = PartyAging::receivables($company->getKey(), '2026-06-30');
    $row = $aging['rows'][0];

    expect($row['current'])->toBe('500.00')   // Jun invoice, 29 days old
        ->and($row['d90plus'])->toBe('700.00') // Jan invoice minus 300 receipt, ~180 days
        ->and($row['total'])->toBe('1200.00')
        ->and($aging['totals']['total'])->toBe('1200.00')
        // Reconciles with the party ledger / GL control account.
        ->and((string) PartyLedger::receivable($customer))->toBe('1200.00');
});

it('omits fully settled parties from the aging', function () {
    [$company, $customer, $accounts] = agingSetup();
    app(PostJournal::class)->handle(JournalDraft::make('2026-03-01')
        ->debit($accounts['receivable'], '400', party: $customer)->credit($accounts['sales'], '400'));
    app(PostJournal::class)->handle(JournalDraft::make('2026-03-05')
        ->debit($accounts['cash'], '400')->credit($accounts['receivable'], '400', party: $customer));

    $aging = PartyAging::receivables($company->getKey(), '2026-06-30');

    expect($aging['rows'])->toHaveCount(0)
        ->and($aging['totals']['total'])->toBe('0.00');
});

it('reports invoice-level receivables with payments applied FIFO to the oldest', function () {
    [$company, $customer, $accounts] = agingSetup();
    app(PostJournal::class)->handle(JournalDraft::make('2026-01-01', memo: 'INV-Jan')
        ->debit($accounts['receivable'], '1000', party: $customer)->credit($accounts['sales'], '1000'));
    app(PostJournal::class)->handle(JournalDraft::make('2026-06-01', memo: 'INV-Jun')
        ->debit($accounts['receivable'], '500', party: $customer)->credit($accounts['sales'], '500'));
    app(PostJournal::class)->handle(JournalDraft::make('2026-06-10')
        ->debit($accounts['cash'], '300')->credit($accounts['receivable'], '300', party: $customer));

    $detail = PartyAgingDetail::receivables($company->getKey(), '2026-06-30');

    expect($detail['rows'])->toHaveCount(2);
    [$jan, $jun] = $detail['rows']; // sorted by party then date
    expect($jan['document'])->toBe('INV-Jan')
        ->and($jan['original'])->toBe('1000.00')->and($jan['paid'])->toBe('300.00')
        ->and($jan['outstanding'])->toBe('700.00')->and($jan['bucket'])->toBe('d90plus')
        ->and($jun['original'])->toBe('500.00')->and($jun['paid'])->toBe('0.00')
        ->and($jun['outstanding'])->toBe('500.00')->and($jun['bucket'])->toBe('current')
        ->and($detail['totals']['total'])->toBe('1200.00')
        // reconciles with the party ledger.
        ->and((string) PartyLedger::receivable($customer))->toBe('1200.00');
});

it('reports a supplier invoice in payables aging and clears it when fully paid', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);
    $mk = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $cash = $mk('1000', 'Cash', AccountType::Asset);
    $grni = $mk('2100', 'GRNI', AccountType::Liability);
    $payable = $mk('2000', 'AP', AccountType::Liability);
    $supplier = Supplier::factory()->create(['company_id' => $company->getKey()]);

    // Supplier invoice 42,000 (Dr GRNI / Cr AP), then a partial payment of 12,000.
    app(PostJournal::class)->handle(JournalDraft::make('2026-06-01', memo: 'Supplier invoice SINV-1')
        ->debit($grni, '42000')->credit($payable, '42000', party: $supplier));
    app(PostJournal::class)->handle(JournalDraft::make('2026-06-05')
        ->debit($payable, '12000', party: $supplier)->credit($cash, '12000'));

    $detail = PartyAgingDetail::payables($company->getKey(), '2026-06-30');
    expect($detail['rows'])->toHaveCount(1)
        ->and($detail['rows'][0]['document'])->toBe('Supplier invoice SINV-1')
        ->and($detail['rows'][0]['original'])->toBe('42000.00')
        ->and($detail['rows'][0]['paid'])->toBe('12000.00')
        ->and($detail['rows'][0]['outstanding'])->toBe('30000.00')
        ->and($detail['totals']['total'])->toBe('30000.00')
        ->and((string) PartyLedger::payable($supplier))->toBe('30000.00');

    // Pay the balance — the invoice drops off the aging (nothing outstanding).
    app(PostJournal::class)->handle(JournalDraft::make('2026-06-06')
        ->debit($payable, '30000', party: $supplier)->credit($cash, '30000'));

    expect(PartyAgingDetail::payables($company->getKey(), '2026-06-30')['rows'])->toHaveCount(0)
        ->and((string) PartyLedger::payable($supplier))->toBe('0.00');
});

it('builds a customer statement with a running balance', function () {
    [, $customer, $accounts] = agingSetup();
    app(PostJournal::class)->handle(JournalDraft::make('2026-01-01')
        ->debit($accounts['receivable'], '1000', party: $customer)->credit($accounts['sales'], '1000'));
    app(PostJournal::class)->handle(JournalDraft::make('2026-06-10')
        ->debit($accounts['cash'], '300')->credit($accounts['receivable'], '300', party: $customer));

    $statement = PartyStatement::forCustomer($customer);

    expect($statement['opening'])->toBe('0.00')
        ->and($statement['closing'])->toBe('700.00')
        ->and($statement['lines'])->toHaveCount(2)
        ->and($statement['lines'][0]['running'])->toBe('1000.00')
        ->and($statement['lines'][1]['running'])->toBe('700.00');
});
