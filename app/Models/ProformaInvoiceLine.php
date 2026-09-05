<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $quantity
 * @property string $unit_price
 */
class ProformaInvoiceLine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'proforma_invoice_id', 'product_id', 'description', 'quantity', 'unit_price',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
        ];
    }

    /** Line value = quantity × unit price (foreign currency). */
    public function amount(): BigDecimal
    {
        return BigDecimal::of($this->quantity)->multipliedBy($this->unit_price);
    }

    /** @return BelongsTo<ProformaInvoice, $this> */
    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
