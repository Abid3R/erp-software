<?php

namespace App\Models;

use App\Enums\DeliveryOrderStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property DeliveryOrderStatus $status
 */
class DeliveryOrder extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'sales_order_id', 'customer_id', 'warehouse_id',
        'delivery_date', 'delivery_address', 'vehicle_no', 'driver_name', 'driver_phone',
        'transporter', 'customer_reference', 'received_by', 'status', 'notes',
        // Optional export links (nullable) — a local DO leaves these empty.
        'proforma_invoice_id', 'letter_of_credit_id', 'commercial_invoice_id', 'export_shipment_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'status' => DeliveryOrderStatus::class];
    }

    /** @return HasMany<DeliveryOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryOrderLine::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<ProformaInvoice, $this> */
    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /** @return BelongsTo<LetterOfCredit, $this> */
    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class);
    }

    /** @return BelongsTo<CommercialInvoice, $this> */
    public function commercialInvoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class);
    }

    /** @return BelongsTo<ExportShipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ExportShipment::class, 'export_shipment_id');
    }

    public function totalQuantity(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, DeliveryOrderLine $l): BigDecimal => $c->plus(BigDecimal::of($l->quantity)),
            BigDecimal::zero(),
        );
    }
}
