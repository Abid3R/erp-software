<?php

namespace App\Models;

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ExpenseStatus $status
 * @property ExpensePaymentMethod $payment_method
 */
class Expense extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'expense_date', 'payment_method', 'supplier_id',
        'reference', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'payment_method' => ExpensePaymentMethod::class,
            'status' => ExpenseStatus::class,
        ];
    }

    /** @return HasMany<ExpenseLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseLine::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function total(): BigDecimal
    {
        return $this->lines->reduce(
            fn (BigDecimal $c, ExpenseLine $l): BigDecimal => $c->plus(BigDecimal::of($l->amount)),
            BigDecimal::zero(),
        );
    }
}
