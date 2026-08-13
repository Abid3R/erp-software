<?php

namespace App\Models;

use App\Enums\OpeningBalanceLineType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property OpeningBalanceLineType $type
 * @property string|null $side
 * @property string|null $amount
 * @property string|null $quantity
 * @property string|null $unit_cost
 */
class OpeningBalanceLine extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'opening_balance_id', 'type', 'account_id', 'side',
        'customer_id', 'supplier_id', 'warehouse_id', 'product_id',
        'quantity', 'unit_cost', 'amount',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => OpeningBalanceLineType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<OpeningBalance, $this> */
    public function openingBalance(): BelongsTo
    {
        return $this->belongsTo(OpeningBalance::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
