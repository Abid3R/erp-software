<?php

namespace App\Actions\Purchasing;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Models\Journal;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;

/**
 * Book a supplier invoice against previously-received goods (spec #20): clears the
 * GRNI accrual and recognises the payable to the supplier — Dr GRNI / Cr Accounts
 * Payable (tagged with the supplier for the AP subledger).
 */
class RecordSupplierInvoice
{
    public function __construct(
        private PostJournal $postJournal,
        private LedgerAccounts $accounts,
    ) {}

    public function handle(
        Supplier $supplier,
        BigDecimal|string|int $amount,
        string $date,
        ?Model $reference = null,
        ?string $documentNumber = null,
    ): Journal {
        $companyId = $supplier->getAttribute('company_id');
        $amt = BigDecimal::of($amount);

        $draft = JournalDraft::make($date, memo: "Supplier invoice: {$supplier->name}", reference: $documentNumber, source: $reference)
            ->debit($this->accounts->get('grni', $companyId), $amt)
            ->credit($this->accounts->get('payable', $companyId), $amt, party: $supplier);

        return $this->postJournal->handle($draft, $supplier->company);
    }
}
