<?php

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Reporting\BalanceSheet;
use App\Domain\Reporting\GeneralLedger;
use App\Domain\Reporting\ProfitAndLoss;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * Owner invests 5000; buys 2000 inventory; sells for 1500 cash (cost 1000).
 *
 * @return array{0: Company, 1: array<string, Account>}
 */
function reportingScenario(): array
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
        'inventory' => $make('1200', 'Inventory', AccountType::Asset),
        'capital' => $make('3000', 'Share Capital', AccountType::Equity),
        'sales' => $make('4000', 'Sales', AccountType::Revenue),
        'cogs' => $make('5000', 'COGS', AccountType::Expense),
    ];

    $post = app(PostJournal::class);
    $post->handle(JournalDraft::make('2026-06-01', 'Capital')->debit($accounts['cash'], '5000')->credit($accounts['capital'], '5000'));
    $post->handle(JournalDraft::make('2026-06-02', 'Buy stock')->debit($accounts['inventory'], '2000')->credit($accounts['cash'], '2000'));
    $post->handle(JournalDraft::make('2026-06-03', 'Sale')->debit($accounts['cash'], '1500')->credit($accounts['sales'], '1500'));
    $post->handle(JournalDraft::make('2026-06-03', 'Cost')->debit($accounts['cogs'], '1000')->credit($accounts['inventory'], '1000'));

    return [$company, $accounts];
}

it('computes profit and loss from the ledger', function () {
    [$company] = reportingScenario();

    $pl = ProfitAndLoss::for($company->getKey());

    expect((string) $pl['revenue'])->toBe('1500.00')
        ->and((string) $pl['expenses'])->toBe('1000.00')
        ->and((string) $pl['net_profit'])->toBe('500.00');
});

it('produces a balanced balance sheet (A = L + E + retained earnings)', function () {
    [$company] = reportingScenario();

    $bs = BalanceSheet::for($company->getKey());

    expect((string) $bs['assets'])->toBe('5500.00')          // cash 4500 + inventory 1000
        ->and((string) $bs['liabilities'])->toBe('0.00')
        ->and((string) $bs['equity'])->toBe('5000.00')       // share capital
        ->and((string) $bs['retained_earnings'])->toBe('500.00')
        ->and($bs['balanced'])->toBeTrue();
});

it('produces a general ledger with a running balance', function () {
    [, $accounts] = reportingScenario();

    $gl = GeneralLedger::forAccount($accounts['cash']);

    expect($gl['lines'])->toHaveCount(3)
        ->and((string) $gl['closing'])->toBe('4500.00')       // +5000 -2000 +1500
        ->and($gl['lines'][0]['running'])->toBe('5000.00')
        ->and($gl['lines'][1]['running'])->toBe('3000.00')
        ->and($gl['lines'][2]['running'])->toBe('4500.00');
});
