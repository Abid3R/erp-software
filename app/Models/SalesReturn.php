<?php

namespace App\Models;

use App\Enums\SalesReturnStatus;
use App\Models\Concerns\BelongsToCompany;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SalesReturnStatus $status
 */
class SalesReturn extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'customer_id', 'warehouse_id', 'return_date', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['return_date' => 'date', 'status' => SalesReturnStatus::class];
    }

    /** @return HasMany<SalesReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesReturnLine::class);
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
            fn (BigDecimal $c, SalesReturnLine $l): BigDecimal => $c->plus(BigDecimal::of($l->quantity)->multipliedBy($l->unit_price)),
            BigDecimal::zero(),
        );
    }
}
