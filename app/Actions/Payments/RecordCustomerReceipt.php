<?php

namespace App\Actions\Payments;

use App\Actions\Accounting\PostJournal;
use App\Domain\Accounting\JournalDraft;
use App\Domain\Accounting\LedgerAccounts;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Exceptions\DuplicatePaymentException;
use App\Models\Customer;
use App\Models\Payment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Record a customer receipt: Dr Cash/Bank / Cr Accounts Receivable (tagged with
 * the customer), reducing their outstanding balance (spec #23). Idempotent — a
 * repeated key is rejected before anything is written, and the DB unique
 * constraint is the race-safe backstop (spec #23, #26).
 */
class RecordCustomerReceipt
{
    public function __construct(
        private PostJournal $postJournal,
        private LedgerAccounts $accounts,
    ) {}

    public function handle(
        Customer $customer,
        BigDecimal|string|int $amount,
        PaymentMethod $method,
        string $date,
        string $idempotencyKey,
        ?string $documentNumber = null,
        ?string $note = null,
    ): Payment {
        $companyId = $customer->getAttribute('company_id');

        $this->assertNotDuplicate($companyId, $idempotencyKey);

        $amount = BigDecimal::of($amount)->toScale((int) config('erp.currency.precision', 2), RoundingMode::HALF_UP);

        return DB::transaction(function () use ($customer, $companyId, $amount, $method, $date, $idempotencyKey, $documentNumber, $note) {
            $payment = Payment::query()->create([
                'company_id' => $companyId,
                'direction' => PaymentDirection::Receipt,
                'party_type' => $customer->getMorphClass(),
                'party_id' => $customer->getKey(),
                'date' => $date,
                'amount' => (string) $amount,
                'method' => $method,
                'reference' => $documentNumber,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
                'created_by' => Auth::id(),
            ]);

            $draft = JournalDraft::make($date, memo: "Receipt: {$customer->name}", reference: $documentNumber, source: $payment)
                ->debit($this->accounts->get($method->accountRole(), $companyId), $amount)
                ->credit($this->accounts->get('receivable', $companyId), $amount, party: $customer);

            $journal = $this->postJournal->handle($draft, $customer->company);
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
