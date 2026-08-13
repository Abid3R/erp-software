<?php

namespace App\Actions\Accounting;

use App\Actions\Inventory\ReceiveStock;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\InventoryTransactionType;
use App\Enums\OpeningBalanceLineType;
use App\Enums\OpeningBalanceStatus;
use App\Exceptions\OpeningBalanceException;
use App\Models\OpeningBalance;
use App\Models\OpeningBalanceLine;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts an Opening Balances document (spec: onboarding — Phase 26/27). Every line is
 * booked against the Opening Balance Equity contra so the entry is always balanced:
 * GL/AR/stock as debits, AP as a credit. Stock also creates an Opening inventory
 * movement via {@see ReceiveStock}. One journal, atomically.
 */
class PostOpeningBalances
{
    public function __construct(
        private LedgerAccounts $accounts,
        private ReceiveStock $receiveStock,
        private PostJournal $postJournal,
    ) {}

    public function handle(OpeningBalance $opening): OpeningBalance
    {
        if ($opening->status === OpeningBalanceStatus::Posted) {
            throw OpeningBalanceException::alreadyPosted();
        }

        return DB::transaction(function () use ($opening): OpeningBalance {
            $opening->loadMissing('lines.account', 'lines.customer', 'lines.supplier', 'lines.warehouse', 'lines.product');
            $companyId = (int) $opening->company_id;
            $company = $opening->company;
            $date = Carbon::parse($opening->as_of_date)->format('Y-m-d');
            $equity = $this->accounts->get('opening_balance_equity', $companyId);

            $draft = JournalDraft::make($date, memo: "Opening balances {$opening->number}", source: $opening);
            $posted = 0;

            foreach ($opening->lines as $line) {
                $posted += $this->applyLine($draft, $line, $opening, $companyId, $equity->getKey());
            }

            if ($posted === 0) {
                throw OpeningBalanceException::empty();
            }

            $this->postJournal->handle($draft, $company);
            $opening->update(['status' => OpeningBalanceStatus::Posted]);

            return $opening->refresh();
        });
    }

    /** Adds the balanced legs for one line; returns 1 if it contributed, else 0. */
    private function applyLine(JournalDraft $draft, OpeningBalanceLine $line, OpeningBalance $opening, int $companyId, int $equityId): int
    {
        switch ($line->type) {
            case OpeningBalanceLineType::Gl:
                $amount = BigDecimal::of($line->amount ?? '0');
                if ($line->account_id === null || $amount->isLessThanOrEqualTo(0)) {
                    return 0;
                }
                if ($line->side === 'credit') {
                    $draft->debit($equityId, $amount)->credit($line->account_id, $amount);
                } else {
                    $draft->debit($line->account_id, $amount)->credit($equityId, $amount);
                }

                return 1;

            case OpeningBalanceLineType::Ar:
                $amount = BigDecimal::of($line->amount ?? '0');
                if ($line->customer === null || $amount->isLessThanOrEqualTo(0)) {
                    return 0;
                }
                $draft->debit($this->accounts->get('receivable', $companyId), $amount, party: $line->customer)
                    ->credit($equityId, $amount);

                return 1;

            case OpeningBalanceLineType::Ap:
                $amount = BigDecimal::of($line->amount ?? '0');
                if ($line->supplier === null || $amount->isLessThanOrEqualTo(0)) {
                    return 0;
                }
                $draft->debit($equityId, $amount)
                    ->credit($this->accounts->get('payable', $companyId), $amount, party: $line->supplier);

                return 1;

            case OpeningBalanceLineType::Stock:
                $qty = BigDecimal::of($line->quantity ?? '0');
                if ($line->warehouse === null || $line->product === null || $qty->isLessThanOrEqualTo(0)) {
                    return 0;
                }
                $txn = $this->receiveStock->handle(
                    $line->warehouse, $line->product, (string) $qty, (string) ($line->unit_cost ?? '0'),
                    InventoryTransactionType::Opening, $opening,
                );
                $value = BigDecimal::of($txn->total_cost);
                if ($value->isPositive()) {
                    $draft->debit($this->accounts->get('inventory', $companyId), $value)->credit($equityId, $value);
                }

                return 1;
        }
    }
}
