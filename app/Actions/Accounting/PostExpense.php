<?php

namespace App\Actions\Accounting;

use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\ExpenseStatus;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts an Expense voucher (spec: Accounts → Expenses): debits each expense-account
 * line and credits the settlement account — Cash, Bank, or Accounts Payable (tagged
 * with the supplier) for on-credit expenses. One balanced journal, atomically.
 */
class PostExpense
{
    public function __construct(
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(Expense $expense): Expense
    {
        if ($expense->status === ExpenseStatus::Posted) {
            throw ExpenseException::alreadyPosted();
        }
        if ($expense->payment_method->isOnCredit() && $expense->supplier_id === null) {
            throw ExpenseException::supplierRequiredForCredit();
        }

        return DB::transaction(function () use ($expense): Expense {
            $expense->loadMissing('lines.account', 'supplier', 'company');
            $companyId = (int) $expense->company_id;
            $date = Carbon::parse($expense->expense_date)->format('Y-m-d');

            $draft = JournalDraft::make(
                $date,
                memo: "Expense {$expense->number}",
                reference: $expense->reference,
                source: $expense,
            );

            $total = BigDecimal::zero();
            foreach ($expense->lines as $line) {
                $amount = BigDecimal::of($line->amount);
                if ($amount->isLessThanOrEqualTo(0)) {
                    continue;
                }
                $draft->debit($line->account, $amount);
                $total = $total->plus($amount);
            }

            if ($total->isLessThanOrEqualTo(0)) {
                throw ExpenseException::empty();
            }

            $creditAccount = $this->accounts->get($expense->payment_method->creditRole(), $companyId);
            $draft->credit($creditAccount, $total, party: $expense->supplier);

            $this->postJournal->handle($draft, $expense->company);

            $expense->update(['status' => ExpenseStatus::Posted]);

            return $expense->refresh();
        });
    }
}
