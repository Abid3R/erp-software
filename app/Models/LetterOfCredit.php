<?php

namespace App\Models;

use App\Enums\LetterOfCreditStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Letter of Credit — a bank instrument covering one or more Proforma Invoices.
 * Informational/financial control only; it never posts to the ledger. Utilisation
 * is DERIVED from the non-cancelled PIs linked to it, so allocated/remaining are
 * always in sync with the PIs and never stored redundantly.
 *
 * @property LetterOfCreditStatus $status
 * @property string $amount
 * @property string $exchange_rate
 */
class LetterOfCredit extends Model
{
    use Auditable;
    use BelongsToCompany;
    use HasDocuments;

    protected $table = 'letters_of_credit';

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'lc_date', 'customer_id', 'beneficiary', 'issuing_bank',
        'advising_bank', 'amount', 'currency_code', 'exchange_rate', 'issue_date', 'expiry_date',
        'latest_shipment_date', 'payment_terms', 'port_of_loading', 'port_of_discharge',
        'description', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lc_date' => 'date',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'latest_shipment_date' => 'date',
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'status' => LetterOfCreditStatus::class,
        ];
    }

    public static function booted(): void
    {
        static::creating(function (self $lc): void {
            if (empty($lc->number)) {
                $lc->number = DocumentNumber::next('letter_of_credit', 'LC-', static::query()->count());
            }
        });
    }

    /** Σ of the totals of all non-cancelled PIs linked to this LC (foreign currency). */
    public function allocated(): BigDecimal
    {
        $scale = (int) config('erp.currency.precision', 2);

        return $this->proformaInvoices
            ->reject(fn (ProformaInvoice $pi): bool => $pi->status->isCancelled())
            ->reduce(fn (BigDecimal $c, ProformaInvoice $pi): BigDecimal => $c->plus($pi->total()), BigDecimal::zero())
            ->toScale($scale, \Brick\Math\RoundingMode::HALF_UP);
    }

    /** LC amount still available for allocation. */
    public function remaining(): BigDecimal
    {
        $scale = (int) config('erp.currency.precision', 2);

        return BigDecimal::of($this->amount)->minus($this->allocated())
            ->toScale($scale, \Brick\Math\RoundingMode::HALF_UP);
    }

    /**
     * Utilisation-derived status. Returns FullyUtilized/PartiallyUtilized when the
     * LC is live and has allocations; otherwise leaves the user-set status as is
     * (Draft/Applied/Received/Confirmed/Expired/Cancelled).
     */
    public function utilisationStatus(): LetterOfCreditStatus
    {
        if (in_array($this->status, [LetterOfCreditStatus::Expired, LetterOfCreditStatus::Cancelled], true)) {
            return $this->status;
        }

        $allocated = $this->allocated();
        if ($allocated->isZero()) {
            return $this->status;
        }

        return $allocated->isGreaterThanOrEqualTo($this->amount)
            ? LetterOfCreditStatus::FullyUtilized
            : LetterOfCreditStatus::PartiallyUtilized;
    }

    /** True when the expiry date has passed. */
    public function isPastExpiry(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<ProformaInvoice, $this> */
    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class);
    }

    /** @return HasMany<CommercialInvoice, $this> */
    public function commercialInvoices(): HasMany
    {
        return $this->hasMany(CommercialInvoice::class);
    }

    /** @return HasMany<ExportShipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(ExportShipment::class);
    }
}
