<?php

namespace App\Models;

use App\Enums\ProformaInvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use App\Support\DocumentNumber;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proforma Invoice — a pre-shipment commercial document sent to the buyer (often
 * to open an LC). Pulls its lines from a Sales Order and may be allocated to one
 * Letter of Credit. Informational: no inventory or ledger impact. Amounts are in
 * the document's own currency; total() drives LC allocation.
 *
 * @property ProformaInvoiceStatus $status
 * @property string $discount
 * @property string $exchange_rate
 */
class ProformaInvoice extends Model
{
    use Auditable;
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'pi_date', 'customer_id', 'sales_order_id', 'letter_of_credit_id',
        'warehouse_id', 'currency_code', 'exchange_rate', 'payment_terms', 'incoterm',
        'delivery_terms', 'discount', 'tax_rate_id', 'status', 'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pi_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'discount' => 'decimal:4',
            'status' => ProformaInvoiceStatus::class,
        ];
    }

    public static function booted(): void
    {
        static::creating(function (self $pi): void {
            if (empty($pi->number)) {
                $pi->number = DocumentNumber::next('proforma_invoice', 'PI-', static::query()->count());
            }
        });
    }

    private function scale(): int
    {
        return (int) config('erp.currency.precision', 2);
    }

    /** Σ line amounts (quantity × unit price), foreign currency. */
    public function subtotal(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, ProformaInvoiceLine $l): BigDecimal => $c->plus($l->amount()),
            BigDecimal::zero(),
        )->toScale($this->scale(), RoundingMode::HALF_UP);
    }

    public function discountAmount(): BigDecimal
    {
        return BigDecimal::of($this->discount ?? 0)->toScale($this->scale(), RoundingMode::HALF_UP);
    }

    /** Taxable base = subtotal − discount. */
    public function taxableAmount(): BigDecimal
    {
        return $this->subtotal()->minus($this->discountAmount());
    }

    public function taxAmount(): BigDecimal
    {
        $rate = $this->taxRate;

        return $rate !== null
            ? $rate->taxFor($this->taxableAmount(), $this->scale())
            : BigDecimal::zero()->toScale($this->scale());
    }

    /** Document total = taxable + tax (foreign currency). Drives LC allocation. */
    public function total(): BigDecimal
    {
        return $this->taxableAmount()->plus($this->taxAmount());
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<LetterOfCredit, $this> */
    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** @return HasMany<ProformaInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProformaInvoiceLine::class);
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
