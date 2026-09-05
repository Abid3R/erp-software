<?php

namespace App\Actions\Export;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Enums\CommercialInvoiceStatus;
use App\Exceptions\ExportException;
use App\Models\CommercialInvoice;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a Commercial Invoice. A draft/approved invoice is simply marked
 * cancelled. A posted invoice is reversed with a mirror journal (posted journals
 * are immutable — corrections are made by reversal, never by editing) that backs
 * out the AR, sales and output-VAT lines, then marked cancelled.
 */
class CancelCommercialInvoice
{
    public function __construct(private PostJournal $postJournal) {}

    public function handle(CommercialInvoice $invoice, ?string $reason = null): CommercialInvoice
    {
        if ($invoice->status === CommercialInvoiceStatus::Cancelled) {
            throw ExportException::alreadyCancelled();
        }

        return DB::transaction(function () use ($invoice, $reason): CommercialInvoice {
            $invoice->loadMissing('journal.lines');
            $journal = $invoice->journal;

            // Reverse the original posting by swapping debits and credits.
            if ($invoice->status === CommercialInvoiceStatus::Posted && $journal !== null) {
                $date = Carbon::parse($invoice->invoice_date)->format('Y-m-d');
                $draft = JournalDraft::make(
                    $date,
                    memo: 'Reversal — commercial invoice '.$invoice->number.($reason !== null ? ' ('.$reason.')' : ''),
                    reference: $invoice->number,
                    source: $invoice,
                );

                foreach ($journal->lines as $line) {
                    $debit = BigDecimal::of($line->debit);
                    $credit = BigDecimal::of($line->credit);
                    $party = $line->party_id !== null ? $invoice->customer : null;

                    if ($debit->isPositive()) {
                        $draft->credit($line->account_id, $debit, party: $party);
                    }
                    if ($credit->isPositive()) {
                        $draft->debit($line->account_id, $credit, party: $party);
                    }
                }

                $this->postJournal->handle($draft, $invoice->company);
            }

            $invoice->update(['status' => CommercialInvoiceStatus::Cancelled]);

            return $invoice->refresh();
        });
    }
}
