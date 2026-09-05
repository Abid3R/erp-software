<?php

namespace App\Actions\Export;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\CommercialInvoiceStatus;
use App\Exceptions\ExportException;
use App\Models\CommercialInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Post a Commercial Invoice to the ledger — the one export document with a
 * financial impact. Books AR in the BASE currency (foreign total × exchange_rate):
 * Dr Receivable (tagged with the customer) / Cr Sales (+ Cr Output-VAT when taxed).
 * COGS is NOT posted here — the linked Delivery Order already booked it.
 *
 * Idempotent and safe against double-clicks: an already-posted or cancelled invoice
 * is rejected before anything is written, and the whole post is one transaction.
 */
class PostCommercialInvoice
{
    public function __construct(
        private PostJournal $postJournal,
        private LedgerAccounts $accounts,
    ) {}

    public function handle(CommercialInvoice $invoice): CommercialInvoice
    {
        if ($invoice->status === CommercialInvoiceStatus::Posted) {
            throw ExportException::alreadyPosted();
        }
        if ($invoice->status === CommercialInvoiceStatus::Cancelled) {
            throw ExportException::alreadyCancelled();
        }
        if ($invoice->status !== CommercialInvoiceStatus::Approved) {
            throw ExportException::notApproved();
        }

        $invoice->loadMissing('lines', 'taxRate', 'customer', 'letterOfCredit');

        // Block posting against a dead LC.
        $lc = $invoice->letterOfCredit;
        if ($lc !== null && ($lc->status->isCancelled() || $lc->status->isExpired() || $lc->isPastExpiry())) {
            throw ExportException::lcExpired();
        }

        $revenue = $invoice->toBase($invoice->taxableAmount());
        $tax = $invoice->toBase($invoice->taxAmount());
        $receivable = $revenue->plus($tax);

        if ($receivable->isLessThanOrEqualTo(0)) {
            throw ExportException::emptyInvoice();
        }

        return DB::transaction(function () use ($invoice, $revenue, $tax, $receivable): CommercialInvoice {
            $companyId = (int) $invoice->company_id;
            $date = Carbon::parse($invoice->invoice_date)->format('Y-m-d');

            $draft = JournalDraft::make(
                $date,
                memo: "Commercial invoice {$invoice->number}",
                reference: $invoice->number,
                source: $invoice,
            )
                ->debit($this->accounts->get('receivable', $companyId), $receivable, party: $invoice->customer)
                ->credit($this->accounts->get('sales', $companyId), $revenue);

            if ($tax->isPositive()) {
                $taxAccount = $invoice->taxRate?->outputAccount($this->accounts)
                    ?? $this->accounts->get('output_vat', $companyId);
                $draft->credit($taxAccount, $tax);
            }

            $journal = $this->postJournal->handle($draft, $invoice->company);

            $invoice->update([
                'status' => CommercialInvoiceStatus::Posted,
                'journal_id' => $journal->getKey(),
            ]);

            return $invoice->refresh();
        });
    }
}
