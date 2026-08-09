<?php

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Reporting\DashboardMetrics;
use App\Enums\AccountType;
use App\Enums\PeriodStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

it('computes dashboard metrics from the ledger', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);

    $make = fn (string $code, string $name, AccountType $type) => Account::create([
        'company_id' => $company->getKey(), 'code' => $code, 'name' => $name, 'type' => $type,
    ]);
    $inventory = $make('1200', 'Inventory', AccountType::Asset);
    $receivable = $make('1100', 'AR', AccountType::Asset);
    $payable = $make('2000', 'AP', AccountType::Liability);
    $sales = $make('4000', 'Sales', AccountType::Revenue);
    $cogs = $make('5000', 'COGS', AccountType::Expense);

    $post = app(PostJournal::class);
    $post->handle(JournalDraft::make('2026-06-01')->debit($inventory, '3200')->credit($payable, '3200'));
    $post->handle(JournalDraft::make('2026-06-02')->debit($receivable, '4200')->credit($sales, '4200'));
    $post->handle(JournalDraft::make('2026-06-02')->debit($cogs, '1000')->credit($inventory, '1000'));

    $m = app(DashboardMetrics::class)->for($company->getKey());

    expect((string) $m['revenue'])->toBe('4200.00')
        ->and((string) $m['expenses'])->toBe('1000.00')
        ->and((string) $m['net_profit'])->toBe('3200.00')
        ->and((string) $m['inventory_value'])->toBe('2200.00')   // 3200 - 1000
        ->and((string) $m['receivables'])->toBe('4200.00')
        ->and((string) $m['payables'])->toBe('3200.00');
});

it('renders the dashboard with the financial overview for an authorised user', function () {
    $company = Company::factory()->create();
    $user = superAdminFor($company); // widget is Shield-gated

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Net Profit');
});
