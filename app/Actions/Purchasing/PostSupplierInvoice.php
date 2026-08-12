<?php

namespace App\Actions\Purchasing;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\SupplierInvoiceStatus;
use App\Exceptions\SupplierInvoiceException;
use App\Models\Account;
use App\Models\SupplierInvoice;
use App\Models\TaxRate;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Posts a Supplier Invoice (spec: Purchasing → Supplier Invoice, Phase 10). Each
 * line debits its account (GRNI clearing for received goods, or an expense), input
 * VAT is resolved from the configurable TaxRate in force on the invoice date, and
 * Accounts Payable is credited with the gross (net + VAT), tagged to the supplier.
 * One balanced journal, atomically.
 */
class PostSupplierInvoice
{
    public function __construct(
        private LedgerAccounts $accounts,
        private PostJournal $postJournal,
    ) {}

    public function handle(SupplierInvoice $invoice): SupplierInvoice
    {
        if ($invoice->status === SupplierInvoiceStatus::Posted) {
            throw SupplierInvoiceException::alreadyPosted();
        }

        return DB::transaction(function () use ($invoice): SupplierInvoice {
            $invoice->loadMissing('lines.account', 'supplier', 'company');
            $companyId = (int) $invoice->company_id;
            $date = Carbon::parse($invoice->invoice_date)->format('Y-m-d');

            $draft = JournalDraft::make(
                $date,
                memo: "Supplier invoice {$invoice->number}",
                reference: $invoice->supplier_ref,
                source: $invoice,
            );

            $net = BigDecimal::zero();
            /** @var array<int, array{account: Account, amount: BigDecimal}> $vatByAccount */
            $vatByAccount = [];

            foreach ($invoice->lines as $line) {
                $amount = BigDecimal::of($line->amount);
                if ($amount->isLessThanOrEqualTo(0)) {
                    continue;
                }
                $draft->debit($line->account, $amount);
                $net = $net->plus($amount);

                if ($line->tax_code === null) {
                    continue;
                }
                $rate = TaxRate::effective($line->tax_code, $date, $companyId);
                if ($rate === null) {
                    continue;
                }
                $tax = $rate->taxFor($amount);
                if ($tax->isLessThanOrEqualTo(0)) {
                    continue;
                }
                $account = $rate->inputAccount($this->accounts);
                $key = (int) $account->getKey();
                $vatByAccount[$key] ??= ['account' => $account, 'amount' => BigDecimal::zero()];
                $vatByAccount[$key]['amount'] = $vatByAccount[$key]['amount']->plus($tax);
            }

            if ($net->isLessThanOrEqualTo(0)) {
                throw SupplierInvoiceException::empty();
            }

            $gross = $net;
            foreach ($vatByAccount as $vat) {
                $draft->debit($vat['account'], $vat['amount']);
                $gross = $gross->plus($vat['amount']);
            }

            $draft->credit($this->accounts->get('payable', $companyId), $gross, party: $invoice->supplier);

            $this->postJournal->handle($draft, $invoice->company);

            $invoice->update(['status' => SupplierInvoiceStatus::Posted]);

            return $invoice->refresh();
        });
    }
}
