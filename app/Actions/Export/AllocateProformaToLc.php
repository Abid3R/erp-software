<?php

namespace App\Actions\Export;

use App\Enums\ProformaInvoiceStatus;
use App\Exceptions\ExportException;
use App\Models\LetterOfCredit;
use App\Models\ProformaInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Link (or unlink) a Proforma Invoice to a Letter of Credit and keep the LC's
 * derived utilisation status in sync. Blocks over-allocation — the sum of the
 * non-cancelled PI totals may not exceed the LC amount — unless an authorised
 * override is passed (spec: LC validation, PI ↔ LC connection).
 */
class AllocateProformaToLc
{
    /**
     * @param  bool  $allowOverride  Caller has verified the actor holds the override permission.
     */
    public function handle(ProformaInvoice $pi, LetterOfCredit $lc, bool $allowOverride = false): ProformaInvoice
    {
        if ($pi->status->isCancelled()) {
            throw ExportException::piCancelled();
        }
        if ($pi->status !== ProformaInvoiceStatus::Approved) {
            throw ExportException::piNotApproved();
        }
        if ($lc->status->isCancelled()) {
            throw ExportException::lcCancelled();
        }
        if ($lc->status->isExpired() || $lc->isPastExpiry()) {
            throw ExportException::lcExpired();
        }

        return DB::transaction(function () use ($pi, $lc, $allowOverride): ProformaInvoice {
            // Utilisation excluding this PI (it may already be linked to this LC).
            $lc->load('proformaInvoices.lines', 'proformaInvoices.taxRate');
            $allocatedOthers = $lc->proformaInvoices
                ->reject(fn (ProformaInvoice $other): bool => $other->status->isCancelled() || $other->is($pi))
                ->reduce(fn ($carry, ProformaInvoice $other) => $carry->plus($other->total()), \Brick\Math\BigDecimal::zero());

            $projected = $allocatedOthers->plus($pi->total());

            if (! $allowOverride && $projected->isGreaterThan($lc->amount)) {
                $remaining = \Brick\Math\BigDecimal::of($lc->amount)->minus($allocatedOthers);
                throw ExportException::lcOverAllocated((string) $remaining, (string) $pi->total());
            }

            $pi->update(['letter_of_credit_id' => $lc->getKey()]);

            // Refresh the LC's derived utilisation status.
            $lc->refresh()->load('proformaInvoices.lines', 'proformaInvoices.taxRate');
            $lc->update(['status' => $lc->utilisationStatus()]);

            return $pi->refresh();
        });
    }
}
