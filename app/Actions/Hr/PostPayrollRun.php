<?php

namespace App\Actions\Hr;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\PayrollStatus;
use App\Exceptions\PayrollException;
use App\Models\PayrollRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts a payroll run's salary journal to the ledger (spec Phase 17). Books the
 * period's total net pay as Dr Salary Expense / Cr Employee Payable (settled later
 * by supplier-style payments to each employee). Idempotent — a run with a journal
 * already attached is rejected. Atomic.
 */
class PostPayrollRun
{
    public function __construct(
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(PayrollRun $run): PayrollRun
    {
        if ($run->status->isPostedToGl() || $run->journal_id !== null) {
            throw PayrollException::alreadyPosted();
        }

        return DB::transaction(function () use ($run): PayrollRun {
            $run->loadMissing('payslips', 'company');
            $companyId = (int) $run->company_id;
            $total = $run->netTotal();

            if ($total->isLessThanOrEqualTo(0)) {
                throw PayrollException::nothingToPost();
            }

            // The salary journal is dated the last day of the payroll month.
            $date = Carbon::create($run->year, $run->month, 1)->endOfMonth()->toDateString();

            $draft = JournalDraft::make(
                $date,
                memo: "Payroll {$run->periodLabel()}",
                reference: $run->name,
                source: $run,
            )
                ->debit($this->accounts->get('salary_expense', $companyId), $total)
                ->credit($this->accounts->get('employee_payable', $companyId), $total);

            $journal = $this->postJournal->handle($draft, $run->company);

            $run->update([
                'status' => PayrollStatus::Posted,
                'journal_id' => $journal->getKey(),
                'posted_at' => now(),
            ]);

            return $run->refresh();
        });
    }
}
