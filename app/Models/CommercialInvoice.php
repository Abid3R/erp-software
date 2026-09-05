<?php

namespace App\Models;

use App\Enums\CommercialInvoiceStatus;
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
 * Commercial Invoice — the legal export invoice billed to the buyer. This is the
 * one export document that posts to the ledger: on posting it books AR in the base
 * currency (foreign total × exchange_rate). COGS is not posted here; the linked
 * Delivery Order already booked it when goods left the warehouse.
 *
 * @property CommercialInvoiceStatus $status
 * @property string $discount
 * @property string $exchange_rate
 */
class CommercialInvoice extends Model
{
    use Auditable;
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'invoice_date', 'customer_id', 'sales_order_id', 'proforma_invoice_id',
        'delivery_order_id', 'letter_of_credit_id', 'export_shipment_id', 'consignee', 'buyer',
        'country_of_origin', 'destination_country', 'currency_code', 'exchange_rate', 'incoterm',
        'payment_terms', 'discount', 'tax_rate_id', 'status', 'journal_id', 'terms', 'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'discount' => 'decimal:4',
            'status' => CommercialInvoiceStatus::class,
        ];
    }

    public static function booted(): void
    {
        static::creating(function (self $ci): void {
            if (empty($ci->number)) {
                $ci->number = DocumentNumber::next('commercial_invoice', 'CI-', static::query()->count());
            }
        });
    }

    private function scale(): int
    {
        return (int) config('erp.currency.precision', 2);
    }

    /** Σ line amounts (foreign currency). */
    public function subtotal(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, CommercialInvoiceLine $l): BigDecimal => $c->plus($l->amount()),
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

    /** Invoice total in its own (foreign) currency. */
    public function total(): BigDecimal
    {
        return $this->taxableAmount()->plus($this->taxAmount());
    }

    /** A foreign-currency amount converted to the base (BDT) currency for the GL. */
    public function toBase(BigDecimal $foreign): BigDecimal
    {
        return $foreign->multipliedBy($this->exchange_rate)->toScale($this->scale(), RoundingMode::HALF_UP);
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

    /** @return BelongsTo<ProformaInvoice, $this> */
    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /** @return BelongsTo<DeliveryOrder, $this> */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    /** @return BelongsTo<LetterOfCredit, $this> */
    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class);
    }

    /** @return BelongsTo<ExportShipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ExportShipment::class, 'export_shipment_id');
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /** @return HasMany<CommercialInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CommercialInvoiceLine::class);
    }

    /** @return HasMany<PackingList, $this> */
    public function packingLists(): HasMany
    {
        return $this->hasMany(PackingList::class);
    }
}
