<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property QuotationStatus $status
 */
class Quotation extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'customer_id', 'warehouse_id', 'quote_date',
        'valid_until', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quote_date' => 'date', 'valid_until' => 'date', 'status' => QuotationStatus::class];
    }

    /** @return HasMany<QuotationLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
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

    public function total(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, QuotationLine $l): BigDecimal => $c->plus(BigDecimal::of($l->quantity)->multipliedBy($l->unit_price)),
            BigDecimal::zero(),
        );
    }
}
