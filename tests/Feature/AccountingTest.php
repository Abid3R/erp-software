<?php

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\TrialBalance;
use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\PeriodClosedException;
use App\Exceptions\PostingException;
use App\Exceptions\UnbalancedJournalException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Journal;
use App\Support\CompanyContext;

beforeEach(fn () => app(CompanyContext::class)->forget());
afterEach(fn () => app(CompanyContext::class)->forget());

/**
 * @return array{0: Company, 1: Account, 2: Account}
 */
function accountingSetup(): array
{
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    AccountingPeriod::create([
        'company_id' => $company->getKey(), 'name' => 'FY2026', 'fiscal_year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => PeriodStatus::Open,
    ]);

    $cash = Account::create(['company_id' => $company->getKey(), 'code' => '1000', 'name' => 'Cash', 'type' => AccountType::Asset]);
    $sales = Account::create(['company_id' => $company->getKey(), 'code' => '4000', 'name' => 'Sales', 'type' => AccountType::Revenue]);

    return [$company, $cash, $sales];
}

it('posts a balanced journal', function () {
    [$company, $cash, $sales] = accountingSetup();

    $journal = app(PostJournal::class)->handle(
        JournalDraft::make('2026-06-01', memo: 'Cash sale')
            ->debit($cash, '1000.00')
            ->credit($sales, '1000.00'),
    );

    expect($journal->status)->toBe(JournalStatus::Posted)
        ->and($journal->lines)->toHaveCount(2)
        ->and($journal->isBalanced())->toBeTrue()
        ->and(TrialBalance::isBalanced($company->getKey()))->toBeTrue();
});

it('rejects an unbalanced journal and writes nothing', function () {
    [, $cash, $sales] = accountingSetup();

    $draft = JournalDraft::make('2026-06-01')->debit($cash, '1000')->credit($sales, '900');

    expect(fn () => app(PostJournal::class)->handle($draft))
        ->toThrow(UnbalancedJournalException::class);
    expect(Journal::query()->count())->toBe(0);
});

it('rejects a journal with fewer than two lines', function () {
    [, $cash] = accountingSetup();

    $draft = JournalDraft::make('2026-06-01')->debit($cash, '100');

    expect(fn () => app(PostJournal::class)->handle($draft))
        ->toThrow(PostingException::class);
});

it('rejects posting into a closed period', function () {
    [$company, $cash, $sales] = accountingSetup();
    AccountingPeriod::query()->where('company_id', $company->getKey())->update(['status' => 'closed']);

    $draft = JournalDraft::make('2026-06-01')->debit($cash, '100')->credit($sales, '100');

    expect(fn () => app(PostJournal::class)->handle($draft))
        ->toThrow(PeriodClosedException::class);
});

it('rejects posting to an inactive or non-postable account', function () {
    [, $cash, $sales] = accountingSetup();
    $sales->update(['is_postable' => false]);

    $draft = JournalDraft::make('2026-06-01')->debit($cash, '100')->credit($sales, '100');

    expect(fn () => app(PostJournal::class)->handle($draft))
        ->toThrow(PostingException::class);
});

it('forbids modifying or deleting a posted journal (immutability)', function () {
    [, $cash, $sales] = accountingSetup();
    $journal = app(PostJournal::class)->handle(
        JournalDraft::make('2026-06-01')->debit($cash, '100')->credit($sales, '100'),
    );

    expect(fn () => $journal->update(['memo' => 'tampered']))->toThrow(PostingException::class);
    expect(fn () => $journal->delete())->toThrow(PostingException::class);
});

it('keeps the trial balance balanced across multiple postings', function () {
    [$company, $cash, $sales] = accountingSetup();
    $post = app(PostJournal::class);

    $post->handle(JournalDraft::make('2026-06-01')->debit($cash, '1000')->credit($sales, '1000'));
    $post->handle(JournalDraft::make('2026-06-02')->debit($cash, '250.50')->credit($sales, '250.50'));

    $totals = TrialBalance::totals($company->getKey());
    expect($totals['debit']->isEqualTo($totals['credit']))->toBeTrue()
        ->and((string) $totals['debit'])->toBe('1250.50');
});
