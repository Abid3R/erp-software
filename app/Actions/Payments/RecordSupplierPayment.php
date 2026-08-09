<?php

namespace App\Actions\Payments;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Exceptions\DuplicatePaymentException;
use App\Models\Payment;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Record a payment to a supplier: Dr Accounts Payable (tagged with the supplier) /
 * Cr Cash/Bank, reducing what we owe them (spec #23). Idempotent, like receipts.
 */
class RecordSupplierPayment
{
    public function __construct(
        private PostJournal $postJournal,
        private LedgerAccounts $accounts,
    ) {}

    public function handle(
        Supplier $supplier,
        BigDecimal|string|int $amount,
        PaymentMethod $method,
        string $date,
        string $idempotencyKey,
        ?string $documentNumber = null,
        ?string $note = null,
    ): Payment {
        $companyId = $supplier->getAttribute('company_id');

        $this->assertNotDuplicate($companyId, $idempotencyKey);

        $amount = BigDecimal::of($amount)->toScale((int) config('erp.currency.precision', 2), RoundingMode::HALF_UP);

        return DB::transaction(function () use ($supplier, $companyId, $amount, $method, $date, $idempotencyKey, $documentNumber, $note) {
            $payment = Payment::query()->create([
                'company_id' => $companyId,
                'direction' => PaymentDirection::Payment,
                'party_type' => $supplier->getMorphClass(),
                'party_id' => $supplier->getKey(),
                'date' => $date,
                'amount' => (string) $amount,
                'method' => $method,
                'reference' => $documentNumber,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'created_by' => Auth::id(),
            ]);

            $draft = JournalDraft::make($date, memo: "Payment: {$supplier->name}", reference: $documentNumber, source: $payment)
                ->debit($this->accounts->get('payable', $companyId), $amount, party: $supplier)
                ->credit($this->accounts->get($method->accountRole(), $companyId), $amount);

            $journal = $this->postJournal->handle($draft, $supplier->company);
            $payment->update(['journal_id' => $journal->getKey()]);

            return $payment;
        });
    }

    private function assertNotDuplicate(int $companyId, string $key): void
    {
        $exists = Payment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->exists();

        if ($exists) {
            throw DuplicatePaymentException::forKey($key);
        }
    }
}
