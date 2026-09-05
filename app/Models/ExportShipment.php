<?php

namespace App\Models;

use App\Enums\ExportShipmentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Export Shipment — the physical consignment that ties the whole export chain
 * together (order, PI, LC, commercial invoice, packing list, delivery order).
 * Operational only: no ledger or stock impact of its own.
 *
 * @property ExportShipmentStatus $status
 */
class ExportShipment extends Model
{
    use Auditable;
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'shipment_date', 'customer_id', 'sales_order_id', 'proforma_invoice_id',
        'letter_of_credit_id', 'commercial_invoice_id', 'delivery_order_id', 'port_of_loading',
        'port_of_discharge', 'vessel_flight', 'container_no', 'seal_no', 'freight_forwarder',
        'bl_awb_no', 'status', 'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'shipment_date' => 'date',
            'status' => ExportShipmentStatus::class,
        ];
    }

    public static function booted(): void
    {
        static::creating(function (self $shipment): void {
            if (empty($shipment->number)) {
                $shipment->number = DocumentNumber::next('export_shipment', 'SHP-', static::query()->count());
            }
        });
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

    /** @return BelongsTo<DeliveryOrder, $this> */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    /** @return HasMany<PackingList, $this> */
    public function packingLists(): HasMany
    {
        return $this->hasMany(PackingList::class);
    }
}
