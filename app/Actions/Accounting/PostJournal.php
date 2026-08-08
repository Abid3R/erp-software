<?php

namespace App\Actions\Accounting;

use App\Domain\Accounting\JournalDraft;
use App\Enums\JournalStatus;
use App\Exceptions\PeriodClosedException;
use App\Exceptions\PostingException;
use App\Exceptions\UnbalancedJournalException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Journal;
use App\Support\CompanyContext;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Validate and post a journal, atomically (spec #12, #26). Enforces, before any
 * write: at least two lines; debits == credits; every account exists, belongs to
 * the company, is active and postable; and the date falls in an open period.
 * The result is an immutable posted journal.
 */
class PostJournal
{
    public function __construct(private CompanyContext $context) {}

    public function handle(JournalDraft $draft, ?Company $company = null): Journal
    {
        $companyId = $company?->getKey() ?? $this->context->currentId();

        if ($companyId === null) {
            throw new PostingException('No company context for posting.');
        }

        $this->assertStructure($draft);
        $this->assertAccounts($draft, $companyId);
        $period = $this->resolveOpenPeriod($companyId, $draft->date);

        $moneyScale = (int) config('erp.currency.precision', 2);

        return DB::transaction(function () use ($draft, $companyId, $period, $moneyScale) {
            $journal = Journal::query()->create([
                'company_id' => $companyId,
                'accounting_period_id' => $period->getKey(),
                'date' => $draft->date,
                'reference' => $draft->reference,
                'memo' => $draft->memo,
                'status' => JournalStatus::Posted,
                'source_type' => $draft->source?->getMorphClass(),
                'source_id' => $draft->source?->getKey(),
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            foreach ($draft->lines as $line) {
                $journal->lines()->create([
                    'company_id' => $companyId,
                    'account_id' => $line['account_id'],
                    'debit' => (string) $line['debit']->toScale($moneyScale, RoundingMode::HALF_UP),
                    'credit' => (string) $line['credit']->toScale($moneyScale, RoundingMode::HALF_UP),
                    'memo' => $line['memo'],
                ]);
            }

            return $journal->load('lines');
        });
    }

    private function assertStructure(JournalDraft $draft): void
    {
        if (count($draft->lines) < 2) {
            throw new PostingException('A journal must have at least two lines.');
        }

        if (! $draft->isBalanced()) {
            throw UnbalancedJournalException::make(
                (string) $draft->totalDebit(),
                (string) $draft->totalCredit(),
            );
        }
    }

    private function assertAccounts(JournalDraft $draft, int $companyId): void
    {
        $ids = $draft->accountIds();

        /** @var \Illuminate\Support\Collection<int, Account> $accounts */
        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $account = $accounts->get($id);

            if ($account === null) {
                throw new PostingException("Account {$id} is missing or belongs to another company.");
            }
            if (! $account->canReceivePostings()) {
                throw new PostingException("Account {$account->code} is inactive or not postable.");
            }
        }
    }

    private function resolveOpenPeriod(int $companyId, string $date): AccountingPeriod
    {
        $on = Carbon::parse($date)->toDateString();

        $period = AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $on)
            ->whereDate('end_date', '>=', $on)
            ->first();

        if ($period === null || ! $period->acceptsPostings()) {
            throw PeriodClosedException::forDate($on);
        }

        return $period;
    }
}
