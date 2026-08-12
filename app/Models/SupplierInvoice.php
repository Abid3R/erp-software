<?php

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SupplierInvoiceStatus $status
 */
class SupplierInvoice extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'supplier_id', 'purchase_order_id', 'invoice_date',
        'supplier_ref', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'status' => SupplierInvoiceStatus::class];
    }

    /** @return HasMany<SupplierInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** Net (tax-exclusive) total. */
    public function netTotal(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, SupplierInvoiceLine $l): BigDecimal => $c->plus(BigDecimal::of($l->amount)),
            BigDecimal::zero(),
        );
    }
}
