<?php

namespace App\Models;

use App\Enums\PurchaseReturnStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PurchaseReturnStatus $status
 * @property \Illuminate\Support\Carbon $return_date
 */
class PurchaseReturn extends Model
{
    use BelongsToCompany;
    use HasDocuments;

    /** @var list<string> */
    protected $fillable = [
        'company_id', 'number', 'supplier_id', 'warehouse_id', 'return_date', 'status', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['return_date' => 'date', 'status' => PurchaseReturnStatus::class];
    }

    /** @return HasMany<PurchaseReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class);
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
}
